# Regreen ML Service

A standalone Python (FastAPI) microservice for the AI/ML side of Regreen. The
Laravel app stays the source of truth and calls this service over HTTP for
inference. Keeping ML in its own process means the Python dependency tree
(NumPy, PyTorch, etc.) never touches the PHP app, and the model can scale or
deploy independently.

```
Browser ──▶ Laravel (PHP)  ──HTTP──▶  ML service (FastAPI)  ──▶  model
                 ▲                                                  │
                 └──────────────  prediction JSON  ◀───────────────┘
```

## Layout

```
ml/
├── app/
│   ├── main.py         # FastAPI app + middleware + router wiring
│   ├── config.py       # env-driven settings (ML_* vars)
│   ├── security.py     # X-API-Key shared-secret dependency
│   ├── schemas.py      # Pydantic request/response models
│   ├── land_analysis.py   # swappable land-cover (Computer Vision) models
│   ├── severity.py        # burn classes, weights and severity bands (one source of truth)
│   ├── yolo.py            # YOLOv8 burn detector (lazy ultralytics/torch import)
│   ├── terrain.py         # DEM math: slope, aspect, hillshade, ruggedness
│   ├── terrain_sources.py # DEM sources: BIG DEMNAS + NASA SRTM (Earth Engine)
│   └── routers/
│       ├── health.py      # GET /health (public)
│       ├── predict.py     # POST /api/v1/predict (API-key protected)
│       └── land.py        # POST /api/v1/land-analysis (API-key protected)
├── tests/              # pytest smoke tests
├── train_yolo.py       # fine-tune the burn detector, validate, publish weights
├── evaluate_yolo.py    # mAP50 / mAP50-95 / precision / recall per class
├── requirements.txt    # base runtime deps (light, 3.14-safe)
├── requirements-ml.txt # ultralytics + torch, only for training/inference of YOLO
├── requirements-dev.txt
└── .env.example
```

## Setup

From the `ml/` directory:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1        # Windows PowerShell
# source .venv/bin/activate         # macOS / Linux

pip install -r requirements-dev.txt
copy .env.example .env              # cp on macOS/Linux
```

> Python note: this repo's interpreter is 3.14, which is very new. The base
> dependencies install fine, but heavier ML wheels (PyTorch, TensorFlow, older
> NumPy) may not ship cp314 builds yet. If an install fails, either pin a
> version that has a 3.14 wheel or run this service on Python 3.12. The detector
> stack lives in its own file for this reason: `pip install -r requirements-ml.txt`
> only where training or YOLO inference actually runs (see "Fire severity and the
> YOLOv8 burn detector").

## Run

```powershell
uvicorn app.main:app --reload --port 8001
```

- Swagger UI: http://localhost:8001/docs
- Health: http://localhost:8001/health

## Test

```powershell
pytest
```

## Calling it from Laravel

`config/services.php` exposes an `ml` block, driven by these `.env` values:

```env
ML_SERVICE_URL=http://localhost:8001
ML_SERVICE_KEY=            # must match the service's ML_API_KEY
```

```php
$response = Http::withHeader('X-API-Key', config('services.ml.key'))
    ->baseUrl(config('services.ml.url'))
    ->post('/api/v1/predict', ['features' => [0.2, 1.4, 3.1]]);

