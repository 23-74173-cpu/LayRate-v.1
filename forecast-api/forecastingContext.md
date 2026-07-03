# Forecasting API Context

## File
`forecast-api/ForecastingV5.py`

## Purpose
SARIMA + XGBoost ensemble forecasting for daily egg production (`Total_Eggs`) using the LayRate MySQL database.

## Bugs Fixed

### 1. Cross-cage lag contamination in XGBoost
**Problem:** `build_xgb_training_frame()` created lag/rolling features by shifting the entire target column globally. With multiple cages in the dataframe, the first rows of one cage inherited lag values from the previous cage.

**Fix:** The function now groups by `Cage_ID` before creating lag/rolling features. Lag values are computed per cage only.

### 2. Multi-cage data passed to single-series models
**Problem:** `evaluate_models()`, `automatic_forecast()`, and `manual_forecast()` accepted multi-cage dataframes directly. SARIMA expects a single time series, and XGBoost recursive forecasting expects a single history, so passing multiple cages produced invalid forecasts.

**Fix:** Added `aggregate_all_cages()` helper that sums count/floor variables (eggs, hens, feed, mortality), averages ratios (temperature, humidity, flock age, protein, lay rate), flags heat stress if any cage experienced it, and derives daily mortality from the aggregated hen count. The three public functions now call `aggregate_all_cages()` before training.

### 3. Recursive XGBoost history validation
**Problem:** `recursive_xgb_forecast()` and `recursive_xgb_forecast_ensemble()` did not verify that the history represented a single cage/series.

**Fix:** Added `_validate_single_cage_history()` guard that raises `ValueError` if the history dataframe contains more than one unique `Cage_ID`.

### 4. Breed filter case sensitivity
**Problem:** `load_dataset_from_db()` required an exact-case breed match.

**Fix:** Breed matching is now case-insensitive, and the available-breeds error message is sorted.

### 5. Model recommendation edge case
**Problem:** `recommend_model()` compared MAPE values with `np.isclose()`, which would crash if both MAPEs were `None` (e.g., all actual egg counts were zero).

**Fix:** Added explicit handling for `None` MAPE values; falls back to MAE when both are unavailable.

## New Function

### `aggregate_all_cages(df: pd.DataFrame) -> pd.DataFrame`
Converts a multi-cage daily dataframe into a single farm-level series.

Aggregation rules:
- **Sum:** `Total_Eggs`, `Live_Hens`, `Total_Feed_Consumed_kg`, `Monthly_Mortality`
- **Mean:** `Flock_Age_Weeks`, `Temperature_C`, `Humidity_Percent`, `Crude_Protein_Percent`, `Lay_Rate_Percent`
- **Max:** `Heat_Stress` (flagged if any cage had heat stress)
- **Mode:** `Breed`
- `Cage_ID` is set to `"ALL"`
- `Daily_Mortality` is recomputed from aggregated `Live_Hens`

If the input already has one or zero cages, it returns a sorted copy unchanged.

## Validation

Two synthetic test scenarios were run successfully:

1. **Multi-cage data (2 cages, 100 days each):**
   - `evaluate_models()` returns metrics and holdout predictions.
   - `automatic_forecast()` returns a 7-day forecast.
   - Confirmed `build_xgb_training_frame()` produces cage-pure lag features.

2. **Single-cage data (1 cage, 100 days):**
   - `fit_xgb_model()` trains successfully.
   - `evaluate_models()` and `automatic_forecast()` complete.

## Related Files
- `forecast-api/api.py` — FastAPI endpoints that call `ForecastingV5.py`.
- `forecast-api/forecast_cli.py` — CLI entry point used by PHP; already filters/aggregates before calling the module, so it remains compatible.

## Laravel Integration

### Files Added/Modified
- `forecast-api/forecast_runner.py` — JSON-only bridge executed by Laravel.
- `app/Http/Controllers/ForecastController.php` — now calls Python instead of computing forecasts.
- `config/services.php` — added `forecast.python_binary` config.
- `.env.example` — added `FORECAST_PYTHON_BINARY=python`.

### `forecast_runner.py`
Accepts CLI arguments, loads data via `ForecastingV5.load_dataset_from_db()`, filters by cage/breed, and calls either `automatic_forecast()` or `manual_forecast()`. On failure it prints a JSON error object and exits with code 1. On success it prints the result JSON and exits with code 0.

Arguments:
- `--mode {auto,manual}`
- `--cage CAGE_CODE` or `ALL`
- `--breed BREED` or `ALL`
- `--horizon N`
- For manual mode: `--manual-breed`, `--live-hens`, `--flock-age-weeks`, `--temperature-c`, `--humidity-percent`, `--crude-protein-percent`, `--total-feed-consumed-kg`, `--monthly-mortality`, `--heat-stress`

### `ForecastController`
- `index()` and `generate()` methods preserved.
- `generateForecast()` no longer calculates values; it executes `forecast_runner.py` via Symfony Process.
- Database credentials are passed to the Python process through environment variables.
- Returned predictions are stored in the `forecasts` table with `cage_id`, `breed`, `forecast_date`, `target_date`, and `predicted_egg_count`.
- Errors from the Python process are caught and flashed back to the user.

### Configuration
Set the Python interpreter in `.env`:
```env
FORECAST_PYTHON_BINARY=python
```

### Forecast Metrics Display
After clicking **Generate Forecast**, the page now displays:
- The **recommended model** (SARIMA or XGBoost)
- **MAE**, **RMSE**, and **MAPE** for both SARIMA and XGBoost

These metrics are flashed to the session from `ForecastController::generate()` and rendered in `resources/views/forecast.blade.php`.

### Required Python Dependencies
Install from `forecast-api/requirements.txt`:
```bash
cd forecast-api
pip install -r requirements.txt
```

## Troubleshooting

### `No module named 'sqlalchemy'`
The Laravel process is launching a Python interpreter that does not have the forecast dependencies installed.

The controller now auto-detects a project virtual environment at:
- `forecast-api/.venv/Scripts/python.exe` (Windows)
- `forecast-api/.venv/bin/python` (Linux/macOS)

If a venv exists, it is used automatically. If you prefer to use a specific interpreter, set the full path in `.env`:
```env
FORECAST_PYTHON_BINARY=C:\Users\donor\OneDrive\Desktop\Layrate_Forecasting\forecast-api\.venv\Scripts\python.exe
```

### `OSError: [WinError 10106] The requested service provider could not be loaded or initialized`
This happens when the Python subprocess cannot initialize Winsock because Windows system environment variables (especially `SYSTEMROOT`, `SYSTEMDRIVE`, `WINDIR`, `PATH`) are missing.

The controller now explicitly passes these variables to the Python process, so this error should be resolved.

If you still see it, ensure your web server / PHP process has access to:
```
SYSTEMROOT=C:\Windows
SYSTEMDRIVE=C:
WINDIR=C:\Windows
PATH=...
```

### `Dataset must have at least 90 records`
`ForecastingV5.py` requires at least 90 historical records per forecast scope. If your database is freshly seeded, it may only have ~14 days of data.

Run the provided seeder to backfill 90 days:
```bash
cd forecast-api
.venv\Scripts\python seed_90_day_dataset.py
```

This inserts synthetic production logs, environmental logs, and feed consumption logs for all cages while preserving existing recent data.

## Note
The actual filename is `ForecastingV5.py` (capital `F` and `V`).
