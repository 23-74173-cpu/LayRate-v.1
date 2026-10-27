# LayRate Final Defense Verification

**Assessment date:** October 27, 2026
**Defense date:** October 29, 2026
**Overall status:** **NOT YET VERIFIED — authenticated rehearsal and accessibility/parity checks remain**
**Finance:** Excluded from defense per client decision.

## Resolved regression failures

- Dashboard Cage Performance is confirmed as a selected-date snapshot. Replaced obsolete Week/Month/Full tests with deterministic selected-date tests for exact cage egg totals, daily HDEP, date labels, ranking, HDEP tie-break, cage filter, empty day, date changes/future clamp and legacy `days` parameter. Production calculations were unchanged. Historical period visualization remains in Production History. Updated `docs/dashboard-capstone-presentation.md` to match.
- Report query assertion now instruments `ReportController::buildSummary()` specifically and captures the full request separately. Production summary issues two intended queries: one combined aggregate and one active-hen count. No summary N+1 was observed. No threshold was blindly increased, and report values/formulas were unchanged.
- Details and historical root-cause context are in `docs/LAYRATE_REGRESSION_FAILURE_RESOLUTION.md` and `docs_dev/regression_failure_inventory.md`.

## Test results

All DB-writing PHPUnit runs used `layrate_qa_20261027`. `phpunit.xml` force-selects that schema and `Tests\TestCase` retains the fail-closed connection/schema guard. `layrate` and `layrate_testing` were not targeted.

| Suite | Result | Evidence |
|---|---:|---|
| `DashboardControllerTest` | 36 passed / 228 assertions | `/tmp/opencode/layrate-dashboard-selected-date.log` |
| `ReportSummaryConsolidationTest` | 6 passed / 24 assertions | `/tmp/opencode/layrate-report-query-measure-final.log` |
| Requested affected-class group (Dashboard, report, egg/feed/pre-order, FCR, markup, auth/user management) | 207 passed / 947 assertions | `/tmp/opencode/layrate-original-affected-final-defense.log` |
| Expanded requested group (+ Forecast and environment service coverage) | 220 passed / 962 assertions | `/tmp/opencode/layrate-expanded-calculation-final-defense.log` |
| Focused FCR, markup and authentication group | 41 passed / 221 assertions | `/tmp/opencode/layrate-final-focused.log` |

Expanded runs emitted 135 PHPUnit deprecations for doc-comment metadata. These warnings were not suppressed or bulk-converted. Historical actual production ends **2026-08-27**; later missing actuals are expected. Synthetic later dates were used only inside the isolated QA database. Forecasts remain separate from actuals.

## Browser and accessibility verification

- Latest browser attempt found `/login`. No credential submission or retry occurred. Authenticated workflows require the user to manually refresh the authorized session.
- Playwright CLI could not launch because its configured Chrome path `/opt/google/chrome/chrome` is missing. No package/system installation was performed. Existing `/usr/bin/chromium` and repository Playwright were used for logged-out-only checks.
- Logged-out evidence: reduced-motion preference matched; CSS `zoom: 2` proxy applied (not native browser zoom); keyboard Tab progressed email → password; zero page errors and zero HTTP >=400 responses. This does not verify authenticated pages.
- Representative authored contrast ratios: ink/white 16.48:1, muted/white 4.83:1, secondary/white 6.53:1, danger/white 8.12:1, white/navy 8.63:1. Dashboard chart tick gray `#9CA3AF`/white is 2.54:1; review for small-label contrast. This sample is not an exhaustive rendered audit.
- Dialog trapping/restoration, dense table keyboard/touch scrolling, selected-date interaction in browser, screen-reader announcements, chart/table data parity and reduced-motion active animation states were not freshly verified.
- Earlier authenticated 16-route/four-viewport and sampled keyboard/dialog evidence is documented in `docs/LAYRATE_AUTHENTICATED_BROWSER_QA_REPORT.md`, but was not repeated in this continuation.

## Defense workflow status

