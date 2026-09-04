"""Land-analysis endpoint.

Accepts an aerial/drone image (multipart upload) and returns a land-cover
perception result. Heavy lifting lives in `app.land_analysis`; this router
stays thin: validate the upload, run the active model, map failures to HTTP.
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile

from app.land_analysis import ImageDecodeError, get_model
from app.schemas import LandAnalysisResponse
from app.security import require_api_key

router = APIRouter(tags=["land-analysis"], dependencies=[Depends(require_api_key)])

# Mirror the Laravel-side 50 MB upload limit.
MAX_BYTES = 50 * 1024 * 1024


@router.post("/land-analysis", response_model=LandAnalysisResponse)
async def analyze_land(image: Annotated[UploadFile, File()]) -> dict:
    """Analyze a single drone image and return estimated land cover."""
    contents = await image.read()

    if len(contents) == 0:
        raise HTTPException(status_code=422, detail="The uploaded image is empty.")

    if len(contents) > MAX_BYTES:
        raise HTTPException(status_code=413, detail="Maximum file size is 50 MB.")

    try:
        return get_model().analyze(contents)
    except ImageDecodeError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
