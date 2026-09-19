"""Tests for the climate layer: rainfall derivation and the NASA POWER source.

The fixture under `tests/fixtures/` is a real, verbatim climatology response
captured from NASA POWER for Mount Merapi (-7.53 / 110.45), so the derivations
are asserted against NASA's own numbers, including the months that make the
planting calendar interesting.
"""

from __future__ import annotations

import json
from pathlib import Path

import httpx
import pytest

from app.climate import (
    DAYS_IN_MONTH,
    MONTHS,
    POWER_URL,
    NasaPowerSource,
)
from app.sampling import SourceUnavailable

FIXTURES = Path(__file__).parent / "fixtures"
MERAPI = json.loads((FIXTURES / "nasa_power_merapi.json").read_text(encoding="utf-8"))


def transport(payload: object, requests: list[httpx.Request] | None = None) -> httpx.MockTransport:
    def handler(request: httpx.Request) -> httpx.Response:
        if requests is not None:
            requests.append(request)

        return httpx.Response(200, json=payload)

    return httpx.MockTransport(handler)


async def test_monthly_rainfall_is_scaled_from_daily_means() -> None:
    source = NasaPowerSource(POWER_URL, transport=transport(MERAPI))

    normals = (await source.sample(-7.53, 110.45)).normals

    # NASA reports mm/day; a month is that mean times its day count.
    # January: 11.15 mm/day * 31 days = 345.7 mm.
    assert normals.monthly_rainfall_mm["JAN"] == 345.7
    # August: 1.03 mm/day * 31 days = 31.9 mm.
    assert normals.monthly_rainfall_mm["AUG"] == 31.9
    assert set(normals.monthly_rainfall_mm) == set(MONTHS)
    assert normals.annual_rainfall_mm == pytest.approx(2090, abs=15)


async def test_dry_and_wet_months_follow_the_oldeman_thresholds() -> None:
    source = NasaPowerSource(POWER_URL, transport=transport(MERAPI))

    normals = (await source.sample(-7.53, 110.45)).normals

    # July (53.6 mm) and August (31.9 mm) are below 60 mm; September (69.6 mm)
    # is merely humid, which is the distinction a planting calendar turns on.
    assert normals.dry_month_names == ["JUL", "AUG"]
    assert normals.dry_months == 2
    assert normals.wet_months == 8

    assert normals.driest_month.month == "AUG"
    assert normals.driest_month.rainfall_mm == 31.9
    assert normals.wettest_month.month == "JAN"
    assert normals.wettest_month.rainfall_mm == 345.7


async def test_temperature_humidity_and_topsoil_wetness_are_reported() -> None:
    source = NasaPowerSource(POWER_URL, transport=transport(MERAPI))

    normals = (await source.sample(-7.53, 110.45)).normals

    assert normals.mean_temperature_c == 25.0
    assert normals.mean_daily_max_c == 38.7
    assert normals.mean_humidity_pct == 82.5
    assert normals.topsoil_wetness_pct == 77.0


async def test_parameters_power_does_not_serve_are_reported_as_null() -> None:
    """POWER silently drops unsupported parameters instead of failing the request."""
    payload = json.loads(json.dumps(MERAPI))
    del payload["properties"]["parameter"]["GWETTOP"]
    del payload["properties"]["parameter"]["RH2M"]

    source = NasaPowerSource(POWER_URL, transport=transport(payload))

    normals = (await source.sample(-7.53, 110.45)).normals

    assert normals.topsoil_wetness_pct is None
    assert normals.mean_humidity_pct is None
    # The required series still drive the block.
    assert normals.dry_months == 2


async def test_missing_rainfall_makes_the_block_unavailable() -> None:
    payload = json.loads(json.dumps(MERAPI))
    del payload["properties"]["parameter"]["PRECTOTCORR"]

    source = NasaPowerSource(POWER_URL, transport=transport(payload))

    with pytest.raises(SourceUnavailable, match="no PRECTOTCORR"):
        await source.sample(-7.53, 110.45)


async def test_the_missing_value_sentinel_is_treated_as_missing() -> None:
    payload = json.loads(json.dumps(MERAPI))
    payload["properties"]["parameter"]["PRECTOTCORR"]["AUG"] = -999.0

    source = NasaPowerSource(POWER_URL, transport=transport(payload))

    with pytest.raises(SourceUnavailable, match="no PRECTOTCORR for AUG"):
        await source.sample(-7.53, 110.45)


async def test_climate_samples_are_cached_per_coordinate() -> None:
    requests: list[httpx.Request] = []
    source = NasaPowerSource(POWER_URL, transport=transport(MERAPI, requests))

    first = await source.sample(-7.53, 110.45)
    second = await source.sample(-7.53, 110.45)

    assert first == second
    assert len(requests) == 1


async def test_power_is_asked_for_a_non_leap_year_of_days() -> None:
    """The day counts that convert mm/day to mm/month must be the documented ones."""
    assert len(DAYS_IN_MONTH) == 12
    assert sum(DAYS_IN_MONTH) == 365
    assert DAYS_IN_MONTH[MONTHS.index("FEB")] == 28

    requests: list[httpx.Request] = []
    source = NasaPowerSource(POWER_URL, transport=transport(MERAPI, requests))

    await source.sample(-7.53, 110.45)

    params = requests[0].url.params
    assert params["latitude"] == "-7.53"
    assert params["longitude"] == "110.45"
    assert params["community"] == "AG"
    assert "PRECTOTCORR" in params["parameters"]
    assert "T2M_MIN" not in params["parameters"]
