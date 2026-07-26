# Investigation Report: Egg Management Header Buttons & Recurring Chart Bugs (2026-07-26)

> **Status: investigation only. No fixes applied.** Per explicit instruction, this report exists so scope can be reviewed before any code changes.
> **Methodology:** two parallel code/git-archaeology research passes (full file:line reads, `git show`/`git log -S` diff inspection) plus direct live-browser reproduction (Playwright, headless Chrome) with DOM/canvas introspection, console/network monitoring, and screenshot evidence for every claim below. Where a finding is inference rather than directly observed, it's labeled as such.

---

## Issue 1: Egg Management header buttons ("Egg Weights" / "Stock Settings")

### Root cause: confirmed, live-reproduced. Not a cache issue, not duplicate code — a Turbo Frame architecture boundary.

**What's actually happening:** the "Egg Weights" and "Thresholds" buttons (see naming note below) were never meant to appear on all 5 tabs — they only exist on the Egg Stocks tab, by design. But the *disappearing/reappearing* behavior the team observed is real, and has a precise, reproducible mechanism:

The shared `<x-page-header>` (with its action buttons) lives **outside** `<turbo-frame id="egg-content">` in every one of the 5 tab views. The tab bar (`eggs/_tabs.blade.php`) switches tabs by setting `turbo-frame#egg-content`'s `src` attribute — a **frame-scoped** fetch that only swaps the frame's own content. The header sitting outside that frame is **never touched** by a tab click, in either direction:

- Click **into** Egg Stocks from another tab → body content correctly updates to show Stocks, but the header stays frozen from whatever tab was last **fully loaded** (subtitle text included, not just the buttons).
- Click **away** from Egg Stocks to another tab → the header does not clear either. Once "showing buttons" (from a full reload while on Stocks), it keeps showing them even after navigating to a tab that shouldn't have them.
- A genuine full-page reload (typed URL, bookmark, hard refresh, or `F5`) is the **only** thing that correctly repaints the header, because that's the only path that re-renders the whole page, header included.

**Why this looked cache-related:** clearing the compiled-view cache *also* requires a subsequent full page load to see any effect — so "clear the cache, it works again" and "the actual fix is that you did a full reload, not that you cleared anything" look identical from the outside. This is a coincidental correlation, not causation.

### Live evidence

Reproduced directly (screenshots captured):

| Step | Action | Result |
|---|---|---|
| 1 | Full page load of `/eggs/logging` | No buttons (correct — Logging has none) |
| 2 | **Click** "Egg Stocks" tab (frame-scoped nav) | URL and body correctly show Stocks — **but header still reads the Egg Logging subtitle, no buttons appear** |
| 3 | Full page **reload** while still on `/eggs/stocks` | Buttons now correctly appear |
| 4a | Click away to "Recent Logs" | Buttons **incorrectly persist** (they should not be there) |
| 4b | Click back to "Egg Stocks" | Buttons still showing (never left, so this proves nothing new, but confirms the header is simply inert to frame navigation in both directions) |

