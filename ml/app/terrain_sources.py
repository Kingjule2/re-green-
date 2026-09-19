"""DEM sources that sample elevation around a drone-survey point.

Two national/global Digital Elevation Models back the terrain context, plus the
option to run without any:

* ``demnas`` - **DEMNAS**, Badan Informasi Geospasial (BIG), Indonesia. Built
  from IFSAR (5 m), TERRASAR-X (5 m) and ALOS PALSAR (11.25 m) data and served
  as a 0.27 arc-second (~8 m) product through BIG's ArcGIS image service. It is
  the authoritative elevation model inside Indonesia, so it is preferred
  whenever the survey point falls inside its footprint.
  Portal: https://tanahair.indonesia.go.id/portal-web/unduh/demnas
  Service: https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer

* ``srtm`` - **NASA SRTM V3 (SRTMGL1)**, ~30 m, global (-56..60 lat) via Google
  Earth Engine asset ``USGS/SRTMGL1_003``. The fallback when a point sits
  outside DEMNAS coverage, and the only option outside Indonesia.
  Catalog: https://developers.google.com/earth-engine/datasets/catalog/USGS_SRTMGL1_003

Both sources hand back a :class:`~app.terrain.TerrainMetrics` for one point; the
derivation itself is shared and lives in `app.terrain`. Sampling never raises
into the request path: :func:`sample_terrain` always returns a report that says
either what was measured or why nothing was measured.
"""

from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass
from functools import lru_cache

import httpx

from app.config import settings
from app.sampling import (
    Sampler,
    SampleReport,
    SourceInfo,
    SourceUnavailable,
    describe,
    no_source_reason,
    resolve,
)
from app.terrain import ElevationWindow, TerrainMetrics, derive_metrics, ground_spacing

logger = logging.getLogger(__name__)

# Approximate DEMNAS footprint from BIG's published raster extent
# (XMin 94.75, YMin -11.25, XMax 141, YMax 6.25 in EPSG:4326).
DEMNAS_EXTENT = {"west": 94.75, "south": -11.25, "east": 141.0, "north": 6.25}

# DEMNAS pixel size, in degrees (0.27 arc-second), used when the service's own
# metadata cannot be read.
DEMNAS_DEFAULT_PIXEL_SIZE_DEG = 0.27 / 3600

# Number of echo values ArcGIS sends back for a single-band raster. Anything
# else means the service is rendering a multi-band product we cannot read as
# elevation.
IDENTIFY_BAND_SEPARATOR = ","

# Source ids mapped to their display labels, for coverage messages.
SOURCE_LABELS = {"demnas": "DEMNAS (BIG)", "srtm": "NASA SRTM (Earth Engine)"}


@dataclass(frozen=True)
class TerrainSample:
    """Terrain metrics plus the metadata the UI needs to attribute them."""

    metrics: TerrainMetrics
    source: SourceInfo
    latitude: float
    longitude: float
    resolution_m: float
    window_rows: int
    window_cols: int
    window_spacing_x_m: float
    window_spacing_y_m: float


