"""Bulk import a forecast input Excel sheet into the app's native tables.

Reads the sheet produced by generate_forecast_sheet.py and writes the data
into the native operational tables so the forecasting pipeline (which
aggregates these on demand) and the rest of the app stay in sync:

  - production_logs   : cage-level egg_count / hen_count are distributed
                        across the cage's slots (weighted by active hens per
                        slot) and upserted on (cage_slot_id, log_date).
  - egg_size_logs     : one 'unsorted' entry per production log with eggs.
  - environmental_logs: temperature / humidity recorded for the reporting
                        date at 12:00:00, flagged as a manual override.
  - feed_consumption_logs: each date's farm-wide feed total (the sum of the
                        sheet's per-cage Feed_Consumed_kg) is distributed
                        across active cages with active hens, weighted by live
                        active hen count using largest-remainder — the same
                        distribution the feeds/nutrition module applies to a
                        whole-farm feeding entry. The batch is matched by crude
                        protein (else most recent).
  - mortality_logs    : mortality_count per cage / date.

All native writes happen in a single transaction: either everything commits
or nothing does.
"""

import argparse
import json
import math
import os
import sys
from pathlib import Path

import pandas as pd
from sqlalchemy import create_engine, text


def load_env():
    """Load environment variables from .env for standalone debugging."""
    env_path = Path(__file__).resolve().parent.parent / ".env"
    if env_path.exists():
        with open(env_path) as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, _, val = line.partition("=")
                key = key.strip()
                val = val.strip().strip("\"'")
                if not os.environ.get(key):
                    os.environ[key] = val


load_env()

REQUIRED_COLUMNS = {"Date", "Cage_Code"}

INSERT_COLUMNS = {
    "Date", "Cage_Code", "Breed", "Flock_Age_Weeks", "Hen_Count", "Egg_Count",
    "Temperature_C", "Humidity_Percent", "Crude_Protein_Percent",
    "Feed_Consumed_kg", "Mortality_Count",
}

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
    """Parse the Excel file and return (df, raw_row_count, invalid_rows, date_range, missing_columns).

    - df: cleaned DataFrame with only valid rows (ready for DB insert)
    - raw_row_count: total rows in the original file (excluding header)
    - invalid_rows: list of dicts with row_number, reason for each skipped row
    - date_range: dict with start/end or None
    - missing_columns: list of Excel column names that are entirely absent from the file
    """
    df = pd.read_excel(file_path, engine="openpyxl")
    raw_row_count = len(df)
    df.columns = [str(c).strip() for c in df.columns]

    file_cols = set(df.columns)

    missing = REQUIRED_COLUMNS - file_cols
    if missing:
        raise ValueError(f"Missing required columns: {', '.join(sorted(missing))}")

# Detect any other INSERT columns missing from the file (Date/Cage_Code already checked).
    missing_cols = sorted(INSERT_COLUMNS - file_cols - REQUIRED_COLUMNS)

    df = df.rename(columns=COLUMN_MAP)

    # Track invalid rows before dropping.
    invalid_rows = []

    # If columns are entirely missing, mark every row as invalid and empty the
    # DataFrame so valid_rows is 0 (no rows can be imported).
    if missing_cols:
        for idx in df.index:
            invalid_rows.append({
                "row": int(idx) + 2,
                "reason": f"missing columns: {', '.join(missing_cols)}",
            })
        df = df.iloc[0:0]

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

    return df, raw_row_count, invalid_rows, date_range, missing_cols


def preview_forecast_input(file_path: str) -> dict:
    """Parse the file and return preview metadata without writing to DB."""
    df, raw_row_count, invalid_rows, date_range, missing_cols = parse_forecast_file(file_path)

    return {
        "total_rows": raw_row_count,
        "valid_rows": len(df),
        "invalid_rows": invalid_rows,
        "invalid_count": len(invalid_rows),
        "date_range": date_range,
        "missing_columns": missing_cols,
    }


