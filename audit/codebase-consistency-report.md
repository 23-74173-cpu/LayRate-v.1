# LayRate — Codebase Consistency Audit

**Scope:** Laravel 12 app + Blade/Turbo frontend — 114 Blade views, 31 controllers, 38 models, 16 console commands, 149 web routes, 5 API routes.
**Method:** structural survey + targeted grep verification. Findings are *consistency* issues (not a bug pass), each verified against source.
**Generated:** 2026-08-22

---

## 0. Headline finding

`DESIGN-SYSTEM.md` — the file the design tokens and several components cite
(`resources/css/app.css` @theme comments: "… (DESIGN-SYSTEM.md §2 / §3.2 / §4 / §5 / §6 / §9)", plus
`resources/views/components/status-badge.blade.php` and `resources/views/environment/_live-data.blade.php`)
— **does not exist anywhere in the repo.** The UI's canonical design document is missing, so the
design drift in §2 is largely a symptom of an undocumented system.

---

## 1. CODE-LEVEL INCONSISTENCY

### 1.1 Naming convention drift

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `routes/web.php` + all controllers | Controller actions mix CRUD verbs (`store`, `update`, `destroy`) with ad-hoc domain verbs (`storeBulkAdd`, `storeConsumption`, `saveThresholds`, `removeCell`, `batchUpdatePosition`, `regenerateKey`) with no rule for which applies | Pick one convention (resource verbs per entity, or a consistent `<verb><Entity>`) and document it |
| `routes/web.php:59-62` vs `AnalyticsController` | Same period-HDEP data exposed via differently-named methods (`DashboardController::cagePerformance/productionHistory` vs `AnalyticsController::cageOrAll`) | Name the shared computation identically once extracted (see §1.5) |
| `AlertController.php:87-95`, `CageController.php:552-560`, `EggCountSseController.php:83,90` vs `AnalyticsController.php:200-218`, `ReportController.php:523,562` | JSON payloads mix snake_case keys (`cage_code`, `is_read`, `triggered_at`) and camelCase keys (`cageCode`, `topEggsCage`, `avgHdep`, `cageId`) | Pick one casing for all JSON keys and apply everywhere |
| migrations `…012851` (egg_size_logs), `…012852` (egg_stock_batches), `…000012` (mortality_logs), `…010002` (sensor_occupancy_readings) | Same concept named 4 ways: `egg_count` (production_logs, pre_orders) vs `count` (egg_size_logs, egg_stock_batches, mortality_logs) vs `reported_count` (sensor_occupancy_readings) vs `predicted_egg_count` (forecasts); `hen_count` (production_logs) vs `current_occupancy` (cage_slots) | Standardize `egg_count` / `hen_count` names; rename via migration |
| `2026_08_13_000005_create_forecast_runs_table.php:23-26`, `forecast_input_records`, `forecasts` | Forecast tables use 3 identifier strategies: `cage_id`+`cage_code` dual (forecast_runs), `cage_code` string only (forecast_input_records), `cage_id` FK only (forecasts) | Pick one forecast identity strategy and keep it |
| `resources/views/**` | 4 coexisting partial conventions: `_underscore-prefix` (`egg-logging/_logs`), `-skeleton` suffix pairs (24 files), `partials/` subfolders (7 chicken modals + top-level `partials/`), kebab `components/` | Keep `components/`; choose one partial scheme (recommend `_prefix`) |
| `cages/label.blade.php` vs `cages/print-label.blade.php` | Near-duplicate label views; only `print-label` is routed | Delete the unrouted `label.blade.php` |
| `forecast.blade.php:1002,1013`; `environment/_live-data.blade.php:133` | JS callback naming aberration `closeImportModalFn()` vs `openImportModal()`; redundant `window.initEnvCharts = function initEnvCharts()` | Align naming (`closeImportModal`), drop redundant binding |
| Controllers | Controller class naming mixes plural and singular (`ChickensController`, `EggLogsSseController`, `SettingsController` vs `CageController`, `PreOrderController`, `NoteController`, `MortalityController`) | Standardize (singular entities, consistent convention) |

