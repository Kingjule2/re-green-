"""Runtime configuration, loaded from environment / .env."""

from __future__ import annotations

from functools import lru_cache
from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Environment-driven settings for the ML service.

    Values are read from the process environment first, then from an `.env`
    file sitting next to this service. See `.env.example` for the full list.
    """

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        env_prefix="ML_",
        extra="ignore",
    )

    app_name: str = "Regreen ML Service"
    environment: str = "local"
    debug: bool = False

    # Shared secret the Laravel app sends as `X-API-Key`. Leave empty to allow
    # unauthenticated access in local development only.
    api_key: str | None = None

    # CORS origins allowed to call this service directly (e.g. the Vite dev
    # server). Comma-separated in the env var, e.g. "http://localhost:5173".
    allowed_origins: list[str] = ["http://localhost:5173"]

    # Where model artifacts live on disk.
    model_dir: str = "models"

    # --- Terrain context (DEM sampling) ---
    # Which Digital Elevation Model to sample for a survey point:
    #   auto   - DEMNAS inside Indonesia, NASA SRTM (Earth Engine) elsewhere
    #   demnas - BIG DEMNAS only
    #   srtm   - NASA SRTM via Google Earth Engine only
    #   none   - skip terrain context entirely
    terrain_source: Literal["auto", "demnas", "srtm", "none"] = "auto"

    # Whether a lower-resolution source may stand in when the preferred one
    # cannot deliver (e.g. SRTM when BIG's service is unreachable).
    terrain_fallback: bool = True

    # HTTP timeout (seconds) for DEM service calls.
    terrain_timeout: float = 20.0

    # BIG's ArcGIS image service for the national DEM.
    demnas_service_url: str = (
        "https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer"
    )

    # Google Earth Engine (NASA SRTM). Requires a Google Cloud project and a
    # service account with Earth Engine access; without them the SRTM source
    # reports itself as unconfigured instead of failing silently.
    gee_project: str | None = None
    gee_service_account: str | None = None
    gee_private_key_file: str | None = None
    gee_srtm_asset: str = "USGS/SRTMGL1_003"
    gee_timeout: float = 60.0

    # --- Soil context (ISRIC SoilGrids, 250 m, global) ---
    #   soilgrids - query ISRIC's public point API
    #   none      - skip soil context
    soil_source: Literal["soilgrids", "none"] = "soilgrids"

    # SoilGrids is a slower service than the DEM endpoints; 30 s covers it.
    soil_timeout: float = 30.0

    soil_grids_url: str = "https://rest.isric.org/soilgrids/v2.0/properties/query"

    # --- Climate context (NASA POWER agroclimatology, free, no key) ---
    #   power - query NASA POWER climatology
    #   none  - skip climate context
    climate_source: Literal["power", "none"] = "power"

    climate_timeout: float = 30.0

    power_url: str = "https://power.larc.nasa.gov/api/temporal/climatology/point"

    # Soil and climate normals change on a yearly scale, so samples are cached
    # per coordinate to avoid repeating public API round trips.
    point_cache_size: int = 256

    # --- Burn detector (YOLOv8) ---
    # Fine-tuned weights for the six burn classes. When the file (or the
    # `ultralytics` package it needs) is missing, land analysis degrades to the
    # colour heuristic instead of failing, and `fire_severity.reason` says so.
    # Train with `python train_yolo.py --data <dataset>/data.yaml`.
    yolo_weights: str = "models/regreen-burn-yolov8n.pt"

    # Detection thresholds handed straight to ultralytics.
    yolo_confidence: float = 0.25
    yolo_iou: float = 0.45
    yolo_imgsz: int = 640

    # None lets ultralytics pick (GPU when available, else CPU).
    yolo_device: str | None = None

    # Reported in the analysis provenance next to `yolo_weights`.
    yolo_model_version: str = "0.2.0"


@lru_cache
def get_settings() -> Settings:
    """Return a cached Settings instance."""
    return Settings()


settings = get_settings()
