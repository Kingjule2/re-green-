"""Tests for the land-analysis endpoint and colour baseline."""

from __future__ import annotations

from io import BytesIO

import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app.climate import ClimateNormals, ClimateSample, MonthValue
from app.main import app
from app.routers import land as land_router
from app.sampling import SampleReport, SourceInfo
from app.soil import SoilProfile, SoilSample, SoilTexture
from app.terrain import TerrainMetrics
from app.terrain_sources import TerrainSample

client = TestClient(app)

ENDPOINT = "/api/v1/land-analysis"


def _image_bytes(color: tuple[int, int, int], size: tuple[int, int] = (80, 80)) -> bytes:
    buffer = BytesIO()
    Image.new("RGB", size, color).save(buffer, format="PNG")
    return buffer.getvalue()


def _analyze(color: tuple[int, int, int], size: tuple[int, int] = (80, 80)) -> dict:
    response = client.post(
        ENDPOINT,
        files={"image": ("frame.png", _image_bytes(color, size), "image/png")},
    )
    assert response.status_code == 200, response.text
    return response.json()


def test_green_image_is_mostly_vegetation() -> None:
    body = _analyze((34, 139, 34))

    cover = body["land_cover"]
    assert cover["dense_vegetation"] + cover["sparse_vegetation"] > 90
    assert body["model"]["name"] == "color-histogram"


def test_brown_image_is_mostly_bare_soil() -> None:
    cover = _analyze((150, 100, 60))["land_cover"]
    assert cover["bare_soil"] > 50


def test_blue_image_is_mostly_water() -> None:
    cover = _analyze((30, 90, 180))["land_cover"]
    assert cover["water"] > 50


def test_response_shape_is_complete() -> None:
    body = _analyze((34, 139, 34), size=(120, 90))

    assert body["image"] == {"width": 120, "height": 90}

    total = sum(body["land_cover"].values())
    assert abs(total - 100) < 1.5

    assert 0.5 <= body["confidence"]["overall"] <= 1.0

    segmentation = body["segmentation"]
    assert len(segmentation["grid"]) == segmentation["rows"]
    assert len(segmentation["grid"][0]) == segmentation["cols"]
    assert set(segmentation["legend"]).issuperset({"dense_vegetation", "water", "bare_soil"})


def test_empty_upload_is_rejected() -> None:
    response = client.post(ENDPOINT, files={"image": ("empty.png", b"", "image/png")})
    assert response.status_code == 422


def test_non_image_upload_is_rejected() -> None:
    response = client.post(
        ENDPOINT,
        files={"image": ("notes.txt", b"this is not an image", "image/png")},
    )
    assert response.status_code == 422


# ------------------------------------------------- environmental context blocks


TERRAIN_SOURCE = SourceInfo(
    id="demnas",
    label="DEMNAS (BIG)",
    dataset="DEMNAS - DEM Nasional Indonesia",
    provider="Badan Informasi Geospasial (BIG)",
    service_url="https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer",
    portal_url="https://tanahair.indonesia.go.id/portal-web/unduh/demnas",
)


def test_context_blocks_are_reported_unavailable_without_coordinates() -> None:
    body = _analyze((34, 139, 34))

    for block in ("terrain", "soil", "climate"):
        assert body[block]["available"] is False, block
        assert "coordinates" in body[block]["reason"], block
        assert body[block]["profile"] is None, block
        assert body[block]["source"] is None, block


