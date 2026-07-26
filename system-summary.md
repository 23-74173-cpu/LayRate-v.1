# LayRate System Summary

## Current State (Commit 3 — Sprint 1 Complete)

### Sprint 1 P0 Items Implemented

#### 1. Reports AJAX Conversion
- **Endpoint**: `GET /reports/data` returns JSON (`html` + `summary`)
- **JS**: `reportFetch()` + `renderReportResults()` + `updateCsvHref()`
- **Partial**: Extracted `reports/_preview.blade.php` (AJAX-updatable)
- **History**: `pushState` on filter change, `popstate` handler with form-sync from URL
- **CSV**: Button href updates dynamically via `updateCsvHref()`
- **42/42 tests pass**

#### 2. Forecast Dropdowns
- **Approach**: Turbo Frame navigation via setting `src` attribute on `turbo-frame#forecast-workspace`
- **Scope toggle**: Whole Farm / Per Cage / Per Breed — full `data-turbo-action="advance"` links
- **Cage/Breed dropdown**: `onchange` sets `frame.src` to load new scope without full page reload
- **Chart**: Re-initializes via `turbo:load` event inside the frame script
- **12/12 tests pass**

#### 3. Egg Section Tabs
- **Tabs**: `_tabs.blade.php` — 5 tabs: Egg Logging, Recent Logs, Egg Stocks, Pre-Orders, History
- **Frame**: `turbo-frame id="egg-content" data-turbo-action="advance"` on all 5 pages
- **Lazy-load**: Tab click navigates within frame via Turbo Drive
- **21/21 tests pass**

#### 4. Egg Production History
- **Group-by**: Day / Week / Month with same-frame navigation via `data-turbo-frame="egg-content"`
- **Pagination**: Links intercepted by Turbo Drive within the frame
- **Back/forward**: `data-turbo-action="advance"` enables history navigation
- **14/14 tests pass**

### Verification
- **89/89** automated Playwright tests pass
- All 4 features verified with 0 full-page loads during filter/tab/scope navigation

### Architecture Changes
- **`reports.blade.php`**: Converted from full-page GET to JSON fetch + DOM replace; `popstate` handler syncs form from URL params; uses `pushState`/`replaceState` for history
- **`forecast/_workspace.blade.php`**: Dropdown `onchange` sets `turbo-frame#forecast-workspace` src attribute
- **`eggs/pre-orders.blade.php`**: Added `<h2 class="sr-only">` inside turbo frame for heading consistency
- **Verification scripts**: `test_sprint1_verify.py` (comprehensive), `test_reports_ajax.py`, `test_forecast_dropdowns.py`, `test_egg_tabs.py`, `test_egg_history.py`

### Not Yet Implemented / Gaps
- No automated alert generation from sensor data crossing thresholds
- No test coverage for existing pre-Sprint-1 features
- No Arduino-to-PHP data ingestion pipeline
- No email/password reset flow
- Forecast algorithm remains a toy (averaging + 0.3% sinusoidal variation)
