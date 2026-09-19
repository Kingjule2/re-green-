"""Tests for the soil layer: the USDA texture triangle and the SoilGrids source.

The fixtures under `tests/fixtures/` are real, verbatim responses captured from
ISRIC's public service for the same point the crop tests use (Mount Merapi,
-7.53 / 110.45), so the parsing is asserted against the service's actual shape
rather than an invented one.
"""

from __future__ import annotations

import json
from pathlib import Path

import httpx
import pytest

from app.sampling import SourceUnavailable
from app.soil import (
    SOILGRIDS_URL,
    SoilGridsSource,
    texture_from_fractions,
    usda_texture_class,
)

FIXTURES = Path(__file__).parent / "fixtures"
MERAPI = json.loads((FIXTURES / "soilgrids_merapi.json").read_text(encoding="utf-8"))


def transport(payload: object, requests: list[httpx.Request] | None = None) -> httpx.MockTransport:
    def handler(request: httpx.Request) -> httpx.Response:
        if requests is not None:
            requests.append(request)

        return httpx.Response(200, json=payload)

    return httpx.MockTransport(handler)


# ------------------------------------------------------------- texture triangle


@pytest.mark.parametrize(
    ("sand", "silt", "clay", "expected"),
    [
        (92.0, 5.0, 3.0, "Sand"),
        (82.0, 12.0, 6.0, "Loamy Sand"),
        (65.0, 25.0, 10.0, "Sandy Loam"),
        (43.0, 40.0, 17.0, "Loam"),
        (10.0, 65.0, 25.0, "Silt Loam"),
        (5.0, 90.0, 5.0, "Silt"),
        (55.0, 20.0, 25.0, "Sandy Clay Loam"),
        (30.0, 35.0, 35.0, "Clay Loam"),
        (15.0, 50.0, 35.0, "Silty Clay Loam"),
        (50.0, 10.0, 40.0, "Sandy Clay"),
        (15.0, 45.0, 40.0, "Silty Clay"),
        (25.0, 20.0, 55.0, "Clay"),
    ],
)
def test_usda_texture_triangle_covers_all_twelve_classes(
    sand: float, silt: float, clay: float, expected: str
) -> None:
    assert usda_texture_class(sand, silt, clay) == expected


def test_texture_fractions_are_normalized_before_classifying() -> None:
    # SoilGrids' three fractions are independent predictions and do not sum to
    # exactly 100; the raw values stay on the result for provenance.
    texture = texture_from_fractions(sand_pct=33.8, silt_pct=31.8, clay_pct=34.4)

    assert texture.class_name == "Clay Loam"
    assert (texture.sand_pct, texture.silt_pct, texture.clay_pct) == (33.8, 31.8, 34.4)


def test_texture_from_fractions_rejects_empty_input() -> None:
    with pytest.raises(ValueError):
        texture_from_fractions(0.0, 0.0, 0.0)


# ------------------------------------------------------------- SoilGrids source


async def test_soilgrids_source_reads_a_real_merapi_response() -> None:
    source = SoilGridsSource(SOILGRIDS_URL, transport=transport(MERAPI))

    sample = await source.sample(-7.53, 110.45)
    profile = sample.profile

    # 343 g/kg of clay divided by the response's d_factor of 10 is 34.3%.
    assert profile.texture.sand_pct == 33.8
    assert profile.texture.silt_pct == 31.8
    assert profile.texture.clay_pct == 34.3
    assert profile.texture.class_name == "Clay Loam"

    # The layer below is reported too: it decides drainage and rooting depth.
    assert profile.subsoil_texture.class_name == "Clay Loam"
    assert profile.subsoil_texture.clay_pct == 34.6

    assert profile.ph == 5.3
    assert profile.organic_carbon_g_kg == 103.6
    assert profile.nitrogen_g_kg == 5.77

    assert sample.source.id == "soilgrids"
    assert sample.depth_cm == 5
    assert sample.resolution_m == 250.0
    assert (sample.latitude, sample.longitude) == (-7.53, 110.45)


async def test_soilgrids_source_requests_both_depths_and_all_properties() -> None:
    requests: list[httpx.Request] = []
    source = SoilGridsSource(SOILGRIDS_URL, transport=transport(MERAPI, requests))

    await source.sample(-7.53, 110.45)

    params = requests[0].url.params
    assert sorted(params.get_list("property")) == [
        "clay",
        "nitrogen",
        "phh2o",
        "sand",
        "silt",
        "soc",
    ]
    assert sorted(params.get_list("depth")) == ["0-5cm", "5-15cm"]
    assert params["lat"] == "-7.53"
    assert params["lon"] == "110.45"
    assert params["value"] == "mean"


async def test_soilgrids_samples_are_cached_per_coordinate() -> None:
    requests: list[httpx.Request] = []
    source = SoilGridsSource(SOILGRIDS_URL, transport=transport(MERAPI, requests))

    first = await source.sample(-7.53, 110.45)
    second = await source.sample(-7.53, 110.45)

    assert first == second
    # One call for the point, none for the repeat analysis of the same field.
    assert len(requests) == 1


async def test_soilgrids_reports_an_empty_response_as_unavailable() -> None:
    source = SoilGridsSource(SOILGRIDS_URL, transport=transport({"properties": {"layers": []}}))

    with pytest.raises(SourceUnavailable, match="no soil properties"):
        await source.sample(-7.53, 110.45)


async def test_soilgrids_reports_missing_topsoil_chemistry_as_unavailable() -> None:
    def layer(name: str, value: int | None) -> dict:
        return {
            "name": name,
            "unit_measure": {"d_factor": 10},
            "depths": [
                {"label": "0-5cm", "values": {"mean": value}},
                {"label": "5-15cm", "values": {"mean": value}},
            ],
        }

    payload = {
        "properties": {
            "layers": [
                layer("sand", 338),
                layer("silt", 318),
                layer("clay", 343),
                # SoilGrids reports a null mean where it has no prediction.
                layer("phh2o", None),
                layer("soc", 1036),
                layer("nitrogen", 577),
            ]
        }
    }

    source = SoilGridsSource(SOILGRIDS_URL, transport=transport(payload))

    with pytest.raises(SourceUnavailable, match="no topsoil chemistry"):
        await source.sample(-7.53, 110.45)


async def test_soilgrids_rejects_fractions_that_are_not_a_soil() -> None:
    def layer(name: str, value: int) -> dict:
        return {
            "name": name,
            "unit_measure": {"d_factor": 10},
            "depths": [
                {"label": "0-5cm", "values": {"mean": value}},
                {"label": "5-15cm", "values": {"mean": value}},
            ],
        }

    payload = {
        "properties": {
            "layers": [layer("sand", 100), layer("silt", 100), layer("clay", 100)]
        }
    }

    source = SoilGridsSource(SOILGRIDS_URL, transport=transport(payload))

    with pytest.raises(SourceUnavailable, match="do not describe a soil"):
        await source.sample(-7.53, 110.45)


async def test_soilgrids_reports_an_unreachable_service() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectTimeout("")

    source = SoilGridsSource(SOILGRIDS_URL, transport=httpx.MockTransport(handler))

    with pytest.raises(SourceUnavailable, match="could not be reached: ConnectTimeout"):
        await source.sample(-7.53, 110.45)
