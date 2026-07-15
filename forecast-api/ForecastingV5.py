from datetime import date
import logging
import os
from typing import Any, Optional

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
CAGE_COLUMN = "Cage_ID"
TARGET_COLUMN = "Total_Eggs"

XGB_SEEDS = [42, 123, 999]
DEFAULT_SARIMA_ORDER = (1, 1, 1)
DEFAULT_SARIMA_SEASONAL_ORDER = (1, 1, 1, 7)

MIN_REQUIRED_RECORDS = 90
MORTALITY_ROLLING_WINDOW = 30
HEAT_STRESS_TEMP_THRESHOLD = 30.0
HEAT_STRESS_HUMIDITY_THRESHOLD = 80.0
MAX_FORECAST_DAYS_FROM_TODAY = 30

REQUIRED_COLUMNS = [
    DATE_COLUMN,
    CAGE_COLUMN,
    "Breed",
    "Live_Hens",
    "Flock_Age_Weeks",
    "Temperature_C",
    "Humidity_Percent",
    "Crude_Protein_Percent",
    "Total_Feed_Consumed_kg",
    "Monthly_Mortality",
    TARGET_COLUMN,
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

FORECAST_DATASET_QUERY = """
SELECT
    date AS `Date`,
    cage_code AS `Cage_ID`,
    breed AS `Breed`,
    flock_age_weeks AS `Flock_Age_Weeks`,
    COALESCE(hen_count, 0) AS `Live_Hens`,
    COALESCE(egg_count, 0) AS `Total_Eggs`,
    temperature_c AS `Temperature_C`,
    humidity_percent AS `Humidity_Percent`,
    COALESCE(crude_protein_percent, 0) AS `Crude_Protein_Percent`,
    COALESCE(feed_consumed_kg, 0) AS `Total_Feed_Consumed_kg`,
    COALESCE(mortality_count, 0) AS `Daily_Mortality`
FROM forecast_input_records
WHERE date IS NOT NULL
  AND cage_code IS NOT NULL
  AND TRIM(cage_code) != ''
ORDER BY `Date`, `Cage_ID`
"""


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
    df = df.sort_values(DATE_COLUMN).reset_index(drop=True)
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


def compute_daily_mortality(df: pd.DataFrame) -> pd.DataFrame:
    """Compute Daily_Mortality from explicit mortality_count if present,
    otherwise derive from changes in Live_Hens per cage."""
    df = df.sort_values([CAGE_COLUMN, DATE_COLUMN]).copy()
    if "Daily_Mortality" in df.columns:
        df["Daily_Mortality"] = df["Daily_Mortality"].fillna(0).clip(lower=0)
    else:
        df["Daily_Mortality"] = df.groupby(CAGE_COLUMN)["Live_Hens"].diff(-1) * -1
        df["Daily_Mortality"] = df["Daily_Mortality"].clip(lower=0).fillna(0)
    return df


def compute_monthly_mortality(df: pd.DataFrame) -> pd.DataFrame:
    """Compute Monthly_Mortality as a 30-day rolling sum of Daily_Mortality per cage."""
    df = df.sort_values([CAGE_COLUMN, DATE_COLUMN]).copy()
    df["Monthly_Mortality"] = (
        df.groupby(CAGE_COLUMN)["Daily_Mortality"]
        .rolling(window=MORTALITY_ROLLING_WINDOW, min_periods=1)
        .sum()
        .reset_index(level=0, drop=True)
        .astype(float)
    )
    return df


def compute_heat_stress(df: pd.DataFrame) -> pd.DataFrame:
    """Compute Heat_Stress as 1 when Temperature > 30 or Humidity > 80, otherwise 0."""
    df = df.copy()
    df["Heat_Stress"] = (
        (df["Temperature_C"] > HEAT_STRESS_TEMP_THRESHOLD)
        | (df["Humidity_Percent"] > HEAT_STRESS_HUMIDITY_THRESHOLD)
    ).astype(int)
    return df


def build_modeling_frame(df: pd.DataFrame) -> pd.DataFrame:
    """Build the final feature frame from the raw database query result."""
    df = df.copy()
    df[DATE_COLUMN] = pd.to_datetime(df[DATE_COLUMN], errors="coerce")
    df = df.dropna(subset=[DATE_COLUMN])

    df = compute_daily_mortality(df)
    df = compute_monthly_mortality(df)
    df = compute_heat_stress(df)

    if "Lay_Rate_Percent" not in df.columns:
        df["Lay_Rate_Percent"] = 0.0
        mask = df["Live_Hens"] > 0
        df.loc[mask, "Lay_Rate_Percent"] = (
            df.loc[mask, "Total_Eggs"] / df.loc[mask, "Live_Hens"] * 100
        ).round(2)

    df = df.sort_values([CAGE_COLUMN, DATE_COLUMN]).reset_index(drop=True)
    return df


def build_xgb_training_frame(df):
    """Build XGB training frame with lag/rolling features computed per cage.

    The input may contain multiple cages; lag features are never allowed to
    cross cage boundaries. Cage_ID is retained so the model can disambiguate
    series if desired, but it is not used as a model feature by default.
    """
    required_cols = set([DATE_COLUMN, TARGET_COLUMN] + XGB_EXOGENOUS_FEATURES)
    missing = required_cols - set(df.columns)
    if missing:
        raise ValueError(f"build_xgb_training_frame missing columns: {sorted(missing)}")

    frames = []
    has_cage_col = CAGE_COLUMN in df.columns
    groups = [df] if not has_cage_col else [g for _, g in df.groupby(CAGE_COLUMN, sort=False)]
    for group in groups:
        group = group.sort_values(DATE_COLUMN).copy()
        base_cols = [DATE_COLUMN, TARGET_COLUMN] + XGB_EXOGENOUS_FEATURES
        if has_cage_col:
            base_cols = [DATE_COLUMN, CAGE_COLUMN, TARGET_COLUMN] + XGB_EXOGENOUS_FEATURES
        frame = group[base_cols].copy()
        frame[TARGET_COLUMN] = frame[TARGET_COLUMN].astype(float)
        for lag in XGB_LAGS:
            frame[f"lag_{lag}"] = frame[TARGET_COLUMN].shift(lag)
        frame["rolling_mean_7"] = frame[TARGET_COLUMN].shift(1).rolling(7).mean()
        frame["rolling_mean_14"] = frame[TARGET_COLUMN].shift(1).rolling(14).mean()
        frames.append(frame)
    return pd.concat(frames, ignore_index=True).dropna().reset_index(drop=True)


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


def _validate_single_cage_history(history_df):
    if CAGE_COLUMN in history_df.columns and history_df[CAGE_COLUMN].nunique() > 1:
        raise ValueError(
            "Recursive XGBoost forecast expects a single-cage/aggregated history. "
            f"Received {history_df[CAGE_COLUMN].nunique()} cages."
        )


def recursive_xgb_forecast(model, history_df, forecast_df):
    _validate_single_cage_history(history_df)
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
    _validate_single_cage_history(history_df)
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
    sarima_mae, _, sarima_mape = sarima_metrics
    xgb_mae, _, xgb_mape = xgb_avg_metrics

    # If MAPE is unavailable for both models (e.g. all actuals are zero), fall
    # back to MAE.
    if sarima_mape is None and xgb_mape is None:
        return "SARIMA" if sarima_mae <= xgb_mae else "XGBoost"
    if sarima_mape is None:
        return "XGBoost"
    if xgb_mape is None:
        return "SARIMA"

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


def aggregate_all_cages(df: pd.DataFrame) -> pd.DataFrame:
    """Aggregate a multi-cage daily frame into a single farm-level series.

    Sums count/floor variables (hens, eggs, feed, mortality) and averages
    percentages/ratios (age, temperature, humidity, protein, lay rate).
    Heat stress is flagged if any cage experienced it that day. Breed is taken
    as the most common value.
    """
    if CAGE_COLUMN not in df.columns or df[CAGE_COLUMN].nunique() <= 1:
        return df.copy().sort_values(DATE_COLUMN).reset_index(drop=True)

    agg_spec = {
        TARGET_COLUMN: "sum",
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
    }
    agg_spec = {k: v for k, v in agg_spec.items() if k in df.columns}

    agg = df.groupby(DATE_COLUMN, as_index=False).agg(agg_spec)
    agg[CAGE_COLUMN] = "ALL"
    if "Live_Hens" in agg.columns:
        agg["Daily_Mortality"] = agg["Live_Hens"].diff(-1) * -1
        agg["Daily_Mortality"] = agg["Daily_Mortality"].clip(lower=0).fillna(0)
    return agg.sort_values(DATE_COLUMN).reset_index(drop=True)


def _build_mysql_connection_string() -> Optional[str]:
    """Build a SQLAlchemy MySQL connection string from environment variables."""
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    database = os.getenv("DB_DATABASE", "layrate")
    username = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    if not database or not username:
        return None
    return f"mysql+pymysql://{username}:{password}@{host}:{port}/{database}"


def _execute_query(connection_string: Optional[str], db_path: Optional[str], sql: str) -> pd.DataFrame:
    """Execute the forecasting query against a SQLAlchemy or SQLite connection."""
    if connection_string:
        from sqlalchemy import create_engine
        engine = create_engine(connection_string)
        try:
            df = pd.read_sql_query(sql, engine)
        finally:
            engine.dispose()
        return df

    import sqlite3
    resolved_path = db_path or "../database/database.sqlite"
    conn = sqlite3.connect(resolved_path)
    try:
        df = pd.read_sql_query(sql, conn)
    finally:
        conn.close()
    return df


def load_dataset_from_db(
    db_path: Optional[str] = None,
    connection_string: Optional[str] = None,
    breed: str = "ALL",
) -> pd.DataFrame:
    """Load the forecast dataset directly from the forecast_input_records table.

    The dataset is read from the denormalized forecast_input_records table,
    which is populated from imported forecast input sheets. Mortality is taken
    from the explicit mortality_count column when available; otherwise it is
    derived from changes in Live_Hens.

    Parameters
    ----------
    db_path : str, optional
        Path to SQLite database file. Ignored if connection_string is provided.
    connection_string : str, optional
        SQLAlchemy connection string (e.g.
        "mysql+pymysql://user:pass@host:3306/dbname").
        Takes precedence over db_path. When omitted, environment variables
        DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD are used
        to build a MySQL connection string.
    breed : str, default "ALL"
        Filter by breed name, or "ALL" for no filter.

    Returns
    -------
    pd.DataFrame with the required modeling columns.
    """
    if not connection_string:
        connection_string = _build_mysql_connection_string()

    df = _execute_query(connection_string, db_path, FORECAST_DATASET_QUERY)
    df = build_modeling_frame(df)

    missing_columns = [col for col in REQUIRED_COLUMNS if col not in df.columns]
    if missing_columns:
        raise ValueError(f"Query result missing required columns: {', '.join(missing_columns)}")

    invalid_dates = int(df[DATE_COLUMN].isna().sum())
    if invalid_dates > 0:
        logger.warning("Dropping %d row(s) with invalid dates.", invalid_dates)
        df = df.dropna(subset=[DATE_COLUMN]).copy()
    df = df.sort_values([CAGE_COLUMN, DATE_COLUMN]).reset_index(drop=True)

    if len(df) < MIN_REQUIRED_RECORDS:
        raise ValueError(
            f"Dataset must have at least {MIN_REQUIRED_RECORDS} records. Found {len(df)}."
        )

    if breed.upper() != "ALL":
        valid_breeds = df["Breed"].dropna().astype(str).unique().tolist()
        breed_match = next((b for b in valid_breeds if b.lower() == breed.lower()), None)
        if breed_match is None:
            raise ValueError(
                f"Breed '{breed}' not found. Available breeds: {', '.join(sorted(valid_breeds))}"
            )
        df = df.loc[df["Breed"].astype(str).str.lower() == breed.lower()].copy()
        df = df.reset_index(drop=True)

    return df


def evaluate_models(df):
    df = aggregate_all_cages(df)
    if len(df) < MIN_REQUIRED_RECORDS:
        raise ValueError(
            f"Aggregated dataset must have at least {MIN_REQUIRED_RECORDS} records. Found {len(df)}."
        )

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


def _resolve_future_dates(forecast_days: int, start_date: Optional[str] = None) -> pd.DatetimeIndex:
    """Build future forecast dates, enforcing the max 30-day-ahead limit."""
    today = pd.Timestamp(date.today())
    max_allowed_date = today + pd.Timedelta(days=MAX_FORECAST_DAYS_FROM_TODAY)

    if start_date:
        start = pd.to_datetime(start_date, errors="coerce")
        if pd.isna(start):
            raise ValueError(f"Invalid start_date: {start_date!r}. Use YYYY-MM-DD format.")
    else:
        start = today + pd.Timedelta(days=1)

    if start < today + pd.Timedelta(days=1):
        raise ValueError(
            f"Forecast start date must be at least tomorrow ({(today + pd.Timedelta(days=1)).date()}). "
            f"Received: {start.date()}."
        )

    end = start + pd.Timedelta(days=forecast_days - 1)
    if end > max_allowed_date:
        raise ValueError(
            f"Forecast end date ({end.date()}) exceeds the maximum allowed date "
            f"({max_allowed_date.date()} = today + {MAX_FORECAST_DAYS_FROM_TODAY} days). "
            f"Reduce the horizon or choose an earlier start date."
        )

    return pd.date_range(start=start, periods=forecast_days, freq="D")


def automatic_forecast(df, forecast_days: int = 7, start_date: Optional[str] = None):
    if df.empty or len(df) < 2:
        raise ValueError("Dataset is too small for forecasting.")

    df = aggregate_all_cages(df)
    if len(df) < MIN_REQUIRED_RECORDS:
        raise ValueError(
            f"Aggregated dataset must have at least {MIN_REQUIRED_RECORDS} records. Found {len(df)}."
        )

    train_df, test_df = fixed_train_test_split(df)

    sarima_eval = fit_sarima_model(train_df[TARGET_COLUMN].astype(float))
    sarima_test_preds = np.asarray(sarima_eval.forecast(steps=len(test_df)), dtype=float)
    sarima_mae, sarima_rmse, sarima_mape = summarize_metrics(test_df[TARGET_COLUMN], sarima_test_preds)

    xgb_run_preds = []
    for seed in XGB_SEEDS:
        model = fit_xgb_model(train_df, seed=seed)
        preds = recursive_xgb_forecast(model, train_df, test_df)
        xgb_run_preds.append(preds)

    xgb_avg_preds_eval = np.mean(xgb_run_preds, axis=0)
    xgb_mae, xgb_rmse, xgb_mape = summarize_metrics(test_df[TARGET_COLUMN], xgb_avg_preds_eval)

    recommended = recommend_model(
        (sarima_mae, sarima_rmse, sarima_mape),
        (xgb_mae, xgb_rmse, xgb_mape),
    )

    sarima_full = fit_sarima_model(df[TARGET_COLUMN].astype(float))
    xgb_full_models = [fit_xgb_model(df, seed) for seed in XGB_SEEDS]

    future_dates = _resolve_future_dates(forecast_days, start_date)

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

    expected_eggs = np.clip(np.rint(np.asarray(forecast_values, dtype=float)), 0, None).astype(int)

    forecast_list = []
    for i in range(forecast_days):
        forecast_list.append({
            "date": _to_native(future_dates[i]),
            "predicted_egg_count": int(expected_eggs[i]),
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

    df = aggregate_all_cages(df)
    if len(df) < MIN_REQUIRED_RECORDS:
        raise ValueError(
            f"Aggregated dataset must have at least {MIN_REQUIRED_RECORDS} records. Found {len(df)}."
        )

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
    expected_eggs = np.clip(np.rint(np.asarray(forecast_values, dtype=float)), 0, None).astype(int)

    forecast_list = []
    for i in range(forecast_days):
        forecast_list.append({
            "date": _to_native(future_dates[i]),
            "predicted_egg_count": int(expected_eggs[i]),
        })

    return {
        "recommended_model": "XGBoost",
        "metrics": {
            "xgboost": {"MAE": round(eval_mae, 2), "RMSE": round(eval_rmse, 2), "MAPE": round(eval_mape, 2)},
        },
        "forecast": forecast_list,
    }


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)

    df = load_dataset_from_db(breed="ALL")
    print(f"Loaded {len(df)} rows, columns: {list(df.columns)}")
    print(f"Date range: {df['Date'].min()} to {df['Date'].max()}")
    print(f"Cages: {df['Cage_ID'].unique()}")
    print(f"Breeds: {df['Breed'].unique()}")

    result = evaluate_models(df)
    print(f"\nRecommended model: {result['recommended_model']}")

    fc = automatic_forecast(df, forecast_days=7)
    print(f"\n7-day forecast ({fc['recommended_model']}):")
    for f in fc["forecast"]:
        print(f"  {f['date']}: {f['predicted_egg_count']} eggs")
