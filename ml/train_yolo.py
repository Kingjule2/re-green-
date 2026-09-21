"""Fine-tune the YOLOv8 burn-scar detector on the six-class dataset.

Run from `ml/` inside a virtualenv that has `requirements-ml.txt` installed:

    python train_yolo.py --data datasets/burn/data.yaml
    python train_yolo.py --data datasets/burn/data.yaml --model yolov8s.pt --epochs 150 --device 0

The script trains, validates on the held-out split, prints the metrics, and copies
the best checkpoint to `models/regreen-burn-yolov8n.pt` — the path
`ML_YOLO_WEIGHTS` defaults to, so a successful run is enough to switch
`POST /api/v1/land-analysis` from the colour heuristic to the detector.

Dataset layout (standard YOLO detection format, one `.txt` per image):

    datasets/burn/
    ├── data.yaml
    ├── images/
    │   ├── train/*.jpg
    │   └── val/*.jpg
    └── labels/
        ├── train/*.txt
        └── val/*.txt

`data.yaml` must list the six classes **in this order** (the order is the
contract — `app/severity.py` maps class names to severity weights):

    path: datasets/burn        # dataset root, relative to ml/
    train: images/train
    val: images/val
    names:
      0: unburned_vegetation
      1: vegetation_regrowth
      2: bare_soil
      3: charred_soil
      4: water
      5: built_area

Label format: one line per box, `class cx cy w h`, with the centre and size
normalized to 0-1 (`cx = x_px / image_width`, `w = box_width_px / image_width`).
Coordinates outside 0-1 are clamped by ultralytics at training time, so a broken
label shows up as a box glued to the frame edge rather than an error.

Labelling guide (what each class means on an aerial/drone frame):
  * `unburned_vegetation` — green canopy, no visible scorch; the control class,
    and what "recovered" looks like after rehabilitation.
  * `vegetation_regrowth` — post-fire young growth: lighter, sparser green
    patches on otherwise bare or charred ground, fern/shrub stage.
  * `bare_soil` — exposed mineral soil with no vegetation, unburned (dry fields,
    cleared land, dirt roads are still `bare_soil` if no burn scar is visible).
  * `charred_soil` — visibly burned ground: black to grey ash, or the scorched
    orange-brown crust where the surface layer burned off. When in doubt compare
    with `bare_soil`: charred is darker and lower contrast.
  * `water` — rivers, ponds, flooded fields; count them so the severity formula
    can exclude them (water carries no burn weight).
  * `built_area` — buildings, asphalt, concrete, hard infrastructure.

Label every instance of a class, and leave objects smaller than ~20 px unlabelled
rather than guessing: a noisy tiny box costs more in mAP than a missed pebble.
Keep a val split of at least 15 % of the images, captured on different days and
different lands than the train split — the detector must generalize across
seasons, and that is what the per-class recall in `evaluate_yolo.py` proves.
"""

from __future__ import annotations

import argparse
import shutil
from pathlib import Path
from typing import Any

from evaluate_yolo import collect_metrics, format_report

DEFAULT_BASE_MODEL = "yolov8n.pt"
DEFAULT_PROJECT = "runs/burn"
DEFAULT_NAME = "regreen-burn"
DEFAULT_OUT = "models/regreen-burn-yolov8n.pt"


def build_parser() -> argparse.ArgumentParser:
    """Command line: dataset, base model, and the training budget."""
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--data", required=True, help="dataset YAML (see the module docstring)")
    parser.add_argument(
        "--model",
        default=DEFAULT_BASE_MODEL,
        help=f"base checkpoint to fine-tune (default: {DEFAULT_BASE_MODEL}, COCO-pretrained)",
    )
    parser.add_argument("--epochs", type=int, default=100, help="training epochs (default: 100)")
    parser.add_argument("--imgsz", type=int, default=640, help="training image size (default: 640)")
    parser.add_argument("--batch", type=int, default=16, help="batch size (default: 16)")
    parser.add_argument(
        "--project",
        default=DEFAULT_PROJECT,
        help=f"where ultralytics writes runs (default: {DEFAULT_PROJECT})",
    )
    parser.add_argument("--name", default=DEFAULT_NAME, help=f"run name (default: {DEFAULT_NAME})")
    parser.add_argument(
        "--device",
        default=None,
        help="ultralytics device, e.g. cpu / 0 (default: ultralytics picks)",
    )
    parser.add_argument(
        "--out",
        default=None,
        help="where the best weights are copied (default: deduced from --data and --model)",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)

    try:
        from ultralytics import YOLO
    except ImportError as exc:
        print(f"ultralytics is not installed ({exc}). Install requirements-ml.txt first.")
        return 1

    is_seg = "-seg" in str(args.model).lower()
    is_fire = "fire" in str(args.data).lower()

    if args.project == DEFAULT_PROJECT and is_fire:
        args.project = "runs/fire"
        args.name = "fire-uav"

    if not args.out:
        if is_fire:
            args.out = "models/fire-yolov8n-seg.pt" if is_seg else "models/fire-yolov8n.pt"
        else:
            args.out = "models/regreen-burn-yolov8n-seg.pt" if is_seg else "models/regreen-burn-yolov8n.pt"

    print(f"Training {args.model} ({'segmentation' if is_seg else 'detection'}) on {args.data}")
    print(f"Epochs: {args.epochs} | Image size: {args.imgsz} px | Batch size: {args.batch}")
    print(f"Destination target: {args.out}")
    model = YOLO(args.model)
    model.train(
        data=args.data,
        epochs=args.epochs,
        imgsz=args.imgsz,
        batch=args.batch,
        project=args.project,
        name=args.name,
        device=args.device,
        exist_ok=True,
    )

    best = _best_weights(model)
    if best is None:
        print("Training finished but no best.pt was found; check the run directory.")
        return 1

    # Validate the checkpoint that will ship, not the last epoch: best.pt is what
    # the API will load, and its metrics are the ones worth quoting.
    metrics = YOLO(str(best)).val(
        data=args.data, imgsz=args.imgsz, batch=args.batch, device=args.device, verbose=False
    )
    print("\n" + format_report(collect_metrics(metrics, weights=str(best), data=args.data)))

    destination = Path(args.out)
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(best, destination)
    print(f"\nCopied {best} -> {destination}")
    print("Restart the ML service to pick it up (weights are loaded once per process).")

    return 0


def _best_weights(model: Any) -> Path | None:
    """Locate the best checkpoint ultralytics wrote for this run."""
    trainer = getattr(model, "trainer", None)
    if trainer is None:
        return None

    candidates = [
        Path(str(getattr(trainer, "best", ""))),
        Path(str(getattr(trainer, "save_dir", ""))) / "weights" / "best.pt",
    ]

    for candidate in candidates:
        if str(candidate) not in ("", ".") and candidate.is_file():
            return candidate

    return None


if __name__ == "__main__":
    raise SystemExit(main())
