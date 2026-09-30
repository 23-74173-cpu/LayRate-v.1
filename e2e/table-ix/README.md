# Table IX automated functional benchmark — run contract

24 cases, 8 features × (primary / boundary / error). Black-box against the
**dev database** (`layrate` @ 127.0.0.1) — no resets, no factories — because
cases assert on device-originated Mar–Jun 2026 rows and dated forecast runs.

## Run order (enforced by filename prefixes — do not reorder)

Playwright runs spec files alphabetically. The order below is load-bearing:
later files write rows that earlier files' assertions forbid.

1. `01-f1-forecast` — read-only page loads plus server-rejected POSTs
   (no ForecastRun is created by any F1 case).
2. `02-f3-dashboard` — read-only. Must run BEFORE any spec that writes
   `environmental_logs`/`sensor_occupancy_readings`: a single fresh sensor
   row puts CAGE-T back into the live cards and breaks the degraded-state
   assertions (TC-07/08).
3. `03-f2-sensor` — WRITES benchmark rows (fixed `recorded_at` 2026-01-15,
   zero-egg IR leg; see the spec header). Run `04-cleanup.sql` afterwards.
4. `04-f4-reports` — read-only (explicit ranges; immune to Jan-15 rows).
5. `05-f5-alerts` — threshold changes self-restore in-test; the breach case
   writes one Jan-15 env row + one alert (covered by terminal cleanup).
6. `06-f6-offline` — read-only (+ route abortion, no writes).
7. `07-f7-rbac` — role flips restore in-test (promote-then-demote the
   operator account itself); zero account residue.
8. F8 (`serial-bridge/test_table_ix_f8.py`, separate Python runner — see
   below) writes Jan-15 rows + sensor alerts. ALWAYS LAST + terminal cleanup.
4. `04+` (to come: f4 reports read-only; f5 alerts mutates thresholds —
   snapshot/restore in-test; f6 offline read-only; f7 RBAC creates/demotes
   users — restore in-test; f8 bridge script writes live rows — always LAST).

## Prerequisites

- App serving the dev DB (`npx playwright test` boots `artisan serve`
  automatically, reusing an existing server when present).
- Seed users present: `admin@layrate.local` / `operator@layrate.local`
  (password `password`).
- F1 pins: `EXPECTED_BADGES` in `01-f1-forecast.spec.js` must match the
  CURRENT reporting-date farm run (Manila-midnight boundary). After a date
  roll, regenerate (farm, horizon 7) and re-pin — never loosen to fuzzy
  assertions.
- F2 device key: `LAYRATE_DEVICE_KEY` env, defaults to the
  `serial-bridge/sensors.json` dev key.

## Terminal cleanup (after every full run)

```sql
DELETE FROM environmental_logs WHERE cage_id = 5 AND DATE(recorded_at) = '2026-01-15';
DELETE FROM production_logs WHERE cage_slot_id = 61 AND log_date = '2026-01-15';
DELETE FROM sensor_occupancy_readings WHERE hardware_item_id = 5 AND DATE(recorded_at) = '2026-01-15';
DELETE FROM alerts WHERE cage_id = 5 AND alert_day >= CURRENT_DATE - INTERVAL 1 DAY
  AND alert_type IN ('egg_over_hen', 'occupancy_mismatch', 'sensor_reset',
    'temperature_high', 'temperature_low', 'humidity_high', 'humidity_low')
  AND is_read = 0;
UPDATE hardware_items SET health_state = 'unknown', last_valid_reading_at = NULL,
  consecutive_same_readings = 0 WHERE id IN (5, 6);
```

## F8 runner (separate — Playwright cannot touch a UART)

```sh
cd serial-bridge
.venv/bin/python -m unittest test_table_ix_f8 -v
```

Drives canned Arduino bytes through the real `BlockParser`/`build_payload`
(see the file header). Same Jan-15 hygiene + terminal cleanup as above
(sensor_reset alerts included in the alert predicate).

Committed baselines every full run must leave behind: thresholds
`temp 18/30, hum 40/70` (F5 restores these in-test — verify with a SELECT
through the app stack, don't assume); current-date farm forecast rows intact;
alerts table back to its 2 pre-existing rows; users table at 2 rows
(admin + operator).

## State resets MUST go through the app stack (hard lesson)

`Setting::thresholds()` is cached for 3600 s in the database cache store.
Raw `UPDATE settings ...` via mysql bypasses `Setting::set()` and does NOT
clear that cache — the app then serves stale values for up to an hour while
direct SELECTs show the new ones, which makes tests snapshot/restore stale
state and pass falsely. Always reset via tinker/`Setting::set()` (which
clears the cache), e.g. `php /tmp/reset_thr.php`, or
`Cache::forget('settings:thresholds')` after a raw write. Same caution
applies to any other cached model — check for `Cache::remember` before
touching its table directly.