class DemnasSource(Sampler[TerrainSample]):
    """BIG DEMNAS, sampled through the national ArcGIS image service.

    A DEM needs a neighbourhood to produce slope, so nine elevations are read
    around the point (one pixel apart, in the service's own pixel size) and the
    shared Horn/Riley math turns them into the derived products.
    """

    info = SourceInfo(
        id="demnas",
        label="DEMNAS (BIG)",
        dataset="DEMNAS - DEM Nasional Indonesia, 0.27 arc-second (~8 m)",
        provider="Badan Informasi Geospasial (BIG)",
        service_url="https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer",
        portal_url="https://tanahair.indonesia.go.id/portal-web/unduh/demnas",
    )

    def __init__(
        self,
        service_url: str | None = None,
        timeout: float = 20.0,
        transport: httpx.AsyncBaseTransport | None = None,
    ) -> None:
        self.service_url = (service_url or self.info.service_url).rstrip("/")
        self.timeout = timeout
        self._transport = transport
        self._pixel_size_deg: float | None = None

    def covers(self, latitude: float, longitude: float) -> bool:
        return (
            DEMNAS_EXTENT["west"] <= longitude <= DEMNAS_EXTENT["east"]
            and DEMNAS_EXTENT["south"] <= latitude <= DEMNAS_EXTENT["north"]
        )

    def unavailable_reason(self) -> str | None:
        return None

    async def sample(self, latitude: float, longitude: float) -> TerrainSample:
        pixel_size = await self._pixel_size()
        spacing_x, spacing_y = ground_spacing(latitude, pixel_size)

        async with httpx.AsyncClient(timeout=self.timeout, transport=self._transport) as client:
            values = await asyncio.gather(
                *(
                    self._identify(
                        client,
                        latitude + row_offset * pixel_size,
                        longitude + col_offset * pixel_size,
                    )
                    for row_offset in (1, 0, -1)
                    for col_offset in (-1, 0, 1)
                )
            )

        window = ElevationWindow(values=tuple(values), spacing_x_m=spacing_x, spacing_y_m=spacing_y)

        return TerrainSample(
            metrics=derive_metrics(window),
            source=self.info,
            latitude=latitude,
            longitude=longitude,
            resolution_m=round(spacing_x, 1),
            window_rows=3,
            window_cols=3,
            window_spacing_x_m=round(spacing_x, 1),
            window_spacing_y_m=round(spacing_y, 1),
        )

    async def _pixel_size(self) -> float:
        """The service's pixel size in degrees, read from its metadata once."""
        if self._pixel_size_deg is not None:
            return self._pixel_size_deg

        self._pixel_size_deg = DEMNAS_DEFAULT_PIXEL_SIZE_DEG

        try:
            async with httpx.AsyncClient(timeout=self.timeout, transport=self._transport) as client:
                response = await client.get(self.service_url, params={"f": "json"})
                response.raise_for_status()
                metadata = response.json()
        except (httpx.HTTPError, ValueError) as exc:
            logger.warning(
                "DEMNAS metadata unavailable, assuming %.5f deg pixels: %s",
                self._pixel_size_deg,
                exc,
            )

            return self._pixel_size_deg

        pixel_size = metadata.get("pixelSizeX") or metadata.get("pixelSizeY")
        if isinstance(pixel_size, (int, float)) and pixel_size > 0:
            self._pixel_size_deg = float(pixel_size)

        return self._pixel_size_deg

    async def _identify(
        self, client: httpx.AsyncClient, latitude: float, longitude: float
    ) -> float:
        """Read one elevation value from the image service."""
        try:
            response = await client.get(
                f"{self.service_url}/identify",
                params={
                    "geometry": (
                        f'{{"x":{longitude},"y":{latitude},"spatialReference":{{"wkid":4326}}}}'
                    ),
                    "geometryType": "esriGeometryPoint",
                    "returnGeometry": "false",
                    "f": "json",
                },
            )
            response.raise_for_status()
            payload = response.json()
        except (httpx.HTTPError, ValueError) as exc:
            raise SourceUnavailable(f"DEMNAS could not be reached: {describe(exc)}") from exc

        value = payload.get("value")

        if value in (None, "", "NoData"):
            raise SourceUnavailable(
                f"DEMNAS has no elevation value at {latitude:.5f}, {longitude:.5f}."
            )

        text = str(value)
        if IDENTIFY_BAND_SEPARATOR in text:
            raise SourceUnavailable(
                "DEMNAS returned a multi-band render, which is not a raw elevation sample."
            )

        try:
            return float(text)
        except ValueError as exc:
            raise SourceUnavailable(
                f"DEMNAS returned an unreadable elevation value: {text!r}"
            ) from exc