### 1.2 Validation inconsistency

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `app/Http/Requests/StoreHardwareItemRequest.php` | The **only** FormRequest is dead code (0 references) — it duplicates rule-for-rule `HardwareItemController::hardwareValidator()` (lines 30-90) | Wire it into `store`/`update` and remove `hardwareValidator()`, or delete the Request |
| 13 controllers: `SettingsController`, `SensorIngestionController`, `ChickensController`, `ForecastController`, `AccountController`, `EggLoggingController`, `EnvironmentController`, `DeviceController`, `SystemTimeController`, `EggStockController`, `MortalityController`, `AuthController`, `CageController` | Inline `$request->validate([...])` | Move payload validation to FormRequests |
| 9 controllers: `EggLoggingController`, `EggStockController`, `PreOrderController`, `FeedController`, `CageController`, `ChickensController`, `MortalityController`, `NoteController`, `HardwareItemController` | Manual `Validator::make` (with `$validator->after()` / `passes()`) instead of `->validate()` or FormRequests | Convert to FormRequests |
| Above three rows | Three validation styles + one orphan FormRequest coexist with no rule for which module uses which | Standardize on FormRequests for all POST/PUT/PATCH payloads |

### 1.3 API / error-handling / response-shape inconsistency

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `AlertController.php:68`, `EggLoggingController` (verifyOverride) | Success envelope `['ok' => true]` vs the dominant `['success' => true]` used everywhere else | Use one envelope (recommend `success`) |
| `MobileAppController::dashboardStatus`, `PreOrderController::poolData`, `EggStockController::poolData`, `RelayCommandController::show` | Raw payload keys with no success envelope (`temperature…`, `['pools'=>…]`, `['relay'=>…]`) | Wrap all JSON in a standard envelope |
| `FeedController`, `EggLoggingController`, `EggStockController`, `ChickensController`, `ForecastController`, `MortalityController` vs `CageController:386,404`, `SensorIngestionController`, `SettingsController::backupNow`, `DeviceAuth.php:23`, `ApiAuth.php:18` | Error channel mixes `errors` (validator-shaped) vs `error` (string) vs `message` (string) | One error shape, e.g. `{success:false, error:{…}}` |
| `EnvironmentController:208-211` (404 `success:false`) vs `EggCountSseController` (raw `response('Cage not found',404)`) | 404 expressed inconsistently | Consistent 404 JSON/event contract |
| `ForecastController:513,519,606` | User/validation-class failures returned as HTTP 500 | Return 422 for validation/business-rule failures |
| `SensorIngestionController:314` | Only 207 partial-success response in the app; undocumented | Document or align with other 2xx usage |
| `AlertController` (in both `web.php` and `api.php`) vs `EggLoggingController`/`FeedController` (branch on `wantsJson()`) | Web + API served from one controller two different ways | Separate Web/API controllers or gate `wantsJson()` everywhere |
| `EggCountSseController`, `EggLogsSseController`, `EnvironmentRelaySseController` | 3 SSE endpoints: different heartbeat cadence (1s/3s/1s) and non-stream error responses (`response('…',404)`) | Shared SSE contract: heartbeat, `event: error`, reconnect policy |

### 1.4 Eloquent usage inconsistency

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `ForecastController` (`:40-56`, `:700-754`, `:912-922`), `SyncForecastInputRecords.php` | `forecast_input_records` accessed only via raw `DB::table()` — **no Eloquent model** exists for a table with 3 migrations, while sibling `Forecast`/`ForecastRun` are models | Create a `ForecastInputRecord` model |
| `SensorIngestionController:59,294,302,318` vs `CageController`, `EggStockController`, `ChickensController`, `MortalityController`, `PreOrder`, `Hen` | Manual `DB::beginTransaction()`+commit/rollback vs `DB::transaction(closure)` | Use the closure form everywhere |
| `EggLoggingController:223-236` vs `MortalityController` | Same mortality data queried raw (`DB::table('mortality_logs')->join('mortality_log_hens')`) in one controller, Eloquent in the other | Use the Eloquent relation |
| `DashboardController:102-121` and `AnalyticsController:48-66` | Byte-identical raw `join('cage_slots')`+`selectRaw` period stats in two controllers | Extract a model scope/service |
| `CageController:534-544,763-769` | `DB::table('cage_slots')->update()` slot renumbering inside flows that otherwise use `CageSlot::create/update` | Use model updates |

