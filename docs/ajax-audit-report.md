# AJAX Conversion Audit — Full Codebase Inventory

> **Date:** 2026-07-25  
> **Scope:** Every UI control that changes displayed data across all authenticated modules.  
> **Methodology:** Manual inspection of every Blade view + controller route.

---

## 1. Inventory Table

Legend: **FR** = Full page reload (GET link or form submit); **TF** = Turbo Frame navigation (partial load); **AJ** = AJAX fetch/JSON; **CS** = Client-side JS only (no server call); **PL** = Polling/live data

### Dashboard

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| D1 | Cage filter tabs (All / CAGE-A, etc.) | `dashboard.blade.php:42-55` | **TF** — sets frame `src` + `.reload()` | Already AJAX | — | Turbo Frame navigation; works correctly |
| D2 | Metric card clicks → navigate away | `_metric-cards.blade.php:6-60` | **TF** — `Turbo.visit()` to another route | N/A (navigation) | — | Intentional page nav |
| D3 | Feed/Mortality rows → navigate away | `_feed-mortality.blade.php:20,60` | **TF** — `Turbo.visit()` with filters | N/A (navigation) | — | Intentional page nav |
| D4 | Onboarding farm layout form | `dashboard.blade.php:16-35` | **FR** — standard POST | Low | Low | Rarely used (one-time setup) |
| D5 | Live clock tick | `dashboard.blade.php:225-236` | **PL** — `setInterval(1000)` client-only | N/A | — | Not a data control |

### Analytics

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| A1 | Cage scope tabs (All / CAGE-A…CAGE-U) | `analytics.blade.php:12-28` | **AJ** — `analyticsFetch()` → JSON + `renderAnalyticsCharts()` | Already AJAX (just fixed) | — | Shared `analyticsFetch()` helper; updates 3 charts + KPI + URL atomically |
| A2 | Period tabs (Week / Month / 3 Months) | `analytics.blade.php:62-70` | **AJ** — same `analyticsFetch()` pattern | Already AJAX (just fixed) | — | Same helper as A1 |
| A3 | Turbo Frame lazy-load (initial charts) | `analytics.blade.php:75` | **TF** — `loading="lazy"` | Already partial | — | Skeleton shown; frame src updated by A1/A2 handlers |

### Forecast

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| F1 | Scope links (Farm / Per Cage / Per Breed) | `_workspace.blade.php:27-38` | **TF** — `data-turbo-frame="forecast-workspace"` | Already partial | — | Turbo Frame GET; frame content has chart |
| F2 | Cage dropdown (scope=cage) | `_workspace.blade.php:43-48` | **FR** — `onchange → this.form.submit()` | **High** | **High** | Triggers full POST + page reload just to show a different dropdown. Could use Turbo Frame or inline fetch. |
| F3 | Breed dropdown (scope=breed) | `_workspace.blade.php:53-58` | **FR** — same `onchange → submit()` | **High** | **High** | Same issue as F2 |
| F4 | Horizon radio buttons (7/14/30 day) | `_workspace.blade.php:68-74` | **CS** — form input, no auto-submit | Low | Low | User must click Generate |
| F5 | Generate Forecast button | `_workspace.blade.php:77` | **TF** — POST with `data-turbo-stream` | Already partial | — | Shows loading overlay + progress bar; Turbo Stream response replaces frames |
| F6 | Month select (calendar) | `_calendar.blade.php:48-53` | **TF** — `data-turbo-frame="production-calendar"` | Already partial | — | Turbo Frame GET |
| F7 | Year select (calendar) | `_calendar.blade.php:54-59` | **TF** — same as F6 | Already partial | — | Turbo Frame GET |
| F8 | Prev/Next month buttons + Today link | `_calendar.blade.php:63-76` | **TF** — same frame navigation | Already partial | — | Turbo Frame GET |
| F9 | Clear Forecast button | `_calendar.blade.php:78-92` | **TF** — POST → Turbo Stream | Already partial | — | Replaces both frames |
| F10 | Calendar day click → single-day forecast | `_calendar.blade.php:200` | **CS** → opens modal → form POST → Turbo Stream | Already partial | — | Modal opens client-side; form uses Turbo Stream |
| F11 | Download input sheet (start/end date) | `forecast.blade.php:100-106` | **FR** — GET → file download | Low | Low | File download; needs full response |
| F12 | Import file upload | `forecast.blade.php:208` | **AJ** — XHR upload → JSON | Already AJAX | — | Returns JSON, on success does `window.location.reload()` (could use Turbo visit instead) |

