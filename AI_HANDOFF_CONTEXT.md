# LayRate — AI Handoff Context

**Purpose of this file:** LayRate has accumulated 26 audit/analysis/reference markdown files across multiple sessions and tools. This is an index of all of them — what each contains, whether it's still accurate, and which one is the actual current source of truth — so a new AI assistant (or teammate) can get oriented without reading all 8,300+ lines individually.

**Last updated:** 2026-07-17 — the final verified recalculation (80.52%, 208/208 suite green) after the Reports & Analytics implementation pass (commit cd13284) and subsequent fix passes.

---

## Read this first: the one authoritative document

**[`docs/project-status.md`](docs/project-status.md)** is the current, maintained source of truth for project completion. Everything else in this list is a historical snapshot that fed into it at some point. If a claim in any other document below conflicts with `project-status.md`, **trust `project-status.md`** — it was last verified via live browser testing, direct database queries, and a full automated test run (208/208 passing, 623 assertions), not assumption.

Current headline numbers (from `project-status.md`, verified not estimated):
- **89 total tracked items: 66 ✅ implemented | 18 ⚠️ partial | 5 ❌ missing** (plus 6 dropped/deferred, excluded from active count)
- **Overall completion: 80.52%** (verified recalculation)
- Full per-section breakdown, an "Audit History" table showing how this number moved across 6 passes, and a prioritized ROI list are all in that file.

---

## ⚠️ Known stale claims — do not repeat these

Several older documents contain claims that are **factually wrong about the current codebase**. These were true at some point, or were never verified before being written down, and later passes disproved them. Flagging explicitly so a new AI doesn't propagate them:

| Stale claim | Found in | Actual current state |
|---|---|---|
| "Tailwind CSS, Chart.js, and Lucide Icons are loaded via CDN" | `CONTEXT.md`, `UI-UX-AUDIT.md` (header), `docs/cdn-audit-report.md` | **False.** Verified by grepping every `<script src=` / `<link href=` in the entire `resources/views/` tree — zero external URLs anywhere. All four libraries (Tailwind, Chart.js, Lucide, Turbo) plus the Inter font are self-hosted in `public/css/` and `public/js/`, served via Laravel's `asset()` helper. Confirmed 2026-07-17 in response to a direct "check for CDN usage" request. |
| "RBAC exists on an unmerged feature branch" | `QA_REPORT_2026-07-10.md` | **False.** No such branch exists or ever existed. Access control is a binary `admin`/`operator` flag on `User` + one `Gate::define('admin', ...)` + `EnsureAdmin` middleware. That report's test counts and other claims are accurate — only this one claim is wrong. |
| "Analytics charts don't render — root cause unknown, needs browser debugging" | `docs/codebase-audit-2026-07-16.md`, `docs/completion-analysis-2026-07-16.md` (item 79, originally ⚠️) | **Superseded.** Root cause was diagnosed in `docs/reports-analytics-deep-audit-2026-07-16.md` — a `window.hdepChart` naming collision with the browser's automatic `id`→global binding. **Fixed 2026-07-17 in commit cd13284** via a namespaced `window.__analyticsCharts` store + type-guarded `destroyChart()`. Live-verified: all 3 charts render with real data, re-render keeps exactly 3 Chart.js instances (no leak). |
| "Forecast section: 80.5%, single item, 100% feature-complete" | `docs/project-status.md`, prior versions | **Arithmetic error, now corrected to 67.4%.** Items #91/#92 were added to the section's checklist in a later pass but never recalculated into the percentage — it was still using the original 1-item/100% math. Fixed 2026-07-17. |
| "Egg Management: 10✅, 2⚠️" | `docs/project-status.md`, prior versions | **Off-by-one, corrected to 10✅/3⚠️.** The detail table has always listed 3 partial items (61, 63, 64); the header summary undercounted. |

---

## Audit & Analysis Documents (chronological)

### 2026-07-04 — [`docs/audit-hen-placement.md`](docs/audit-hen-placement.md) (614 lines)
Deep schema-and-code trace of every path that assigns, moves, removes, or displays the hen↔cage_slot relationship. Read all 19 relevant migrations, models, and did live DB queries. This predates the slot-grid model's stabilization — largely superseded by later work, but useful if debugging occupancy/placement edge cases.

### 2026-07-05 — [`AUDIT_2026-07-05.md`](AUDIT_2026-07-05.md) (246 lines)
Audit of Egg Management, Hardware, Environment, and Feed & Nutrition (Forecast explicitly out of scope, still in development at the time). Investigation only, no fixes applied. Findings from this fed into the numbered item system later formalized in `codebase-audit-2026-07-16.md`.

