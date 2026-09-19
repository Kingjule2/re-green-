"""FastAPI application entrypoint.

Run locally:
    uvicorn app.main:app --reload --port 8001

Interactive docs are then served at http://localhost:8001/docs
"""

from __future__ import annotations

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app import __version__
from app.config import settings
from app.routers import health, land, predict

app = FastAPI(
    title=settings.app_name,
    version=__version__,
    debug=settings.debug,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Unauthenticated liveness probe.
app.include_router(health.router)

# Versioned, API-key-protected inference surface.
app.include_router(predict.router, prefix="/api/v1")
app.include_router(land.router, prefix="/api/v1")