$prediction = $response->json('prediction');
```

Land analysis goes through `App\Services\Ml\LandAnalysisClient`, which posts the
image as `multipart/form-data` with the survey's `latitude` / `longitude` and
normalizes the response (land cover, segmentation, fire severity, detections,
terrain) into the contract `App\Services\Land\LandIntelligenceEngine` consumes.

## Agricultural context (DEM, soil, climate)

`POST /api/v1/land-analysis` accepts optional `latitude` / `longitude` form
fields next to the image. When they are present the service samples everything
that can be measured about that point and returns it beside the land cover:

| Block | Contents | Source |
| --- | --- | --- |
| `terrain` | elevation, slope, aspect, hillshade, ruggedness | **DEMNAS** (BIG, Indonesia, ~8 m) or **NASA SRTM V3** (Earth Engine, ~30 m) |
| `soil` | USDA texture class, sand/silt/clay, pH, organic carbon, nitrogen | **ISRIC SoilGrids v2.0** (global, 250 m) |
| `climate` | annual + monthly rainfall, dry/wet months, temperature, humidity, topsoil wetness | **NASA POWER** agroclimatology (free, no key) |

Each block reports its own provenance (`source`) and either its values or the
reason they are missing — a dataset that is unreachable degrades one block and
nothing else, because a missing elevation model must never take the land-cover
result down with it.

```env
# Terrain
ML_TERRAIN_SOURCE=auto            # auto | demnas | srtm | none
ML_TERRAIN_FALLBACK=true
ML_TERRAIN_TIMEOUT=20
ML_DEMNAS_SERVICE_URL=https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer
ML_GEE_PROJECT=                   # SRTM via Earth Engine needs a service account
ML_GEE_SERVICE_ACCOUNT=
ML_GEE_PRIVATE_KEY_FILE=

# Soil
ML_SOIL_SOURCE=soilgrids          # soilgrids | none
ML_SOIL_TIMEOUT=30

# Climate
ML_CLIMATE_SOURCE=power           # power | none
ML_CLIMATE_TIMEOUT=30

