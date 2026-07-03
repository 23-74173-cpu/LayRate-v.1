"""CLI entry point for per-cage forecasting called by PHP."""
import argparse
import json
import os
import sys
import warnings
from pathlib import Path

import numpy as np
import pandas as pd

warnings.filterwarnings("ignore")

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from ForecastingV5 import (
    TARGET_COLUMN,
    XGB_SEEDS,
    _to_native,
    build_deployment_feature_frame,
    fit_sarima_model,
    fit_xgb_model,
    fixed_train_test_split,
    load_dataset_from_db,
    recursive_xgb_forecast,
    recursive_xgb_forecast_ensemble,
    recommend_model,
    summarize_metrics,
)


def _connection_string() -> str:
    """Build a SQLAlchemy MySQL connection string from environment variables."""
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    database = os.getenv("DB_DATABASE", "layrate")
    username = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    return f"mysql+pymysql://{username}:{password}@{host}:{port}/{database}"


def run(cage_code: str, horizon: int) -> dict:
    connection_string = _connection_string()

    df = load_dataset_from_db(connection_string=connection_string, breed="ALL")
    df = df.sort_values("Date").reset_index(drop=True)

    if cage_code.upper() == "ALL":
        cage_label = "ALL"
        df = df.groupby("Date", as_index=False).agg({
            "Total_Eggs": "sum",
            "Live_Hens": "sum",
            "Flock_Age_Weeks": "mean",
            "Temperature_C": "mean",
            "Humidity_Percent": "mean",
            "Crude_Protein_Percent": "mean",
            "Total_Feed_Consumed_kg": "sum",
            "Monthly_Mortality": "sum",
            "Lay_Rate_Percent": "mean",
            "Heat_Stress": "max",
            "Breed": lambda x: x.mode().iloc[0] if not x.mode().empty else x.iloc[0],
        })
        df = df.sort_values("Date").reset_index(drop=True)
    else:
        cage_label = cage_code
        df = df.loc[df["Cage_ID"].astype(str) == cage_code].copy()
        df = df.reset_index(drop=True)

    if df.empty:
        return {"error": f"No data found for cage '{cage_code}'"}
    if len(df) < 14:
        return {"error": f"Only {len(df)} records for {cage_code}; need at least 14"}

    train_df, test_df = fixed_train_test_split(df)

    sarima_model = fit_sarima_model(train_df[TARGET_COLUMN].astype(float))
    sarima_test_preds = sarima_model.forecast(steps=len(test_df))
    sarima_mae, sarima_rmse, sarima_mape = summarize_metrics(
        test_df[TARGET_COLUMN], sarima_test_preds
    )

    xgb_run_preds = []
    for seed in XGB_SEEDS:
        model = fit_xgb_model(train_df, seed=seed)
        preds = recursive_xgb_forecast(model, train_df, test_df)
        xgb_run_preds.append(preds)

    xgb_avg_preds = np.mean(xgb_run_preds, axis=0)
    xgb_mae, xgb_rmse, xgb_mape = summarize_metrics(test_df[TARGET_COLUMN], xgb_avg_preds)

    recommended = recommend_model(
        (sarima_mae, sarima_rmse, sarima_mape),
        (xgb_mae, xgb_rmse, xgb_mape),
    )

    # refit on full data for deployment forecast
    sarima_full = fit_sarima_model(df[TARGET_COLUMN].astype(float))
    xgb_full_models = [fit_xgb_model(df, seed) for seed in XGB_SEEDS]

    future_dates = pd.date_range(
        start=pd.Timestamp.now().normalize() + pd.Timedelta(days=1),
        periods=horizon,
        freq="D",
    )

    if recommended == "XGBoost":
        last_row = df.iloc[-1]
        feature_frame = build_deployment_feature_frame(
            last_row, future_dates,
            temp=float(last_row["Temperature_C"]),
            humidity=float(last_row["Humidity_Percent"]),
            feed=float(last_row["Total_Feed_Consumed_kg"]),
            mortality=float(last_row["Monthly_Mortality"]),
            heat=float(last_row["Heat_Stress"]),
        )
        forecast_values = recursive_xgb_forecast_ensemble(xgb_full_models, df, feature_frame)
    else:
        forecast_values = sarima_full.forecast(steps=horizon)

    expected_eggs = np.clip(np.rint(np.asarray(forecast_values, dtype=float)), 0, None).astype(int)

    historical = df.tail(14).copy()
    historical["Date"] = pd.to_datetime(historical["Date"])

    forecast_list = []
    for i in range(horizon):
        forecast_list.append({
            "date": _to_native(future_dates[i]),
            "predicted_egg_count": int(expected_eggs[i]),
        })

    metrics = {
        "recommended_model": recommended,
        "sarima": {
            "MAE": round(sarima_mae, 2),
            "RMSE": round(sarima_rmse, 2),
            "MAPE": round(sarima_mape, 2) if sarima_mape is not None else None,
        },
        "xgboost": {
            "MAE": round(xgb_mae, 2),
            "RMSE": round(xgb_rmse, 2),
            "MAPE": round(xgb_mape, 2) if xgb_mape is not None else None,
        },
    }

    return {
        "cage_code": cage_code,
        "horizon": horizon,
        "metrics": metrics,
        "forecast": forecast_list,
        "historical": [
            {
                "date": str(r["Date"].date()) if hasattr(r["Date"], "date") else str(r["Date"]),
                "egg_count": int(r[TARGET_COLUMN]),
            }
            for _, r in historical.iterrows()
        ],
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--cage", default="CAGE-A")
    parser.add_argument("--horizon", type=int, default=7)
    args = parser.parse_args()

    result = run(args.cage, args.horizon)
    print(json.dumps(result))


if __name__ == "__main__":
    main()