def distribute(total, weights):
    """Split integer `total` across `weights` using largest remainder.

    Returns a list of ints with sum == total. Used to split per-cage
    hen_count / egg_count into per-slot values.
    """
    if len(weights) == 0:
        return []
    if total is None or total <= 0:
        return [0] * len(weights)

    wsum = sum(weights)
    if wsum <= 0:
        weights = [1] * len(weights)
        wsum = len(weights)

    exact = [total * w / wsum for w in weights]
    floors = [int(x) for x in exact]
    remainder = total - sum(floors)
    order = sorted(range(len(exact)), key=lambda i: exact[i] - int(exact[i]), reverse=True)
    for i in order[:remainder]:
        floors[i] += 1
    return floors


def load_cage_structure(conn):
    """Map cage_code -> {id, slots: [(slot_id, active_hens), ...]}.

    Weights come from the live active hens per slot (matching how the app
    derives per-slot hen counts on the egg logging screen). Slots with no
    active hens get weight 0.
    """
    cages = conn.execute(text("SELECT id, cage_code FROM cages")).fetchall()
    structure = {}
    for cage_id, cage_code in cages:
        slots = conn.execute(
            text(
                """
                SELECT cs.id AS slot_id,
                       (SELECT COUNT(*) FROM hens h
                         WHERE h.cage_slot_id = cs.id AND h.is_active = 1) AS active_hens
                  FROM cage_slots cs
                 WHERE cs.cage_id = :cid
                 ORDER BY cs.slot_number, cs.id
                """
            ),
            {"cid": cage_id},
        ).fetchall()
        structure[cage_code] = {
            "id": cage_id,
"slots": [(int(s.slot_id), int(s.active_hens)) for s in slots],
        }
    return structure


def resolve_feed_batch_id(conn, crude_protein):
    """Best feed_batches.id for a row.

    Prefers a batch whose crude_protein matches the file value; otherwise
    falls back to the most recently received batch. Returns None when no
    feed batches exist at all.
    """
    if crude_protein is not None:
        row = conn.execute(
            text(
                """
                SELECT id FROM feed_batches
                ORDER BY ABS(crude_protein - :cp), date_received DESC, id DESC
                LIMIT 1
                """
            ),
            {"cp": crude_protein},
).fetchone()
        if row:
            return row[0]

    row = conn.execute(
        text("SELECT id FROM feed_batches ORDER BY date_received DESC, id DESC LIMIT 1")
    ).fetchone()
    return row[0] if row else None


def ensure_feed_batch_id(conn, crude_protein, fallback_date, feed_batch_cache, stats):
    """Best matching feed_batches.id for a row, auto-creating a fallback batch
    when no feed batches exist at all so imported feed is never dropped.

    The auto-created batch (crude_protein from the file row, date = the feed
    date, a unique IMPORT- batch code) is created once per import and reused
    for every date, mirroring what the app's FeedBatch model would have done.
    """
    key = crude_protein
    batch_id = feed_batch_cache.get(key, None)
    if batch_id is None and key not in feed_batch_cache:
        batch_id = resolve_feed_batch_id(conn, key)
        feed_batch_cache[key] = batch_id
    if batch_id is not None:
        return batch_id

    fallback_key = "__auto_created__"
    batch_id = feed_batch_cache.get(fallback_key)
    if batch_id is not None:
        feed_batch_cache[key] = batch_id
        return batch_id

    base = f"IMPORT-{fallback_date.strftime('%Y%m%d')}"
    batch_code, seq = base, 1
    while conn.execute(
        text("SELECT id FROM feed_batches WHERE batch_code = :bc LIMIT 1"),
        {"bc": batch_code},
    ).fetchone():
        seq += 1
        batch_code = f"{base}-{seq}"

    protein = round(float(crude_protein), 2) if crude_protein is not None else 0.0
    conn.execute(
        text(
            """
            INSERT INTO feed_batches
                (batch_code, crude_protein, date_received, notes)
            VALUES
                (:bc, :cp, :dr, 'Auto-created by forecast import')
            """
        ),
        {"bc": batch_code, "cp": protein, "dr": fallback_date},
    )
    batch_id = conn.execute(text("SELECT LAST_INSERT_ID()")).scalar()
    feed_batch_cache[fallback_key] = batch_id
    feed_batch_cache[key] = batch_id
    stats["feed_batches_created"] = stats.get("feed_batches_created", 0) + 1
    return batch_id