### 1.5 Dead code & duplicated logic

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `DashboardController.php` + `AnalyticsController.php:39` (service-locates `app(DashboardController::class)`) | HDEP/period computation duplicated verbatim; a controller instantiates another controller to reuse it | Extract `PeriodStatsService` / model scope |
| `PreOrderController.php:219-274` vs `ForecastController::farmHistorical` | 14-day avg-HDEP×hens forecast re-implemented independently (own 85% fallback, 25%-per-size split) | Extract a `ForecastEstimator` service |
| `PreOrderController::poolData` vs `EggStockController::poolData`/`EggStockBatch::getAvailablePools()` | "Available egg pool" computed two ways (raw `stocked−committed` vs model method) | Route both through the model method |
| `Controller::checkMortalitySpike`, `EnvironmentAlertService`, `SensorIngestionController:328-375`, `FeedController::checkLowStock`, `EggStockBatch::checkLowStock`, `PreOrderController::runDepletionCheck` | "Create alert if not already today" written 6 times | Extract `AlertsService::createIfAbsent()` |
| `EnvironmentController:47-52`, `ReportController:336-337`, `EnvironmentStatusService`, `EnvironmentAlertService` | Temp>30/hum>70 thresholds encoded in 4 places despite the service claiming to be the authority | Make the service the single source |
| `ChickensController::paginateCageGroups`, `EggProductionHistoryController::paginateCollection`, `ReportController::paginateCollection/paginateSection` | Manual `LengthAwarePaginator` slicing re-implemented 3× | One shared pagination helper |
| `MobileAppController.php` | Unreachable controller — no route references it (only a test registers a test route); mobile API is `mobile-api/app.py` | Wire routes or delete |
| `app/Models/IncubatorStatus.php` | Dead model: table created (`…2026_07_15_000002`) then dropped (`…2026_07_15_000003`); zero references | Delete |
| `app/Http/Middleware/ApiAuth.php` | Unused middleware (0 references; sibling `DeviceAuth` is used) | Delete or wire in |
| `ForecastController::respondAfterGenerate()` (`:1078-1097`) | Never called — superseded by `respondQueued` | Delete |
| `ForecastController:1327` | `exportCsv` reads `predicted_hdep` after it was renamed to `predicted_egg_count` (`2026_07_02_000000`) | Use `predicted_egg_count` |
| `MortalityController.php:272-276` | Trailing empty doc-comment function stub | Remove |
| `AccountController.php:94`, `CageController.php:34,1097` | Same setting key `farm_grid_rows` defaults to `6` in one place and `4` in two others (`Setting.php:28` acknowledges the weirdness) | Single `Setting::DEFAULTS` registry |
| `DashboardController` (`:51-52,96-98,138-140`), `AnalyticsController` | 7/14/30-day date-range scaffolding re-derived while `ReportingDateService` exists | Add `ReportingDateService::range()/labels()` |

---

## 2. UI/DESIGN INCONSISTENCY

**Reference (de-facto design system):** the `@theme` block in `resources/css/app.css` — colors `primary:#0075de`, `secondary:#213183`, `ink:#1f1f1f`, `ink-muted:#615d59`, `ink-faint:#a39e98`, `hairline:#e6e6e6`, `canvas-soft:#f6f5f4`, ok/watch/alert/cage/sticker palettes; radius `xs4 sm5 md8 lg12 xl16`; spacing `xxs4..xxl32`; font Inter. The canonical `DESIGN-SYSTEM.md` does not exist (§0).

### 2.1 Token usage — the systemic drift

Token-backed utilities are almost never used; raw hex is the norm. Verified counts across all 114 views:

