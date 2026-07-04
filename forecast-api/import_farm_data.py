"""Import farm data from Excel into the LayRate MySQL database.

Reads a single-sheet Excel file with columns matching create_import_template.py
and performs an idempotent import:

  - Creates cages that do not already exist (keyed by Cage_Code).
  - Creates missing cage slots based on Rows x Slots_Per_Row.
  - Ensures each slot has the required number of active hens, auto-generating
    unique tag codes when needed.
  - Inserts production_logs, environmental_logs, feed_batches,
    feed_consumption_logs, and mortality_logs.

Usage:
    python import_farm_data.py farm_data.xlsx --recorded-by 1
"""

import argparse
import logging
import os
import re
from datetime import date, datetime
from typing import Optional

import pandas as pd
from sqlalchemy import create_engine, text
from sqlalchemy.engine import Engine

logging.basicConfig(level=logging.INFO, format="%(levelname)s: %(message)s")
logger = logging.getLogger(__name__)

VALID_BREEDS = {
    "ISA Brown",
    "Lohmann Brown-Classic",
    "Dekalb White",
    "Hy-Line Brown",
    "Novogen Brown",
}

VALID_MORTALITY_REASONS = {
    "Disease",
    "Heat Stress",
    "Injury",
    "Predator",
    "Unknown",
    "Other",
}

DEFAULT_ROWS = 3
DEFAULT_SLOTS_PER_ROW = 5
DEFAULT_MAX_CHICKENS_PER_SLOT = 4
ENV_RECORDING_HOUR = 12


# ───────────────────────── Database helpers ─────────────────────────


def build_engine() -> Engine:
    """Build a SQLAlchemy MySQL engine from environment variables."""
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    database = os.getenv("DB_DATABASE", "layrate")
    username = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    if not database or not username:
        raise ValueError("DB_DATABASE and DB_USERNAME must be provided.")
    return create_engine(f"mysql+pymysql://{username}:{password}@{host}:{port}/{database}")


# ─────────────────────────── Normalization ──────────────────────────


def to_str(value) -> Optional[str]:
    if pd.isna(value):
        return None
    return str(value).strip() or None


def to_int(value, default: int = 0) -> int:
    try:
        if pd.isna(value):
            return default
        return int(float(value))
    except (ValueError, TypeError):
        return default


def to_float(value, default: float = 0.0) -> float:
    try:
        if pd.isna(value):
            return default
        return float(value)
    except (ValueError, TypeError):
        return default


def to_date(value) -> Optional[date]:
    if pd.isna(value):
        return None
    parsed = pd.to_datetime(value, errors="coerce")
    if pd.isna(parsed):
        return None
    return parsed.date()


def normalize_breed(value) -> str:
    value = to_str(value)
    if value:
        for b in VALID_BREEDS:
            if b.lower() == value.lower():
                return b
    return "ISA Brown"


def normalize_reason(value) -> str:
    value = to_str(value)
    if value:
        for r in VALID_MORTALITY_REASONS:
            if r.lower() == value.lower():
                return r
    return "Unknown"


