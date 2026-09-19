"""Rainfall and temperature normals for a survey point, from NASA POWER.

Rain is the half of the water story a single drone flight can never see: the
same canopy looks identical in a 3000 mm valley and a 900 mm one, but only one
of them needs irrigation to grow maize. The long-term monthly distribution is
what separates "enough rain" from "rain at the wrong time", and the number of
dry months is the single most useful number for planning a planting calendar.

Source: **NASA POWER** (Prediction Of Worldwide Energy Resources), climatology
endpoint, community ``AG`` (agroclimatology). Free, no key, and built from the
same NASA reanalysis record that feeds the POWER agriculture tooling. Values are
long-term monthly means of daily totals.

Portal: https://power.larc.nasa.gov
Docs:   https://power.larc.nasa.gov/docs/services/api/temporal/climatology/
"""

from __future__ import annotations

import logging
from dataclasses import dataclass
from functools import lru_cache

import httpx

from app.cache import BoundedCache
from app.config import settings
from app.sampling import Sampler, SampleReport, SourceInfo, SourceUnavailable, describe, resolve

logger = logging.getLogger(__name__)

POWER_URL = "https://power.larc.nasa.gov/api/temporal/climatology/point"

# Agroclimatology parameters: rain, temperature, humidity, topsoil wetness.
#
# POWER silently drops parameters it does not serve for a given endpoint, so the
# requirement is split: rainfall (PRECTOTCORR) and mean temperature (T2M) are what
# the crop engine scores against and must be present, the rest are reported as
# null when the service does not answer with them. (`T2M_MIN` is not served by
# the climatology endpoint at all, which is why the diurnal minimum is not part
# of the contract.)
PARAMETERS = ("PRECTOTCORR", "T2M", "T2M_MAX", "RH2M", "GWETTOP")

MONTHS = ("JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC")

# A non-leap year: monthly means of daily totals scale to monthly totals.
DAYS_IN_MONTH = (31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31)

# Oldeman/BMKG convention for Indonesian agronomy: a dry month brings less than
# 60 mm of rain, a wet month more than 100 mm, between the two it is humid.
DRY_MONTH_MM = 60.0
WET_MONTH_MM = 100.0

# POWER reports missing data as a large negative sentinel.
MISSING_SENTINEL = -900.0

SOURCE = SourceInfo(
    id="nasa-power",
    label="NASA POWER",
    dataset=(
        "NASA POWER agroclimatology climatology "
        "(rainfall, temperature, humidity, topsoil wetness)"
    ),
    provider="NASA Langley Research Center (POWER Project)",
    service_url=POWER_URL,
    portal_url="https://power.larc.nasa.gov/data-access-viewer/",
)


@dataclass(frozen=True)
class MonthValue:
    """A named extreme month."""

    month: str
    rainfall_mm: float


@dataclass(frozen=True)
class ClimateNormals:
    """Long-term rainfall and temperature normals for a point.

    The optional fields are null when NASA POWER does not answer with them for
    the requested point; nothing is estimated in their place.
    """

    annual_rainfall_mm: float
    monthly_rainfall_mm: dict[str, float]
    dry_months: int
    dry_month_names: list[str]
    wet_months: int
    driest_month: MonthValue
    wettest_month: MonthValue
    mean_temperature_c: float
    mean_daily_max_c: float | None
    mean_humidity_pct: float | None
    topsoil_wetness_pct: float | None


@dataclass(frozen=True)
class ClimateSample:
    """Climate normals plus the metadata the UI needs to attribute them."""

    normals: ClimateNormals
    source: SourceInfo
    latitude: float
    longitude: float


