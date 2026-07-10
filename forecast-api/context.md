# Forecast API — Context & Latest Updates

**Last updated:** 2026-07-04

## Purpose

The `forecast-api/` directory contains the Python forecasting pipeline for the LayRate Laravel application. It is responsible for:

- Generating protected forecast input Excel sheets.
- Importing forecast input spreadsheets into the `forecast_input_records` table.
- Running SARIMA / XGBoost models to produce egg production forecasts.
- Returning forecast results to the Laravel backend as JSON.

## Data Source

The forecasting pipeline reads historical data from the **`forecast_input_records`** table.

This table is a flattened, import-friendly dataset that combines data otherwise stored in:

- `production_logs` (egg_count, hen_count)
- `environmental_logs` (temperature_c, humidity_pct)
- `feed_batches` + `feed_consumption_logs` (feed_batch_code, crude_protein, feed_consumed_kg)
- `mortality_logs` (mortality_count)
- `hens` / `cages` (breed, flock_age_weeks, cage_code)

## Key Files

| File | Role |
|------|------|
| `ForecastingV5.py` | Main forecasting model. Reads from `forecast_input_records`, trains SARIMA and XGBoost, returns metrics and predictions. |
| `forecast_runner.py` | CLI wrapper invoked by Laravel. Parses arguments, calls the model, outputs JSON. |
| `generate_forecast_sheet.py` | Creates a protected Excel template with locked columns for Date, Cage_Code, Breed, Flock_Age_Weeks, and Hen_Count. |
| `import_forecast_input.py` | Imports a filled Excel file into `forecast_input_records`. |
| `requirements.txt` | Python dependencies for the forecasting environment. |

## Latest Updates (2026-07-04)

### Forecasting Data Flow
- `ForecastingV5.py` now sources historical data from `forecast_input_records` instead of joining normalized tables.
- The `forecast_input_records` table is the single source of truth for the forecasting model.

### Excel Template & Import
- `generate_forecast_sheet.py` now produces protected sheets. Pre-filled columns are locked so operators cannot accidentally edit Date, Cage_Code, Breed, Flock_Age_Weeks, or Hen_Count.
- `import_forecast_input.py` bulk-imports valid Excel rows into `forecast_input_records`.

### Laravel Integration
- `app/Http/Controllers/ForecastController.php` queries `forecast_input_records` for historical chart data and triggers the Python runner for new forecasts.
- Forecast generation streams JSON metrics (MAE, RMSE, MAPE) and predicted values back to the Laravel view.

### Forecast Page Behavior
- Historical egg count graph is always shown.
- Forecasted data and model metrics only appear after clicking **Generate Forecast**.
- Historical graph displays the last **14 days** of production records.
- Chart labels show month/day (e.g., `Jun 21`) to avoid duplicate weekday names.

### Chart Reliability Fixes
- Chart instance variable renamed to `window.forecastChartInstance` to avoid conflict with the canvas element ID.
- Forecast page script wrapped in an IIFE to prevent `const` redeclaration errors during Turbo navigation.
- Added explicit canvas dimensions and error handling for missing data or failed Chart.js loads.

### Automatic Data Continuity
- New Artisan command: `php artisan forecast:sync-input-records`
  - Aggregates records from `production_logs`, `environmental_logs`, `feed_consumption_logs`, and `mortality_logs`.
  - Upserts rows into `forecast_input_records` using the unique key `(date, cage_code)`.
  - Allows the forecast history to grow continuously beyond the 90-day minimum.
- Scheduler added in `routes/console.php` to run the sync command daily at 02:00.

## Common Commands

```bash
# Generate a forecast input sheet
python forecast-api/generate_forecast_sheet.py

# Import a filled forecast sheet
python forecast-api/import_forecast_input.py path/to/file.xlsx

# Run the forecast runner manually
python forecast-api/forecast_runner.py --mode auto --cage CAGE-A --horizon 7

# Sync app tables into forecast_input_records
php artisan forecast:sync-input-records

# Preview what would be synced
php artisan forecast:sync-input-records --dry-run

# List scheduled tasks
php artisan schedule:list
```

## Environment

- Python binary is resolved from `FORECAST_PYTHON_BINARY` env variable, or falls back to project virtual environments (`forecast-api/.venv`, `.venv`), then system `python`.
- Database credentials are passed to Python scripts via environment variables (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
