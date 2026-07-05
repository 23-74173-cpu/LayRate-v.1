from datetime import date
import logging
from typing import Any

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.metrics import mean_absolute_error, mean_squared_error
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder
from statsmodels.tsa.statespace.sarimax import SARIMAX
from xgboost import XGBRegressor


logger = logging.getLogger(__name__)

DATE_COLUMN = "Date"
TARGET_COLUMN = "Total_Eggs"
XGB_SEEDS = [42, 123, 999]
DEFAULT_SARIMA_ORDER = (1, 1, 1)
DEFAULT_SARIMA_SEASONAL_ORDER = (1, 1, 1, 7)

MIN_REQUIRED_RECORDS = 90

REQUIRED_COLUMNS = [
    "Date",
    "Cage_ID",
    "Breed",
    "Live_Hens",
    "Flock_Age_Weeks",
    "Temperature_C",
    "Humidity_Percent",
    "Crude_Protein_Percent",
    "Total_Feed_Consumed_kg",
    "Monthly_Mortality",
    "Total_Eggs",
    "Lay_Rate_Percent",
    "Heat_Stress",
]

XGB_EXOGENOUS_FEATURES = [
    "Breed",
    "Live_Hens",
    "Flock_Age_Weeks",
    "Temperature_C",
    "Humidity_Percent",
    "Crude_Protein_Percent",
    "Total_Feed_Consumed_kg",
    "Monthly_Mortality",
    "Heat_Stress",
]

XGB_LAGS = [1, 2, 3, 7, 14]
XGB_LAG_FEATURES = [f"lag_{lag}" for lag in XGB_LAGS]
XGB_ROLLING_FEATURES = ["rolling_mean_7", "rolling_mean_14"]
XGB_FEATURES = XGB_EXOGENOUS_FEATURES + XGB_LAG_FEATURES + XGB_ROLLING_FEATURES


def _to_native(obj: Any) -> Any:
    if isinstance(obj, (np.integer,)):
        return int(obj)
    if isinstance(obj, (np.floating,)):
        if np.isnan(obj):
            return None
        return float(obj)
    if isinstance(obj, np.ndarray):
        return obj.tolist()
    if isinstance(obj, pd.Timestamp):
        return obj.isoformat()
    if isinstance(obj, pd.DataFrame):
        return obj.to_dict(orient="records")
    if isinstance(obj, pd.Series):
        return obj.tolist()
    return obj


def _to_native_dict(data: dict) -> dict:
    return {k: _to_native(v) for k, v in data.items()}


def _df_to_records(df: pd.DataFrame) -> list:
    records = df.to_dict(orient="records")
    result = []
    for record in records:
        converted = {}
        for k, v in record.items():
            converted[k] = _to_native(v)
        result.append(converted)
    return result


def safe_mape(actual, predicted):
    actual = np.asarray(actual, dtype=float)
    predicted = np.asarray(predicted, dtype=float)
    non_zero_mask = actual != 0
    if not non_zero_mask.any():
        return None
    return float(
        np.mean(np.abs((actual[non_zero_mask] - predicted[non_zero_mask]) / actual[non_zero_mask]))
        * 100
    )


def summarize_metrics(actual, predicted):
    actual = np.asarray(actual, dtype=float)
    predicted = np.asarray(predicted, dtype=float)
    mae = float(mean_absolute_error(actual, predicted))
    rmse = float(np.sqrt(mean_squared_error(actual, predicted)))
    mape = safe_mape(actual, predicted)
    return mae, rmse, mape


def fixed_train_test_split(df):
    split_idx = int(len(df) * 0.8)
    if split_idx <= 0 or split_idx >= len(df):
        raise ValueError("Cannot create fixed 80/20 split from current dataset size.")
    train_df = df.iloc[:split_idx].copy().reset_index(drop=True)
    test_df = df.iloc[split_idx:].copy().reset_index(drop=True)
    return train_df, test_df


def fit_sarima_model(train_series):
    model = SARIMAX(
        train_series.astype(float),
        order=DEFAULT_SARIMA_ORDER,
        seasonal_order=DEFAULT_SARIMA_SEASONAL_ORDER,
        enforce_stationarity=False,
        enforce_invertibility=False,
    )
    return model.fit(disp=False)


def build_xgb_training_frame(df):
    frame = df[[DATE_COLUMN, TARGET_COLUMN] + XGB_EXOGENOUS_FEATURES].copy()
    frame[TARGET_COLUMN] = frame[TARGET_COLUMN].astype(float)
    for lag in XGB_LAGS:
        frame[f"lag_{lag}"] = frame[TARGET_COLUMN].shift(lag)
    frame["rolling_mean_7"] = frame[TARGET_COLUMN].shift(1).rolling(7).mean()
    frame["rolling_mean_14"] = frame[TARGET_COLUMN].shift(1).rolling(14).mean()
    return frame.dropna().reset_index(drop=True)


