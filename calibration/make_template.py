#!/usr/bin/env python3
"""Generate the pre-filled DHT22 calibration data-entry template.

Each (unit, session) block contributes --per-block readings starting at the
given Manila start time, spaced by the block interval:

    python3 make_template.py --serial DHT22-001 --per-block 10 \\
        --block "Unit 1,Morning,2026-09-20 06:00,15" \\
        --block "Unit 1,Midday,2026-09-20 11:00,15" \\
        --block "Unit 1,Afternoon,2026-09-20 16:00,15" \\
        --block "Unit 2,Morning,2026-09-21 06:00,15" ... \\
        --out dht22_calibration_template.csv

Units are location campaigns of one handheld module, so blocks for different
units normally sit on different dates/times — put the real measurement plan
in the --block list. Sensor columns are filled from the Pi DB via
fetch_unit_readings (same serial for every block unless the module is
re-registered mid-campaign); reference columns stay blank for hand entry.
"""
import argparse
import csv
import sys

from fetch_unit_readings import (
    TS_FMT, add_db_args, db_connect, match_timestamps, parse_manila,
    resolve_cage,
)

HEADERS = ["Sensor Unit", "Session", "Timestamp",
           "DHT22 Temp (°C)", "Reference Temp (°C)",
           "DHT22 Humidity (%RH)", "Reference Humidity (%RH)"]


def parse_block(spec, per_block):
    try:
        unit, session, start, interval = [p.strip() for p in spec.split(",", 3)]
        base = parse_manila(start)  # validates format now, fails fast
        step = int(interval)
    except (ValueError, AttributeError) as exc:
        raise RuntimeError(
            f"Bad --block {spec!r}; want \"UNIT,SESSION,YYYY-MM-DD HH:MM,INTERVAL_MIN\": {exc}")
    from datetime import timedelta
    return [(unit, session, base + timedelta(minutes=step * k)) for k in range(per_block)]


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--serial", default="DHT22-001")
    parser.add_argument("--per-block", type=int, default=10)
    parser.add_argument("--block", action="append", default=[],
                        help='Repeatable: "UNIT,SESSION,YYYY-MM-DD HH:MM,INTERVAL_MIN"')
    parser.add_argument("--tolerance-sec", type=int, default=120)
    parser.add_argument("--out", default="dht22_calibration_template.csv")
    add_db_args(parser)
    args = parser.parse_args(argv)

    if not args.block:
        raise RuntimeError("Pass at least one --block (see --help).")

    plan = []
    for spec in args.block:
        plan.extend(parse_block(spec, args.per_block))
    wanted = [w for _, _, w in plan]

    con = db_connect(args)
    try:
        cage_id = resolve_cage(con, args.serial)
        matched = match_timestamps(con, cage_id, wanted, args.tolerance_sec)
    finally:
        con.close()

    rows, missed = [], 0
    for (unit, session, w), _ in zip(plan, wanted):
        hit = matched.get(w)
        if hit is None:
            missed += 1
        rows.append({
            "Sensor Unit": unit,
            "Session": session,
            "Timestamp": w.strftime(TS_FMT),
            "DHT22 Temp (°C)": f"{float(hit['temperature_c']):.1f}" if hit else "",
            "Reference Temp (°C)": "",
            "DHT22 Humidity (%RH)": f"{float(hit['humidity_pct']):.1f}" if hit else "",
            "Reference Humidity (%RH)": "",
        })

    with open(args.out, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=HEADERS)
        writer.writeheader()
        writer.writerows(rows)

    print(f"serial={args.serial} rows={len(rows)} matched={len(rows) - missed} "
          f"unmatched={missed} -> {args.out}")
    if missed:
        print("WARNING: some sensor cells left blank (gap > tolerance); "
              "re-run fetch_unit_readings.py once the bridge backfills, or "
              "narrow the tolerance.", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
