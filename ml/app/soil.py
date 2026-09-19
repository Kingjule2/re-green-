"""Soil properties for a survey point, from ISRIC SoilGrids.

"Kandungan tanah" is not something a drone camera can see, but it decides
everything about what a field can grow: texture governs how water and roots
move, pH governs which nutrients are available, and organic carbon is the
difference between topsoil and dust.

Source: **ISRIC SoilGrids v2.0** (global, 250 m), served as a REST point query.
Every property comes back as an integer in the dataset's mapped units, and the
response carries the `d_factor` needed to convert it to the target unit — that
conversion is applied here, never guessed.

The texture class is derived locally with the USDA texture triangle, because the
soil triangle is what crop requirements are written against (a crop that wants
"Clay Loam" needs a name, not three fractions).

Portal: https://soilgrids.org
Docs:   https://www.isric.org/explore/soilgrids
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

SOILGRIDS_URL = "https://rest.isric.org/soilgrids/v2.0/properties/query"

# Properties requested, in SoilGrids' own vocabulary.
PROPERTIES = ("clay", "sand", "silt", "soc", "phh2o", "nitrogen")

# The layers the crop engine scores against, plus the layer below it for
# drainage and rooting context.
TOPSOIL_DEPTH = "0-5cm"
SUBSOIL_DEPTH = "5-15cm"

# SoilGrids' own uncertainty is far larger than this, but the three texture
# fractions must at least describe a plausible soil before we classify it.
TEXTURE_SUM_TOLERANCE_PCT = 5.0

# Depth label written on the sample, for provenance.
TOPSOIL_DEPTH_CM = 5

SOURCE = SourceInfo(
    id="soilgrids",
    label="ISRIC SoilGrids",
    dataset="ISRIC SoilGrids v2.0 (clay, silt, sand, SOC, pH, nitrogen; 250 m)",
    provider="ISRIC - World Soil Information",
    service_url=SOILGRIDS_URL,
    portal_url="https://soilgrids.org",
)


@dataclass(frozen=True)
class SoilTexture:
    """The three USDA texture fractions, in percent by weight."""

    sand_pct: float
    silt_pct: float
    clay_pct: float
    class_name: str


@dataclass(frozen=True)
class SoilProfile:
    """Topsoil chemistry and texture for a point."""

    texture: SoilTexture
    ph: float
    organic_carbon_g_kg: float
    nitrogen_g_kg: float
    subsoil_texture: SoilTexture


@dataclass(frozen=True)
class SoilSample:
    """A soil profile plus the metadata the UI needs to attribute it."""

    profile: SoilProfile
    source: SourceInfo
    latitude: float
    longitude: float
    depth_cm: int
    resolution_m: float


def usda_texture_class(sand_pct: float, silt_pct: float, clay_pct: float) -> str:
    """USDA soil texture class from sand/silt/clay percentages.

    The twelve classes come from the USDA texture triangle, evaluated in its
    documented order: the narrow classes are tested before the broad ones, so
    a composition on a boundary gets the specific name rather than "loam".
    """
    sand, silt, clay = sand_pct, silt_pct, clay_pct

    if silt + 1.5 * clay < 15:
        return "Sand"
    if silt + 1.5 * clay >= 15 and silt + 2 * clay < 30:
        return "Loamy Sand"
    if (clay >= 7 and clay < 20 and sand > 52 and silt < 50) or (
        clay < 7 and silt < 50 and sand > 43
    ):
        return "Sandy Loam"
    if clay >= 7 and clay < 27 and silt >= 28 and silt < 50 and sand <= 52:
        return "Loam"
    if (silt >= 50 and clay >= 12 and clay < 27) or (silt >= 50 and silt < 80 and clay < 12):
        return "Silt Loam"
    if silt >= 80 and clay < 12:
        return "Silt"
    if clay >= 20 and clay < 35 and silt < 28 and sand > 45:
        return "Sandy Clay Loam"
    if clay >= 27 and clay < 40 and sand > 20 and sand <= 45:
        return "Clay Loam"
    if clay >= 27 and clay < 40 and sand <= 20:
        return "Silty Clay Loam"
    if clay >= 35 and sand > 45:
        return "Sandy Clay"
    if clay >= 40 and silt >= 40:
        return "Silty Clay"

    return "Clay"


def texture_from_fractions(sand_pct: float, silt_pct: float, clay_pct: float) -> SoilTexture:
    """Normalize the fractions to 100% and name the resulting texture class.

    SoilGrids' three fractions do not always sum to exactly 100 (independent
    predictions), so they are scaled before classification and the original
    values are kept on the result.
    """
    total = sand_pct + silt_pct + clay_pct
    if total <= 0:
        raise ValueError("Soil texture fractions must sum to a positive value.")

    sand = sand_pct / total * 100
    silt = silt_pct / total * 100
    clay = clay_pct / total * 100

    return SoilTexture(
        sand_pct=round(sand_pct, 1),
        silt_pct=round(silt_pct, 1),
        clay_pct=round(clay_pct, 1),
        class_name=usda_texture_class(sand, silt, clay),
    )


class SoilGridsSource(Sampler[SoilSample]):
    """SoilGrids point query, sampled at 0-5 cm (topsoil) and 5-15 cm (subsoil)."""

    info = SOURCE

    def __init__(
        self,
        base_url: str | None = None,
        timeout: float | None = None,
        transport: httpx.AsyncBaseTransport | None = None,
    ) -> None:
        self.base_url = (base_url or settings.soil_grids_url).rstrip("/")
        self.timeout = timeout or settings.soil_timeout
        self._transport = transport
        # Soil properties do not change between surveys: cache by coordinate.
        self._cache: BoundedCache[SoilSample] = BoundedCache(settings.point_cache_size)

    def unavailable_reason(self) -> str | None:
        return None

    async def sample(self, latitude: float, longitude: float) -> SoilSample:
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
                    params=[
                        ("lon", longitude),
                        ("lat", latitude),
                        *(("property", prop) for prop in PROPERTIES),
                        ("depth", TOPSOIL_DEPTH),
                        ("depth", SUBSOIL_DEPTH),
                        ("value", "mean"),
                    ],
                )
                response.raise_for_status()

                return response.json()
        except (httpx.HTTPError, ValueError) as exc:
            raise SourceUnavailable(f"SoilGrids could not be reached: {describe(exc)}") from exc

    def _parse(self, payload: dict, latitude: float, longitude: float) -> SoilSample:
        layers = payload.get("properties", {}).get("layers")
        if not isinstance(layers, list) or not layers:
            raise SourceUnavailable(
                f"SoilGrids returned no soil properties at {latitude:.5f}, {longitude:.5f}."
            )

        values: dict[tuple[str, str], float] = {}

        for layer in layers:
            name = layer.get("name")
            factor = layer.get("unit_measure", {}).get("d_factor")
            if not isinstance(name, str) or not isinstance(factor, (int, float)) or factor == 0:
                continue

            for depth in layer.get("depths", []):
                mean = (depth.get("values") or {}).get("mean")
                if mean is None:
                    continue

                values[(name, str(depth.get("label")))] = float(mean) / float(factor)

        topsoil = self._texture(values, TOPSOIL_DEPTH, latitude, longitude)
        subsoil = self._texture(values, SUBSOIL_DEPTH, latitude, longitude)

        ph = values.get(("phh2o", TOPSOIL_DEPTH))
        carbon = values.get(("soc", TOPSOIL_DEPTH))
        nitrogen = values.get(("nitrogen", TOPSOIL_DEPTH))

        if ph is None or carbon is None or nitrogen is None:
            raise SourceUnavailable(
                f"SoilGrids has no topsoil chemistry at {latitude:.5f}, {longitude:.5f}."
            )

        return SoilSample(
            profile=SoilProfile(
                texture=topsoil,
                ph=round(ph, 1),
                organic_carbon_g_kg=round(carbon, 1),
                nitrogen_g_kg=round(nitrogen, 2),
                subsoil_texture=subsoil,
            ),
            source=self.info,
            latitude=latitude,
            longitude=longitude,
            depth_cm=TOPSOIL_DEPTH_CM,
            resolution_m=250.0,
        )

    def _texture(
        self, values: dict[tuple[str, str], float], depth: str, latitude: float, longitude: float
    ) -> SoilTexture:
        fractions = {
            prop: values.get((prop, depth)) for prop in ("sand", "silt", "clay")
        }

        if any(value is None for value in fractions.values()):
            raise SourceUnavailable(
                f"SoilGrids has no texture fractions for {depth} "
                f"at {latitude:.5f}, {longitude:.5f}."
            )

        sand, silt, clay = (float(value) for value in fractions.values())

        if abs(sand + silt + clay - 100.0) > TEXTURE_SUM_TOLERANCE_PCT:
            raise SourceUnavailable(
                f"SoilGrids texture fractions at {latitude:.5f}, {longitude:.5f} do not "
                f"describe a soil (sand+silt+clay = {sand + silt + clay:.1f}%)."
            )

        return texture_from_fractions(sand, silt, clay)


@lru_cache
def _soilgrids() -> SoilGridsSource:
    return SoilGridsSource()


def _no_soil_reason() -> str:
    if settings.soil_source == "none":
        return "soil context is disabled (ML_SOIL_SOURCE=none)"

    return "no soil dataset is configured"


async def sample_soil(latitude: float, longitude: float) -> SampleReport[SoilSample]:
    """Sample soil properties for a point. Never raises."""
    return await resolve(
        [_soilgrids()] if settings.soil_source != "none" else [],
        latitude,
        longitude,
        fallback=False,
        empty_reason=_no_soil_reason(),
    )