| Workflow | Status | Limitation |
|---|---|---|
| Dashboard historical production | BLOCKED | Needs refreshed authorized browser session; selected-date behavior is covered by deterministic tests. |
| Cage layout and hen inventory | BLOCKED | No authenticated route access this continuation. |
| Egg Logging and Recent Logs | BLOCKED | No authenticated route access; no writes made. |
| Egg Stocks and Pre-Orders | BLOCKED | No authenticated route access; no writes made. |
| Feed Management and FCR | PARTIAL | Deterministic FCR calculation tests pass; authenticated UI demonstration not performed. |
| Environment Monitoring | BLOCKED | No authenticated route access; no sensor/hardware operation. |
| Forecast | PARTIAL | Forecast tests pass; no authenticated forecast workflow or generate/import/reset action. |
| Reports and exports | PARTIAL | Report summary and export-related regression tests pass; no browser export/print. |

Finance remains intentionally excluded. No actual farm rows were fabricated, no development data was intentionally changed, no hardware was operated, and no commit/push/deploy occurred.

## Screenshots and evidence

- No authenticated screenshots were generated in this continuation because the session was not authenticated.
- Prior screenshot artifacts and route matrix are referenced in `docs/LAYRATE_AUTHENTICATED_BROWSER_QA_REPORT.md` and `/tmp/opencode/layrate-*.png`.
- Fresh test logs are listed in the test-results table above.

## Remaining issues and readiness

1. Manual authorized browser refresh needed for all eight requested workflows and dashboard selected-date interaction.
2. Complete native 200% zoom, keyboard/touch scrolling, dialog focus lifecycle, screen reader/live status, reduced-motion active states and chart/table parity.
3. Investigate/fix or document low contrast of chart tick gray `#9CA3AF` on white after validating rendered size/weight and background.
4. Complete actual-data parity against records through 2026-08-27; keep forecasts distinct and Finance excluded.
5. 135 PHPUnit metadata deprecations remain.

**Assessment:** Regression failures requested in this continuation are resolved and the expanded requested PHPUnit selection passes. The final defense is **NOT YET VERIFIED** until authenticated workflow rehearsal, complete accessibility checks and relevant live-data parity are completed.

---

## Continuation run: October 27, 2026 (Windows dev machine)

Run on a Windows/XAMPP checkout at `add8f0c`. Nothing was committed or pushed. `layrate`, `layrate_demo` and `layrate_testing` were not written to. PHPUnit used `layrate_qa_20261027`. Browser checks used a disposable copy of `layrate` (`layrate_qa_browser_20261027`, copied with `mysqldump --single-transaction`, then migrated) and two QA-only accounts that exist only in that copy, served on `127.0.0.1:8010` with sessions, cache and queue kept inside the copy. No hardware, relay or system-time action was taken. Finance was not opened.

### Fixed in this run (uncommitted, needs review)

1. **A failed sign-in showed no message.** In Chromium, a wrong password, an unknown email, a server validation error and the rate-limit lockout all reloaded a blank login form: no message, and the typed email was gone. The cause below does not depend on the browser; Firefox and WebKit were checked after the fix. Cause: Laravel shows a flashed error once. The login script's `fetch()` followed the redirect, which used up the error, and then loaded `/login` a second time with `location.replace()`. The earlier JS fault fixes did not touch this path, and PHPUnit cannot see it because it does not run the script. Fix in `resources/views/auth/login.blade.php`: on that path the script reads the message from the page it already fetched (`[data-login-error]`) and shows it in the existing alert, clears the password and keeps the email. The alert also gets `tabindex="-1"`, so the script's existing `focus()` call works (focus was falling to the page body). Regression test: `test_failed_login_message_is_shown_once_and_marked_for_the_login_script`.
2. **The test schema guard ran after the wipe.** `Tests\TestCase::setUp()` checked the schema after `parent::setUp()`, but `RefreshDatabase` runs `migrate:fresh` inside `parent::setUp()`. Shown on a throwaway schema: a sentinel table was dropped, and only then was "Refusing RefreshDatabase" printed. A cached config (`php artisan config:cache`, which the Pi deploy runs) overrides `phpunit.xml`, so on a dev machine with a cached config a test run would wipe the database the cache names. The check also skipped itself unless `APP_ENV` was `testing`, and a cached config changes that too. The check now runs in `createApplication()`, before any database work, in every environment. It cannot live in a `RefreshDatabase` hook: 27 test classes re-import that trait, and a re-imported trait method replaces the parent's. Same throwaway proof after the fix: refused, sentinel kept, for a plain test class and for one that re-imports the trait. Regression test: `tests/Feature/TestDatabaseGuardTest.php`.

