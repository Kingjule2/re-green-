"""Tests for fire-severity scoring and the charred-soil colour class.

The severity formula is the one thing both the YOLOv8 detector and the colour
baseline share, so these tests pin the *contract*: the weights, the band
boundaries, the detector -> land-cover mapping, and the fact that the two scoring
entry points produce identical numbers for identical evidence.
"""

from __future__ import annotations

from collections import Counter
from io import BytesIO

import pytest
from PIL import Image

from app.land_analysis import CLASS_COLORS, ColorHistogramModel
from app.severity import (
    COVER_CLASSES,
    SEVERITY_COLORS,
    SEVERITY_WEIGHTS,
    balance_cover,
    cover_from_detections,
    mean_confidence,
    severity_band,
    severity_from_cover,
    severity_from_detections,
)


def _cover(**shares: float) -> dict[str, float]:
    """A full seven-class cover dict; unnamed classes are zero."""
    return {key: shares.get(key, 0.0) for key in COVER_CLASSES}


def _detection(label: str, box: list[float], confidence: float = 0.9) -> dict:
    return {"label": label, "confidence": confidence, "box": box}


def _image_bytes(color: tuple[int, int, int], size: tuple[int, int] = (80, 80)) -> bytes:
    buffer = BytesIO()
    Image.new("RGB", size, color).save(buffer, format="PNG")
    return buffer.getvalue()


# ------------------------------------------------------------------- weights


def test_weights_follow_the_contract_ladder() -> None:
    assert SEVERITY_WEIGHTS == {
        "charred_soil": 100.0,
        "bare_soil": 60.0,
        "vegetation_regrowth": 20.0,
        "unburned_vegetation": 0.0,
    }
    # Water and built-up area are not burn evidence: they must not carry a weight.
    assert "water" not in SEVERITY_WEIGHTS
    assert "built_area" not in SEVERITY_WEIGHTS


def test_cover_classes_match_the_land_cover_legend_order() -> None:
    """The two vocabularies must stay aligned: severity reorders nothing."""
    assert tuple(CLASS_COLORS) == COVER_CLASSES
    assert CLASS_COLORS["charred_soil"] == "#3f3f46"
    assert set(SEVERITY_COLORS) == {"unburned", "low", "moderate", "high"}


@pytest.mark.parametrize(
    ("score", "level"),
    [
        (0.0, "unburned"),
        (9.9, "unburned"),
        (10.0, "low"),
        (34.9, "low"),
        (35.0, "moderate"),
        (64.9, "moderate"),
        (65.0, "high"),
        (100.0, "high"),
    ],
)
def test_band_boundaries(score: float, level: str) -> None:
    assert severity_band(score)[0] == level


@pytest.mark.parametrize(
    ("charred", "dense", "expected_score", "expected_level"),
    [
        (0.0, 100.0, 0.0, "unburned"),
        (9.0, 91.0, 9.0, "unburned"),
        (10.0, 90.0, 10.0, "low"),
        (34.0, 66.0, 34.0, "low"),
        (35.0, 65.0, 35.0, "moderate"),
        (64.0, 36.0, 64.0, "moderate"),
        (65.0, 35.0, 65.0, "high"),
        (100.0, 0.0, 100.0, "high"),
    ],
)
def test_severity_from_cover_bands(
    charred: float, dense: float, expected_score: float, expected_level: str
) -> None:
    """Charred share against unburned canopy, i.e. the pure burn signal."""
    result = severity_from_cover(_cover(charred_soil=charred, dense_vegetation=dense))

    assert result["score"] == expected_score
    assert result["level"] == expected_level
    assert result["label"] == severity_band(expected_score)[1]


