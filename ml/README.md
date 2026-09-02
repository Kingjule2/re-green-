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
│   └── routers/
│       ├── health.py   # GET /health (public)
│       └── predict.py  # POST /api/v1/predict (API-key protected)
├── tests/              # pytest smoke tests
├── requirements.txt    # base runtime deps (light, 3.14-safe)
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
> version that has a 3.14 wheel or run this service on Python 3.12.

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

## Replacing the stub model

`app/routers/predict.py` → `_run_inference()` currently returns the mean of the
input features. Swap it for a real model load + call (scikit-learn via joblib,
an ONNX Runtime session, a PyTorch model, or a hosted LLM client). Keep model
artifacts out of git — the `.gitignore` already excludes `models/` and common
weight formats.
