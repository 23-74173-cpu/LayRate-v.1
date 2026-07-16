# Repository Structure Audit — 2026-07-16

**Scope:** Full mapping of the `LayRate-Main` repository, identifying all documentation files, structural inconsistencies, and cross-referencing against `docs/project-status.md`.

---

## 1. Complete Directory Tree

### Root Level

| Path | Description |
|------|-------------|
| `app/` | Laravel application code |
| `artisan` | Laravel CLI entry point |
| `bootstrap/` | Laravel app bootstrap (framework-managed) |
| `composer.json` / `composer.lock` | PHP dependency manifests |
| `config/` | Laravel configuration files (10 files) |
| `database/` | Migrations, factories, seeders |
| `dist/` | Built frontend assets (not connected to Laravel views) |
| `docs/` | Project documentation, audits, plans, specs |
| `forecast-api/` | Python forecasting pipeline (SARIMA + XGBoost) |
| `node_modules/` | npm dependencies |
| `package.json` / `package-lock.json` | Node dependency manifests |
| `phpunit.xml` | PHPUnit configuration |
| `public/` | Web server document root (compiled CSS/JS, index.php) |
| `resources/` | Blade views, CSS source, JS source |
| `routes/` | web.php (all HTTP routes), console.php (Artisan commands) |
| `storage/` | Logs, cache, sessions, compiled views |
| `tests/` | Feature and Unit tests |
| `vendor/` | Composer dependencies |

### `app/` — Application Code

| Path | Description |
|------|-------------|
| `app/Console/Commands/` | 10 Artisan commands (backfill, audit, repair, sync, truncate) |
| `app/Http/Controllers/` | 22 controllers (including base Controller) |
| `app/Http/Middleware/` | `DeviceAuth.php`, `EnsureAdmin.php` |
| `app/Http/Requests/` | `StoreHardwareItemRequest.php` (only form request) |
| `app/Models/` | 26 Eloquent models |
| `app/Providers/` | `AppServiceProvider.php` |
| `app/Services/` | 5 service classes (FCR, Environment, Timeline, Alerts) |

### `resources/` — Views & Assets

| Path | Description |
|------|-------------|
| `resources/css/` | Tailwind CSS source (app.css) |
| `resources/js/` | app.js (minimal — vanilla JS inline in Blade) |
| `resources/views/` | All Blade templates |
| `resources/views/analytics/` | Analytics page + chart partial + skeleton |
| `resources/views/auth/` | Login page |
| `resources/views/cages/` | Cage management (index, bulk-add, confirm-delete, labels) |
| `resources/views/chickens/` | Chicken inventory + lifecycle modals + sub-views |
| `resources/views/chickens/partials/` | 6 modal partials (register, cull, move, remove, health, weight) |
| `resources/views/components/` | 13 reusable Blade components |
| `resources/views/dashboard/` | Dashboard + 3 lazy-loaded turbo-frame partials + skeletons |
| `resources/views/egg-logging/` | Egg logging + edit modal + logs partial + skeleton |
| `resources/views/eggs/` | Egg management hub: stocks, pre-orders, recent-logs, qr-print, tabs |
| `resources/views/eggs/pre-orders/` | Pre-order table + skeleton |
| `resources/views/eggs/stocks/` | Stock live-data + skeleton |
| `resources/views/environment/` | Environment monitoring + live-data + logs + skeletons |
| `resources/views/feed/` | Feed management + live-data + FCR content + skeleton |
| `resources/views/forecast/` | Forecast UI + calendar + results + workspace + skeleton |
| `resources/views/hardware/` | Hardware inventory + live-data + skeleton |
| `resources/views/layouts/` | Main app layout (sidebar, header, Turbo Drive) |
| `resources/views/mortality/` | Mortality logging + logs partial + skeleton |
| `resources/views/notes/` | Notes CRUD |
| `resources/views/notifications/` | Alerts list + table + skeleton |
| `resources/views/partials/` | `cage-sensor-badge.blade.php`, `slot-box.blade.php` |

### `docs/` — Documentation

| Path | Description |
|------|-------------|
| `docs/superpowers/plans/` | 3 implementation plans (1784–2868 lines each) |
| `docs/superpowers/specs/` | 4 design specs (74–242 lines) |
| `docs/` root | 12 current audit/report/workflow files |

