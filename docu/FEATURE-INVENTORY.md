# LayRate — Feature Inventory

Flat feature inventory of the LayRate system, grouped into 15 module areas. Every feature lists its entry point (route → controller/command/file) and its implementation status:

- **Fully implemented** — working end-to-end, exercised by the UI/API/scheduler.
- **Partially implemented** — functional core but with known gaps, hard-coded values, or untested paths.
- **Stubbed / planned** — placeholder, mock-only, or aspirational code.

Key facts established during this audit: 136 web routes + 3 API routes; 25 controllers; 27 models; 34 live tables (26 application + 8 framework); 16 console commands; 6 scheduled tasks; a Python forecast API (2 legacy variants + runner); an Arduino firmware; a Python serial bridge; a Flask mobile API; and a React Native/Expo companion app that is currently **100% mock-only** (no network calls; data comes from `mock/` + AsyncStorage).

Scheduled tasks (routes/console.php + bootstrap/app.php) are listed inside the module they automate: `alerts:check-environment` every 15 min (Env Monitoring), `hardware:check-staleness` every 15 min (Hardware), `environment:compute-daily-averages` daily 03:00 (Env Monitoring), `forecast:sync-input-records` daily 02:00 (Forecasting), `layrate:reconcile-occupancy --apply` daily (Chickens), `layrate:audit-egg-stock` (Egg Stock).

---

## 1. Authentication, Access & Account Management

1. **Login / logout** — Email+password authentication with `remember`, deactivated-account rejection, session regeneration on login, full invalidation on logout. Entry: `POST /login`, `POST /logout` → `AuthController::login/logout` (`app/Http/Controllers/AuthController.php`). **Fully implemented.**
2. **nftables walled-garden client authorization** — On successful non-local login, spawns `sudo /usr/local/bin/layrate-auth-client <clientIp>` in the background so the browser IP is admitted through the firewall. Entry: `AuthController::login` lines 36-39. **Fully implemented** (OS helper script is external; PHP side verified).
3. **Forgot-password / password reset** — No password reset flow exists; password changes are only possible while logged in. **Stubbed / absent** (no routes).
4. **Team user management (admin)** — Create / edit / toggle-active users with roles `admin|operator`; cannot change own role; cannot deactivate self; cannot deactivate the last active admin. Entry: `POST /settings/users`, `PUT /settings/users/{user}`, `POST /settings/users/{user}/toggle-active` → `AccountController::storeUser/updateUser/toggleUserActive` (`app/Http/Controllers/AccountController.php`). **Fully implemented.**
5. **Self profile edit (name/email)** — `POST /profile` → `AccountController::updateProfile`. **Fully implemented.**
6. **Change own password** — `POST /account/password` → `AccountController::updatePassword`; verifies current password, enforces `Password::min(8)`, re-syncs session auth hash. **Fully implemented.**
7. **Logout other devices** — `POST /profile/logout-other-devices` → `AccountController::logoutOtherDevices` using Laravel `logoutOtherDevices` + session hash resync. **Fully implemented** (DB session driver only; synthetic row under file driver).
8. **Manual egg-logging override PIN** — Set/change a 4-6 digit PIN (rejects weak PINs: 0000-9999 sequences, 1234, 4321, 0123, 1212); requires current PIN or password when one already exists. Entry: `POST /account/pin` → `AccountController::updatePin` (`app/Http/Controllers/AccountController.php`, `WEAK_PINS`). **Fully implemented.**
9. **Profile activity stats** — Per-user counts of egg logs, eggs total, feed logs, mortality logs; admin-only team/staff panels. Entry: `GET /profile` → `AccountController::profile`. **Fully implemented.**
10. **Admin role enforcement** — `EnsureAdmin` middleware alias on all destructive/high-privilege routes (cage layout, devices, forecast generate/clear/import, delete mutations, reports/forecast admin gating in-controller). Entry: `bootstrap/app.php` alias; `routes/web.php`. **Fully implemented.**
11. **Active-user guard** — `EnsureUserIsActive` middleware on the web group rejects deactivated sessions. Entry: `bootstrap/app.php`. **Fully implemented.**
12. **DB backup (MySQL)** — `POST /settings/backup` (admin) → `SettingsController::backupNow`; mysqldump with `--single-transaction --routines --triggers --events`, 300s timeout, chmod 0775 + chgrp www-data, 7-file retention purge, streams then deletes. Entry: `routes/web.php:188` → `app/Http/Controllers/SettingsController.php`. **Fully implemented** (MySQL-only; non-MySQL rejected with explicit error).
13. **Opcache reset endpoint** — Admin-only `GET /_reset-opcache` calls `opcache_reset()` when available (gated behind `auth` + `admin` middleware since 2026-08-22; non-admins get 403). Entry: `routes/web.php:47`. **Fully implemented**.
14. **Account password hashing compatibility** — Laravel `Hash::make` (`$2y$`) interoperable with Flask bcrypt (`$2b$`→`$2y$` conversion on both sides). Verified in `mobile-api/app.py`. **Fully implemented.**

## 2. Dashboard & Farm Overview