# Soil and climate normals change yearly: samples are cached per coordinate.
ML_POINT_CACHE_SIZE=256
```

### What each source is, and why it is here

* **DEMNAS** — Badan Informasi Geospasial's national DEM, built from IFSAR (5 m),
  TERRASAR-X (5 m) and ALOS PALSAR (11.25 m) data, served as a 0.27 arc-second
  (~8 m) product. Preferred inside Indonesia; the authoritative model there.
  <https://tanahair.indonesia.go.id/portal-web/unduh/demnas> ·
  service: <https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer>
* **NASA SRTM V3** (`USGS/SRTMGL1_003`) — ~30 m global coverage (56 S to 60 N)
  through Earth Engine, for points outside Indonesia and as a fallback.
  <https://developers.google.com/earth-engine/datasets/catalog/USGS_SRTMGL1_003>
* **ISRIC SoilGrids v2.0** — global 250 m soil properties; the response's own
  `d_factor` is applied to convert mapped units, and the USDA texture triangle
  is applied locally because crop requirements are written against class names.
  <https://soilgrids.org>
* **NASA POWER** — agroclimatology climatology (20-year, 2001-2020) from the
  POWER project: rainfall, temperature, humidity and topsoil wetness. Free and
  keyless, which is why it carries the rainfall end of the story.
  <https://power.larc.nasa.gov>
* **Ina-GeoPortal** — the national geospatial catalogue (administrative
  boundaries, hydrography, infrastructure): <https://tanahair.indonesia.go.id/portal-web>

### How the products are derived

`app/terrain.py` follows the published operators — Horn (1981) for slope and
aspect, the ESRI/GDAL illumination model for hillshade, Riley et al. (1999) for
ruggedness — so the same window of elevation samples always yields the same
numbers. Slope and aspect need a 3x3 window, so DEMNAS is read as nine samples
one pixel apart; Earth Engine computes its four products server-side in one call.

`app/soil.py` normalizes the three texture fractions and names the class with
the USDA triangle; `app/climate.py` converts POWER's mm/day means into monthly
totals and classifies months with the Oldeman/BMKG thresholds (< 60 mm is a dry
month, > 100 mm a wet one). The rainfall *impact* on a specific slope — erosivity
and drainage risk — is derived downstream in `app/Services/Agriculture`.

## From context to agriculture (Laravel side)

The ML service reports measurements; the decisions are made in PHP, where the
rest of the pipeline lives (`app/Services/Agriculture/`):

* `CropCatalog` — ten Indonesian crops (arabica/robusta coffee, cocoa, tea,
  avocado, banana, rice, maize, rubber, oil palm) with the elevation,
  temperature, rainfall, pH, slope, moisture and texture envelope each one
  grows in, plus the weight the scoring engine gives each parameter. Ported from
  the ReGreen prototype's `data/crops.js` so the numbers keep matching the design.
* `CropSuitabilityEngine` — weighted multi-criteria scoring for every crop at
  this site (sub-score per parameter, combined by the crop's own weights) and a
  classification from *Very Suitable* down to *Not Suitable*. Parameters that
  were never measured are excluded and their weights renormalized, and the
  result names them in `missing_inputs` instead of inventing a neutral score.
* `AgricultureEngine` — assembles the assessment: the land's own capacity (slope
  x elevation band), the ranked crops, and the **rainfall impact** — erosivity
  (rain energy on that gradient, capped by whichever of the two is weaker),
  drainage risk (frequent wet months on flat heavy soil) and the dry season that
  has to be bridged with stored water.

`RestorationRecommendationEngine` then turns that into advice: `crop_recommendation`
(the best-ranked crop that clears the *moderately suitable* bar, with its
limiting factors called out), `rainfall_management` (water storage, planting
calendar, drainage), and the existing slope conservation and bioengineering
recommendations for the post-disaster case.

Crop ranking needs both the terrain and the climate context; when either is
missing the assessment reports `ranking_available: false` rather than ranking
crops off half a picture.

## Fire severity and the YOLOv8 burn detector

`POST /api/v1/land-analysis` returns two burn-specific blocks next to the land
cover. Both come from whichever model is active, so swapping the model changes
the accuracy, never the shape of the answer:

```json
"fire_severity": {
  "available": true, "reason": null,
  "level": "moderate", "label": "Moderate", "score": 52.0, "confidence": 0.81,
  "method": "yolov8",
  "evidence": {"charred_soil_pct": 18.0, "bare_soil_pct": 26.0, "vegetation_pct": 44.0,
               "detections": {"charred_soil": 3, "vegetation_regrowth": 1}}
},
"detections": [{"label": "charred_soil", "confidence": 0.87, "box": [0.12, 0.34, 0.22, 0.18]}],
"model": {"name": "yolov8n-burn", "version": "0.2.0", "task": "detect",
          "weights": "models/regreen-burn-yolov8n.pt", "device": "cpu"}
```

### The six detector classes and how they become land cover

`app/severity.py` is the single source of truth (`DETECTOR_CLASSES`,
`DETECTOR_TO_COVER`, `SEVERITY_WEIGHTS`, `SEVERITY_BANDS`); the legend order is
asserted against `land_analysis.CLASS_COLORS` in the tests, so the two
vocabularies cannot drift.

| Detector class (dataset order) | Land cover | Burn weight |
| --- | --- | --- |
| `unburned_vegetation` | `dense_vegetation` | 0 |
| `vegetation_regrowth` | `sparse_vegetation` | 20 |
| `bare_soil` | `bare_soil` | 60 |
| `charred_soil` | `charred_soil` (`#3f3f46`) | 100 |
| `water` | `water` | — (excluded) |
| `built_area` | `built_area` | — (excluded) |

Area no box covers is reported as `other`, so `land_cover` always has all seven
classes and always sums to exactly 100 %. `water` and `built_area` carry no burn
weight and are excluded from the mean rather than diluting it.

### Severity

`score` is the **area-weighted mean** of the detected regions —
`sum(area_i × weight_i) / sum(area_i)` over the four weighted classes — and the
bands are `< 10` unburned, `< 35` low, `< 65` moderate, `>= 65` high. Colour
(`SEVERITY_COLORS`) runs cool-to-hot alongside the levels.

