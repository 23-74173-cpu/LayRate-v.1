"""Generate a forecast input Excel sheet based on actual cages.

The sheet is structured for daily egg logging per cage (total eggs and total
hens for the whole cage). After it is filled, import_farm_data.py can load the
data into the database, where ForecastingV5.py reads it for model training and
forecasting.

The number of hens per cage is pre-filled from the count of active hens in the hens table.

Pre-filled columns (Date, Cage_Code, Breed, Flock_Age_Weeks, Hen_Count) are
sheet-protected and non-editable. Only Egg_Count, Temperature_C,
Humidity_Percent, Crude_Protein_Percent, Feed_Consumed_kg,
and Mortality_Count can be edited.
"""

import argparse
import os
from datetime import date, timedelta
from pathlib import Path

import pandas as pd
from openpyxl.styles import Protection
from sqlalchemy import create_engine, text


def load_env():
    """Load environment variables from the project root .env file."""
    env_path = Path(__file__).resolve().parent.parent / ".env"
    if not env_path.exists():
        return
    with open(env_path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            value = value.strip().strip('"').strip("'")
            if value and key not in os.environ:
                os.environ[key] = value


def build_engine():
    """Build a SQLAlchemy MySQL engine from environment variables."""
    load_env()
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    database = os.getenv("DB_DATABASE", "layrate")
    username = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    if not database or not username:
        raise ValueError("DB_DATABASE and DB_USERNAME must be provided.")
    return create_engine(f"mysql+pymysql://{username}:{password}@{host}:{port}/{database}")


def _parse_date(value: str) -> date:
    """Parse a YYYY-MM-DD date string."""
    try:
        return date.fromisoformat(value)
    except ValueError as exc:
        raise ValueError(f"Invalid date '{value}'. Use YYYY-MM-DD format.") from exc


def generate_forecast_sheet(
    start_date: date | None = None,
    end_date: date | None = None,
    days: int = 90,
    output: str = "forecast_input_sheet.xlsx",
):
    engine = build_engine()

    with engine.connect() as conn:
        result = conn.execute(
            text(
                """
                SELECT
                    c.cage_code,
                    COUNT(h.id) AS hen_count,
                    COALESCE(MAX(h.breed), 'ISA Brown') AS breed,
                    COALESCE(MAX(h.flock_age_weeks), 0) AS flock_age_weeks
                FROM cages c
                JOIN cage_slots cs ON cs.cage_id = c.id
                LEFT JOIN hens h ON h.cage_slot_id = cs.id AND h.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id, c.cage_code
                ORDER BY c.cage_code
                """
            )
        )
        cages = result.fetchall()

    if not cages:
        raise ValueError("No active cages found in the database.")

    # Resolve date range.
    if start_date and end_date:
        if start_date > end_date:
            raise ValueError("start_date cannot be after end_date.")
    elif start_date or end_date:
        raise ValueError("Both --start-date and --end-date are required when specifying a range.")
    else:
        end_date = date.today()
        start_date = end_date - timedelta(days=days - 1)

    dates = pd.date_range(start=start_date, end=end_date, freq="D").date

    rows = []
    for d in dates:
        # Flock age decreases by 1 week for every 7 days back from the end date.
        days_back = (end_date - d).days
        weeks_back = days_back // 7

        for cage in cages:
            flock_age = max(0, int(cage.flock_age_weeks) - weeks_back)
            rows.append(
                {
                    "Date": d,
                    "Cage_Code": cage.cage_code,
                    "Breed": cage.breed,
                    "Flock_Age_Weeks": flock_age,
                    "Hen_Count": int(cage.hen_count),
                    "Egg_Count": "",
                    "Temperature_C": "",
                    "Humidity_Percent": "",
                    "Crude_Protein_Percent": "",
                    "Feed_Consumed_kg": "",
                    "Mortality_Count": "",
                }
            )

    df = pd.DataFrame(rows)

    LOCKED_COLUMNS = {
        "Date",
        "Cage_Code",
        "Breed",
        "Flock_Age_Weeks",
        "Hen_Count",
    }
    editable_col_indices = [
        idx for idx, col in enumerate(df.columns, start=1) if col not in LOCKED_COLUMNS
    ]

    with pd.ExcelWriter(output, engine="openpyxl") as writer:
        df.to_excel(writer, index=False, sheet_name="Forecast Input")
        ws = writer.sheets["Forecast Input"]
        ws.column_dimensions['A'].width = 14

        # Unlock the editable cells in data rows (row 1 is the header).
        for row in range(2, ws.max_row + 1):
            for col_idx in editable_col_indices:
                ws.cell(row=row, column=col_idx).protection = Protection(locked=False)

        # Enable sheet protection so locked cells cannot be edited.
        ws.protection.sheet = True

    print(f"Generated forecast input sheet: {output}")
    print(f"Rows: {len(rows)} ({len(dates)} days x {len(cages)} cage(s))")
    print(f"Columns: {', '.join(df.columns)}")
    print(f"Locked columns: {', '.join(sorted(LOCKED_COLUMNS))}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Generate a forecast input Excel sheet from database cages."
    )
    parser.add_argument(
        "--start-date",
        default=None,
        help="Start date in YYYY-MM-DD format (requires --end-date).",
    )
    parser.add_argument(
        "--end-date",
        default=None,
        help="End date in YYYY-MM-DD format (requires --start-date).",
    )
    parser.add_argument(
        "--days",
        type=int,
        default=90,
        help="Number of days ending today (default: 90). Ignored when --start-date and --end-date are provided.",
    )
    parser.add_argument(
        "--output",
        default="forecast_input_sheet.xlsx",
        help="Output Excel filename (default: forecast_input_sheet.xlsx).",
    )
    args = parser.parse_args()

    start_date = _parse_date(args.start_date) if args.start_date else None
    end_date = _parse_date(args.end_date) if args.end_date else None

    generate_forecast_sheet(
        start_date=start_date,
        end_date=end_date,
        days=args.days,
        output=args.output,
    )