def write_native_tables(conn, records, cage_structure, source_file):
    """Write per-cage forecast input rows into the app's native tables.

    Returns a stats dict for logging:
        production, env, feed, mortality, egg_size: row counts written
        skips: list of human-readable reasons for rows not written
    """
    stats = {
        "production": 0,
        "egg_size": 0,
        "env": 0,
        "feed": 0,
        "mortality": 0,
        "skips": [],
    }

    feed_batch_cache = {}

    for rec in records:
        cage_code = rec["cage_code"]
        date = rec["date"]

        cage = cage_structure.get(cage_code)
        if cage is None:
            stats["skips"].append(f"{cage_code} {date}: unknown cage_code, native tables skipped")
            continue

        cage_id = cage["id"]
        slots = cage["slots"]

        # ---- Production logs (distributed across slots) ----
        hen_total = int(rec["hen_count"] or 0)
        egg_total = int(rec["egg_count"] or 0)

        if slots and hen_total > 0:
            weights = [w for _, w in slots]
            hen_parts = distribute(hen_total, weights)
            egg_parts = distribute(egg_total, weights)

            # Clamp egg <= hen per slot so hdep stays sane; push overflow to a
            # slot with headroom (keeps the cage total intact).
            for i, (slot_id, _) in enumerate(slots):
                if egg_parts[i] > hen_parts[i]:
                    excess = egg_parts[i] - hen_parts[i]
                    egg_parts[i] = hen_parts[i]
                    while excess > 0:
                        moved = False
                        for j in range(len(slots)):
                            if j != i and egg_parts[j] < hen_parts[j]:
                                room = hen_parts[j] - egg_parts[j]
                                take = min(room, excess)
                                egg_parts[j] += take
                                excess -= take
                                moved = True
                                if excess == 0:
                                    break
                        if not moved:
                            egg_parts[i] += excess
                            excess = 0

            for (slot_id, _), hen, egg in zip(slots, hen_parts, egg_parts):
                hdep = round(egg / hen * 100, 2) if hen > 0 else 0.0
                conn.execute(
                    text(
                        """
                        INSERT INTO production_logs
                            (cage_slot_id, log_date, egg_count, hen_count, hdep, notes, logged_via)
                        VALUES
                            (:sid, :date, :egg, :hen, :hdep, :notes, :via)
                        ON DUPLICATE KEY UPDATE
                            egg_count = VALUES(egg_count),
                            hen_count = VALUES(hen_count),
                            hdep = VALUES(hdep),
                            notes = VALUES(notes),
                            logged_via = VALUES(logged_via)
                        """
                    ),
                    {
                        "sid": slot_id,
                        "date": date,
                        "egg": egg,
                        "hen": hen,
                        "hdep": hdep,
                        "notes": f"Import: {source_file}",
                        "via": "unknown",
                    },
                )
                stats["production"] += 1

                if egg > 0:
                    log_row = conn.execute(
                        text(
                            "SELECT id FROM production_logs WHERE cage_slot_id = :sid AND log_date = :date LIMIT 1"
                        ),
                        {"sid": slot_id, "date": date},
                    ).fetchone()
                    if log_row:
                        conn.execute(
                            text(
                                """
                                INSERT INTO egg_size_logs (production_log_id, egg_size, count)
                                VALUES (:pid, 'unsorted', :cnt)
                                ON DUPLICATE KEY UPDATE count = VALUES(count)
                                """
                            ),
                            {"pid": log_row[0], "cnt": egg},
                        )
                        stats["egg_size"] += 1
        else:
            stats["skips"].append(
                f"{cage_code} {date}: no slots or hen_count <= 0, production skipped"
            )

        # ---- Environmental log ----
        if rec["temperature_c"] is not None and rec["humidity_percent"] is not None:
            recorded_at = f"{date} 12:00:00"
            conn.execute(
                text(
                    """
                    INSERT INTO environmental_logs
                        (cage_id, recorded_at, temperature_c, humidity_pct, is_override)
                    VALUES
                        (:cid, :at, :t, :h, 1)
                    ON DUPLICATE KEY UPDATE
                        temperature_c = VALUES(temperature_c),
                        humidity_pct = VALUES(humidity_pct),
                        is_override = 1
                    """
                ),
                {
                    "cid": cage_id,
                    "at": recorded_at,
                    "t": rec["temperature_c"],
                    "h": rec["humidity_percent"],
                },
            )
            stats["env"] += 1