### Feed & Nutrition

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| FD1 | Tab switcher (Batches / Consumption / FCR) | `_live-data.blade.php:31-35` | **CS** — `feedSwitchTab()` toggles `.hidden`, updates `?tab=` URL | Already client-side | — | No server call needed |
| FD2 | Batches table pagination | `_live-data.blade.php:253` | **FR** — standard GET paginator links | **Medium** | **Medium** | Full page reload resets tab state, loses scroll position. Turbo Frame pagination (like mortality already has) would fix. |
| FD3 | Consumption table pagination | `_live-data.blade.php:330` | **FR** — same as FD2 | **Medium** | **Medium** | Same issue. |
| FD4 | FCR cage selector | `_live-data.blade.php:346` | **AJ** — inline `fetch()` with `AbortController` | Already AJAX | — | Fetches HTML, swaps into `#fcr-content`. Good pattern. |
| FD5 | FCR group-by buttons (Day/Week/Month) | `_live-data.blade.php:357-364` | **AJ** — same `fcrLoad()` pattern | Already AJAX | — | Same good pattern as FD4. |
| FD6 | Feed batch CRUD forms (store/update/destroy) | `feed.blade.php` / controllers | **FR** — standard POST full page reload | **Medium** | **Medium** | After adding/editing a batch, user is bounced back. Inline AJAX like egg-logging store would feel smoother. |
| FD7 | Consumption CRUD forms | `feed.blade.php` / controllers | **FR** — same as FD6 | **Medium** | **Medium** | Same. |

### Egg Logging

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| EG1 | Egg section tabs (Logging / Recent / Stocks / Pre-Orders / History) | `_tabs.blade.php:1-7` | **FR** — standard `<a>` route links | **Medium** | **Medium** | Each tab is a full page — losing scroll and form state. Could use Turbo Frame + lazy load or inline `fetch()`. |
| EG2 | Cage selector (inline log form) | `egg-logging.blade.php:68` | **CS** — `switchCage()` shows/hides `.cage-grid` divs | Already client-side | — | No server call |
| EG3 | Slot card click (populate log form) | `egg-logging.blade.php:101-120` | **CS** — `selectSlot()` fills form fields | Already client-side | — | No server call |
| EG4 | Log Entry form submit | `egg-logging.blade.php:571` | **AJ** — `fetch()` POST → JSON → update slot card | Already AJAX | — | Good pattern; shows checkmark on success |
| EG5 | Sensor override PIN/PW submission | `egg-logging.blade.php:505` | **AJ** — `fetch()` POST → JSON | Already AJAX | — | Throttled (6/min) |
| EG6 | Edit log (pencil → modal → PUT) | `_logs.blade.php:53` → form submit | **FR** — modal opens client-side, but form submit is full page reload | **Medium** | **Medium** | Similar to egg-logging store: could submit edit via AJAX and update table row in-place |
| EG7 | Delete log button | `_logs.blade.php:58` | **FR** — standard DELETE form | Low | Low | Deletion with confirmation is fine as full page |
| EG8 | Logs list pagination | `_logs.blade.php:75` | **FR** — standard GET paginator | **Medium** | **Medium** | Full page reload; Turbo Frame like mortality has would be smoother |
| EG9 | Recent Logs: Cage/Slot/Breed filters | `eggs/recent-logs.blade.php:17-70` | **FR** — GET form submit → full page | **High** | **High** | Filter form is used frequently; full reload resets all filters, loses scroll. High-value AJAX target. |
| EG10 | Recent Logs: Filter/Reset buttons | `eggs/recent-logs.blade.php:75-78` | **FR** — same GET form | **High** | **High** | Same as EG9 |
| EG11 | Egg Production History: Group-by tabs (Day/Week/Month) | `egg-production-history.blade.php:43-47` | **FR** — standard GET links | **Medium** | **Medium** | Frequently used period toggle; full reload resets pagination position |
| EG12 | Egg Production History: table pagination | `egg-production-history.blade.php:77` | **FR** — standard GET paginator | **Medium** | **Medium** | Same |

