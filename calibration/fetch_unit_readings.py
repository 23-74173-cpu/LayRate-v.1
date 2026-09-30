#!/usr/bin/env python3
"""Fetch DHT22 readings nearest to wanted timestamps (shared matcher).

The handheld calibration module streams through the Pi bridge exactly like
any DHT22: hardware_items (by serial) -> cage_id -> environmental_logs.
DB stores UTC wall time; user timestamps are Asia/Manila wall time, so the
matcher converts explicitly (see TZ note in README.md).

Used two ways:
  1. Imported by make_template.py (pre-fill path).
  2. Standalone: fill the DHT22 columns of a hand-filled CSV that already
     has Timestamp cells (field path):
       python3 fetch_unit_readings.py --serial DHT22-001 \\
           --in template_filled.csv --out template_sensorfilled.csv
"""
import argparse
import csv
import os
import sys
from datetime import datetime, timedelta, timezone

import pymysql

try:
    from zoneinfo import ZoneInfo
except ImportError:  # Python < 3.9 (Pi runs 3.11, this is belt-and-braces)
    ZoneInfo = None

MANILA = ZoneInfo("Asia/Manila") if ZoneInfo else None
TS_FMT = "%Y-%m-%d %H:%M"


def parse_manila(ts):
    dt = datetime.strptime(ts.strip(), TS_FMT)
    if MANILA is None:
        raise RuntimeError("zoneinfo unavailable; need Python >= 3.9")
    return dt.replace(tzinfo=MANILA)


def db_connect(args):
    password = args.db_password or os.getenv("LAYRATE_DB_PASSWORD")
    if not password:
        raise RuntimeError(
            "DB password required: --db-password or LAYRATE_DB_PASSWORD env")
    return pymysql.connect(
        host=args.db_host, port=args.db_port, user=args.db_user,
        password=password, database=args.db_name,
        cursorclass=pymysql.cursors.DictCursor)


def add_db_args(parser):
    parser.add_argument("--db-host", default="127.0.0.1")
    parser.add_argument("--db-port", type=int, default=3307)
    parser.add_argument("--db-user", default="layrate")
    parser.add_argument("--db-name", default="layrate")
    parser.add_argument("--db-password", default=None,
                        help="Prefer LAYRATE_DB_PASSWORD env; never commit it.")


def resolve_cage(con, serial):
    with con.cursor() as cur:
        cur.execute(
            "SELECT cage_id FROM hardware_items "
            "WHERE serial_number = %s AND device_type = 'DHT22'", (serial,))
        row = cur.fetchone()
    if not row or row["cage_id"] is None:
        raise RuntimeError(
            f"Serial '{serial}' is not a DHT22 mapped to a cage. "
            "Check hardware_items (is the test module streaming yet?) or pass --serial.")
    return row["cage_id"]


def fetch_window(con, cage_id, start_utc, end_utc):
    """All readings for the cage in [start, end] (UTC datetimes)."""
    with con.cursor() as cur:
        cur.execute(
            "SELECT recorded_at, temperature_c, humidity_pct "
            "FROM environmental_logs "
            "WHERE cage_id = %s AND recorded_at BETWEEN %s AND %s "
            "ORDER BY recorded_at",
            (cage_id, start_utc.strftime("%Y-%m-%d %H:%M:%S"),
             end_utc.strftime("%Y-%m-%d %H:%M:%S")))
        return cur.fetchall()


def nearest(rows, target_utc, tolerance_sec):
    """Closest row to target_utc within tolerance; None when the window gaps."""
    best, best_dt = None, None
    for r in rows:
        recorded = r["recorded_at"]
        if recorded.tzinfo is None:
            recorded = recorded.replace(tzinfo=timezone.utc)
        dt = abs((recorded - target_utc).total_seconds())
        if dt <= tolerance_sec and (best_dt is None or dt < best_dt):
            best, best_dt = r, dt
    return best


def match_timestamps(con, cage_id, wanted_manila, tolerance_sec=120):
    """wanted_manila: list of Manila datetimes -> {wanted: row-or-None}."""
    if not wanted_manila:
        return {}
    lo = min(wanted_manila).astimezone(timezone.utc) - timedelta(minutes=15)
    hi = max(wanted_manila).astimezone(timezone.utc) + timedelta(minutes=15)
    rows = fetch_window(con, cage_id, lo, hi)
    return {w: nearest(rows, w.astimezone(timezone.utc), tolerance_sec)
            for w in wanted_manila}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--serial", default="DHT22-001",
                        help="Test module serial (must stream to the Pi during sessions)")
    parser.add_argument("--in", dest="input", required=True,
                        help="CSV with a Timestamp column (YYYY-MM-DD HH:MM, Manila)")
    parser.add_argument("--out", dest="output", required=True)
    parser.add_argument("--tolerance-sec", type=int, default=120,
                        help="Max |reading - wanted| to accept a match")
    add_db_args(parser)
    args = parser.parse_args(argv)

    with open(args.input, newline="", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        if "Timestamp" not in (reader.fieldnames or []):
            raise RuntimeError("Input CSV needs a 'Timestamp' column")
        rows = list(reader)
        fields = reader.fieldnames

    wanted = [parse_manila(r["Timestamp"]) for r in rows if r["Timestamp"].strip()]
    con = db_connect(args)
    try:
        cage_id = resolve_cage(con, args.serial)
        matched = match_timestamps(con, cage_id, wanted, args.tolerance_sec)
    finally:
        con.close()

    missed = 0
    for r, w in zip(rows, [parse_manila(x["Timestamp"]) for x in rows if x["Timestamp"].strip()]):
        hit = matched.get(w)
        if hit is None:
            missed += 1
            continue
        r["DHT22 Temp (°C)"] = f"{float(hit['temperature_c']):.1f}"
        r["DHT22 Humidity (%RH)"] = f"{float(hit['humidity_pct']):.1f}"

    with open(args.output, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)

    print(f"serial={args.serial} cage={cage_id} rows={len(rows)} "
          f"matched={len(rows) - missed} unmatched={missed} -> {args.output}")
    if missed:
        print("WARNING: unmatched timestamps left blank (gap > tolerance).",
              file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
