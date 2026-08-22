# Alert Dedup Correctness Audit — 6 "create alert if not already created today" implementations

**Scope:** the 6 independent implementations listed below. Audit only — no code changed.
**Date:** 2026-08-22

**App timezone baseline:** `config/app.php` → `'timezone' => env('APP_TIMEZONE', 'UTC')`. Neither `.env` nor `.env.example` sets `APP_TIMEZONE`, so **the app runs on UTC**; `now()`/`today()` and the `'triggered_at' => now()` stamp are UTC. The Pi's `.env` could not be re-verified (SSH timed out while writing this), but the local default is UTC, and the divergence windows below are structural — they persist under any app timezone that isn't exactly the regime each dedup key assumes. `ReportingDateService::timezone()` is hardcoded to **Asia/Manila** (UTC+8), and `reportingDate()` = *previous* calendar day between 00:00–06:00 Manila (i.e. UTC 16:00–22:00).

**Stored timestamp mechanics (root of most divergences):** every implementation writes `'triggered_at' => now()` → a **naive datetime stamped in the app timezone (UTC by default)**. `whereDate('triggered_at', $key)` compares `DATE(triggered_at)` — the literal wall-clock date of the stored value — to the key string. So the dedup matches **only when the key's calendar date equals the app-timezone wall-clock date of the stored stamp.**

---

## Master table

| File / method | "today" definition | Timezone source | Race-condition risk | Trigger condition | DB-level constraint |
|---|---|---|---|---|---|
| `Controller.php` → `checkMortalitySpike` (:20-24) | `whereDate('triggered_at', $logDate)`; `$logDate` = **user-entered `log_date`** (`MortalityController.php:118,218`) or **`ReportingDateService::reportingDateString()`** (`ChickensController.php:608`) | Key = farm-calendar / Asia/Manila reporting date; **stamp = UTC `now()`** → mixed regimes | **Yes** (theoretical). exists→create with no transaction/lock for this path. Two parallel mortality writes for the same cage/date (two users, or a double-submit) both pass and both insert. Low practical likelihood (destructive forms), but real. | Per-cage, per-`$logDate`, **unread** alert; fires when `MortalityLog::where(cage_id,$logDate)->sum('count') >= 3` (:16) | **No** |
| `EnvironmentAlertService.php` → `check` (:53-57) | `whereDate('triggered_at', $log->recorded_at->toDateString())` — date of the reading payload | `recorded_at` from `serial-bridge/bridge.py:184` = **UTC** (`datetime.now(timezone.utc)` + `+00:00`); stamp = UTC `now()` → **internally consistent while app tz = UTC** | **Yes** (real competing producers). `exists()` then `create()`, no lock, no unique constraint. Two producers: `SensorIngestionController:89` (live bridge) **and** `alerts:check-environment` cron (`CheckEnvironmentAlerts`, every 15 min, `withoutOverlapping`) (`routes/console.php`). A bridge POST racing the cron sweep can both pass the exists-check and both insert. | Per-cage, per-type, **unread**; fire on `temp < min`, `temp > max`, `hum < min`, `hum > max` — **strict** inequalities; boundary `==` is deliberately NOT an alert (watch-vs-alert convention, :13-14). Threshold must be set (non-null). Up to 4 alert types per reading. | **No** |
| `SensorIngestionController.php` → `createSensorResetAlert` (:333-337) | `whereDate('triggered_at', ReportingDateService::reportingDateString())` — **Asia/Manila reporting date (06:00 reset)** | Key = Asia/Manila; **stamp = UTC `now()`** → **mismatched** (§2.2) | **Yes** (theoretical only — sensor asserts are single-seq bridge). exists→create runs inside the ingestion txn, but a plain SELECT under REPEATABLE READ still allows a phantom insert with no unique key; no cron shares this path. | Per-cage, per-`sensor_reset` type, **unread**; fires when an IR count is **lower** than the stored `ProductionLog::egg_count` for that slot+day (regression guard, `:171-181`) | **No** |
| `SensorIngestionController.php` → `createOccupancyMismatchAlert` (:358-362) | `whereDate('triggered_at', now()->parse($recordedAt)->toDateString())` — date of the reading payload | `recorded_at` = **UTC** from bridge; stamp = UTC → consistent *today*. **Fragile**: the offset is whatever the sender includes (the API doc example itself shows `+08:00`, `:36`); any non-UTC sender breaks the key↔stamp alignment | **Yes** (theoretical; same txn, no lock/unique; no cron overlap). Practically sequential bridge. | Per-cage, per-`occupancy_mismatch` type, **unread**; fires on **any** `reportedCount !== $slot->current_occupancy` (:211-213) — including everyday ±1 counting variance, not only impossible drops | **No** |
| `EggStockBatch.php` → `checkLowStock` (:191-196) | `whereDate('triggered_at', today())` — **app-tz calendar day (UTC)** | Key = app tz (UTC); stamp = UTC → consistent. Dedup **also** scoped by `cage_id null` and `message LIKE '%{size}%'` (size identified by substring in the message text) | **Yes** (theoretical; exists→create, no txn/lock). Called from `EggStockController` store/update/saveThresholds payload actions (user requests) — two near-simultaneous actions can both pass. Low-moderate. | Per-size (`small/medium/large/jumbo`), **unread**, `cage_id null`; fires when `$threshold > 0` **and `$available <= $threshold`** (:186-189). Shares `alert_type 'low_stock'` + `cage_id null` with FeedController (§2.4). | **No** |
| `FeedController.php` → `checkLowStock` (:525-529) | `whereDate('triggered_at', today())` — **app-tz calendar day (UTC)** | Key = app tz (UTC); stamp = UTC → consistent. **Not** scoped by size/batch (no message filter) | **Yes** (theoretical; exists→create, no txn/lock). Called from `storeConsumption`/`updateConsumption`/`storeFarmFeedEntry`/`updateFarmFeedEntry` (:264,301,367,406) — two parallel submits can both pass. Low-moderate. | `$batch` must have non-null `total_quantity_kg` and `low_stock_threshold`, and `is_low_stock` (:517-523); single generic **unread** alert/day (any batch). Same `low_stock` type + `cage_id null` as EggStockBatch (§2.4). | **No** |
| `PreOrderController.php` → `runDepletionCheck` (:287-291) | `whereDate('triggered_at', ReportingDateService::reportingDateString())` — **Asia/Manila reporting date (06:00 reset)** — but the branch is **unreachable** (§2.1) | Key = Asia/Manila; stamp = UTC → mismatched (§2.2) — moot, code never runs | **N/A (dead branch — §2.1).** If it ran, exists→create with no txn on **every `GET /eggs/pre-orders`** (index `:102`): two parallel page loads would both insert | Intended: per-size **unread** alert when `available < 0`, message filtered by size. **Never fires — condition impossible** (see §2.1) | **No** |