### Egg Stocks

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| ES1 | Add Stock form submit | `eggs/stocks.blade.php:562` | **AJ** — `fetch()` POST → JSON → `Turbo.visit()` replace | Already AJAX | — | On success does Turbo.visit (full page replace); could update table inline + show notification instead |
| ES2 | Stock Cage dropdown (in modal) → pool data | `eggs/stocks.blade.php:539` | **AJ** — `fetch()` → JSON pool data | Already AJAX | — | Good pattern |
| ES3 | Egg weight config form | `eggs/stocks.blade.php:65` | **FR** — standard POST | Low | Low | One-time config, rarely changed |
| ES4 | Thresholds config form | `eggs/stocks.blade.php:112` | **FR** — standard POST | Low | Low | Rarely changed |
| ES5 | Stock table lazy load | `eggs/stocks.blade.php:162` | **TF** — `loading="lazy"` | Already partial | — | Turbo Frame |
| ES6 | Edit stock (pencil → modal → PUT) | `_live-data.blade.php:47` → form | **FR** — modal opens client-side, form submit = full reload | **Medium** | **Medium** | Could AJAX-update the table row |
| ES7 | Stock table pagination | `_live-data.blade.php:67` | **FR** — standard GET paginator (inside Turbo Frame, so partial) | Already partial (TF) | — | Turbo Frame handles pagination |
| ES8 | Delete stock button | `_live-data.blade.php:53` | **FR** — standard DELETE | Low | Low | Confirmation + reload is acceptable |

### Environment

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| EN1 | Tab switcher (Live Data / Log History) | `environment.blade.php:10-13` | **CS** — `switchEnvTab()` toggles panels, starts/stops polling | Already client-side | — | No server call |
| EN2 | Live Data polling (10s interval) | `environment.blade.php:54-71` | **PL** — `fetch()` every 10s → `DOMParser` replace frame innerHTML | Already polling | — | Re-inits Chart.js charts on each poll via `initEnvCharts()` |
| EN3 | Log History lazy load | `environment.blade.php:27` | **TF** — `loading="lazy"` | Already partial | — | Turbo Frame |
| EN4 | Alert threshold config form | `_live-data.blade.php:33` | **FR** — standard POST | Low | Low | Infrequent config change |

### Hardware

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| H1 | Add Device form submit | `hardware/index.blade.php` → controller | **FR** — standard POST | Low | Low | Infrequent action; full reload acceptable |
| H2 | Edit Device form submit | `hardware/index.blade.php` → controller | **FR** — standard PUT | Low | Low | Same |
| H3 | Delete Device | `hardware/index.blade.php` → controller | **FR** — standard DELETE | Low | Low | Same |
| H4 | Device type select (show/hide slot/cage fields) | `hardware/index.blade.php:55-63` | **CS** — `updateAddAssignment()` toggles form fields | Already client-side | — | No server call |
| H5 | Live data table lazy load | `hardware/index.blade.php:31-33` | **TF** — `loading="lazy"` | Already partial | — | Turbo Frame |
| H6 | Hardware table pagination | `hardware/index.blade.php:115` | **TF** — paginator inside Turbo Frame | Already partial | — | Turbo Frame handles pagination |

