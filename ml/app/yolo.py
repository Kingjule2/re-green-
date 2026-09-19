"""YOLOv8 burn-scar detector.

`YoloBurnModel` is the trained Computer Vision model behind
`POST /api/v1/land-analysis`: it detects burn-scar regions and turns them into
the same perception shape the colour baseline produces (land cover, segmentation
grid, detections, fire severity), so nothing downstream changes when the weights
arrive.

Everything heavy is lazy: `ultralytics` (and the torch it pulls in) is imported
inside :meth:`YoloBurnModel.load`, and the service runs without either for as
long as no weights are installed — `land_analysis.get_model()` then falls back to
the colour heuristic and reports why in `fire_severity.reason`.

Weights come from `ML_YOLO_WEIGHTS` (default `models/regreen-burn-yolov8n.pt`,
resolved from the working directory, i.e. `ml/`). Train them with
`train_yolo.py`, measure them with `evaluate_yolo.py`; see `ml/README.md`.
"""

from __future__ import annotations

import importlib.util
import math
from io import BytesIO
from pathlib import Path
from typing import Any

from PIL import Image, UnidentifiedImageError

from app.config import settings
from app.land_analysis import (
    THUMB_MAX,
    ClassifiedImage,
    ImageDecodeError,
    LandAnalysisModel,
    ModelUnavailableError,
)
from app.severity import (
    DETECTOR_TO_COVER,
    cover_from_detections,
    mean_confidence,
    severity_from_detections,
)

_TRAIN_HINT = (
    "install ml/requirements-ml.txt (ultralytics + torch) and train the weights with "
    "`python train_yolo.py --data <dataset>/data.yaml` (see ml/README.md)"
)

_VEGETATION = frozenset({"unburned_vegetation", "vegetation_regrowth"})
_BARE_SOIL = frozenset({"bare_soil", "charred_soil"})
_WATER = frozenset({"water"})


class YoloBurnModel(LandAnalysisModel):
    """YOLOv8 detector fine-tuned on the six burn classes."""

    name = "yolov8n-burn"
    task = "detect"

    def __init__(
        self,
        *,
        weights: str | None = None,
        version: str | None = None,
        confidence: float | None = None,
        iou: float | None = None,
        imgsz: int | None = None,
        device: str | None = None,
    ) -> None:
        self.weights = settings.yolo_weights if weights is None else weights
        self.version = version or settings.yolo_model_version
        self.confidence = settings.yolo_confidence if confidence is None else confidence
        self.iou = settings.yolo_iou if iou is None else iou
        self.imgsz = settings.yolo_imgsz if imgsz is None else imgsz
        self.device = settings.yolo_device if device is None else device
        self._detector: Any = None

    # -- availability ---------------------------------------------------------

    @property
    def weights_path(self) -> Path:
        """Absolute weights location (a relative `ML_YOLO_WEIGHTS` resolves from cwd)."""
        return Path(self.weights).expanduser().absolute()

    def unavailable_reason(self) -> str | None:
        """Why the detector cannot run yet, or None when it can.

        Both failures are reported at once, because they usually happen together
        (`ultralytics`/torch have no cp314 wheels yet): the caller shows this
        string to the user in `fire_severity.reason`.
        """
        problems = []
        if importlib.util.find_spec("ultralytics") is None:
            problems.append("the `ultralytics` package is not installed in this environment")
        if not self.weights_path.is_file():
            problems.append(
                f"no detector weights at '{self.weights_path}' (set ML_YOLO_WEIGHTS)"
            )
        if not problems:
            return None

        return (
            f"{'; '.join(problems)}, so fire severity comes from the colour heuristic "
            f"instead of YOLOv8. To enable the detector, {_TRAIN_HINT}."
        )

    @classmethod
    def create(cls) -> YoloBurnModel:
        """Build the detector, or raise `ModelUnavailableError` explaining why not."""
        model = cls()
        reason = model.unavailable_reason()
        if reason is not None:
            raise ModelUnavailableError(reason)

        return model

    def load(self) -> Any:
        """Import ultralytics and load the weights (first call only)."""
        if self._detector is None:
            try:
                from ultralytics import YOLO
            except ImportError as exc:
                raise ModelUnavailableError(
                    f"ultralytics could not be imported ({exc}), so fire severity comes "
                    f"from the colour heuristic instead of YOLOv8. To enable the detector, "
                    f"{_TRAIN_HINT}."
                ) from exc

            self._detector = YOLO(str(self.weights_path))

        return self._detector

    # -- pipeline -------------------------------------------------------------

    def classify(self, image_bytes: bytes) -> ClassifiedImage:
        """Detect burn regions and rasterize the boxes onto the working image.

        One inference pass feeds everything: the boxes become `detections` and
        the raster, the raster feeds the inherited majority-grid `segment()`, and
        `calculate_metrics` scores the boxes' area share.
        """
        image = _decode(image_bytes)
        detector = self.load()

        # Record the device ultralytics actually picked (the setting may leave it
        # to `auto`), so the analysis provenance names the real one.
        resolved = getattr(detector, "device", None)
        if resolved is not None:
            self.device = str(resolved)

        results = detector.predict(
            source=image,
            conf=self.confidence,
            iou=self.iou,
            imgsz=self.imgsz,
            device=self.device,
            verbose=False,
        )
        detections = _detections_from(results[0], getattr(detector, "names", None))
        keys, width, height = _rasterise(image, detections)

        return ClassifiedImage(
            keys=keys,
            width=width,
            height=height,
            source_width=image.width,
            source_height=image.height,
            detections=detections,
        )

    def calculate_metrics(
        self, classified: ClassifiedImage
    ) -> tuple[dict[str, float], dict[str, float]]:
        """Land cover and confidence from the boxes, not from the raster counts.

        The percentages come from each box's area share (`cover_from_detections`),
        which is the same quantity the YOLO training labels describe; the raster
        is only for the overlay. Confidence is the area-weighted mean of the
        detections' own scores, so a group with no detection reports the frame's
        overall confidence rather than an invented one.
        """
        overall = mean_confidence(classified.detections)

        return cover_from_detections(classified.detections), {
            "overall": overall,
            "vegetation": mean_confidence(classified.detections, _VEGETATION, default=overall),
            "bare_soil": mean_confidence(classified.detections, _BARE_SOIL, default=overall),
            "water": mean_confidence(classified.detections, _WATER, default=overall),
        }

    def fire_severity(self, classified: ClassifiedImage, cover: dict[str, float]) -> dict:
        """Severity from the detected boxes, scored exactly like the baseline."""
        return {
            "available": True,
            "reason": None,
            "method": "yolov8",
            **severity_from_detections(
                classified.detections, classified.source_width * classified.source_height
            ),
        }


