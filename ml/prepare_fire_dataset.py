"""Convert BurnedAreaUAV_dataset JSON polygons into YOLOv8 segmentation dataset.

Outputs to:
    ml/datasets/fire/
    ├── data.yaml
    ├── images/
    │   ├── train/
    │   └── val/
    └── labels/
        ├── train/
        └── val/
"""

import json
import shutil
from pathlib import Path
from PIL import Image

def clamp(val: float, min_val: float = 0.0, max_val: float = 1.0) -> float:
    return max(min_val, min(max_val, val))

def convert_split(
    frames_dir: Path,
    jsons_dir: Path,
    out_img_dir: Path,
    out_lbl_dir: Path,
    class_id: int = 0,
) -> int:
    out_img_dir.mkdir(parents=True, exist_ok=True)
    out_lbl_dir.mkdir(parents=True, exist_ok=True)

    json_files = sorted(jsons_dir.glob("*.json"))
    converted_count = 0

    for jpath in json_files:
        frame_name = jpath.stem + ".png"
        img_src = frames_dir / frame_name
        if not img_src.is_file():
            # Try alternate extension or check if frame exists
            continue

        with open(jpath, "r", encoding="utf-8") as f:
            data = json.load(f)

        shapes = data.get("shapes", [])
        
        # Get dimensions
        img_w = data.get("imageWidth")
        img_h = data.get("imageHeight")
        if not img_w or not img_h:
            with Image.open(img_src) as img:
                img_w, img_h = img.size

        lines = []
        for shape in shapes:
            label = shape.get("label", "").lower()
            if label not in ("fogo", "fire"):
                continue
            
            pts = shape.get("points", [])
            if len(pts) < 3:
                continue

            poly_coords = []
            for pt in pts:
                nx = clamp(float(pt[0]) / float(img_w))
                ny = clamp(float(pt[1]) / float(img_h))
                poly_coords.append(f"{nx:.6f} {ny:.6f}")

            line = f"{class_id} " + " ".join(poly_coords)
            lines.append(line)

        # Write label file (even if empty to denote background / negative sample)
        lbl_dst = out_lbl_dir / (jpath.stem + ".txt")
        with open(lbl_dst, "w", encoding="utf-8") as f:
            f.write("\n".join(lines))
            if lines:
                f.write("\n")

        # Copy image file
        img_dst = out_img_dir / frame_name
        if not img_dst.is_file() or img_dst.stat().st_size != img_src.stat().st_size:
            shutil.copyfile(img_src, img_dst)

        converted_count += 1

    return converted_count


def main():
    root_dir = Path(__file__).resolve().parent.parent
    ds_root = root_dir / "kumpulan ds" / "BurnedAreaUAV_dataset"
    out_root = Path(__file__).resolve().parent / "datasets" / "fire"

    train_frames = ds_root / "PNG" / "train" / "frames"
    train_jsons = ds_root / "JSON" / "train_valid_json"
    test_frames = ds_root / "PNG" / "test" / "frames"
    test_jsons = ds_root / "JSON" / "test_json"

    print(f"Converting BurnedAreaUAV_dataset from: {ds_root}")
    print(f"Outputting to: {out_root}")

    train_count = convert_split(
        train_frames,
        train_jsons,
        out_root / "images" / "train",
        out_root / "labels" / "train",
    )
    val_count = convert_split(
        test_frames,
        test_jsons,
        out_root / "images" / "val",
        out_root / "labels" / "val",
    )

    yaml_content = f"""# BurnedAreaUAV Fire Dataset (YOLOv8 Segmentation)
path: datasets/fire
train: images/train
val: images/val

names:
  0: fogo
"""
    yaml_path = out_root / "data.yaml"
    with open(yaml_path, "w", encoding="utf-8") as f:
        f.write(yaml_content)

    print(f"Done! Converted {train_count} train images and {val_count} val images.")
    print(f"Dataset config written to: {yaml_path}")


if __name__ == "__main__":
    main()
