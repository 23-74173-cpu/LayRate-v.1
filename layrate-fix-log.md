# LayRate — Fix Log

Running log of work completed from `layrate-audit-backlog.md`. OpenCode: append a new
dated section per prompt as you finish it — don't overwrite prior entries. Keep each
entry to: what was found, what changed (files + line ranges), how it was verified,
and any open questions for a follow-up pass.

---

## Prompt 1 — Fix dead `stock_depletion` alert branch

**Status:** ✅ Completed (2026-08-22)

**Root cause:** `PreOrderController::index()` clamped `available = max(0, $pool)`
before passing it to `runDepletionCheck`, which checked `available < 0` —
structurally unreachable.

**Fix:** Alert now keys off `$data['deficit'] > 0` (already computed in `index()`),
not the clamped `available`. `available` kept clamped for UI (`eggs/pre-orders.blade.php:32`
renders it, must stay non-negative). Bonus fix: old code's `abs($data['available'])`
would have produced `shortfall = 0` even if reachable — now uses the real deficit.

**Verified:** Manual test plan executed — pending-order deficit scenario confirmed
one `stock_depletion` alert row created with correct message/shortfall count;
dedup confirmed stable on reload; negative control (fulfilled order) confirmed
no new alert.

**Open questions / follow-ups:**
- Dedup key (`ReportingDateString()`) vs UTC `triggered_at` stamp mismatch is
  NOT fixed here — tracked in Prompt 2.
- Added inline comment flagging the Manila 06:00–08:00 dedup window caveat.

---

## Prompt 2 — Alert dedup: timezone/calendar mismatch

**Status:** ✅ Completed (2026-08-22) — Option A implemented after user confirmed.

**Findings:**
- 3 of 6 dedup checks keyed on `ReportingDateService::reportingDateString()` (Asia/Manila, 06:00 reset) while `triggered_at` was stamped with UTC `now()` → during Manila 06:00–08:00 (UTC 22:00–24:00) the key never matched the stamp, so dedup silently failed. Affected: `createSensorResetAlert`, `runDepletionCheck`, `checkMortalitySpike`.
- User-facing "today" across the app = the reporting date (dashboard today/periods, egg-logging default + `before_or_equal` validation, analytics, feed, mortality "today" stat, egg SSE) — the farm's operational day is 06:00-to-06:00, not the UTC calendar day.
- Caller disagreement: `checkMortalitySpike` called from `MortalityController:118,218` (user-entered `log_date`) and `ChickensController:608` (recomputed `reportingDateString()`).

**Decision (Option A vs B) and reasoning:** **Option A** (dedup day = farm reporting date). The prompt's "vote for A" condition was met: `ReportingDateService` is the convention for every user-facing "today". Option B would, during Manila 06:00–08:00, key the farm's NEW day under the previous UTC date and silently suppress a fresh alert via yesterday's — a missed alert.