def _decode(image_bytes: bytes) -> Image.Image:
    """Decode the upload once, so inference and the raster share one image."""
    try:
        image = Image.open(BytesIO(image_bytes))
        image.load()
    except (UnidentifiedImageError, OSError) as exc:
        raise ImageDecodeError("The uploaded file could not be read as an image.") from exc

    return image.convert("RGB")


def _detections_from(result: Any, fallback_names: Any = None) -> list[dict]:
    """Map one ultralytics result onto the API's normalized detection shape."""
    boxes = getattr(result, "boxes", None)
    if boxes is None:
        return []

    names = getattr(result, "names", None) or fallback_names or {}

    return [
        {
            "label": _class_name(names, int(index)),
            "confidence": round(_clamp(float(score)), 4),
            "box": [round(_clamp(float(value)), 4) for value in coordinates[:4]],
        }
        for coordinates, score, index in zip(
            boxes.xywhn.tolist(), boxes.conf.tolist(), boxes.cls.tolist(), strict=False
        )
    ]


def _class_name(names: Any, index: int) -> str:
    """Resolve a class index through ultralytics' `names` (dict or list)."""
    if isinstance(names, dict):
        return str(names.get(index, index))

    try:
        return str(names[index])
    except (IndexError, KeyError, TypeError):
        return str(index)


def _rasterise(image: Image.Image, detections: list[dict]) -> tuple[list[str], int, int]:
    """Paint the boxes onto a downscaled frame as *land-cover* labels.

    Boxes are applied weakest-first so the detector's most confident region owns
    every pixel it covers; pixels no box covers stay `other`. Painting at the
    working resolution lets the inherited `segment()` turn this into the 28x28
    majority grid the contract asks for.
    """
    thumb = image.convert("RGB")
    thumb.thumbnail((THUMB_MAX, THUMB_MAX))
    width, height = thumb.size

    ranked = []
    for detection in sorted(detections, key=lambda item: item["confidence"]):
        cover = DETECTOR_TO_COVER.get(str(detection["label"]))
        if cover is None:
            continue

        x, y, box_width, box_height = (float(value) for value in detection["box"])
        ranked.append((cover, x * width, y * height, box_width * width, box_height * height))

    keys = ["other"] * (width * height)
    for cover, x, y, box_width, box_height in ranked:
        left, top = max(0, int(x)), max(0, int(y))
        right = min(width, int(math.ceil(x + box_width)))
        bottom = min(height, int(math.ceil(y + box_height)))

        for row in range(top, bottom):
            start = row * width
            for column in range(left, right):
                keys[start + column] = cover

    return keys, width, height


def _clamp(value: float, low: float = 0.0, high: float = 1.0) -> float:
    return min(high, max(low, value))
