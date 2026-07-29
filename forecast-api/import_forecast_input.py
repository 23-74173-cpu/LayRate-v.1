"""Bulk import a forecast input Excel sheet into forecast_input_records.

Reads the sheet produced by generate_forecast_sheet.py and upserts rows into
the MySQL forecast_input_records table. Existing rows for the same
(date, cage_code) are updated; new rows are inserted.
"""

import argparse
import json
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
    "Crude_Protein_Percent": "crude_protein_percent",
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


def parse_forecast_file(file_path: str):
    """Parse the Excel file and return (df, raw_row_count, invalid_rows).

    - df: cleaned DataFrame with only valid rows (ready for DB insert)
    - raw_row_count: total rows in the original file (excluding header)
    - invalid_rows: list of dicts with row_number, reason for each skipped row
    """
    df_raw = pd.read_excel(file_path, engine="openpyxl", dtype=str)
    raw_row_count = len(df_raw)

    df = pd.read_excel(file_path, engine="openpyxl")
    df.columns = [str(c).strip() for c in df.columns]

    missing = REQUIRED_COLUMNS - set(df.columns)
    if missing:
        raise ValueError(f"Missing required columns: {', '.join(sorted(missing))}")

    df = df.rename(columns=COLUMN_MAP)

    # Track invalid rows before dropping.
    invalid_rows = []

    # Check for bad dates (row numbers are 1-indexed, +1 for header).
    if "date" in df.columns:
        original_dates = df["date"].copy()
        df["date"] = pd.to_datetime(df["date"], errors="coerce").dt.date
        for idx in df.index:
            if pd.isna(df.loc[idx, "date"]):
                raw_val = original_dates.loc[idx] if idx in original_dates.index else "—"
                invalid_rows.append({
                    "row": int(idx) + 2,  # +2: +1 for 0-index, +1 for header
                    "reason": f"invalid date ({raw_val})",
                })

    # Check for missing/empty cage_code.
    if "cage_code" in df.columns:
        df["cage_code"] = df["cage_code"].astype(str).str.strip()
        for idx in df.index:
            if pd.isna(df.loc[idx, "cage_code"]) or df.loc[idx, "cage_code"] == "" or df.loc[idx, "cage_code"] == "nan":
                invalid_rows.append({
                    "row": int(idx) + 2,
                    "reason": "missing Cage_Code",
                })

    # Coerce numeric columns and track invalid values.
    numeric_cols = [
        "flock_age_weeks", "hen_count", "egg_count",
        "temperature_c", "humidity_percent", "feed_consumed_kg", "mortality_count",
    ]
    for col in numeric_cols:
        if col in df.columns:
            original_vals = df[col].copy()
            df[col] = pd.to_numeric(df[col], errors="coerce")
            for idx in df.index:
                if pd.isna(df.loc[idx, col]) and not pd.isna(original_vals.loc[idx]):
                    raw_val = original_vals.loc[idx]
                    if str(raw_val).strip() not in ("", "nan", "None", "NaN"):
                        # Only flag if the original value was non-empty and non-numeric
                        try:
                            float(raw_val)
                        except (ValueError, TypeError):
                            invalid_rows.append({
                                "row": int(idx) + 2,
                                "reason": f"non-numeric {col} ({raw_val})",
                            })

    # Drop rows with missing required identifiers (these are already in invalid_rows).
    before_drop = len(df)
    df = df.dropna(subset=["date", "cage_code"])
    df = df[df["cage_code"] != ""]
    after_drop = len(df)

    # Add rows that were dropped by dropna but not already flagged.
    dropped_rows = set(r["row"] for r in invalid_rows)
    for idx in range(before_drop):
        excel_row = idx + 2
        if excel_row not in dropped_rows:
            # Check if this row was dropped
            pass  # dropna rows are already captured above

    # Date range of valid rows.
    date_range = None
    if not df.empty and "date" in df.columns:
        dates = df["date"].dropna()
        if not dates.empty:
            date_range = {
                "start": str(dates.min()),
                "end": str(dates.max()),
            }

    # Deduplicate invalid rows by (row, reason).
    seen = set()
    unique_invalid = []
    for r in invalid_rows:
        key = (r["row"], r["reason"])
        if key not in seen:
            seen.add(key)
            unique_invalid.append(r)
    invalid_rows = sorted(unique_invalid, key=lambda x: x["row"])

    return df, raw_row_count, invalid_rows, date_range


def preview_forecast_input(file_path: str) -> dict:
    """Parse the file and return preview metadata without writing to DB."""
    df, raw_row_count, invalid_rows, date_range = parse_forecast_file(file_path)

    return {
        "total_rows": raw_row_count,
        "valid_rows": len(df),
        "invalid_rows": invalid_rows,
        "invalid_count": len(invalid_rows),
        "date_range": date_range,
    }


def import_forecast_input(file_path: str, source_file: str | None = None) -> int:
    df, raw_row_count, invalid_rows, _ = parse_forecast_file(file_path)

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
                    temperature_c, humidity_percent, crude_protein_percent,
                    feed_consumed_kg, mortality_count, source_file
                ) VALUES (
                    :date, :cage_code, :breed, :flock_age_weeks, :hen_count,
                    :egg_count, :temperature_c, :humidity_percent,
                    :crude_protein_percent,
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
                    crude_protein_percent = VALUES(crude_protein_percent),
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
    parser.add_argument(
        "--preview",
        action="store_true",
        help="Parse the file and output JSON preview without writing to DB.",
    )
    args = parser.parse_args()

    if args.preview:
        result = preview_forecast_input(args.file)
        print(json.dumps(result))
    else:
        count = import_forecast_input(args.file, source_file=args.source_file)
        print(f"Imported {count} row(s) into forecast_input_records.")
