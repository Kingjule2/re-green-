"""Prediction endpoints.

The handler here is a deterministic stub so the service runs end-to-end before
any real model exists. Replace `_run_inference` with a call into your loaded
model (scikit-learn, PyTorch, ONNX Runtime, an LLM client, etc.).
"""

from __future__ import annotations

from fastapi import APIRouter, Depends

from app.schemas import PredictRequest, PredictResponse
from app.security import require_api_key

router = APIRouter(tags=["predict"], dependencies=[Depends(require_api_key)])

MODEL_VERSION = "stub-0.1.0"


def _run_inference(features: list[float]) -> float:
    """Placeholder inference. Swap for a real model call."""
    return sum(features) / len(features)


@router.post("/predict", response_model=PredictResponse)
async def predict(payload: PredictRequest) -> PredictResponse:
    """Return a prediction for a single feature vector."""
    score = _run_inference(payload.features)
    return PredictResponse(prediction=score, model_version=MODEL_VERSION)
