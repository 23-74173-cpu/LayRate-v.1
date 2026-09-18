#!/usr/bin/env python3
"""Fill the Jun 26 - Aug 27 2026 mock-data gap from production_data_2026-09-18-1.xlsx.

Scope (per user choice "fill gaps only"):
  * Rows BEFORE 2026-06-26 are left completely untouched.
  * For each xlsx row with Date >= 2026-06-26 and Cage in (CAGE-A/B/C):
      - production_logs : 15 per-slot rows (ordered by slot_number) that sum to
        the xlsx Hen_Count / Egg_Count, is_demo=0, logged_via='unknown'.
        Existing rows (stale is_demo=1 demo data) are updated in place so
        egg_size_logs FKs stay intact.
      - egg_size_logs   : exactly one 'unsorted' row per production log with
        count = egg_count (mirrors the Mar-Jun real rows).
      - environmental_logs : one real (is_demo=0) noon reading per cage/day.
        Stale is_demo=1 demo rows in the window are removed (they are excluded
        from forecasts but pollute live "latest reading" views).
      - feed_consumption_logs : one row per cage/day with the xlsx
        Feed_Consumed_kg, linked to a 17.0% CP batch (created if missing).
      - mortality_logs  : one row (reason Unknown) when xlsx Mortality_Count>0,
        created or updated to the xlsx count. Stale A/B/C rows on dates where
        the xlsx says 0 are removed. CAGE-D/T rows are never touched.
  * Hens / cages / slots are NOT modified (breed + flock-age columns on export
    keep coming from the hens table).

Usage:
    python3 scripts/import_xlsx_mock_data.py --dry-run
    python3 scripts/import_xlsx_mock_data.py

DB credentials are read from the project .env. Requires: openpyxl, pymysql.
"""
import argparse
import datetime
import os
import sys

import openpyxl
import pymysql

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT_XLSX = os.path.join(ROOT, "production_data_2026-09-18-1.xlsx")
CUTOFF = "2026-06-26"  # only touch dates >= this; earlier real data stays as-is
END = "2026-08-27"  # ... and <= this; recent demo rows after this stay as-is
CAGES = ("CAGE-A", "CAGE-B", "CAGE-C")
SLOTS_PER_CAGE = 15
FEED_BATCH_CODE = "F-XLSX-17"


def load_env(path):
    env = {}
    with open(path) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            v = v.strip().strip('"').strip("'")
            env[k.strip()] = v
    return env


def iso_date(v):
    if isinstance(v, (datetime.datetime, datetime.date)):
        return v.strftime("%Y-%m-%d")
    return str(v).strip()[:10]


