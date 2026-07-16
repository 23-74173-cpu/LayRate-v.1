# Codebase Audit — 2026-07-16

Status key: ✅ IMPLEMENTED | ⚠️ PARTIALLY IMPLEMENTED | ❌ NOT STARTED

## GENERAL (Items 1–20)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 1 | Title + subtitle in page headers | ✅ | 18/24 pages use `<x-page-header>` component. Outliers: print/confirm/login pages intentionally different. | Consistent |
| 2 | Components inside container with header | ✅ | `resources/views/layouts/app.blade.php:299` — `main.page-wrapper` wraps all `@yield('content')` | |
| 3 | Consistent font sizing | ⚠️ | Standard Tailwind scale used everywhere. `text-[20px]` modal titles deliberate (30+ occurrences). Two minor outliers: `text-[22px]` in hardware metrics, `text-[11px]` in FCR badges. | Minor; two ad-hoc sizes |
| 4 | Modals inside site (no native confirm/alert) | ⚠️ | **4 remaining `return confirm()` calls**: `forecast/_calendar.blade.php:74`, `hardware/index.blade.php:75`, `eggs/pre-orders/_table.blade.php:60`, `chickens/_mortality-records.blade.php:43`. **3 remaining `alert()` calls**: `egg-logging.blade.php:504`, `feed.blade.php:373`, `forecast/_calendar.blade.php:341`. | Medium severity |
| 5 | Consistent buttons/dropdowns/tabs | ⚠️ | Four primary button variants exist: `bg-[#102A4C]`, `bg-[#002D5E]`, `bg-[#0075de]`, `bg-[#2D7D46]`. Chicken modals use inline `onmouseover` JS instead of Tailwind `hover:` classes. `rounded-lg` vs `rounded-full` on submit buttons (deliberate but inconsistent). | Medium |
| 6 | Hardware Inventory section | ✅ | `HardwareItemController` with full CRUD. `routes/web.php:122-126`, `resources/views/hardware/index.blade.php`. | |
| 7 | CRUD completeness | ⚠️ | **Full CRUD**: Cages, Chickens, Hardware, Environment (thresholds), Feed (batches/consumption/entries), Notes, Egg Stocks, Pre-Orders, Alerts. **Incomplete**: Mortality (no Update in controller — only `destroy` is admin). Environment logs (no Delete). | |
| 8 | Pagination bug — plain `<a>` vs AJAX | ⚠️ | App uses **Turbo Drive** (intercepts all `<a>` clicks). `<x-paginator>` component exists but is **underutilized** — 5+ sub-views have inline "Showing X-Y of Z" text-only without page links: `mortality/_logs.blade.php:65`, `feed/_live-data.blade.php:326-331`, `chickens/_mortality-records.blade.php:61`, `chickens/_culling-records.blade.php:45`, `chickens/_removal-records.blade.php:36`. Feed has a manual prev link. | Medium — missing navigable pagers in 5 sub-views |
| 9 | Modals as overlays | ✅ | All modals use `fixed inset-0 z-50` pattern. **Except**: Cage delete uses separate confirm-delete page for "permanent" deletion path. | |
| 10 | Skeleton loading | ✅ | 17 dedicated skeleton partials across all lazy-loaded turbo-frames. Only forecast uses spinner overlay instead of skeleton. | |
| 11 | Cage/hen data consistency | ✅ | Single source of truth: `ProductionLog::sum('egg_count')` used by both Dashboard (`DashboardController.php:93`) and Egg Production History (`EggProductionHistoryController.php:22`). | |
| 12 | Codebase errors/unused imports | ⚠️ | PHP syntax passes. No fatal errors. Several minor issues: unused `$eggWeights` variable reference in older code, some view files declare `CAGE_COLORS` JS var but it may be underused. | |
| 13 | "Wing" references | ✅ | **Zero** "wing" references in any application code (`app/`, `resources/views/`, `routes/`, `config/`, `database/`). Only in docs/planning files. | Clean |
| 14 | Modal buttons (Cancel, X/close) | ✅ | 30+ modals with close buttons using `p-1.5 rounded-full hover:bg-black/5 transition-colors` pattern with `data-lucide="x"`. Cancel buttons present in all modals. | |
| 15 | Backdrop click-to-close | ⚠️ | Most modals have backdrop click handlers. **Missing**: `forecast.blade.php` modals (lines 29, 74, 119) — backdrop overlays lack `onclick` handlers. Escape key not universally handled (only implemented in confirm-modal, alerts-modal, register-modal, cull-modal, cages/index). | Low |
| 16 | Consistent icon set | ✅ | **100% Lucide**. No Font Awesome, inline SVGs, or emoji icons. `data-lucide` attributes throughout. Single source `js/lucide.min.js`. | |
| 17 | Proper cursor states | ✅ | `cursor-pointer` on all interactive divs/cards. Native `<a>` and `<button>` elements use browser defaults (correct). | |
| 18 | Card hover "raise" animation | ✅ | Three consistent patterns: `hover:shadow-md` (metric cards), `hover:bg-[#F5F6F8]` (table rows), `hover:bg-[#001F42]` (primary buttons). Non-interactive cards correctly have no hover. | |
| 19 | RBAC | ✅ | Two roles: `admin` and `operator` (implied). `User::isAdmin()` at `User.php:29-32`. Admin middleware at `EnsureAdmin.php:11-17` (aborts 403). Applied to: all cage mutations, device CRUD, `eggs.logging.destroy`, `eggs.stocks.destroy`, `mortality.destroy`. | |
| 20 | Responsive across screen sizes | ⚠️ | Most modules are responsive. Some issues: Dashboard cage grid forces 2 columns on mobile (`_cage-overview.blade.php:10`). No full mobile audit done for every page. | Appears generally responsive |

