#!/usr/bin/env python3
import argparse
import json
import os
import sys
from collections import defaultdict

try:
    import cv2
    import numpy as np
except Exception as exc:
    print(json.dumps({
        "success": False,
        "error": f"OpenCV/Numpy import failed: {str(exc)}"
    }))
    sys.exit(1)


def density_fallback(image_path):
    image = cv2.imread(image_path)

    if image is None:
        return {
            "success": False,
            "error": f"Image could not be opened: {image_path}",
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

    is_dense = density_score >= 55 or contour_count >= 80

    if is_dense:
        detections = [
            {
                "class_name": "unknown_mixed_ewaste_pile",
                "quantity": 1,
                "confidence": max(55, min(86, density_score)),
                "source": "opencv_density_fallback",
                "reason": "Dense visible pile detected before trained YOLO model is available."
            }
        ]
    else:
        detections = [
            {
                "class_name": "unknown_electronic_item",
                "quantity": 1,
                "confidence": 35,
                "source": "opencv_density_fallback",
                "reason": "Trained YOLO model is not available; item requires staff verification."
            }
        ]

    return {
        "success": True,
        "engine": "opencv_density_fallback",
        "model_loaded": False,
        "density": {
            "density_score": density_score,
            "edge_density": round(edge_density, 4),
            "contour_count": contour_count,
            "is_dense": is_dense,
        },
        "detections": detections,
        "raw_boxes": [],
    }


def yolo_detect(image_path, model_path, confidence):
    try:
        from ultralytics import YOLO
    except Exception as exc:
        fallback = density_fallback(image_path)
        fallback["warning"] = f"Ultralytics not installed. Using fallback. Details: {str(exc)}"
        return fallback

    if not model_path or not os.path.isfile(model_path):
        fallback = density_fallback(image_path)
        fallback["warning"] = f"YOLO model not found at: {model_path}. Using fallback."
        return fallback

    model = YOLO(model_path)
    results = model.predict(
        source=image_path,
        conf=confidence,
        imgsz=640,
        verbose=False
    )

    counts = defaultdict(int)
    confs = defaultdict(list)
    raw_boxes = []

    names = model.names if hasattr(model, "names") else {}

    for result in results:
        boxes = getattr(result, "boxes", None)

        if boxes is None:
            continue

        for box in boxes:
            cls_id = int(box.cls[0].item())
            conf = float(box.conf[0].item()) * 100
            class_name = str(names.get(cls_id, f"class_{cls_id}"))
            class_name = class_name.strip().lower().replace(" ", "_").replace("-", "_")

            counts[class_name] += 1
            confs[class_name].append(conf)

            xyxy = box.xyxy[0].tolist()
            raw_boxes.append({
                "class_name": class_name,
                "confidence": round(conf, 2),
                "box": {
                    "x1": round(float(xyxy[0]), 2),
                    "y1": round(float(xyxy[1]), 2),
                    "x2": round(float(xyxy[2]), 2),
                    "y2": round(float(xyxy[3]), 2),
                }
            })

    detections = []

    for class_name, quantity in counts.items():
        avg_conf = sum(confs[class_name]) / max(1, len(confs[class_name]))

        detections.append({
            "class_name": class_name,
            "quantity": quantity,
            "confidence": round(avg_conf, 2),
            "source": "yolo",
            "reason": "Detected by trained local YOLO model."
        })

    if not detections:
        fallback = density_fallback(image_path)
        fallback["engine"] = "yolo_no_detection_fallback"
        fallback["model_loaded"] = True
        fallback["warning"] = "YOLO model loaded but returned no detections."
        return fallback

    return {
        "success": True,
        "engine": "yolo",
        "model_loaded": True,
        "model_path": model_path,
        "detections": detections,
        "raw_boxes": raw_boxes,
    }


def main():
    parser = argparse.ArgumentParser(description="Local e-waste YOLO detector")
    parser.add_argument("--image", required=True)
    parser.add_argument("--model", default="")
    parser.add_argument("--confidence", type=float, default=0.35)

    args = parser.parse_args()

    if not os.path.isfile(args.image):
        print(json.dumps({
            "success": False,
            "error": f"Image file not found: {args.image}"
        }))
        sys.exit(1)

    result = yolo_detect(args.image, args.model, args.confidence)

    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
