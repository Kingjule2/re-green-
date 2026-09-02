"""Request/response models shared across routers."""

from __future__ import annotations

from pydantic import BaseModel, Field


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
