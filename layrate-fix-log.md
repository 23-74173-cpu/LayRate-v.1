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

**Status:** ⬜ Not started

**Root cause identified:**

**Fix applied:**

**Verified:**

**Open questions / follow-ups:**

---

## Prompt 6 — Hardware disconnect/fault detection audit

**Status:** ⬜ Not started

**Current detection logic (as-built, not as-documented):**

**Gaps found:**

**Proposed device-health state machine:**

**Open questions / follow-ups:**

---

## Prompt 7 — predicted_hdep export bug

**Status:** ⬜ Not started

**Findings:**

**Fix applied:**

**Verified:**

---

## Prompt 8 — Decouple GenerateForecastJob from ForecastController

**Status:** ⬜ Not started

**Extraction plan (what moved, what stayed):**

**Fix applied:**

**Verified:**

**Open questions / follow-ups:**

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

## Summary — outstanding items

| # | Item | Status |
|---|---|---|
| 1 | Dead stock_depletion alert branch | ✅ Done |
| 2 | Alert dedup timezone mismatch | ✅ Done (Option A: reporting-day window; ChickensController caller agreement) |
| 3 | Alert dedup DB unique constraint | ✅ Done (dry-run 0 msmt; backup db:backup; migrated MySQL layrate; e2e constraint-blocked dup; Pi prod pending its own run) |
| 4 | low_stock cross-suppression | ✅ Done (split into low_stock_feed / low_stock_eggs; backfill migration; no regression) |
| 5 | Manual sensor override not reflected | ⬜ |
| 6 | Hardware fault-detection audit | ⬜ |
| 7 | predicted_hdep export bug | ⬜ |
| 8 | GenerateForecastJob decoupling | ⬜ |
| 9 | JSON/error response contract | ⬜ |
| 10 | DESIGN-SYSTEM.md + shared components | ⬜ |
| 11 | Delete verified dead code | ⬜ |
