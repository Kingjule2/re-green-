"""Tests for the YOLOv8 integration: availability probe, mapping, fallback.

`ultralytics`/torch are not installable on this interpreter, so the detector's
own inference cannot run in the suite. What *is* testable — and what actually
matters to the API contract — is everything around it: the availability probe
and its reason, the mapping from boxes to land cover / severity / grid, and the
guarantee that a service without weights still answers with the colour heuristic
and says so.
"""

from __future__ import annotations

import sys
from io import BytesIO

import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app import config
from app.land_analysis import ColorHistogramModel, ModelUnavailableError, get_model
from app.main import app
from app.yolo import YoloBurnModel

client = TestClient(app)

ENDPOINT = "/api/v1/land-analysis"

NAMES = {
    0: "unburned_vegetation",
    1: "vegetation_regrowth",
    2: "bare_soil",
    3: "charred_soil",
    4: "water",
    5: "built_area",
}


def _image_bytes(color: tuple[int, int, int] = (34, 139, 34), size=(80, 80)) -> bytes:
    buffer = BytesIO()
    Image.new("RGB", size, color).save(buffer, format="PNG")
    return buffer.getvalue()


# ------------------------------------------------------- the heavy stack stays out


def test_importing_the_service_does_not_pull_the_heavy_stack() -> None:
    """`app.main` is imported at module level above; torch must not be."""
    assert "ultralytics" not in sys.modules
    assert "torch" not in sys.modules


# ---------------------------------------------------------------- availability


def test_availability_probe_names_weights_and_how_to_train(
    monkeypatch: pytest.MonkeyPatch, tmp_path
) -> None:
    missing = tmp_path / "regreen-burn-yolov8n.pt"
    monkeypatch.setattr(config.settings, "yolo_weights", str(missing))

    model = YoloBurnModel()
    reason = model.unavailable_reason()

    assert reason is not None
    assert str(missing) in reason
    assert "train_yolo.py" in reason

    with pytest.raises(ModelUnavailableError) as error:
        YoloBurnModel.create()

    assert str(error.value) == reason