### `database/` — Data Layer

| Path | Description |
|------|-------------|
| `database/migrations/` | 50 migration files covering full schema history |
| `database/factories/` | `UserFactory.php` |
| `database/seeders/` | `DatabaseSeeder.php` (60 cage_slots, 180 hens, 4 sensor slots) |

### Non-Laravel Additions

| Path | Description |
|------|-------------|
| `forecast-api/` | Python forecasting pipeline (5 Python files, Dockerfile, README) |
| `LayRate - Arduino/` | Arduino Uno firmware (PlatformIO project) |
| `dist/` | Build output (index.html + bundled JS/CSS — not connected to Laravel) |
| `.github/workflows/deploy.yml` | CI/CD to Raspberry Pi |

### Clutter / Non-Standard

| Path | Type | Issue |
|------|------|-------|
| `.history/` | VS Code local history (root) | Should be in `.gitignore` |
| `LayRate - Arduino/.history/` | VS Code local history (Arduino) | Should be in `.gitignore` |
| `test-results/` | Empty directory | Leftover, no purpose |
| `cage_slots_backup_20260701.sql` | SQL dump (0 lines — empty file) | Stray artifact at repo root |

---

## 2. All Documentation Files — Complete Inventory

### 2a. Root-Level `.md` Files (13 files)

| # | File | Date | Author | Lines | Summary |
|---|------|------|--------|-------|---------|
| 1 | `README.md` | 2026-06-10 | FelmanE30 | 59 | Project overview. Minimal — no setup instructions, no architecture. |
| 2 | `CONTEXT.md` | 2026-07-03 | 23-74173-cpu | 375 | Full codebase context used by AI tools. Comprehensive technical overview. |
| 3 | `API_ROUTES.md` | 2026-07-08 | FelmanE30 | 187 | Auto-documented API route list. Mirrors `routes/web.php`. |
| 4 | `AUDIT_2026-07-05.md` | 2026-07-08 | 23-74173-cpu | 246 | Egg Management, Hardware, Environment, Feed audit. Several findings superseded by later work. |
| 5 | `CHANGELOG_AUDIT.md` | 2026-07-03 | 23-74173-cpu | 608 | Changes June 29–July 2 across 15 commits. Historical snapshot. |
| 6 | `CODEBASE-ANALYSIS.md` | 2026-07-15 | 23-74173-cpu | 327 | Deep analysis of Forecast model, sensor ingestion, egg stock/pool architecture. |
| 7 | `DESIGN-notion.md` | 2026-07-01 | 23-74173-cpu | 498 | Extracted Notion design language reference (colors, typography, spacing). |
| 8 | `DESIGN-SYSTEM.md` | 2026-07-01 | 23-74173-cpu | 473 | Mapped Notion design tokens onto LayRate's functional requirements. |
| 9 | `QA_REPORT_2026-07-10.md` | 2026-07-10 | FelmanE30 | 153 | Full regression/integration QA. 137/137 tests passing. |
| 10 | `REDESIGN-AUDIT.md` | 2026-07-01 | 23-74173-cpu | 203 | Post-redesign visual consistency sweep. Flags non-Notion spacing/colors in 12+ views. |
| 11 | `TEST_PLAN.md` | 2026-07-10 | FelmanE30 | 824 | Manual testing plan with step-by-step test cases for all modules. |
| 12 | `UI-UX-AUDIT.md` | 2026-07-01 | 23-74173-cpu | 620 | Full UI/UX audit with improvement plan. Baseline design tokens + per-section findings. |

### 2b. `docs/` Files (14 files)

