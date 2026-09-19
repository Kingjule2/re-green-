"""Fire-severity scoring: one source of truth for every burn-evidence consumer.

Severity is scored from *area share*, never from raw counts: how much of the
surveyed land burned is what matters, not how many boxes a detector happened to
draw. The YOLOv8 detector and the dependency-free colour baseline therefore call
the same weighted mean (:func:`severity_from_detections` and
:func:`severity_from_cover`), so swapping the model never changes what a score
of 62 means.

Weights follow the post-fire recovery ladder — freshly charred ground 100,
exposed bare soil 60 (canopy gone, erosion risk), early regrowth 20, unburned
vegetation 0. Water and built-up area carry no weight at all: they are not burn
evidence, so they are excluded from the mean instead of diluting it. Bands:
< 10 unburned, < 35 low, < 65 moderate, >= 65 high.

The detector vocabulary (6 classes) and the land-cover vocabulary (7 classes)
meet only here: `DETECTOR_TO_COVER` is the mapping the API contract fixes
(unburned -> dense, regrowth -> sparse, the rest one-to-one), and
`COVER_TO_DETECTOR` lets the colour baseline reach the same weights from its
land-cover output.
"""

from __future__ import annotations

import math
from collections import Counter
from collections.abc import Container, Mapping, Sequence

# --------------------------------------------------------------------- classes

#: The six classes the YOLOv8 burn detector is trained on, in dataset order.
#: `data.yaml` must list `names` in exactly this order (see `train_yolo.py`).
DETECTOR_CLASSES: tuple[str, ...] = (
    "unburned_vegetation",
    "vegetation_regrowth",
    "bare_soil",
    "charred_soil",
    "water",
    "built_area",
)

#: Detector class -> land-cover class. The vegetation band is split by burn
#: status; the other four classes map one-to-one.
DETECTOR_TO_COVER: dict[str, str] = {
    "unburned_vegetation": "dense_vegetation",
    "vegetation_regrowth": "sparse_vegetation",
    "bare_soil": "bare_soil",
    "charred_soil": "charred_soil",
    "water": "water",
    "built_area": "built_area",
}

#: Land-cover classes in payload order. Must match `land_analysis.CLASS_COLORS`
#: (asserted in the tests). `other` is the residual class, never detected.
COVER_CLASSES: tuple[str, ...] = (
    "dense_vegetation",
    "sparse_vegetation",
    "bare_soil",
    "charred_soil",
    "water",
    "built_area",
    "other",
)

#: Land-cover class -> the detector class whose burn weight it carries; `other`
#: (unclassifiable pixels) carries none.
COVER_TO_DETECTOR: dict[str, str | None] = {
    cover: None for cover in COVER_CLASSES
} | {cover: detector for detector, cover in DETECTOR_TO_COVER.items()}

# ------------------------------------------------------------------- severity

#: Burn weight per detector class, 0 (unburned) to 100 (freshly charred).
SEVERITY_WEIGHTS: dict[str, float] = {
    "charred_soil": 100.0,
    "bare_soil": 60.0,
    "vegetation_regrowth": 20.0,
    "unburned_vegetation": 0.0,
}

#: `(upper bound, level, label)`; a score falls in the first band it is under.
SEVERITY_BANDS: tuple[tuple[float, str, str], ...] = (
    (10.0, "unburned", "Unburned"),
    (35.0, "low", "Low"),
    (65.0, "moderate", "Moderate"),
    (math.inf, "high", "High"),
)

#: Level -> display colour, cool (recovered) to hot (fresh burn).
SEVERITY_COLORS: dict[str, str] = {
    "unburned": "#16A34A",
    "low": "#EAB308",
    "moderate": "#F59E0B",
    "high": "#DC2626",
}

_OTHER = "other"


def severity_band(score: float) -> tuple[str, str]:
    """Return the `(level, label)` a 0-100 severity score falls in."""
    for ceiling, level, label in SEVERITY_BANDS:
        if score < ceiling:
            return level, label

    return "high", "High"


# -------------------------------------------------------------------- scoring


def severity_from_cover(
    cover_pct: Mapping[str, float],
    *,
    counts: Mapping[str, int] | None = None,
    confidence: float | None = None,
) -> dict:
    """Score land-cover percentages with the burn weights.

    This is what the colour baseline calls: its `dense_vegetation` and
    `sparse_vegetation` shares stand for the detector's `unburned_vegetation`
    and `vegetation_regrowth`, so the percentages run through exactly the same
    weighted mean :func:`severity_from_detections` applies to boxes.
    """
    areas: dict[str, float] = {}
    for key, value in cover_pct.items():
        detector = COVER_TO_DETECTOR.get(key)
        if detector is not None:
            areas[detector] = areas.get(detector, 0.0) + float(value)

    return _score(areas, counts=counts, confidence=confidence)


def severity_from_detections(
    detections: Sequence[Mapping[str, object]],
    image_area: float = 1.0,
    *,
    confidence: float | None = None,
) -> dict:
    """Score detection boxes by area, using the same weighted mean.

    `box` is `[x, y, w, h]` — normalized to the frame (the shape the API
    contract returns) or in pixels, in which case `image_area` is the frame's
    pixel count. Boxes of weightless classes (water, built_area) are counted as
    area the score does not cover, which the reported confidence reflects.

    When `confidence` is not given, the detector's own area-weighted mean
    detection confidence is reported instead of the coverage estimate.
    """
    areas: dict[str, float] = {}
    for detection in detections:
        label = str(detection.get("label", ""))
        areas[label] = areas.get(label, 0.0) + _box_share(detection.get("box"), image_area) * 100.0

    if confidence is None:
        confidence = mean_confidence(detections)

    return _score(areas, counts=detection_counts(detections), confidence=confidence)


