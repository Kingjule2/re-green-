"""Tests for the terrain layer: DEM math, DEM sources and the API contract.

The math is asserted against the published operators, not against whatever the
implementation happens to produce: Horn (1981) gradients, the ESRI hillshade
model, and Riley's Terrain Ruggedness Index. A synthetic plane of known
steepness therefore has exactly one correct slope and aspect.
"""

from __future__ import annotations

import json
from math import atan, cos, degrees, radians, sin, sqrt

import httpx
import pytest

from app import terrain_sources
from app.sampling import SampleReport, SourceInfo, SourceUnavailable
from app.terrain import (
    ElevationWindow,
    TerrainMetrics,
    derive_metrics,
    ground_spacing,
    meters_per_degree_lon,
)
from app.terrain_sources import (
    DemnasSource,
    EarthEngineSrtmSource,
    TerrainSample,
    sample_terrain,
)

FLAT = ElevationWindow(values=(100.0,) * 9, spacing_x_m=10.0, spacing_y_m=10.0)

# A 1:1 plane facing north-west and its mirror facing south-east. Both have a
# gradient magnitude of sqrt(2), i.e. atan(sqrt(2)) = 54.74 degrees of slope.
NORTH_WEST = (0.0, 1.0, 2.0, 1.0, 2.0, 3.0, 2.0, 3.0, 4.0)
SOUTH_EAST = (0.0, -1.0, -2.0, -1.0, -2.0, -3.0, -2.0, -3.0, -4.0)

DIAGONAL_SLOPE_DEG = 54.7


def plane(values: tuple[float, ...], spacing: float = 1.0) -> ElevationWindow:
    """A 3x3 window with equal x and y spacing, for exact-math assertions."""
    return ElevationWindow(values=values, spacing_x_m=spacing, spacing_y_m=spacing)


# --------------------------------------------------------------------------- math


def test_flat_ground_has_no_slope_no_aspect_and_no_ruggedness() -> None:
    metrics = derive_metrics(FLAT)

    assert metrics.slope_deg == 0.0
    assert metrics.aspect_deg is None
    assert metrics.ruggedness_m == 0.0
    assert metrics.elevation_m == 100.0
    # A flat surface under a 45 degree light still receives cos(45) of it.
    assert metrics.hillshade == pytest.approx(cos(radians(45)), abs=1e-3)


def test_horn_slope_and_compass_aspect_on_known_gradients() -> None:
    # Rising 20 m per 10 m eastward is a 2.0 gradient, which faces west (270).
    eastward = plane((0.0, 20.0, 40.0, 0.0, 20.0, 40.0, 0.0, 20.0, 40.0), spacing=10.0)
    east = derive_metrics(eastward)
    assert east.slope_deg == pytest.approx(degrees(atan(2.0)), abs=0.05)
    assert east.aspect_deg == 270.0

    # The same rise southward faces north (0).
    southward = plane((0.0, 0.0, 0.0, 20.0, 20.0, 20.0, 40.0, 40.0, 40.0), spacing=10.0)
    south = derive_metrics(southward)
    assert south.slope_deg == pytest.approx(degrees(atan(2.0)), abs=0.05)
    assert south.aspect_deg == 0.0

    assert derive_metrics(plane(NORTH_WEST)).slope_deg == pytest.approx(
        DIAGONAL_SLOPE_DEG, abs=0.05
    )
    assert derive_metrics(plane(NORTH_WEST)).aspect_deg == 315.0
    assert derive_metrics(plane(SOUTH_EAST)).aspect_deg == 135.0


def test_aspect_is_dropped_below_the_flat_slope_threshold() -> None:
    # 1 cm of rise per 10 m is a 0.06 degree slope: flatter than the threshold,
    # where the DEM's own noise rather than the landscape decides the direction.
    nearly_flat = plane((0.0, 0.01, 0.02, 0.0, 0.01, 0.02, 0.0, 0.01, 0.02), spacing=10.0)

    assert derive_metrics(nearly_flat).slope_deg < 0.5
    assert derive_metrics(nearly_flat).aspect_deg is None