### Chickens / Inventory

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| CH1 | Tab switcher (Inventory / Mortality / Culled / Removed) | `chickens/index.blade.php:17-23` | **CS** — `switchTab()` toggles panels, updates `?tab=` URL | Already client-side | — | No server call |
| CH2 | Status radio pills (All / Active / Inactive) | `chickens/index.blade.php:40-41` | **TF** — sets frame `src` → Turbo navigates inventory frame | Already partial | — | Works via Turbo Frame; no page reload |
| CH3 | Tag search (debounced 300ms) | `chickens/index.blade.php:51-54` | **TF** — `debounceFilter()` → sets frame `src` | Already partial | — | Smooth; Turbo Frame handles filtering |
| CH4 | Cage filter dropdown | `chickens/index.blade.php:60-65` | **TF** — `filterInventory()` sets frame `src` | Already partial | — | Same pattern |
| CH5 | Breed filter dropdown | `chickens/index.blade.php:71-76` | **TF** — same | Already partial | — | Same |
| CH6 | Sort dropdown (8 options) | `chickens/index.blade.php:82-91` | **TF** — same | Already partial | — | Same |
| CH7 | Clear filters button | `chickens/index.blade.php:95` | **TF** — `clearFilters()` resets + sets frame `src` | Already partial | — | Same |
| CH8 | Inventory list lazy load | `chickens/index.blade.php:120-122` | **TF** — `loading="lazy"` | Already partial | — | Turbo Frame; filters forward params |
| CH9 | Inventory list pagination | `_inventory-list.blade.php:139` | **TF** — paginator inside Turbo Frame | Already partial | — | Turbo Frame handles pagination |
| CH10 | Mortality / Culling / Removal records lazy load | `chickens/index.blade.php:193-213` | **TF** — `loading="lazy"` each | Already partial | — | Separate frames per tab panel |
| CH11 | Mortality form (all fields) | `chickens/index.blade.php:153-179` | **FR** — standard POST | **Medium** | **Medium** | Recording mortality via AJAX + inline table update would be smoother (similar to egg-logging pattern) |
| CH12 | Bulk Move / Cull / Remove form submits | `chickens/index.blade.php:107-115` | **FR** — modal → standard POST | **Medium** | **Medium** | After action, page reloads. Could AJAX-submit and update inventory frame |
| CH13 | Cage row expand/collapse | `_inventory-list.blade.php:17-18` | **CS** — `toggleCage()` toggles slot list | Already client-side | — | Good |
| CH14 | Slot expand/collapse | `_inventory-list.blade.php:49-50` | **CS** — `toggleSlot()` toggles hen list | Already client-side | — | Good |
| CH15 | Toggle columns button | `_inventory-list.blade.php:30-33` | **CS** — `toggleColumns()` shows/hides columns | Already client-side | — | Good |
| CH16 | Mortality records delete | `_mortality-records.blade.php:30-36` | **TF** — DELETE form inside Turbo Frame → frame navigation | Already partial | — | Turbo Frame handles it |
| CH17 | Mortality records pagination | `_mortality-records.blade.php:47` | **TF** — paginator inside Turbo Frame | Already partial | — | Turbo Frame |
| CH18 | Culling records pagination | `_culling-records.blade.php:34` | **TF** — paginator inside Turbo Frame | Already partial | — | Turbo Frame |
| CH19 | Removal records pagination | `_removal-records.blade.php:34` | **TF** — paginator inside Turbo Frame | Already partial | — | Turbo Frame |
| CH20 | "Place into cage" links | `_inventory-list.blade.php:11-14`, `_unplaced-list.blade.php:43-46` | **FR** — standard GET link | Low | Low | Navigation to bulk-add page; intentional |

