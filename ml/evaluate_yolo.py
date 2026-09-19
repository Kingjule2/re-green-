"""Evaluate trained burn weights: mAP50, mAP50-95, precision and recall.

Run from `ml/` inside a virtualenv that has `requirements-ml.txt` installed:

    python evaluate_yolo.py --data datasets/burn/data.yaml
    python evaluate_yolo.py --data datasets/burn/data.yaml --json reports/burn-metrics.json

These numbers are the accuracy evidence for the pitch: mAP50 is the headline
figure, and the per-class rows show which classes the detector still confuses
(`vegetation_regrowth` against `unburned_vegetation` is the usual pair, because
early regrowth looks like unburned canopy from above).

Metrics come from ultralytics' own validator (`model.val`), which is the
definition of mAP the YOLO ecosystem uses, so the numbers are comparable with
published burn-scar detectors on the same split.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any

DEFAULT_WEIGHTS = "models/regreen-burn-yolov8n.pt"


def build_parser() -> argparse.ArgumentParser:
    """Command line: which weights, which dataset, how big the batches are."""
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument(
        "--weights",
        default=DEFAULT_WEIGHTS,
        help=f"trained detector weights (default: {DEFAULT_WEIGHTS})",
    )
    parser.add_argument("--data", required=True, help="dataset YAML (see train_yolo.py)")
    parser.add_argument("--imgsz", type=int, default=640, help="inference size (default: 640)")
    parser.add_argument("--batch", type=int, default=16, help="batch size (default: 16)")
    parser.add_argument(
        "--device",
        default=None,
        help="ultralytics device, e.g. cpu / 0 (default: ultralytics picks)",
    )
    parser.add_argument("--json", default=None, help="also write the metrics to this JSON file")
    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)

    try:
        from ultralytics import YOLO
    except ImportError as exc:
        print(f"ultralytics is not installed ({exc}). Install requirements-ml.txt first.")
        return 1

    weights = Path(args.weights)
    if not weights.is_file():
        print(f"no weights at '{weights}'. Train them with train_yolo.py first.")
        return 1

    metrics = YOLO(str(weights)).val(
        data=args.data, imgsz=args.imgsz, batch=args.batch, device=args.device, verbose=False
    )
    report = collect_metrics(metrics, weights=str(weights), data=args.data)

    print(format_report(report))

    if args.json:
        destination = Path(args.json)
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text(json.dumps(report, indent=2), encoding="utf-8")
        print(f"\nWrote {destination}")

    return 0


def collect_metrics(metrics: Any, *, weights: str, data: str) -> dict:
    """Normalize an ultralytics validation result into a plain report dict."""
    box = getattr(metrics, "box", None)

    return {
        "weights": weights,
        "data": data,
        "overall": {
            "map50": _number(getattr(box, "map50", None)),
            "map50_95": _number(getattr(box, "map", None)),
            "precision": _number(getattr(box, "mp", None)),
            "recall": _number(getattr(box, "mr", None)),
        },
        "per_class": _per_class(box, getattr(metrics, "names", None)),
    }


def format_report(report: dict) -> str:
    """Render the report as a fixed-width table."""
    overall = report["overall"]
    lines = [
        f"weights: {report['weights']}",
        f"data:    {report['data']}",
        "",
        f"mAP50: {overall['map50']:.4f}   mAP50-95: {overall['map50_95']:.4f}   "
        f"precision: {overall['precision']:.4f}   recall: {overall['recall']:.4f}",
        "",
        f"{'class':<22}{'mAP50':>9}{'mAP50-95':>11}{'precision':>11}{'recall':>9}",
        "-" * 62,
    ]

    for row in report["per_class"]:
        lines.append(
            f"{row['class']:<22}{row['map50']:>9.4f}{row['map50_95']:>11.4f}"
            f"{row['precision']:>11.4f}{row['recall']:>9.4f}"
        )

    return "\n".join(lines)


def _per_class(box: Any, names: Any) -> list[dict]:
    """One row per evaluated class, aligned with ultralytics' class indices."""
    maps = _series(getattr(box, "maps", None))
    ap50 = _series(getattr(box, "ap50", None))
    precision = _series(getattr(box, "p", None))
    recall = _series(getattr(box, "r", None))
    indices = _series(getattr(box, "ap_class_index", None)) or list(range(len(maps)))

    return [
        {
            "class": _class_name(names, int(index)),
            "map50": _at(ap50, position),
            "map50_95": _at(maps, position),
            "precision": _at(precision, position),
            "recall": _at(recall, position),
        }
        for position, index in enumerate(indices)
    ]


def _class_name(names: Any, index: int) -> str:
    if isinstance(names, dict):
        return str(names.get(index, index))

    try:
        return str(names[index])
    except (IndexError, KeyError, TypeError):
        return str(index)


def _series(values: Any) -> list[float]:
    """Flatten a numpy array / list into floats, treating None as 'absent'."""
    if values is None:
        return []

    tolist = getattr(values, "tolist", None)
    if callable(tolist):
        values = tolist()

    return [float(value) for value in values]


def _at(series: list[float], position: int) -> float:
    return round(series[position], 4) if position < len(series) else 0.0


def _number(value: Any) -> float:
    return round(float(value), 4) if value is not None else 0.0


if __name__ == "__main__":
    raise SystemExit(main())