def _score(
    areas: Mapping[str, float],
    *,
    counts: Mapping[str, int] | None = None,
    confidence: float | None = None,
) -> dict:
    """Weighted mean over weighted areas, plus its band and evidence.

    `areas` holds percent of frame per *detector* class. Classes without a
    weight are excluded from the mean but kept in the denominator, so the
    confidence falls when a large part of the frame carries no burn evidence.
    With no `confidence`, coverage (weighted area / scored area) is reported.
    """
    weighted_area = sum(
        value for key, value in areas.items() if key in SEVERITY_WEIGHTS and value > 0.0
    )
    scored_area = sum(value for value in areas.values() if value > 0.0)

    if weighted_area > 0.0:
        score = (
            sum(
                value * SEVERITY_WEIGHTS[key]
                for key, value in areas.items()
                if key in SEVERITY_WEIGHTS and value > 0.0
            )
            / weighted_area
        )
    else:
        score = 0.0

    level, label = severity_band(score)
    coverage = weighted_area / scored_area if scored_area > 0.0 else 0.0

    return {
        "level": level,
        "label": label,
        "score": round(score, 1),
        "confidence": round(_clamp(confidence if confidence is not None else coverage), 2),
        "evidence": {
            "charred_soil_pct": round(areas.get("charred_soil", 0.0), 1),
            "bare_soil_pct": round(areas.get("bare_soil", 0.0), 1),
            "vegetation_pct": round(
                areas.get("unburned_vegetation", 0.0) + areas.get("vegetation_regrowth", 0.0), 1
            ),
            "detections": dict(counts or {}),
        },
    }


# ----------------------------------------------------------------- land cover


def cover_from_detections(
    detections: Sequence[Mapping[str, object]], image_area: float = 1.0
) -> dict[str, float]:
    """Map detector boxes onto the seven land-cover classes, as percentages.

    Unburned vegetation becomes `dense_vegetation` and regrowth becomes
    `sparse_vegetation`; the remaining four classes map one-to-one. Whatever no
    box covers is reported as `other`, so a detector that recognises nothing
    yields 100 % `other` instead of an invented cover. Overlapping boxes are
    counted as drawn (beyond the detector's own NMS there is no de-duplication)
    and the residual is clamped at zero.
    """
    cover = dict.fromkeys(COVER_CLASSES, 0.0)

    for detection in detections:
        label = DETECTOR_TO_COVER.get(str(detection.get("label", "")))
        if label is not None:
            cover[label] += _box_share(detection.get("box"), image_area) * 100.0

    return balance_cover(cover)


def balance_cover(cover: Mapping[str, float]) -> dict[str, float]:
    """Round every class but `other`, then give `other` the residual.

    Both scoring paths go through this so the classes always add up to exactly
    100 %, which the API contract promises and the Laravel engines rely on when
    they renormalize the shares.
    """
    balanced = {
        key: round(float(cover.get(key, 0.0)), 1) for key in COVER_CLASSES if key != _OTHER
    }
    balanced[_OTHER] = round(max(0.0, 100.0 - sum(balanced.values())), 1)

    return balanced


# ------------------------------------------------------------------ detection


def detection_counts(detections: Sequence[Mapping[str, object]]) -> dict[str, int]:
    """How many boxes of each detector class were found (non-empty classes only)."""
    return dict(Counter(str(detection.get("label", "")) for detection in detections))


def mean_confidence(
    detections: Sequence[Mapping[str, object]],
    labels: Container[str] | None = None,
    *,
    default: float = 0.0,
) -> float:
    """Area-weighted mean detection confidence, optionally over `labels` only.

    Weighting by box area keeps one large, confident scar from being drowned out
    by a scatter of tiny boxes. Returns `default` when nothing matches.
    """
    matched_area = 0.0
    weighted_confidence = 0.0

    for detection in detections:
        label = str(detection.get("label", ""))
        if labels is not None and label not in labels:
            continue

        share = _box_share(detection.get("box"), 1.0)
        score = float(detection.get("confidence") or 0.0)
        matched_area += share
        weighted_confidence += share * score

    if matched_area <= 0.0:
        return round(_clamp(default), 2)

    return round(_clamp(weighted_confidence / matched_area), 2)


def _box_share(box: object, image_area: float) -> float:
    """Fraction of the frame one `[x, y, w, h]` box covers (0-1)."""
    if not isinstance(box, (list, tuple)) or len(box) != 4:
        return 0.0

    x, y, width, height = (float(value) for value in box)
    area = abs(width * height)

    if max(abs(x), abs(y), abs(width), abs(height)) > 1.0:
        # Pixel coordinates rather than fractions of the frame.
        area /= image_area if image_area > 0.0 else 1.0

    return _clamp(area)


def _clamp(value: float, low: float = 0.0, high: float = 1.0) -> float:
    return min(high, max(low, value))
