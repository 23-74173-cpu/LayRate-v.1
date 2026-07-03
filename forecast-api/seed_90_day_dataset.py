"""Seed a synthetic 90-day historical dataset for LayRate forecasting.

Backfills production_logs, environmental_logs, and feed_consumption_logs for
all cages so that ForecastingV5.py meets its MIN_REQUIRED_RECORDS threshold.
Existing recent records are preserved.
"""
import os
from datetime import date, timedelta

import numpy as np
import pymysql

DB_CONFIG = {
    "host": os.getenv("DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("DB_PORT", "3307")),
    "user": os.getenv("DB_USERNAME", "root"),
    "password": os.getenv("DB_PASSWORD", "root"),
    "database": os.getenv("DB_DATABASE", "layrate"),
}

DAYS_TO_SEED = 90
RECORDED_BY_ID = 1  # First seeded user is typically the admin.
FEED_BATCH_ID = 1  # Assumes at least one feed batch exists.


def connect():
    return pymysql.connect(**DB_CONFIG)


def fetch_cages_and_slots(conn):
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT c.id, c.cage_code, cs.id AS cage_slot_id, cs.slot_number
            FROM cages c
            JOIN cage_slots cs ON cs.cage_id = c.id
            ORDER BY c.cage_code, cs.slot_number
            """
        )
        rows = cur.fetchall()

    cages = {}
    for cage_id, cage_code, slot_id, slot_number in rows:
        cages.setdefault(cage_code, {"cage_id": cage_id, "slots": []})
        cages[cage_code]["slots"].append((slot_id, slot_number))
    return cages


def fetch_earliest_log_date(conn):
    with conn.cursor() as cur:
        cur.execute("SELECT MIN(log_date) FROM production_logs")
        result = cur.fetchone()
    return result[0]


def generate_daily_egg_counts(base_rate: float, days: int, seed: int = 42):
    rng = np.random.default_rng(seed)
    trend = np.linspace(0, -0.05, days)  # Slight decline over time.
    seasonal = 0.08 * np.sin(np.linspace(0, 4 * np.pi, days))
    noise = rng.normal(0, 0.06, days)
    rates = np.clip(base_rate + trend + seasonal + noise, 0.5, 0.98)
    return rates


def seed_production_logs(conn, cages, start_date, days, recorded_by):
    counts = {}
    with conn.cursor() as cur:
        for cage_code, info in cages.items():
            base_rate = {"CAGE-A": 0.88, "CAGE-B": 0.85, "CAGE-C": 0.82, "CAGE-D": 0.80}.get(
                cage_code, 0.85
            )
            rates = generate_daily_egg_counts(base_rate, days, seed=hash(cage_code) % 10000)
            counts[cage_code] = []
            for day_offset in range(days):
                log_date = start_date + timedelta(days=day_offset)
                hens_per_slot = 4
                for slot_idx, (slot_id, _) in enumerate(info["slots"]):
                    # Vary hens slightly for realism; keep most slots full.
                    hens = hens_per_slot if slot_idx > 0 else 3
                    eggs = int(round(hens * rates[day_offset]))
                    counts[cage_code].append(
                        (slot_id, log_date, eggs, hens, recorded_by)
                    )

        sql = """
            INSERT INTO production_logs (cage_slot_id, log_date, egg_count, hen_count, recorded_by)
            VALUES (%s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE egg_count = VALUES(egg_count), hen_count = VALUES(hen_count)
        """
        for cage_code, records in counts.items():
            cur.executemany(sql, records)
            print(f"Seeded {len(records)} production log rows for {cage_code}.")
    conn.commit()


def seed_environmental_logs(conn, cages, start_date, days):
    rng = np.random.default_rng(123)
    records = []
    for cage_code, info in cages.items():
        base_temp = {"CAGE-A": 25.5, "CAGE-B": 26.0, "CAGE-C": 25.0, "CAGE-D": 26.5}.get(
            cage_code, 25.5
        )
        for day_offset in range(days):
            log_date = start_date + timedelta(days=day_offset)
            recorded_at = f"{log_date} 12:00:00"
            temp = round(base_temp + rng.normal(0, 1.5), 2)
            humidity = round(60 + rng.normal(0, 7), 2)
            records.append((info["cage_id"], recorded_at, temp, humidity))

    with conn.cursor() as cur:
        cur.executemany(
            """
            INSERT INTO environmental_logs (cage_id, recorded_at, temperature_c, humidity_pct)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE temperature_c = VALUES(temperature_c), humidity_pct = VALUES(humidity_pct)
            """,
            records,
        )
    conn.commit()
    print(f"Seeded {len(records)} environmental log rows.")


def seed_feed_consumption(conn, cages, start_date, days, feed_batch_id, recorded_by):
    rng = np.random.default_rng(456)
    records = []
    for cage_code, info in cages.items():
        base_feed = {"CAGE-A": 32.0, "CAGE-B": 30.0, "CAGE-C": 28.0, "CAGE-D": 29.0}.get(
            cage_code, 30.0
        )
        for day_offset in range(days):
            log_date = start_date + timedelta(days=day_offset)
            feed = round(base_feed + rng.normal(0, 2), 2)
            records.append((info["cage_id"], feed_batch_id, log_date, feed, recorded_by))

    with conn.cursor() as cur:
        cur.executemany(
            """
            INSERT INTO feed_consumption_logs (cage_id, feed_batch_id, log_date, feed_consumed_kg, recorded_by)
            VALUES (%s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE feed_consumed_kg = VALUES(feed_consumed_kg)
            """,
            records,
        )
    conn.commit()
    print(f"Seeded {len(records)} feed consumption rows.")


def main():
    conn = connect()
    try:
        cages = fetch_cages_and_slots(conn)
        earliest = fetch_earliest_log_date(conn)

        if earliest is None:
            # No existing data; seed ending yesterday.
            end_date = date.today() - timedelta(days=1)
        else:
            # Backfill before existing data.
            end_date = earliest - timedelta(days=1)

        start_date = end_date - timedelta(days=DAYS_TO_SEED - 1)
        print(f"Seeding data from {start_date} to {end_date} for {len(cages)} cages.")

        seed_production_logs(conn, cages, start_date, DAYS_TO_SEED, RECORDED_BY_ID)
        seed_environmental_logs(conn, cages, start_date, DAYS_TO_SEED)
        seed_feed_consumption(conn, cages, start_date, DAYS_TO_SEED, FEED_BATCH_ID, RECORDED_BY_ID)

        # Print summary.
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT c.cage_code, COUNT(DISTINCT DATE(pl.log_date)) AS days
                FROM production_logs pl
                JOIN cage_slots cs ON cs.id = pl.cage_slot_id
                JOIN cages c ON c.id = cs.cage_id
                GROUP BY c.cage_code
                """
            )
            print("\nProduction log days per cage:")
            for row in cur.fetchall():
                print(f"  {row[0]}: {row[1]} days")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
