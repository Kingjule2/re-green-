"""Land-analysis endpoint.

Accepts an aerial/drone image (multipart upload) plus optional survey
coordinates, and returns a land-cover perception together with the environmental
context around the point: terrain from a DEM, soil properties from SoilGrids,
and climate normals from NASA POWER. Heavy lifting lives in `app.land_analysis`,
`app.terrain_sources`, `app.soil` and `app.climate`; this router stays thin:
validate the upload, run the active model and the point samplers, and map the
results onto the response schema.
"""

from __future__ import annotations

import asyncio
from collections.abc import Awaitable, Callable
from typing import Annotated

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile

from app.climate import ClimateSample, sample_climate
from app.land_analysis import ImageDecodeError, get_model
from app.sampling import SampleReport, SourceInfo
from app.schemas import LandAnalysisResponse
from app.security import require_api_key
from app.soil import SoilSample, sample_soil
from app.terrain_sources import TerrainSample, sample_terrain

router = APIRouter(tags=["land-analysis"], dependencies=[Depends(require_api_key)])

# Mirror the Laravel-side 50 MB upload limit.
MAX_BYTES = 50 * 1024 * 1024

# Reported for every context block when the survey carried no coordinates.
NO_COORDINATES = "no survey coordinates were supplied with the image"


@router.post("/land-analysis", response_model=LandAnalysisResponse)
async def analyze_land(
    image: Annotated[UploadFile, File()],
    latitude: Annotated[float | None, Form()] = None,
    longitude: Annotated[float | None, Form()] = None,
) -> dict:
    """Analyze a single drone image and return land cover plus environmental context."""
    contents = await image.read()

    if len(contents) == 0:
        raise HTTPException(status_code=422, detail="The uploaded image is empty.")

    if len(contents) > MAX_BYTES:
        raise HTTPException(status_code=413, detail="Maximum file size is 50 MB.")

    _validate_coordinates(latitude, longitude)

    # Each point sample is a network round trip, so they run alongside the image
    # model instead of after it. The model itself runs in a worker thread so a
    # heavier segmentation network cannot stall the event loop.
    context = [
        asyncio.create_task(_sample(sample_terrain, latitude, longitude)),
        asyncio.create_task(_sample(sample_soil, latitude, longitude)),
        asyncio.create_task(_sample(sample_climate, latitude, longitude)),
    ]

    try:
        perception = await asyncio.to_thread(get_model().analyze, contents)
    except ImageDecodeError as exc:
        for task in context:
            task.cancel()
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    terrain, soil, climate = await asyncio.gather(*context)

    perception["terrain"] = _terrain_payload(terrain)
    perception["soil"] = _soil_payload(soil)
    perception["climate"] = _climate_payload(climate)

    return perception


def _validate_coordinates(latitude: float | None, longitude: float | None) -> None:
    if (latitude is None) != (longitude is None):
        raise HTTPException(
            status_code=422, detail="latitude and longitude must be provided together."
        )

    if latitude is not None and not -90.0 <= latitude <= 90.0:
        raise HTTPException(status_code=422, detail="latitude must be between -90 and 90.")

    if longitude is not None and not -180.0 <= longitude <= 180.0:
        raise HTTPException(status_code=422, detail="longitude must be between -180 and 180.")


async def _sample[T](
    sampler: Callable[[float, float], Awaitable[SampleReport[T]]],
    latitude: float | None,
    longitude: float | None,
) -> SampleReport[T]:
    """Sample one context, or explain that there was no point to sample."""
    if latitude is None or longitude is None:
        return SampleReport(available=False, reason=NO_COORDINATES)

    return await sampler(latitude, longitude)


def _source_payload(source: SourceInfo) -> dict:
    return {
        "id": source.id,
        "label": source.label,
        "dataset": source.dataset,
        "provider": source.provider,
        "service_url": source.service_url,
        "portal_url": source.portal_url,
    }


def _point_payload(latitude: float, longitude: float) -> dict:
    return {"latitude": round(latitude, 6), "longitude": round(longitude, 6)}


def _terrain_payload(report: SampleReport[TerrainSample]) -> dict:
    """Map the terrain domain objects onto the API schema."""
    if not report.available or report.sample is None:
        return {
            "available": False,
            "reason": report.reason,
            "source": None,
            "sampled_at": None,
            "profile": None,
        }

    sample = report.sample
    metrics = sample.metrics

    return {
        "available": True,
        "reason": None,
        "source": _source_payload(sample.source),
        "sampled_at": _point_payload(sample.latitude, sample.longitude),
        "profile": {
            "elevation_m": metrics.elevation_m,
            "slope_deg": metrics.slope_deg,
            "aspect_deg": metrics.aspect_deg,
            "hillshade": metrics.hillshade,
            "ruggedness_m": metrics.ruggedness_m,
            "resolution_m": sample.resolution_m,
            "window": {
                "rows": sample.window_rows,
                "cols": sample.window_cols,
                "spacing_x_m": sample.window_spacing_x_m,
                "spacing_y_m": sample.window_spacing_y_m,
            },
        },
    }


def _soil_payload(report: SampleReport[SoilSample]) -> dict:
    """Map the soil domain objects onto the API schema."""
    if not report.available or report.sample is None:
        return {
            "available": False,
            "reason": report.reason,
            "source": None,
            "sampled_at": None,
            "depth_cm": None,
            "resolution_m": None,
            "profile": None,
        }

    sample = report.sample
    profile = sample.profile

    def texture(payload) -> dict:
        return {
            "class_name": payload.class_name,
            "sand_pct": payload.sand_pct,
            "silt_pct": payload.silt_pct,
            "clay_pct": payload.clay_pct,
        }

    return {
        "available": True,
        "reason": None,
        "source": _source_payload(sample.source),
        "sampled_at": _point_payload(sample.latitude, sample.longitude),
        "depth_cm": sample.depth_cm,
        "resolution_m": sample.resolution_m,
        "profile": {
            "texture": texture(profile.texture),
            "subsoil_texture": texture(profile.subsoil_texture),
            "ph": profile.ph,
            "organic_carbon_g_kg": profile.organic_carbon_g_kg,
            "nitrogen_g_kg": profile.nitrogen_g_kg,
        },
    }


def _climate_payload(report: SampleReport[ClimateSample]) -> dict:
    """Map the climate domain objects onto the API schema."""
    if not report.available or report.sample is None:
        return {
            "available": False,
            "reason": report.reason,
            "source": None,
            "sampled_at": None,
            "profile": None,
        }

    sample = report.sample
    normals = sample.normals

    return {
        "available": True,
        "reason": None,
        "source": _source_payload(sample.source),
        "sampled_at": _point_payload(sample.latitude, sample.longitude),
        "profile": {
            "annual_rainfall_mm": normals.annual_rainfall_mm,
            "monthly_rainfall_mm": normals.monthly_rainfall_mm,
            "dry_months": normals.dry_months,
            "dry_month_names": normals.dry_month_names,
            "wet_months": normals.wet_months,
            "driest_month": {
                "month": normals.driest_month.month,
                "rainfall_mm": normals.driest_month.rainfall_mm,
            },
            "wettest_month": {
                "month": normals.wettest_month.month,
                "rainfall_mm": normals.wettest_month.rainfall_mm,
            },
            "mean_temperature_c": normals.mean_temperature_c,
            "mean_daily_max_c": normals.mean_daily_max_c,
            "mean_humidity_pct": normals.mean_humidity_pct,
            "topsoil_wetness_pct": normals.topsoil_wetness_pct,
        },
    }