| Value | Occurrences | Token it should map to | Token usage today |
|---|---|---|---|
| `#6B7280` | 486 | `text-ink-muted` | 25 |
| `#D9D9D9` | 395 | `border-hairline` | 17 |
| `#333333` / `#333` | 306 | `text-ink` / `ink-secondary` (`#31302e` used 36×) | ~0 |
| `#002D5E` / `#001F42` / `#001b3d` | ~280 | new `--color-navy` (or `primary-active`/`secondary`) | — (not tokenized) |
| `#1f1f1f` | 261 | `text-ink` | — |
| `#e6e6e6` | 240 | `hairline` | (borders use `border-hairline` 17×) |
| `#615d59` | 236 | `text-ink-muted` (as inline hex) | 25 |
| `#102A4C` | 101 | legacy navy — clearest "not migrated yet" tell | — |
| `#F5F6F8`/`#f8f8f8`/`#F7F7F5`/`#F9F9F7` | ~100+ | `canvas-soft` | — |
| `#9CA3AF` | 76 | `text-ink-faint` | 6 |

**Key finding:** even the shared components are off-token — `components/card.blade.php` (`border-[#D9D9D9]`, `bg-[#F9F9F7]`, `text-[#333333]`), `components/button.blade.php` (`bg-[#002D5E]`, `text-[#6B7280]`), `components/underline-tabs.blade.php` (`#D9D9D9`, `#002D5E`, `#6B7280`). So drift is **baked into the design system itself**; every consumer inherits it.

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `resources/css/app.css` @theme, `components/status-badge.blade.php`, `environment/_live-data.blade.php` | Reference a `DESIGN-SYSTEM.md` that doesn't exist | Author it from the @theme tokens, or strip the references |
| `components/card.blade.php`, `components/button.blade.php`, `components/underline-tabs.blade.php`, `components/loading-modal`, `components/alerts-modal`, `components/chart-canvas` | Components hardcode `#D9D9D9`, `#F9F9F7`, `#002D5E`, `#6B7280`, `#333333` instead of tokens | Rewrite components on tokens; define `--color-navy` |
| Every module view (KPI accents) | ~20 ad-hoc hexes for accents — `#2D7D46`, `#1D4E8F`, `#C2703E`, `#6B4C8A`, `#2C7C91`, `#C2405C` — duplicated across `dashboard/_metric-cards`, `eggs/pre-orders`, `eggs/stocks`, `forecast` | Add `--color-kpi-*` scale or reuse cage/ok/watch tokens |
| `reports/_report-table.blade.php:5`, `reports/pdf.blade.php:38`, `mortality.blade.php:9,103-106`, `mortality/_logs.blade.php:20-28`, `system-time.blade.php:29`, `forecast.blade.php:268` | Legacy Bootstrap alert palette (`#F8D7DA`, `#721C24`, `#FFF3CD`, `#664D03`, `#B45309`…) | Use `ok-*`/`watch-*`/`alert-*` tokens |
| `dashboard/_metric-cards.blade.php`, `eggs/pre-orders.blade.php:21-25`, `eggs/stocks.blade.php:24-30` | Same KPI color set encoded per-page by value | Centralize in tokens |
| `profile.blade.php`, `chickens/partials/*-modal`, `mortality.blade.php`, `cages/index.blade.php` | Mixes inline `style="color:#…"` with arbitrary-value classes — two mechanisms for the same decoration | Use classes exclusively |