# ---- Mortality log ----
        mort = int(rec["mortality_count"] or 0)
        if mort > 0:
            existing = conn.execute(
                text(
                    """
                    SELECT id FROM mortality_logs
                     WHERE cage_id = :cid AND log_date = :date
                     ORDER BY id LIMIT 1
                    """
                ),
                {"cid": cage_id, "date": date},
            ).fetchone()
            if existing:
                conn.execute(
                    text("UPDATE mortality_logs SET count = :cnt WHERE id = :id"),
                    {"cnt": mort, "id": existing[0]},
                )
            else:
                conn.execute(
                    text(
                        """
                        INSERT INTO mortality_logs (cage_id, log_date, count, reason, notes)
                        VALUES (:cid, :date, :cnt, 'Unknown', :notes)
                        """
                    ),
                    {
                        "cid": cage_id,
                        "date": date,
                        "cnt": mort,
                        "notes": f"Import: {source_file}",
                    },
                )
            stats["mortality"] += 1

    # ---- Feed consumption logs (whole-farm, distributed by hen share) ----
    # Feed is recorded farm-wide, not per cage: the feeds/nutrition module's
    # whole-farm feeding flow (FeedController::distributeFarmFeedEntry) takes
    # one total_kg per date and splits it across active cages with active hens,
    # weighted by live active hen count using largest-remainder so the
    # distributed rows exactly sum to the total. Each date's farm total on
    # import is the sum of the sheet's per-cage Feed_Consumed_kg rows, and the
    # same distribution is applied so imported feed matches what the module
    # would have recorded.
    feed_by_date = {}
    for rec in records:
        if rec["feed_consumed_kg"] is None or rec["feed_consumed_kg"] <= 0:
            continue
        if cage_structure.get(rec["cage_code"]) is None:
            continue
        entry = feed_by_date.setdefault(rec["date"], {"total_kg": 0.0, "crude_protein": None})
        entry["total_kg"] += float(rec["feed_consumed_kg"])
        if entry["crude_protein"] is None and rec["crude_protein_percent"] is not None:
            entry["crude_protein"] = rec["crude_protein_percent"]

    if feed_by_date:
        active_rows = conn.execute(
            text(
                """
                SELECT cs.cage_id AS cage_id, COUNT(*) AS active_hens
                  FROM hens h
                  JOIN cage_slots cs ON cs.id = h.cage_slot_id
                  JOIN cages c ON c.id = cs.cage_id
                 WHERE h.is_active = 1 AND c.is_active = 1
                 GROUP BY cs.cage_id
                """
            )
        ).fetchall()
        weighted = [(int(r.cage_id), int(r.active_hens)) for r in active_rows]
        total_hens = sum(hens for _, hens in weighted)
    else:
        weighted, total_hens = [], 0

    for date, entry in feed_by_date.items():
        total_kg = entry["total_kg"]
        if total_kg <= 0:
            continue
        if not weighted or total_hens <= 0:
            stats["skips"].append(f"{date}: no active cages with hens, feed skipped")
            continue

        key = entry["crude_protein"]
        batch_id = ensure_feed_batch_id(conn, key, date, feed_batch_cache, stats)
        if batch_id is None:
            stats["skips"].append(f"{date}: no feed_batches available and fallback creation failed, feed skipped")
            continue

        # Largest-remainder to the cent, mirroring distributeFarmFeedEntry().
        total_cents = int(math.floor(total_kg * 100 + 0.5))
        shares = []
        for cage_id, hens in weighted:
            cent_value = (hens / total_hens) * total_kg * 100
            shares.append([cage_id, int(cent_value), cent_value - int(cent_value)])
        applied_cents = sum(s[1] for s in shares)
        remaining_cents = max(0, total_cents - applied_cents)
        order = sorted(range(len(shares)), key=lambda i: shares[i][2], reverse=True)
        for idx in order[:remaining_cents]:
            shares[idx][1] += 1

        for cage_id, base_cents, _ in shares:
            kg = base_cents / 100
            existing = conn.execute(
                text(
                    """
                    SELECT id FROM feed_consumption_logs
                     WHERE cage_id = :cid AND log_date = :date
                     ORDER BY id LIMIT 1
                    """
                ),
                {"cid": cage_id, "date": date},
            ).fetchone()
            if existing:
                conn.execute(
                    text(
                        """
                        UPDATE feed_consumption_logs
                           SET feed_batch_id = :bid, feed_consumed_kg = :kg,
                               source = 'direct', farm_feed_entry_id = NULL
                         WHERE id = :id
                        """
                    ),
                    {"bid": batch_id, "kg": kg, "id": existing[0]},
                )
            else:
                conn.execute(
                    text(
                        """
                        INSERT INTO feed_consumption_logs
                            (cage_id, feed_batch_id, log_date, feed_consumed_kg, source)
                        VALUES
                            (:cid, :bid, :date, :kg, 'direct')
                        """
                    ),
                    {"cid": cage_id, "bid": batch_id, "date": date, "kg": kg},
                )
            stats["feed"] += 1

    return stats