The colour baseline (`ColorHistogramModel`) applies *the identical formula* to
the pixel fractions its classifier produced, which is why the two paths return
comparable numbers for comparable evidence — a test pins that equality. Only
`confidence` differs in meaning: the detector reports its own area-weighted mean
detection confidence, the fallback reports how much of the frame carries burn
evidence at all.

`charred_soil` is its own colour class in the baseline: dark (`0.12 <= v <= 0.45`),
desaturated (`s <= 0.45`) and warm or effectively colourless (`h <= 0.17` or
`h >= 0.90` or `s <= 0.18`). Deep shadow (`v < 0.12`) and dark *saturated*
pixels (dark green canopy, blue-grey shade) stay out of it, so regrowth is never
counted as burnt.

### Training the detector

`train_yolo.py` fine-tunes a COCO-pretrained checkpoint on the six-class burn
dataset, validates the resulting `best.pt`, and copies it to
`models/regreen-burn-yolov8n.pt` — the path `ML_YOLO_WEIGHTS` already points at:

```powershell
pip install -r requirements-ml.txt          # Python 3.12/3.13: no cp314 torch yet
python train_yolo.py --data datasets/burn/data.yaml
python train_yolo.py --data datasets/burn/data.yaml --model yolov8s.pt --epochs 150 --device 0
```

The dataset is standard YOLO detection format, and the module docstring carries
the layout, the `data.yaml` class order and the labelling guide:

```
datasets/burn/
├── data.yaml                # names: the six classes, in the order above
├── images/{train,val}/*.jpg
└── labels/{train,val}/*.txt # `class cx cy w h`, normalized to 0-1
```

### Evaluating it

```powershell
python evaluate_yolo.py --data datasets/burn/data.yaml
python evaluate_yolo.py --data datasets/burn/data.yaml --json reports/burn-metrics.json
```

It prints overall mAP50, mAP50-95, precision and recall plus a per-class table
(straight from ultralytics' validator, so the numbers are comparable with
published burn-scar detectors). Those are the accuracy figures worth quoting in
the pitch — alongside the split they were measured on.

### Honest status: the weights are not in this repository

No trained burn weights ship with the repo (model artifacts stay out of git), and
`ultralytics`/torch are not in `requirements.txt`. Until both are present,
`get_model()` selects `ColorHistogramModel` and the response says exactly what is
missing instead of pretending:

```json
"model": {"name": "color-histogram", "version": "0.1.0", "task": null, "weights": null, "device": null},
"fire_severity": {
  "method": "colour-heuristic",
  "reason": "the `ultralytics` package is not installed in this environment; no detector weights at
             '.../ml/models/regreen-burn-yolov8n.pt' (set ML_YOLO_WEIGHTS), so fire severity comes from
             the colour heuristic instead of YOLOv8. To enable the detector, install
             ml/requirements-ml.txt (ultralytics + torch) and train the weights with
             `python train_yolo.py --data <dataset>/data.yaml` (see ml/README.md)."
}
```

So the numbers this service reports today are colour-heuristic estimates, labelled
as such in `fire_severity.method` and `fire_severity.reason`, and the whole
detector path (boxes → cover → severity → grid) is covered by tests with a stubbed
detector until real weights exist.

```env
ML_YOLO_WEIGHTS=models/regreen-burn-yolov8n.pt
ML_YOLO_CONFIDENCE=0.25
ML_YOLO_IOU=0.45
ML_YOLO_IMGSZ=640
ML_YOLO_DEVICE=                 # empty: ultralytics picks (GPU when available, else CPU)
ML_YOLO_MODEL_VERSION=0.2.0
```

## Replacing the stub model

`app/routers/predict.py` → `_run_inference()` currently returns the mean of the
input features. Swap it for a real model load + call (scikit-learn via joblib,
an ONNX Runtime session, a PyTorch model, or a hosted LLM client). Keep model
artifacts out of git — the `.gitignore` already excludes `models/` and common
weight formats.