### 2.2 Component duplication (re-inventing shared components)

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `profile.blade.php` (13 raw cards), `mortality.blade.php:17,96,116`, `reports.blade.php:63,148`, `system-time.blade.php:8`, `feed/_live-data`, `environment/_live-data`, `eggs/pre-orders/_table`, `notifications/_table`, `hardware/_live-data`, `analytics.blade.php:45+` | Hand-rolled `bg-white rounded-lg border border-[#D9D9D9] p-5` cards when `<x-card>` exists | Wrap content in `<x-card>` |
| ~15 files re-implement modal shell inline (`notes/index:83-85`, `mortality:128-130`, `feed:27-29/96-98/168-170/251-253`, `eggs/pre-orders:99-101`, `eggs/stocks:57-59`, `egg-logging/_edit-modal`, all 7 `chickens/partials/*-modal`, `forecast/_workspace`) | Duplicated `bg-black/50 backdrop-blur rounded-2xl p-6 …` modal backdrop+panel pattern; `<x-confirm-modal>`/`<x-loading-modal>` only cover confirm/spinner | Extract a shared `CardModal` component |
| `system-time.blade.php:38`, `profile.blade.php:90`, `errors/419.blade.php:14-21`, `chickens/partials/*-modal`, `mortality.blade.php:183-189`, `feed.blade.php:144` | Hand-rolled primary buttons (`bg-[#002D5E]`, `#102A4C`, `#0075de`) instead of `<x-button>` | Use `<x-button>` |
| `mortality.blade.php:9,106`, `mortality/_logs.blade.php:37-39`, `reports/_summary-pills.blade.php`, `eggs/pre-orders/_table:31-33`, `feed/_live-data:213`, `profile.blade.php:100-106`, `cages/index:270` | Hand-rolled status pills/chips instead of `<x-status-badge>` / `<x-cage-color>` | Use the badge components |
| Every table across modules | Per-table empty-state markup re-implemented 5+ different ways (§2.4) | Add `<x-empty-state icon message>` |

### 2.3 Styling drift (radius / padding / buttons / tables / labels / KPI cards)

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `<x-card>` = `rounded-lg` vs `notes/index:10,39`, `egg-logging:37`, `chickens/_inventory-list`, `dashboard/_calendar:179+`, `auth/login:58` = `rounded-xl` vs dashboard KPIs = `rounded-2xl` vs reports = `p-8` | Card tier radius/padding differ per page with no system: `p-4`/`p-5`/`p-6`/`p-7`/`p-8` | One radius+padding per card tier, encoded in `<x-card>` variants |
| `<x-button>` (rounded-lg) vs `rounded-full` on dashboard tabs, FAB items, `confirm-modal`, pills; navy value differs: `#002D5E` vs `#213183` vs `#0075de` | Button radius + primary color resolved independently per page | Single `primary`/`danger` button variant; keep `rounded-full` only for pills/FAB |
| `feed/_live-data`, `environment/_logs`, `forecast/_results`, `eggs/pre-orders/_table` vs `egg-logging/_logs:6-11`, `egg-production-history:105-108` vs `mortality/_logs:9-14` vs `chickens/*-records` (4 variants) vs `reports/_report-table:12` vs `dashboard/_cage-performance-content:105` | **6 distinct table-header recipes** (label size, case, tracking, bg, padding) across pages | One `<x-table>` / `.th` recipe |
| `dashboard/_metric-cards` (chips/watermark/`#102A4C` values) vs module metric cards (`text-xs uppercase text-[#6B7280]` label + `text-2xl text-[#333333]`) vs `profile:41-56` (centered navy) | 3 diverging KPI/stat-card layouts | Canonical `KpiCard` component; fold the others in |
| Field labels: `text-xs tracking-wider text-[#6B7280]` (mortality/reports/pre-orders/login/stocks) vs `text-sm text-[#333333]` (feed/notes/profile/system-time) | Two competing form-label dialects | One label style (recommend `text-xs tracking-wide text-ink-muted`) |

### 2.4 Empty-state & loading-state treatment

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| Most lazy frames use `<x-skeleton>` (analytics, chickens, environment, feed, hardware, eggs, egg-logging, notifications, mortality, forecast, dashboard) | ✅ shared pattern — but `feed/_live-data:374-383` (FCR panel), dashboard calendar skeleton, and parts of `forecast/_results-skeleton` hand-roll `animate-pulse` divs | Reuse `<x-skeleton variant>` everywhere |
| `egg-logging/_logs:79`, `feed/_live-data:253,329`, `chickens/_mortality-records:42`, `eggs/pre-orders/_table:59`, `environment/_logs` vs `notes/index:74` (centered block) vs `notifications/_table:3-6` (icon card) vs chart wrappers hiding canvas vs nothing rendered | **5+ empty-state patterns**, decided per file | `<x-empty-state icon message>` |

### 2.5 Redesign / migration status (pages NOT yet migrated to the current design)

Criteria: still using legacy navy `#102A4C`, Bootstrap alert palette, or structurally bypassing shared components.