def fit_xgb_model(train_df, seed):
    train_features = build_xgb_training_frame(train_df)
    if train_features.empty:
        raise ValueError("Insufficient rows for XGBoost after lag/rolling feature creation.")
    categorical_features = ["Breed"]
    numeric_features = [
        "Live_Hens",
        "Flock_Age_Weeks",
        "Temperature_C",
        "Humidity_Percent",
        "Crude_Protein_Percent",
        "Total_Feed_Consumed_kg",
        "Monthly_Mortality",
        "Heat_Stress",
    ] + XGB_LAG_FEATURES + XGB_ROLLING_FEATURES
    preprocessor = ColumnTransformer(
        transformers=[
            ("breed_encoder", OneHotEncoder(handle_unknown="ignore", sparse_output=False), categorical_features),
            ("numeric", "passthrough", numeric_features),
        ]
    )
    model = XGBRegressor(
        n_estimators=500,
        learning_rate=0.05,
        max_depth=4,
        subsample=0.9,
        colsample_bytree=0.9,
        random_state=seed,
        objective="reg:squarederror",
    )
    pipeline = Pipeline([("preprocessor", preprocessor), ("model", model)])
    pipeline.fit(train_features[XGB_FEATURES], train_features[TARGET_COLUMN])
    return pipeline


def build_recursive_feature_row(history_values, source_row):
    if len(history_values) < max(XGB_LAGS):
        raise ValueError("Not enough history to create lagged features for recursive forecasting.")
    row = {
        "Breed": source_row["Breed"],
        "Live_Hens": float(source_row["Live_Hens"]),
        "Flock_Age_Weeks": float(source_row["Flock_Age_Weeks"]),
        "Temperature_C": float(source_row["Temperature_C"]),
        "Humidity_Percent": float(source_row["Humidity_Percent"]),
        "Crude_Protein_Percent": float(source_row["Crude_Protein_Percent"]),
        "Total_Feed_Consumed_kg": float(source_row["Total_Feed_Consumed_kg"]),
        "Monthly_Mortality": float(source_row["Monthly_Mortality"]),
        "Heat_Stress": float(source_row["Heat_Stress"]),
    }
    for lag in XGB_LAGS:
        row[f"lag_{lag}"] = float(history_values[-lag])
    row["rolling_mean_7"] = float(np.mean(history_values[-7:]))
    row["rolling_mean_14"] = float(np.mean(history_values[-14:]))
    return row


def recursive_xgb_forecast(model, history_df, forecast_df):
    history_values = history_df[TARGET_COLUMN].astype(float).tolist()
    predictions = []
    for _, source_row in forecast_df.iterrows():
        feature_row = build_recursive_feature_row(history_values, source_row)
        feature_input = pd.DataFrame([feature_row])
        pred = float(model.predict(feature_input)[0])
        predictions.append(pred)
        history_values.append(pred)
    return np.asarray(predictions, dtype=float)


def recursive_xgb_forecast_ensemble(models, history_df, forecast_df):
    history_bank = [history_df[TARGET_COLUMN].astype(float).tolist() for _ in models]
    ensemble_preds = []
    for _, source_row in forecast_df.iterrows():
        step_preds = []
        for model_idx, model in enumerate(models):
            feature_row = build_recursive_feature_row(history_bank[model_idx], source_row)
            feature_input = pd.DataFrame([feature_row])
            pred = float(model.predict(feature_input)[0])
            history_bank[model_idx].append(pred)
            step_preds.append(pred)
        ensemble_preds.append(float(np.mean(step_preds)))
    return np.asarray(ensemble_preds, dtype=float)


def recommend_model(sarima_metrics, xgb_avg_metrics):
    _, _, sarima_mape = sarima_metrics
    _, _, xgb_mape = xgb_avg_metrics
    sarima_mae = sarima_metrics[0]
    xgb_mae = xgb_avg_metrics[0]
    if np.isclose(sarima_mape, xgb_mape):
        return "SARIMA" if sarima_mae <= xgb_mae else "XGBoost"
    return "SARIMA" if sarima_mape < xgb_mape else "XGBoost"