### Cage Management

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| CA1 | Cage tab filter (All / A / B / C / etc.) | `cages/index.blade.php:177-191` | **CS** — `filterCage()` shows/hides `.cage-card` | Already client-side | — | All data already in DOM |
| CA2 | Slot expand panel (click mini slot) | `cages/index.blade.php:290-307` | **AJ** — `fetch()` GET hens-json → renders inline | Already AJAX | — | Good pattern |
| CA3 | Add Cage form submit | `cages/index.blade.php` → controller | **FR** — standard POST | Low | Low | Mutation; confirmation is important |
| CA4 | Edit Cage form submit | `cages/index.blade.php` → controller | **FR** — standard PUT | Low | Low | Same |
| CA5 | Delete Cage (confirm + force) | `cages/index.blade.php:777,1905` | **AJ** — `fetch()` DELETE → `Turbo.visit()` | Already AJAX | — | Good; confirmation modal + inline fetch |
| CA6 | Cage drag/drop on farm layout canvas | `cages/index.blade.php:1312-1314` | **CS** — drag/drop updates `pendingMoves` | Already client-side | — | No server call until save |
| CA7 | Save Layout button | `cages/index.blade.php:70` | **AJ** — `fetch()` POST → `Turbo.visit()` | Already AJAX | — | Batches position updates |
| CA8 | Grid Settings (rows × cols) | `cages/index.blade.php:148,154` | **AJ** — `fetch()` POST → updates farm grid | Already AJAX | — | |
| CA9 | Rearrange / reorder slots | `cages/index.blade.php:2006-2042` | **AJ** — `fetch()` POST → `Turbo.visit()` | Already AJAX | — | |
| CA10 | Print label button | `cages/index.blade.php:388` | `window.open()` new window | N/A | — | Printer-friendly; intentional |
| CA11 | Bulk-add form submit | `cages/bulk-add.blade.php` → controller | **FR** — standard POST | Low | Low | Infrequent; mutation with hen assignment |

### Reports

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| R1 | Report Type dropdown | `reports.blade.php:43-50` | **FR** — GET form submit → full reload | **High** | **High** | Frequently used filter; full reload resets all other filters + scroll |
| R2 | From date picker | `reports.blade.php:54-55` | **FR** — same GET form | **High** | **High** | Same as R1 |
| R3 | To date picker | `reports.blade.php:58-60` | **FR** — same GET form | **High** | **High** | Same |
| R4 | Cage filter dropdown | `reports.blade.php:64-70` | **FR** — same GET form | **High** | **High** | Same |
| R5 | Reason filter (mortality only) | `reports.blade.php:76-82` | **FR** — same GET form | **High** | **High** | Same |
| R6 | Generate Report button | `reports.blade.php:85-87` | **FR** — submits GET form → full reload | **High** | **High** | Core action; whole page reloads |
| R7 | Export CSV | `reports.blade.php:89-91` | **FR** — GET → CSV download | N/A | — | Needs full response for file download |
| R8 | View Printable / Back to Preview | `reports.blade.php:116-118,93-95` | **FR** — GET links | N/A | — | Printer-friendly; intentional full page |
| R9 | Print button | `reports.blade.php:96-98` | `window.print()` | N/A | — | Browser print |
| R10 | Pagination | `reports.blade.php:147` | **FR** — standard GET links | **High** | **High** | Full reload loses scroll and filter state |

### Notifications

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| N1 | Notifications table lazy load | `notifications/index.blade.php:19-21` | **TF** — `loading="lazy"` | Already partial | — | Turbo Frame |
| N2 | Mark individual alert read | `_table.blade.php:32-35` | **TF** — POST → frame navigation | Already partial | — | Turbo Frame handles it |
| N3 | Mark all read | route | **FR** — standard POST | Low | Low | Infrequent action |
| N4 | Notifications pagination | `_table.blade.php:47` | **TF** — paginator inside Turbo Frame | Already partial | — | Turbo Frame |

