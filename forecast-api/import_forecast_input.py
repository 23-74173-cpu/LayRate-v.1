"""Bulk import a forecast input Excel sheet into forecast_input_records.

Reads the sheet produced by generate_forecast_sheet.py and upserts rows into
the MySQL forecast_input_records table. Existing rows for the same
(date, cage_code) are updated; new rows are inserted.
"""

import argparse
import os
from pathlib import Path

import pandas as pd
from sqlalchemy import create_engine, text


REQUIRED_COLUMNS = {"Date", "Cage_Code"}

COLUMN_MAP = {
    "Date": "date",
    "Cage_Code": "cage_code",
    "Breed": "breed",
    "Flock_Age_Weeks": "flock_age_weeks",
    "Hen_Count": "hen_count",
    "Egg_Count": "egg_count",
    "Temperature_C": "temperature_c",
    "Humidity_Percent": "humidity_percent",
    "Feed_Consumed_kg": "feed_consumed_kg",
    "Mortality_Count": "mortality_count",
}


def build_engine():
    """Build a SQLAlchemy MySQL engine from environment variables."""
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    database = os.getenv("DB_DATABASE", "layrate")
    username = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    if not database or not username:
        raise ValueError("DB_DATABASE and DB_USERNAME must be provided.")
    return create_engine(f"mysql+pymysql://{username}:{password}@{host}:{port}/{database}")


def clean_value(value):
    """Convert pandas NaN/NaT values to None for MySQL insertion."""
    if value is None:
        return None
    try:
        if pd.isna(value):
            return None
    except (TypeError, ValueError):
        pass
    return value


def import_forecast_input(file_path: str, source_file: str | None = None) -> int:
    df = pd.read_excel(file_path, engine="openpyxl")
    df.columns = [str(c).strip() for c in df.columns]

    missing = REQUIRED_COLUMNS - set(df.columns)
    if missing:
        raise ValueError(f"Missing required columns: {', '.join(sorted(missing))}")

    df = df.rename(columns=COLUMN_MAP)

    # Normalize date and cage_code.
    df["date"] = pd.to_datetime(df["date"], errors="coerce").dt.date
    df["cage_code"] = df["cage_code"].astype(str).str.strip()

    # Coerce numeric columns.
    numeric_cols = [
        "flock_age_weeks",
        "hen_count",
        "egg_count",
        "temperature_c",
        "humidity_percent",
        "feed_consumed_kg",
        "mortality_count",
    ]
    for col in numeric_cols:
        if col in df.columns:
            df[col] = pd.to_numeric(df[col], errors="coerce")

    # Drop rows missing required identifiers.
    df = df.dropna(subset=["date", "cage_code"])
    df = df[df["cage_code"] != ""]

    if df.empty:
        raise ValueError("No valid rows to import after parsing dates and cage codes.")

    # Keep only columns that exist in the target table, plus source_file.
    db_columns = set(COLUMN_MAP.values()) | {"source_file"}
    df = df[[c for c in df.columns if c in db_columns or c == "source_file"]]
    df["source_file"] = source_file or os.path.basename(file_path)

    records = []
    for _, row in df.iterrows():
        record = {k: clean_value(v) for k, v in row.items()}
        records.append(record)

    engine = build_engine()
    with engine.begin() as conn:
        conn.execute(
            text(
                """
                INSERT INTO forecast_input_records (
                    date, cage_code, breed, flock_age_weeks, hen_count, egg_count,
                    temperature_c, humidity_percent,
                    feed_consumed_kg, mortality_count, source_file
                ) VALUES (
                    :date, :cage_code, :breed, :flock_age_weeks, :hen_count,
                    :egg_count, :temperature_c, :humidity_percent,
                    :feed_consumed_kg,
                    :mortality_count, :source_file
                )
                ON DUPLICATE KEY UPDATE
                    breed = VALUES(breed),
                    flock_age_weeks = VALUES(flock_age_weeks),
                    hen_count = VALUES(hen_count),
                    egg_count = VALUES(egg_count),
                    temperature_c = VALUES(temperature_c),
                    humidity_percent = VALUES(humidity_percent),
                    feed_consumed_kg = VALUES(feed_consumed_kg),
                    mortality_count = VALUES(mortality_count),
                    source_file = VALUES(source_file),
                    updated_at = NOW()
                """
            ),
            records,
        )

    return len(records)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Import a forecast input Excel sheet into forecast_input_records."
    )
    parser.add_argument("file", help="Path to the Excel file to import.")
    parser.add_argument(
        "--source-file",
        default=None,
        help="Value to store in the source_file column (default: file basename).",
    )
    args = parser.parse_args()

    count = import_forecast_input(args.file, source_file=args.source_file)
    print(f"Imported {count} row(s) into forecast_input_records.")