def build_holdout_table(test_df, sarima_preds, xgb_preds):
    actual = test_df[TARGET_COLUMN].astype(float).to_numpy()
    actual_i = np.rint(actual).astype(int)
    sarima_i = np.rint(np.asarray(sarima_preds, dtype=float)).astype(int)
    xgb_i = np.rint(np.asarray(xgb_preds, dtype=float)).astype(int)
    sarima_err = np.abs(actual - np.asarray(sarima_preds, dtype=float))
    xgb_err = np.abs(actual - np.asarray(xgb_preds, dtype=float))
    return pd.DataFrame({
        "Date": test_df[DATE_COLUMN].values,
        "Actual_Total_Eggs": actual_i,
        "SARIMA_Prediction": sarima_i,
        "XGBoost_Prediction": xgb_i,
        "SARIMA_Absolute_Error": sarima_err.round(2),
        "XGBoost_Absolute_Error": xgb_err.round(2),
    })


def build_deployment_feature_frame(last_row, forecast_dates, temp, humidity, feed, mortality, heat):
    rows = []
    for step_idx in range(1, len(forecast_dates) + 1):
        row = {
            "Breed": last_row["Breed"],
            "Live_Hens": float(last_row["Live_Hens"]),
            "Flock_Age_Weeks": float(last_row["Flock_Age_Weeks"]) + (step_idx / 7.0),
            "Temperature_C": temp,
            "Humidity_Percent": humidity,
            "Crude_Protein_Percent": float(last_row["Crude_Protein_Percent"]),
            "Total_Feed_Consumed_kg": feed,
            "Monthly_Mortality": mortality,
            "Heat_Stress": heat,
        }
        rows.append(row)
    frame = pd.DataFrame(rows)
    frame[DATE_COLUMN] = forecast_dates
    return frame


def load_dataset(file_path: str, breed: str = "ALL"):
    raw_df = pd.read_excel(file_path)

    column_mapping = {
        "Cage_Code": "Cage_ID",
        "Hen_Count": "Live_Hens",
        "Egg_Count": "Total_Eggs",
        "Feed_Consumed_kg": "Total_Feed_Consumed_kg",
        "Mortality_Count": "Monthly_Mortality",
    }
    df = raw_df.rename(columns=column_mapping)

    df["Lay_Rate_Percent"] = (df["Total_Eggs"] / df["Live_Hens"].replace(0, np.nan)) * 100
    df["Heat_Stress"] = 0.0

    agg_rules = {
        "Cage_ID": "first",
        "Breed": "first",
        "Live_Hens": "sum",
        "Flock_Age_Weeks": "mean",
        "Temperature_C": "mean",
        "Humidity_Percent": "mean",
        "Crude_Protein_Percent": "mean",
        "Total_Feed_Consumed_kg": "sum",
        "Monthly_Mortality": "sum",
        "Total_Eggs": "sum",
        "Heat_Stress": "max",
    }
    df = df.groupby(DATE_COLUMN, as_index=False, sort=False).agg(agg_rules)
    df["Lay_Rate_Percent"] = (df["Total_Eggs"] / df["Live_Hens"].replace(0, np.nan)) * 100

    missing_columns = [col for col in REQUIRED_COLUMNS if col not in df.columns]
    if missing_columns:
        raise ValueError("Missing required columns: " + ", ".join(missing_columns))
    df[DATE_COLUMN] = pd.to_datetime(df[DATE_COLUMN], errors="coerce")
    invalid_dates = int(df[DATE_COLUMN].isna().sum())
    if invalid_dates > 0:
        logger.warning("Dropping %d row(s) with invalid dates.", invalid_dates)
        df = df.dropna(subset=[DATE_COLUMN]).copy()
    df = df.sort_values(DATE_COLUMN).reset_index(drop=True)
    if len(df) < MIN_REQUIRED_RECORDS:
        raise ValueError(
            f"Dataset must have at least {MIN_REQUIRED_RECORDS} records. Found {len(df)}."
        )
    if breed.upper() != "ALL":
        valid_breeds = df["Breed"].dropna().astype(str).unique().tolist()
        if breed not in valid_breeds:
            raise ValueError(
                f"Breed '{breed}' not found. Available breeds: {', '.join(valid_breeds)}"
            )
        df = df.loc[df["Breed"].astype(str) == breed].copy()
        df = df.reset_index(drop=True)
    return df