### Pre-Orders

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| PO1 | Status / Egg Size / Date range filters | `eggs/pre-orders.blade.php:48-75` | **FR** — GET form submit → full reload | **Medium** | **Medium** | Filter-heavy page; full reload resets all |
| PO2 | Apply Filters / Reset buttons | `eggs/pre-orders.blade.php:77-82` | **FR** — same form | **Medium** | **Medium** | Same |
| PO3 | Pre-orders table lazy load | `eggs/pre-orders.blade.php:94-96` | **TF** — `loading="lazy"` with filter params | Already partial | — | Turbo Frame |
| PO4 | Pool data load (available stock) | `eggs/pre-orders.blade.php:286-296` | **AJ** — `fetch()` → JSON | Already AJAX | — | Good pattern |
| PO5 | Add Pre-Order form submit | `eggs/pre-orders.blade.php` → controller | **FR** — standard POST | Low | Low | Mutation; full reload acceptable |
| PO6 | Edit Status (modal → PATCH) | `_table.blade.php:48-49` → form | **FR** — modal opens client-side; form submit = full reload | Low | Low | Infrequent status change |
| PO7 | Delete order | `_table.blade.php:51-55` | **FR** — standard DELETE | Low | Low | Confirmation + reload acceptable |
| PO8 | Pre-orders table pagination | `_table.blade.php:66` | **TF** — paginator inside Turbo Frame | Already partial | — | Turbo Frame |

### Account / Settings / Profile

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| AC1 | Tab switcher (Profile / Staff / Security) | `profile.blade.php:11-14` | **CS** — `switchProfileTab()` toggles panels | Already client-side | — | No server call |
| AC2 | Profile update form (name/email) | `profile.blade.php:67-74` | **FR** — standard POST | Low | Low | Infrequent |
| AC3 | Password change form | `profile.blade.php:131-154` | **FR** — standard POST | Low | Low | Sensitive action; full page is fine |
| AC4 | PIN change form | `profile.blade.php:175-209` | **FR** — standard POST | Low | Low | Sensitive action |
| AC5 | Add/Edit/Deactivate user (admin) | `profile.blade.php:225-258` | **FR** — standard POST/PUT | Low | Low | Admin actions; infrequent |
| AC6 | Sign out other devices | `profile.blade.php:365-372` | **FR** — standard POST | Low | Low | Security action |

### Notes

| # | Control | Location | Mechanism | AJAX Candidate? | Priority | Notes |
|---|---|---|---|---|---|---|
| NO1 | Add Note form | `notes/index.blade.php:23-30` | **FR** — standard POST | Low | Low | Infrequent; simple page |
| NO2 | Notes pagination | `notes/index.blade.php:81` | **FR** — standard GET paginator | Low | Low | Low-traffic page |

---

## 2. Pages with Charts / Live Data / Polling

| Page | Charts | Auto-refresh? | Poll Pattern | Notes |
|---|---|---|---|---|
| **Dashboard** | No charts | No | — | Metric cards render server-side via Turbo Frame; KPI modal shows pre-loaded data |
| **Analytics** | 3 Chart.js (HDEP, Eggs, Feed vs HDEP) | No | — | AJAX-driven via `analyticsFetch()` + `renderAnalyticsCharts()`; destroyed/recreated on every scope change |
| **Forecast** | 1 Chart.js (forecast line) | No | — | Created on frame load; destroyed/recreated via `LayRateChart` on generate/clear |
| **Environment** | 2 Chart.js (temp, humidity 24h trend) | **Yes — 10s poll** | Inline `fetch()` → `DOMParser` → replace frame innerHTML → `initEnvCharts()` | Stale-on-poll bug possible if chart instances aren't destroyed before re-creation (currently uses `LayRateChart.create()` which calls `destroy()` first) |
| **Feed FCR** | No charts | No | — | Tab uses inline `fetch()` with AbortController |
| **All others** | None | No | — | |

---

## 3. Reusable Infrastructure Already Built