def test_cover_and_detection_paths_use_the_same_formula() -> None:
    """The colour fallback and the detector must agree on identical evidence."""
    cover = _cover(
        dense_vegetation=12.0,
        sparse_vegetation=44.0,
        bare_soil=26.0,
        charred_soil=18.0,
    )
    detections = [
        _detection("unburned_vegetation", [0.0, 0.85, 0.3, 0.4]),  # 0.12 of the frame
        _detection("charred_soil", [0.0, 0.0, 0.6, 0.3]),  # 0.18
        _detection("bare_soil", [0.6, 0.0, 0.5, 0.52]),  # 0.26
        _detection("vegetation_regrowth", [0.0, 0.3, 0.8, 0.55]),  # 0.44
    ]

    from_cover = severity_from_cover(cover)
    from_boxes = severity_from_detections(detections, 4000 * 3000)

    # Unburned vegetation carries weight 0 but still sits in the denominator, as
    # the contract's area-weighted mean prescribes; water/built_area do not.
    expected = (18 * 100 + 26 * 60 + 44 * 20) / (12 + 18 + 26 + 44)
    assert from_cover["score"] == round(expected, 1)
    assert from_boxes["score"] == from_cover["score"]
    assert from_boxes["level"] == from_cover["level"]
    assert from_boxes["evidence"] == {
        "charred_soil_pct": 18.0,
        "bare_soil_pct": 26.0,
        "vegetation_pct": 56.0,
        "detections": {
            "unburned_vegetation": 1,
            "charred_soil": 1,
            "bare_soil": 1,
            "vegetation_regrowth": 1,
        },
    }


def test_evidence_counts_boxes_and_reports_vegetation_as_a_whole() -> None:
    result = severity_from_detections(
        [
            _detection("unburned_vegetation", [0.0, 0.0, 0.4, 0.5]),  # 0.20
            _detection("vegetation_regrowth", [0.0, 0.5, 0.4, 0.25]),  # 0.10
            _detection("water", [0.5, 0.0, 0.5, 0.5]),  # 0.25, weightless
        ]
    )

    assert result["evidence"]["vegetation_pct"] == 30.0
    assert result["evidence"]["detections"] == {
        "unburned_vegetation": 1,
        "vegetation_regrowth": 1,
        "water": 1,
    }
    # Vegetation alone stays in the lowest band: regrowth carries 20 and the
    # unburned canopy 0, so 10 % regrowth out of 30 % vegetation scores 6.7.
    assert result["score"] == 6.7
    assert result["level"] == "unburned"


def test_weightless_classes_dilute_confidence_but_not_the_score() -> None:
    """A mostly-flooded frame must not look confident about burn severity."""
    result = severity_from_cover(_cover(charred_soil=40.0, water=60.0))

    assert result["score"] == 100.0
    assert result["confidence"] == 0.4

    all_water = severity_from_cover(_cover(water=100.0))
    assert all_water["score"] == 0.0
    assert all_water["level"] == "unburned"
    assert all_water["confidence"] == 0.0


def test_confidence_keeps_the_detectors_own_scores() -> None:
    result = severity_from_detections(
        [
            _detection("charred_soil", [0.0, 0.0, 0.5, 0.5], confidence=0.9),  # 0.25
            _detection("charred_soil", [0.5, 0.5, 0.5, 0.5], confidence=0.6),  # 0.25
        ]
    )

    assert result["confidence"] == 0.75
    assert result["evidence"]["detections"] == {"charred_soil": 2}


def test_pixel_boxes_score_like_normalized_boxes() -> None:
    normalized = severity_from_detections([_detection("charred_soil", [0.1, 0.2, 0.5, 0.4])])
    pixels = severity_from_detections(
        [_detection("charred_soil", [200.0, 400.0, 1000.0, 800.0])], 2000.0 * 2000.0
    )

    assert pixels["score"] == normalized["score"]
    assert pixels["evidence"]["charred_soil_pct"] == normalized["evidence"]["charred_soil_pct"]


def test_mean_confidence_weights_by_box_area() -> None:
    detections = [
        _detection("charred_soil", [0.0, 0.0, 0.8, 0.8], confidence=0.9),  # 0.64
        _detection("charred_soil", [0.9, 0.9, 0.05, 0.05], confidence=0.1),  # 0.0025
    ]

    assert mean_confidence(detections) == 0.9
    assert mean_confidence(detections, {"bare_soil"}, default=0.5) == 0.5
    assert mean_confidence([]) == 0.0