def evaluate_models(df):
    train_df, test_df = fixed_train_test_split(df)

    sarima_model = fit_sarima_model(train_df[TARGET_COLUMN].astype(float))
    sarima_test_preds = np.asarray(sarima_model.forecast(steps=len(test_df)), dtype=float)
    sarima_mae, sarima_rmse, sarima_mape = summarize_metrics(
        test_df[TARGET_COLUMN], sarima_test_preds
    )

    run_results_list = []
    xgb_run_models = []
    for run_idx, seed in enumerate(XGB_SEEDS, start=1):
        model = fit_xgb_model(train_df, seed=seed)
        preds = recursive_xgb_forecast(model, train_df, test_df)
        mae, rmse, mape = summarize_metrics(test_df[TARGET_COLUMN], preds)
        run_results_list.append({
            "run": run_idx,
            "seed": seed,
            "MAE": round(mae, 2),
            "RMSE": round(rmse, 2),
            "MAPE": round(mape, 2),
        })
        xgb_run_models.append({"model": model, "predictions": preds})

    xgb_avg_mae = float(np.mean([r["MAE"] for r in run_results_list]))
    xgb_avg_rmse = float(np.mean([r["RMSE"] for r in run_results_list]))
    xgb_avg_mape = float(np.mean([r["MAPE"] for r in run_results_list]))
    xgb_avg_preds = np.mean([r["predictions"] for r in xgb_run_models], axis=0)

    sarima_metrics = (sarima_mae, sarima_rmse, sarima_mape)
    xgb_avg_metrics = (xgb_avg_mae, xgb_avg_rmse, xgb_avg_mape)
    recommended = recommend_model(sarima_metrics, xgb_avg_metrics)

    comparison = [
        {"Model": "SARIMA", "MAE": round(sarima_mae, 2), "RMSE": round(sarima_rmse, 2), "MAPE": round(sarima_mape, 2)},
        {"Model": "XGBoost Avg", "MAE": round(xgb_avg_mae, 2), "RMSE": round(xgb_avg_rmse, 2), "MAPE": round(xgb_avg_mape, 2)},
    ]

    holdout_df = build_holdout_table(test_df, sarima_test_preds, xgb_avg_preds)
    holdout_predictions = _df_to_records(holdout_df)

    result = {
        "recommended_model": recommended,
        "sarima_metrics": {
            "MAE": round(sarima_mae, 2),
            "RMSE": round(sarima_rmse, 2),
            "MAPE": round(sarima_mape, 2),
        },
        "xgboost_metrics": {
            "MAE": round(xgb_avg_mae, 2),
            "RMSE": round(xgb_avg_rmse, 2),
            "MAPE": round(xgb_avg_mape, 2),
            "runs": run_results_list,
        },
        "comparison": comparison,
        "holdout_predictions": holdout_predictions,
    }

    return result


