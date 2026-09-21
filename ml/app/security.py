"""Simple shared-secret auth between the Laravel app and this service."""

from __future__ import annotations

from fastapi import Header, HTTPException, status

from app.config import settings


async def require_api_key(x_api_key: str | None = Header(default=None)) -> None:
    """Reject requests that do not carry the shared `X-API-Key`.

    When `ML_API_KEY` is unset the check is skipped, which is convenient for
    local development but must never be the case in production. Wire this as a
    dependency on any router that performs real work.
    """
    if not settings.api_key:
        return

    if x_api_key is None or x_api_key != settings.api_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing API key.",
        )