# ----------------------------------------------------------------- land cover


def test_cover_from_detections_maps_classes_and_residual_to_other() -> None:
    cover = cover_from_detections(
        [
            _detection("unburned_vegetation", [0.0, 0.0, 0.5, 0.4]),  # 20 -> dense
            _detection("vegetation_regrowth", [0.5, 0.0, 0.25, 0.4]),  # 10 -> sparse
            _detection("charred_soil", [0.75, 0.0, 0.25, 0.4]),  # 10
            _detection("water", [0.0, 0.5, 0.5, 0.5]),  # 25
            _detection("built_area", [0.5, 0.5, 0.25, 0.5]),  # 12.5
            _detection("something_unknown", [0.0, 0.0, 1.0, 1.0]),  # not a class
        ]
    )

    assert cover == {
        "dense_vegetation": 20.0,
        "sparse_vegetation": 10.0,
        "bare_soil": 0.0,
        "charred_soil": 10.0,
        "water": 25.0,
        "built_area": 12.5,
        "other": 22.5,
    }
    assert sum(cover.values()) == pytest.approx(100.0)


def test_cover_from_detections_without_boxes_is_all_other() -> None:
    assert cover_from_detections([]) == _cover(other=100.0)


def test_balance_cover_absorbs_rounding_into_other() -> None:
    """Three equal thirds round to 99.9 %; `other` must take the 0.1 % back."""
    cover = balance_cover(
        {"dense_vegetation": 33.333, "charred_soil": 33.333, "bare_soil": 33.334, "other": 0.0}
    )

    assert cover["dense_vegetation"] == 33.3
    assert cover["charred_soil"] == 33.3
    assert cover["bare_soil"] == 33.3
    assert cover["other"] == 0.1
    assert sum(cover.values()) == pytest.approx(100.0)


def test_balance_cover_clamps_overlapping_boxes_at_zero_other() -> None:
    cover = balance_cover({"charred_soil": 80.0, "bare_soil": 40.0, "other": 0.0})

    assert cover["other"] == 0.0


# --------------------------------------------------------------- colour model


def _counts(color: tuple[int, int, int]) -> Counter:
    model = ColorHistogramModel()
    return Counter(model.classify(_image_bytes(color)).keys)


def test_dark_warm_ground_classifies_as_charred_soil() -> None:
    """Soot / scorched mineral soil: dark, desaturated, warm."""
    counts = _counts((45, 38, 30))

    assert counts["charred_soil"] == sum(counts.values())


def test_deep_shadow_stays_unclassified() -> None:
    """Below the value floor the pixel is a shadow, not ash."""
    counts = _counts((20, 22, 24))

    assert counts["other"] == sum(counts.values())
    assert counts["charred_soil"] == 0


def test_dark_green_canopy_is_not_mistaken_for_charred_soil() -> None:
    """Regrowth in shade is vegetation: counting it as burnt inflates severity."""
    counts = _counts((46, 74, 46))

    assert counts["charred_soil"] == 0
    assert counts["dense_vegetation"] + counts["sparse_vegetation"] == sum(counts.values())


def test_bright_brown_soil_keeps_its_own_class() -> None:
    counts = _counts((150, 100, 60))

    assert counts["bare_soil"] == sum(counts.values())
    assert counts["charred_soil"] == 0


def test_charred_image_reports_high_severity_through_the_model() -> None:
    result = ColorHistogramModel().analyze(_image_bytes((45, 38, 30)))

    assert result["land_cover"]["charred_soil"] == 100.0
    assert result["fire_severity"]["score"] == 100.0
    assert result["fire_severity"]["level"] == "high"
    assert result["fire_severity"]["evidence"]["charred_soil_pct"] == 100.0
