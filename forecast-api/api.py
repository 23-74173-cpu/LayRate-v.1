import os

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import Optional

from ForecastingV5 import (
    load_dataset_from_db,
    evaluate_models,
    automatic_forecast,
    manual_forecast,
)

app = FastAPI(
    title="LayRate Forecasting API",
    description="SARIMA and XGBoost ensemble forecasting for poultry egg production",
    version="2.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:8000",
        "http://127.0.0.1:8000",
        "http://localhost:8001",
        "http://127.0.0.1:8001",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def _connection_string() -> Optional[str]:
    """Build a SQLAlchemy MySQL connection string from environment variables."""
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    database = os.getenv("DB_DATABASE", "layrate")
    username = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    if not database or not username:
        return None
    return f"mysql+pymysql://{username}:{password}@{host}:{port}/{database}"


class EvaluationRequest(BaseModel):
    breed: str = Field(default="ALL", description="Breed name or 'ALL' for no filter")


class ForecastRequest(BaseModel):
    breed: str = Field(default="ALL", description="Breed name or 'ALL' for no filter")
    forecast_days: int = Field(default=7, ge=1, le=365, description="Number of days to forecast")


class ManualForecastRequest(BaseModel):
    breed: str = Field(description="Breed name to filter the dataset")
    forecast_days: int = Field(ge=1, le=365, description="Number of days to forecast")
    live_hens: int = Field(ge=0, description="Number of live hens")
    flock_age_weeks: int = Field(ge=0, description="Age of the flock in weeks")
    temperature_c: float = Field(description="Ambient temperature in Celsius")
    humidity_percent: float = Field(ge=0, le=100, description="Relative humidity percentage")
    crude_protein_percent: float = Field(ge=0, description="Crude protein percentage in feed")
    total_feed_consumed_kg: float = Field(ge=0, description="Total feed consumed in kilograms")
    monthly_mortality: int = Field(ge=0, description="Monthly mortality count")
    heat_stress: int = Field(ge=0, le=1, description="Heat stress indicator (0 or 1)")


def _load_and_filter(breed: str):
    """Load dataset directly from the normalized MySQL database and optionally filter by breed."""
    try:
        df = load_dataset_from_db(connection_string=_connection_string(), breed=breed)
    except ValueError as e:
        raise HTTPException(
            status_code=400,
            detail={"success": False, "error": str(e)},
        )
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail={"success": False, "error": f"Failed to load dataset: {str(e)}"},
        )
    return df


@app.get(
    "/",
    summary="Health check",
    response_description="API status message",
)
async def root():
    """
    Health check endpoint.

    Returns a simple status message confirming the API is running.

    **Response Example:**
    ```json
    {
        "message": "LayRate Forecasting API Running"
    }
    ```
    """
    return {"message": "LayRate Forecasting API Running"}


@app.post(
    "/evaluate",
    summary="Evaluate SARIMA vs XGBoost on holdout data",
    response_description="Model evaluation metrics and holdout predictions",
)
async def evaluate(request: EvaluationRequest):
    """
    Run a full model evaluation comparing SARIMA and XGBoost.

    - Performs an 80/20 temporal train-test split.
    - Fits SARIMA on the training set and forecasts the test period.
    - Fits XGBoost (3 independent runs with seeds 42, 123, 999) on the training set
      and generates recursive multi-step forecasts for the test period.
    - Computes MAE, RMSE, and MAPE for both models.
    - Selects the recommended model (lowest MAPE; tie-breaker: lowest MAE).
    - Returns the comparison table and per-row holdout predictions.

    **Request Body Example:**
    ```json
    {"breed": "ALL"}
    ```

    **Response Example:**
    ```json
    {
        "recommended_model": "XGBoost",
        "sarima_metrics": {"MAE": 0.74, "RMSE": 0.94, "MAPE": 8.56},
        "xgboost_metrics": {"MAE": 0.17, "RMSE": 0.32, "MAPE": 2.2, "runs": [...]},
        "comparison": [...],
        "holdout_predictions": [...]
    }
    ```
    """
    df = _load_and_filter(request.breed)
    try:
        result = evaluate_models(df)
    except ValueError as e:
        raise HTTPException(
            status_code=400,
            detail={"success": False, "error": str(e)},
        )
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail={"success": False, "error": f"Evaluation failed: {str(e)}"},
        )
    return result


@app.post(
    "/forecast",
    summary="Generate automatic forecast using the recommended model",
    response_description="Future forecast with predicted egg counts",
)
async def forecast(request: ForecastRequest):
    """
    Generate a future forecast automatically.

    The endpoint internally runs model evaluation to determine the best model,
    then generates a forecast for the specified number of days.

    - If XGBoost is recommended, it uses the latest observed environmental values
      carried forward with flock age incremented daily.
    - If SARIMA is recommended, it uses SARIMA directly (no exogenous variables needed).

    **Request Body Example:**
    ```json
    {"breed": "ALL", "forecast_days": 7}
    ```

    **Response Example:**
    ```json
    {
        "recommended_model": "XGBoost",
        "forecast": [
            {"date": "2026-06-23T00:00:00", "predicted_egg_count": 8},
            {"date": "2026-06-24T00:00:00", "predicted_egg_count": 8}
        ]
    }
    ```
    """
    df = _load_and_filter(request.breed)
    try:
        result = automatic_forecast(df, forecast_days=request.forecast_days)
    except ValueError as e:
        raise HTTPException(
            status_code=400,
            detail={"success": False, "error": str(e)},
        )
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail={"success": False, "error": f"Forecast failed: {str(e)}"},
        )
    return result


@app.post(
    "/manual-forecast",
    summary="Generate forecast with user-provided environmental inputs using XGBoost",
    response_description="Future forecast with predicted egg counts",
)
async def manual_forecast_endpoint(request: ManualForecastRequest):
    """
    Generate a forecast using manually entered environmental conditions.

    Uses XGBoost ensemble (3 seeds) with all exogenous variables provided by the user.
    Flock age is automatically incremented across the forecast horizon.

    **Request Body Example:**
    ```json
    {
        "breed": "ISA Brown",
        "forecast_days": 7,
        "live_hens": 12,
        "flock_age_weeks": 26,
        "temperature_c": 33.5,
        "humidity_percent": 82,
        "crude_protein_percent": 17.5,
        "total_feed_consumed_kg": 34.2,
        "monthly_mortality": 0,
        "heat_stress": 1
    }
    ```

    **Response Example:**
    ```json
    {
        "recommended_model": "XGBoost",
        "forecast": [
            {"date": "2026-06-23T00:00:00", "predicted_egg_count": 10},
            {"date": "2026-06-24T00:00:00", "predicted_egg_count": 10}
        ]
    }
    ```
    """
    df = _load_and_filter(request.breed)
    try:
        result = manual_forecast(
            df=df,
            forecast_days=request.forecast_days,
            breed=request.breed,
            live_hens=request.live_hens,
            flock_age_weeks=request.flock_age_weeks,
            temperature_c=request.temperature_c,
            humidity_percent=request.humidity_percent,
            crude_protein_percent=request.crude_protein_percent,
            total_feed_consumed_kg=request.total_feed_consumed_kg,
            monthly_mortality=request.monthly_mortality,
            heat_stress=request.heat_stress,
        )
    except ValueError as e:
        raise HTTPException(
            status_code=400,
            detail={"success": False, "error": str(e)},
        )
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail={"success": False, "error": f"Manual forecast failed: {str(e)}"},
        )
    return result


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "api:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
    )
