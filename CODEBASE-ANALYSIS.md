# LayRate Codebase Analysis — Findings Report

> **Status:** Audit / findings only. No code changes have been implemented yet.  
> **Date:** 2026-07-10  
> **Owner:** Development team  
> **How to use this file:** Check off items in the Priority Ranking section as they are completed. Update the relevant area sections when gaps are closed or new issues are discovered.

---

## 1. Forecast Model

### Current State

`ForecastController` delegates forecasting to a Python pipeline in `forecast-api/`:

- `forecast_runner.py` loads data from `forecast_input_records` and runs `ForecastingV5.py`.
- `ForecastingV5.py` implements:
  - **SARIMA(1,1,1)(1,1,1,7)** baseline.
  - **XGBoost ensemble** (3 seeds) with exogenous features:
    - `Breed` (one-hot), `Live_Hens`, `Flock_Age_Weeks`, `Temperature_C`, `Humidity_Percent`, `Crude_Protein_Percent`, `Total_Feed_Consumed_kg`, `Monthly_Mortality`, `Heat_Stress`.
    - Lag features: 1, 2, 3, 7, 14 days.
    - Rolling means: 7, 14 days.
  - Model selection on an 80/20 time-based holdout using MAE/MAPE.
  - Recursive multi-step forecasting for XGBoost.
- `SyncForecastInputRecords` Artisan command (`forecast:sync-input-records`) already exists to denormalize production logs, environmental logs, feed consumption, and mortality into `forecast_input_records`.

### Gaps

1. **Static future covariates.** When XGBoost is selected in automatic mode, every future day is fed the *last observed* temperature, humidity, feed, mortality, and heat-stress values (`ForecastingV5.py`, `build_deployment_feature_frame`, lines 374–391). The model has no weather forecast, no planned feed schedule, and no projected mortality, so heat-wave or cold-front impacts beyond day 1 are ignored.
2. **No recency weighting.** Training rows are treated equally; older data at a different flock age is as influential as yesterday.
3. **No uncertainty output.** Only point predictions are returned; users get no prediction interval for inventory planning.
4. **Manual import remains the primary path.** The `forecast_input_records` table is populated by spreadsheet import; the sync command exists but is not scheduled or wired into normal workflows, so stale/missing records are likely.
5. **Per-cage vs. aggregated ambiguity.** `automatic_forecast` always calls `aggregate_all_cages`, so per-cage forecasts are trained only on that cage's history, but farm-level forecasts collapse cages into one series, losing cage-specific behavior.

### Proposed Next Steps

| Step | Change | Effort | Priority |
|---|---|---|---|
| 1 | Add a simple weather-proxy for future covariates: use the 7-day historical average for the same calendar day, or allow a user-entered "expected avg temp/humidity" during generation. Keep the same model; just vary `build_deployment_feature_frame`. | Small (1–2 days) | High |
| 2 | Add `sample_weight` to XGBoost training using exponential decay (e.g., weight = 0.95^(days ago)). | Small (1 day) | Medium |
| 3 | Return approximate prediction intervals by using the variance across the 3-seed ensemble or by bootstrapping residuals. Display as a shaded range in the chart. | Medium (2–3 days) | Medium |
| 4 | Schedule `forecast:sync-input-records` nightly via Laravel scheduler and add a "last synced" timestamp to the Forecast page so operators know data freshness. | Small (1 day) | High |
| 5 | Long-term: separate per-cage models for farm-level forecast by averaging per-cage predictions instead of aggregating history. | Medium (3–5 days) | Low |

### Specific Answers

- **Does it account for hen_count changes mid-period?** Yes. `Live_Hens` is a feature, and `Monthly_Mortality` is a 30-day rolling sum derived from explicit mortality logs or `Live_Hens` diffs (`ForecastingV5.py`, `compute_daily_mortality` / `compute_monthly_mortality`).
- **Could environmental data improve accuracy?** Already included (`Temperature_C`, `Humidity_Percent`, `Heat_Stress`). Value is capped by the static-future-covariate problem.

---

## 2. RBAC

### Current State