### 2026-07-05 — [`docs/audit-cage-chicken-wishlist.md`](docs/audit-cage-chicken-wishlist.md) (434 lines)
Wishlist-style audit of Cage and Chicken features, every finding cited to file:line. Covers slot reorder mechanisms, sensor checkbox behavior, and more — many items here were later resolved (see `project-status.md` Cages/Chickens sections for current status; don't trust this file's status column without cross-checking).

### 2026-07-05 — [`docs/verification-report.md`](docs/verification-report.md) (203 lines)
Follow-up to the wishlist audit — takes specific claimed findings and verifies them with real evidence (curl requests, `artisan tinker`, direct DB queries, rendered HTML), correcting some and confirming others. Good example of "verify, don't assume" methodology later applied more broadly.

### 2026-07-01 — [`docs/changes-2026-07-01.md`](docs/changes-2026-07-01.md) (54 lines)
Changelog-style note on a specific session's UI fixes (design system token expansion, Dashboard Cage Overview badge positioning). Historical record, not an audit.

### ~2026-06-29 to 07-02 — [`CHANGELOG_AUDIT.md`](CHANGELOG_AUDIT.md) (608 lines)
Retrospective audit of 15 commits / 119 files changed over a 4-day window (Egg Logging edit, Feed delete, Mortality edit+cascade, Hardware Inventory as a new feature, the slot-grid schema migration, etc.). Useful as historical narrative of how the app got to its current shape; not a current-state checklist.

### June 2026 — [`UI-UX-AUDIT.md`](UI-UX-AUDIT.md) (620 lines)
Broad design/HCI improvement plan — layout architecture, visual consistency, HCI principles, user-type considerations, reusable component recommendations, quick wins vs. longer-term refactors. **Header claims CDN-loaded stack — see stale-claims table above.** This is the document that originally proposed the Notion-inspired "warm minimalism" direction later formalized in `DESIGN-SYSTEM.md`.

### Phase 5/6 completion — [`REDESIGN-AUDIT.md`](REDESIGN-AUDIT.md) (203 lines)
Final visual-consistency sweep after the Notion redesign's Phase 5, checking spacing scale violations (`p-5`/`gap-3`/`gap-5`) file-by-file and verifying per-section component adoption. **Directly relevant to `project-status.md` item #87** — this document's spacing-violation table is effectively the same finding the 2026-07-17 re-audit rediscovered (53 files still non-compliant), suggesting the spacing cleanup has been attempted at least twice without full completion.

### 2026-07-10 — [`CODEBASE-ANALYSIS.md`](CODEBASE-ANALYSIS.md) (327 lines)
Findings-only report (no fixes) on the Forecast model specifically — documents the SARIMA/XGBoost pipeline architecture, and first identified the "static future covariates" and "no recency weighting" limitations that later became `project-status.md` items #91/#92.

### 2026-07-10 — [`QA_REPORT_2026-07-10.md`](QA_REPORT_2026-07-10.md) (153 lines)
Full regression pass: test suite baseline, migration safety checks, live HTTP verification per module. **Contains one confirmed-false claim about RBAC — see stale-claims table above.** Otherwise accurate; established the pattern of checking `layrate_testing` DB isolation and running pending migrations safely that's been followed in every session since.

### 2026-07-16 — [`docs/codebase-audit-2026-07-16.md`](docs/codebase-audit-2026-07-16.md) (165 lines)
The formal 84-item requirements checklist (later extended to 89 items) that `project-status.md` is built on top of. This is the **original numbering scheme** — items 1–84, later extended with 85–95. Treat as a historical snapshot; current status per item lives in `project-status.md`, not here.

### 2026-07-16 — [`docs/completion-analysis-2026-07-16.md`](docs/completion-analysis-2026-07-16.md) (383 lines)
The 4-dimension scoring methodology (Feature Completeness 40% / Code Quality 25% / UI-UX Consistency 20% / Data Integrity 15%) with the full per-section math. **This is the methodology reference** — `project-status.md`'s section percentages are computed using this exact framework. If you need to recalculate a section's score, replicate the math style shown here.

### 2026-07-16 — [`docs/repo-structure-audit-2026-07-16.md`](docs/repo-structure-audit-2026-07-16.md) (435 lines)
Full directory-tree mapping and documentation-file cross-reference. Re-checked 2026-07-17 (see below) — confirmed clean, only one new doc added since (the Reports/Analytics deep-dive).