def test_model_keeps_the_configured_provenance(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(config.settings, "yolo_model_version", "9.9.9")
    monkeypatch.setattr(config.settings, "yolo_confidence", 0.31)
    monkeypatch.setattr(config.settings, "yolo_imgsz", 320)

    model = YoloBurnModel()

    assert model.name == "yolov8n-burn"
    assert model.task == "detect"
    assert model.version == "9.9.9"
    assert model.confidence == 0.31
    assert model.imgsz == 320
    assert model.weights == config.settings.yolo_weights


# ------------------------------------------------------------- the fallback path


def test_endpoint_serves_the_colour_fallback_when_weights_are_missing(
    monkeypatch: pytest.MonkeyPatch, tmp_path
) -> None:
    """No weights, no detector: still a valid analysis, with the reason attached."""
    missing = tmp_path / "absent-yolov8.pt"
    monkeypatch.setattr(config.settings, "yolo_weights", str(missing))
    get_model.cache_clear()

    try:
        response = client.post(
            ENDPOINT,
            files={"image": ("frame.png", _image_bytes(), "image/png")},
        )
    finally:
        get_model.cache_clear()

    assert response.status_code == 200, response.text
    body = response.json()

    assert body["model"] == {
        "name": "color-histogram",
        "version": "0.1.0",
        "task": None,
        "weights": None,
        "device": None,
    }
    assert body["detections"] == []

    severity = body["fire_severity"]
    assert severity["method"] == "colour-heuristic"
    assert severity["available"] is True
    assert severity["level"] == "unburned"
    assert severity["reason"] is not None
    assert str(missing) in severity["reason"]
    assert "train_yolo.py" in severity["reason"]


def test_get_model_returns_the_baseline_when_the_detector_cannot_run(
    monkeypatch: pytest.MonkeyPatch, tmp_path
) -> None:
    monkeypatch.setattr(config.settings, "yolo_weights", str(tmp_path / "absent.pt"))
    get_model.cache_clear()

    try:
        model = get_model()
    finally:
        get_model.cache_clear()

    assert isinstance(model, ColorHistogramModel)
    assert model.heuristic_reason != ColorHistogramModel().heuristic_reason


# ------------------------------------------------------------- the detector path


class _Array:
    """Minimal stand-in for the numpy arrays ultralytics returns."""

    def __init__(self, values: list) -> None:
        self._values = values

    def tolist(self) -> list:
        return self._values


class _Boxes:
    def __init__(self, boxes: list[dict]) -> None:
        self.xywhn = _Array([box["box"] for box in boxes])
        self.conf = _Array([box["confidence"] for box in boxes])
        self.cls = _Array([box["cls"] for box in boxes])


class _Result:
    def __init__(self, boxes: list[dict]) -> None:
        self.boxes = _Boxes(boxes)
        self.names = NAMES


class _Detector:
    """Stands in for an ultralytics `YOLO` object (no torch on this interpreter)."""

    device = "cpu"

    def __init__(self, boxes: list[dict]) -> None:
        self._boxes = boxes
        self.calls: list[dict] = []

    def predict(self, **kwargs) -> list[_Result]:
        self.calls.append(kwargs)
        return [_Result(self._boxes)]


def _stub_model(
    boxes: list[dict], monkeypatch: pytest.MonkeyPatch
) -> tuple[YoloBurnModel, _Detector]:
    detector = _Detector(boxes)
    model = YoloBurnModel(weights="models/regreen-burn-yolov8n.pt")
    monkeypatch.setattr(model, "_detector", detector)

    return model, detector


def test_detector_boxes_become_cover_severity_and_grid(monkeypatch: pytest.MonkeyPatch) -> None:
    model, detector = _stub_model(
        [
            {"cls": 3, "confidence": 0.9, "box": [0.0, 0.0, 0.5, 0.5]},  # charred, 25 %
            {"cls": 1, "confidence": 0.5, "box": [0.5, 0.5, 0.5, 0.5]},  # regrowth, 25 %
        ],
        monkeypatch,
    )

    result = model.analyze(_image_bytes())

    # Boxes -> land cover: the vegetation classes split, unclassified area is `other`.
    assert result["land_cover"] == {
        "dense_vegetation": 0.0,
        "sparse_vegetation": 25.0,
        "bare_soil": 0.0,
        "charred_soil": 25.0,
        "water": 0.0,
        "built_area": 0.0,
        "other": 50.0,
    }

    # Detections keep the detector's own labels and normalized boxes.
    assert result["detections"] == [
        {"label": "charred_soil", "confidence": 0.9, "box": [0.0, 0.0, 0.5, 0.5]},
        {"label": "vegetation_regrowth", "confidence": 0.5, "box": [0.5, 0.5, 0.5, 0.5]},
    ]

    # Severity: (25 * 100 + 25 * 20) / 50 = 60 -> moderate.
    severity = result["fire_severity"]
    assert severity["method"] == "yolov8"
    assert severity["reason"] is None
    assert severity["available"] is True
    assert severity["score"] == 60.0
    assert severity["level"] == "moderate"
    assert severity["confidence"] == 0.7
    assert severity["evidence"] == {
        "charred_soil_pct": 25.0,
        "bare_soil_pct": 0.0,
        "vegetation_pct": 25.0,
        "detections": {"charred_soil": 1, "vegetation_regrowth": 1},
    }

    # The raster feeds the same 28x28 majority grid the baseline produces.
    segmentation = result["segmentation"]
    assert (segmentation["cols"], segmentation["rows"]) == (28, 28)
    assert len(segmentation["grid"]) == 28
    assert segmentation["grid"][0][0] == "charred_soil"
    assert segmentation["grid"][27][27] == "sparse_vegetation"
    assert segmentation["grid"][0][27] == "other"
    assert segmentation["legend"]["charred_soil"] == "#3f3f46"

    assert result["image"] == {"width": 80, "height": 80}
    assert result["model"] == {
        "name": "yolov8n-burn",
        "version": config.settings.yolo_model_version,
        "task": "detect",
        "weights": "models/regreen-burn-yolov8n.pt",
        "device": "cpu",
    }

    # Inference runs once, with the configured thresholds, on the decoded image.
    assert len(detector.calls) == 1
    assert detector.calls[0]["conf"] == config.settings.yolo_confidence
    assert detector.calls[0]["iou"] == config.settings.yolo_iou
    assert detector.calls[0]["imgsz"] == config.settings.yolo_imgsz
    assert detector.calls[0]["verbose"] is False


def test_detector_without_boxes_reports_an_unburned_frame(monkeypatch: pytest.MonkeyPatch) -> None:
    """An empty frame is legitimate evidence of no burn, not a missing answer."""
    model, _ = _stub_model([], monkeypatch)

    result = model.analyze(_image_bytes())

    assert result["land_cover"]["other"] == 100.0
    assert result["detections"] == []
    assert result["fire_severity"]["level"] == "unburned"
    assert result["fire_severity"]["confidence"] == 0.0