15. **Dashboard metric cards** — Today's eggs, HDEP (today ÷ active hens), HDEP delta vs yesterday, lifetime eggs, total hens, live avg temp/hum, per-cage feed-today with target (`feed_per_hen_daily` default 0.12 kg), mortality-today grouped by cage. Entry: `GET /dashboard/stats` → `DashboardController::stats` (`app/Http/Controllers/DashboardController.php`). **Fully implemented.**
16. **Cage overview grid** — Per-cage cards: today's eggs, hen count, breed, color, sensor presence, sensor status text (DHT22 online/offline recency, IR active count). Entry: `GET /dashboard` → `DashboardController::index`. **Fully implemented.**
17. **Feed & mortality panel** — `GET /dashboard/feed-mortality` → `DashboardController::feedMortality`. **Fully implemented.**
18. **Production calendar** — Month grid with per-day egg totals from ProductionLog, ±6-day margin to cover adjacent-month cells, month totals/logged days, year navigation, optional cage filter. Entry: `GET /dashboard/calendar` → `DashboardController::calendar`. **Fully implemented.**
19. **Onboarding gate** — Shows setup prompt when `farm_grid_rows/cols` settings missing. Entry: `DashboardController::buildDashboardData`. **Fully implemented.**

## 3. Cage & Farm-Layout Management

