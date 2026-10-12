# LayRate Forecast API

This directory contains the Python forecasting pipeline for the LayRate poultry farm management system. It trains SARIMA and XGBoost models on historical data aggregated on demand from the app's native production tables (`production_logs`, `environmental_logs`, etc.) and returns egg-production forecasts to the Laravel backend.

## What is inside

| File | Purpose |
|------|---------|
| `ForecastingV5.py` | SARIMA/XGBoost model selection, with a three-model XGBoost ensemble. |
| `forecast_runner.py` | CLI entry point executed by Laravel. Outputs JSON. |
| `generate_forecast_sheet.py` | Creates a protected Excel template for operators to fill in. |
| `import_forecast_input.py` | Imports a filled Excel sheet into the app's native production tables. |
| `requirements.txt` | Python dependencies. |
| `Dockerfile` | Container image for the forecasting environment. |
| `Forecast.py` | Older standalone Excel prototype (not used by Laravel). |

## Prerequisites

- Python 3.11 (other 3.x versions may work but are not tested)
- Access to the LayRate MySQL/MariaDB database
- The Laravel application database must already be created and migrated

## Setup

### 1. Create a virtual environment

From the project root:

```bash
cd forecast-api
python -m venv .venv
```

On Windows:

```powershell
.venv\Scripts\activate
```

On Linux/macOS:

```bash
source .venv/bin/activate
```

### 2. Install dependencies

With the virtual environment activated:

```bash
pip install -r requirements.txt
```

To verify the installation:

```bash
python -c "import ForecastingV5; print('OK')"
```

## Environment variables

The scripts read database credentials from the environment. You can either export them manually or rely on the parent Laravel `.env` file (some scripts load it automatically).

Required variables:

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=layrate
DB_USERNAME=root
DB_PASSWORD=root
```

Laravel also uses:

```bash
FORECAST_PYTHON_BINARY=python
```

If a virtual environment exists at `forecast-api/.venv`, the Laravel controller will use it automatically. Otherwise, set the full path:

```bash
FORECAST_PYTHON_BINARY=C:\Users\donor\OneDrive\Desktop\Layrate_Forecasting\forecast-api\.venv\Scripts\python.exe
```

## Usage

### Generate a forecast input sheet

This produces a protected Excel file with locked pre-filled columns (Date, Cage_Code, Breed, Flock_Age_Weeks, Hen_Count) and editable data columns.

```bash
# Default: last 90 days ending today
python generate_forecast_sheet.py --days 90 --output forecast_input_sheet.xlsx

# Custom date range
python generate_forecast_sheet.py --start-date 2026-01-01 --end-date 2026-01-30 --output forecast_input_sheet.xlsx
```

### Import a filled forecast sheet

```bash
python import_forecast_input.py forecast_input_sheet.xlsx
```

Existing rows for the same `(date, cage_code)` are updated; new rows are inserted.

### Run a forecast manually

```bash
python forecast_runner.py --mode auto --cage ALL --breed ALL --horizon 7
```

Options:

- `--mode` : `auto` (uses last known features) or `manual` (requires extra parameters)
- `--cage` : cage code (e.g. `CAGE-A`) or `ALL`
- `--breed` : breed name or `ALL`
- `--horizon` : `7`, `14`, or `30`

Manual mode example:

```bash
python forecast_runner.py --mode manual \
  --cage ALL --breed ALL --horizon 7 \
  --manual-breed "ISA Brown" \
  --live-hens 100 \
  --flock-age-weeks 24 \
  --temperature-c 29.0 \
  --humidity-percent 75.0 \
  --crude-protein-percent 16.5 \
  --total-feed-consumed-kg 45.0 \
  --monthly-mortality 0 \
  --heat-stress 0
```

## Laravel integration

The Laravel `ForecastController` calls `forecast_runner.py` automatically when you click **Generate Forecast** in the web UI. You do not need to run the scripts by hand unless you want to test the pipeline or generate/import sheets outside the browser.

Laravel commands related to forecasting:

```bash
# List scheduled tasks
php artisan schedule:list
```

> The old `forecast:sync-input-records` command and its scheduled syncs were
> removed — the forecasting pipeline now aggregates production data on demand,
> so no denormalized `forecast_input_records` copy is maintained.

## Saved forecasts and comparison

Each scope (farm, cage, or breed) has one fixed forecast per target day.
Generating an overlapping range keeps saved values and fills only missing dates.
A fully saved range returns immediately without retraining. The 7/14/30-day
controls and custom day/range selection remain available.

**Compare Production** shows the saved forecast next to completed production.
Missing or unfinished production remains pending; a recorded zero is compared.
The dashboard also uses the same fixed predictions. Existing run history is
retained, and the earliest advance prediction is used for older duplicate runs.
Saved forecasts cannot be cleared or replaced through the normal interface.

**Advanced ? Historical Test** is for retrospective testing and stays separate
from saved advance forecasts. Flock age increases as future forecast days advance
in both automatic and manual forecasting modes.

## Forecast worker and progress

Forecast generation needs a running queue worker when `QUEUE_CONNECTION=database`.
On Windows, the restart loop can be launched from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/queue-worker-local.ps1
```

On the Pi, use the supervised `infra/layrate-queue-worker.service` service.
After deploying changes, run `php artisan queue:restart` so workers reload code.
The Python process has a 300-second limit, the job a 330-second limit, and queue
reservation expiry is at least 360 seconds to avoid concurrent retries.

The progress bar is an estimate until results are saved. Completion reaches 100%;
failed/stalled jobs and network deadlines release the UI and show an error.
Missing production data and invalid input should be corrected before retrying.

## Data requirements

`ForecastingV5.py` requires at least **90 historical records** for the selected scope (whole farm, cage, or breed) before it can train and evaluate models.

## Docker (optional)

Build the image:

```bash
docker build -t layrate-forecast .
```

Run a forecast inside the container:

```bash
docker run --rm \
  -e DB_HOST=host.docker.internal \
  -e DB_PORT=3306 \
  -e DB_DATABASE=layrate \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=root \
  layrate-forecast \
  python forecast_runner.py --mode auto --horizon 7
```

## Troubleshooting

### `ModuleNotFoundError: No module named '...'`

Activate the virtual environment and reinstall dependencies:

```bash
pip install -r requirements.txt
```

### `Dataset must have at least 90 records`

Import a forecast input sheet covering at least 90 days, or run the Laravel sync command to backfill from existing app tables.

### Laravel reports the Python environment is missing packages

Make sure `FORECAST_PYTHON_BINARY` points to the interpreter inside `forecast-api/.venv`, or ensure the venv exists so the controller auto-detects it.

### `OSError: [WinError 10106]`

The PHP process must pass Windows system environment variables (`SYSTEMROOT`, `SYSTEMDRIVE`, `WINDIR`, `PATH`) to the Python subprocess. The controller already does this; if the error persists, verify the PHP/web server has access to those variables.
