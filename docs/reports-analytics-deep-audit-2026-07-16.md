# Reports & Analytics — Deep Functional/Technical/UX Audit

**Date:** 2026-07-16
**Method:** Static code review + live browser reproduction (Playwright, logged in as admin) + raw DB verification queries. Not a re-hash of `project-status.md` items 77-84 — this goes underneath them.

---

## 1. Analytics — scope and data sources

`AnalyticsController` (93 lines, [app/Http/Controllers/AnalyticsController.php](../app/Http/Controllers/AnalyticsController.php)) has two actions, `index()` and `charts()` (the turbo-frame partial), which are near-duplicates — both build `$cage`, `$logs`, `$feedLogs`, `$avgHdep`, `$bestDay`, `$worstDay` from scratch, running the same 3 queries twice on first load (once for `index`, once for the frame's own `charts` fetch). Not a bug, just duplicated logic that could be one private method.

| Chart | Type | Query | Filters |
|---|---|---|---|
| HDEP Trend | Line | `$cage->productionLogs()->where('log_date','>=',now()->subDays($days))` | cage (tab), period (tab: week=7d/month=30d/3months=90d) |
| Eggs Collected/Day | Bar | same `$logs`, `egg_count` | same |
| Feed vs HDEP | Scatter | `$logs` joined client-side in JS against `FeedConsumptionLog::where('cage_id',...)` | same |

Filters are **cage** and **period** only — both implemented as full-page links (`<a href="{{ route('analytics', [...]) }}">`), not a `<select>` dropdown or AJAX-only control. One cage at a time; no cross-cage comparison.

**Confirmed accurate (still true):** no breed, mortality, or temperature query anywhere in the file — grep for all three terms returns nothing. Matches the audit and the documented scope decision in `project-status.md` line 345 ("Analytics stays focused on HDEP/eggs/feed-vs-HDEP by decision, not oversight").

---

## 2. The "charts not loading" bug (item 79) — definitive root cause found

**This is not the previously-hypothesized Chart.js/Turbo `defer` timing race.** It is a 100%-reproducible JavaScript bug, unrelated to load timing, confirmed on three independent live loads (fresh navigation, reload, and a `period=3months` navigation) with an identical stack trace every time:

```
TypeError: window.hdepChart.destroy is not a function
    at initAnalyticsCharts (<anonymous>:22:52)
    ...
    at re.activateScriptElements (turbo.js:3:24299)
```

**Root cause:** [resources/views/analytics/_charts.blade.php:74](../resources/views/analytics/_charts.blade.php#L74) does:
```js
if (window.hdepChart) window.hdepChart.destroy();
window.hdepChart = new Chart(document.getElementById('hdepChart'), {...});
```
This pattern (cache the last Chart.js instance on `window`, destroy it before re-creating, to avoid Chart.js's "Canvas is already in use" error on re-render) is a reasonable idea — **but it never works**, because of a browser quirk: any element with an `id` attribute is automatically exposed as a global variable (`window[id]`) the instant it's parsed into the DOM — before any script runs. Since the canvas is `<canvas id="hdepChart">`, `window.hdepChart` is **already non-null** on the very first render — but it's the raw `<canvas>` DOM node, not a Chart instance. Canvas elements have no `.destroy()` method, so the guard throws immediately, and the exception aborts the whole IIFE before it reaches `new Chart(...)` for any of the three charts.

Verified via live evaluation:
```json
{ "hasChart": "function", "hdepChart": "HTMLCanvasElement", "eggsChart": "HTMLCanvasElement", "feedHdepChart": "HTMLCanvasElement" }
```
`window.Chart` (the library) loads fine — `hasChart: "function"` proves Chart.js itself is available. The bug is entirely in the naming collision, not in script load order. **This also means it fails identically on every single page load, forever — not intermittently, and not only on Turbo-frame re-renders.** It happens on a hard fresh browser navigation too (tested via direct `page.goto`), because the canvas is inserted via the turbo-frame's async fetch either way — there is no code path where `window.hdepChart` is ever assigned a real Chart instance.

**Net effect: all three charts on the Analytics page have never rendered for any user, on any load, ever**, since this code was written. The metadata summary row (avg HDEP, best/worst day, breed, flock age) still renders correctly, because that's plain Blade output with no dependency on the broken script.

### Fix (straightforward, low-risk)
Rename the three cache variables to avoid colliding with the canvas element IDs — e.g. `window.__hdepChartInstance` instead of `window.hdepChart` (or a single namespaced object `window.__analyticsCharts = {}`). Three-line change in `_charts.blade.php`, no controller/query changes needed. **Go: this can be fixed directly, no further live debugging required** — the root cause is fully understood and the fix is mechanical.

---

## 3. Chart type/library appropriateness

- **Line for HDEP trend, bar for daily eggs, scatter for feed-vs-HDEP** — all three are the correct chart type for their data shape. No changes needed here.
- **Canvas-reuse handling**: the *intent* (destroy-before-recreate) is correct engineering for a Turbo/SPA-style app reusing the same canvas ID across re-renders — the bug is purely the naming collision above, not the underlying strategy. Once the variable name is fixed, this guard will correctly prevent Chart.js's "Canvas is already in use" duplicate-registration error on subsequent cage/period changes.
- No other Chart.js console warnings observed in any of the three reproductions (deprecation notices, plugin warnings) — the library itself is used correctly (v4-style options, no legacy API calls).

---

## 4. Data accuracy and performance

- **Accuracy verified**: Analytics reported `AVG HDEP 85.6%` for CAGE-A over the 90-day window. Raw DB query for the same cage/window: `AVG(hdep)=85.6, n=210 rows`. **Exact match.**
- **No N+1 queries** in either action — `Cage::with(['hens' => ...])` eager-loads correctly, `productionLogs()`/`FeedConsumptionLog::where(...)` are each single queries. No loop-triggered lazy loads.
- **Indexes confirmed present** for every filter column actually used: `production_logs (cage_slot_id, log_date)` composite, `feed_consumption_logs (cage_id, log_date)` composite. No missing-index risk at current or realistic near-term data volume.
- Default period ("week") currently shows **0%** for CAGE-A because there's genuinely no `production_logs` data in the last 7 days from today (2026-07-16) — the latest log for CAGE-A is 2026-07-02. This is a stale-data observation, not a bug in the query.

---

## 5. Analytics UX gaps

- **Cage/period changes trigger a full Turbo Drive page visit**, not a scoped frame-only update. The links point at `route('analytics', [...])` (the full `index()` action) rather than targeting the `analytics-charts` frame directly. Turbo still avoids a hard reload (SPA-style body swap) and the frame's skeleton re-shows during the fetch, so there is a loading state — it's just heavier than necessary (whole page re-renders, including sidebar/header, on every filter click).
- The turbo-frame's `loading="lazy"` attribute is close to meaningless here: the frame sits above the fold on every load, so it fires almost immediately regardless. This just adds a guaranteed skeleton flash on first paint with no actual lazy-loading benefit. Minor.
- **No export of chart data or chart images** — no download/CSV/PNG option on the Analytics page at all (Reports has CSV export; Analytics has none).
- **No comparison mode** — confirmed strictly one-cage-at-a-time via cage tabs; no side-by-side view.

---

## 6. Reports — scope and report types

`ReportController` (208 lines, confirmed) supports exactly **4 report types**: `production` (default), `feed`, `environment`, `mortality`, selected via `type` param, with `from`/`to`/`cage`/`reason` (mortality-only) filters. Both output formats work end-to-end and were verified live:
- **CSV export** (`exportCsv()`) — streams correctly, generates from the same `buildReport()` used for the on-screen table.
- **Printable HTML with letterhead** — `@media print` CSS hides sidebar/header/filters, letterhead (logo, "LayRate Poultry Farm", report title, date range), metadata strip, summary pills, data table, and a two-signature block all render in the DOM (confirmed via direct read of `reports.blade.php`).

**Consistency check (production ↔ Mortality/other sections):** MortalityController computes its own "today total" via `whereDate('log_date', today())` — calendar-day boundary, same style as `ReportController`'s `whereBetween($from, $to)`. No rolling-vs-calendar mismatch here (unlike the FCR issue from earlier this session) — both use plain calendar dates.

---

## 7. Reports — gaps identified

| Gap | Status |
|---|---|
| Egg Production/Stock report | **Missing.** `buildReport()`'s `match($type)` only covers production/feed/environment/mortality — no `egg_stock` case despite the substantial egg-stock system built this session (`EggStockBatch`, `PreOrder`). |
| Feed Consumption report | **Exists** (`type=feed`) — confirmed working. |
| Financial/Cost report | **Not possible with current schema.** `FeedBatch` has `unit_cost`/`total_cost` (cost side exists), but neither `EggStockBatch` nor `PreOrder` has any price/cost field — there is no revenue data anywhere in the database to pair against feed cost. This is a data-model gap, not just a missing report. |
| Scheduled/emailed reports | **Not implemented**, but the infrastructure pattern already exists in this codebase — `routes/console.php` has a working daily scheduled job (`forecast:sync-input-records` at 02:00). Adding a scheduled report would follow the same pattern; no new infrastructure needed. |
| Preview table before export (item 84) | Confirmed still the only *previously* tracked gap — but see below, there are more. |
| Save/bookmark a report configuration | **Missing** — every report run requires re-selecting type/dates/cage/reason from scratch; no saved presets. |

---

## 8. Report generation reliability

- **Empty-data handling**: tested live with a date range guaranteed to have zero rows (2020-01-01 to 2020-01-07). Result: clean "No data found for the selected filters." message, no error, no broken table. **Handled gracefully.**
- **Performance**: tested live with a 2-year range across all cages (the entire dataset, 630 production_log rows) — 424ms page load. No timeout risk at current data volume; the underlying queries are properly indexed (see §4) so this should scale reasonably as data grows.
- **Printable HTML correctness**: letterhead, filters-reflected-in-output (cage, date range, generated-by/date, record count), and signature block all present in markup. **Pagination is not explicitly handled** — the print CSS only sets `page-break-inside: avoid` on the signature block and `tfoot`; a long table (e.g., a full year, all cages) has no `page-break-inside: avoid` on `<tr>`, so table rows could split awkwardly across printed pages. Minor, cosmetic.

### Bug found (not previously tracked): stale/incorrect breed in Production report
`productionReport()` ([ReportController.php:114](../app/Http/Controllers/ReportController.php#L114)) does:
```php
'breed' => $log->cageSlot->hens->first()?->breed ?? '—',
```
The eager load (`with(['cageSlot.cage', 'cageSlot.hens'])`) does **not** filter `hens` by `is_active`, and `.first()` has no defined order. In the current dataset every hen in a shared slot happens to be the same breed and active, so this isn't visibly wrong today — but it's a latent bug: if a slot has ever had a hen replaced (mortality, transfer), the report could non-deterministically attribute the wrong breed to a historical egg-production row. **Analytics gets this right** — `AnalyticsController` eager-loads `hens => fn($q) => $q->where('is_active', 1)`, and `CageSlot` already has a canonical `primaryHen()` method (`hens()->where('is_active', 1)->first()`) that Reports should be using instead of the raw unfiltered collection.

---

## 9. Cross-section data consistency

Tested live: Reports (`type=production, cage=CAGE-A, from=2026-06-19, to=2026-07-02`) → **AVG HDEP 85.6%, Records: 210**. Analytics (`cage=CAGE-A, period=3months`, which covers the same underlying rows) → **AVG HDEP 85.6%**. Raw DB query → **85.6%, 210 rows**. **All three agree exactly.** No FCR-style rolling-window-vs-calendar-boundary discrepancy between Analytics and Reports for HDEP/production data — Analytics uses a rolling `now()->subDays()` window (by design, for a live trend view) while Reports uses explicit calendar boundaries (by design, for a fixed audit-style report); when pointed at the same actual date span, they agree.

The one identified cross-section inconsistency is the breed-lookup bug in §8 (Reports doesn't filter active hens; Analytics does) — a code inconsistency, not a data-consistency discrepancy in the numbers themselves.

---

## 10. Test coverage

**Confirmed: zero test coverage for both controllers.** No test file references `AnalyticsController`, the `analytics`/`analytics.charts` routes, `ReportController`, or the `reports`/`reports.csv` routes. `EggReportingAndHistoryTest.php` (the only file matching a "Report" grep) exclusively tests `eggs.logging.logs`, `dashboard.stats`, and `egg-production-history` — unrelated routes. Full current test file list: 17 Feature tests + 2 Unit tests, none touching these two controllers. This is more precise than the earlier "~10%" estimate — the actual number is 0%.

---

## Prioritized Findings

### 🔴 Broken (fix first)
1. **Analytics charts never render, for anyone, ever** — `window.hdepChart`/`eggsChart`/`feedHdepChart` collide with auto-exposed canvas-element globals, crashing `initAnalyticsCharts()` before any chart is created. **Root cause confirmed, fix is a 3-variable rename in `_charts.blade.php`. Ready to fix directly — no further live debugging needed.**
2. **Zero test coverage** on both controllers — any fix to #1 (or anything else here) ships unverified without new tests.

### 🟠 Missing expected feature / latent bug
3. **Breed misattribution risk in Production report** — unfiltered `hens->first()` instead of the existing `primaryHen()` pattern Analytics already uses correctly. Not currently visible in data, but a real latent bug.
4. **No Egg Stock report type**, despite a full egg-stock system existing in the app.
5. **No Financial/Cost report** — blocked on a data-model gap (no price field on `EggStockBatch`/`PreOrder`), not just a missing controller method.
6. Analytics cage/period filters do a full-page Turbo visit instead of a scoped frame update — works, but heavier than necessary.

### 🟡 Nice-to-have
7. No chart data/image export on Analytics.
8. No cage-comparison view on Analytics.
9. No saved/bookmarked report configurations.
10. No scheduled/emailed reports (pattern for this already exists in `routes/console.php`, so cheap to add later).
11. Report table has no explicit print pagination handling for very long tables.
12. `loading="lazy"` on the analytics turbo-frame is a no-op given its above-the-fold position.
13. Duplicated query logic between `AnalyticsController::index()` and `::charts()`.
14. `buildSummary()` runs 4 sequential queries per report type where one aggregate query could do — not a performance risk at current scale, just not maximally efficient.

---

## Go/No-Go on item 79

**Go — fix directly.** The root cause is fully reproduced and understood (a JS global-scope naming collision, not a timing race), confirmed identically across three independent live tests. No further live debugging is required before implementing the fix.

## Requires a product decision from you

- Whether to add an Egg Stock report type (straightforward once decided).
- Whether to add price/cost fields to `EggStockBatch`/`PreOrder` to enable a Financial report (schema change — bigger decision).
- Whether scheduled/emailed reports are in scope for this project.
- Whether chart export or cage-comparison mode on Analytics is worth building given time constraints.
- Whether to refactor the Analytics cage/period links to target the turbo-frame directly (UX polish, not a functional requirement).

## Straightforward code fixes (no decision needed, just implementation time)
- The chart-rendering bug itself (#1).
- The breed-lookup fix in Reports (#3).
- Adding a first round of tests for both controllers (#2).