- Two roles in `users.role`: `admin` and `operator`.
- A single middleware, `EnsureAdmin`, checks `$request->user()?->isAdmin()`.
- `routes/web.php` applies `admin` middleware to only:
  - Cage mutations (`CageController::store`, `update`, `position`, `delete`, `sensor info`, etc.)
  - Device mutations (`DeviceController::store`, `regenerateKey`, `destroy`)
  - Egg log delete (`EggLoggingController::destroy`)
  - Mortality log delete (`MortalityController::destroy`)
- Everything else is accessible to any authenticated user.
- No Laravel policies, gates, or resource authorization. Controllers do not perform role checks beyond route-level middleware.
- `User::isAdmin()` is the only role check method (`app/Models/User.php:29`).

### Gaps

1. **No permission matrix documented or enforced.** It is unclear which role should manage feed batches, hardware inventory, environment thresholds, pre-orders, egg stocks, reports, or forecasts.
2. **Inconsistent destructive actions.** Feed batch create/update/delete, hardware CRUD, egg stock delete, pre-order delete, note delete, and settings changes are all unguarded and available to operators.
3. **No model-level authorization.** A motivated operator could bypass intended restrictions by directly hitting resource IDs (e.g., editing another user's note, deleting a feed batch) where only route-level middleware exists.
4. **No role-aware UI hiding.** Views likely render action buttons to operators even when the backend route would 403; this creates a poor UX.

### Proposed Permission Matrix

| Module | Admin | Operator |
|---|---|---|
| Dashboard / view data | ✅ | ✅ |
| Egg logging (create) | ✅ | ✅ |
| Egg logging (delete) | ✅ | ❌ |
| Mortality logging (create) | ✅ | ✅ |
| Mortality logging (delete) | ✅ | ❌ |
| Feed batches / consumption CRUD | ✅ | ✅ (consumption only; no delete) |
| Cage layout CRUD | ✅ | ❌ (view only) |
| Hardware inventory | ✅ | ✅ (view; assign; no delete) |
| Environment thresholds | ✅ | ❌ (view only) |
| Forecasts / reports | ✅ | ✅ (view/generate) |
| Pre-orders / egg stock | ✅ | ✅ |
| Account settings (own) | ✅ | ✅ |
| User management / PINs | ✅ | ❌ |
| Device API keys | ✅ | ❌ |

### Proposed Next Steps

| Step | Change | Effort | Priority |
|---|---|---|---|
| 1 | Create Laravel Policies for `Cage`, `FeedBatch`, `HardwareItem`, `MortalityLog`, `ProductionLog`, `Setting`, etc., and replace route `admin` middleware with `authorize()` calls in controllers. | Medium (3–4 days) | High |
| 2 | Add `@can` directives in Blade views so operator UIs hide unavailable actions. | Small–Medium (2 days) | High |
| 3 | Introduce a `manage-settings` ability and gate for threshold/farm-layout changes. | Small (1 day) | Medium |

---

## 3. Data-Integrity Safeguards

### Current State

- Application-level enforcement is already present and tested:
  - `CageController::bulkAdd` uses `lockForUpdate` and re-validates capacity inside the transaction.
  - `MortalityController` links hens via `mortality_log_hens` and decrements slot occupancy.
  - `RepairMortalityHenState` Artisan command (`mortality:repair-hen-state`) backfills missing pivot rows.
  - `OccupancyInvariantsTest` verifies `SUM(active hens) == current_occupancy` and capacity limits.
- Existing console commands:
  - `BackfillCageTransfers`
  - `BackfillChickenIds`
  - `RecoverMortalityLogs`
  - `RepairMortalityHenState`
  - `SyncForecastInputRecords`
  - `TruncateFarmData`
- Database migrations (`database/migrations/2026_01_01_000001_create_cages_table.php` and `2026_01_01_000002_create_cage_slots_table.php`) define `total_capacity` and `current_occupancy` but have no `CHECK` constraints.
- No generated columns or triggers enforce the hen↔mortality link.
- No periodic "data health" job scans for drift.

### Gaps

1. **No DB-level guardrails.** A future bug, manual SQL update, or race condition could leave `cage_slots.current_occupancy` negative or over capacity, or leave `hens.is_active = 0` rows without a corresponding `mortality_log_hens` entry.
2. **No scheduled integrity audit.** The repair commands exist but are run manually; drift can accumulate unnoticed.
3. **No soft-delete / audit trail.** Data changes (cage mutations, mortality edits) are hard to reconstruct after the fact.

### Proposed Next Steps

| Step | Change | Effort | Priority |
|---|---|---|---|
| 1 | Add a scheduled nightly Artisan command `data:integrity-check` that reports (and optionally repairs) `current_occupancy > max_chickens_per_slot`, `current_occupancy < 0`, `SUM(active hens) != current_occupancy`, inactive hens missing mortality/culling/removal records, and orphaned `mortality_log_hens` rows. Post a dashboard alert or email if violations are found. | Medium (2–3 days) | High |
| 2 | Add a migration with `CHECK (current_occupancy <= max_chickens_per_slot)` on `cage_slots` and `CHECK (current_occupancy >= 0)`. Note: MariaDB/MySQL 8 enforce CHECK constraints; verify target version. | Small (1 day) | Medium |
| 3 | Add DB indexes on frequently checked columns: `hens(cage_slot_id, is_active)`, `mortality_log_hens(hen_id)`, `cage_slots(cage_id)`. | Small (1 day) | Low |

---

## 4. Hardware / Sensor Pipeline

### Current State

- **Ingestion endpoint exists:** `POST /api/sensor-readings` authenticated by `X-Device-Key` via `App\Http\Middleware\DeviceAuth` (`routes/api.php:18`).
- **Device model:** `App\Models\Device` stores hashed API keys; `DeviceController` manages device CRUD (admin-only routes).
- **DHT22 and IR break-beam support:** `SensorIngestionController` (`app/Http/Controllers/SensorIngestionController.php`):
  - Stores environmental logs and occupancy readings.
  - Creates `occupancy_mismatch` alerts when IR count differs from `slot->current_occupancy`.
  - Triggers `EnvironmentAlertService::check()` on each environmental log.
- **Environmental alerts exist:** `EnvironmentAlertService` (`app/Services/EnvironmentAlertService.php`) creates `temperature_low`, `temperature_high`, `humidity_low`, `humidity_high` alerts once per cage per day.
- **Arduino firmware:** `LayRate - Arduino/src/main.cpp` reads DHT22 and IR break-beam and prints human-readable serial blocks; no network stack, no local buffering.
- **Relay device type** is defined in `HardwareItem::DEVICE_TYPES` (`app/Models/HardwareItem.php:29`) but is only tracked in inventory.

### Gaps

1. **No bridge from Arduino to backend.** The Arduino outputs human-readable serial blocks; nothing in the repository parses them and POSTs to `/api/sensor-readings`.
2. **No resilience to network drops.** Without a Pi-side buffer, any connectivity loss creates silent data gaps.
3. **No calibration alerts.** `last_calibration_date` is stored and editable (`app/Http/Requests/StoreHardwareItemRequest.php:26`) but never checked.
4. **No automated cooling fan.** The `relay` device type is purely inventory; no threshold-driven GPIO control or fail-safe behavior is implemented.
5. **Pi crash/power-loss fail-safe missing.** Even if a fan were implemented, loss of Pi/Arduino power leaves the fan in its last state (or off).

### Proposed Next Steps

| Step | Change | Effort | Priority |
|---|---|---|---|
| 1 | Add a lightweight Pi-side Python client (e.g., `hardware/pi_bridge.py`) that reads Arduino serial, parses the blocks, buffers readings in a local SQLite queue, and POSTs to `/api/sensor-readings` with retry/backoff. Include a systemd service file. | Medium (3–5 days) | High |
| 2 | Add a scheduled command `hardware:check-calibration` that creates an alert when `last_calibration_date` is older than a configured threshold (e.g., 180 days). | Small (1–2 days) | Medium |
| 3 | Implement a standalone Arduino/Pi fan controller sketch/service with watchdog: fan on when temp > threshold, off when below, and **hardware fail-safe** (e.g., fan defaults to ON if watchdog/heartbeat is lost). | Medium (3–4 days) | Medium |
| 4 | Add a "sensor offline" alert when `environmental_logs` has no row for a cage within the expected interval (e.g., 15 minutes). | Small (1–2 days) | High |

---

## 5. Frontend Regression Risk

### Current State

- `tests/` is populated with PHPUnit Feature tests (16 files), including `OccupancyInvariantsTest`, `EnvironmentThresholdTest`, `SensorIngestionTest`, etc.
- `@playwright/test` is in `package.json` devDependencies, but no Playwright specs or config exist.
- `layouts/app.blade.php` has been touched repeatedly (header color, FAB shape, Turbo/sidebar behavior), and each change requires manual screenshot review.
- Tailwind CSS is compiled to `public/css/tailwind.css`; regressions can be introduced by both Blade class changes and CSS rebuilds.

### Gaps

1. **No automated visual regression.** Header, sidebar, dashboard metric cards, and environment status badges are high-risk areas with no automated coverage.
2. **No E2E smoke tests for core journeys.** Login → dashboard → egg logging → environment page is not exercised end-to-end.
3. **Playwright is installed but unused.** The dependency is already present; only test files are missing.

### Proposed Next Steps

| Step | Change | Effort | Priority |
|---|---|---|---|
| 1 | Add a minimal Playwright smoke-test suite (`tests/e2e/`) covering: login, sidebar expand/collapse, header render, dashboard load, and environment page status badges. Run in CI on push/PR. | Medium (3–4 days) | High |
| 2 | Add targeted screenshot assertions for the most regressed components: `#main-header`, `#sidebar`, dashboard metric cards, and the Forecast FAB. Store baseline screenshots and fail on pixel diff above threshold. | Medium (2–3 days) | Medium |
| 3 | Run Playwright against both desktop (1280×720) and mobile (375×812) viewports. | Small (adds 1 day) | Medium |
| 4 | Add a CI step that runs `npx @tailwindcss/cli` and fails if `git diff public/css/tailwind.css` is non-empty after a Blade change, ensuring CSS is always rebuilt. | Small (1 day) | Low |

---

## 6. Loading States, Skeleton Loading, and Modals

### 6.1 Loading / Skeleton Feedback

#### Current State

- **Global form submission indicator:** `loadingButton()` in `resources/views/layouts/app.blade.php` is used on 17 forms across cages, feed, egg-logging, mortality, hardware, and egg-stock pages.
- **Skeleton component exists:** `resources/views/components/skeleton.blade.php` renders an `aria-busy="true"` animate-pulse block and is used as Turbo Frame fallback content on:
  - Dashboard: stats, cage overview, feed/mortality
  - Environment: live data, logs
  - Feed: live data
  - Eggs: stocks, pre-orders, recent logs
  - Mortality: logs
  - Analytics: charts
  - Forecast: results
  - Chickens: inventory, mortality/culling/removal records
  - Notifications: table
- **Inline loading text/spinners** are used in `resources/views/cages/index.blade.php` for farm-canvas save and slot-panel fetches.

#### Gaps

1. **No `aria-busy` on Turbo Frame containers.** The skeleton component has `aria-busy="true"`, but the parent `<turbo-frame>` elements themselves do not.
2. **Pages without skeleton fallback for lazy frames:** `resources/views/chickens/index.blade.php` lazy-loads inventory/mortality/culling/removal records but the inventory-list frame has no `loading="lazy"` and no skeleton.
3. **Synchronous pages with no loading feedback:** `resources/views/reports.blade.php`, `resources/views/account.blade.php`, and `resources/views/egg-logging.blade.php` (slot grid is synchronous; only nested `egg-logs-list` has a skeleton).
4. **No global loading bar for non-Turbo navigation.** Only the Turbo loading bar exists; non-Turbo forms rely solely on `loadingButton()`.

### 6.2 Confirmation Modals

#### Current State

- Shared component: `resources/views/components/confirm-modal.blade.php`.
- Explicitly included in:
  - `resources/views/feed.blade.php:81`
  - `resources/views/notes/index.blade.php:130`
  - `resources/views/hardware/index.blade.php:355`
  - `resources/views/cages/index.blade.php:571`
- Auto-wired to forms with `data-confirm` and triggered via `confirmModal(message, form, actionLabel)`.
- Data-confirm is used correctly for feed batch delete, feed consumption delete, egg log delete, mortality log delete, hardware item remove, device delete, egg stock batch delete, and note delete.

#### Native `confirm()` / `alert()` Instances Still Present (5)

| # | File | Line | Current behavior |
|---|---|---|---|
| 1 | `resources/views/feed.blade.php` | 373 | `alert('Could not check batch status. Please try again.')` on fetch failure |
| 2 | `resources/views/egg-logging.blade.php` | 422 | `alert('No override PIN set yet — please set one in Account Settings.')` after override verify |
| 3 | `resources/views/hardware/index.blade.php` | 75 | `onclick="return confirm('Regenerate API key? The old key will stop working immediately.')"` |
| 4 | `resources/views/eggs/pre-orders/_table.blade.php` | 60 | `onsubmit="return confirm('Cancel this pre-order?')"` |
| 5 | `resources/views/chickens/_mortality-records.blade.php` | 43 | `onsubmit="return confirm('Delete this mortality record?')"` |

#### ⚠️ Bugs Discovered

- **BUG — Missing `<x-confirm-modal />` on parent pages.** `resources/views/mortality.blade.php` and `resources/views/egg-logging.blade.php` do **not** include the confirm-modal component, yet their lazy-loaded frame partials (`resources/views/mortality/_logs.blade.php` and `resources/views/egg-logging/_logs.blade.php`) contain `data-confirm` forms. Because the modal markup is absent, `confirmModal()` is undefined and the delete buttons likely do nothing or throw a JS error in those contexts.
- **BUG — Unregistered `@can('admin')` gate.** `resources/views/chickens/_mortality-records.blade.php:40` uses `@can('admin')`, but no `admin` ability or gate is registered anywhere in the app. The delete button is therefore never rendered for any user, including admins.

### 6.3 Other Essential Modals

#### Current State

- **Alerts modal:** `resources/views/components/alerts-modal.blade.php` is included in `resources/views/layouts/app.blade.php:307`, has proper `role="dialog"`, `aria-modal="true"`, backdrop click acknowledgment, Escape close, and server-side acknowledgment POST.
- **Informational modals** are implemented inline throughout:
  - Dashboard: onboarding, stats detail, KPI breakdown
  - Cages: add, edit, delete
  - Chickens: register, move, remove, removal, cull, health event, weight check
  - Feed: add batch, edit batch, consumption, farm entry
  - Hardware: add, edit, device
  - Eggs: stock add/edit, pre-order add/edit status
  - Mortality: edit
  - Egg logging: override PIN, edit log
  - Forecast: download template, import

#### Gaps

1. **No shared generic modal component.** Every modal re-declares the same `fixed inset-0 z-50`, backdrop blur, centered card, close X, and Escape handler.
2. **Inconsistent modal markup details:**
   - `resources/views/forecast.blade.php` download/import modals lack `role="dialog"` and `aria-modal="true"`.
   - `resources/views/cages/index.blade.php` `deleteCageModal` and `resources/views/notes/index.blade.php` `noteEditModal` omit `min-h-screen min-h-[100dvh]` used elsewhere.
   - Several chicken modals have both `class="hidden"` and `style="display: none;"` (`removeModal`, `moveModal`), which is redundant and can conflict with JS toggling.
3. **Duplicated Escape-key handling.** Almost every page with a modal adds its own `keydown` listener for `Escape`.
4. **Onboarding modal is not dismissible.** `resources/views/dashboard.blade.php` onboarding modal has no close button and no Escape handler (intentional, but worth noting).

### 6.4 Duplication / Maintainability Gaps

1. **Modal shell duplicated ~20 times.** Each CRUD modal repeats the wrapper, backdrop, card styling, close button, and focus/escape logic.
2. **Confirm-modal included redundantly.** `resources/views/cages/index.blade.php` builds its own elaborate `deleteCageModal` inline and then also includes `<x-confirm-modal />` at the bottom (the latter appears unused in that file).
3. **Skeleton wrapper duplicated.** Some skeleton partials add extra `bg-gray-200 rounded animate-pulse` divs around `<x-skeleton>` instead of extending the component.
4. **Loading indicator patterns mixed:** a page may use `loadingButton`, inline text, skeleton fallback, and spinner overlay all differently.

### Proposed Next Steps (Loading States / Modals)

| Step | Change | Effort | Priority |
|---|---|---|---|
| 1 | Add `<x-confirm-modal />` to `resources/views/mortality.blade.php` and `resources/views/egg-logging.blade.php`; replace the 5 remaining native `confirm()`/`alert()` calls; fix or remove the bogus `@can('admin')` usage in `resources/views/chickens/_mortality-records.blade.php`. | Small (1–2 days) | High |
| 2 | Create a shared generic `<x-modal>` shell component and migrate the ~20 inline CRUD modals to it, enforcing consistent `role`, `aria-modal`, backdrop click, and Escape behavior. | Medium (3–4 days) | Medium |
| 3 | Add `aria-busy="true"` to lazy Turbo Frames and add skeleton fallbacks to `reports`, `account`, and the main `egg-logging` view. | Small–Medium (2 days) | Medium |
| 4 | Add `loadingButton` to `account.blade.php` password/PIN forms and add a spinner/skeleton to `reports.blade.php` filter/report generation. | Small (1 day) | Low |

---

## Priority Ranking

> Check off items as they are completed. This list combines all proposed steps from sections 1–6, ordered High → Medium → Low.

### High

- [ ] **High:** Fix broken/missing confirmation modal wiring — add `<x-confirm-modal />` to `resources/views/mortality.blade.php` and `resources/views/egg-logging.blade.php`; replace the 5 remaining native `confirm()`/`alert()` calls; fix or remove the bogus `@can('admin')` gate usage in `resources/views/chickens/_mortality-records.blade.php`.
- [ ] **High:** Formalize RBAC with Laravel policies/gates and hide unauthorized UI actions in Blade views.
- [ ] **High:** Build Pi serial-to-API bridge with local buffering and retry/backoff.
- [ ] **High:** Add Playwright smoke tests for login, sidebar, header, dashboard, and environment page.
- [ ] **High:** Schedule `forecast:sync-input-records` nightly and add a "last synced" timestamp to the Forecast page.
- [ ] **High:** Add a "sensor offline" alert when `environmental_logs` has no row for a cage within the expected interval.
- [ ] **High:** Add a scheduled nightly `data:integrity-check` Artisan command that reports/repairs occupancy/capacity and mortality-hen invariant violations.

### Medium

- [ ] **Medium:** Add future-covariate weather proxy to the forecast model (historical 7-day average or user-entered expected temp/humidity).
- [ ] **Medium:** Create a shared generic `<x-modal>` shell component and migrate the ~20 inline CRUD modals to it.
- [ ] **Medium:** Add `aria-busy="true"` to lazy Turbo Frames and add skeleton fallbacks to `reports`, `account`, and the main `egg-logging` view.
- [ ] **Medium:** Add recency weighting to XGBoost training (`sample_weight` with exponential decay).
- [ ] **Medium:** Add DB CHECK constraints on `cage_slots.current_occupancy` and targeted indexes for integrity checks.
- [ ] **Medium:** Add a scheduled `hardware:check-calibration` command that alerts when `last_calibration_date` exceeds a configured threshold.
- [ ] **Medium:** Implement automated cooling fan control with hardware fail-safe (defaults ON on watchdog loss).
- [ ] **Medium:** Return approximate prediction intervals in forecasts and display as a shaded range.

### Low

- [ ] **Low:** Add targeted DB indexes: `hens(cage_slot_id, is_active)`, `mortality_log_hens(hen_id)`, `cage_slots(cage_id)`.
- [ ] **Low:** Add Playwright screenshot assertions for `#main-header`, `#sidebar`, dashboard metric cards, and the Forecast FAB.
- [ ] **Low:** Add a CI step that fails if `public/css/tailwind.css` is stale after Blade changes.
- [ ] **Low:** Add `loadingButton` to `account.blade.php` forms and a spinner/skeleton to `reports.blade.php`.
- [ ] **Low:** Refactor farm-level forecasting to average per-cage predictions instead of aggregating history.