### 2026-07-16 — [`docs/reports-analytics-deep-audit-2026-07-16.md`](docs/reports-analytics-deep-audit-2026-07-16.md) (172 lines)
Deep investigation specifically into Analytics and Reports, including **live Playwright browser testing** that definitively diagnosed the chart-rendering bug (item #79) and found two new latent issues (breed-lookup bug in the Production report, missing Egg Stock report type). This is the source of the most significant corrections in the current `project-status.md`.

### 2026-07-17 — `docs/project-status.md` final verified recalculation (commit cd13284 + subsequent fix passes)
The re-audit pass that diagnosed items #79/#93/#94, followed by the implementation pass that fixed all three (reports-analytics-deep-audit → code fixes → preview table build → test coverage → stale Forecast test rewrite). **Final state: 80.52% (verified), test suite fully green at 208/208 (623 assertions).** The Audit History table inside `project-status.md` tracks all five passes on this date.

### 2026-07-17 — CDN usage check (this session, informal, folded into this document)
Direct grep of every script/link tag in `resources/views/` plus a domain-keyword search (`cdn.`, `jsdelivr`, `unpkg`, `cdnjs`, `googleapis`, etc.). Result: zero external URLs. See stale-claims table above for which older docs this contradicts.

---

## Design & Architecture Reference (not audits — don't treat as checklists)

| Document | Lines | What it is |
|---|---|---|
| [`DESIGN-notion.md`](DESIGN-notion.md) | 498 | Analysis of Notion's own design language (warm minimalism, single structural blue, paper-soft canvas) — the inspiration source, not LayRate-specific. |
| [`DESIGN-SYSTEM.md`](DESIGN-SYSTEM.md) | 473 | LayRate's own design system, mapping Notion's tokens onto LayRate's functional needs (sidebar as a documented exception, muted status-color ramp, cage-identity palette). **Phase 0 (this doc) is complete; Phase 2 (applying it to every view) is only ~40-60% done** — several inline hex colors still contradict the tokens defined here. Contains the exact Tailwind config block (§9) if someone picks this migration back up. |
| [`docs/egg-stock-architecture.md`](docs/egg-stock-architecture.md) | 102 | How the egg pipeline works: Egg Logging → Egg Size Logs → Stock Pool → Stock Batches → Pre-Orders, with the pool-availability formula. Accurate as of when written; egg management has since gained low-stock alerts and freshness tracking (see `project-status.md` items #89/#90). |
| [`docs/system-workflow.md`](docs/system-workflow.md) | 144 | End-user, step-by-step "how to use LayRate" guide. Reference material, not an audit. |
| [`docs/proposed-system-workflow.md`](docs/proposed-system-workflow.md) | 101 | As-Is (manual farm process) → To-Be (LayRate) mapping — useful for understanding *why* each feature exists, for a capstone defense or similar. |
| [`docs/CHANGELOG_SUMMARY.md`](docs/CHANGELOG_SUMMARY.md) | 139 | Narrative changelog of the slot-grid cage model migration. Historical record. |
| [`CONTEXT.md`](CONTEXT.md) | 375 | General tech-stack/codebase overview. **Contains the stale CDN claim — see table above.** Otherwise a reasonable orientation doc. |
| [`docs/api-endpoints.md`](docs/api-endpoints.md) | 212 | Endpoint inventory, written for the same class activity as `API_ROUTES.md` below — largely duplicates it. |
| [`API_ROUTES.md`](API_ROUTES.md) | 187 | Full route list with method/purpose/access-level, written for a professor/documentation purpose. Accurate as a route inventory; doesn't track feature completeness. |
| [`TEST_PLAN.md`](TEST_PLAN.md) | 824 | Manual QA test plan (58 test cases across 10 modules) written for a class testing activity — assigns cases to "Tester 1"/"Tester 2". Not related to the automated PHPUnit suite. |

---

## If you're picking up work on this project, in order of likely relevance

1. **Read `docs/project-status.md` fully.** It's the only document guaranteed current.
2. **If touching Analytics or Reports**, read `docs/reports-analytics-deep-audit-2026-07-16.md` — it has the exact fix needed for the chart bug and the two new latent-bug findings, ready to implement.
3. **If touching visual/CSS work**, read `DESIGN-SYSTEM.md` §9 (the Tailwind config block) before adding new colors — the tokens already exist, they're just inconsistently applied. Don't invent a third palette on top of the two that already exist.
4. **If asked to verify a claim from any file above**, re-check it against current code first — this project has a documented pattern (see stale-claims table) of audit docs going stale as fast as they're written, because multiple people/sessions work on the codebase in parallel without always updating the tracking docs.
5. **Do not run destructive database commands** (`migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`) — the live `layrate` database is real farm data, not a disposable dev seed. Tests run against an isolated `layrate_testing` database (see `tests/Feature/DbSanityCheckTest.php`, a sentinel that enforces this).