def test_hillshade_matches_the_esri_illumination_model() -> None:
    zenith = radians(45)
    slope = radians(DIAGONAL_SLOPE_DEG)

    # Illumination = cos(zenith)cos(slope) + sin(zenith)sin(slope)cos(135 - aspect),
    # where 135 degrees is the north-west light expressed as a compass bearing.
    facing_the_light = cos(zenith) * cos(slope) + sin(zenith) * sin(slope) * cos(radians(135 - 315))
    away_from_it = cos(zenith) * cos(slope) + sin(zenith) * sin(slope) * cos(radians(135 - 135))

    assert facing_the_light < 0  # fully shaded, so clamped to zero
    assert derive_metrics(plane(NORTH_WEST)).hillshade == 0.0
    assert derive_metrics(plane(SOUTH_EAST)).hillshade == pytest.approx(away_from_it, abs=1e-3)


def test_ruggedness_is_riley_terrain_ruggedness_index() -> None:
    # sqrt(sum((z - z_center)^2)) over the 8 neighbours.
    assert derive_metrics(plane(NORTH_WEST)).ruggedness_m == pytest.approx(sqrt(12), abs=0.005)
    assert derive_metrics(FLAT).ruggedness_m == 0.0

    cliff = plane((100.0, 100.0, 100.0, 100.0, 0.0, 100.0, 100.0, 100.0, 100.0))
    assert derive_metrics(cliff).ruggedness_m == pytest.approx(sqrt(80_000), abs=0.005)


def test_metrics_are_rounded_to_the_documented_precision() -> None:
    metrics = derive_metrics(plane(NORTH_WEST))

    assert metrics.slope_deg == round(metrics.slope_deg, 1)
    assert metrics.hillshade == round(metrics.hillshade, 3)
    assert metrics.ruggedness_m == round(metrics.ruggedness_m, 2)

    # Rounding is idempotent: re-deriving from the rounded values cannot drift.
    assert (
        TerrainMetrics(
            elevation_m=metrics.elevation_m,
            slope_deg=metrics.slope_deg,
            aspect_deg=metrics.aspect_deg,
            hillshade=metrics.hillshade,
            ruggedness_m=metrics.ruggedness_m,
        )
        == metrics
    )


def test_window_rejects_the_wrong_shape_and_impossible_spacing() -> None:
    with pytest.raises(ValueError):
        ElevationWindow(values=(1.0, 2.0, 3.0), spacing_x_m=10.0, spacing_y_m=10.0)

    with pytest.raises(ValueError):
        ElevationWindow(values=(1.0,) * 9, spacing_x_m=0.0, spacing_y_m=10.0)


def test_ground_spacing_shrinks_with_longitude_and_not_with_latitude() -> None:
    x_equator, y_equator = ground_spacing(0.0, 1 / 3600)
    x_high, y_high = ground_spacing(45.0, 1 / 3600)

    assert x_equator == pytest.approx(111_320 / 3600, rel=1e-9)
    assert y_equator == pytest.approx(110_540 / 3600, rel=1e-9)

    # Latitude spacing is constant; longitude spacing follows cos(latitude).
    assert y_high == pytest.approx(y_equator, rel=1e-9)
    assert x_high == pytest.approx(x_equator * cos(radians(45)), rel=1e-6)
    assert meters_per_degree_lon(60.0) == pytest.approx(111_320 * cos(radians(60)), rel=1e-6)


# ------------------------------------------------------------------ DEMNAS source


def demnas_service(
    pixel_size_deg: float, rise_m_per_pixel: float = 20.0
) -> tuple[httpx.MockTransport, list[httpx.Request]]:
    """A fake BIG image service serving an eastward-rising plane centred on 106 E."""
    requests: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        requests.append(request)

        if not request.url.path.endswith("/identify"):
            return httpx.Response(
                200, json={"pixelSizeX": pixel_size_deg, "pixelSizeY": pixel_size_deg}
            )

        geometry = json.loads(request.url.params["geometry"])
        elevation = (geometry["x"] - 106.0) / pixel_size_deg * rise_m_per_pixel

        return httpx.Response(200, json={"value": str(elevation)})

    return httpx.MockTransport(handler), requests


