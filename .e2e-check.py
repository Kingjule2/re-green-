"""Throwaway end-to-end check of the farmer upload pipeline.

Logs in through the real HTTP API, uploads a synthetic burnt-land photo to one
of the seeded lands, polls the analysis until it finishes, and prints what the
ML service and the rule-based engines produced. Delete after use.
"""

from __future__ import annotations

import io
import os
import re
import sys
import time
import urllib.parse

import httpx
from PIL import Image, ImageDraw

BASE = os.environ.get("REGREEN_BASE", "http://127.0.0.1:8020")


def burnt_photo() -> bytes:
    """A synthetic post-fire photo: charred ground, bare soil, patchy regrowth."""
    image = Image.new("RGB", (800, 600), (60, 52, 46))
    draw = ImageDraw.Draw(image)

    # Charred ground (near-black warm brown) over the upper half.
    draw.rectangle([0, 0, 800, 300], fill=(38, 32, 28))
    draw.ellipse([80, 40, 300, 180], fill=(30, 26, 24))
    draw.ellipse([420, 90, 700, 260], fill=(44, 36, 30))

    # Exposed mineral soil across the middle.
    draw.rectangle([0, 300, 800, 420], fill=(150, 105, 60))
    draw.rectangle([120, 300, 320, 420], fill=(172, 122, 74))

    # Regrowth at the bottom edge.
    draw.rectangle([0, 420, 800, 600], fill=(96, 132, 52))
    draw.ellipse([40, 430, 260, 580], fill=(70, 116, 44))
    draw.ellipse([520, 440, 780, 600], fill=(84, 128, 56))

    buffer = io.BytesIO()
    image.save(buffer, format="JPEG", quality=88)
    return buffer.getvalue()


def csrf_headers(client: httpx.Client) -> dict[str, str]:
    """Session + CSRF: the API runs in the web middleware group.

    The browser reads the token from the Blade meta tag; a script can read the
    same token from the XSRF-TOKEN cookie Laravel sets for JavaScript clients.
    """
    client.get("/api/v1/public/summary", headers={"Accept": "application/json"})
    token = urllib.parse.unquote(client.cookies.get("XSRF-TOKEN") or "")

    return {"X-XSRF-TOKEN": token, "Accept": "application/json"}


def main() -> int:
    with httpx.Client(base_url=BASE, timeout=60.0, follow_redirects=True) as client:
        login = client.post(
            "/api/v1/auth/login",
            json={"email": "petani@regreen.id", "password": "password"},
            headers=csrf_headers(client),
        )
        print("login:", login.status_code)
        if login.status_code != 200:
            print(login.text[:600])
            return 1
        print("account:", login.json()["data"]["name"], "|", login.json()["data"]["role"])
        headers = csrf_headers(client)

        lands = client.get("/api/v1/lands", headers={"Accept": "application/json"}).json()
        land = next(item for item in lands["data"] if item["name"] == "Lahan Sungai Keruh")
        print("land:", land["id"], land["name"], "| periods:", land["analyses_count"])

        upload = client.post(
            f"/api/v1/lands/{land['id']}/analyses",
            files={"image": ("lahan-uji.jpg", burnt_photo(), "image/jpeg")},
            data={"captured_at": "2026-08-15", "capture_source": "phone", "notes": "Uji end-to-end"},
            headers=headers,
        )
        print("upload:", upload.status_code)
        if upload.status_code != 201:
            print(upload.text[:800])
            return 1

        analysis_id = upload.json()["data"]["id"]
        print("analysis:", analysis_id, "status:", upload.json()["data"]["status"])

        for _ in range(40):
            time.sleep(1.5)
            detail = client.get(f"/api/v1/analyses/{analysis_id}", headers={"Accept": "application/json"}).json()["data"]
            if detail["is_finished"]:
                break
        else:
            print("timed out waiting for the analysis")
            return 1

        print("status:", detail["status"], "| error:", detail["error"])
        print("burn severity:", detail["burn_severity"]["level"], detail["burn_severity"]["score"],
              "| method:", detail["burn_severity"]["method"], "| reason:", detail["burn_severity"]["reason"])
        print("model:", detail["model"])
        print("metrics:", {key: detail["metrics"][key] for key in ("health_score", "vegetation_percentage", "bare_soil_percentage", "charred_percentage", "restoration_potential")})
        print("progress:", detail["progress"])

        intelligence = detail["land_intelligence"]
        print("land cover:", [(entry["key"], entry["percentage"]) for entry in intelligence["land_cover"]])
        print("issues:", [(issue["type"], issue["severity"]) for issue in intelligence["issues"]])
        agriculture = intelligence["agriculture"]
        print("ranking available:", agriculture["ranking_available"], "| reason:", agriculture["ranking_reason"])
        print("input sources:", agriculture["input_sources"])
        print("top crops:", [(crop["name"], crop["score"], crop["classification"]) for crop in agriculture["crops"][:3]])
        print("recommendations:", [(item["action"], item["priority"]) for item in detail["recommendation"]["recommendations"]])
        print("detections:", len(detail["detections"]))

        progress = client.get(f"/api/v1/lands/{land['id']}/progress", headers={"Accept": "application/json"}).json()["data"]
        print("monitoring periods:", progress["periods"], "| comparison:", progress["comparison"]["direction"],
              progress["comparison"]["vegetation_delta"], "pp over", progress["span_days"], "days")

        carbon = client.get(f"/api/v1/lands/{land['id']}/carbon", headers={"Accept": "application/json"}).json()["data"]
        print("carbon:", carbon["eligibility"]["status"], carbon["sequestration_tco2e_per_year"], "tCO2e/yr |",
              len(carbon["eligibility"]["failed"]), "failed checks")

        report = client.post(
            "/api/v1/reports",
            json={"scope": "all", "title": "Uji laporan"},
            headers=headers,
        )
        print("report:", report.status_code, "| lands in snapshot:", report.json()["data"]["summary"]["lands"])
        pdf = client.get(f"/api/v1/reports/{report.json()['data']['id']}/pdf", headers={"Accept": "application/json"})
        print("pdf:", pdf.status_code, pdf.headers.get("content-type"), pdf.content[:8])

    return 0


if __name__ == "__main__":
    sys.exit(main())
