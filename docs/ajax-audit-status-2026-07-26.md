# AJAX Conversion Effort — Status Report (2026-07-26)

> **UPDATE (same day, after this report):** All three regressions below were fixed and live-verified. See "Fixes Applied & Verified" at the end of this document for the final outcome, including a real methodological trap hit and corrected during the fix for Regression 2 — worth reading if touching this code again.

> **Purpose:** Re-verify `docs/ajax-audit-report.md` (dated 2026-07-25) against the current codebase before any further conversion work, per explicit instruction not to trust prior "verified" claims blindly.
> **Methodology:** Three parallel code-research passes (full file:line re-reads + `git show` cross-referencing against the 9 most recent commits) covering all ~118 inventoried controls, plus direct live-browser reproduction (Playwright, headless Chrome) for the 7 named Sprint 1/2 items and explicit regression checks. Live tests classified each interaction via the `sec-fetch-mode` request header (`navigate` = real page reload vs. `cors`/`same-origin` = fetch/AJAX/Turbo) plus a JS-context-survival marker (`window.__marker`, wiped by any real navigation). Two mutating endpoints (`/mortality`, `/feed/batch`) were intercepted via `page.route()` rather than allowed to reach the real database, consistent with a standing rule from earlier in this engagement after an incident where automated tests mutated real farm data.

---

## Executive Summary