## HEADER & SIDEBAR (Items 21–25)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 21 | Header/sidebar theme color | ✅ | `bg-sidebar-bg` (dark theme) + `bg-surface h-12` top bar. Theme color `#1a2342` in meta tag. Consistent app-wide. | |
| 22 | "Offline/local network" indicator removed | ✅ | No "offline", "online", "connected", or "network" text exists in any view. | |
| 23 | Logout in sidebar footer | ✅ | `app.blade.php:236-244` — Logout form in sidebar bottom, after Notifications/Settings/Profile links. | |
| 24 | Breadcrumb dynamic | ✅ | `app.blade.php:487-498` — Client-side JS updates breadcrumb based on `SECTION_LABELS` map. Updates on Turbo navigation. | |
| 25 | Notes section exists | ✅ | `routes/web.php:73-76` — `/notes`. Full CRUD in `NoteController.php`. | |

## DASHBOARD (Items 26–33)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 26 | Date/time live-updating | ✅ | JS `setInterval` clock at `dashboard.blade.php:216-227` — updates every second. | |
| 27 | KPI cards prominent font | ✅ | `text-4xl font-bold` (36px) for primary numbers in `_metric-cards.blade.php`. Environment card uses `text-3xl` (30px) for dual metrics. | |
| 28 | KPI cards clickable | ✅ | `_metric-cards.blade.php:184-188` — `Turbo.visit(card.dataset.nav)` on click. All 5 cards navigate to their sections. | |
| 29 | KPI cards pre-select cage | ❌ | No cage ID is passed when navigating. Cards use `route()` without any query parameters. | Needs product decision |
| 30 | Cage Overview card padding | ⚠️ | Padding appears adequate (`p-3` / `p-4`). Hard to verify without visual rendering. | |
| 31 | Sensor summary descriptive status | ⚠️ | Needs visual check of dashboard. Code shows conditional classes for Normal/Watch/Alert status. | Not verified visually |
| 32 | Feed/Mortality scrollable (5 items) | ❌ | `_feed-mortality.blade.php` renders all items in a list. No limit=5 or scroll-based truncation found. | Needs implementation |
| 33 | KPI card flip-to-detail | ❌ | **Not implemented as flip animation**. Implemented as a **modal** — long-press (500ms touchstart) opens a per-cage breakdown modal via `openKpiModal()`. No CSS flip animation exists. | Modal-based, not flip |