class NasaPowerSource(Sampler[ClimateSample]):
    """NASA POWER climatology point query."""

    info = SOURCE

    def __init__(
        self,
        base_url: str | None = None,
        timeout: float | None = None,
        transport: httpx.AsyncBaseTransport | None = None,
    ) -> None:
        self.base_url = (base_url or settings.power_url).rstrip("/")
        self.timeout = timeout or settings.climate_timeout
        self._transport = transport
        # Climatology is a fixed reference period: cache by coordinate.
        self._cache: BoundedCache[ClimateSample] = BoundedCache(settings.point_cache_size)

    def unavailable_reason(self) -> str | None:
        return None

    async def sample(self, latitude: float, longitude: float) -> ClimateSample:
        cached = self._cache.get(latitude, longitude)
        if cached is not None:
            return cached

        payload = await self._fetch(latitude, longitude)
        sample = self._parse(payload, latitude, longitude)

        return self._cache.put(latitude, longitude, sample)

    async def _fetch(self, latitude: float, longitude: float) -> dict:
        try:
            async with httpx.AsyncClient(timeout=self.timeout, transport=self._transport) as client:
                response = await client.get(
                    self.base_url,
                    params={
                        "parameters": ",".join(PARAMETERS),
                        "community": "AG",
                        "longitude": longitude,
                        "latitude": latitude,
                        "format": "JSON",
                    },
                )
                response.raise_for_status()

                return response.json()
        except (httpx.HTTPError, ValueError) as exc:
            raise SourceUnavailable(f"NASA POWER could not be reached: {describe(exc)}") from exc

    def _parse(self, payload: dict, latitude: float, longitude: float) -> ClimateSample:
        parameters = payload.get("properties", {}).get("parameter")
        if not isinstance(parameters, dict):
            raise SourceUnavailable(
                f"NASA POWER returned no climate parameters at {latitude:.5f}, {longitude:.5f}."
            )

        rainfall = self._series(parameters, "PRECTOTCORR", latitude, longitude, required=True)
        monthly = {
            month: round(rainfall[month] * days, 1)
            for month, days in zip(MONTHS, DAYS_IN_MONTH, strict=True)
        }
        annual = round(sum(monthly.values()), 1)

        dry = [month for month in MONTHS if monthly[month] < DRY_MONTH_MM]
        wet = [month for month in MONTHS if monthly[month] > WET_MONTH_MM]

        driest = min(MONTHS, key=lambda month: monthly[month])
        wettest = max(MONTHS, key=lambda month: monthly[month])

        mean_temp = self._series(parameters, "T2M", latitude, longitude, required=True)["ANN"]
        max_temp = self._optional(parameters, "T2M_MAX", latitude, longitude)
        humidity = self._optional(parameters, "RH2M", latitude, longitude)
        wetness = self._optional(parameters, "GWETTOP", latitude, longitude)

        return ClimateSample(
            normals=ClimateNormals(
                annual_rainfall_mm=annual,
                monthly_rainfall_mm=monthly,
                dry_months=len(dry),
                dry_month_names=dry,
                wet_months=len(wet),
                driest_month=MonthValue(month=driest, rainfall_mm=monthly[driest]),
                wettest_month=MonthValue(month=wettest, rainfall_mm=monthly[wettest]),
                mean_temperature_c=round(mean_temp, 1),
                mean_daily_max_c=None if max_temp is None else round(max_temp["ANN"], 1),
                mean_humidity_pct=None if humidity is None else round(humidity["ANN"], 1),
                # GWETTOP is a 0-1 wetness fraction of the topsoil layer.
                topsoil_wetness_pct=None if wetness is None else round(wetness["ANN"] * 100, 1),
            ),
            source=self.info,
            latitude=latitude,
            longitude=longitude,
        )

    def _optional(
        self, parameters: dict, name: str, latitude: float, longitude: float
    ) -> dict[str, float] | None:
        """An optional series, or None when POWER does not serve it for this point."""
        try:
            return self._series(parameters, name, latitude, longitude, required=False)
        except SourceUnavailable:
            return None

    def _series(
        self,
        parameters: dict,
        name: str,
        latitude: float,
        longitude: float,
        *,
        required: bool,
    ) -> dict[str, float] | None:
        series = parameters.get(name)
        keys = (*MONTHS, "ANN")

        if not isinstance(series, dict):
            if required:
                raise SourceUnavailable(
                    f"NASA POWER returned no {name} at {latitude:.5f}, {longitude:.5f}."
                )

            return None

        missing = [key for key in keys if self._value(series.get(key)) is None]

        if missing:
            if required:
                raise SourceUnavailable(
                    f"NASA POWER has no {name} for {', '.join(missing)} "
                    f"at {latitude:.5f}, {longitude:.5f}."
                )

            return None

        return {key: self._value(series[key]) for key in keys}

    @staticmethod
    def _value(raw: object) -> float | None:
        if not isinstance(raw, (int, float)) or float(raw) <= MISSING_SENTINEL:
            return None

        return float(raw)


@lru_cache
def _power() -> NasaPowerSource:
    return NasaPowerSource()


def _no_climate_reason() -> str:
    if settings.climate_source == "none":
        return "climate context is disabled (ML_CLIMATE_SOURCE=none)"

    return "no climate dataset is configured"


async def sample_climate(latitude: float, longitude: float) -> SampleReport[ClimateSample]:
    """Sample climate normals for a point. Never raises."""
    return await resolve(
        [_power()] if settings.climate_source != "none" else [],
        latitude,
        longitude,
        fallback=False,
        empty_reason=_no_climate_reason(),
    )
