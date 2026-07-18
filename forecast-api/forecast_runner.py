"""JSON-only bridge between Laravel and ForecastingV5.py.

Reads data from the LayRate MySQL database, optionally filters by cage or
breed, runs the approved SARIMA/XGBoost forecasting pipeline, and prints a
single JSON object to stdout. No logging, warnings, or debug output is emitted.
"""
import argparse
import json
import os
import sys
import warnings
from pathlib import Path

warnings.filterwarnings("ignore")

# Ensure ForecastingV5.py is importable regardless of cwd
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from ForecastingV5 import (
    CAGE_COLUMN,
    automatic_forecast,
    load_dataset_from_db,
    manual_forecast,
)


def fail(message: str, code: int = 1):
    print(json.dumps({"success": False, "error": message}), file=sys.stdout)
    sys.exit(code)


def _connection_string() -> str:
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    database = os.getenv("DB_DATABASE", "layrate")
    username = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    return f"mysql+pymysql://{username}:{password}@{host}:{port}/{database}"


def load_and_filter(breed: str, cage_code: str):
    """Load dataset and optionally filter by breed and/or cage."""
    connection_string = _connection_string()
    df = load_dataset_from_db(connection_string=connection_string, breed=breed)

    if cage_code and cage_code.upper() != "ALL" and CAGE_COLUMN in df.columns:
        df = df.loc[df[CAGE_COLUMN].astype(str) == cage_code].copy()
        df = df.reset_index(drop=True)

    return df


def run_automatic(args) -> dict:
    df = load_and_filter(breed=args.breed, cage_code=args.cage)
    result = automatic_forecast(df, forecast_days=args.horizon, start_date=args.start_date)
    return {"success": True, **result}


def run_manual(args) -> dict:
    df = load_and_filter(breed=args.breed, cage_code=args.cage)
    result = manual_forecast(
        df,
        forecast_days=args.horizon,
        breed=args.manual_breed,
        live_hens=args.live_hens,
        flock_age_weeks=args.flock_age_weeks,
        temperature_c=args.temperature_c,
        humidity_percent=args.humidity_percent,
        crude_protein_percent=args.crude_protein_percent,
        total_feed_consumed_kg=args.total_feed_consumed_kg,
        monthly_mortality=args.monthly_mortality,
        heat_stress=args.heat_stress,
    )
    return {"success": True, **result}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--mode", choices=["auto", "manual"], default="auto")
    parser.add_argument("--cage", default="ALL")
    parser.add_argument("--breed", default="ALL")
    parser.add_argument("--horizon", type=int, default=7)
    parser.add_argument("--start-date", default=None,
                        help="Forecast start date (YYYY-MM-DD). Defaults to tomorrow.")

    # Manual-only parameters
    parser.add_argument("--manual-breed", default=None)
    parser.add_argument("--live-hens", type=int, default=None)
    parser.add_argument("--flock-age-weeks", type=int, default=None)
    parser.add_argument("--temperature-c", type=float, default=None)
    parser.add_argument("--humidity-percent", type=float, default=None)
    parser.add_argument("--crude-protein-percent", type=float, default=None)
    parser.add_argument("--total-feed-consumed-kg", type=float, default=None)
    parser.add_argument("--monthly-mortality", type=int, default=None)
    parser.add_argument("--heat-stress", type=int, default=None)

    args = parser.parse_args()

    try:
        if args.mode == "manual":
            missing = [
                name
                for name, value in {
                    "manual-breed": args.manual_breed,
                    "live-hens": args.live_hens,
                    "flock-age-weeks": args.flock_age_weeks,
                    "temperature-c": args.temperature_c,
                    "humidity-percent": args.humidity_percent,
                    "crude-protein-percent": args.crude_protein_percent,
                    "total-feed-consumed-kg": args.total_feed_consumed_kg,
                    "monthly-mortality": args.monthly_mortality,
                    "heat-stress": args.heat_stress,
                }.items()
                if value is None
            ]
            if missing:
                fail(f"Manual forecast missing parameters: {', '.join(missing)}")
            result = run_manual(args)
        else:
            result = run_automatic(args)
    except Exception as exc:
        fail(str(exc))

    print(json.dumps(result, separators=(",", ":")))


if __name__ == "__main__":
    main()
