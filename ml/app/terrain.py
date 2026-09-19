"""Terrain products derived from a Digital Elevation Model.

The Computer Vision layer only sees what the camera sees: a canopy, bare soil,
water. It cannot tell a 3-degree floodplain from a 35-degree ridge, and that
difference decides whether an exposed patch becomes farmland, an erosion gully,
or a landslide scar after a disaster. Terrain is the missing half of the
picture, and it is what turns a land-cover estimate into an agriculture and
post-disaster restoration decision.

This module holds the *math* only: a small window of elevation samples goes in,
derived products come out (elevation, slope, aspect, hillshade, ruggedness).
It is pure, deterministic and dependency-free, so the same window always
produces the same numbers regardless of which DEM source (BIG DEMNAS, NASA
SRTM via Earth Engine, ...) sampled it. All network access lives in
`app.terrain_sources`.

Every formula here is the published standard, not an invention:

* slope / aspect - Horn (1981) 3x3 third-order finite difference, the same
  operator GDAL, ArcGIS and Earth Engine use.
* hillshade - the ESRI/GDAL illumination model, azimuth 315 (NW) and altitude
  45 degrees, normalised to 0..1.
* ruggedness - Riley et al. (1999) Terrain Ruggedness Index, the root sum of
  squared elevation differences between the centre cell and its 8 neighbours.
"""

from __future__ import annotations

from dataclasses import dataclass
from math import atan, atan2, cos, degrees, hypot, radians, sin, sqrt

# Slope (degrees) below which aspect is physically undefined: on ground flatter
# than this the DEM's own noise, not the landscape, decides the direction.
FLAT_SLOPE_DEG = 0.5

# Illumination geometry for the hillshade product. 315 degrees is the north-west
# convention shared by GDAL, ArcGIS and Earth Engine's `ee.Terrain.hillshade`.
HILLSHADE_AZIMUTH_DEG = 315.0
HILLSHADE_ALTITUDE_DEG = 45.0

# Ground metres per degree of latitude (WGS84 mean) and of longitude at the
# equator. Longitude is scaled by cos(latitude) at the sampling location.
METERS_PER_DEGREE_LAT = 110_540.0
METERS_PER_DEGREE_LON = 111_320.0


@dataclass(frozen=True)
class ElevationWindow:
    """A 3x3 elevation window sampled around a point.

    `values` is row-major and ordered north-west to south-east, matching the
    kernel layout the Horn operator expects::

        z1 z2 z3     north row
        z4 z5 z6     centre row (z5 = the sampled point)
        z7 z8 z9     south row

    `spacing_x_m` / `spacing_y_m` are the ground distances between adjacent
    samples, in metres. They differ on a geographic (lat/lon) grid because a
    degree of longitude shrinks with latitude.
    """

    values: tuple[float, ...]
    spacing_x_m: float
    spacing_y_m: float

    def __post_init__(self) -> None:
        if len(self.values) != 9:
            raise ValueError("An elevation window must hold exactly 9 values (3x3).")
        if self.spacing_x_m <= 0 or self.spacing_y_m <= 0:
            raise ValueError("Elevation window spacing must be positive.")

    @property
    def center(self) -> float:
        """Elevation of the sampled point itself, in metres."""
        return self.values[4]


@dataclass(frozen=True)
class TerrainMetrics:
    """Derived terrain products for one sampled point.

    Values are rounded on construction so identical windows always serialize
    to identical JSON, whichever source produced them.

    * `elevation_m` - metres above mean sea level.
    * `slope_deg` - 0 (flat) to 90 (vertical).
    * `aspect_deg` - compass direction the slope faces, 0 = north, clockwise.
      `None` on terrain flatter than `FLAT_SLOPE_DEG`, where aspect is undefined.
    * `hillshade` - illumination factor 0 (shadow) to 1 (fully lit).
    * `ruggedness_m` - Riley TRI: metres of elevation dispersion across the
      3x3 window. 0 on a plane, large on cliffs and gullies.
    """

    elevation_m: float
    slope_deg: float
    aspect_deg: float | None
    hillshade: float
    ruggedness_m: float

    def __post_init__(self) -> None:
        object.__setattr__(self, "elevation_m", round(self.elevation_m, 1))
        object.__setattr__(self, "slope_deg", round(self.slope_deg, 1))
        object.__setattr__(self, "hillshade", round(self.hillshade, 3))
        object.__setattr__(self, "ruggedness_m", round(self.ruggedness_m, 2))

        # The rule lives here, not in each source, so every DEM reports the same
        # contract: aspect is a compass bearing only where a slope exists.
        if self.aspect_deg is None or self.slope_deg < FLAT_SLOPE_DEG:
            object.__setattr__(self, "aspect_deg", None)
        else:
            object.__setattr__(self, "aspect_deg", round(self.aspect_deg, 1))


def derive_metrics(window: ElevationWindow) -> TerrainMetrics:
    """Derive all terrain products from a 3x3 elevation window."""
    z1, z2, z3, z4, z5, z6, z7, z8, z9 = window.values

    # Horn (1981): third-order finite difference, weighted 2 on the edges
    # perpendicular to the axis being differenced.
    dz_dx = ((z3 + 2 * z6 + z9) - (z1 + 2 * z4 + z7)) / (8 * window.spacing_x_m)
    dz_dy = ((z7 + 2 * z8 + z9) - (z1 + 2 * z2 + z3)) / (8 * window.spacing_y_m)

    gradient = hypot(dz_dx, dz_dy)
    slope_deg = degrees(atan(gradient))

    aspect_deg = _aspect(dz_dx, dz_dy)

    return TerrainMetrics(
        elevation_m=z5,
        slope_deg=slope_deg,
        aspect_deg=aspect_deg,
        hillshade=_hillshade(slope_deg, aspect_deg),
        ruggedness_m=sqrt(sum((value - z5) ** 2 for value in window.values)),
    )


def _aspect(dz_dx: float, dz_dy: float) -> float:
    """Compass aspect (0 = north, clockwise) from the Horn gradients."""
    if dz_dx == 0 and dz_dy == 0:
        return 0.0

    # atan2(dz/dy, -dz/dx) measures from east; the branches below rotate it into
    # the compass convention used by GDAL and ArcGIS.
    angle = degrees(atan2(dz_dy, -dz_dx))
    if angle < 0:
        return 90.0 - angle
    if angle > 90.0:
        return 450.0 - angle

    return 90.0 - angle


def _hillshade(slope_deg: float, aspect_deg: float) -> float:
    """ESRI/GDAL-style illumination factor in 0..1 for the given geometry."""
    zenith = radians(90.0 - HILLSHADE_ALTITUDE_DEG)
    azimuth = radians(360.0 - HILLSHADE_AZIMUTH_DEG + 90.0)
    slope = radians(slope_deg)
    aspect = radians(aspect_deg)

    lit = cos(zenith) * cos(slope) + sin(zenith) * sin(slope) * cos(azimuth - aspect)

    return min(1.0, max(0.0, lit))


def meters_per_degree_lon(latitude: float) -> float:
    """Ground metres covered by one degree of longitude at `latitude`."""
    return METERS_PER_DEGREE_LON * cos(radians(max(-89.9, min(89.9, latitude))))


def ground_spacing(latitude: float, pixel_size_deg: float) -> tuple[float, float]:
    """Ground spacing (x = east-west, y = north-south) of a lat/lon DEM pixel, in metres."""
    if pixel_size_deg <= 0:
        raise ValueError("Pixel size must be positive.")

    return pixel_size_deg * meters_per_degree_lon(latitude), pixel_size_deg * METERS_PER_DEGREE_LAT
