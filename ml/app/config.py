"""Runtime configuration, loaded from environment / .env."""

from __future__ import annotations

from functools import lru_cache

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


@lru_cache
def get_settings() -> Settings:
    """Return a cached Settings instance."""
    return Settings()


settings = get_settings()