**DB-level constraint for all six: NO.** `alerts` has **no unique constraint**. `2026_01_01_000008_create_alerts_table` defines the columns with none; `2026_08_13_000004_add_dedup_and_listing_indexes` adds only **non-unique** indexes (`alerts_dedup_check_index (cage_id, alert_type, is_read, triggered_at)` — its own comment describes the check as the app's job); `2026_07_09_100003_add_dedup_unique_indexes` adds unique keys to `environmental_logs` and `sensor_occupancy_readings` **but not `alerts`**. Dedup is 100% application-level exists-then-create; only a day-truncated generated column could ever make a DB unique key work on `triggered_at`. `cage_id` is nullable (`2026_07_01_012850`).

**Common quirk, uniform (not a divergence):** all six gate on `is_read = 0`, so "already alerted today" really means "…and not yet read" — marking an alert read allows a fresh one for the same cage/type/day.

---

## STEP 2 — FLAG DIVERGENCES

### 2.1 TOP DIVERGENCE — `runDepletionCheck` can NEVER fire (dead alert code)

`PreOrderController::index` builds the summary with **`'available' => max(0, $pool)`** (:91-97) and then calls `runDepletionCheck($summary)` (:102). But `runDepletionCheck` tests **`if ($data['available'] < 0)`** (:282). Because `max(0, …)` guarantees `available >= 0` **always**, the `< 0` branch is **unreachable**: `stock_depletion` alerts **can never be created**. The data needed to fire (`'deficit' => $pool < 0 ? abs($pool) : 0`, :98) is computed in the same array and never used by the check. Concrete lines: `PreOrderController.php:91-99` (index), `:282` (dead condition). This is "alert never fires when it should."

### 2.2 "Today" boundary / timezone inconsistency — three different calendars

1. **UTC-calendar day** — `EnvironmentAlertService` (recorded_at), `createOccupancyMismatchAlert` (recorded_at), **both** `low_stock` implementations (`today()`). Consistent with the UTC-stamped `triggered_at`.
2. **Asia/Manila reporting date (06:00 reset)** — `createSensorResetAlert` key, `runDepletionCheck` key, and `ChickensController:608`'s call into `checkMortalitySpike`.
3. **User-entered farm-calendar `log_date`** — `checkMortalitySpike` via `MortalityController:118,218`.

Groups 2–3 do not equal the UTC wall-clock date written into `triggered_at`:

- **Manila 00:00–06:00** (UTC 16:00–22:00): `reportingDate()` returns the **previous** Manila calendar day, which happens to **equal the UTC calendar date** here — so the key still matches the UTC stamp. Dedup *works* in this window.
- **Manila 06:00–08:00** (UTC 22:00–24:00): reporting date = start of the farm's "today" = **UTC+1**, while the stamp is UTC today → **dedup can never match** (key = tomorrow vs stamp = today). This is the only systematically-broken window.
- Net: for **2 of every 24 hours** (Manila 06:00–08:00 on a UTC app), any key computed from `reportingDateString()` cannot match the stored stamp — so `sensor_reset` can double-fire, and `runDepletionCheck` would have double-fired on every page load in that window (if it were reachable).
- `checkMortalitySpike` via `MortalityController` additionally breaks on **backdated logs**: the dedup key is the user-picked `log_date` (e.g. yesterday), but the stamp is UTC today → whereDate(yesterday) never matches today's stamp → re-logging the same past date re-creates the spike alert. `whereDate('log_date', $logDate)` on the *sum* query is fine; only the alert stamp is out of sync.
- Note `ChickensController:608` and `MortalityController:118/218` feed the **same** `mortality_spike` type from two different date-key sources (reporting date vs user `log_date`) — the dedup keys themselves disagree about what "today" is for the same alert type.

**Verdict:** of the six, only `EnvironmentAlertService`, `createOccupancyMismatchAlert` and the two `low_stock` checks are self-consistent on a UTC app. `createSensorResetAlert`, `runDepletionCheck`, and `checkMortalitySpike` (all callers) are in a different timezone than the stamps they dedup against.

### 2.3 Race-condition risk "in practice" (how sensors actually POST)

- **The sensor path is a single, sequential producer**: `serial-bridge/bridge.py` runs one `while True` loop (`run_loop`) that parses serial blocks and POSTs **one request at a time** (`.post(...)` at :350 blocks, then the next block). No threads, no asyncio. So `SensorIngestionController::store` is **not** realistically hit concurrently by the bridge itself.
- The one real overlapping producer is the **cron**: `alerts:check-environment` (`CheckEnvironmentAlerts`) re-runs `EnvironmentAlertService::check` over the last 24 h of logs **every 15 minutes** (`routes/console.php`, `withoutOverlapping`). That cron sweep and a live bridge POST can race on the same `(cage_id, alert_type, date)` pair — both select "not exists" and both insert. This is the most plausible duplicate-alert race in the system, though its 15-min cadence makes collisions rare.
- The other four call sites are **user/UI driven** (mortality, stock, consumption, page loads). Real-world concurrency requires two near-simultaneous submissions/tabs — plausible for `runDepletionCheck` (page loads), low for the form-driven ones.

### 2.4 Cross-implementation collision: two different alerts share one dedup key

`FeedController::checkLowStock` (`alert_type='low_stock'`, `cage_id null`, no size filter) and `EggStockBatch::checkLowStock` (`alert_type='low_stock'`, `cage_id null`, `message LIKE '%size%'`) are two **semantically different** alerts (feed-batch kg low vs egg-count pool low) that share the same type + null-cage key, so:

- If **EggStock** fires first today, **Feed** is suppressed (Feed's exists-check has no size filter, so it matches EggStock's row).
- If **Feed** fires first today, **EggStock** still fires per size (its `LIKE '%size%'` doesn't match Feed's batch-code message) → you get *both*.
- Order-dependent suppression: some days the feed warning disappears, some days both appear. This is a real dedup-key semantics divergence, not just naming.

### 2.5 Double-fire / never-fire summary (concrete, not hypothetical)

| Behavior | Where | Why (line + condition) |
|---|---|---|
| **Never fires** | `PreOrderController::runDepletionCheck` | `index()` clamps `available = max(0, $pool)` (`:97`); check is `$data['available'] < 0` (`:282`) — unreachable |
| **Can double-fire (dedup window)** | `SensorIngestionController::createSensorResetAlert` | dedup key = `reportingDateString()` vs stamp `now()` → key never matches during Manila 06:00–08:00 (UTC 22:00–24:00) (`:336` vs `:348`) |
| **Can double-fire (dedup window)** | `PreOrderController::runDepletionCheck` (if fixed to run) | same key-vs-stamp mismatch; runs on every `GET /eggs/pre-orders` |
| **Can double-fire (dedup window + backdating)** | `Controller::checkMortalitySpike` via `MortalityController` | key = user `log_date` (`:118,218`) vs UTC stamp — never matches for backdated dates or Manila-morning entries |
| **Dedup key disagreement for same type** | `checkMortalitySpike` callers | `MortalityController` passes `log_date`, `ChickensController:608` passes `reportingDateString()` |
| **Cross-suppression order bug** | `EggStockBatch::checkLowStock` ↔ `FeedController::checkLowStock` | shared `'low_stock'`/`cage_id null`; asymmetric message filter (§2.4) |
| **Rare duplicate inserts (real race)** | `EnvironmentAlertService::check` | two producers (bridge + 15-min cron) with exists→create and no unique constraint |

---

## STEP 3 — STOP & WAIT

No fix has been applied. Ranking for when you choose one to tackle:

1. **`PreOrderController::runDepletionCheck` dead branch** (never fires) — wrong condition (`max(0,$pool)` vs `< 0`), not a timing nuance. Cheapest, highest-visibility correctness win.
2. **Timezone/calendar alignment** — the four Asia/Manila / user-date keys vs UTC-stamped `triggered_at` (SensorReset, stock_depletion, mortality). Decide one definition of "today" (recommend storing `triggered_at` in the same calendar the key uses), then make all keys + stamps agree.
3. **DB-level dedup for `alerts`** — needs a day-truncated/generated column + unique key; kills the theoretical races and the is_read loophole concern for all six at once.
4. **`low_stock` shared-key collision** — separate feed vs egg low-stock alert types.
Which one first is your call — one at a time, verified individually.