def automatic_forecast(df, forecast_days: int = 7):
    if df.empty or len(df) < 2:
        raise ValueError("Dataset is too small for forecasting.")

    train_df, test_df = fixed_train_test_split(df)

    sarima_eval = fit_sarima_model(train_df[TARGET_COLUMN].astype(float))
    sarima_test_preds = np.asarray(sarima_eval.forecast(steps=len(test_df)), dtype=float)
    sarima_mae, sarima_rmse, sarima_mape = summarize_metrics(test_df[TARGET_COLUMN], sarima_test_preds)

    xgb_run_preds = []
    for seed in XGB_SEEDS:
        model = fit_xgb_model(train_df, seed=seed)
        preds = recursive_xgb_forecast(model, train_df, test_df)
        mae, rmse, mape = summarize_metrics(test_df[TARGET_COLUMN], preds)
        xgb_run_preds.append(preds)

    xgb_avg_preds_eval = np.mean(xgb_run_preds, axis=0)
    xgb_mae, xgb_rmse, xgb_mape = summarize_metrics(test_df[TARGET_COLUMN], xgb_avg_preds_eval)

    recommended = recommend_model(
        (sarima_mae, sarima_rmse, sarima_mape),
        (xgb_mae, xgb_rmse, xgb_mape),
    )

    sarima_full = fit_sarima_model(df[TARGET_COLUMN].astype(float))
    xgb_full_models = [fit_xgb_model(df, seed) for seed in XGB_SEEDS]

    future_dates = pd.date_range(
        start=pd.Timestamp(date.today()) + pd.Timedelta(days=1),
        periods=forecast_days,
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
        forecast_values = sarima_full.forecast(steps=forecast_days)

    expected_eggs = np.rint(np.asarray(forecast_values, dtype=float)).astype(int)

    forecast_list = []
    for i in range(forecast_days):
        forecast_list.append({
            "date": _to_native(future_dates[i]),
            "expected_eggs": int(expected_eggs[i]),
        })

    return {
        "recommended_model": recommended,
        "metrics": {
            "sarima": {"MAE": round(sarima_mae, 2), "RMSE": round(sarima_rmse, 2), "MAPE": round(sarima_mape, 2)},
            "xgboost": {"MAE": round(xgb_mae, 2), "RMSE": round(xgb_rmse, 2), "MAPE": round(xgb_mape, 2)},
        },
        "forecast": forecast_list,
    }


def manual_forecast(
    df,
    forecast_days: int,
    breed: str,
    live_hens: int,
    flock_age_weeks: int,
    temperature_c: float,
    humidity_percent: float,
    crude_protein_percent: float,
    total_feed_consumed_kg: float,
    monthly_mortality: int,
    heat_stress: int,
):
    if df.empty or len(df) < max(XGB_LAGS):
        raise ValueError("Dataset is too small for manual XGBoost forecasting.")

    train_df, test_df = fixed_train_test_split(df)

    run_preds = []
    for seed in XGB_SEEDS:
        model = fit_xgb_model(train_df, seed=seed)
        preds = recursive_xgb_forecast(model, train_df, test_df)
        run_preds.append(preds)

    avg_preds = np.mean(run_preds, axis=0)
    eval_mae, eval_rmse, eval_mape = summarize_metrics(test_df[TARGET_COLUMN], avg_preds)

    xgb_models = [fit_xgb_model(df, seed) for seed in XGB_SEEDS]

    future_dates = pd.date_range(
        start=pd.Timestamp(date.today()) + pd.Timedelta(days=1),
        periods=forecast_days,
        freq="D",
    )

    rows = []
    for step_idx in range(1, forecast_days + 1):
        row = {
            "Breed": breed,
            "Live_Hens": float(live_hens),
            "Flock_Age_Weeks": float(flock_age_weeks) + (step_idx / 7.0),
            "Temperature_C": temperature_c,
            "Humidity_Percent": humidity_percent,
            "Crude_Protein_Percent": float(crude_protein_percent),
            "Total_Feed_Consumed_kg": total_feed_consumed_kg,
            "Monthly_Mortality": monthly_mortality,
            "Heat_Stress": heat_stress,
        }
        rows.append(row)

    feature_frame = pd.DataFrame(rows)
    feature_frame[DATE_COLUMN] = future_dates

    forecast_values = recursive_xgb_forecast_ensemble(xgb_models, df, feature_frame)
    expected_eggs = np.rint(np.asarray(forecast_values, dtype=float)).astype(int)

    forecast_list = []
    for i in range(forecast_days):
        forecast_list.append({
            "date": _to_native(future_dates[i]),
            "expected_eggs": int(expected_eggs[i]),
        })

    return {
        "recommended_model": "XGBoost",
        "metrics": {
            "xgboost": {"MAE": round(eval_mae, 2), "RMSE": round(eval_rmse, 2), "MAPE": round(eval_mape, 2)},
        },
        "forecast": forecast_list,
    }


if __name__ == "__main__":
    print("=" * 50)
    print("Egg Production Forecasting (V5)")
    print("=" * 50)

    valid_options = {1, 7, 14, 30}
    while True:
        try:
            user_input = input("Enter forecast days (1, 7, 14, 30): ").strip()
            forecast_days = int(user_input)
            if forecast_days not in valid_options:
                print("Invalid choice. Please choose one of: 1, 7, 14, 30")
                continue
            break
        except ValueError:
            print("Invalid input. Please enter a number.")

    dataset_path = "layrate_forecast_input_updated.xlsx"
    print(f"\nLoading dataset: {dataset_path}")
    df = load_dataset(dataset_path)

    start_date = (pd.Timestamp(date.today()) + pd.Timedelta(days=1)).strftime("%Y-%m-%d")
    print(f"Generating {forecast_days}-day forecast starting from {start_date}...")
    result = automatic_forecast(df, forecast_days=forecast_days)

    print("\n" + "=" * 50)
    print("RESULTS")
    print("=" * 50)
    print(f"Recommended Model: {result['recommended_model']}")
    print("\nMetrics:")
    print(
        f"  SARIMA  -> MAE: {result['metrics']['sarima']['MAE']}, "
        f"RMSE: {result['metrics']['sarima']['RMSE']}, "
        f"MAPE: {result['metrics']['sarima']['MAPE']}%"
    )
    print(
        f"  XGBoost -> MAE: {result['metrics']['xgboost']['MAE']}, "
        f"RMSE: {result['metrics']['xgboost']['RMSE']}, "
        f"MAPE: {result['metrics']['xgboost']['MAPE']}%"
    )
    print(f"\n{forecast_days}-Day Forecast:")
    for item in result["forecast"]:
        print(f"  {item['date'][:10]} -> Expected eggs: {item['expected_eggs']}")