| Pattern | Location | Used By | Notes |
|---|---|---|---|
| `LayRateChart` (create/destroy/destroyAll) | `app.blade.php:616-652` | Analytics, Forecast, Environment | Singleton lifecycle manager; calls `inst.destroy()` before creating new; `turbo:before-cache` tears all down |
| `analyticsFetch()` (fetch + render + replaceState) | `analytics.blade.php:192-234` | Analytics only | Pattern is reusable: fetch JSON → update KPI elements by ID → call chart renderer → run callback → replaceState |
| `renderAnalyticsCharts()` (shared chart renderer) | `analytics.blade.php:96-196` | Analytics only | Accepts `(logs, feedLogs, cageColor, isAll, cageCode)` and renders all 3 charts atomically |
| Turbo Frame `loading="lazy"` + `src` attribute | Multiple views (Chickens, Hardware, Notifications, Pre-Orders, Environment logs) | Standard pattern | Frame fetches content lazily; skeleton shown while loading |
| Turbo Frame filter pattern (set `frame.src` → Turbo navigates) | Chickens inventory (`filterInventory()`) | Chickens | Debounced search, dropdowns, radio pills all set frame `src` attribute; Turbo handles the fetch |
| Inline `fetch()` with `AbortController` | Feed FCR (`fcrLoad()`) | Feed only | Cancels in-flight request before starting new one; fetches HTML, swaps into container |
| `fetch()` POST with JSON → DOM update | Egg logging store, Add Stock | Egg Logging, Stocks | Intercepts form submit, sends JSON, on success updates relevant DOM (slot card checkmark), on error shows toast |
| `Turbo.visit()` for programmatic navigation | Stocks (after add), Cages (after delete) | Stocks, Cages | Used when inline update is not enough and a full Turbo Drive navigation is needed |

---

## 4. Prioritized Recommendations

### P0 — High impact, frequently used, clear AJAX win

| Order | Module | Controls | Current | Recommended Pattern | Est. Effort |
|---|---|---|---|---|---|
| 1 | **Reports** — All filters (type, dates, cage, reason, pagination) | R1-R6, R10 | Full page GET reload every filter change | `analyticsFetch()`-style: fetch JSON → update result table + summary pills inline. Same pattern as Analytics. | Medium |
| 2 | **Forecast** — Cage/Breed dropdowns auto-submit | F2, F3 | `onchange → this.form.submit()` full page reload | Wrap workspace in Turbo Frame + set frame `src` on change (same as Chickens filter pattern). Frame fetches partial and replaces inline. | Small |
| 3 | **Egg Logging** — Section tabs (Logging / Recent / Stocks / Pre-Orders / History) | EG1 | Full page reload switching between egg sub-features | Turbo Frame lazy-load each tab panel (content already in separate routes). Wire tab clicks to set frame `src`. | Small |
| 4 | **Egg Production History** — Group-by tabs + pagination | EG11, EG12 | Full page reload on Day/Week/Month toggle | Turbo Frame navigation (like Chickens inventory). Frame already exists conceptually; wrap it. | Small |

### P1 — Good AJAX candidates, less frequent but still impactful

| Order | Module | Controls | Current | Recommended Pattern | Est. Effort |
|---|---|---|---|---|---|
| 5 | **Recent Logs** — All filters + pagination | EG9, EG10 | Full page GET form | Turbo Frame filter pattern (like Chickens). Table is already in a Turbo Frame (`#egg-logs-list`) — just make filter changes set `frame.src` instead of submitting the form. | Small |
| 6 | **Pre-Orders** — Filter form (status, size, dates) + pagination | PO1, PO2, PO8 | Full page GET form | Same Turbo Frame filter pattern. Table already lazy-loaded in a frame; wire filters to set `frame.src`. | Small |
| 7 | **Feed** — Batches/Consumption pagination | FD2, FD3 | Full page reload | Wrap each tab panel content in a Turbo Frame; let pagination links navigate within the frame. Same as Mortality logs pattern. | Small |
| 8 | **Feed** — Batch/Consumption CRUD | FD6, FD7 | Full page reload after mutation | Egg-logging-style: `fetch()` POST → JSON → update table row/add row without reload. | Medium |
| 9 | **Chickens** — Mortality form submit | CH11 | Full page reload after recording death | Egg-logging-style: `fetch()` POST → JSON → append row to mortality table + update counts. | Medium |
| 10 | **Chickens** — Bulk Move/Cull/Remove | CH12 | Full page reload after action | AJAX submit + refresh the inventory Turbo Frame via `frame.reload()`. | Medium |