## CAGES (Items 34–51)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 34 | Slot swapping / layout reorder | ✅ | `batchReorderSlots()` at `CageController.php:413-463` (renumbers slots). `updatePosition()` at line 315 (grid position). `batchUpdatePosition()` at line 351 (bulk grid save). `removeCell()` at line 982. | |
| 35 | Only admins edit layout | ✅ | Cage mutation routes wrapped in `middleware('admin')` at `web.php:59-71`. View-level `@if($isAdmin)` gates in `cages/index.blade.php`. | |
| 36 | Sensor checkbox unchecking | ✅ | Cage edit modal at `cages/index.blade.php:414-438` — IR sensor checkboxes work via toggle. Fixed per earlier session. | |
| 37 | Clicking slot shows detail | ✅ | `hensJson()` at `CageController.php:478-531` — slot click returns hen data via AJAX. | |
| 38 | "Bulk Add Chicken" in Cage header | ✅ | `cages/index.blade.php:11-17` — outlined button "Bulk Add Chickens" in page header. Also per-cage `plus-circle` icon at line 183. | |
| 39 | Flexible cage row display | ✅ | Grid layout auto-adjusts based on row count (`grid-template-columns: repeat(...)`). Single cages can span full rows. | |
| 40 | Cage deletion modal (not redirect) | ⚠️ | **Normal delete**: inline AJAX modal at `cages/index.blade.php:482-568`. **"Delete Permanently"**: redirects to **separate page** `cages/confirm-delete.blade.php`. | Hybrid — most delete is modal |
| 41 | Granular delete options (checkboxes) | ✅ | `cages/index.blade.php:496-534` — radio for hen action, checkbox for sensor return, checkboxes for preserving production/mortality/feed/env records. | |
| 42 | Available sensor stock counts | ✅ | `cages/index.blade.php:426-458` — IR and DHT22 availability shown with remaining count. JS disables when depleted. Server-side validation at `CageController.php:143-153`. | |
| 43 | Bulk Add chicken breed counts | ✅ | `cages/bulk-add.blade.php` — shows available unplaced hens grouped by breed with count badges. | |
| 44 | Cage edit: separate sensor sections | ✅ | `cages/index.blade.php` — IR break-beam (lines 414-438) and DHT22 (lines 440-458) are separate sections in the edit modal, with distinct icons and headers. | |
| 45 | Sensor Device IDs auto-generate globally | ✅ | `CageController::nextDeviceId()` at `CageController.php:217-230` — scans all `HardwareItem.serial_number` for max numeric suffix, increments globally. `IRBBS_N` and `DHT22_N` format. | |
| 46 | Sensor counts live from Hardware | ✅ | `CageController::index()` at `CageController.php:35-39` — queries `HardwareItem::availableForAssignment()`. Live DB query. | |
| 47 | "Cage Info" view exists | ❌ | No single-cage detail view (`show()` method absent from CageController). No route for `GET /cages/{cage}`. Slot detail is via AJAX slots-json, not a full view. | Missing feature |
| 48 | Bulk Add data from Chicken Inventory | ✅ | `cages/bulk-add.blade.php` — pulls unplaced hen data from `Hen::whereNull('cage_slot_id')->where('is_active', true)`. | |
| 49 | Printable cage label | ✅ | `CageController::printLabel()` at line 899. Route `GET /cages/{cage}/print-label`. View at `resources/views/cages/print-label.blade.php`. | |
| 50 | Cage Overview horizontal/vertical toggle | ❌ | Comment at `cages/index.blade.php:37`: `{{-- Layout flow toggle removed per audit decision --}}`. Toggle was intentionally removed. | Needs decision |
| 51 | Sensor inventory errors in cages | ⚠️ | No critical errors found. Potential UX issue: the "No DHT22 sensors left in inventory" message appears only after user tries to add beyond available count — could show earlier in the flow. | Minor UX |

## CHICKENS (Items 52–56)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 52 | Add Chicken form fields | ✅ | `chickens/partials/register-modal.blade.php` — includes: Quantity (line 22), Breed (line 30), Sex (line 43), Source/Origin (line 55), Date Acquired (line 64), Age at Acquisition in weeks (line 72), Initial Health Status (line 81), Notes (line 89). | All required fields present |
| 53 | Culling section with required reason | ✅ | `ChickensController::storeCulling()` at line 215. View `chickens/partials/cull-modal.blade.php` with `reason` field (enum: Disease/Heat Stress/Injury/Predator/Unknown/Other). | |
| 54 | Uses Row/Col location format | ✅ | No "wing" references anywhere. Cage locations stored as `location_row`, `location_column` in `cages` table. | |
| 55 | Chicken ID auto-generates CHK-YYYY-NNNNN | ✅ | `Hen.php:29-48` — boot method auto-generates `chicken_id` as `CHK-{YEAR}-{00001}` format. | |
| 56 | Full lifecycle tracking | ⚠️ | **Registration**: ✅ (register-modal + store). **Cage Assignment**: ✅ (via Bulk Add + cage edit). **Transfers**: ✅ (`CageTransfer` model at `app/Models/CageTransfer.php`). **Health events**: ✅ (`HealthEvent` model). **Weight checks**: ✅ (`WeightCheck` model). **Culling**: ✅ (`CullingLog` model). **Removal**: ✅ (`Removal` model, separate from mortality). **Mortality**: ✅ (`MortalityLog` at `app/Models/MortalityLog.php`). | Complete |