**Fix applied:**
- `app/Services/ReportingDateService.php` — added `reportingDayWindow(string $date): array`, returning the half-open `[start, end)` reporting-day window (Manila `$date@reset` → `$date+1@reset`) as naive datetime strings in the app timezone. Honors the pre-06:00 backup (02:00 belongs to the previous reporting day).
- `app/Http/Controllers/Controller.php` (`checkMortalitySpike`) — dedup now `triggered_at >= dayStart AND < dayEnd` (window anchored to the mortality `log_date`) instead of `whereDate(triggered_at, $logDate)`.
- `app/Http/Controllers/SensorIngestionController.php` (`createSensorResetAlert`) — same window change, anchored to `reportingDateString()`.
- `app/Http/Controllers/PreOrderController.php` (`runDepletionCheck`) — same window change, anchored to `reportingDateString()`.
- `app/Http/Controllers/ChickensController.php` (`remove`/bulk batch) — bound `$logDate = reportingDateString()` once; used for both the MortalityLog write and the `checkMortalitySpike` key, so both callers now use the same key definition (the mortality row's `log_date`). `MortalityController` already passes the row's `log_date`.
- Kept `triggered_at => now()` (true instant) — relative alert display (`diffForHumans`) is unchanged.
- Out of scope (unchanged per prompt): `EnvironmentAlertService`, `createOccupancyMismatchAlert`, both `low_stock` checks (still UTC-day).

**Verified:**
- `php -l` clean on all 5 changed files.
- Tinker boundary assertions: the previously-broken instant (Manila 06:30 = UTC 22:30 prev day) now falls inside its reporting-day window (`2026-08-21 22:00` → `2026-08-22 22:00`); pre-06:00 instant (Manila 02:00) falls in the *previous* reporting day's window; `now()` always sits in its own window.
- Ran `AlertControllerTest`, `PreOrderTest`, `ChickensControllerTest`: 4 failures / 15 passed — **identical with the changes stashed** (poolData etc. pre-existing, unrelated to this prompt). No regression.

**Open questions / follow-ups:**
- The other 3 dedup checks still use UTC-day. Before Prompt 3 (unique "day" DB column), recommend converting them to the reporting-day window so the constraint is uniform app-wide.
- `mortality.blade.php:47` form defaults `log_date` to UTC `date('Y-m-d')`, one day behind the reporting date during Manila 00:00–08:00 — user-visible drift, out of scope here.
- Prompt 3 will still need the `is_read` "re-arm on read" behavior decision (dedup ignores read alerts).

---

## Prompt 3 — Alert dedup: DB-level unique constraint

**Status:** ✅ Completed (2026-08-22). Applied to reachable MySQL `layrate` on this host (Pi/prod host unreachable at apply time — see Open questions).

**Findings:**
- `alerts` had zero unique constraints; all dedup was application-level `exists()`→`create()`. Confirmed sole enforcement gap behind every theoretical race.
- Engine targets: MySQL (prod `.env`) + SQLite (local/tests). Constraint must be portable.

**Constraint design (final):**
- Two plain app-written columns (no generated/expression columns, no CONVERT_TZ): `alert_day DATE` (reporting date) + `dedup_key VARCHAR(120)` (scope identity, grammar via `Alert::dedupKey()`).
- **`UNIQUE(dedup_key, alert_day)`**, no partial/filtered index, **no `is_read` condition** — one alert per dedup_key per reporting day, full stop. Deliberately removes the "mark read re-arms same-day" behavior (decision #2).
- NULL `cage_id` collapsed into the `0:` prefix in `dedup_key` (avoids MySQL NULL-distinct-unique trap without functional indexes).
- Migration (`2026_08_22_000002_add_alerts_dedup_columns.php`): add columns → backfill → delete pre-existing duplicate identities (keep newest) → add index.
- Runtime: `Alert::dedupKey()` + `Alert::createDeduped()` (swallows unique violations as "already exists"); all 6 create sites pass `dedup_key` + `alert_day`.

**Fix applied (files):**
- `app/Models/Alert.php`, `app/Services/ReportingDateService.php` (`reportingDateFor`), 6 create sites (`Controller`, `EnvironmentAlertService`, `SensorIngestionController` x2, `EggStockBatch`, `FeedController`, `PreOrderController`), `tests/Feature/FeedBatchManagementTest.php` (rewritten `subsequent_day` test), migration `2026_08_22_000002_add_alerts_dedup_columns.php`.

**Verified:**
- **Dry-run gate (mortality message parser):** 0 rows in `alerts` with `alert_type='mortality_spike'` on the reachable DB → **0 non-matching messages**. Parser safe for data reachable here; note the reachable DB had no mortality rows to exercise (Pi production unreachable).
- **Backup before apply:** `db:backup` → `storage/app/private/backups/layrate_backup_2026-08-22_035559.sql` (245 KB).
- **Migrate:** `000001_split_low_stock_alert_types` (0.82ms) + `000002_add_alerts_dedup_columns` (271.9ms) both DONE against MySQL `layrate`. Existing 2 alerts backfilled: `3:humidity_high` / `2:humidity_watch`, both `alert_day 2026-07-31`; `alerts_dedup_key_day_unique` (dedup_key, alert_day) index present (Non_unique=0).
- **End-to-end on real MySQL:** `Alert::createDeduped` same key+day → first inserts (id 3), duplicate returns `NULL` (constraint blocked, no 500); verify rows cleaned up, `alerts` back to 2 rows.
- Full suite (SQLite): still **35 failed / 335 passed** — identical pre-existing baseline, no regression.

**Open questions / follow-ups:**
- **Production Pi deploy (2026-08-22, later):** pushed `37fbd45` → auto-deploy failed at `000002`'s unique-index ALTER on real dup data. Root cause: `->having(DB::raw('COUNT(*) > 1'))` compiles to `having COUNT(*) > 1 = ?` (nil binding) → dedupe silently deleted nothing. Fixed in `ecd9711` (`->havingRaw(...)`). Recovery: dropped the two partial columns, reran the failed workflow. Final prod state: both migrations Ran, `alerts_dedup_key_day_unique` live, 9 duplicate rows (cage 5, report day 2026-07-21) deduped to 3 keeps (18/19/17; 6 removed — all confirmed genuine organic alerts; duplicates came from the old is_read re-arm loophole), colliding `createDeduped()` → NULL (no 500), alerts total 48 (was 54). Pi test suite can't run (deploy is `composer --no-dev`); local suite @ `ecd9711` = 35 failed/335 passed — identical baseline, no new failures.
- The other 3 dedup checks still use their old UTC-day fast paths vs reporting-day `alert_day` — equivalent result (constraint backstops); optional alignment pass later.

---

## Prompt 4 — Alert dedup: low_stock cross-suppression

**Status:** ✅ Completed (2026-08-22)

**Findings:** `FeedController::checkLowStock` and `EggStockBatch::checkLowStock` shared `alert_type='low_stock'` + `cage_id null` but mean different things (feed-batch kg vs egg-count pool). Asymmetric dedup (Feed has no size filter): if EggStock fired first, Feed was suppressed that day; if Feed fired first, per-size EggStock still fired — order-dependent.

**Fix applied:**
- `app/Http/Controllers/FeedController.php` (`checkLowStock`) — `'low_stock'` → `'low_stock_feed'` (exists + create).
- `app/Models/EggStockBatch.php` (`checkLowStock`) — `'low_stock'` → `'low_stock_eggs'` (exists + create).
- `app/Http/Controllers/AlertController.php:34` — `$eggTypes` grouping: `'low_stock'` replaced by `['low_stock_feed', 'low_stock_eggs']`.
- `tests/Feature/FeedBatchManagementTest.php` — all `'alert_type','low_stock'` assertions → `'low_stock_feed'`.
- New migration `database/migrations/2026_08_22_000001_split_low_stock_alert_types.php` — backfill: message contains size + " eggs — " → `low_stock_eggs`, else `low_stock_feed`; `down()` reverses.
- Untouched (same-name-different-meaning): `low_stock_threshold` field/column/forms, `egg_low_stock_threshold_*` setting keys, `FeedBatch::is_low_stock`.

**Verified:**
- Full suite with Prompt 4 stashed vs applied: **identical 35 failed / 335 passed** → the split introduced zero regressions (the 35 are pre-existing, e.g. `PreOrderTest poolData`, batch-code seeder count).
- `FeedBatchManagementTest` low-stock block (created / not-created / same-day dedup) all green.

**Open questions / follow-ups:**
- `docu/FEATURE-INVENTORY.md:94-95,116,157,161` still documents a single `low_stock` type — docs-only, update when convenient.

---

## Prompt 5 — Manual sensor override not reflected after fetch

**Status:** ✅ Implemented against the local test DB only (not Pi / not shared MySQL). Awaiting deploy/backfill decision.

**Root cause identified:**
- Env-reading "manual override" (`EnvironmentController::updateLog`) persisted the override as an ordinary `EnvironmentalLog` row stamped `recorded_at = 12:00:00` (deleting the day's raws first) with **no marker flag**.
- Live/current read (`Cage::latestEnvironmentLog`, `EnvironmentController::liveData`) returns the **newest row by recorded_at** — so the next bridge reading (every few seconds) becomes "latest" and **reverts the display to live**; a past-day override can never surface in live at all. There is no "override wins" precedence. (Egg-count manual override does this correctly via `logged_via='manual'` guarded at `SensorIngestionController:159`; relay manual mode correct via `control_mode`.)
- TWO further latent bugs found (per item 4), both from sharing the `(cage_id, 12:00:00)` key:
  - **Bug A:** nightly `environment:compute-daily-averages` upserts via `EnvironmentalLog::updateOrCreate(['cage_id', 'recorded_at'=>noon])` → would silently **overwrite the override row every night** (`ComputeDailyEnvironmentAverages.php:60-69`).
  - **Bug B:** a DHT ingestion reading landing exactly on the noon second would `updateOrCreate` over the override (negligible probability, but real).

**Fix applied (files + key lines):**
- `database/migrations/2026_08_22_000003_add_is_override_to_environmental_logs.php` — add `is_override` boolean default false (no backfill).
- `app/Models/EnvironmentalLog.php` — add `is_override` to fillable + boolean cast.
- `app/Http/Controllers/EnvironmentController.php` — `updateLog` sets `is_override=true` on the noon row (:165); `liveData` prefers a current-reporting-day override over the newest raw via `ReportingDateService::reportingDayWindow` (import added; override fetch + `$overrideByCage` overlay, ~:44-58); `logs()` treats an override day as authoritative (CASE/MAX collapse avg/min/max/count, ~:109-124).
- `app/Console/Commands/ComputeDailyEnvironmentAverages.php` — excludes `is_override=1` from AVG and **skips writing** for a day that has an override (fixes Bug A).
- `app/Http/Controllers/SensorIngestionController.php` — guard so an exact-noon DHT collision can't clobber an override (mirrors `:159` egg pattern; fixes Bug B).
- `tests/Feature/EnvironmentLogOverrideTest.php` — 5 tests (write path, current-day wins, nightly intact/excluded, past-day average, reporting-day boundary attribution).

**Verified (local test DB = `layrate_testing`, MySQL):**
- `is_override` column exists, `tinyint(1)` NOT NULL default 0.
- New tests: **5 passed (16 assertions)**.
- Full suite: **35 failed / 340 passed (1111 assertions)** vs baseline 35 failed / 335 passed (1095) → **+5 passing (the new test file), 0 new or different failures** (the 35 are the pre-existing set, e.g. PreOrderTest poolData, FeedBatch code-gen).
- Item-2 check: **0 pre-existing `recorded_at = 12:00:00` rows** on both reachable DBs (local `layrate` and Pi prod) → no un-flagged legacy overrides exist, so **no backfill needed**.

**Open questions / follow-ups:**
- This is **not deployed or pushed** (local test DB only). Decide deploy timing (next driver commit pushes it to the Pi via deploy.yml).
- No backfill warranted (0 noon rows found); if a legacy override ever shows up in the future WITHOUT the flag, it would look like a normal noon reading — flagged here, not acted on.

---

## Prompt 6 — Hardware disconnect/fault detection audit

**Status:** ✅ Design complete — implementation pending (no implementation yet; awaiting review; implement against report §5 resolutions).

**Current detection logic (as-built):**
- Entry: `serial-bridge/bridge.py` single-threaded loop (`POLL_INTERVAL 0.05 s`), POSTs to `/api/sensor-readings` (10 s timeout); 5 s reconnect on `SerialException`; no per-sensor liveness tracking at the edge.
- Staleness: `hardware:check-staleness` cron (every 15 min, `CheckHardwareSensorStaleness`) — DHT22: cage-level quiet > 60 min → `status=faulty` + `sensor_stale`; IR: no occupancy reading > 24 h → faulty only if recent egg activity, else `sensor_no_activity`; calibration > 90 d → `calibration_overdue`. Status lives on `hardware_items.status`, not `cage_slots`.
- Disconnected vs faulty: only binary active/faulty; the single nuance is IR idle-vs-faulty. No stuck/implausible/flapping detection.
- Relay safety: firmware decides "invalid DHT → OFF (SAFETY)"; Laravel mirrors/derives (`relay_seen_at` online <2 min; `relay_safety` display), decision not centralized server-side.
- UI: per-item + per-cage rows with >30-min "Stale" badges (threshold ≠ cron's 60-min/24-h), relay fan states; dashboard has no sensor indicator; polling only (10 s env), SSE only for relay.

**Key gaps found:** stuck-value/out-of-range not detected; no auto-recovery (faulty is terminal — `findActiveForIngestion` drops non-active); threshold drift (30 vs 60/24); no device/bridge liveness; no debounce; safety decision firmware-only; partial-slot/unassigned cases.

**Proposed design (in report):** state machine `online / stale / disconnected / faulty / recovering`, `health_state` separate from admin `status`, `last_valid_reading_at` + `fault_issue`, `devices.last_seen_at`, 3-tick debounce, per-type thresholds, server-centralized safety, auto-recovery.

**Deliverable:** `/audit/hardware-fault-detection-report.md`.

**Open questions / follow-ups:**
- Refinement pass (2026-08-22) resolved all four review points + locked both open questions — see report §5: (1) liveness MUST exclude `is_override=1` rows; (2) cadence = ingestion-triggered eval + 15-min backstop cron; (3) recovering emits one `health_recovering` notice via existing `Alert::createDeduped()` (key `{cage|0}:health_*`, `alert_day`=reporting date), fault re-entry needs its own 3-tick debounce; (4) relay safety = hard advisory-only constraint (never gates firmware control loop). Locked: `health_state` separate from admin `status`; thresholds `Setting`-backed per type.

---

## Prompt 7 — predicted_hdep export bug

**Status:** ✅ Completed (2026-08-22)

**Findings:**
- `2026_07_02_000000` indeed renamed `predicted_hdep` → `predicted_egg_count` (decimal 10,2) and the model only knows `predicted_egg_count`. The forecast now stores **egg count**, not HDEP %.
- Stale refs in code (whole-repo grep, excl. migration `down()`): `ForecastController::exportCsv:1327` (`?? $f->predicted_hdep` dead fallback), and `forecast/_results.blade.php:32` (`number_format($f->predicted_hdep,1) }}%` → rendered "0.0%") and `:51` (JS chart data).

**Fix applied (files + lines):**
- `app/Http/Controllers/ForecastController.php:1327` — `$f->predicted_egg_count ?? 0` (dropped stale fallback).
- `resources/views/forecast/_results.blade.php:24,32` — header → "Predicted Eggs"; cell → `number_format($f->predicted_egg_count,1)` (no stray `%`).
- `resources/views/forecast/_results.blade.php:51` — JS chart value → `(float)$f->predicted_egg_count`.

**Verified:** `php -l` clean; grep clean of `predicted_hdep` outside the rename migration. Full suite: **35 failed / 340 passed (1111 assertions)** — identical to the post-Prompt-5 baseline (35/340/1111) → **no new or different failures** (ForecastAsyncTest + SyncForecastInputRecordsTest in the passing set).

**Open questions / follow-ups:**
- The forecast chart still plots **Historical `hdep` (%)** against **Forecast `predicted_egg_count` (eggs)** on the same axis — a unit mismatch that predates this rename; flagged for a deliberate follow-up (not fixed here).

---

## Prompt 8 — Decouple GenerateForecastJob from ForecastController

**Status:** ✅ Completed (2026-08-22) — local verification only; **pending Pi deploy** (same pattern as Prompt 7 — Pi unreachable this session).

**Extraction plan (what moved, what stayed):**
- Moved → new `app/Services/ForecastGenerationService.php`: `farmHistorical/breedHistorical/cageHistorical`, `generateForecast`, `executePythonForecast`, `persistForecasts`, `buildForecastCollection`, plus `resolvePythonBinary`/`processEnv` (public, so the controller's other Python flows — downloadTemplate/import/importPreview/importConfirm — call the one source). Stays: `ForecastRules.php`, `checkForecastDataSufficiency`, all other ForecastController responsibilities. Pre-checked: no moved method relies on controller instance state (all data via params/config/statics); `resolvePythonBinary`/`processEnv` were identical across all callers (single method, 5 call sites).

**Fix applied (files + line ranges):**
- `app/Services/ForecastGenerationService.php` (new) — the 9 methods moved verbatim.
- `app/Http/Controllers/ForecastController.php` — removed the 9 moved methods (−305 lines); added `use` + private `forecastService()` accessor; re-pointed historical call sites (`:84,106,129`, `:1163,1174,1185`, `:1247,1253,1260`, `:1475,1480,1486`) and python binary/env call sites (`:177,207`, `:435,461`, `:555,573`, `:638,658`) to the service.
- `app/Jobs/GenerateForecastJob.php` — `handle(ForecastGenerationService $service)`; 4 calls swapped; controller import removed; docblock updated.
- Untouched: `ForecastRules.php`, `checkForecastDataSufficiency`, imports/render/export logic.

**Verified:**
- Pre-flight greps: no instance-state in moved slice; binary/env handlers identical across flows; no leftover old `handle(controller)` / constructor usage anywhere.
- `ForecastAsyncTest`: **9 passed (31 assertions)** — incl. the sync-queue `handle()` path exercising the new service injection.
- Full suite: **35 failed / 340 passed (1111 assertions)** — identical baseline, **no new/different failures**.

**Open questions / follow-ups:**
- **Pending Pi deploy** (queue worker `layrate-queue-worker.service` will use the new service once deployed; behavior parity guaranteed since web + worker share the same service).
- `app/Services` naming chosen over `app/Forecast` (ForecastRules is a tiny static helper, not the home).

---

## Prompt 9 — JSON/error response contract

**Status:** ⬜ Not started

**Contract defined (link to /docs/api-contract.md):**

**3 endpoints chosen and why:**

**Fix applied:**

**Verified:**

**Open questions / follow-ups (remaining controllers to convert later):**

---

## Prompt 10 — DESIGN-SYSTEM.md + 3 shared components

**Status:** ⬜ Not started

**DESIGN-SYSTEM.md drafted:** (yes/no, link)

**Navy token decision:**

**Fix applied (card/button/underline-tabs):**

**Verified:**

**Open questions / follow-ups:**

---

## Prompt 11 — Delete verified dead code

**Status:** ⬜ Not started

**Verification method used:**

**Deleted:**

**Held back (found a live reference, did not delete):**

---

## Prompt 12 — Forecast chart axis mismatch

**Status:** ✅ Completed (2026-08-22) — verified, awaiting commit.

**Root cause:** `forecast/_results.blade.php`'s `forecastChart` plotted **Historical HDEP %** (`$l->hdep`) and **Forecast daily egg count** (`$f->predicted_egg_count`, per the P7 rename) on one linear y-axis hardcoded `min:0,max:100`. Real egg counts (spot-check on local dev MySQL: historical 124–228, forecast ~119–120) **always exceed 100**, so the forecast series was clipped/off-scale and the "% vs count" pair was misleading on a single axis.

**Fix (view/chart-config only — ForecastGenerationService/Controller untouched, P8 not reopened):**
- `resources/views/forecast/_results.blade.php`: title → "Historical vs Forecast Eggs"; historical series now maps `egg_count` (already present on historical rows) so **both series share a single egg-count axis**; `y: { min:0, max:100 }` → `y: { beginAtZero: true }` (dynamic max, baseline 0).
- Stray-label scan: **no other "Hdep" references** remain in forecast views (title was the only one; the results-table header was already "Predicted Eggs" from P7).

**Verified:**
- `php artisan view:cache` compiles cleanly (all blade OK).
- Full suite: **35 failed / 343 passed (1116 assertions)** — identical baseline, **no new or different failures**.

**Open questions / follow-ups:** none (units now consistent end-to-end: historical + forecast both eggs; dynamic axis fits real ranges).

---

## Security fix — unauthenticated opcache reset route

**Status:** ✅ Done (2026-08-22) — verified, awaiting commit.

**Root cause:** `routes/web.php:47` `GET /_reset-opcache` called `opcache_reset()` with **no auth middleware** — added in `744af8a` as a "Temporary" debugging leftover; documented in FEATURE-INVENTORY as "deliberately unauthenticated." Any anonymous visitor could force cache invalidation on demand (unnecessary attack surface / minor DoS vector).

**Fix:** Gated behind the existing `auth` + `admin` middleware (reuses `EnsureAdmin`, which 403s non-admins — same pattern used by every other admin-only route; no new guard invented). Removed the "Temporary / no auth" comment; updated `docu/FEATURE-INVENTORY.md:29` to match.

**Verified:**
- `routes/web.php` lint clean.
- New `tests/Feature/OpcacheResetRouteTest.php` (3 passed / 5 assertions): guest → redirect to login · non-admin authenticated user → **403** · admin → **200 "opcache reset done"**.
- Full suite: **35 failed / 343 passed (1116 assertions)** vs baseline 35/340/1111 → +3 passing = exactly the new test; **no new or different failures**.

**Open questions / follow-ups:** none (ops can still reset opcache as admin via this endpoint, or via CLI `php -r "opcache_reset()"`).

## Summary — outstanding items

| # | Item | Status |
|---|---|---|
| 1 | Dead stock_depletion alert branch | ✅ Done |
| 2 | Alert dedup timezone mismatch | ✅ Done (Option A: reporting-day window; ChickensController caller agreement) |
| 3 | Alert dedup DB unique constraint | ✅ Done (dry-run 0 msmt; backup db:backup; migrated MySQL layrate; e2e constraint-blocked dup; Pi prod pending its own run) |
| 4 | low_stock cross-suppression | ✅ Done (split into low_stock_feed / low_stock_eggs; backfill migration; no regression) |
| 5 | Manual sensor override not reflected | ✅ local-test only (env override precedence + nightly/noon clobber fix); 0 noon rows → no backfill; pending deploy decision |
| 6 | Hardware fault-detection audit | ✅ design (report + §5 resolutions) / ⬜ implementation pending |
| 7 | predicted_hdep export bug | ✅ Done (exportCsv + _results view → predicted_egg_count; suite 35/340, no new failures) |
| 8 | GenerateForecastJob decoupling | ✅ Done (ForecastGenerationService; suite 35/340 no new failures; pending Pi deploy) |
| 9 | JSON/error response contract | ⬜ |
| 10 | DESIGN-SYSTEM.md + shared components | ⬜ |
| 11 | Delete verified dead code | ⬜ |
| 12 | Forecast chart axis mismatch | ⬜ |
| 13 | Unauthenticated /_reset-opcache route | ✅ Done (gated auth+admin; test 3/3; suite no new failures) |