### P2 — Lower priority, infrequent or low-impact

| Order | Module | Controls | Current | Recommended Pattern | Est. Effort |
|---|---|---|---|---|---|
| 11 | **Egg Stocks** — Edit stock form | ES6 | Full page reload | Same as feed CRUD: AJAX update row. | Small |
| 12 | **Egg Stocks** — Add Stock success | ES1 | `Turbo.visit()` full page replace | Could reload the stock table frame instead of full page. | Small |
| 13 | **Egg Logging** — Edit log form | EG6 | Full page reload after edit | Same as store: AJAX PUT → update row. | Small |
| 14 | **Notifications** — Mark all read | N3 | Full page reload | Could POST with fetch and reload frame. | Small |

### P3 — Leave as-is (infrequent, sensitive, or file download)

- Dashboard onboarding form (D4)
- Account/Settings forms (AC2-AC6) — password/PIN changes should be full page
- Notes (NO1, NO2) — low traffic page
- Hardware CRUD (H1-H3) — infrequent
- Cage forms (CA3, CA4, CA11) — mutations with confirmation needed
- Reports CSV export / Print (R7-R9) — needs full response
- Forecast download/import (F11, F12) — file operations
- Delete actions with confirmation (EG7, ES8, H3, PO7, CH16) — acceptable as full page
- All farm layout drag/drop + save (CA6-CA9) — already AJAX
- All client-side only controls — no change needed

---

## 5. Risk Assessment for "Stale on Switch" Bugs

| Pattern | Risk Level | Mitigation |
|---|---|---|
| **Turbo Frame filter switching** (set `src` + Turbo navigates) | Low | Turbo handles frame lifecycle; no manual DOM management. Used successfully in Chickens, Mortality, Hardware. |
| **JSON fetch + inline chart destroy/create** (`analyticsFetch` pattern) | **Medium** | Must ensure `LayRateChart.destroy()` is called before re-creating. Must update ALL associated DOM elements (titles, labels) — not just chart data. This is the pattern that had the scope-mismatch bug. |
| **HTML fetch + DOM replace** (FCR `fcrLoad()`, Environment polling) | Low | Replaces container entirely; no retained state. |
| **Polling with chart re-creation** (Environment 10s) | **Medium** | Must call `LayRateChart.destroy()` before each poll re-render (the code currently does this via `LayRateChart.create()` which calls `destroy()` internally). If `turbo:before-cache` fires mid-poll, it could tear down live charts. |
| **Turbo Frame with closed-over JS data** | **Medium** | This was the root cause of the stale-analytics bug. Avoid storing data in IIFE closures inside frames. Use shared window-level functions (`window.renderAnalyticsCharts`, `window.analyticsFetch`) instead. |

---

## 6. Recommended Implementation Order

```
Sprint 1 (P0 — High Value, Low Risk):
  1. Reports: convert all filters to AJAX (fetch JSON → update table + pills)
  2. Forecast: convert Cage/Breed dropdowns to Turbo Frame navigation
  3. Egg section tabs → Turbo Frame lazy load
  4. Egg Production History: group-by tabs → Turbo Frame navigation

Sprint 2 (P1 — Medium Value, Medium Effort):
  5. Recent Logs filters → Turbo Frame src navigation
  6. Pre-Orders filters → Turbo Frame src navigation
  7. Feed pagination → Turbo Frame per tab panel
  8. Feed CRUD → AJAX submit (egg-logging pattern)

Sprint 3 (P2 — Lower Value):
  9. Chickens mortality form → AJAX submit
  10. Chickens bulk actions → AJAX + frame reload
  11. Egg Stocks edit → AJAX row update
  12. Egg Logging edit → AJAX row update
```
