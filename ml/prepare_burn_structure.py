"""Setup and validate the 6-class ReGreen post-wildfire restoration dataset.

Standard layout:
    ml/datasets/burn/
    ├── data.yaml
    ├── images/
    │   ├── train/
    │   └── val/
    └── labels/
        ├── train/
        └── val/

The six required classes (in order):
    0: unburned_vegetation
    1: vegetation_regrowth
    2: bare_soil
    3: charred_soil
    4: water
    5: built_area
"""

import sys
from pathlib import Path

REGREEN_CLASSES = {
    0: "unburned_vegetation",
    1: "vegetation_regrowth",
    2: "bare_soil",
    3: "charred_soil",
    4: "water",
    5: "built_area",
}

def init_burn_structure(base_dir: Path):
    images_train = base_dir / "images" / "train"
    images_val = base_dir / "images" / "val"
    labels_train = base_dir / "labels" / "train"
    labels_val = base_dir / "labels" / "val"

    for folder in [images_train, images_val, labels_train, labels_val]:
        folder.mkdir(parents=True, exist_ok=True)
        # Create a .gitkeep if empty
        gitkeep = folder / ".gitkeep"
        if not any(folder.iterdir()):
            gitkeep.touch()

    data_yaml = base_dir / "data.yaml"
    content = f"""# ReGreen 6-Class Post-Wildfire Restoration Dataset (YOLOv8)
path: datasets/burn
train: images/train
val: images/val

names:
  0: unburned_vegetation
  1: vegetation_regrowth
  2: bare_soil
  3: charred_soil
  4: water
  5: built_area
"""
    with open(data_yaml, "w", encoding="utf-8") as f:
        f.write(content)

    print(f"Initialized ReGreen burn dataset structure at: {base_dir}")
    print(f"Created config at: {data_yaml}")


def validate_dataset(base_dir: Path) -> bool:
    print(f"\n--- Validating {base_dir} ---")
    data_yaml = base_dir / "data.yaml"
    if not data_yaml.is_file():
        print(f"ERROR: data.yaml missing at {data_yaml}")
        return False

    valid = True
    for split in ["train", "val"]:
        img_dir = base_dir / "images" / split
        lbl_dir = base_dir / "labels" / split

        img_files = list(img_dir.glob("*.jpg")) + list(img_dir.glob("*.png")) + list(img_dir.glob("*.jpeg"))
        lbl_files = list(lbl_dir.glob("*.txt"))

        print(f"Split [{split}]: {len(img_files)} images, {len(lbl_files)} label files.")

        class_counts = {cid: 0 for cid in REGREEN_CLASSES}
        poly_count = 0
        box_count = 0

        for lpath in lbl_files:
            with open(lpath, "r", encoding="utf-8") as f:
                for line_idx, line in enumerate(f, 1):
                    line = line.strip()
                    if not line:
                        continue
                    parts = line.split()
                    try:
                        cid = int(parts[0])
                    except ValueError:
                        print(f"ERROR in {lpath}:{line_idx}: invalid class '{parts[0]}'")
                        valid = False
                        continue

                    if cid not in REGREEN_CLASSES:
                        print(f"ERROR in {lpath}:{line_idx}: class {cid} not in 0..5")
                        valid = False
                        continue

                    class_counts[cid] += 1
                    coords = [float(x) for x in parts[1:]]
                    if len(coords) == 4:
                        box_count += 1
                    elif len(coords) >= 6 and len(coords) % 2 == 0:
                        poly_count += 1
                    else:
                        print(f"WARNING in {lpath}:{line_idx}: unusual coordinate count ({len(coords)})")

        if lbl_files:
            print(f"  Annotation types: {poly_count} polygons (segmentation), {box_count} boxes (detection)")
            print("  Class distribution:")
            for cid, cname in REGREEN_CLASSES.items():
                print(f"    [{cid}] {cname:<22}: {class_counts[cid]}")

    return valid


def main():
    base_dir = Path(__file__).resolve().parent / "datasets" / "burn"
    init_burn_structure(base_dir)
    validate_dataset(base_dir)


if __name__ == "__main__":
    main()