async def test_demnas_source_reads_a_grid_and_derives_the_known_plane() -> None:
    pixel_size = 0.27 / 3600
    transport, requests = demnas_service(pixel_size)
    source = DemnasSource("https://example.test/ImageServer", transport=transport)

    sample = await source.sample(0.0, 106.0)

    # The fake DEM rises 20 m per pixel eastward, so the derived products must
    # be exactly those of that plane - which also proves the 3x3 window was
    # assembled in the right order (north-west first).
    spacing_x_m = pixel_size * meters_per_degree_lon(0.0)
    slope_deg = degrees(atan(20.0 / spacing_x_m))
    lit = cos(radians(45)) * cos(radians(slope_deg)) + sin(radians(45)) * sin(
        radians(slope_deg)
    ) * cos(radians(135 - 270))

    assert sample.metrics.elevation_m == 0.0
    assert sample.metrics.slope_deg == pytest.approx(slope_deg, abs=0.05)
    assert sample.metrics.aspect_deg == 270.0
    assert sample.metrics.ruggedness_m == pytest.approx(sqrt(6) * 20.0, abs=0.05)
    # A 67 degree west-facing slope falls outside the north-west light, so the
    # hillshade is clamped into the documented 0..1 range.
    assert lit < 0
    assert sample.metrics.hillshade == 0.0

    assert sample.resolution_m == pytest.approx(8.3, abs=0.15)
    assert sample.window_rows == 3 and sample.window_cols == 3
    assert sample.source.id == "demnas"

    # One metadata read plus nine elevation samples.
    assert len(requests) == 10


async def test_demnas_source_reports_missing_values_as_unavailable() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        if request.url.path.endswith("/identify"):
            return httpx.Response(200, json={"value": "NoData"})

        return httpx.Response(200, json={"pixelSizeX": 0.27 / 3600})

    source = DemnasSource(
        "https://example.test/ImageServer", transport=httpx.MockTransport(handler)
    )

    with pytest.raises(SourceUnavailable, match="no elevation value"):
        await source.sample(-6.2, 106.8)


async def test_demnas_source_rejects_a_multiband_render() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        if request.url.path.endswith("/identify"):
            return httpx.Response(200, json={"value": "12.5,34.0,9.1"})

        return httpx.Response(200, json={"pixelSizeX": 0.27 / 3600})

    source = DemnasSource(
        "https://example.test/ImageServer", transport=httpx.MockTransport(handler)
    )

    with pytest.raises(SourceUnavailable, match="multi-band"):
        await source.sample(-6.2, 106.8)


async def test_demnas_source_reports_an_unreachable_service() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectTimeout("")

    source = DemnasSource(
        "https://example.test/ImageServer", transport=httpx.MockTransport(handler)
    )

    # httpx sends no message for a connect timeout, so the failure must still
    # name the cause instead of trailing off into nothing.
    with pytest.raises(SourceUnavailable, match="could not be reached: ConnectTimeout"):
        await source.sample(-6.2, 106.8)


def test_demnas_covers_only_its_published_extent() -> None:
    source = DemnasSource("https://example.test/ImageServer")

    assert source.covers(-6.2, 106.8) is True  # Jakarta
    assert source.covers(46.9, 7.4) is False  # Bern


# -------------------------------------------------------------------- SRTM source


def test_srtm_reports_why_it_cannot_sample() -> None:
    unconfigured = EarthEngineSrtmSource(service_account="", private_key_file="")

    assert "Earth Engine" in (unconfigured.unavailable_reason() or "")

    configured = EarthEngineSrtmSource(
        service_account="robot@project.iam.gserviceaccount.com", private_key_file="key.json"
    )

    assert configured.unavailable_reason() is None


def test_srtm_coverage_stops_at_its_latitude_limits() -> None:
    source = EarthEngineSrtmSource()

    assert source.covers(-6.2, 106.8) is True
    assert source.covers(61.0, 10.0) is False


# ------------------------------------------------------------ source resolution


def sample_stub(source_id: str, calls: list[str], *, fails: bool = False):
    """A stand-in DEM source that records when it is sampled."""

    class Stub:
        info = SourceInfo(
            id=source_id,
            label=source_id,
            dataset=source_id,
            provider=source_id,
            service_url="https://example.test",
            portal_url="https://example.test",
        )

        def covers(self, latitude: float, longitude: float) -> bool:
            return True

        def unavailable_reason(self) -> str | None:
            return None

        async def sample(self, latitude: float, longitude: float) -> TerrainSample:
            calls.append(source_id)

            if fails:
                raise SourceUnavailable(f"{source_id} is down")

            return TerrainSample(
                metrics=TerrainMetrics(12.0, 3.0, 90.0, 0.5, 1.0),
                source=self.info,
                latitude=latitude,
                longitude=longitude,
                resolution_m=8.3,
                window_rows=3,
                window_cols=3,
                window_spacing_x_m=8.3,
                window_spacing_y_m=8.3,
            )

    return Stub()