| # | File | Date | Author | Lines | Summary |
|---|------|------|--------|-------|---------|
| 1 | `project-status.md` | 2026-07-16 | *(just created)* | 437 | **Current master document.** Merges requirements audit + completion scoring. |
| 2 | `codebase-audit-2026-07-16.md` | 2026-07-16 | *(no git log)* | 165 | 84-item requirements checklist with ✅/⚠️/❌ status. **Superseded by project-status.md.** |
| 3 | `completion-analysis-2026-07-16.md` | 2026-07-16 | *(no git log)* | 383 | 4-dimension percentage scoring. **Superseded by project-status.md.** |
| 4 | `verification-report.md` | 2026-07-05 | 23-74173-cpu | 203 | Live HTTP/tinker verification of 9 findings. |
| 5 | `cdn-audit-report.md` | 2026-07-01 | 23-74173-cpu | 105 | External CDN dependency audit (Tailwind CDN, Chart.js, Lucide, Inter). |
| 6 | `audit-cage-chicken-wishlist.md` | 2026-07-05 | 23-74173-cpu | 434 | Cage & Chicken features wishlist audit. Lists 14 gaps/missing features. |
| 7 | `audit-hen-placement.md` | 2026-07-05 | 23-74173-cpu | 614 | Hen placement workflow audit. Workflow diagram + detailed findings. |
| 8 | `egg-stock-architecture.md` | 2026-07-16 | 23-74173-cpu | 102 | Egg stock pool architecture design doc. Includes 4 known follow-ups. |
| 9 | `api-endpoints.md` | 2026-07-15 | 23-74173-cpu | 212 | Auto-generated endpoint list from route definitions. |
| 10 | `CHANGELOG_SUMMARY.md` | 2026-07-08 | FelmanE30 | 139 | Recent changes summary covering slot-grid migration and earlier work. |
| 11 | `changes-2026-07-01.md` | 2026-07-01 | 23-74173-cpu | 54 | Uncommitted changes from July 1 session (design system, dashboard, loading bar). |
| 12 | `proposed-system-workflow.md` | 2026-06-28 | FelmanE30 | 101 | "To-be" system workflow proposal. |
| 13 | `system-workflow.md` | 2026-06-28 | FelmanE30 | 144 | Current system workflow description. |
| 14 | `audit-hen-placement.md` | *(see #7 above, counted as 6)* | | | |

*(Correction: 13 unique files listed for docs/, not 14. `audit-hen-placement.md` appears once.)*

### 2c. `docs/superpowers/` Files (7 files)

| # | File | Date | Lines | Summary |
|---|------|------|-------|---------|
| 1 | `plans/2026-06-10-report-print-design.md` | 2026-06-10 | 408 | Implementation plan for report print/document design. Superseded by live code. |
| 2 | `plans/2026-06-28-cage-level-feature-set.md` | 2026-06-28 | 1,784 | Cage-level feature set plan. Superseded by live code. |
| 3 | `plans/2026-06-29-slot-grid-cage-model.md` | 2026-06-29 | 2,868 | Slot-grid migration plan. Superseded by live code. |
| 4 | `specs/2026-06-10-report-print-design.md` | 2026-06-10 | 74 | Report print design spec. |
| 5 | `specs/2026-06-28-cage-level-feature-set-design.md` | 2026-06-28 | 127 | Cage-level feature set design (forecasting, sensor override, flock tracking, PIN). |
| 6 | `specs/2026-06-29-slot-grid-cage-model-design.md` | 2026-06-29 | 104 | Slot-grid cage model design. |
| 7 | `specs/2026-06-30-pre-slot-migration-audit.md` | 2026-06-30 | 242 | Pre-migration codebase audit. Contains 1 unresolved issue not in project-status.md. |

### 2d. `forecast-api/` Documentation (3 files)

| # | File | Date | Author | Lines | Summary |
|---|------|------|--------|-------|---------|
| 1 | `README.md` | 2026-07-07 | HannsDonor | 199 | How to set up/run the Python forecasting pipeline. |
| 2 | `context.md` | 2026-07-05 | HannsDonor | 93 | Forecast API context and latest updates. |
| 3 | `forecastingContext.md` | 2026-07-07 | HannsDonor | 192 | Technical details of SARIMA + XGBoost ensemble forecasting. |

---

## 3. Structural Inconsistencies & Clutter

### 3a. Stray / Orphaned Files

| File | Issue |
|------|-------|
| `cage_slots_backup_20260701.sql` | 0-byte SQL dump file at repo root. Likely incomplete or failed export. Remove after confirming no content needed. |
| `forecast_input_sheet.xlsx` | 12KB XLSX at repo root. Used by forecast pipeline. Should be under `forecast-api/`. |
| `layrate_forecast_input_updated.xlsx` | 16KB XLSX at repo root. Same — should be under `forecast-api/`. |
| `.history/` (root) | VS Code local history directory — should be in `.gitignore`. Currently tracked/unclean. |
| `LayRate - Arduino/.history/` | Same VS Code local history for the Arduino subproject. |
| `test-results/` | Empty directory. No files inside. Leftover artifact. |

### 3b. Code-Level Inconsistencies

| Issue | Details |
|-------|---------|
| **Migration sequence gap** | `2026_01_01_000005` → `2026_01_01_000007` — no `000006` exists in the `2026_01_01*` series. `000006` appears in `2026_07_03_000006_create_removals_table.php` (July, not January). This may have been intentionally skipped or a numbering error in earlier development. No functional impact. |
| **Naming: Controllers** | Consistent `StudlyCase` + `Controller` suffix. One outlier: `HardwareItemController` (full word) vs `CageController` (short). No inconsistency issue. |
| **Naming: Models** | Consistent singular `StudlyCase`. One note: `MortalityLogHen` is a pivot/junction model vs. `MortalityLog` — no issue. |
| **Naming: Tests** | Mixed naming: some `*Test.php` with `@test` annotation (e.g., `EggReportingAndHistoryTest`), some use `test_` prefix method naming (`CageDeleteFlowTest` uses `test_standard_delete_...`). Both valid in PHPUnit but mix of conventions. |
| **Views: Reports folder** | `resources/views/reports/` does **not exist** — `reports.blade.php` is the only report view, directly under `resources/views/`. Every other feature module has its own subdirectory. |
| **`app/Http/Requests/`** | Contains only `StoreHardwareItemRequest.php` — all other controllers use inline `$request->validate()`. No form request classes for any other entity. |
| **No `routes/api.php`** | All routes are web-only. The `SensorIngestionController` sits in `Http/Controllers` and has web routes — no API route file exists despite REST-like ingestion endpoint. |

### 3c. Non-Standard Laravel Conventions

| Item | Explanation |
|------|-------------|
| `forecast-api/` | Entirely non-Laravel. Python project for ML forecasting. Correctly isolated. |
| `LayRate - Arduino/` | Arduino PlatformIO firmware. Separate subproject, should be in its own repo. |
| `dist/` | Build output folder. Not Laravel convention — typically `public/build/` if used. Likely from an early Vite build that was never wired to Blade views. |
| `docs/superpowers/` | "Superpowers" is a project-internal codename for AI-assisted planning. Contains planning docs generated before features were implemented. Historical reference. |
| `CONTEXT.md` | AI context file for Claude/opencode. Not a standard Laravel file. Informative but lives at root. |
| `app/Services/` | Contains domain services (`FcrCalculator`, `EnvironmentStatusService`, etc.). Acceptable Laravel practice, though some teams use `app/Actions/` or `app/Domain/`. |

---

## 4. Deep Review of Documentation Files — Cross-Reference with project-status.md

### 4a. Current / Recently Updated (keep as-is)

| File | Status | Notes |
|------|--------|-------|
| `docs/project-status.md` | **CURRENT MASTER** | Created 2026-07-16. Merges all prior audits. |
| `docs/egg-stock-architecture.md` | **Current** | 2026-07-16. Architecture design doc. Contains 3 follow-up items not in project-status.md (see §7). |
| `docs/cdn-audit-report.md` | **Current** | 2026-07-01. Still relevant — Tailwind CDN unpinned, no self-hosted fallback. |

### 4b. Superseded by project-status.md

| File | Status | Superseded By |
|------|--------|---------------|
| `docs/codebase-audit-2026-07-16.md` | **Superseded** | Merged into `project-status.md` |
| `docs/completion-analysis-2026-07-16.md` | **Superseded** | Merged into `project-status.md` |

### 4c. Stale / Partially Superseded (findings may no longer reflect codebase)

| File | Staleness | Details |
|------|-----------|---------|
| `UI-UX-AUDIT.md` | Moderate | 2026-07-01. Some issues fixed by later commits (loading bar, button spinners). Still references old spacing values (`p-5`, `gap-3`) in several views — may have been partially fixed. |
| `REDESIGN-AUDIT.md` | Moderate | 2026-07-01. Same vintage as UI-UX-AUDIT. Spacing violations list may be partially resolved. |
| `AUDIT_2026-07-05.md` | **Stale** | 2026-07-05. "Recent logs has cage-only filter" — this was fixed by adding breed/slot/logged_via filters in later work (now item 59 ✅ in project-status). "FeedController hardcoded recorded_by = 1" — fixed per CONTEXT.md. |
| `CHANGELOG_AUDIT.md` | **Stale** | 2026-07-03. Covers commits June 29–July 2 only. Many more commits since. |
| `docs/changes-2026-07-01.md` | **Stale** | Lists uncommitted changes from a single session. All now committed. |
| `docs/CHANGELOG_SUMMARY.md` | **Stale** | 2026-07-08. Covers slot-grid migration and earlier. No updates since. |
| `docs/proposed-system-workflow.md` | **Stale** | 2026-06-28. Proposed workflow — likely all implemented. |
| `docs/system-workflow.md` | **Stale** | 2026-06-28. May no longer match current implementation after slot-grid migration. |
| `docs/superpowers/plans/*` | **Archive** | 2026-06-10 to 2026-06-29. Implementation plans used to guide development. Superseded by live code. |
| `docs/superpowers/specs/*` | **Archive** | 2026-06-10 to 2026-06-30. Specs used before implementation. Superseded by live code. |

### 4d. Cross-Reference: Issues in Older Docs NOT Captured in project-status.md

| Source | Issue | In project-status.md? |
|--------|-------|----------------------|
| `pre-slot-migration-audit.md` | `FeedController::storeConsumption()` hardcoded `recorded_by => 1` | **NO — resolved by later work.** Fixed in Commit 1 per CONTEXT.md. |
| `egg-stock-architecture.md` §6 | `storeClassified()` does not lock `egg_size_logs` before reading — theoretical race | **NO** — should be evaluated for addition to "Still Missing" or "Needs Product Decision" |
| `egg-stock-architecture.md` §6 | No automated alert generation for low stock | **NO** — feed has low-stock alerts; egg stock does not. |
| `egg-stock-architecture.md` §6 | No stock batch aging / expiry logic | **NO** — not captured anywhere |
| `audit-cage-chicken-wishlist.md` | Lists 14 gaps. Many duplicated in project-status.md items 47, 50, 80–82, etc. | Mostly covered. Cross-reference needed. |
| `audit-hen-placement.md` | Hen placement workflow analysis. No specific unresolved issues found that aren't in project-status.md. | Covered. |
| `QA_REPORT_2026-07-10.md` | 137/137 tests passing. No RBAC merged. No SensorIngestionTest on main at that time. | Partially — test counts updated in project-status.md (163 tests now). RBAC not in scope. |

---

## 5. Proposed Documentation Folder Structure

Current: 13 root-level `.md` files + 14 in `docs/` + 7 in `docs/superpowers/` = 34 documentation files scattered across 3 locations.

### Proposed Structure

```
docs/
├── README.md                     # One-line: "See project-status.md for the current state"
├── project-status.md             # KEEP — living master status document
├── architecture/
│   ├── egg-stock-architecture.md # KEEP — current architecture doc
│   ├── system-workflow.md        # ARCHIVE — may be stale
│   └── api-endpoints.md          # KEEP — auto-generated reference
├── audits/
│   ├── verification-report.md    # KEEP — contains verified findings
│   ├── cdn-audit-report.md       # KEEP — still actionable
│   ├── audit-cage-chicken-wishlist.md  # ARCHIVE — superseded by project-status.md
│   ├── audit-hen-placement.md    # ARCHIVE — superseded
│   └── QA_REPORT_2026-07-10.md   # KEEP — historical QA snapshot
├── decisions/
│   # NEW — Product decision log, one file per decision
│   # e.g. cage-orientation-toggle.md, analytics-scope.md
├── archive/
│   # Moved here, NOT deleted
│   ├── codebase-audit-2026-07-16.md
│   ├── completion-analysis-2026-07-16.md
│   ├── AUDIT_2026-07-05.md       # Stale — findings superseded
│   ├── CHANGELOG_AUDIT.md        # Stale
│   ├── CHANGELOG_SUMMARY.md      # Stale
│   ├── changes-2026-07-01.md     # Stale
│   ├── proposed-system-workflow.md # Stale
│   ├── system-workflow.md        # Stale (move original here, keep copy in architecture/)
│   ├── UI-UX-AUDIT.md            # Stale — partially superseded
│   ├── REDESIGN-AUDIT.md         # Stale
│   ├── CODEBASE-ANALYSIS.md      # Superseded by project-status.md
│   ├── CONTEXT.md                # Move from root
│   ├── API_ROUTES.md             # Move from root
│   ├── DESIGN-notion.md          # Move from root
│   ├── DESIGN-SYSTEM.md          # Move from root
│   ├── TEST_PLAN.md              # Historical test plan
│   ├── README.md (root)          # Minimal — update to point at docs/
│   └── superpowers/              # Entire directory — historical planning only
│       ├── plans/
│       └── specs/
├── forecast-api/
│   # Forecast pipeline docs should move here from forecast-api/ root
│   # (README.md, context.md, forecastingContext.md)
```

**Rationale:**
- `docs/architecture/` — permanent architectural decisions, schema docs, workflow descriptions
- `docs/audits/` — audit reports with ongoing relevance (verified findings, CDN issues)
- `docs/decisions/` — NEW: product decision log (one file per decision) so the "Needs Product Decision" section in project-status.md can link to specific discussion docs
- `docs/archive/` — superseded snapshots, historical plans, implementation specs. Preserved for reference, not deleted.
- `root .md files` — move to appropriate `docs/` subdirectory. The root `README.md` should be updated to be the project's true README (setup instructions, architecture overview), not duplicate these docs.

---

## 6. Disposition Recommendations

### KEEP AS-IS (active reference)

| File | Reason |
|------|--------|
| `docs/project-status.md` | Current master status document |
| `docs/egg-stock-architecture.md` | Current architecture design |
| `docs/cdn-audit-report.md` | Still actionable — CDN issues unfixed |
| `docs/verification-report.md` | Contains verified findings from live testing |
| `docs/api-endpoints.md` | Useful auto-generated route reference |
| `CONTEXT.md` | AI context file — essential for LLM-assisted development |

### MERGE into project-status.md (then archive source)

| File | What to merge |
|------|---------------|
| `docs/codebase-audit-2026-07-16.md` | Already merged (this is the source document) |
| `docs/completion-analysis-2026-07-16.md` | Already merged (this is the source document) |

### ARCHIVE (move to `docs/archive/`, do not delete)

| File | Reason |
|------|--------|
| `docs/superpowers/plans/*` (3 files) | Historical implementation plans. Superseded by live code. |
| `docs/superpowers/specs/*` (4 files) | Historical design specs. Superseded by live code. |
| `AUDIT_2026-07-05.md` | Partially stale — several findings fixed |
| `CHANGELOG_AUDIT.md` | Covers 15 commits from June 29–July 2 only |
| `docs/CHANGELOG_SUMMARY.md` | Superseded by project-status.md and git log |
| `docs/changes-2026-07-01.md` | Single-session uncommitted changes list |
| `docs/proposed-system-workflow.md` | May not reflect current implementation |
| `docs/system-workflow.md` | May not reflect current implementation |
| `UI-UX-AUDIT.md` | Partially fixed — kept for remaining issues |
| `REDESIGN-AUDIT.md` | Partially fixed — spacing violations may remain |
| `CODEBASE-ANALYSIS.md` | Deep analysis now reflected in project-status.md |
| `API_ROUTES.md` | Move from root to archive (mirrors code) |
| `DESIGN-notion.md` | Design reference — archive, not actively updated |
| `DESIGN-SYSTEM.md` | Design reference — archive, not actively updated |
| `TEST_PLAN.md` | Historical test plan |
| `forecast-api/README.md` | Move to `docs/forecast-api/` for discoverability |
| `forecast-api/context.md` | Move to `docs/forecast-api/` for discoverability |
| `forecast-api/forecastingContext.md` | Move to `docs/forecast-api/` for discoverability |

### STALE / OUTDATED — Flag for Manual Review

| File | Issue |
|------|-------|
| `QA_REPORT_2026-07-10.md` | Valid as of July 10 but 163 tests now (was 137). Deploy/RBAC status may have changed. |
| `audit-cage-chicken-wishlist.md` | Lists 14 gaps — some covered in project-status.md, need cross-reference. |
| `audit-hen-placement.md` | Contains workflow analysis — specific unresolved items should be extracted and the rest archived. |
| `README.md` (root) | 59-line stub. **Should be rewritten** as proper project README with setup instructions. Currently provides almost no useful information. |

---

## 7. Unresolved Items — Proposed Additions to project-status.md

Based on cross-referencing all documentation files against `docs/project-status.md`, the following items are **not yet captured** and should be considered for addition:

### From `docs/egg-stock-architecture.md` §6 (Known Follow-Ups)

1. **Egg stock: `storeClassified()` missing `lockForUpdate` on read**
   - `EggStockController::storeClassified()` reads unsorted `egg_size_logs` records without `lockForUpdate`
   - Then decrements atomically — MySQL `unsigned` constraint prevents negative counts
   - Theoretical race: two concurrent classification operations could both read the same records
   - Safe but not elegant — add `lockForUpdate` to the read
   - **Proposed: Add to CAGES or EGG MANAGEMENT section as ⚠️ item**

2. **Egg stock: No low-stock alert generation**
   - System does not warn when a size's pool drops below a configurable threshold
   - Feed has this feature (`FeedBatch::lowStockAlert`), egg stock does not
   - **Proposed: Add to EGG MANAGEMENT section as ❌ item**

3. **Egg stock: No batch aging / expiry logic**
   - `EggStockBatch` has `freshness_status` attribute but no automatic flagging or removal of old stock
   - **Proposed: Add to EGG MANAGEMENT section as ❌ or ⚠️ item**

### From `UI-UX-AUDIT.md` / `REDESIGN-AUDIT.md`

4. **Spacing violations still present in 12+ views**
   - Non-Notion spacing (`p-5`, `gap-3`, `gap-5`) in mortality, feed, analytics, forecast, reports, environment, account, chickens, cages/bulk-add, cages/confirm-delete views
   - May be partially fixed; needs re-audit
   - **Proposed: Add to GENERAL section as ⚠️ item (cross-referencing original audit)**

### From `CODEBASE-ANALYSIS.md`

5. **Forecast model: static future covariates**
   - XGBoost uses last-observed temperature/humidity/feed/mortality for all future days
   - No weather forecast, no planned feed schedule, no projected mortality
   - **Proposed: Add to FORECAST section as ⚠️ item (algorithm limitation)**

6. **Forecast model: no recency weighting**
   - Training rows treated equally; older data at different flock age is as influential as yesterday
   - **Proposed: Add to FORECAST section as ⚠️ or ❌ item**

### From `QA_REPORT_2026-07-10.md`

7. **No RBAC branch merged to main**
   - The QA report notes RBAC work exists on a feature branch but has never been merged
   - project-status.md item 19 (RBAC) shows ✅ — this may be inaccurate if only basic admin/operator middleware exists without a full RBAC system
   - **Proposed: Verify current RBAC state. If only `EnsureAdmin` middleware exists (no roles/permissions tables, no permission checking), downgrade item 19 from ✅ to ⚠️.**

---

## 8. Summary Statistics

| Category | Count |
|----------|-------|
| Total documentation files found | 34 (13 root + 14 docs/ + 7 superpowers + 3 forecast-api) |
| Currently active / keep as-is | 5 |
| Superseded by project-status.md | 2 |
| Stale / partially stale | 13 |
| Archive (historical planning) | 14 |
| Unresolved items proposed for project-status.md | 7 new items + 1 status verification |

### Disposition Summary

| Action | Count |
|--------|-------|
| KEEP AS-IS | 5 |
| MERGE into project-status.md *(already done)* | 2 |
| ARCHIVE (move to docs/archive/) | 22 |
| STALE/OUTDATED (flag for review) | 4 |
| MOVE (relocate within forecast-api/) | 3 |
