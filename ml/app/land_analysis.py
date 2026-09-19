"""Land-cover analysis models.

The product treats the Computer Vision layer as swappable: the rest of the
system depends on the *shape* of the result, not on any particular model. This
module defines that abstraction (`LandAnalysisModel`) plus a dependency-light
baseline (`ColorHistogramModel`), and `get_model()` selects the trained YOLOv8
burn detector (`app.yolo.YoloBurnModel`) when its weights and `ultralytics` are
actually present.

The baseline performs genuine (if coarse) colour-based segmentation over a
downscaled copy of the image. Its output is always an AI-derived *estimate*,
never ground truth.

Post-fire severity and the burn detections are part of the result contract too,
so `analyze()` returns them for every model: the detector fills them from its
boxes, the baseline applies the identical formula to pixel fractions (see
`app.severity`).
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from collections import Counter
from dataclasses import dataclass, field
from functools import lru_cache
from io import BytesIO

from PIL import Image, UnidentifiedImageError

from app.severity import COVER_CLASSES, balance_cover, severity_from_cover

# Ordered land-cover classes and their display colours. Kept in sync with the
# Laravel LandIntelligenceEngine so overlays and legends match across services,
# and with `severity.COVER_CLASSES`, which uses the same order (tests assert it).
CLASS_COLORS: dict[str, str] = {
    "dense_vegetation": "#15803d",
    "sparse_vegetation": "#84cc16",
    "bare_soil": "#b45309",
    "charred_soil": "#3f3f46",
    "water": "#0ea5e9",
    "built_area": "#78716c",
    "other": "#94a3b8",
}

# Working resolution for classification and the coarse overlay grid.
THUMB_MAX = 180
_GRID_COLS = 28

#: Explanation reported in `fire_severity.reason` when a model has no detector
#: and the caller knows nothing more specific. `get_model()` always knows more:
#: it passes the concrete cause (missing weights / missing ultralytics) along.
HEURISTIC_REASON = (
    "fire severity is derived from colour-classified pixel fractions (heuristic), "
    "not from a trained YOLOv8 burn detector"
)


class ImageDecodeError(ValueError):
    """Raised when the uploaded bytes cannot be read as an image."""


class ModelUnavailableError(RuntimeError):
    """Raised when a model cannot run: weights or its library are missing."""


@dataclass(frozen=True)
class ClassifiedImage:
    """Per-pixel classification of a downscaled image."""

    keys: list[str]
    width: int
    height: int
    source_width: int
    source_height: int
    #: Detector boxes in the API's normalized shape; empty for detector-less
    #: models such as the colour baseline.
    detections: list[dict] = field(default_factory=list)


class LandAnalysisModel(ABC):
    """Contract for any land-analysis model.

    `analyze` is a template method that runs the pipeline
    (classify -> calculate_metrics -> segment -> severity); concrete models
    implement the individual steps.
    """

    name: str = "abstract"
    version: str = "0.0.0"

    # Detector provenance, reported in the `model` block; left empty by the
    # colour baseline, which has no task, weights or device.
    task: str | None = None
    weights: str | None = None
    device: str | None = None

    #: Why this model's severity is a heuristic. Only used by models without a
    #: detector; `get_model()` replaces it with the concrete cause.
    heuristic_reason: str = HEURISTIC_REASON

    def analyze(self, image_bytes: bytes) -> dict:
        """Run the full pipeline and return a normalized result dict."""
        classified = self.classify(image_bytes)
        cover, confidence = self.calculate_metrics(classified)
        segmentation = self.segment(classified)

        return {
            "land_cover": cover,
            "confidence": confidence,
            "segmentation": segmentation,
            "detections": classified.detections,
            "fire_severity": self.fire_severity(classified, cover),
            "image": {"width": classified.source_width, "height": classified.source_height},
            "model": {
                "name": self.name,
                "version": self.version,
                "task": self.task,
                "weights": self.weights,
                "device": self.device,
            },
        }

    @abstractmethod
    def classify(self, image_bytes: bytes) -> ClassifiedImage:
        """Classify each pixel of the (downscaled) image into a land-cover class."""

    @abstractmethod
    def calculate_metrics(
        self, classified: ClassifiedImage
    ) -> tuple[dict[str, float], dict[str, float]]:
        """Return (land_cover percentages, confidence scores)."""

    def segment(self, classified: ClassifiedImage) -> dict:
        """Coarse overlay grid: the majority class of each cell.

        Works on any model's per-pixel raster, which is why it lives here: the
        colour baseline and the detector both paint pixels and then get the same
        28x28 grid the contract specifies.
        """
        cols = max(1, min(_GRID_COLS, classified.width))
        rows = max(1, min(_GRID_COLS, classified.height))

        cells: list[Counter] = [Counter() for _ in range(cols * rows)]
        for index, key in enumerate(classified.keys):
            x = index % classified.width
            y = index // classified.width
            col = min(cols - 1, x * cols // classified.width)
            row = min(rows - 1, y * rows // classified.height)
            cells[row * cols + col][key] += 1

        grid = [
            [self._majority(cells[row * cols + col]) for col in range(cols)]
            for row in range(rows)
        ]

        return {"cols": cols, "rows": rows, "grid": grid, "legend": dict(CLASS_COLORS)}

    @staticmethod
    def _majority(counter: Counter) -> str:
        if not counter:
            return "other"
        return counter.most_common(1)[0][0]

    def fire_severity(self, classified: ClassifiedImage, cover: dict[str, float]) -> dict:
        """Default severity for models without a detector.

        The burn weights are applied to the colour classifier's pixel fractions
        — the same formula the detector's boxes go through — so the two paths
        stay comparable and a score always means the same thing.
        """
        return {
            "available": True,
            "reason": self.heuristic_reason,
            "method": "colour-heuristic",
            **severity_from_cover(cover),
        }


class ColorHistogramModel(LandAnalysisModel):
    """Deterministic colour-based land-cover baseline built on Pillow."""

    name = "color-histogram"
    version = "0.1.0"

    def __init__(self, heuristic_reason: str | None = None) -> None:
        if heuristic_reason is not None:
            self.heuristic_reason = heuristic_reason

    def classify(self, image_bytes: bytes) -> ClassifiedImage:
        try:
            image = Image.open(BytesIO(image_bytes))
            image.load()
        except (UnidentifiedImageError, OSError) as exc:
            raise ImageDecodeError("The uploaded file could not be read as an image.") from exc

        source_width, source_height = image.size

        thumb = image.convert("RGB")
        thumb.thumbnail((THUMB_MAX, THUMB_MAX))
        hsv = thumb.convert("HSV")
        width, height = hsv.size

        # tobytes() yields H,S,V per pixel in row-major order (fast, not deprecated).
        raw = hsv.tobytes()
        keys = [
            self._classify_pixel(raw[i] / 255.0, raw[i + 1] / 255.0, raw[i + 2] / 255.0)
            for i in range(0, len(raw), 3)
        ]

        return ClassifiedImage(
            keys=keys,
            width=width,
            height=height,
            source_width=source_width,
            source_height=source_height,
        )

    def calculate_metrics(
        self, classified: ClassifiedImage
    ) -> tuple[dict[str, float], dict[str, float]]:
        total = max(1, len(classified.keys))
        counts = Counter(classified.keys)

        cover = balance_cover(
            {key: counts.get(key, 0) / total * 100.0 for key in COVER_CLASSES}
        )

        classified_fraction = 1.0 - counts.get("other", 0) / total
        confidence = {
            "overall": self._confidence(0.55, classified_fraction, 0.98),
            "vegetation": self._confidence(0.60, classified_fraction, 0.98),
            "bare_soil": self._confidence(0.55, classified_fraction, 0.95),
            "water": self._confidence(0.50, classified_fraction, 0.95),
        }

        return cover, confidence

    @staticmethod
    def _confidence(base: float, classified_fraction: float, ceiling: float) -> float:
        return round(min(ceiling, max(0.5, base + 0.4 * classified_fraction)), 2)

    @staticmethod
    def _classify_pixel(h: float, s: float, v: float) -> str:
        """Map a single HSV pixel (each channel in 0..1) to a land-cover class."""
        if v < 0.12:
            # Too dark to classify reliably (deep shadow, unlit canopy).
            return "other"
        if 0.12 <= v <= 0.45 and s <= 0.45 and (h <= 0.17 or h >= 0.90 or s <= 0.18):
            # Charred soil / ash: burnt ground is dark and desaturated, warm when
            # the mineral soil shows through (`h <= 0.17` reds and browns) and
            # effectively colourless when it is pure soot (`s <= 0.18`). The
            # three guards keep it off its neighbours:
            #   * `v >= 0.12` leaves the deep-shadow floor above to `other`;
            #   * `s <= 0.45` leaves dark saturated greens to the vegetation
            #     branch below, so regrowth is never counted as burnt;
            #   * a dark pixel with a clear colour cast is not ash (`h` outside
            #     the warm/neutral range while `s > 0.18`), i.e. blue-grey canopy
            #     shade stays out of the burnt fraction;
            #   * `v <= 0.45` keeps the brighter orange/brown soils (e.g. the
            #     bare_soil legend colour, v ~ 0.71) in `bare_soil`, which the
            #     warm-hue branch below claims.
            return "charred_soil"
        if s < 0.12:
            # Bright achromatic: built structures / bare rock. Dark achromatic
            # pixels were already claimed by the charred-soil rule above.
            return "built_area"
        if 0.17 <= h <= 0.47:
            # Green band. Saturated, mid-value greens read as dense canopy.
            if s >= 0.35 and v <= 0.78:
                return "dense_vegetation"
            return "sparse_vegetation"
        if 0.47 < h <= 0.72:
            return "water"
        if h <= 0.16 or h >= 0.95:
            # Warm hues (orange/brown/red) read as exposed soil.
            return "bare_soil"
        return "other"


@lru_cache
def get_model() -> LandAnalysisModel:
    """Return the active land-analysis model (cached).

    The trained YOLOv8 burn detector wins when it can actually run; otherwise the
    colour baseline takes over and carries the concrete reason (weights path and
    how to train them) into `fire_severity.reason`, so a missing model is
    visible to the user instead of silent.

    `app.yolo` is imported inside the function because it imports this module:
    loading it at module level would create an import cycle.
    """
    from app.yolo import YoloBurnModel

    try:
        return YoloBurnModel.create()
    except ModelUnavailableError as exc:
        return ColorHistogramModel(heuristic_reason=str(exc))
