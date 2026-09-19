"""Request/response models shared across routers."""

from __future__ import annotations

from pydantic import BaseModel, ConfigDict, Field


class PredictRequest(BaseModel):
    """Payload sent by the Laravel backend for a single prediction."""

    features: list[float] = Field(
        ...,
        min_length=1,
        description="Ordered feature vector for the model.",
        examples=[[0.2, 1.4, 3.1]],
    )


class PredictResponse(BaseModel):
    """Model output returned to the caller."""

    prediction: float
    model_version: str

    model_config = ConfigDict(protected_namespaces=())


class LandCover(BaseModel):
    """Estimated share (percent) of each land-cover class."""

    dense_vegetation: float = 0.0
    sparse_vegetation: float = 0.0
    bare_soil: float = 0.0
    charred_soil: float = 0.0
    water: float = 0.0
    built_area: float = 0.0
    other: float = 0.0


class Confidence(BaseModel):
    """AI confidence scores (0-1) for the detection."""

    overall: float
    vegetation: float
    bare_soil: float
    water: float


class Segmentation(BaseModel):
    """Coarse land-cover grid for overlay rendering."""

    cols: int
    rows: int
    grid: list[list[str]]
    legend: dict[str, str]


class ImageInfo(BaseModel):
    """Dimensions of the analyzed source image, in pixels."""

    width: int
    height: int


class TerrainWindow(BaseModel):
    """The elevation neighbourhood the terrain products were derived from."""

    rows: int
    cols: int
    spacing_x_m: float
    spacing_y_m: float


class SourceDescriptor(BaseModel):
    """Provenance of a dataset sample."""

    id: str
    label: str
    dataset: str
    provider: str
    service_url: str
    portal_url: str


class TerrainProfile(BaseModel):
    """Derived terrain products for the surveyed point."""

    elevation_m: float
    slope_deg: float
    aspect_deg: float | None = Field(
        default=None,
        description="Compass bearing the slope faces (0 = north). Null on flat ground.",
    )
    hillshade: float = Field(description="Illumination factor, 0 (shadow) to 1 (fully lit).")
    ruggedness_m: float = Field(
        description="Riley Terrain Ruggedness Index: elevation dispersion across the window."
    )
    resolution_m: float
    window: TerrainWindow


class SampledPoint(BaseModel):
    """The coordinate the terrain was sampled at."""

    latitude: float
    longitude: float


class TerrainContext(BaseModel):
    """Terrain around the surveyed point, or the reason it is missing.

    A missing terrain context never fails an analysis: `available` is false and
    `reason` explains which source was tried and what it reported.
    """

    available: bool
    reason: str | None = None
    source: SourceDescriptor | None = None
    sampled_at: SampledPoint | None = None
    profile: TerrainProfile | None = None


class SoilTexture(BaseModel):
    """USDA texture fractions of a soil layer."""

    class_name: str = Field(description="USDA texture class, e.g. 'Clay Loam'.")
    sand_pct: float
    silt_pct: float
    clay_pct: float


class SoilProfile(BaseModel):
    """Topsoil chemistry and texture for the sampled point."""

    texture: SoilTexture
    subsoil_texture: SoilTexture
    ph: float
    organic_carbon_g_kg: float
    nitrogen_g_kg: float


class SoilContext(BaseModel):
    """Soil properties around the surveyed point, or the reason they are missing."""

    available: bool
    reason: str | None = None
    source: SourceDescriptor | None = None
    sampled_at: SampledPoint | None = None
    depth_cm: int | None = None
    resolution_m: float | None = None
    profile: SoilProfile | None = None


class MonthValue(BaseModel):
    """A named rainfall extreme."""

    month: str
    rainfall_mm: float


class ClimateNormals(BaseModel):
    """Long-term rainfall and temperature normals for the surveyed point."""

    annual_rainfall_mm: float
    monthly_rainfall_mm: dict[str, float]
    dry_months: int = Field(description="Months below 60 mm of rain (Oldeman/BMKG).")
    dry_month_names: list[str]
    wet_months: int = Field(description="Months above 100 mm of rain.")
    driest_month: MonthValue
    wettest_month: MonthValue
    mean_temperature_c: float
    mean_daily_max_c: float | None = Field(
        default=None,
        description="Long-term mean of the daily maximum; null when POWER does not answer with it.",
    )
    mean_humidity_pct: float | None = None
    topsoil_wetness_pct: float | None = Field(
        default=None, description="NASA POWER GWETTOP, as a percentage."
    )


class ClimateContext(BaseModel):
    """Climate normals around the surveyed point, or the reason they are missing."""

    available: bool
    reason: str | None = None
    source: SourceDescriptor | None = None
    sampled_at: SampledPoint | None = None
    profile: ClimateNormals | None = None


class ModelInfo(BaseModel):
    """Provenance of the model that produced the result."""

    name: str
    version: str
    task: str | None = Field(
        default=None, description="Detector task, e.g. 'detect'; null for the colour baseline."
    )
    weights: str | None = Field(
        default=None, description="Weights the detector loaded; null for the colour baseline."
    )
    device: str | None = Field(
        default=None, description="Device the detector ran on; null if none."
    )


class Detection(BaseModel):
    """One detected burn region, in normalized frame coordinates."""

    label: str = Field(description="Detector class, e.g. 'charred_soil'.")
    confidence: float = Field(description="Detector confidence, 0-1.")
    box: list[float] = Field(
        ...,
        min_length=4,
        max_length=4,
        description="Normalized [x, y, w, h] with 0 <= x,y and x+w, y+h <= 1.",
    )


class SeverityEvidence(BaseModel):
    """What the severity score was computed from, in percent of the frame."""

    charred_soil_pct: float = 0.0
    bare_soil_pct: float = 0.0
    vegetation_pct: float = Field(
        default=0.0, description="Unburned vegetation plus regrowth."
    )
    detections: dict[str, int] = Field(
        default_factory=dict, description="Boxes found per detector class; empty for the baseline."
    )


class FireSeverity(BaseModel):
    """Post-fire severity from the active detector, or from the colour fallback."""

    available: bool
    reason: str | None = Field(
        default=None,
        description="Why the score is a heuristic (missing weights / library); null when YOLO ran.",
    )
    level: str = Field(description="unburned | low | moderate | high.")
    label: str = Field(description="Display label for the level.")
    score: float = Field(description="Area-weighted severity, 0 (unburned) to 100 (fully charred).")
    confidence: float = Field(
        description="Detection confidence for YOLO; share of the frame carrying burn evidence "
        "for the colour fallback.",
    )
    method: str = Field(description="yolov8 | colour-heuristic.")
    evidence: SeverityEvidence


class LandAnalysisResponse(BaseModel):
    """Land-cover perception returned to the Laravel backend."""

    land_cover: LandCover
    confidence: Confidence
    segmentation: Segmentation | None = None
    detections: list[Detection] = Field(
        default_factory=list, description="Detected burn regions; empty for the colour baseline."
    )
    fire_severity: FireSeverity
    terrain: TerrainContext
    soil: SoilContext
    climate: ClimateContext
    image: ImageInfo
    model: ModelInfo

    model_config = ConfigDict(protected_namespaces=())