Screenshot from step 2 is the clearest single piece of evidence: tab bar shows "Egg Stocks" active, body shows the Stocks summary cards and "No stock batches yet" table — but the header subtitle still reads *"Log daily egg production per cage slot"* (Egg Logging's subtitle), and no header buttons are present.

Also directly tested and ruled out: `php artisan view:clear` alone, run with the app otherwise untouched, produces **zero change** in button visibility before vs. after (identical screenshots, identical DOM state across all 5 tabs). This rules out the compiled-view-cache hypothesis directly, not just by inference.

### On "duplicate/conflicting header code" — not found; ruled out

- All 5 tab views (`egg-logging.blade.php`, `eggs/recent-logs.blade.php`, `eggs/stocks.blade.php`, `eggs/pre-orders.blade.php`, `egg-production-history.blade.php`) call the exact same shared component, `<x-page-header>` (`resources/views/components/page-header.blade.php`) — one source, not several competing copies.
- All 5 include the same `eggs/_tabs.blade.php` partial identically (only the `activeTab` param differs).
- Only `eggs/stocks.blade.php` populates the header's `<x-slot:actions>` — the other 4 simply pass none. There's no evidence anything was "removed from one place but left in another."

### On the "Stock Settings" naming

That exact label does not exist anywhere in the current codebase. Git history (`git show a0801fd`) shows it's residual memory of an **older** UI: before commit `a0801fd`, the Egg Weight/Threshold config lived in a collapsible `<details><summary>Egg Weight & Stock Settings</summary>` accordion on the Stocks tab. That commit removed the accordion and replaced it with the current two header buttons, "Egg Weights" and "Thresholds" — both still Stocks-only. So the actual current button labels are **"Egg Weights"** and **"Thresholds"**, not "Stock Settings."

### Filter/action-row padding comparison (Stocks / Pre-Orders vs. Cages)

Confirmed, real inconsistency — three different conventions for "where does the primary create action go":

| Section | Placement | Gap | Alignment |
|---|---|---|---|
| Cages | Page header actions slot | `gap-2` | `items-center` |
| Egg Stocks ("Add Stock") | Free-standing block below summary cards, no filter row present | n/a (single button) | n/a |
| Pre-Orders ("Add Pre-Order") | Embedded inside the filter `<form>`'s trailing button group | `gap-4` | `items-end` (form), `items-center` (button group) |

`gap-4` (Pre-Orders) reads noticeably looser than `gap-2` (Cages) for a conceptually equivalent "row of controls." Not a bug, but a real, evidenced consistency gap worth deciding on.

### Recommendation for Issue 1

The fix is architectural, not a one-line patch: the header (or at minimum its actions slot / subtitle) needs to either (a) move inside `turbo-frame#egg-content` so it's part of what gets swapped on tab clicks, or (b) be driven by a small piece of shared JS that updates header content on `turbo:frame-load` the same way `eggs/stocks.blade.php`'s own modal-binding code already does (see the AJAX audit report from earlier today — the exact same frame-boundary pattern was found and fixed there for modal event bindings; this is a sibling case of the identical root mechanism, on the header instead of a modal). Given how directly this reproduces and how narrow the actual affected surface is (title/subtitle/actions of one shared component, only relevant on Egg Stocks), this looks like a contained fix — but it touches all 5 views' relationship to the frame boundary, so I'd want to scope it precisely before touching code, per your instruction not to fix yet.

---

## Issue 2: LayRateChart recurring bugs (Analytics / Forecast / Environment)

### Summary: most currently-reported symptoms did not reproduce live. The two real, confirmed bugs are already fixed (one earlier today, one in a prior commit). One code-level inconsistency remains as an unconfirmed risk, not a reproduced bug.

This section required being careful not to repeat this project's own documented pattern of declaring things fixed without live proof — so every symptom below is reported as either **confirmed-bug**, **confirmed-not-currently-reproducible**, or **inference-only**, explicitly.

### `LayRateChart` current implementation (`resources/views/layouts/app.blade.php:617-671`)

Read in full. Key facts:

- `create(id, config)` — calls `destroy(id)` first (tears down any existing instance for that id), then constructs a fresh `new Chart(canvas, config)`. Confirmed: **no instance-leak risk**, even when called repeatedly (e.g. every 10s from Environment's poll) — each call properly destroys the prior instance before creating the new one.
- `update(id, config)` — **does not fully apply the new config.** It swaps `inst.data` wholesale, and swaps only `inst.options.scales` and `inst.options.plugins` — everything else on `options` (and `config.type` entirely) is silently ignored. If ever called with a config of a different chart type or materially different options shape, the live chart would keep stale structure from its original creation. **This is a real bug in the helper** — but confirmed via full-codebase search that **zero call sites currently use `.update()`** (every chart on every page now calls `.create()`). So it's a landmine for a future contributor, not an active symptom today.

### The `.update()` → `.create()` fix from earlier today

Confirmed still in place in `environment/_live-data.blade.php` (currently an **uncommitted** working-tree change, not yet part of any commit) — not reverted by anything since.

### The guard-clause / `preventDefault` bug: confirmed found, confirmed already fixed, with a specific git timeline

This is the most concrete finding in the report. Using `git log -S"preventDefault"` (a history search for when this literal string was added/removed, not just a current-state grep):

- **Introduced** in commit `b3c326a` ("Fix analytics chart scope sync..."), which added tab-click handlers to `analytics.blade.php` with the guard **before** `preventDefault()`:
  ```js
  if (tab.style.borderBottomColor === 'rgb(0, 45, 94)') return;  // guard fires first
  e.preventDefault();                                             // never reached when guard returns
  ```
  When the guard's condition was true (clicking an already-active tab), `preventDefault()` was skipped, the browser followed the link as a real navigation, and the whole page reloaded — which incidentally "fixed" any stale chart state because everything reloaded from scratch. This is exactly the mechanism described in your brief.
- **Fixed** in commit `a0801fd`, ~11.5 hours later, which reordered both the period-tab and cage-tab handlers to call `preventDefault()` immediately after the frame-existence check, before any "already active" guard.
- **Notable:** `a0801fd`'s commit message is *"Fix egg tab header rendering and padding..."* — it does not mention Analytics, charts, or preventDefault at all. It fixed this bug as an incidental part of a differently-described change. This plausibly explains why the bug could have been "found and fixed once" without leaving an easily searchable trail — anyone grepping commit messages for chart-related keywords would miss this exact commit.
- **Live-confirmed current state:** I directly tested clicking an already-active tab on Analytics (period tabs), Environment ("Live Data" tab), and Egg Management ("Egg Stocks" tab clicked while already on Egg Stocks) — in all three, no navigation occurred (JS context survived, zero `sec-fetch-mode: navigate` requests). **The specific bug pattern does not currently exist anywhere in the codebase** (also confirmed by direct code search across all tab-click handlers in Analytics, Forecast, Environment, Egg tabs, and Chickens).

### Full commit timeline for this symptom family (chronological)

Worth including as-is, since it directly supports the "fixed multiple times" concern in your brief:

1. `7627b40` — first attempt at chart-destroy guards, pre-dates the shared `LayRateChart` helper (each page had its own ad-hoc logic).
2. `104768e` — broad UI/UX pass touching the same files, not chart-specific.
3. `ae7cdbb` — **introduces `LayRateChart` itself**, explicitly claiming to "fix clipping and stale-data-on-filter bugs" (attempt #1 at these exact symptoms).
4. `b3c326a` — fixes a different stale-data issue (per-date aggregation) but **introduces** the guard/preventDefault bug (attempt #2, regresses part of what #3 fixed).
5. `a0801fd` — fixes the guard/preventDefault bug as a side effect of an unrelated-sounding commit (attempt #3, and the one that appears to have actually landed correctly).
6. *(uncommitted, today)* — the `.update()`→`.create()` Environment fix, a distinct bug in the same symptom family (blank/stale charts), not yet committed.

Three real attempts at the same symptom class in under 24 hours, only the third (plus today's uncommitted fourth, in a different file) actually resolved cleanly — this is a legitimate, evidenced basis for the "recurring" framing in your brief, not just a perception.

### Per-symptom live reproduction results

| Reported symptom | Live test result |
|---|---|
| Charts blank on initial load (Analytics) | **Not currently reproducible as a bug.** Fresh load with the default "Week" period shows 0 data points — but switching to "Month" immediately renders correctly (183k+ non-transparent pixels, 6 data points, clean screenshot, no errors). This matches the restored farm database's data simply not covering the current default date window on this dev machine, not a rendering defect. |
| Charts blank on initial load (Forecast) | **Not a bug — confirmed correct empty-state handling.** The page shows `"No historical data to display."` via its own error-overlay mechanism, which is working exactly as designed. The `[ForecastChart] Chart.js not available, polling...` console warning that looked alarming at first resolved itself within 500ms in every one of 3 repeated test runs (`Chart.js became available` logged immediately after) — this is a working defensive-polling mechanism, not a failure. |
| Charts blank on initial load (Environment) | **Confirmed real bug, already found and fixed earlier today** (the `.update()`/detached-canvas issue — see the AJAX audit status report from this morning for full detail). Canvas correctly stays hidden with an empty-state message when there's no recent sensor data (this dev machine's DHT22 sensors haven't reported in 1–3 weeks), which is correct behavior, not the bug. |
| Stale titles/labels after cage switch | **Not reproduced.** Tested switching Analytics cage scope (All → CAGE-A → CAGE-B → rapid A→B→A), reading the specific KPI/chart-title DOM elements (not just scanning page text) after each switch — labels updated correctly every time, including after a deliberately rapid switch sequence designed to expose a race condition. |
| Visual jitter/flicker on tab switch | **Not reproduced under tested conditions.** Measured the HDEP chart canvas's actual rendered CSS width/height across a Month→CAGE-A→All switch sequence — identical dimensions (934×311) at every measurement, zero shift. A **real, separate code-level inconsistency was found** that could plausibly cause this under different conditions (see below) but did not manifest as measurable jitter in this reproduction. |
| Data points/lines clipping outside plot area | **Not visually observed** in any captured screenshot (Analytics Month view chart is clean — all points and gridlines stay within bounds). A global `Chart.defaults.layout.padding = 10px` applies to every chart. See below for a related but unconfirmed risk. |

### Unconfirmed risk (inference, not reproduced): `maintainAspectRatio` inconsistency

Real, confirmed **code-level** inconsistency: Forecast's chart explicitly sets `maintainAspectRatio: false` with a matching fixed-height container (`h-64` / `16rem`, consistent between the Tailwind class and inline style). Analytics's and Environment's charts leave `maintainAspectRatio` at Chart.js's default (`true`) with **no fixed-height container** — meaning their rendered height is computed from container width ÷ 2 at chart-construction time rather than the canvas's own `height` attribute.

This is a plausible mechanism for jitter/clipping under the right conditions (container reflow during font/icon loading, window resize, slower connections where layout timing differs from this test's headless/fixed-viewport environment) — but I want to be precise: **I tested for this directly and did not observe it** in a stable 1280×900 headless Chrome session with no resize. I'm not confident enough in either direction to call this "the cause" of the reported jitter/clipping — it's a real inconsistency worth aligning (Analytics/Environment could adopt the same `maintainAspectRatio: false` + fixed-height pattern Forecast already uses successfully) but I don't have live proof it's actually responsible for the reported symptom, and I don't want to repeat this project's pattern of claiming a fix for something not confirmed as the actual cause.

### Recommendation for Issue 2

Given the live-reproduction results, I'd frame this as: **the two real, root-caused bugs in this symptom family are already handled** (guard/preventDefault — fixed in `a0801fd`; Environment detached-canvas — fixed today, uncommitted). What's left is:
1. Commit today's uncommitted Environment fix (it's real, tested, and currently just sitting in the working tree).
2. Decide whether to proactively align Analytics/Environment's chart sizing options with Forecast's already-correct `maintainAspectRatio: false` pattern, as defensive hardening against a plausible-but-unconfirmed jitter/clipping mechanism — not because it's confirmed to be causing anything right now.
3. Consider removing the dead `.update()` method (or fixing its partial-merge bug) from `LayRateChart`, purely so it doesn't become a landmine if someone reaches for it later thinking it's a safe "smoother" alternative to `.create()`.

None of the historically-reported "blank/stale/jittery/clipping" symptoms reproduced live in their originally-described form on the current codebase, beyond what's already been fixed. I'd want your input on whether that matches what you're currently seeing in practice, since it's possible the reports predate `a0801fd`/today's fix and are simply stale, or there's a reproduction condition (specific browser, real network latency, concurrent multi-tab usage, non-headless rendering quirks) my test setup isn't capturing.

---

## Files referenced (no changes made to any of these)

- `resources/views/components/page-header.blade.php`
- `resources/views/eggs/_tabs.blade.php`
- `resources/views/eggs/stocks.blade.php`, `pre-orders.blade.php`, `recent-logs.blade.php`
- `resources/views/egg-logging.blade.php`, `egg-production-history.blade.php`
- `resources/views/cages/index.blade.php`
- `resources/views/layouts/app.blade.php` (`LayRateChart`)
- `resources/views/analytics.blade.php`, `analytics/_charts.blade.php`
- `resources/views/forecast/_workspace.blade.php`, `forecast/_calendar.blade.php`
- `resources/views/environment.blade.php`, `environment/_live-data.blade.php`
- `app/Http/Controllers/AnalyticsController.php`
