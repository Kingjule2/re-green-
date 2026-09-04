"""Land-cover analysis models.

The product treats the Computer Vision layer as swappable: the rest of the
system depends on the *shape* of the result, not on any particular model. This
module defines that abstraction (`LandAnalysisModel`) plus a dependency-light
baseline (`ColorHistogramModel`) so the pipeline runs end-to-end before a
trained segmentation model (YOLO/U-Net/DeepLab/SegFormer) is introduced.

The baseline performs genuine (if coarse) colour-based segmentation over a
downscaled copy of the image. Its output is always an AI-derived *estimate*,
never ground truth.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from collections import Counter
from dataclasses import dataclass
from functools import lru_cache
from io import BytesIO

from PIL import Image, UnidentifiedImageError

# Ordered land-cover classes and their display colours. Kept in sync with the
# Laravel LandIntelligenceEngine so overlays and legends match across services.
CLASS_COLORS: dict[str, str] = {
    "dense_vegetation": "#15803d",
    "sparse_vegetation": "#84cc16",
    "bare_soil": "#b45309",
    "water": "#0ea5e9",
    "built_area": "#78716c",
    "other": "#94a3b8",
}

# Working resolution for classification and the coarse overlay grid.
_THUMB_MAX = 180
_GRID_COLS = 28


class ImageDecodeError(ValueError):
    """Raised when the uploaded bytes cannot be read as an image."""


@dataclass(frozen=True)
class ClassifiedImage:
    """Per-pixel classification of a downscaled image."""

    keys: list[str]
    width: int
    height: int
    source_width: int
    source_height: int


class LandAnalysisModel(ABC):
    """Contract for any land-analysis model.

    `analyze` is a template method that runs the pipeline
    (classify -> calculate_metrics -> segment); concrete models implement the
    individual steps.
    """

    name: str = "abstract"
    version: str = "0.0.0"

    def analyze(self, image_bytes: bytes) -> dict:
        """Run the full pipeline and return a normalized result dict."""
        classified = self.classify(image_bytes)
        cover, confidence = self.calculate_metrics(classified)
        segmentation = self.segment(classified)

        return {
            "land_cover": cover,
            "confidence": confidence,
            "segmentation": segmentation,
            "image": {"width": classified.source_width, "height": classified.source_height},
            "model": {"name": self.name, "version": self.version},
        }

    @abstractmethod
    def classify(self, image_bytes: bytes) -> ClassifiedImage:
        """Classify each pixel of the (downscaled) image into a land-cover class."""

    @abstractmethod
    def calculate_metrics(
        self, classified: ClassifiedImage
    ) -> tuple[dict[str, float], dict[str, float]]:
        """Return (land_cover percentages, confidence scores)."""

    @abstractmethod
    def segment(self, classified: ClassifiedImage) -> dict:
        """Return a coarse segmentation grid for overlay rendering."""


class ColorHistogramModel(LandAnalysisModel):
    """Deterministic colour-based land-cover baseline built on Pillow."""

    name = "color-histogram"
    version = "0.1.0"

    def classify(self, image_bytes: bytes) -> ClassifiedImage:
        try:
            image = Image.open(BytesIO(image_bytes))
            image.load()
        except (UnidentifiedImageError, OSError) as exc:
            raise ImageDecodeError("The uploaded file could not be read as an image.") from exc

        source_width, source_height = image.size

        thumb = image.convert("RGB")
        thumb.thumbnail((_THUMB_MAX, _THUMB_MAX))
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

        cover = {key: round(counts.get(key, 0) / total * 100, 1) for key in CLASS_COLORS}

        classified_fraction = 1.0 - counts.get("other", 0) / total
        confidence = {
            "overall": self._confidence(0.55, classified_fraction, 0.98),
            "vegetation": self._confidence(0.60, classified_fraction, 0.98),
            "bare_soil": self._confidence(0.55, classified_fraction, 0.95),
            "water": self._confidence(0.50, classified_fraction, 0.95),
        }

        return cover, confidence

    def segment(self, classified: ClassifiedImage) -> dict:
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
    def _confidence(base: float, classified_fraction: float, ceiling: float) -> float:
        return round(min(ceiling, max(0.5, base + 0.4 * classified_fraction)), 2)

    @staticmethod
    def _majority(counter: Counter) -> str:
        if not counter:
            return "other"
        return counter.most_common(1)[0][0]

    @staticmethod
    def _classify_pixel(h: float, s: float, v: float) -> str:
        """Map a single HSV pixel (each channel in 0..1) to a land-cover class."""
        if v < 0.12:
            # Too dark to classify reliably (deep shadow).
            return "other"
        if s < 0.12:
            # Achromatic: built structures / bare rock when bright, else shadow.
            return "built_area" if v >= 0.28 else "other"
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

    Swapping the baseline for a trained model only changes this function.
    """
    return ColorHistogramModel()