20. **Cage grid canvas** — Auto-expanding grid (floors 10 cols × 6 rows; grows to fit placed cages); drag-and-drop positioning. Entry: `GET /cages` → `CageController::index` (`app/Http/Controllers/CageController.php`, ~1132 lines). **Fully implemented.**
21. **Create cage** — rows 1-10 × slots_per_row 1-100 × max_chickens_per_slot 1-10; auto-generates slots and `CAGE-{letters}` code (base-26; CAGE-A…CAGE-Z→CAGE-AA). Entry: `POST /cages` (admin) → `CageController::store`. **Fully implemented.**
22. **Edit/resize cage** — dimension changes trigger slot renumbering (two-phase offset to dodge unique constraints) and resize safety checks (occupied/sensor-bearing slots cannot be orphaned; occupancy capped at new max). Entry: `PUT /cages/{cage}` (admin) → `CageController::update`. **Fully implemented.**
23. **Per-cage IR break-beam & DHT22 assignment** — Checkboxes assign/release break-beams per slot and set DHT22 count per cage; spare inventory consulted before assignment (`Only {N} IR break-beam sensor(s) available…`); auto-assigns oldest spare and stamps next `IRBBS-{n}` / `DHT22-{n}` ID. Entry: `CageController::update`, `assignSpareSensor`, `nextDeviceId`. **Fully implemented.**
24. **Single & batch position move** — `PATCH /cages/{cage}/position` and `POST /cages/batch-position` with out-of-bounds and AABB tile-overlap validation; write via two-phase null-then-set in a transaction. **Fully implemented.**
25. **Slot reordering** — `POST /cages/{cage}/slots/reorder` renumbers slots 1-255 with uniqueness/collision guards and two-phase temp assignment under `lockForUpdate`. **Fully implemented.**
26. **Remove cell / shrink grid** — `POST /cages/remove-cell` clears a placed cage or shrinks the grid only from the edge columns/rows. **Fully implemented.**
27. **Delete cage** — JSON `destroy` with hen action (`move`/`delete`), sensor return-to-spare, and FK-preserve flags; hard `forceDestroy` requiring typed cage-code confirmation with warning log. Entry: `DELETE /cages/{cage}`, `DELETE /cages/{cage}/force`. **Fully implemented.**
28. **Delete info / confirm view** — JSON counts of hens, sensors, production/env/feed/mortality logs and alerts for the delete modal. Entry: `GET /cages/{cage}/delete-info`, `confirm-delete`. **Fully implemented.**
29. **Bulk hen placement** — `GET/POST /cages/bulk-add` manual (per-slot) or auto (even distribution capped by `chickens_per_slot`) placement; TOCTOU guards (double-placement and capacity race), creates placement `CageTransfer`. Entry: `CageController::bulkAdd/storeBulkAdd`. **Fully implemented.**
30. **Print cage label** — Printable per-cage label view with hens grouped by slot. Entry: `GET /cages/{cage}/print-label` → `CageController::printLabel`. **Fully implemented.**
31. **Slot/hen JSON endpoints** — `GET /cages/{cage}/slots-json` and `GET /cages/slots/{slot}/hens-json` (with today's egg-collection status and last 5 cage notes) for the UI. **Fully implemented.**
32. **Resize collision detection** — Proactive warnings when shrinking would orphan occupied slots or exceed per-slot max occupancy (`app/Http/Controllers/CageController.php:699`). **Fully implemented** (warns; no hard save-time revalidation).

## 4. Chicken Inventory, Placement, Health, Mortality & Culling

33. **Hen registration (batch with IDs)** — Registers 1-100 hens at once, generating sequential `CHK-{YYYY}-{00001..}` IDs (DB-locked next-ID lookup), breeds restricted to a hard-coded 5-breed enum; hens start unplaced. Entry: `POST /chickens` → `ChickensController::store` (`app/Http/Controllers/ChickensController.php`). **Fully implemented** (breed list hard-coded — partial configurability gap).
34. **Inventory grid** — Filter/sort/search (cage, breed, status, tag, sort column), grouped by cage with a separate unplaced bucket; paginated by cage-group (6 per page) so one cage isn't split across pages; no-cache headers. Entry: `GET /chickens/inventory-list` → `ChickensController::inventoryList`. **Fully implemented.**
35. **Health events** — Log sick/treated/recovered events with date, description, notes. Entry: `POST /chickens/health-event` → `ChickensController::storeHealthEvent`. **Fully implemented.**
36. **Weight checks** — Log weight (kg, 0-20) per hen with notes. Entry: `POST /chickens/weight-check` → `ChickensController::storeWeightCheck`. **Fully implemented.**
37. **Culling** — Batch cull by CSV of IDs with reason (low_production/illness/aggression/age/other); deactivates hens, decrements slot occupancy, writes `CullingLog`; partial-success reporting. Entry: `POST /chickens/cull` → `ChickensController::storeCulling`. **Fully implemented.**
38. **Removal (sold/given)** — Batch removal with free-text reason + destination; same occupancy/deactivation logic; `Removal` log. Entry: `POST /chickens/removal` → `ChickensController::storeRemoval`. **Fully implemented.**
39. **Bulk slot-to-slot move** — CSV hen IDs, destination slot, optional move_count; capacity check, source/dest occupancy updates, `CageTransfer` trail. Entry: `POST /chickens/move` → `ChickensController::move`. **Fully implemented.**
40. **Bulk remove with mortality option** — Remove hens optionally recorded as mortality (aggregates per cage into `MortalityLog` + pivot rows, fires spike check). Entry: `POST /chickens/remove` → `ChickensController::remove` (spike check at line 596). **Fully implemented.**
41. **Mortality logging** — Per-cage daily mortality with reason enum (Disease/Heat Stress/Injury/Predator/Unknown/Other); FIFO deactivation (oldest placement first), per-hen pivot rows, occupancy decrement with negative guard; edit reduces reactivate hens, increases deactivate more. Entry: `POST/PUT/DELETE /mortality…` → `MortalityController` (`app/Http/Controllers/MortalityController.php`). **Fully implemented.**
42. **Mortality spike detection** — Creates `mortality_spike` alert when a cage's daily total ≥ 3 (hard-coded threshold, deduped per cage/date). Entry: `Controller::checkMortalitySpike` (`app/Http/Controllers/Controller.php:10-37`), called from Mortality & Chickens. **Fully implemented** — TODO in `MortalityController.php:274` to move threshold 3 to settings (open config gap).
43. **Occupancy reconciliation** — Compares stored vs actual active-hen occupancy per slot; `--apply` writes corrections. Entry: `layrate:reconcile-occupancy` (`app/Console/Commands/ReconcileSlotOccupancy.php`). **Fully implemented** (dry-run default).
44. **Cage transfer backfill** — Creates placement `CageTransfer` records for hens lacking one. Entry: `layrate:backfill-cage-transfers --dry-run`. **Fully implemented.**
45. **Chicken ID backfill** — Assigns `CHK-{year}-{n}` to legacy hens missing IDs, sequential by acquisition year. Entry: `layrate:backfill-chicken-ids --dry-run`. **Fully implemented.**
46. **Mortality log recovery** — Creates `MortalityLog` rows for deactivated-by-mortality hens lacking logs. Entry: `mortality:recover-logs --dry-run`. **Fully implemented.**
47. **Mortality hen-state repair** — Backfills `mortality_log_hens` pivot rows for orphaned logs. Entry: `mortality:repair-hen-state --apply`. **Fully implemented.**

## 5. Egg Logging & Production History

48. **Manual egg logging (per slot)** — Multi-slot logging with per-slot egg count, hen count, HDEP auto-computed; size breakdown (small/medium/large/jumbo) must sum to total; died-today correction adds today's dead hens to hen count; cap `egg_count ≤ hen_count`; upsert per slot+date (re-logging overwrites). Entry: `POST /eggs/logging` → `EggLoggingController::store` (`app/Http/Controllers/EggLoggingController.php`). **Fully implemented.**
49. **Size breakdown sync** — `syncSizeLogs` rewrites `EggSizeLog` rows for a production log; when no sizes given but eggs > 0 creates a single `unsorted` row. **Fully implemented.**
50. **Override PIN verification** — Before editing sensor-fed logs, PIN (or account password) must be verified; per-slot session stamp `override_verified_slot.{id}`; throttled 6/min. Entry: `POST /eggs/logging/verify-override` (throttle:6,1) → `EggLoggingController::verifyOverride`. **Fully implemented.**
51. **Edit / reset / delete production log** — Update count/sizes; reset zeroes count, HDEP, deletes size logs (appends `Reset to 0` note); delete is admin-only hard delete. Entry: `PUT/DELETE /eggs/logging/{log}` (+`/reset`). **Fully implemented.**
52. **Live egg-count SSE** — Server-Sent Events streaming per-slot `egg_count`/`hen_count` deltas (8s max runtime, cage filter default `CAGE-T`). Entry: `GET /eggs/logging/live-count` → `EggCountSseController` (`app/Http/Controllers/EggCountSseController.php`). **Fully implemented.**
53. **Live logs SSE** — Streams `log_update` events when a new production log appears (latest_id diffing, 10s runtime). Entry: `GET /eggs/logging/live-logs` → `EggLogsSseController`. **Fully implemented.**
54. **Log listing with filters** — Recent logs (paginate 20) and inline logs (paginate 5) with cage/breed/logged_via filters. Entry: `GET /eggs/recent-logs`, `GET /eggs/logging/logs`. **Fully implemented.**
55. **Production history page** — Day/week/month timeline via `ProductionTimelineService::aggregate`; lifetime eggs, cumulative running total pre-pagination, per-cage and per-size breakdowns (EggSizeLog as source of truth). Entry: `GET /egg-production-history` → `EggProductionHistoryController`. **Fully implemented.**
56. **`logged_via` field** — Intended to record entry source (`manual`/`sensor`) but `store()` never receives it via `validated()` (not in rules) so every manual save writes `'manual'` — a dead/aspirational branch. **Partially implemented** (harmless; dead code in `EggLoggingController.php`).

## 6. Egg Stock Management

57. **Stock live data** — Per-size totals + tray totals (ceil÷30), latest batches (paginate 5), available pools. Entry: `GET /eggs/stocks/live-data` → `EggStockController::liveData` (`app/Http/Controllers/EggStockController.php`). **Fully implemented.**
58. **Harvest/stock egg batch** — Add batch with size (small/medium/large/jumbo/unsorted), count, harvested date, optional cage/slot/source log; pool-overflow guarded via `createWithinPool` (row-locked `available = logged − stocked − preOrdered`). Entry: `POST /eggs/stocks`. **Fully implemented.**
59. **Auto-reclassification** — When stocking a sized batch exceeds the size pool, automatically deducts from `unsorted` `EggSizeLog` rows (locked, FIFO) to cover the shortfall. Entry: `EggStockController::stockWithAutoReclassify`. **Fully implemented.**
60. **Classify unsorted eggs** — Classify an unsorted total into sized buckets (must sum to the total; not enough unsorted → overflow rejection), writing both `EggSizeLog` and `EggStockBatch` per size. Entry: `EggStockController::storeClassified`. **Fully implemented.**
61. **Edit / delete stock batch** — Size/count/date update within pool (locked increase-only check); admin-only delete. Entry: `PUT/DELETE /eggs/stocks/{batch}`. **Fully implemented.**
62. **Egg weights config** — Small/medium/large/jumbo/fallback weights (1-500 g; defaults 50/58/65/73/60) driving FCR egg-mass estimates. Entry: `POST /eggs/stocks/egg-weights` → `EggStockController::saveEggWeights`. **Fully implemented.**
63. **Low-stock thresholds** — Per-size thresholds + freshness (fresh 7 days / aging 14 days, configurable) driving `low_stock` alerts and freshness status. Entry: `POST /eggs/stocks/thresholds` → `EggStockController::saveThresholds`; `EggStockBatch::checkLowStock`. **Fully implemented.**
64. **Low-stock alert** — Creates deduped `low_stock` alert when available ≤ threshold (`Low stock: {Size} eggs — {n} remaining`). Triggered after every stock write. **Fully implemented.**
65. **QR label per batch** — Encodes `LAYRATE|{id}|{date}|{cageCode}|{size}|{count}` → printable view. Entry: `GET /eggs/stocks/{batch}/qr`. **Fully implemented.**
66. **Egg stock audit** — Read-only reconciliation of per-size logged vs stocked vs pending pre-orders (unsorted exempts pre-orders). Entry: `layrate:audit-egg-stock --detail`. **Fully implemented.**
67. **Unsorted size-log backfill** — Creates one `unsorted` EggSizeLog per production log with eggs but no size logs. Entry: `layrate:backfill-unsorted-size-logs --dry-run`. **Fully implemented.**

## 7. Pre-Orders & Egg Sales

68. **Pre-order CRUD** — Customer, reference, size, count, requested/fulfillment dates, status (pending/fulfilled/cancelled); admin-only delete. Entry: `GET/POST/PATCH/DELETE /eggs/pre-orders…` → `PreOrderController` (`app/Http/Controllers/PreOrderController.php`). **Fully implemented.**
69. **Pool reservation** — `createWithinPool`/`updateWithinPool` reserve eggs against pending pre-orders with `lockForUpdate`; throws `OverflowException("Only {n} {size} egg(s) in stock…")`; fulfilling/cancelling releases reservation; count reductions free capacity. **Fully implemented.**
70. **Stock summary panel** — Per size: logged, stocked, committed (pending pre-orders), 7-day forecast, pool = stocked − committed, available (max 0), deficit. **Fully implemented.**
71. **Demand depletion alert** — `stock_depletion` alert when any size pool goes negative (`Pre-order demand for {size} eggs exceeds supply by {n} eggs ({t} trays)`), deduped per size/day. Entry: `PreOrderController::runDepletionCheck`. **Fully implemented.**
72. **7-day size forecast** — Mirrors forecast logic: avg HDEP of last 14 days (fallback 85%) × total active hens × 7 × per-size historical distribution. Entry: `PreOrderController::forecastSize`. **Fully implemented.**
73. **Tray count / humanized egg labels** — `getTrayCountAttribute` (ceil÷30) and `eggLabel()` (dozen/half-dozen/tray). **Fully implemented.**

## 8. Feed Management & Feed Conversion Ratio (FCR)

74. **Feed batch CRUD** — Brand, crude protein (0-100), total quantity, unit cost, received date, low-stock threshold, notes; auto `F-{YYYY}-{001..}` batch codes (DB-locked). Entry: `POST/PUT /feed/batch…` → `FeedController::storeBatch/updateBatch` (`app/Http/Controllers/FeedController.php`). **Fully implemented.**
75. **Batch delete guard** — Cannot delete a batch referenced by consumption logs (returns count). Entry: `GET /feed/batch/{id}/delete-check`, `DELETE /feed/batch/{id}` (admin). **Fully implemented.**
76. **Direct feed consumption** — Per-cage consumption log with batch, date/time, kg, recorded_by, `source='direct'`. Entry: `POST/PUT/DELETE /feed/consumption…`. **Fully implemented.**
77. **Whole-farm feeding distribution** — One farm entry (batch, date, total_kg) distributed across active cages with hens using largest-remainder cent-level allocation (Σ distributed == total_kg exactly); edits delete + redistribute; cascade delete via FK. Entry: `POST/PUT/DELETE /feed/farm-entry…` → `FeedController::store/update/destroyFarmFeedEntry`. **Fully implemented.**
78. **Distributed-entry edit guard** — `source='distributed'` rows can only be edited/removed via the whole-farm entry (refused otherwise). **Fully implemented.**
79. **Feed low-stock alert** — Deduped `low_stock` alert when batch `remaining_kg ≤ threshold` after every consumption/farm-entry write. Entry: `FeedController::checkLowStock`. **Fully implemented.**
80. **Feed KPIs** — Avg CP% across batches, feed this week (kg), avg per-cage per-day, monthly feed cost (Σ kg×unit_cost), live paginated lists. Entry: `GET /feed/live-data`. **Fully implemented.**
81. **FCR calculation** — `FCR = kg feed ÷ kg egg mass`; egg mass from `EggSizeLog` counts × configured per-size weights (fallback weight otherwise); cage-wide and farm-wide over date ranges. Entry: `GET /feed/fcr-data` → `FcrCalculator` (`app/Services/FcrCalculator.php`). **Fully implemented.**
82. **FCR timeline** — Day/week/month bucketed FCR using `ProductionTimelineService::periodForDate`; null when egg mass is 0. **Fully implemented.**
83. **FCR status badges** — Good ≤ 2.5, Warning ≤ 4.0, Critical > 4.0 (config `config/fcr.php`), N/A when null; Tailwind badge classes. Entry: `FcrStatusService` (`app/Services/FcrStatusService.php`). **Fully implemented.**

## 9. Environmental Monitoring & Thresholds

84. **Live environment tab** — Per-cage temperature/humidity with status badges via `EnvironmentStatusService` (Alert strictly outside range, Watch at boundary, OK inside); trend ranges (24h/week/month). Entry: `GET /environment` → `EnvironmentController::index`; `GET /environment/live-data` → `liveData` (`app/Http/Controllers/EnvironmentController.php`). **Fully implemented.**
85. **Environment logs** — Daily per-cage summary (avg/min/max temp+hum, reading count) with date/cage filters. Entry: `GET /environment/logs` → `EnvironmentController::logs`. **Fully implemented.**
86. **Manual override of a day's reading** — Deletes that cage/date's raw readings and inserts a single noon override row so the AVG aggregation produces the override value. Entry: `PUT /environment/logs/{cage}/{date}`. **Fully implemented.**
87. **Threshold config** — temp 0-50°C, hum 0-100%, min≤max enforced; drives badges and alerts. Entry: `POST /environment/thresholds` → `EnvironmentController::saveThresholds`. **Fully implemented.**
88. **Environment alert generation** — Creates deduped `temperature_low/high`, `humidity_low/high` alerts per cage/day on out-of-range readings. Entry: `EnvironmentAlertService::check` (`app/Services/EnvironmentAlertService.php`), called from ingestion and `alerts:check-environment`. **Fully implemented.**
89. **Scheduled alert recheck** — Re-checks last 24h of environment logs every 15 min. Entry: `alerts:check-environment` → `CheckEnvironmentAlerts` (`app/Console/Commands/CheckEnvironmentAlerts.php`). **Fully implemented.**
90. **Daily average computation** — Nightly per-cage avg/min/max temp+hum aggregation (defaults to yesterday; `--date` override). Entry: `environment:compute-daily-averages`. **Fully implemented.**
91. **Status boundary convention documented** — Alert = strictly outside, Watch = exactly at boundary, OK = strictly inside, shared across badges, alerts, and the mobile API. **Fully implemented.**

## 10. Hardware, Devices & Sensor Registry

92. **Hardware item CRUD** — Register hardware (DHT22 / IR_breakbeam / relay / other; status active/faulty/removed/spare; serial unique; dates). Conditional integrity: spares must be unassigned; break-beams require a slot (never a cage); DHT22/relay require a cage (never a slot); one active DHT22 per cage. Entry: `GET/POST/PUT/DELETE /hardware…` → `HardwareItemController` (`app/Http/Controllers/HardwareItemController.php`). **Fully implemented.**
93. **Hardware live data** — Summary counts (active break-beams, active DHT22s, active/faulty totals) + paginated list with latest env reading / occupancy reading eager-loads. Entry: `GET /hardware/live-data`. **Fully implemented.**
94. **Device API-key management (admin)** — Create devices with `lr_` + 40-random-char keys, hashed at rest (`api_key_hash`), shown once; regenerate/destroy. Entry: `POST /devices`, `POST /devices/{device}/regenerate-key`, `DELETE /devices/{device}` → `DeviceController` (`app/Http/Controllers/DeviceController.php`). **Fully implemented.**
95. **Staleness monitoring** — Flags stale sensors per type (DHT22 60 min, IR 24 h, calibration 90 days); creates/clears `sensor_stale`-class alerts; `--dry-run`. Entry: `hardware:check-staleness` → `CheckHardwareSensorStaleness`. **Fully implemented.**
96. **Spare inventory for assignment** — `availableForAssignment` scope (spare OR active-unassigned) powers cage sensor assignment counts. **Fully implemented.**
97. **`relay` device type** — Listed in `DEVICE_TYPES` and validated, but there is **no relay actuation anywhere** in the codebase (firmware has no relay/fan output; ingestion rejects `relay` as non-ingestible). **Partially implemented** — device label only, no control path.
98. **Incubator status** — `IncubatorStatus` model exists but its table was created then dropped by migrations `2026_07_15_000002/000003`; no routes, controllers, or views reference it. **Dead code** (orphaned model).

## 11. Sensor Data Ingestion (Laravel API, Serial Bridge & Arduino Firmware)

99. **Sensor readings API** — `POST /api/sensor-readings` authenticated by `X-Device-Key` (`DeviceAuth` middleware, bcrypt-verified against device hashes). Accepts arrays of readings (serial_number + temp/hum and/or count + recorded_at), transactional, per-reading error accumulation. Entry: `routes/api.php:20` → `SensorIngestionController::store` (`app/Http/Controllers/SensorIngestionController.php`). **Fully implemented.**
100. **DHT22 ingestion** — One `EnvironmentalLog` per cage+timestamp (upsert); triggers environment alerts. **Fully implemented.**
101. **IR break-beam ingestion** — Records `SensorOccupancyReading` (5s same-count debounce), infers `ProductionLog` per slot+date with HDEP, refuses to clobber manual overrides, rejects count regressions (Arduino reset detection → `sensor_reset` alert). **Fully implemented.**
102. **Occupancy mismatch alert** — When sensor count ≠ slot occupancy, deduped `occupancy_mismatch` alert per cage/date. **Fully implemented.**
103. **Response semantics** — 200 clean / 207 partial / 422 nothing-accepted (rolls back) / 500 with debug-gated error leak. **Fully implemented.**
104. **Arduino firmware** — `LayRate-Arduino/src/main.cpp`: IR break-beam on pin 4 (`INPUT_PULLUP`, 1s cooldown) and DHT22 on pin 2 (2s interval, range-validated, 3 retries), change-only serial blocks at 9600 baud. **Fully implemented** (sensor side; no actuation).
105. **Serial bridge (Python)** — `serial-bridge/bridge.py`: parses `Count/Beam/Temp/Humidity` blocks, DTR-reset settling window (discards boot output), posts to the API with `X-Device-Key`, logs 207 partial failures with per-reading errors, auto-detects Arduino port, JSON config overlay. **Fully implemented.**
106. **Alerts API for devices** — `GET /api/alerts`, `PUT /api/alerts/{id}/read` (DeviceAuth-protected) for hardware-side alert polling. Entry: `routes/api.php:23-27` → `AlertController::apiIndex/apiMarkRead`. **Fully implemented.**

## 12. Alerts & Notifications

107. **Notification center** — Full page listing alerts desc, paginate 25. Entry: `GET /notifications` → `AlertController::index`. **Fully implemented.**
108. **Grouped notification table** — Paginate 20 grouped by Eggs (mortality_spike/stock_depletion/occupancy_mismatch/low_stock), Temperature (low/high), Humidity (low/high), Other. Entry: `GET /notifications/table`. **Fully implemented.**
109. **Acknowledge modal** — Session-sticky acknowledgment of alert IDs (`alerts_acknowledged_ids`); does NOT touch DB `is_read`. Entry: `POST /alerts/acknowledge-modal`. **Fully implemented** (session-scoped by design).
110. **Mark read / read all** — `POST /alerts/{alert}/read`, `POST /alerts/read-all`. **Fully implemented.**
111. **Mobile/hardware alert feed** — `GET /api/alerts` (unread-only default, `?all=true`, limit≤100) and mark-read. **Fully implemented** (note: `total` reflects returned collection count, not DB total).
112. **Alert origin inventory** — `mortality_spike` (≥3/day/cage), `temperature_low/high`, `humidity_low/high`, `low_stock` (egg stock + feed), `stock_depletion` (pre-orders), `occupancy_mismatch`, `sensor_reset`, plus `runner_offline` (runner health). All created programmatically; none created via AlertController. **Fully implemented.**

## 13. Analytics, Reports & Exports

113. **Analytics: performance mode** — Per-cage ranking by avg HDEP + total eggs (last 7/30/90 days) via join through cage_slots→cages. Entry: `GET /analytics` → `AnalyticsController::index` (`app/Http/Controllers/AnalyticsController.php`); `GET /analytics/data` JSON. **Fully implemented.**
114. **Analytics: all/farm mode** — Aggregated egg + feed time series for all cages. **Fully implemented.**
115. **Analytics: single-cage mode** — Egg/HDEP/feed series + KPIs (avg/best/worst HDEP, breed, flock age). **Fully implemented.**
116. **Reports page** — Five report types (production, feed, environment, mortality, egg_stock) + combined `all`; date/cage/reason filters; chart payloads gated by `charts` checkbox. Entry: `GET /reports` → `ReportController::index` (`app/Http/Controllers/ReportController.php`). **Fully implemented.**
117. **Report AJAX preview** — `GET /reports/data` returns rendered partial + charts + meta. **Fully implemented.**
118. **CSV export** — Streamed CSV per type or multi-section for `all` (blank-row/label-row separated), `layrate_{type}_{range}.csv`. Entry: `GET /reports/csv`. **Fully implemented.**
119. **Excel export** — Multi-sheet `AllReportsExport` / single `ReportSheetExport` with embedded chart images (base64 PNG ≤5MB decoded to temp files, unlinked via shutdown function, 180px drawings). Entry: `GET/POST /reports/excel`. **Fully implemented.**
120. **PDF export** — dompdf A4 portrait; chart data-URIs embedded; retries once without charts on failure; 256M memory cap. Entry: `GET/POST /reports/pdf`. **Fully implemented.**
121. **No-store report responses** — `Cache-Control: no-store` on report views to defeat Chrome bfcache resurrecting stale print JS. **Fully implemented.**
122. **Environment status in reports** — Alert if temp>30 or hum>70, Watch if temp>28.5 or hum≥70 (hard-coded report-specific thresholds). **Partially implemented** (not wired to configured thresholds).

## 14. Forecasting (Laravel UI + Python Forecast API)

123. **Forecast workspace** — Scope (cage/breed/farm), horizon, calendar; `C01`/`C03` excluded from scope dropdowns; admin-gated generate/clear/import. Entry: `GET /forecast` → `ForecastController::index` (`app/Http/Controllers/ForecastController.php`, ~1900 lines). **Fully implemented.**
124. **Forecast generation (auto/manual)** — Runs `forecast-api/forecast_runner.py` via Symfony Process (300s timeout, DB env passed); auto mode from historical data, manual mode requires all 9 manual fields else silently downgrades to auto; pre-deletes today's stale rows per scope before regenerating. Entry: `POST /forecast/generate` (admin) → `ForecastController::generate`. **Fully implemented.**
125. **Data sufficiency gate** — Requires ≥90 records per scope (cage/breed count or farm distinct-date count). Entry: `ForecastController::checkForecastDataSufficiency`. **Fully implemented.**
126. **Turbo-stream live updates** — After generate, replaces `forecast-workspace` + `production-calendar` frames when `Accept: text/vnd.turbo-stream.html`. **Fully implemented.**
127. **Forecast chart data** — `GET /forecast/data` returns historical vs forecast series, metrics, recommended model, scope label/color. **Fully implemented.**
128. **Clear forecast** — Deletes today's rows per scope. Entry: `POST /forecast/clear` (admin). **Fully implemented.**
129. **Excel template download** — Pre-filled prefilled Date/Cage_Code/Breed/Flock_Age_Weeks/Hen_Count (sheet-protected) + editable input columns, 90-day default or custom range. Entry: `GET /forecast/template` → `generate_forecast_sheet.py`. **Fully implemented.**
130. **Excel import (single-phase)** — Runs `import_forecast_input.py` to upsert `forecast_input_records` keyed by (date, cage_code). Entry: `POST /forecast/import` (admin). **Fully implemented.**
131. **Import preview/confirm (approve flow)** — Two-phase: upload persisted to `storage/app/private/forecast-imports/`, preview runs the importer with `--preview`; confirm validates the temp path stays inside the temp dir (path-traversal guard) and executes, always unlinking. Entry: `POST /forecast/import/preview`, `POST /forecast/import/confirm` (admin). **Fully implemented.**
132. **Forecast exports** — CSV (`target_date,predicted_egg_count,…`), Excel (base64 chart PNG ≤5MB into `ForecastExport`), PDF (chart data-URI, retry without chart). 422 JSON when nothing to export (deliberately not a redirect to avoid silent download). **Fully implemented.**
133. **Production data dump** — Streams all `forecast_input_records` as 11-column CSV. Entry: `GET /forecast/input-records/download`. **Fully implemented.**
134. **Forecast input sync** — Nightly aggregation of production_logs→cage_slots→cages into `forecast_input_records` (per cage, optional from/to). Entry: `forecast:sync-input-records` → `SyncForecastInputRecords`. **Fully implemented.**
135. **Forecast debug** — Dumps input-record counts, cages, breeds, date range, sufficiency per scope. Entry: `forecast:debug`. **Fully implemented.**
136. **Forecast date rules** — Start strictly after today, ≤30 days out (single source of truth for validation + frontend). Entry: `ForecastRules::minStartDate/maxStartDate` (`app/Forecast/ForecastRules.php`). **Fully implemented.**
137. **Python forecasting engine (V5)** — `forecast-api/ForecastingV5.py`: XGBoost ensemble (seeds 42/123/999) + SARIMA(1,1,1)(1,1,1,7); features Breed, Live_Hens, Flock_Age_Weeks, Temperature_C, Humidity_Percent, Crude_Protein_Percent, Total_Feed_Consumed_kg, Monthly_Mortality, Heat_Stress + lags/rolling (XGB_LAGS [1,2,3,7,14]); min 90 records; heat stress at temp≥30 / hum≥80; max 30-day horizon; DB loader via env. Entry: `forecast-api/ForecastingV5.py`; runner: `forecast-api/forecast_runner.py`. **Fully implemented.**
138. **Legacy forecasting engine (v1)** — `forecast-api/Forecast.py`: older variant without rolling-window/heat-stress features and no DB loader; superseded by V5. **Dead code** (kept; runner targets V5).
139. **Forecast Dockerfile** — `forecast-api/Dockerfile` + `requirements.txt` packaging the Python API. **Partially implemented** (present; runtime uses host venv via `resolvePythonBinary`, not Docker).

## 15. Mobile Companion (Flask Mobile API & React Native/Expo App)

140. **Flask mobile API — register/login** — `POST /api/register`, `POST /api/login` against MySQL users (bcrypt `$2b`↔`$2y` interop), returns bearer token persisted in local SQLite `layrate_mobile.db`; on success calls the nftables authorize script. Entry: `mobile-api/app.py`. **Fully implemented** (Flask side).
141. **Flask mobile API — alerts** — `GET /api/alerts` (filter/limit/offset), `PUT /api/alerts/{id}/read`, `PUT /api/alerts/read-all` against MySQL `alerts`. **Fully implemented.**
142. **Flask mobile API — dashboard status** — Latest env reading, today's egg total, total hens from MySQL. Entry: `GET /api/dashboard/status`. **Fully implemented.**
143. **Flask mobile API — environment live** — Per-cage latest readings with OK/Watch/Alert classification mirroring `EnvironmentStatusService`, 30-min staleness, defaults for cages without data. Entry: `GET /api/environment/live`. **Fully implemented** (thresholds hard-coded as fallback; configurable via next endpoint).
144. **Flask mobile API — thresholds** — `GET/PUT /api/environment/thresholds` reads/writes MySQL `settings` (temp_min/max, hum_min/max, cross-field validation). **Fully implemented.**
145. **Flask mobile API — hardware health** — Summary counts of hardware by status/type. Entry: `GET /api/hardware/health`. **Fully implemented.**
146. **Flask mobile API — discovery & health** — `GET /api/ping` (unauthenticated auto-discovery), `GET /api/health`; mDNS `_http._tcp` "Layrate Server" advertisement; `FLASK_HOST/PORT/DEBUG` env overrides. **Fully implemented.**
147. **Flask mobile API — auth guard** — `require_auth` Bearer-token decorator on all protected routes; 404/405/500 JSON error handlers. **Fully implemented.**
148. **RN app — login & auth** — `contexts/AuthContext.tsx` verifies credentials against mock users in AsyncStorage via `lib/storage.ts`; `isAdmin` from role. **Stubbed / mock-only** — no network; must be wired to the Flask API.
149. **RN app — tab screens** — Home (`(tabs)/index.tsx`), Cages, Egg Logging, Environment, More; plus stack screens Analytics, Feed, Forecast, Mortality, Reports, Account, About. **Stubbed / mock-only** — all render `mock/` data (`mock/types.ts`, `mock/production_logs.ts`, etc.).
150. **RN app — UI kit** — 22 components (`components/ui/`): Badge, Button, CageBadge, CageChip, Card, EmptyState, InlineFieldError, Input, LoadingSkeleton, Modal, ProgressBar, SectionTitle, SegmentedControl, Toast, Toggle, icons, collapsible, etc. **Fully implemented** (presentational only).
151. **RN app — real API integration** — `MOBILE_FEATURE_AUDIT.md` documents the required endpoints for every screen (e.g. `GET /api/dashboard/summary`, `POST /api/production-logs`, `GET /api/feed-batches`, `POST /api/forecasts/generate`); **none of these endpoints exist in the Flask API yet**. **Stubbed / planned.**
152. **RN app — offline storage** — `lib/storage.ts` persists logged-in user and mock seed data; `lib/seed.ts` seeds mock content. **Stubbed / mock-only.**
153. **Cage capacity model mismatch** — Mobile `Cage` type uses flat `capacity`; Laravel uses `cage_slots` with per-slot occupancy (noted in `MOBILE_FEATURE_AUDIT.md`). **Partially implemented** — needs reconciliation during real integration.

---

### Cross-cutting findings (dead code / stubs / TODO)

- **IncubatorStatus** — orphaned model, table dropped. Dead code.
- **`logged_via`** — always `'manual'` from manual logging; dead branch.
- **`Forecast.py` (legacy)** — superseded by `ForecastingV5.py`. Dead code (retained).
- **`relay` device type** — label-only; no actuation firmware/API path. Stub.
- **`trendApi` / `dashboardCalendar`** — do not exist as routes (calendar is `DashboardController::calendar`). Non-issues.
- **Mortality spike threshold (3)** — hard-coded; TODO in `MortalityController.php:274` to move to settings.
- **Firmware reset regression** — server-side `sensor_reset` alert handles Arduino reboot resets; firmware prints `Count: 0` at boot (bridge discards via settling window).
- **RN mobile app is entirely mock-only** — no HTTP client code; Flask API covers auth/alerts/environment/hardware only. Integration is the largest planned gap.
- **FCR config** — thresholds live in `config/fcr.php` (2.5 / 4.0); FcrStatusService comment claims they're adjustable via egg-weight UI, but only weights (not FCR thresholds) are editable there. Partial mismatch.