def distribute(total, n):
    """Split total across n slots: first (total % n) slots get one extra."""
    total = int(total)
    base, rem = divmod(total, n)
    return [base + 1 if i < rem else base for i in range(n)]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--xlsx", default=DEFAULT_XLSX)
    args = ap.parse_args()

    env = load_env(os.path.join(ROOT, ".env"))
    conn = pymysql.connect(
        host=env.get("DB_HOST", "127.0.0.1"),
        port=int(env.get("DB_PORT", 3306)),
        user=env.get("DB_USERNAME", "root"),
        password=env.get("DB_PASSWORD", ""),
        database=env.get("DB_DATABASE", "layrate"),
        autocommit=False,
    )
    stats = {k: 0 for k in (
        "prod_insert", "prod_update", "size_write",
        "env_insert", "env_update", "env_demo_deleted",
        "feed_insert", "feed_update",
        "mort_insert", "mort_update", "mort_deleted",
    )}

    try:
        wb = openpyxl.load_workbook(args.xlsx, data_only=True)
        ws = wb["Production Data"]
        rows = list(ws.iter_rows(values_only=True))
        assert rows[0][0] == "Date" and rows[0][1] == "Cage_Code", "unexpected header"
        data = [r for r in rows[1:] if r[0] is not None]
        data = [r for r in data if iso_date(r[0]) >= CUTOFF and r[1] in CAGES]
        by_key = {(iso_date(r[0]), r[1]): r for r in data}
        dates = sorted({k[0] for k in by_key})
        print(f"xlsx rows in scope (date >= {CUTOFF}): {len(by_key)} "
              f"({len(dates)} days: {dates[0]}..{dates[-1]})")

        cur = conn.cursor()
        cur.execute("SELECT id, cage_code FROM cages WHERE cage_code IN ('CAGE-A','CAGE-B','CAGE-C')")
        cage_ids = {code: cid for cid, code in cur.fetchall()}
        assert set(cage_ids) == set(CAGES), f"cages missing: {cage_ids}"
        slots = {}
        for code, cid in cage_ids.items():
            cur.execute("SELECT id FROM cage_slots WHERE cage_id=%s ORDER BY slot_number", (cid,))
            ids = [r[0] for r in cur.fetchall()]
            assert len(ids) == SLOTS_PER_CAGE, f"{code} has {len(ids)} slots"
            slots[code] = ids

        # Feed batch with exactly 17.0% CP to match the xlsx reference.
        cur.execute("SELECT id FROM feed_batches WHERE crude_protein=17.00 LIMIT 1")
        row = cur.fetchone()
        if row:
            batch_id = row[0]
        else:
            if args.dry_run:
                batch_id = -1
                print("would create feed batch F-XLSX-17 (17.0% CP)")
            else:
                cur.execute(
                    "INSERT INTO feed_batches (batch_code, brand, crude_protein,"
                    " date_received, notes) VALUES (%s,%s,%s,%s,%s)",
                    (FEED_BATCH_CODE, "Reference", 17.00, "2026-03-23",
                     "Created by scripts/import_xlsx_mock_data.py to match "
                     "production_data_2026-09-18-1.xlsx"),
                )
                batch_id = cur.lastrowid
                print(f"created feed batch {FEED_BATCH_CODE} id={batch_id}")

        # Remove stale demo env rows in the window (real rows upserted below).
        cur.execute(
            "SELECT COUNT(*) FROM environmental_logs WHERE cage_id IN "
            "(SELECT id FROM cages WHERE cage_code IN ('CAGE-A','CAGE-B','CAGE-C'))"
            " AND DATE(recorded_at) >= %s AND DATE(recorded_at) <= %s AND is_demo=1",
            (CUTOFF, END))
        stats["env_demo_deleted"] = cur.fetchone()[0]
        if not args.dry_run:
            cur.execute(
                "DELETE FROM environmental_logs WHERE cage_id IN (SELECT id FROM cages"
                " WHERE cage_code IN ('CAGE-A','CAGE-B','CAGE-C'))"
                " AND DATE(recorded_at) >= %s AND DATE(recorded_at) <= %s AND is_demo=1",
                (CUTOFF, END))

        for (date, code), r in sorted(by_key.items()):
            hens_total = int(r[4] or 0)
            eggs_total = int(r[5] or 0)
            temp = float(r[6])
            hum = float(r[7])
            feed_kg = float(r[9] or 0)
            mort = int(r[10] or 0)
            cid = cage_ids[code]
            hens_split = distribute(hens_total, SLOTS_PER_CAGE)
            eggs_split = distribute(eggs_total, SLOTS_PER_CAGE)

            for slot_id, h, e in zip(slots[code], hens_split, eggs_split):
                hdep = round(e / h * 100, 2) if h else 0.0
                cur.execute(
                    "SELECT id FROM production_logs WHERE cage_slot_id=%s AND log_date=%s",
                    (slot_id, date))
                prow = cur.fetchone()
                if prow:
                    stats["prod_update"] += 1
                    if not args.dry_run:
                        cur.execute(
                            "UPDATE production_logs SET egg_count=%s, hen_count=%s,"
                            " hdep=%s, is_demo=0, logged_via='unknown',"
                            " recorded_by=NULL, notes=NULL"
                            " WHERE id=%s", (e, h, hdep, prow[0]))
                        cur.execute("DELETE FROM egg_size_logs WHERE production_log_id=%s", (prow[0],))
                        if e > 0:
                            cur.execute(
                                "INSERT INTO egg_size_logs (production_log_id, egg_size, `count`)"
                                " VALUES (%s,'unsorted',%s)", (prow[0], e))
                            stats["size_write"] += 1
                else:
                    stats["prod_insert"] += 1
                    if not args.dry_run:
                        cur.execute(
                            "INSERT INTO production_logs (cage_slot_id, log_date, egg_count,"
                            " hen_count, hdep, is_demo, logged_via)"
                            " VALUES (%s,%s,%s,%s,%s,0,'unknown')",
                            (slot_id, date, e, h, hdep))
                        pid = cur.lastrowid
                        if e > 0:
                            cur.execute(
                                "INSERT INTO egg_size_logs (production_log_id, egg_size, `count`)"
                                " VALUES (%s,'unsorted',%s)", (pid, e))
                            stats["size_write"] += 1
                if args.dry_run and e > 0:
                    stats["size_write"] += 1

            recorded_at = f"{date} 12:00:00"
            cur.execute(
                "SELECT id, temperature_c, humidity_pct, is_demo FROM environmental_logs"
                " WHERE cage_id=%s AND recorded_at=%s", (cid, recorded_at))
            erow = cur.fetchone()
            if erow:
                stats["env_update"] += 1
                if not args.dry_run and (
                        float(erow[1]) != temp or float(erow[2]) != hum or erow[3] != 0):
                    cur.execute(
                        "UPDATE environmental_logs SET temperature_c=%s, humidity_pct=%s,"
                        " is_demo=0, is_override=0 WHERE id=%s", (temp, hum, erow[0]))
            else:
                stats["env_insert"] += 1
                if not args.dry_run:
                    cur.execute(
                        "INSERT INTO environmental_logs (cage_id, recorded_at, temperature_c,"
                        " humidity_pct, is_override, is_demo)"
                        " VALUES (%s,%s,%s,%s,0,0)", (cid, recorded_at, temp, hum))

            cur.execute(
                "SELECT id, feed_consumed_kg, feed_batch_id FROM feed_consumption_logs"
                " WHERE cage_id=%s AND log_date=%s", (cid, date))
            frow = cur.fetchone()
            if frow:
                stats["feed_update"] += 1
                if not args.dry_run and (
                        float(frow[1]) != feed_kg or frow[2] != batch_id):
                    cur.execute(
                        "UPDATE feed_consumption_logs SET feed_consumed_kg=%s,"
                        " feed_batch_id=%s WHERE id=%s", (feed_kg, batch_id, frow[0]))
            else:
                stats["feed_insert"] += 1
                if not args.dry_run:
                    cur.execute(
                        "INSERT INTO feed_consumption_logs (cage_id, feed_batch_id, log_date,"
                        " feed_consumed_kg, source) VALUES (%s,%s,%s,%s,'direct')",
                        (cid, batch_id, date, feed_kg))

            cur.execute(
                "SELECT id, `count` FROM mortality_logs WHERE cage_id=%s AND log_date=%s",
                (cid, date))
            mrows = cur.fetchall()
            if mort > 0:
                if mrows:
                    stats["mort_update"] += 1
                    if not args.dry_run and int(mrows[0][1]) != mort:
                        cur.execute("UPDATE mortality_logs SET `count`=%s WHERE id=%s",
                                    (mort, mrows[0][0]))
                else:
                    stats["mort_insert"] += 1
                    if not args.dry_run:
                        cur.execute(
                            "INSERT INTO mortality_logs (cage_id, log_date, `count`, reason)"
                            " VALUES (%s,%s,%s,'Unknown')", (cid, date, mort))
            elif mrows:
                stats["mort_deleted"] += len(mrows)
                if not args.dry_run:
                    cur.execute("DELETE FROM mortality_logs WHERE cage_id=%s AND log_date=%s",
                                (cid, date))

        if args.dry_run:
            conn.rollback()
            print("DRY RUN - rolled back. Planned changes:")
        else:
            conn.commit()
            print("COMMITTED. Changes applied:")
        for k, v in stats.items():
            print(f"  {k}: {v}")
    finally:
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