| Status | Views |
|---|---|
| **LEGACY (highest priority to migrate)** | `mortality.blade.php` (+`_logs`), `reports.blade.php` + `reports/pdf.blade.php` + `_report-table/_letterhead` (26+ legacy hex), `eggs/stocks.blade.php` (16), `environment.blade.php` (+`_logs`/`_live-data`, partial), `dashboard/_metric-cards.blade.php` (7), `eggs/pre-orders.blade.php` (8), `system-time.blade.php` |
| **PARTIAL (restyled shell, legacy internals)** | `cages/index.blade.php` (heaviest hex density, hand-rolled slots/cards), `profile.blade.php` (hand-rolled cards), `feed.blade.php` + partials, `forecast.blade.php` + partials (bg css mostly modern, alerts remain legacy), `egg-logging.blade.php`, `chickens/index.blade.php`, `hardware/index.blade.php`, `notes/index.blade.php`, `notifications/index.blade.php` |
| **REDESIGNED (cleanest/most consistent)** | `dashboard` (shell + most partials), `analytics` (but all-raw-hex cards), `egg-production-history.blade.php` (heavy `<x-card>` use), `landing.blade.php` (only page fully on tokens), `errors/419.blade.php` |

**Bottom line:** every page got the new `<x-page-header>` shell, but only `landing` and `errors/419` actually use the token system; the rest hardcode equivalent (or conflicting) hex values, so visuals only stay consistent by hand-editing every page.

---

## 3. STRUCTURAL / ARCHITECTURE INCONSISTENCY

### 3.1 Module boundaries — services vs inline controllers

| Module | Where the logic lives | Inconsistency |
|---|---|---|
| Environment | `app/Services/EnvironmentAlertService.php`, `EnvironmentStatusService.php`, `RelayStateService.php` — clean service layer | The only module with a full service layer |
| Feed / FCR | `app/Services/FcrCalculator.php`, `FcrStatusService.php`, `ProductionTimelineService.php` | Service layer, but `FcrStatusService` is **called directly from Blade** (`feed/_fcr-content.blade.php:2,7-9,75-76`) — business logic runs in templates, unlike every other module |
| Forecast | `app/Forecast/ForecastRules.php` + `app/Jobs/GenerateForecastJob.php` — but thin; the real orchestration (Python subprocess, gates, imports/exports) is a 1501-line controller | Logic externalized, still a 1500-line fat controller |
| Hardware | `HardwareItemController.php` (185) — validator closure + cache-busting inline | Sibling of Environment but **zero services** |
| Egg Stock | `EggStockController.php` (415) — reservation/reclassify/alerts inline | No stock service |
| Pre-Orders | `PreOrderController.php` (305) — 7-day forecast, size distribution, depletion inline | No service |
| Cage | `CageController.php` (1133) — base-26 codes, slot renumbering, placement inline | Biggest fat controller, no service |
| Chickens | `ChickensController.php` (629) — batch moves/culling/removals inline | No service |
| Reports / Analytics | `ReportController.php` (743), `AnalyticsController.php` (220) — full logic inline; analytics instantiates `DashboardController` | No service |
| **Alerts** | No `AlertsService` exists; alert *creation* is scattered across **6 files** (see §1.5) | Generation logic has no single owner |

**Fix:** one rule — controllers orchestrate, `app/Services/*` owns business logic; give Hardware/Stock/PreOrder/Cage/Chickens/Reports the same treatment Environment already has.

### 3.2 Controller arrangement / responsibility drift

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `EggLoggingController` + `EggProductionHistoryController` + `EggStockController` + `EggLogsSseController` + `EggCountSseController` | Egg module split across 5 controllers under `/eggs/*` **and** legacy roots; `DashboardController::productionHistory` re-derives what `EggProductionHistoryController` already computes | One egg-domain controller set; single owner for "production history" |
| `HardwareItemController` vs `DeviceController` | Both own devices: hardware index lists devices with `withCount('hardwareItems')`, DeviceController handles CRUD/API-key regen in a separate admin group; two identity concepts (`serial_number` vs `api_key_hash`) | Merge or formally split "sensors" vs "gateways" |
| `AlertController` | Only controller in **both** `web.php` and `api.php` (apiIndex/apiMarkRead siblings) | Convention for Web vs API exposure (see §3.4) |
| `CageController` (1133), `ChickensController` (629), `ReportController` (743), `ForecastController` (1501) | Mixed plural/singular class names + massive responsibilities | Standardize naming; split by concern |
| `app/Jobs/GenerateForecastJob.php` | Job **injects `ForecastController` and calls its public methods** — a worker depends on the web controller | Extract forecast logic to `ForecastService` consumed by both job & controller |

