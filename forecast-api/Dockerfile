FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    g++ \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY ForecastingV5.py forecast_runner.py generate_forecast_sheet.py import_forecast_input.py ./

# The forecast pipeline is invoked as a CLI by the Laravel backend.
# Override CMD at runtime to run a specific script, e.g.:
#   docker run --rm -e DB_HOST=... layrate-forecast python forecast_runner.py --mode auto --horizon 7
CMD ["python", "forecast_runner.py", "--help"]
