# DHT22 calibration pipeline (Table IX sensor-accuracy leg)

One handheld DHT22 module is bench-compared against a calibrated reference
instrument across 3 location campaigns (Unit 1/2/3) × 3 sessions
(Morning/Midday/Afternoon) × 10 readings = 90 paired readings. Reference
readings are taken by hand — nothing here simulates them.

## Workflow

1. **Fix the schedule.** Send 9 `--block` specs (unit, session, start,
   interval-min). The module must stream to the Pi bridge during every block
   (register its serial first if it is not DHT22-001).
2. **Generate the pre-filled template** (runs on the Pi or anywhere with DB
   reachability; password via `LAYRATE_DB_PASSWORD`, never committed):
   `python3 make_template.py --serial DHT22-001 --per-block 10 --block "Unit 1,Morning,2026-09-20 06:00,15" ... --out template.csv`
3. **Measure.** Fill the Reference columns by hand from the calibrated instrument.
4. **Compute:** `python3 compute_metrics.py --in template_filled.csv --out results.md`
   → markdown table (6 unit rows + 2 Overall rows) + tolerance verdicts.

`fetch_unit_readings.py` is the shared matcher (also usable standalone to
backfill a hand-timed sheet). Requires `pymysql` (`pip install pymysql`).

## Technical notes

- Timestamps are Asia/Manila wall time (`YYYY-MM-DD HH:MM`); the Pi stores
  UTC, converted explicitly in code. Matches accept the nearest reading
  within `--tolerance-sec` (default 120 s); anything wider stays blank with
  a warning (bridge gaps, not silent interpolation).
- Table V bands are hardcoded in `compute_metrics.py` exactly as supplied
  (ladders, 2-decimal reporting).
- Manufacturer yardsticks for the discussion section: ±0.5 °C temperature;
  ±2 %RH strict / ±5 %RH lenient humidity.