## EGG MANAGEMENT (Items 57–66)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 57 | Mobile: only selected cage's slots | ✅ | `egg-logging.blade.php:92` — `.cage-grid.hidden` — JS `switchCage()` shows only selected cage. Same behavior on all screen sizes. | |
| 58 | Log Entry aligned on desktop | ✅ | `egg-logging.blade.php:60-78` — Log Entry section inside `<x-card>` container, full-width with cage dropdown. | |
| 59 | Recent Logs has proper filtering | ✅ | `routes/web.php:95` — `eggs.recent-logs` route. `EggLoggingController::recentLogs()` at line 62 — filters by cage, breed, slot, logged_via. View at `eggs/recent-logs.blade.php`. | |
| 60 | Uses new location format | ✅ | No "wing" references in egg-related code. | |
| 61 | Block stocking with no eggs logged? | ⚠️ | **Current behavior**: Stocking is allowed as long as the pool > 0. If no eggs have been logged, the pool is 0 and stock creation will be rejected by `createWithinPool` with "Only 0 egg(s) available" error. This is correct — it rejects if no eggs are logged. | Needs product decision on behavior |
| 62 | KPI: cumulative total eggs logged | ✅ | `DashboardController.php:93` — `lifetimeEggs` = `ProductionLog::sum('egg_count')`. Displayed in Dashboard and Egg Production History page. | |
| 63 | Forecast size distribution | ⚠️ | **Not mathematically fabricated in Forecast page**. The Forecast feature predicts total egg count/HDEP only — no size breakdown. `PreOrderController::forecastSize()` does size-level forecasting separately for stock planning. OK — separate concerns. | |
| 64 | Production Log field purpose clear | ⚠️ | `eggs/stocks.blade.php:194-201` — labeled "PRODUCTION LOG (optional)" with dropdown of log dates filtered by selected cage. Purpose is technically clear but could benefit from a sub-label explaining it links the stock batch to the harvest day's production entry. | Minor docs issue |
| 65 | Production Log connected to both | ✅ | `source_production_log_id` FK in `egg_stock_batches` table links to `production_logs`. Through `production_logs`, the stock batch can trace back to the egg logging entry. | |
| 66 | "Egg Production since day 1" view | ✅ | `EggProductionHistoryController` at `/egg-production-history`. Shows lifetime eggs, timeline (day/week/month), cage breakdown, size breakdown. Sidebar link at `app.blade.php:187`. | |

## HARDWARE (Items 67–68)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 67 | Hardware section with specs/status | ✅ | `HardwareItemController` — index, liveData, store, update, destroy. View at `hardware/index.blade.php` shows status, type, serial. `DeviceController` for Pi devices. | |
| 68 | Fans tracked in Hardware | ❌ | **No fan-related code anywhere** in application code. `HardwareItem::DEVICE_TYPES` includes `'relay'` but no fan-specific functionality. Fans are only referenced in documentation files as a known gap. | Missing feature |

## ENVIRONMENT (Items 69–71)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 69 | Threshold config under KPI cards | ✅ | `environment/_live-data.blade.php` — thresholds form positioned after summary stats (`avgTemp`/`avgHum`) cards in the `liveData()` view. | |
| 70 | Fan status displayed | ❌ | No fan status, KPI card, or animation indicator exists. | Missing feature |
| 71 | Threshold min/max logic correct | ⚠️ | Logic at `EnvironmentStatusService.php`: **Alert**: `< min` OR `> max` (strictly outside, exclusive). **Watch**: `<= min` OR `>= max` (at boundary, inclusive). **OK**: strictly inside (`min < value < max`). The code structure is redundant (some branches are unreachable) but produces **correct results for all boundary cases**. | Works correctly; code slightly redundant |

