#!/usr/bin/env python3
import argparse
import json
import os
import uuid
from pathlib import Path

from PIL import Image, ImageOps, ImageEnhance

try:
    import cv2
    import numpy as np
except Exception:
    cv2 = None
    np = None


def ensure_dir(path: str) -> None:
    Path(path).mkdir(parents=True, exist_ok=True)


def safe_float(value, default=0.0):
    try:
        return float(value)
    except Exception:
        return default


def calculate_density_score(image_path: str) -> dict:
    if cv2 is None or np is None:
        return {
            "density_score": 50,
            "edge_density": 0,
            "contour_count": 0,
            "is_dense": False,
            "method": "fallback_no_opencv"
        }

    image = cv2.imread(image_path)

    if image is None:
        return {
            "density_score": 50,
            "edge_density": 0,
            "contour_count": 0,
            "is_dense": False,
            "method": "opencv_failed"
        }

    resized = cv2.resize(image, (900, 600))
    gray = cv2.cvtColor(resized, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (3, 3), 0)

    edges = cv2.Canny(gray, 50, 150)
    edge_density = float(np.count_nonzero(edges)) / float(edges.size)

    contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    useful_contours = []
    for contour in contours:
        area = cv2.contourArea(contour)
        if 40 <= area <= 30000:
            useful_contours.append(contour)

    contour_count = len(useful_contours)

    density_score = min(
        100,
        round((edge_density * 100 * 1.7) + min(contour_count / 4, 55), 2)
    )

    return {
        "density_score": density_score,
        "edge_density": round(edge_density, 4),
        "contour_count": contour_count,
        "is_dense": density_score >= 55 or contour_count >= 80,
        "method": "opencv"
    }


def save_enhanced_image(source_path: str, output_dir: str) -> str:
    image = Image.open(source_path)
    image = ImageOps.exif_transpose(image)
    image = image.convert("RGB")

    max_width = 1800
    if image.width > max_width:
        ratio = max_width / float(image.width)
        image = image.resize((max_width, int(image.height * ratio)))

    image = ImageEnhance.Sharpness(image).enhance(1.25)
    image = ImageEnhance.Contrast(image).enhance(1.12)
    image = ImageEnhance.Color(image).enhance(1.05)

    output_path = os.path.join(output_dir, "enhanced_full.jpg")
    image.save(output_path, "JPEG", quality=92)

    return output_path


def create_grid_tiles(image_path: str, output_dir: str, rows: int, cols: int, max_tiles: int) -> list:
    image = Image.open(image_path).convert("RGB")
    width, height = image.size

    tiles = []
    tile_index = 1

    tile_width = width // cols
    tile_height = height // rows

    for row in range(rows):
        for col in range(cols):
            if len(tiles) >= max_tiles:
                return tiles

            left = col * tile_width
            top = row * tile_height
            right = width if col == cols - 1 else (col + 1) * tile_width
            bottom = height if row == rows - 1 else (row + 1) * tile_height

            crop = image.crop((left, top, right, bottom))

            tile_name = f"tile_{tile_index:02d}_r{row + 1}_c{col + 1}.jpg"
            tile_path = os.path.join(output_dir, tile_name)
            crop.save(tile_path, "JPEG", quality=92)

            tiles.append({
                "tile_id": f"tile_{tile_index:02d}",
                "row": row + 1,
                "col": col + 1,
                "path": tile_path,
                "box": {
                    "left": left,
                    "top": top,
                    "right": right,
                    "bottom": bottom
                }
            })

            tile_index += 1

    return tiles


def main():
    parser = argparse.ArgumentParser(description="Deep image analyzer crop generator")
    parser.add_argument("--image", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--max-tiles", type=int, default=9)
    parser.add_argument("--rows", type=int, default=3)
    parser.add_argument("--cols", type=int, default=3)

    args = parser.parse_args()

    ensure_dir(args.output_dir)

    enhanced_path = save_enhanced_image(args.image, args.output_dir)
    density = calculate_density_score(enhanced_path)

    tiles = create_grid_tiles(
        enhanced_path,
        args.output_dir,
        rows=max(1, args.rows),
        cols=max(1, args.cols),
        max_tiles=max(1, args.max_tiles),
    )

    image = Image.open(enhanced_path)

    result = {
        "analysis_id": str(uuid.uuid4()),
        "source_image": args.image,
        "enhanced_image": enhanced_path,
        "width": image.width,
        "height": image.height,
        "density": density,
        "tiles": tiles,
        "tile_count": len(tiles),
    }

    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