### 3.3 Folder-structure conventions (4 coexisting schemes)

| Scheme | Used by | Example files |
|---|---|---|
| (A) Root view + same-name partial folder | feed, environment, mortality, forecast, reports, analytics, egg-logging | `feed.blade.php` + `feed/_live-data.blade.php` |
| (B) `index.blade.php` + flat `_partial` files | hardware, notes, notifications, chickens, cages | `hardware/index.blade.php` + `hardware/_live-data.blade.php` |
| (C) `eggs/` module with nested feature folders | eggs | `eggs/pre-orders/_table.blade.php`, `eggs/_tabs.blade.php` |
| (D) `partials/` subfolder for modals | chickens | `chickens/partials/cull-modal.blade.php` (+ top-level `resources/views/partials/`) |

Also: root-level single files (`egg-production-history.blade.php`, `eggs/production-history.blade.php` both exist) and **both** `egg-logging.blade.php` and `egg-logging/_logs.blade.php`; `eggs/recent-logs.blade.php` duplicates `egg-logging/_logs` content. Generic skeleton partials are re-copied per module even though `<x-skeleton>` exists.

**Fix:** pick one scheme (recommend (B): `module/index.blade.php` + `module/_partial.blade.php`); delete duplicate/overlapping views; reuse `<x-skeleton>`.

### 3.4 API vs Web route split

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `routes/api.php` (5 calls, DeviceAuth: `sensor-readings`, `relay/command`, `alerts`, `alerts/{alert}/read`) vs `routes/web.php` (149 routes) | Dozens of true JSON endpoints live in `web.php` under the auth group (`*Data`, `*Json`, `live-*`), while only hardware auth lives in `api.php` | Move all JSON endpoints under `/api` (or a consistent prefix) |
| Same AJAX-tab pattern: `ReportController::data` returns `response()->json(['html'=>…])` but `EnvironmentController::liveData`/`FeedController`/`HardwareController` return `view('_live-data')` | Same "sub-tab panel" problem solved two ways | One contract: partial views (Turbo frames) OR JSON html payloads |
| `routes/web.php` | Flat 149-route file, no grouping/prefix/`Route::resource`; legacy redirects (`/egg-logging`→`/eggs/logging`) coexist with new URLs | Group by module; drop stale redirects |
| `routes/web.php:47` `/_reset-opcache` | Unauthenticated admin power switch ("Temporary") | Gate behind auth+admin or remove |
| middleware aliases (`admin`, `system-time-set`) in `bootstrap/app.php` vs `DeviceAuth::class` referenced by FQCN in `api.php` | Middleware referenced two different ways | Use aliases consistently |
| `API_ROUTES.md` | Documents 42 routes; the app now has 149 web + 5 api | Regenerate from `route:list`; add a response-shape convention (§1.3) |