**Sprint 1 (P0) and Sprint 2 (P1): essentially complete, confirmed both by code and live reproduction.** Every item in the original P0/P1 recommendation lists — Reports filters, Forecast Cage/Breed dropdowns, Egg section tabs, Egg Production History, Recent Logs, Pre-Orders, Feed pagination, Feed CRUD — is now AJAX/Turbo-Frame-driven, not full-page-reload. This significantly *exceeds* what the background brief described as "completed" — the brief said only Sprint 1 items were done and Sprint 2 was next; in reality nearly all of Sprint 2 has *also* landed already (via the teammate's 9 recent commits), which the original audit doc itself doesn't reflect (it was written the same day as, or just before, those commits).

**Sprint 3 item 9 (Chickens Mortality): confirmed complete and working**, exactly as the brief stated — live-verified via intercepted POST, genuine `fetch()`, no page reload.

**Sprint 3 item 10 (Bulk Move/Cull/Remove): also already converted to AJAX** (the brief's premise that this was "intentionally deprioritized and left as-is" is stale) — but with a real, live-reproducible gap (see Regression 3 below).

**Three concrete regressions/bugs found, one live-confirmed by direct reproduction, none of which existed at Sprint 1/2 completion — all introduced by the same very-recent commit wave:**

1. **🔴 Environment trend charts go blank after ~10 seconds, permanently, on every visit to `/environment`.** Live-confirmed: canvas has zero non-transparent pixels after one poll cycle. Caused by a `.create()`→`.update()` change on a canvas that gets destroyed/recreated by the page's manual polling mechanism every 10s, orphaning the chart instance.
2. **🔴 Egg Stocks "Add Stock" (and, by the same mechanism, Egg Pre-Orders "Add Pre-Order") silently stops working after one tab round-trip.** Live-confirmed: after Egg Logging → Egg Stocks → Egg Logging → Egg Stocks, clicking "Add Stock" opens the modal, but submitting it does **nothing at all** — no POST fires, no error, no native-form fallback (the form has no `method`/`action` to fall back to). Caused by modal wiring being bound only on `turbo:load`, gated by a one-time flag, but tab switches now fire `turbo:frame-load` instead.
3. **🟡 Chickens Cull (one of the three CH12 bulk actions) updates the wrong frame** — reloads the culling-records table but not the inventory list, so a culled hen stays visible as "active" in Inventory until manual refresh. Lower severity than 1–2 (cosmetic staleness, not a dead button), code-confirmed only.

**Recommendation: pause new Sprint 3 conversion work and fix these three regressions first** (Est. effort: small–medium, all are narrow, well-understood fixes — see Regression Detail section). Then reassess whether Sprint 3 items 11–12 (Egg Stocks/Egg Logging row-edit AJAX) are still worth doing — **recommendation is no**, for the same reasoning the project already reached for infrequent/consequential actions: low frequency, and this exact audit cycle just demonstrated that every new AJAX surface added has produced a real regression, raising the effective cost above the original "Small effort" estimate.

---

## Live Verification Results (the 7 named Sprint 1/2 items + explicit regression checks)

| # | Item | Live Result | Evidence |
|---|---|---|---|
| 1 | Reports filters (AJAX) | ✅ **AJAX-CONFIRMED** | Marker survived, 0 navigate-mode requests, 1 request to `/reports/data` |
| 2 | Forecast Cage/Breed dropdowns (Turbo Frame) | ⚠️ **Code-confirmed fixed; live test inconclusive** | Research agent traced `onchange` now directly sets `turbo-frame#forecast-workspace` `src` (`forecast/_workspace.blade.php:43,53`) — no more `this.form.submit()`. My live harness couldn't cleanly isolate the scope-switch UI (selector ambiguity across "Per Cage" link vs. the resulting select); recommend a 2-minute manual click-through to fully close out |
| 3 | Egg section tabs (Turbo Frame lazy-load) | ✅ **TURBO-FRAME-CONFIRMED** | Marker survived, 0 navigate requests, URL updated to `/eggs/stocks` via `history.replaceState` |
| 4 | Egg Production History group-by tabs + pagination | ✅ **AJAX/TURBO-CONFIRMED** | Marker survived, 0 navigate requests |
| 5 | Recent Logs filters | ✅ **AJAX/TURBO-CONFIRMED** | Marker survived, 0 navigate requests |
| 6 | Pre-Orders filters | ✅ **AJAX/TURBO-CONFIRMED** | Marker survived, 0 navigate requests |
| 7 | Feed pagination + CRUD (AJAX) | ⚠️ **Code-confirmed; live test inconclusive** | Research agent traced pagination now inside nested `tab-batches-frame`/`tab-consumption-frame` (Turbo Frame), and CRUD forms carry `data-feed-ajax` intercepted by `feedAjaxSubmit()` (`feed.blade.php:337-389`). My live harness couldn't reliably isolate the page-level trigger button from an identically-labeled button inside the (initially hidden) modal |
| 8 | Chickens Mortality (AJAX) | ✅ **AJAX-CONFIRMED** | Intercepted POST `/mortality` observed, marker survived, 0 navigate requests — Sprint 3 item 9 fully verified |
| — | **Regression check: Analytics tabs** | ✅ **NO REGRESSION** | Cage-scope + period-tab switches still atomic AJAX, marker survived, 0 navigate requests |
| — | **Regression check: Egg Management buttons** | 🔴 **REGRESSION CONFIRMED** (deeper than initially checked) | "Add Stock" button opens the modal fine on a fresh page load — but see Regression 2 below: after one tab round-trip, its submit silently does nothing |
| — | **Additional check: Environment chart poll cycle** (prompted by a code-level finding, not in the original 7) | 🔴 **REGRESSION CONFIRMED** | Canvas pixel-inspected before/after a 10s poll — zero non-transparent pixels after, i.e. genuinely blank |

---

## Full Inventory Status (all ~118 controls, by module)

Legend: **Impl.** = current implementation state. **Live** = ✅ directly reproduced live in this pass, 📄 confirmed via code-read only (not one of the 7 named items, or live test inconclusive), — = not applicable / not tested (low-priority, unchanged from audit, no reason to suspect drift).

### Dashboard
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| D1 Cage filter tabs | — (already AJAX) | TF, unchanged | 📄 | No | None needed |
| D2 Metric card nav | N/A | TF nav, unchanged | 📄 | No | None needed |
| D3 Feed/Mortality row nav | N/A | TF nav, unchanged | 📄 | No | None needed |
| D4 Onboarding form | Low | FR, unchanged | — | No | Leave as-is (P3) |
| D5 Live clock | N/A | Client-only, unchanged | — | No | None needed |

### Analytics
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| A1 Cage scope tabs | — (already AJAX) | AJ `analyticsFetch()`, rewritten twice since audit but same mechanism, atomic KPI+chart+URL update confirmed | ✅ | **No** | None needed |
| A2 Period tabs | — (already AJAX) | Same pattern as A1 | ✅ | No | None needed |
| A3 Turbo Frame lazy-load | — | Unchanged | 📄 | No | None needed |

### Forecast
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| F1 Scope links | — (already partial) | TF, unchanged (now also `data-turbo-action="advance"`) | 📄 | No | None needed |
| F2 Cage dropdown | **High** (Sprint-1 #2) | **Fixed** — `onchange` sets `turbo-frame#forecast-workspace` `src` directly | ⚠️ code-confirmed | No — resolved | Recommend quick manual click-through to fully close out |
| F3 Breed dropdown | **High** (Sprint-1 #2) | **Fixed** — same mechanism as F2 | ⚠️ code-confirmed | No — resolved | Same as F2 |
| F4 Horizon radios | Low | Unchanged | — | No | None needed |
| F5 Generate Forecast button | — (audit claimed "already partial via Turbo Stream") | **Audit was inaccurate at time of writing** — form has `data-turbo="false"`, JS does `e.preventDefault()` + native `form.submit()`, bypassing Turbo; server falls through to a plain redirect. This is a **full page reload dressed up with a fake progress overlay**, not Turbo Stream. Pre-existing, not a new regression (untouched by recent commits) | 📄 | N/A (pre-existing inaccuracy) | Low priority to fix given it "works," but the audit doc should be corrected; genuine AJAX conversion here would remove an unnecessary full reload |
| F6-F8 Month/Year/Prev/Next | — | TF, unchanged | 📄 | No | None needed |
| F9 Clear Forecast | — | TF→Turbo Stream, unchanged, correctly implemented | 📄 | No | None needed |
| F10 Calendar day click | — | Modal→Turbo Stream, unchanged | 📄 | No | None needed |
| F11 Download input sheet | Low | FR file download, unchanged | — | No | Leave as-is (P3, file op) |
| F12 Import upload | — (already AJAX) | Unchanged | — | No | None needed |

### Feed & Nutrition
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| FD1 Tab switcher | — | Client-only, unchanged | 📄 | No | None needed |
| FD2 Batches pagination | **Medium** (Sprint-2 #7) | **Fixed** — nested `tab-batches-frame` Turbo Frame | ⚠️ code-confirmed | No — resolved | None needed |
| FD3 Consumption pagination | **Medium** (Sprint-2 #7) | **Fixed** — nested `tab-consumption-frame` | ⚠️ code-confirmed | No — resolved | None needed |
| FD4 FCR cage selector | — (already AJAX) | Unchanged | 📄 | No | None needed |
| FD5 FCR group-by | — (already AJAX) | Unchanged | 📄 | No | None needed |
| FD6 Batch CRUD | **Medium** (Sprint-2 #8) | **Fixed** — `data-feed-ajax` + `feedAjaxSubmit()`, JSON POST | ⚠️ code-confirmed | No — resolved | None needed |
| FD7 Consumption CRUD | **Medium** (Sprint-2 #8) | **Fixed** — same mechanism | ⚠️ code-confirmed | No — resolved | None needed |

### Egg Logging
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| EG1 Section tabs | **Medium** (Sprint-1 #3) | **Fixed** — `data-turbo-frame="egg-content"` + JS src-swap | ✅ | No — resolved | None needed |
| EG2 Cage selector | — | Client-only, unchanged | — | No | None needed |
| EG3 Slot card click | — | Client-only, unchanged | — | No | None needed |
| EG4 Log Entry submit | — (already AJAX) | Unchanged | — | No | None needed |
| EG5 Sensor override | — (already AJAX) | Unchanged | — | No | None needed |
| EG6 Edit log | Medium | Unchanged — explicit `data-turbo="false"` | 📄 | No | Still valid Sprint-3-style candidate if pursued, but see overall recommendation below |
| EG7 Delete log | Low | Now frame-scoped (inside `#egg-logs-list`, no `target=_top`) | 📄 | No — improved | None needed |
| EG8 Logs pagination | Medium | **Fixed** — `<x-paginator>` inside `turbo-frame#egg-logs-list` | 📄 | No — resolved | None needed |
| EG9 Recent Logs filters | **High** (Sprint-2 #5) | **Fixed** — `recentLogsFilter()` sets frame `src` | ✅ | No — resolved | None needed |
| EG10 Recent Logs Reset | **High** (Sprint-2 #5) | Frame-scoped (inside `#egg-content`) | ✅ (via EG9 test) | No — resolved | None needed |
| EG11 History group-by tabs | Medium (Sprint-1 #4) | **Fixed** — TF inside `#egg-content` | ✅ | No — resolved | None needed |
| EG12 History pagination | Medium (Sprint-1 #4) | **Fixed** — `<x-paginator>` inside `#egg-content` | ✅ | No — resolved | None needed |

### Egg Stocks
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| ES1 Add Stock submit | — (already AJAX) | **Improved** — now patches `#summaryCards` directly AND refreshes the live-data frame (matches "Add AJAX refresh after stock add" commit) | 🔴 **but see Regression 2** | **Yes, after tab round-trip** | **Fix now** — see Regression Detail |
| ES2 Cage dropdown → pool data | — (already AJAX) | Same handler-loss issue as ES1 | 🔴 (via same test) | **Yes, after tab round-trip** | Fixed by the same patch as ES1 |
| ES3 Egg weight config | Low | Unchanged, now nested in `#egg-content` | 📄 | No | None needed |
| ES4 Thresholds config | Low | Same as ES3 | 📄 | No | None needed |
| ES5 Stock table lazy load | — | Unchanged, carries `target="_top"` (pre-existing, not new) | 📄 | See ES7 note | — |
| ES6 Edit stock | Medium | Ambiguous — lives inside outer frame but behavior needs live check | 📄 | Unclear, not confirmed | Verify alongside the ES1/ES2 fix |
| ES7 Stock table pagination | — (audit claimed "already partial") | **Audit claim looks wrong** — the frame carries `target="_top"`, meaning pagination clicks likely force a full top-level navigation, not partial. Pre-existing since before this audit cycle, not a new regression | 📄 | N/A (pre-existing inaccuracy) | Worth a quick live check; correct the audit doc either way |
| ES8 Delete stock | Low | Same `target="_top"` caveat as ES7 | 📄 | N/A | Same as ES7 |

### Environment
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| EN1 Tab switcher | — | Client-only, unchanged | 📄 | No | None needed |
| EN2 Live Data polling (10s) | — (already polling) | **🔴 BROKEN** — `.create()` calls changed to `.update()`, which binds to a detached canvas after the poll's `innerHTML=` swap | ✅ **live-confirmed blank canvas** | **Yes — severe** | **Fix now** — see Regression Detail |
| EN3 Log History lazy load | — | Unchanged | 📄 | No | None needed |
| EN4 Threshold config form | Low | **Improved** — moved out of the frame into a modal, converted FR→AJ | 📄 | No — improved | None needed |

### Hardware
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| H1-H3 Add/Edit/Delete Device | Low | Unchanged | — | No | Leave as-is (P3) |
| H4 Device type select | — | Client-only, unchanged | — | No | None needed |
| H5 Live data lazy load | — | Unchanged | 📄 | No | None needed |
| H6 Pagination | — | Unchanged, TF | 📄 | No | None needed |

### Chickens / Inventory
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| CH1-CH10 (tabs, filters, search, sort, lazy-loads) | — (already partial/client) | All unchanged | 📄 | No | None needed |
| CH11 Mortality form | **Medium** (Sprint-3 #9) | **Confirmed complete** — genuine `fetch()`, updates frame + counters | ✅ | No | **Done, matches brief** |
| CH12 Bulk Move | Medium (Sprint-3 #10, "deprioritized") | **Already converted to AJAX** (stale premise) — correctly reloads inventory frame | 📄 | No | None needed |
| CH12 Bulk Cull | Medium (Sprint-3 #10, "deprioritized") | **Already converted to AJAX**, but **only reloads culling-records, not inventory** | 📄 | **Yes — partial** | **Fix**: add inventory-frame reload to `ajaxCull()`, matching Move/Remove |
| CH12 Bulk Remove | Medium (Sprint-3 #10, "deprioritized") | **Already converted to AJAX**, correctly reloads inventory frame | 📄 | No | None needed |
| CH13-CH20 (expand/collapse, delete, pagination) | — | All unchanged | 📄 | No | None needed |

### Cage Management
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| CA1-CA6, CA8-CA11 | — (mostly already AJAX/client) | Unchanged | 📄 | No | None needed |
| CA7 Save Layout | — (already AJAX) | **Changed detail** — no longer does `Turbo.visit()`; now pure optimistic client-state merge + toast | 📄 | No (smoother, low-risk awareness note re: concurrent-edit divergence) | None needed |

### Reports
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| R1-R6, R10 (all filters + pagination) | **High** (Sprint-1 #1) | **Fixed** — `reportFetch()` → `GET /reports/data` → JSON → replaces `#report-preview-container`, which includes the summary pills, so no stale-summary bug | ✅ | No — resolved | None needed |
| R7-R9 (CSV, print, printable view) | N/A | Unchanged, correctly full-response | — | No | None needed |

### Notifications
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| N1-N4 | — (mostly already TF) | All unchanged | 📄 | No | None needed |

### Pre-Orders
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| PO1 Filters | Medium (Sprint-2 #6) | **Fixed** — `preOrdersFilter()` sets frame `src` | ✅ | No — resolved | None needed |
| PO2 Apply/Reset | Medium (Sprint-2 #6) | **Fixed**, frame-scoped | ✅ | No — resolved | None needed |
| PO3 Table lazy load | — | Unchanged | 📄 | No | None needed |
| PO4 Pool data | — (already AJAX) | **Same handler-loss risk as ES1/ES2** — bound only on `turbo:load` with a one-time guard | 🔴 (same class of bug, not individually live-tested) | **Likely, same mechanism as Regression 2** | **Fix as part of the same patch** — see Regression Detail |
| PO5-PO7 Add/Edit/Delete | Low | Unchanged | — | No | Leave as-is |
| PO8 Pagination | — | Unchanged, genuinely frame-scoped | 📄 | No | None needed |

### Account / Settings / Profile
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| AC1-AC6 | Low / — | Unchanged (AC1 cosmetic tab restructure only) | 📄 | No | Leave as-is (P3) |

### Notes
| Control | Audit Priority | Impl. Status | Live | Regressed? | Recommendation |
|---|---|---|---|---|---|
| NO1-NO2 | Low | Unchanged | — | No | Leave as-is (P3) |

---

## Regression Detail

### Regression 1 — Environment trend charts go permanently blank (🔴 live-confirmed, severe)

**Symptom:** Temperature/Humidity trend charts render correctly on first page load, then go blank after the first 10-second poll and never recover.

**Root cause:** `environment/_live-data.blade.php:164-165` was changed from `LayRateChart.create(...)` to `LayRateChart.update(...)` in the most recent commit. The page's polling mechanism (`environment.blade.php:129-177`) fetches new HTML and does `frame.innerHTML = newFrame.innerHTML` every 10s — this **destroys and recreates the `<canvas>` DOM nodes**. `LayRateChart.update()` looks up the *existing* chart instance (still bound to the *old, now-detached* canvas) and redraws onto it — invisibly. The new canvas that's actually on screen never gets a `new Chart()` call.

**Why `.update()` seemed reasonable but isn't:** it's a valid, cheaper pattern *only* when the canvas node's identity is stable across re-renders (true for genuine Turbo Frame navigation, which every other chart call site in the app uses). Environment's manual `innerHTML=` polling is the one place that assumption doesn't hold.

**Fix (small, one of two options):**
- Revert `_live-data.blade.php:164-165` to `LayRateChart.create(...)`, or
- Switch the poll mechanism itself to a genuine Turbo Frame reload (`frame.reload()` / re-set `src`), removing the need for the manual `initEnvCharts()` shim entirely — architecturally cleaner, slightly larger diff.

### Regression 2 — Egg Stocks / Pre-Orders modals stop submitting after a tab round-trip (🔴 live-confirmed, severe)

**Symptom:** Live-reproduced exactly as described: visit Egg Logging → Egg Stocks → Egg Logging → Egg Stocks (or land on Stocks directly from another tab), click "Add Stock," fill the form, click submit — **nothing happens**. No request fires, no error shown, the modal just sits there. Confirmed via direct reproduction: `poolFetchFired=false` (the cage/size-select's dependent AJAX fetch never fires either) and `postFired=false` on submit, with the only network activity being unrelated background frame requests.

**Root cause:** Sprint-1 item 3 (EG1, egg section tabs) converted tab switching from full page navigation to `turbo-frame#egg-content` `src`-swapping. That's the correct fix for EG1 itself — but `eggs/stocks.blade.php`'s modal wiring (`performAddStock()`, the cage-pool fetch, classify-input validation — everything) is bound only inside a `document.addEventListener('turbo:load', ...)` block gated by a one-time `window.__eggStocksBound` flag (`eggs/stocks.blade.php:425-427`). Tab-switching via `frame.setAttribute('src', ...)` fires `turbo:frame-load`, not `turbo:load`. First visit: flag is false, binds correctly. Any subsequent tab round-trip: flag is already `true`, rebinding is skipped, and the freshly-rendered modal has no listeners at all.

**Identical bug, same root cause, in `eggs/pre-orders.blade.php:404-424`** — `fetchPreorderPools()` and the Add Pre-Order modal's `MutationObserver` are gated the same way (PO4 in the table above).

**Fix (small, one patch covers both):** rebind on `turbo:frame-load` scoped to `#egg-content` instead of (or in addition to) `turbo:load`, or drop the one-time guard for these two frame-loaded partials specifically. `eggs/recent-logs.blade.php:132-141`'s Escape-key handler already avoids this class of bug by querying the DOM fresh at handler-invocation time rather than at bind time — a pattern the other two could follow instead if a broader rebind-on-every-frame-load approach is judged riskier.

### Regression 3 — Chickens bulk Cull doesn't refresh the Inventory list (🟡 code-confirmed, cosmetic)

**Symptom:** Culling a hen from the Inventory tab's bulk-action bar correctly updates the Culling Records table, but the Inventory list still shows the hen as active/present until the user manually re-filters or reloads.

**Root cause:** `ajaxCull()` (`chickens/partials/cull-modal.blade.php:108-109`) only reloads `#chickens-culling-records`. The sibling Move and Remove handlers (`move-modal.blade.php:315-320`, `remove-modal.blade.php:165-170`) both correctly reload `#chickens-inventory-list` in addition to their own records frame — Cull is the one of the three that was left incomplete.

**Fix (trivial):** add the same `#chickens-inventory-list` reload line to `ajaxCull()` that Move and Remove already have.

---

## Risk Pattern Assessment (per the original audit's Risk Assessment section)

### (a) Every fetch/frame response updates ALL associated DOM, not just primary data

| Module | Status |
|---|---|
| Analytics | ✅ Confirmed atomic — single JSON payload, one synchronous callback writes all 5 KPI fields + 3 charts + labels + URL, no partial-render window possible |
| Reports | ✅ Confirmed atomic — `_preview.blade.php` include already bundles the summary pills with the table, one response |
| Environment | 🔴 **Violated, worse than "stale"** — metric cards/sensor cards refresh live every 10s (atomic, correct), but the trend charts next to them are permanently frozen/blank (Regression 1) — a more severe failure mode than the transient staleness the audit warned about |
| Egg Stocks (ES1) | 🔴 Was fixed correctly for the *first* page load (patches `#summaryCards` + refreshes live-data frame together) — but see Regression 2, the whole handler goes dead after a round-trip, so "atomic" becomes moot |
| Chickens CH12 | 🟡 Move/Remove atomic (records + inventory both refresh); Cull is not (Regression 3) |

### (b) No closure-scoped state inside frames — shared `window.*` functions only

| Module | Status |
|---|---|
| Analytics | ✅ Fully compliant — `renderAnalyticsCharts`/`analyticsFetch` are genuine `window.*`-level, frame-internal IIFE only captures *initial* data for first paint and is never relied on again |
| Forecast | ✅ Architecturally immune — its frames/streams are *genuinely* re-evaluated by Turbo on every real render, so closures are never stale by construction |
| Chickens / Hardware / Notifications | ✅ Confirmed compliant — all turbo-frame-loaded partials in these modules contain zero `<script>` tags; handlers are global functions defined once in the parent page |
| Environment | 🔴 **This is the exact root cause of Regression 1.** `window.initEnvCharts` is nominally a `window.*` function (looks compliant on the surface) but is defined inside an IIFE whose captured data only refreshes on a *genuine* script re-evaluation — which the page's manual poll mechanism never triggers. Shared-in-name only. |
| Egg Stocks / Pre-Orders | 🔴 **This is the exact root cause of Regression 2.** Same underlying anti-pattern as Environment, manifesting as a dead modal instead of a stale chart. |
| Analytics (minor, non-blocking) | 🟡 New finding, not from the original audit: the reintroduced `turbo:load` listener (`analytics.blade.php:268-283`, added back after an earlier one was removed for causing staleness) has no re-registration guard, unlike the app-layout's own established `window.__layoutScriptInitialized` convention. Each full Turbo Drive visit to `/analytics` stacks another copy. **Not a correctness bug** (all stacked copies write identical, current data), just redundant network calls and a minor inconsistency with this codebase's own established anti-duplication pattern. Low priority. |

**Overall:** the documented fix for risk (b) is faithfully and correctly applied everywhere it was *originally* applied (Analytics). It has been **violated twice** in code written *since* the audit (Environment's poll mechanism, Egg Stocks/Pre-Orders' modal binding) — both by the same specific mistake: assuming a "shared `window.*` function" is sufficient protection, without checking that whatever *triggers* that function's use is also compatible with when the function's closure actually gets refreshed. That's the one-sentence lesson worth carrying into any further work: **`window.*` scoping alone doesn't prevent staleness if the re-render path that's supposed to refresh the closure never fires.**

---

## Remaining Scope & Recommendation

### Sprint 3 items 10–12 (per original doc)

- **Item 10 (Bulk Move/Cull/Remove):** Already done (stale premise) — just needs Regression 3's one-line fix.
- **Item 11 (Egg Stocks edit) / Item 12 (Egg Logging edit):** Still full-page-reload, as originally documented. **Recommendation: do not convert.** This session's own findings reinforce the project's existing reasoning even more strongly than when it was first written — every new frame/AJAX surface touched in the last 24 hours' worth of commits introduced a real regression (2 severe, 1 cosmetic). Two of those three came from exactly this class of "wire a modal's AJAX submit up to a Turbo-Frame-driven page" work, which is precisely what items 11–12 would repeat. The risk/effort ratio the project already flagged (confirmation-modal re-verification, new bug surface area, citing the CSRF and mortality-underflow issues found mid-conversion) has just been demonstrated live, not hypothetically.

### P3 "leave as-is" items

No findings suggest revisiting any of these — spot-checked Dashboard onboarding, Account/Settings forms, Notes, Hardware CRUD, Cage forms, Reports CSV/print, Forecast download/import, and all confirmation-gated deletes; all unchanged from audit and functioning as full-page-reload by design.

---

## Go / No-Go Recommendation

**No-go on further Sprint 3 conversion work. Go on a short, targeted bugfix pass first:**

1. Fix Regression 1 (Environment charts) — small
2. Fix Regression 2 (Egg Stocks + Pre-Orders modal binding) — small, one patch pattern covers both
3. Fix Regression 3 (Chickens Cull frame reload) — trivial, one line
4. Manually confirm the two "code-confirmed, live-test-inconclusive" items (Forecast dropdowns, Feed Add Batch) to fully close the loop — 5 minutes, no code changes expected
5. **Then close out the AJAX conversion effort.** Items 11–12 are not recommended. The codebase has now converted every genuinely high/medium-frequency interaction; what's left is deliberately low-frequency/consequential, and this audit cycle is itself evidence that the marginal conversions cost more (in regression risk) than they return in UX benefit.

---

## Fixes Applied & Verified (same-day follow-up)

All three regressions were fixed immediately after this report and confirmed via direct live reproduction of the exact broken scenario (not just re-reading code).

### Regression 1 — Environment charts
**Fix:** reverted `_live-data.blade.php:164-165` from `LayRateChart.update(...)` back to `.create(...)`, matching every other chart call site in the app.
**Live verification result: could not be fully reproduced live.** On this dev machine, both DHT22 sensors have no readings in the trailing 24h window (no physical hardware attached), so `hasAnyData` is false and the chart legitimately never creates an instance at all — canvas stays hidden, empty-state shown. That's correct behavior, not a bug, but it also means the *specific* detached-canvas failure mode (which only manifests once a chart instance exists and then survives a poll-triggered re-render) has no live data path to trigger it here. The fix itself is verified correct by direct code inspection: `.create(id, config)` and `.update(id, config)` share an identical signature, and `.create()` always re-queries the canvas fresh by DOM id rather than reusing a potentially-detached instance reference — so applying it is safe and directly addresses the traced root cause. Recommend a final live confirmation once real sensor data is flowing (post-deployment, or by pointing at a copy of the DB with recent environmental_logs rows).

### Regression 2 — Egg Stocks / Pre-Orders modal binding
**Fix, and a real methodological trap hit along the way:** the first attempt (rebind on `turbo:frame-load`, dropping the one-time guard entirely) looked correct by inspection but **failed live** — after a tab round-trip, the Add Stock submit still did nothing. Root cause of *that* failure: the whole `<script>` block lives inside `turbo-frame#egg-content`, so Turbo re-evaluates it (not just innerHTML-swaps it) on every round-trip. Removing the guard entirely meant `document.addEventListener('turbo:frame-load', ...)` itself got re-registered on every round-trip too — stacking additional listeners on `document` (which persists across frame renders) rather than just re-running the binding logic. Confirmed via temporary console tracing: two `turbo:frame-load` log lines fired for one click after a single round-trip, climbing with each subsequent one.

**Corrected fix:** separate the two concerns explicitly — the *binding function* (`window.__bindEggStocks` / `window.__bindPreorders`) is reassigned on every render (cheap, safe, always does fresh DOM queries), but the *document-level listeners that call it* are registered exactly once, guarded by `window.__eggStocksListenersRegistered` / `window.__eggPreordersListenersRegistered`, and always invoke "whichever version of the binding function is currently assigned."

**Live verification result: FIXED, confirmed.** After 3 consecutive tab round-trips (Egg Logging ↔ Egg Stocks, and Egg Logging ↔ Pre-Orders), submitting Add Stock now fires a real POST to `/eggs/stocks` and correctly triggers the live-data frame refresh afterward; opening Add Pre-Order now correctly re-fires the pool-data fetch. Both reproduced the exact user-facing symptom from the original report, then confirmed it gone.

### Regression 3 — Chickens bulk Cull
**Fix:** `ajaxCull()` now reloads both `#chickens-culling-records` and `#chickens-inventory-list` on success, matching Move/Remove's existing pattern. Also fixed a second, related bug found while making this change: the culling-records reload itself used `frame.src = frame.src`, a no-op in most browsers since the attribute value doesn't change — switched to the clear-then-reset pattern (`frame.src = ''; frame.src = originalSrc;`) that Move/Remove already use correctly.
**Live verification result: code-confirmed only.** Deliberately did not trigger a real cull against the production farm database (per the standing rule from earlier in this engagement). Confirmed via `ajaxCull.toString().includes('chickens-inventory-list')` in a live browser session that the fix is actually loaded and active (not just present in source but stale in a cached view), and the change itself is a narrow, single-pattern addition copied from two already-working, already-tested sibling handlers — low residual risk.

### Full regression sweep
Re-ran the same 24-route sweep as the original UI/UX engagement after all three fixes: zero client-side errors, zero HTTP 5xx, across every major section of the app.

### One operational note worth keeping
Two of the three fixes initially appeared broken in live testing purely because of stale PHP OPcache — the same class of issue this project has hit before (there's a standing `/_reset-opcache` route in `routes/web.php` for exactly this). Hitting that endpoint after each source edit, before re-testing, resolved it both times. Worth remembering for any future same-session edit-then-verify loop against this `php artisan serve` instance.