class EarthEngineSrtmSource(Sampler[TerrainSample]):
    """NASA SRTM (SRTMGL1, ~30 m) served by Google Earth Engine.

    The derivation runs on Earth Engine's side: `ee.Terrain` produces slope,
    aspect and hillshade, and a 3x3 neighbourhood standard deviation stands in
    for Riley ruggedness because Earth Engine has no single reducer for it.
    """

    info = SourceInfo(
        id="srtm",
        label="NASA SRTM (Earth Engine)",
        dataset="NASA SRTM V3 / SRTMGL1, 1 arc-second (~30 m)",
        provider="NASA / USGS / JPL-Caltech via Google Earth Engine",
        service_url="https://developers.google.com/earth-engine/datasets/catalog/USGS_SRTMGL1_003",
        portal_url="https://code.earthengine.google.com/",
    )

    resolution_m = 30.0

    def __init__(
        self,
        asset: str | None = None,
        project: str | None = None,
        service_account: str | None = None,
        private_key_file: str | None = None,
        timeout: float | None = None,
    ) -> None:
        self.asset = asset or settings.gee_srtm_asset
        self.project = project or settings.gee_project
        self.service_account = service_account or settings.gee_service_account
        self.private_key_file = private_key_file or settings.gee_private_key_file
        self.timeout = timeout or settings.gee_timeout
        self._initialized = False

    def covers(self, latitude: float, longitude: float) -> bool:
        # SRTM's near-global coverage stops at 60 degrees north and 56 south.
        return -56.0 <= latitude <= 60.0

    def unavailable_reason(self) -> str | None:
        try:
            import ee  # noqa: F401
        except ImportError:
            return (
                "the Earth Engine client is not installed "
                "(pip install earthengine-api), so the SRTM fallback is off"
            )

        if not (self.service_account and self.private_key_file):
            return (
                "Earth Engine credentials are not configured "
                "(set ML_GEE_SERVICE_ACCOUNT and ML_GEE_PRIVATE_KEY_FILE)"
            )

        return None

    async def sample(self, latitude: float, longitude: float) -> TerrainSample:
        try:
            metrics = await asyncio.wait_for(
                asyncio.to_thread(self._sample_sync, latitude, longitude),
                timeout=self.timeout,
            )
        except TimeoutError as exc:
            raise SourceUnavailable(
                f"Earth Engine did not answer within {self.timeout:.0f}s."
            ) from exc

        return TerrainSample(
            metrics=metrics,
            source=self.info,
            latitude=latitude,
            longitude=longitude,
            resolution_m=self.resolution_m,
            window_rows=3,
            window_cols=3,
            window_spacing_x_m=self.resolution_m,
            window_spacing_y_m=self.resolution_m,
        )

    def _sample_sync(self, latitude: float, longitude: float) -> TerrainMetrics:
        try:
            import ee
        except ImportError as exc:  # pragma: no cover - guarded by unavailable_reason
            raise SourceUnavailable(str(exc)) from exc

        try:
            self._initialize(ee)
            point = ee.Geometry.Point([longitude, latitude])
            image = self._products(ee)

            values = image.reduceRegion(
                reducer=ee.Reducer.first(),
                geometry=point,
                scale=self.resolution_m,
                bestEffort=True,
                maxPixels=1_000_000,
            ).getInfo()
        except SourceUnavailable:
            raise
        except Exception as exc:  # noqa: BLE001 - Earth Engine raises a wide range of types
            raise SourceUnavailable(f"Earth Engine could not sample SRTM: {exc}") from exc

        return self._to_metrics(values, latitude, longitude)

    def _initialize(self, ee) -> None:
        if self._initialized:
            return

        credentials = ee.ServiceAccountCredentials(self.service_account, self.private_key_file)
        ee.Initialize(credentials=credentials, project=self.project or None)
        self._initialized = True

    def _products(self, ee):
        """The SRTM stack: elevation plus the three classical terrain products."""
        dem = ee.Image(self.asset).select("elevation")

        return ee.Image.cat(
            [
                dem.rename("elevation"),
                ee.Terrain.slope(dem).rename("slope"),
                ee.Terrain.aspect(dem).rename("aspect"),
                ee.Terrain.hillshade(dem, azimuth=315.0, elevation=45.0)
                .divide(255)
                .rename("hillshade"),
                dem.reduceNeighborhood(
                    reducer=ee.Reducer.stdDev(),
                    kernel=ee.Kernel.square(radius=1, units="pixels"),
                ).rename("ruggedness"),
            ]
        )

    def _to_metrics(self, values: dict, latitude: float, longitude: float) -> TerrainMetrics:
        missing = [
            name
            for name in ("elevation", "slope", "aspect", "hillshade", "ruggedness")
            if values.get(name) is None
        ]
        if missing:
            raise SourceUnavailable(
                f"SRTM returned no data at {latitude:.5f}, {longitude:.5f} "
                f"(missing: {', '.join(missing)})."
            )

        return TerrainMetrics(
            elevation_m=float(values["elevation"]),
            slope_deg=float(values["slope"]),
            aspect_deg=float(values["aspect"]),
            hillshade=float(values["hillshade"]),
            ruggedness_m=float(values["ruggedness"]),
        )


@lru_cache
def _demnas() -> DemnasSource:
    return DemnasSource(settings.demnas_service_url, settings.terrain_timeout)


@lru_cache
def _srtm() -> EarthEngineSrtmSource:
    return EarthEngineSrtmSource()


def _candidates(latitude: float, longitude: float, mode: str) -> list[Sampler[TerrainSample]]:
    """Ordered list of sources to try for a point, best (highest resolution) first."""
    if mode == "none":
        return []

    demnas = _demnas()
    srtm = _srtm()

    # A source that does not publish data for the point is never asked: an
    # out-of-coverage failure reports "no DEM covers this" instead of an
    # HTTP error that reads like an outage.
    if mode == "demnas":
        return [demnas] if demnas.covers(latitude, longitude) else []
    if mode == "srtm":
        return [srtm] if srtm.covers(latitude, longitude) else []

    # auto: the national 8 m model wins inside Indonesia; SRTM covers the rest.
    return [source for source in (demnas, srtm) if source.covers(latitude, longitude)]


async def sample_terrain(latitude: float, longitude: float) -> SampleReport[TerrainSample]:
    """Sample terrain for a point, preferring DEMNAS and falling back to SRTM.

    Never raises: an unreachable or unconfigured source is reported as an
    unavailable terrain context, together with the reason, so the caller can
    still return the image analysis.
    """
    mode = settings.terrain_source
    candidates = _candidates(latitude, longitude, mode)

    report = await resolve(
        candidates,
        latitude,
        longitude,
        fallback=settings.terrain_fallback,
        empty_reason=no_source_reason(
            mode,
            f"{latitude:.5f}, {longitude:.5f}",
            context="terrain context",
            env_var="ML_TERRAIN_SOURCE",
            noun="DEM",
            pinned_label=SOURCE_LABELS.get,
        ),
    )

    if not report.available:
        logger.warning("Terrain sampling failed: %s", report.reason)

    return report