def slugify(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")


# ─────────────────────────── Cage / slots ───────────────────────────


def get_or_create_cage(
    conn,
    cage_code: str,
    location: Optional[str],
    rows: int,
    slots_per_row: int,
    max_per_slot: int,
) -> int:
    result = conn.execute(
        text("SELECT id FROM cages WHERE cage_code = :code"), {"code": cage_code}
    ).fetchone()

    if result:
        return result[0]

    total_capacity = rows * slots_per_row * max_per_slot
    result = conn.execute(
        text(
            """
            INSERT INTO cages (
                cage_code, location, rows, slots_per_row,
                max_chickens_per_slot, total_capacity, is_active, created_at, updated_at
            )
            VALUES (
                :code, :location, :rows, :slots_per_row,
                :max_per_slot, :total_capacity, 1, NOW(), NOW()
            )
            """
        ),
        {
            "code": cage_code,
            "location": location or "",
            "rows": rows,
            "slots_per_row": slots_per_row,
            "max_per_slot": max_per_slot,
            "total_capacity": total_capacity,
        },
    )
    logger.info("Created cage %s (id=%s)", cage_code, result.lastrowid)
    return result.lastrowid


def ensure_cage_slots(conn, cage_id: int, rows: int, slots_per_row: int) -> dict:
    """Ensure all slots exist for a cage and return slot_number -> id mapping."""
    existing = conn.execute(
        text("SELECT slot_number FROM cage_slots WHERE cage_id = :cage_id"),
        {"cage_id": cage_id},
    ).fetchall()
    existing_slots = {row[0] for row in existing}

    inserts = []
    slot_number = 1
    for row in range(1, rows + 1):
        for col in range(1, slots_per_row + 1):
            if slot_number not in existing_slots:
                inserts.append(
                    {
                        "cage_id": cage_id,
                        "row_number": row,
                        "column_number": col,
                        "slot_number": slot_number,
                        "current_occupancy": 0,
                    }
                )
            slot_number += 1

    if inserts:
        conn.execute(
            text(
                """
                INSERT INTO cage_slots (
                    cage_id, row_number, column_number, slot_number,
                    current_occupancy, created_at, updated_at
                )
                VALUES (
                    :cage_id, :row_number, :column_number, :slot_number,
                    :current_occupancy, NOW(), NOW()
                )
                """
            ),
            inserts,
        )
        logger.info("Created %d slot(s) for cage id=%d", len(inserts), cage_id)

    result = conn.execute(
        text("SELECT slot_number, id FROM cage_slots WHERE cage_id = :cage_id"),
        {"cage_id": cage_id},
    ).fetchall()
    return {row[0]: row[1] for row in result}


# ───────────────────────────── Hens ─────────────────────────────────


def ensure_hens(
    conn,
    cage_slot_id: int,
    cage_code: str,
    slot_number: int,
    breed: str,
    live_hens: int,
    date_acquired: Optional[date],
    placement_date: Optional[date],
    age_at_placement: int,
    flock_age_weeks: int,
):
    if live_hens <= 0:
        return

    existing_count = conn.execute(
        text("SELECT COUNT(*) FROM hens WHERE cage_slot_id = :slot_id AND is_active = 1"),
        {"slot_id": cage_slot_id},
    ).fetchone()[0]

    if existing_count >= live_hens:
        return

    hens_to_create = live_hens - existing_count
    inserts = []
    breed_slug = slugify(breed)
    for i in range(hens_to_create):
        seq = existing_count + i + 1
        tag_code = f"{cage_code}-S{slot_number}-{breed_slug}-{seq:03d}"
        inserts.append(
            {
                "cage_slot_id": cage_slot_id,
                "tag_code": tag_code,
                "date_acquired": date_acquired,
                "placement_date": placement_date,
                "age_at_placement_weeks": age_at_placement,
                "flock_age_weeks": flock_age_weeks,
                "breed": breed,
                "is_active": 1,
            }
        )

    conn.execute(
        text(
            """
            INSERT INTO hens (
                cage_slot_id, tag_code, date_acquired, placement_date,
                age_at_placement_weeks, flock_age_weeks, breed, is_active,
                created_at, updated_at
            )
            VALUES (
                :cage_slot_id, :tag_code, :date_acquired, :placement_date,
                :age_at_placement_weeks, :flock_age_weeks, :breed, :is_active,
                NOW(), NOW()
            )
            """
        ),
        inserts,
    )
    logger.info(
        "Created %d hen(s) for cage %s slot %d",
        hens_to_create,
        cage_code,
        slot_number,
    )

    conn.execute(
        text("UPDATE cage_slots SET current_occupancy = :count WHERE id = :id"),
        {"count": live_hens, "id": cage_slot_id},
    )


# ─────────────────────────── Fact tables ────────────────────────────


def insert_production_log(
    conn,
    cage_slot_id: int,
    log_date: date,
    egg_count: int,
    hen_count: int,
    recorded_by: Optional[int],
    notes: Optional[str],
):
    hdep = round((egg_count / hen_count) * 100, 2) if hen_count > 0 else 0.0
    conn.execute(
        text(
            """
            INSERT INTO production_logs (
                cage_slot_id, log_date, egg_count, hen_count, hdep,
                recorded_by, notes, created_at
            )
            VALUES (
                :cage_slot_id, :log_date, :egg_count, :hen_count, :hdep,
                :recorded_by, :notes, NOW()
            )
            ON DUPLICATE KEY UPDATE
                egg_count = VALUES(egg_count),
                hen_count = VALUES(hen_count),
                hdep = VALUES(hdep),
                notes = VALUES(notes)
            """
        ),
        {
            "cage_slot_id": cage_slot_id,
            "log_date": log_date,
            "egg_count": egg_count,
            "hen_count": hen_count,
            "hdep": hdep,
            "recorded_by": recorded_by,
            "notes": notes,
        },
    )


def insert_environmental_log(
    conn,
    cage_id: int,
    recorded_at: datetime,
    temperature_c: float,
    humidity_pct: float,
):
    conn.execute(
        text(
            """
            INSERT INTO environmental_logs (
                cage_id, recorded_at, temperature_c, humidity_pct, created_at
            )
            VALUES (:cage_id, :recorded_at, :temperature_c, :humidity_pct, NOW())
            """
        ),
        {
            "cage_id": cage_id,
            "recorded_at": recorded_at,
            "temperature_c": temperature_c,
            "humidity_pct": humidity_pct,
        },
    )


def get_or_create_feed_batch(
    conn, batch_code: str, crude_protein: float, date_received: date
) -> int:
    result = conn.execute(
        text("SELECT id FROM feed_batches WHERE batch_code = :code"),
        {"code": batch_code},
    ).fetchone()
    if result:
        return result[0]

    result = conn.execute(
        text(
            """
            INSERT INTO feed_batches (
                batch_code, crude_protein, date_received, created_at, updated_at
            )
            VALUES (:code, :protein, :date_received, NOW(), NOW())
            """
        ),
        {
            "code": batch_code,
            "protein": crude_protein,
            "date_received": date_received,
        },
    )
    logger.info("Created feed batch %s (id=%s)", batch_code, result.lastrowid)
    return result.lastrowid


def insert_feed_consumption(
    conn,
    cage_id: int,
    feed_batch_id: int,
    log_date: date,
    feed_consumed_kg: float,
    recorded_by: Optional[int],
):
    conn.execute(
        text(
            """
            INSERT INTO feed_consumption_logs (
                cage_id, feed_batch_id, log_date, feed_consumed_kg,
                recorded_by, created_at
            )
            VALUES (
                :cage_id, :feed_batch_id, :log_date, :feed_consumed_kg,
                :recorded_by, NOW()
            )
            ON DUPLICATE KEY UPDATE
                feed_consumed_kg = VALUES(feed_consumed_kg),
                feed_batch_id = VALUES(feed_batch_id)
            """
        ),
        {
            "cage_id": cage_id,
            "feed_batch_id": feed_batch_id,
            "log_date": log_date,
            "feed_consumed_kg": feed_consumed_kg,
            "recorded_by": recorded_by,
        },
    )


def insert_mortality(
    conn,
    cage_id: int,
    log_date: date,
    count: int,
    reason: str,
    notes: Optional[str],
    recorded_by: Optional[int],
):
    conn.execute(
        text(
            """
            INSERT INTO mortality_logs (
                cage_id, log_date, count, reason, notes, recorded_by, created_at
            )
            VALUES (
                :cage_id, :log_date, :count, :reason, :notes, :recorded_by, NOW()
            )
            """
        ),
        {
            "cage_id": cage_id,
            "log_date": log_date,
            "count": count,
            "reason": reason,
            "notes": notes,
            "recorded_by": recorded_by,
        },
    )


# ─────────────────────────── Main import ────────────────────────────


def import_farm_data(file_path: str, recorded_by: Optional[int] = None):
    engine = build_engine()

    df = pd.read_excel(file_path, engine="openpyxl")
    df.columns = [str(c).strip() for c in df.columns]

    required = {"Date", "Cage_Code", "Slot_Number"}
    missing = required - set(df.columns)
    if missing:
        raise ValueError(f"Missing required columns: {', '.join(sorted(missing))}")

    df["Date"] = pd.to_datetime(df["Date"], errors="coerce").dt.date
    df = df.dropna(subset=["Date", "Cage_Code"]).copy()

    if df.empty:
        raise ValueError("No valid rows to import after parsing dates/cage codes.")

    # Track once-per-cage-per-day operations to avoid duplicates.
    processed_env = set()
    processed_feed = set()
    processed_mortality = set()

    with engine.begin() as conn:
        for cage_code in sorted(df["Cage_Code"].unique()):
            cage_df = df[df["Cage_Code"] == cage_code].copy()
            first_row = cage_df.iloc[0]

            location = to_str(first_row.get("Location"))
            rows = to_int(first_row.get("Rows"), DEFAULT_ROWS)
            slots_per_row = to_int(first_row.get("Slots_Per_Row"), DEFAULT_SLOTS_PER_ROW)
            max_per_slot = to_int(
                first_row.get("Max_Chickens_Per_Slot"), DEFAULT_MAX_CHICKENS_PER_SLOT
            )

            cage_id = get_or_create_cage(
                conn, cage_code, location, rows, slots_per_row, max_per_slot
            )
            slot_map = ensure_cage_slots(conn, cage_id, rows, slots_per_row)

            for _, row in cage_df.iterrows():
                slot_number = to_int(row.get("Slot_Number"), 1)
                cage_slot_id = slot_map.get(slot_number)
                if not cage_slot_id:
                    logger.warning(
                        "Slot %d not found for cage %s; skipping row.",
                        slot_number,
                        cage_code,
                    )
                    continue

                log_date = row["Date"]
                breed = normalize_breed(row.get("Breed"))
                live_hens = to_int(row.get("Live_Hens"), 0)

                # Default hen acquisition/placement dates to earliest date for
                # this cage/slot if not supplied.
                slot_min_date = cage_df.loc[
                    cage_df["Slot_Number"] == slot_number, "Date"
                ].min()
                if pd.isna(slot_min_date):
                    slot_min_date = log_date

                date_acquired = to_date(row.get("Date_Acquired")) or slot_min_date
                placement_date = to_date(row.get("Placement_Date")) or slot_min_date
                age_at_placement = to_int(row.get("Age_At_Placement_Weeks"), 0)
                flock_age_weeks = to_int(row.get("Flock_Age_Weeks"), 0)

                ensure_hens(
                    conn,
                    cage_slot_id,
                    cage_code,
                    slot_number,
                    breed,
                    live_hens,
                    date_acquired,
                    placement_date,
                    age_at_placement,
                    flock_age_weeks,
                )

                insert_production_log(
                    conn,
                    cage_slot_id,
                    log_date,
                    to_int(row.get("Egg_Count"), 0),
                    live_hens,
                    recorded_by,
                    to_str(row.get("Notes")),
                )

                env_key = (cage_id, log_date)
                temp = to_float(row.get("Temperature_C"))
                humidity = to_float(row.get("Humidity_Percent"))
                if env_key not in processed_env and (temp or humidity):
                    recorded_at = datetime.combine(
                        log_date, datetime.min.time().replace(hour=ENV_RECORDING_HOUR)
                    )
                    insert_environmental_log(
                        conn, cage_id, recorded_at, temp, humidity
                    )
                    processed_env.add(env_key)

                batch_code = to_str(row.get("Feed_Batch_Code"))
                if batch_code:
                    feed_key = (cage_id, log_date)
                    feed_consumed = to_float(row.get("Feed_Consumed_kg"))
                    crude_protein = to_float(row.get("Crude_Protein_Percent"), 0.0)
                    feed_batch_id = get_or_create_feed_batch(
                        conn, batch_code, crude_protein, log_date
                    )
                    if feed_key not in processed_feed and feed_consumed > 0:
                        insert_feed_consumption(
                            conn,
                            cage_id,
                            feed_batch_id,
                            log_date,
                            feed_consumed,
                            recorded_by,
                        )
                        processed_feed.add(feed_key)

                mortality_count = to_int(row.get("Mortality_Count"), 0)
                if mortality_count > 0:
                    mortality_key = (cage_id, log_date)
                    if mortality_key not in processed_mortality:
                        insert_mortality(
                            conn,
                            cage_id,
                            log_date,
                            mortality_count,
                            normalize_reason(row.get("Mortality_Reason")),
                            to_str(row.get("Mortality_Notes")),
                            recorded_by,
                        )
                        processed_mortality.add(mortality_key)

    logger.info("Import completed: %d row(s) processed.", len(df))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Import farm data from Excel into the LayRate database."
    )
    parser.add_argument("file", help="Path to the Excel import file.")
    parser.add_argument(
        "--recorded-by",
        type=int,
        default=None,
        help="User ID to store as recorded_by for logs.",
    )
    args = parser.parse_args()

    import_farm_data(args.file, recorded_by=args.recorded_by)