### 3.5 Domain layers (Requests / Actions / Services / Jobs / Exports)

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `app/Http/Requests/` | 1 file, unused (see §1.2) | Populate or remove the folder |
| `app/Actions/`, `app/Repositories/` | None exist — logic lives in controllers + a handful of services | Decide the convention (services are the pattern in use) |
| `ReportController` (`exportCsv` closure, `exportPdf` dompdf inline) vs `app/Exports/` (3 Maatwebsite classes) vs `ForecastExport` | Report export solved 3 ways in the same feature | Unify behind a `ReportExportService` |
| `app/Console/Commands/` (16 commands) | Command namespaces mixed: `layrate:*` (5) covers recurring (`layrate:audit-egg-stock`) AND one-off backfills (`layrate:backfill-chicken-ids`), while mortality backfills use `mortality:recover-logs` / `mortality:repair-hen-state`; **`--dry-run` vs `--apply` polarity differs** (`--dry-run` flag vs required `--apply` to write) | `layrate:backfill-*` namespace + uniform `--dry-run` semantics |
| `routes/console.php` + `bootstrap/app.php` | Scheduling split across two files (4 tasks in console.php + 1 in `bootstrap/app.php`'s `withSchedule()`); `db:backup`, `runner:health-check`, `forecast:debug` unscheduled; docs say "6 scheduled tasks", actual is 5 | Consolidate schedules in `routes/console.php`; fix docs |

### 3.6 Documentation drift

| Path(s) | Inconsistency | Suggested fix |
|---|---|---|
| `README.md`, `API_ROUTES.md`, `docu/FEATURE-INVENTORY.md`, `docs/`, `dist/`, `DESIGN-SYSTEM.md` | No authoritative architecture doc; 4 disjoint doc locations; DESIGN-SYSTEM.md missing; stale route/queue counts; concept docs duplicated | Single `docs/` tree: `ARCHITECTURE.md`, `DESIGN-SYSTEM.md`, regenerate API routes |

---

## 4. TOP 10 — WORTH FIXING FIRST

Ranked by user-visible / maintenance pain (1 = highest).

| # | Item | Pain driver |
|---|---|---|
| 1 | **Centralize alert creation** in one `AlertsService::createIfAbsent()` (currently in 6 files: `Controller.php`, `EnvironmentAlertService`, `SensorIngestionController`, `FeedController`, `EggStockBatch`, `PreOrderController`) | Silent duplicates / missed alerts today; one fix kills six copies |
| 2 | **Extract period/HDEP + egg-pool + forecast math** (`DashboardController`↔`AnalyticsController` verbatim copies, `PreOrderController` vs `ForecastController` forecaster, pool computed 2 ways) | The same user-visible numbers fork across pages (dashboard vs analytics vs pre-orders can disagree) |
| 3 | **Fix the JSON/error contract** (`ok` vs `success`, `errors` vs `error` vs `message`, 500s for validation, both controllers in web+api, SSE cadence/errors) | Every API consumer (mobile app, ESP bridge, JS fetch) must special-case; breaks clients on any change |
| 4 | **Create the missing `DESIGN-SYSTEM.md`** and rewrite the 3 shared components (`card`, `button`, `underline-tabs`) on tokens, defining `--color-navy` | The entire UI drift in §2 stems from an undocumented system + off-token base components |
| 5 | **Migrate the LEGACY pages** to the current design: `mortality`, `reports` (+pdf), `eggs/stocks`, `environment`, `dashboard/_metric-cards`, `eggs/pre-orders`, `system-time` (still Bootstrap-alert colors + `#102A4C`) | Most obviously "unfinished" visuals a user sees; highlighted as not-yet-redesigned |
| 6 | **Thin the fat controllers** — split `CageController` (1133), `ForecastController` (1501), `ReportController` (743), `ChickensController` (629) into services; fix `GenerateForecastJob` injecting a controller | Data operations spool into one place; job↔controller coupling blocks background processing changes |
| 7 | **Standardize validation** — wire the dead `StoreHardwareItemRequest` and convert 22 controllers from `$request->validate` / `Validator::make` to FormRequests | Three competing styles make every change a guess; only validation gap blocks a security pass |
| 8 | **Standardize table-header + KPI-card + empty-state components** (`<x-table>`, `<x-card` variants`, `<x-empty-state>`) | Visible rows look different page to page (6 header variants); each new page copies yet another variant |
| 9 | **Uniform JSON key casing & DB column names** (`egg_count` vs `count` vs `reported_count`; snake_case vs camelCase payloads) and record in a convention doc | API + reports couple to key names; renaming now is cheaper than after more integrations |
| 10 | **Delete verified dead code**: `MobileAppController`, `IncubatorStatus` model, `ApiAuth` middleware, `respondAfterGenerate()`, stale `predicted_hdep` in exportCsv, unrouted `cages/label.blade.php`, `MortalityController` stub | Dead surface confuses maintainers and carries old contracts; trivial to remove |