async def test_demnas_is_preferred_over_srtm(monkeypatch: pytest.MonkeyPatch) -> None:
    calls: list[str] = []
    monkeypatch.setattr(terrain_sources, "_demnas", lambda: sample_stub("demnas", calls))
    monkeypatch.setattr(terrain_sources, "_srtm", lambda: sample_stub("srtm", calls))
    monkeypatch.setattr(terrain_sources.settings, "terrain_source", "auto")

    report = await sample_terrain(-6.2, 106.8)

    assert report.available is True
    assert report.sample is not None and report.sample.source.id == "demnas"
    assert calls == ["demnas"]


async def test_srtm_takes_over_when_demnas_fails(monkeypatch: pytest.MonkeyPatch) -> None:
    calls: list[str] = []
    monkeypatch.setattr(
        terrain_sources, "_demnas", lambda: sample_stub("demnas", calls, fails=True)
    )
    monkeypatch.setattr(terrain_sources, "_srtm", lambda: sample_stub("srtm", calls))
    monkeypatch.setattr(terrain_sources.settings, "terrain_source", "auto")
    monkeypatch.setattr(terrain_sources.settings, "terrain_fallback", True)

    report = await sample_terrain(-6.2, 106.8)

    assert report.available is True
    assert report.sample is not None and report.sample.source.id == "srtm"
    assert calls == ["demnas", "srtm"]


async def test_fallback_can_be_switched_off(monkeypatch: pytest.MonkeyPatch) -> None:
    calls: list[str] = []
    monkeypatch.setattr(
        terrain_sources, "_demnas", lambda: sample_stub("demnas", calls, fails=True)
    )
    monkeypatch.setattr(terrain_sources, "_srtm", lambda: sample_stub("srtm", calls))
    monkeypatch.setattr(terrain_sources.settings, "terrain_source", "auto")
    monkeypatch.setattr(terrain_sources.settings, "terrain_fallback", False)

    report = await sample_terrain(-6.2, 106.8)

    assert report.available is False
    assert report.reason is not None and "demnas is down" in report.reason
    assert calls == ["demnas"]


async def test_every_failure_is_reported_when_no_source_can_sample(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    calls: list[str] = []
    monkeypatch.setattr(
        terrain_sources, "_demnas", lambda: sample_stub("demnas", calls, fails=True)
    )
    monkeypatch.setattr(terrain_sources, "_srtm", lambda: sample_stub("srtm", calls, fails=True))
    monkeypatch.setattr(terrain_sources.settings, "terrain_source", "auto")

    report = await sample_terrain(-6.2, 106.8)

    assert report.available is False
    assert report.sample is None
    assert report.reason is not None
    assert "demnas is down" in report.reason
    assert "srtm is down" in report.reason


async def test_disabled_terrain_explains_itself(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(terrain_sources.settings, "terrain_source", "none")

    report = await sample_terrain(-6.2, 106.8)

    assert report == SampleReport(
        available=False, reason="terrain context is disabled (ML_TERRAIN_SOURCE=none)"
    )


async def test_a_pinned_source_is_never_asked_outside_its_coverage(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setattr(terrain_sources.settings, "terrain_source", "demnas")

    # Bern is outside DEMNAS, and the real service would be contacted - and hang
    # - if the coverage check did not skip it first.
    report = await sample_terrain(46.9, 7.4)

    assert report.available is False
    assert report.reason == "DEMNAS (BIG) does not cover 46.90000, 7.40000"


async def test_a_point_no_dem_covers_says_so(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(terrain_sources.settings, "terrain_source", "auto")

    # Antarctica: outside SRTM's 56 S limit and far outside DEMNAS.
    report = await sample_terrain(-70.0, 10.0)

    assert report.available is False
    assert report.reason == "no configured DEM covers -70.00000, 10.00000"
