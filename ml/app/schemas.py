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


class ModelInfo(BaseModel):
    """Provenance of the model that produced the result."""

    name: str
    version: str


class LandAnalysisResponse(BaseModel):
    """Land-cover perception returned to the Laravel backend."""

    land_cover: LandCover
    confidence: Confidence
    segmentation: Segmentation | None = None
    image: ImageInfo
    model: ModelInfo

    model_config = ConfigDict(protected_namespaces=())