def test_terrain_context_is_attached_when_coordinates_are_supplied(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """The endpoint must expose whatever the DEM layer measured, with provenance."""
    sample = TerrainSample(
        metrics=TerrainMetrics(
            elevation_m=812.5, slope_deg=17.3, aspect_deg=210.0, hillshade=0.62, ruggedness_m=4.75
        ),
        source=TERRAIN_SOURCE,
        latitude=-6.8025,
        longitude=107.6182,
        resolution_m=8.3,
        window_rows=3,
        window_cols=3,
        window_spacing_x_m=8.3,
        window_spacing_y_m=8.4,
    )

    async def fake_sample(latitude: float, longitude: float) -> SampleReport[TerrainSample]:
        assert (latitude, longitude) == (-6.8025, 107.6182)
        return SampleReport(available=True, sample=sample)

    async def no_soil(latitude: float, longitude: float) -> SampleReport:
        return SampleReport(available=False, reason="soil not exercised by this test")

    async def no_climate(latitude: float, longitude: float) -> SampleReport:
        return SampleReport(available=False, reason="climate not exercised by this test")

    monkeypatch.setattr(land_router, "sample_terrain", fake_sample)
    # Soil and climate have their own test below; keeping them off the network
    # here keeps this suite hermetic.
    monkeypatch.setattr(land_router, "sample_soil", no_soil)
    monkeypatch.setattr(land_router, "sample_climate", no_climate)

    response = client.post(
        ENDPOINT,
        files={"image": ("frame.png", _image_bytes((34, 139, 34)), "image/png")},
        data={"latitude": "-6.8025", "longitude": "107.6182"},
    )

    assert response.status_code == 200, response.text
    terrain = response.json()["terrain"]

    assert terrain["available"] is True
    assert terrain["reason"] is None
    assert terrain["source"]["id"] == "demnas"
    assert terrain["source"]["portal_url"].endswith("/unduh/demnas")
    assert terrain["sampled_at"] == {"latitude": -6.8025, "longitude": 107.6182}
    assert terrain["profile"]["elevation_m"] == 812.5
    assert terrain["profile"]["slope_deg"] == 17.3
    assert terrain["profile"]["aspect_deg"] == 210.0
    assert terrain["profile"]["ruggedness_m"] == 4.75
    assert terrain["profile"]["resolution_m"] == 8.3
    assert terrain["profile"]["window"] == {
        "rows": 3,
        "cols": 3,
        "spacing_x_m": 8.3,
        "spacing_y_m": 8.4,
    }


def test_soil_and_climate_context_are_attached_when_coordinates_are_supplied(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """Soil chemistry and rainfall normals must reach the caller with provenance."""
    texture = SoilTexture(sand_pct=33.8, silt_pct=31.8, clay_pct=34.3, class_name="Clay Loam")
    soil = SoilSample(
        profile=SoilProfile(
            texture=texture,
            subsoil_texture=texture,
            ph=5.3,
            organic_carbon_g_kg=103.6,
            nitrogen_g_kg=5.77,
        ),
        source=SourceInfo(
            id="soilgrids",
            label="ISRIC SoilGrids",
            dataset="ISRIC SoilGrids v2.0",
            provider="ISRIC - World Soil Information",
            service_url="https://rest.isric.org/soilgrids/v2.0/properties/query",
            portal_url="https://soilgrids.org",
        ),
        latitude=-7.53,
        longitude=110.45,
        depth_cm=5,
        resolution_m=250.0,
    )
    climate = ClimateSample(
        normals=ClimateNormals(
            annual_rainfall_mm=2095.3,
            monthly_rainfall_mm={"JAN": 345.7, "AUG": 31.9},
            dry_months=2,
            dry_month_names=["JUL", "AUG"],
            wet_months=6,
            driest_month=MonthValue(month="AUG", rainfall_mm=31.9),
            wettest_month=MonthValue(month="JAN", rainfall_mm=345.7),
            mean_temperature_c=24.1,
            mean_daily_max_c=28.4,
            mean_humidity_pct=82.5,
            topsoil_wetness_pct=77.0,
        ),
        source=SourceInfo(
            id="nasa-power",
            label="NASA POWER",
            dataset="NASA POWER agroclimatology climatology",
            provider="NASA Langley Research Center (POWER Project)",
            service_url="https://power.larc.nasa.gov/api/temporal/climatology/point",
            portal_url="https://power.larc.nasa.gov/data-access-viewer/",
        ),
        latitude=-7.53,
        longitude=110.45,
    )

    async def fake_terrain(latitude: float, longitude: float) -> SampleReport[TerrainSample]:
        return SampleReport(available=False, reason="DEM not exercised by this test")

    async def fake_soil(latitude: float, longitude: float) -> SampleReport[SoilSample]:
        assert (latitude, longitude) == (-7.53, 110.45)
        return SampleReport(available=True, sample=soil)

    async def fake_climate(latitude: float, longitude: float) -> SampleReport[ClimateSample]:
        assert (latitude, longitude) == (-7.53, 110.45)
        return SampleReport(available=True, sample=climate)

    monkeypatch.setattr(land_router, "sample_terrain", fake_terrain)
    monkeypatch.setattr(land_router, "sample_soil", fake_soil)
    monkeypatch.setattr(land_router, "sample_climate", fake_climate)

    response = client.post(
        ENDPOINT,
        files={"image": ("frame.png", _image_bytes((34, 139, 34)), "image/png")},
        data={"latitude": "-7.53", "longitude": "110.45"},
    )

    assert response.status_code == 200, response.text
    body = response.json()

    assert body["soil"]["available"] is True
    assert body["soil"]["source"]["id"] == "soilgrids"
    assert body["soil"]["depth_cm"] == 5
    assert body["soil"]["resolution_m"] == 250.0
    assert body["soil"]["profile"]["texture"] == {
        "class_name": "Clay Loam",
        "sand_pct": 33.8,
        "silt_pct": 31.8,
        "clay_pct": 34.3,
    }
    assert body["soil"]["profile"]["ph"] == 5.3
    assert body["soil"]["profile"]["organic_carbon_g_kg"] == 103.6

    assert body["climate"]["available"] is True
    assert body["climate"]["source"]["id"] == "nasa-power"
    assert body["climate"]["profile"]["annual_rainfall_mm"] == 2095.3
    assert body["climate"]["profile"]["dry_months"] == 2
    assert body["climate"]["profile"]["dry_month_names"] == ["JUL", "AUG"]
    assert body["climate"]["profile"]["driest_month"] == {"month": "AUG", "rainfall_mm": 31.9}
    assert body["climate"]["profile"]["topsoil_wetness_pct"] == 77.0


def test_half_a_coordinate_pair_is_rejected() -> None:
    response = client.post(
        ENDPOINT,
        files={"image": ("frame.png", _image_bytes((34, 139, 34)), "image/png")},
        data={"latitude": "-6.8"},
    )

    assert response.status_code == 422
    assert "together" in response.json()["detail"]


@pytest.mark.parametrize(
    ("latitude", "longitude"),
    [("91", "107"), ("-6.8", "181")],
)
def test_out_of_range_coordinates_are_rejected(latitude: str, longitude: str) -> None:
    response = client.post(
        ENDPOINT,
        files={"image": ("frame.png", _image_bytes((34, 139, 34)), "image/png")},
        data={"latitude": latitude, "longitude": longitude},
    )

    assert response.status_code == 422