## FEED & NUTRITION (Items 72–76)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 72 | Full CRUD for feed batches | ✅ | `FeedController`: `storeBatch()`, `updateBatch()`, `destroyBatch()` + `checkDeleteBatch()`. Routes at `web.php:137-140`. | |
| 73 | Batch code auto-generates | ✅ | `FeedBatch.php:15-38` — boot method generates `F-YYYY-NNN` format. Preserves manually-set codes. | |
| 74 | Feed brand field exists | ✅ | `FeedBatch.php:11` — `brand` in `$fillable`. Validated as `nullable|string|max:100` at `FeedController.php:145`. | |
| 75 | Image recognition for feed labels | ❌ | **Not discussed or implemented.** No code, no docs, no UI for feed label scanning. | Open question |
| 76 | Feed consumption: per-cage vs farm-wide | ✅ | Per-cage storage with automatic farm-wide distribution. `feed_consumption_logs` has `cage_id` FK + `unique(cage_id, log_date)`. Farm entries distributed via `distributeFarmFeedEntry()` using largest-remainder method. | Both supported |

## ANALYTICS (Items 77–82)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 77 | Cage filtering uses dropdown | ✅ | `analytics.blade.php` — cage filter is a `<select>` dropdown (not buttons). | |
| 78 | Appropriate chart types | ✅ | Chart.js: line chart for HDEP trend, bar chart for eggs collected, scatter plot for feed-vs-HDEP. | |
| 79 | Charts "not loading" root cause | ⚠️ | Charts load via `analytics/charts` turbo-frame. Possible causes: (1) Chart.js loaded with `defer` (`app.blade.php:34`) which may not complete before turbo-frame renders; (2) `<canvas>` inside turbo-frame may not initialize if Chart.js' DOM-ready event fires before the frame's lazy content loads; (3) `lucide.createIcons()` is called but Chart creation is after that. Actual root cause not reproducible from code alone. | Needs browser debugging |
| 80 | Per-hen-breed analytics | ❌ | **No breed filtering/grouping** in `AnalyticsController.php`. The word "breed" does not appear in the controller. | Missing feature |
| 81 | Mortality data in Analytics | ❌ | **No mortality queries** in `AnalyticsController.php`. | Missing feature |
| 82 | Temperature data in Analytics | ❌ | **No environmental log queries** in `AnalyticsController.php`. | Missing feature |

## FORECAST (Item 83)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 83 | Forecast general functional status | ✅ | Works via Python pipeline (`forecast-api/forecast_runner.py`). Scope: per-cage/per-breed/whole-farm, horizon 7/14/30 days. Input validation requires 90+ days of data. Imports XLSX. Stores results in `forecasts` table. | 846-line controller |

## REPORTS (Item 84)

| # | Item | Status | Evidence / File:Line | Notes |
|---|---|---|---|---|
| 84 | Preview table before export | ❌ | `reports.blade.php` — no data preview table rendered. Reports are generated server-side and streamed as final output (HTML printable view or CSV download). The form collects parameters (type, date range, cage, mortality reason) and immediately generates output. | Missing feature |

---

## Items Requiring Product Decisions (not just code fixes)

1. **#29**: KPI cards navigate to sections but don't pre-select a cage (e.g., Mortality Today for Cage A → Mortality page with Cage A pre-filtered). Needs a decision on whether to pass `?cage_id=X` in the target URL.

2. **#50**: Cage Overview horizontal/vertical toggle was intentionally removed. Whether to restore it or finalize a single orientation is undecided.

3. **#61**: The current behavior blocks stocking when pool is 0 (no eggs logged). This implicitly prevents "speculative" stocking. Confirm this is desired.

4. **#75**: Feed label image recognition — never discussed/designed. If planned, needs a spec.

5. **#76**: Daily feed consumption is per-cage, with farm-wide distribution support. This design was not explicitly signed off by the farm operator.

6. **#80, 81, 82**: Analytics missing breed, mortality, and temperature data. Needs product decision on whether to include these in the Analytics view (vs. keeping them in their dedicated sections).

7. **#84**: Reports generate output immediately without a preview table. Whether a preview should be shown before generation is unsettled.
