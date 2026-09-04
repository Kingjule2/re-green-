"""Tests for the land-analysis endpoint and colour baseline."""

from __future__ import annotations

from io import BytesIO

from fastapi.testclient import TestClient
from PIL import Image

from app.main import app

client = TestClient(app)

ENDPOINT = "/api/v1/land-analysis"


def _image_bytes(color: tuple[int, int, int], size: tuple[int, int] = (80, 80)) -> bytes:
    buffer = BytesIO()
    Image.new("RGB", size, color).save(buffer, format="PNG")
    return buffer.getvalue()


def _analyze(color: tuple[int, int, int], size: tuple[int, int] = (80, 80)) -> dict:
    response = client.post(
        ENDPOINT,
        files={"image": ("frame.png", _image_bytes(color, size), "image/png")},
    )
    assert response.status_code == 200, response.text
    return response.json()


def test_green_image_is_mostly_vegetation() -> None:
    body = _analyze((34, 139, 34))

    cover = body["land_cover"]
    assert cover["dense_vegetation"] + cover["sparse_vegetation"] > 90
    assert body["model"]["name"] == "color-histogram"


def test_brown_image_is_mostly_bare_soil() -> None:
    cover = _analyze((150, 100, 60))["land_cover"]
    assert cover["bare_soil"] > 50


def test_blue_image_is_mostly_water() -> None:
    cover = _analyze((30, 90, 180))["land_cover"]
    assert cover["water"] > 50


def test_response_shape_is_complete() -> None:
    body = _analyze((34, 139, 34), size=(120, 90))

    assert body["image"] == {"width": 120, "height": 90}

    total = sum(body["land_cover"].values())
    assert abs(total - 100) < 1.5

    assert 0.5 <= body["confidence"]["overall"] <= 1.0

    segmentation = body["segmentation"]
    assert len(segmentation["grid"]) == segmentation["rows"]
    assert len(segmentation["grid"][0]) == segmentation["cols"]
    assert set(segmentation["legend"]).issuperset({"dense_vegetation", "water", "bare_soil"})


def test_empty_upload_is_rejected() -> None:
    response = client.post(ENDPOINT, files={"image": ("empty.png", b"", "image/png")})
    assert response.status_code == 422


def test_non_image_upload_is_rejected() -> None:
    response = client.post(
        ENDPOINT,
        files={"image": ("notes.txt", b"this is not an image", "image/png")},
    )
    assert response.status_code == 422