def import_forecast_input(file_path: str, source_file: str | None = None) -> int:
    df, raw_row_count, invalid_rows, _, _ = parse_forecast_file(file_path)

    if df.empty:
        raise ValueError("No valid rows to import after parsing.")

    # Keep only columns expected by the INSERT, plus source_file.
    db_columns = set(COLUMN_MAP.values())
    for col in db_columns:
        if col not in df.columns:
            df[col] = None

    df["source_file"] = source_file or os.path.basename(file_path)
    source_name = source_file or os.path.basename(file_path)

# Filter to only the columns the import knows about.
    insert_columns = db_columns | {"source_file"}

    records = []
    for _, row in df.iterrows():
        record = {k: clean_value(v) for k, v in row.items() if k in insert_columns}
        records.append(record)

    engine = build_engine()
    native_stats = None

    # One transaction for all native writes.
    with engine.begin() as conn:
        cage_structure = load_cage_structure(conn)
        native_stats = write_native_tables(conn, records, cage_structure, source_name)

    print(f"Imported {len(records)} row(s) into native production tables.")
    print(
        "Native tables: "
        f"{native_stats['production']} production log(s), "
        f"{native_stats['egg_size']} egg size log(s), "
        f"{native_stats['env']} environmental log(s), "
        f"{native_stats['feed']} feed log(s), "
        f"{native_stats['mortality']} mortality log(s)."
    )
    if native_stats.get("feed_batches_created"):
        print(f"Auto-created {native_stats['feed_batches_created']} feed batch(es) for imported feed (no feed batches existed).")
    if native_stats["skips"]:
        for skip in native_stats["skips"]:
            print(f"  skip: {skip}", file=sys.stderr)

    return len(records)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Import a forecast input Excel sheet into the app's native production tables."
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
        try:
            result = preview_forecast_input(args.file)
            print(json.dumps(result))
        except ValueError as e:
            print(json.dumps({"error": str(e)}))
            raise SystemExit(1)
    else:
        import_forecast_input(args.file, source_file=args.source_file)
