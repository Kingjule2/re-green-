"""Smoke tests for the ML service."""

from __future__ import annotations

from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health_ok() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"


def test_predict_returns_mean_of_features() -> None:
    response = client.post("/api/v1/predict", json={"features": [1.0, 2.0, 3.0]})
    assert response.status_code == 200
    body = response.json()
    assert body["prediction"] == 2.0
    assert body["model_version"]


def test_predict_rejects_empty_features() -> None:
    response = client.post("/api/v1/predict", json={"features": []})
    assert response.status_code == 422