### Results

| Area | Result | Notes |
|---|---|---|
| `AuthControllerTest`, `UserManagementTest`, `TestDatabaseGuardTest` | 33 passed / 165 assertions | `layrate_qa_20261027` |
| Full suite | 603 passed, 36 failed / 2,484 assertions | `layrate_qa_20261027`. The same 36 fail with the original `tests/TestCase.php`, so they predate this run. All are in 8 classes outside the 220-test selection: `AnalyticsControllerTest`, `EnvironmentReferenceCardTest`, `EnvironmentThresholdTest`, `FarmLayoutRemoveCellTest`, `GateAdminTest`, `MassAssignmentSafetyTest`, `RelayControlTest`, `SensorIngestionTest`. |
| Login, Chromium | PASS | Labels, autofocus, Tab order; empty submit blocked with 0 requests; server validation, wrong password, unknown email (same message as wrong password), rate limit; abort, 500, 503, 15 s timeout, 419 reload; one POST on double submit; Enter submits; remember-me cookie set and cleared on sign-out; Back after sign-in stays in the app; Back after sign-out stays on login. |
| Login, Firefox and WebKit | PASS | Wrong password then recovery, abort, Enter submits, Back after sign-in, sign-out then Back. |
| Eight journeys, read-only: 11 pages at 1280 px (admin) and 390 px (operator) | PASS | All HTTP 200, with no console errors, page errors, failed requests or page-level horizontal overflow. Forecast sends operators to the Dashboard (admin-only by design), so present Forecast signed in as an admin. No Finance link for either role. |
| Data parity, sample date 2026-08-27 | PARTIAL | See below. |

Two first-run failures were test-script issues and were rerun: an autofocus read taken too early, and pressing Enter on the Remember me checkbox in WebKit (WebKit does not submit from a checkbox).

### Data parity: 2026-08-27, the last actual date

Actual production rows run from 2026-03-23 to 2026-08-27 (20,856 rows), with none after the cutoff. Figures were read after picking the date in the Dashboard FROM and TO fields.

| Shown | Expected (SQL for that day) | Result |
|---|---|---|
| Eggs Today 472, "▲ 3 vs yesterday" | 472 (469 on 2026-08-26) | Match |
| Lifetime Eggs 74,990 | 74,990 | Match |
| Cage table eggs 217 / 132 / 123, HDEP 90.4% / 91.7% / 85.4% | Same | Match |
| Total Hens 471; cage table Hens 221 / 125 / 125 | 528; 240 / 144 / 144 on that day | Shows today's hens |
| Today's HDEP 100.2% | 472 / 528 = 89.4% | **Wrong** |
| HDEP "▲ 11.4% vs yesterday" | 89.4 vs 88.8 = +0.6 points | **Wrong** |

Cause: `DashboardController` (all-cages branch near line 1471, per-cage branch near line 1445) divides the picked day's eggs by the hens active now, and compares with yesterday's average of the stored slot HDEP values. Across all 158 actual days the card would show over 100% on 122 days (highest 103.6%), about 11 points high on average, because the flock had 528 hens then and has 471 now. Not changed in this run: it is a production formula and needs the team's decision. Proposed fix: for a past date, use that day's logged hens (`SUM(hen_count)` of its production logs) for Total Hens and HDEP, compute yesterday the same way, and keep the current behavior for today.

### Still open

- Chart tick and axis-title gray `#9CA3AF` (2.54:1 on white) is hard-coded in 21 settings across 12 `resources/views/dashboard/_*.blade.php` files, at 9 to 10 px. Proposed: `#6B7280` (4.83:1, the muted text color already used elsewhere). Not changed: it is a visual change right before the defense.
- The 36 full-suite failures above (mostly expected-markup assertions, plus layout, mass-assignment and sensor-alert expectations) were not investigated in this run.
- Not covered in this run: native 200% zoom, screen reader, dialog focus cycle, reduced-motion states, and parity beyond the sample above.

**Assessment (this run):** still **NOT READY**. Login is fixed and verified in three browsers, pending review and commit. The HDEP card on past dates is the main open defense risk.
