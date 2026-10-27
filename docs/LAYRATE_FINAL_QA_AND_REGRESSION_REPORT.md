# LayRate Final QA and Regression Report

**Assessment date:** 2026-10-27
**Target:** Local development checkout only (`/home/joed/LayRate-Main`)
**Defense date:** October 29, 2026
**Overall defense readiness:** **NOT YET VERIFIED**
**Finance scope:** UX-005 is **NOT APPLICABLE — INTENTIONAL CLIENT DECISION** for the defense.

## Executive assessment

Prior authenticated browser evidence remains available for 16 pages at four viewport sizes and sampled keyboard/dialog interactions. Current Dashboard and report query-measurement failures are resolved: selected-date tests pass, and summary aggregation measures two queries independently from the full request. The expanded requested test selection passed 220 tests / 962 assertions. Readiness remains **NOT YET VERIFIED** because fresh authenticated workflows, native zoom, complete accessibility and data parity are outstanding.

## Safety and test database isolation

### Database findings and safeguards

| Item | Status | Evidence |
|---|---|---|
| Development database | PASS — identified, not modified | `.env` and Laravel runtime config resolve MySQL on `127.0.0.1:3306`, schema `layrate`; username root. No destructive or write query was issued to it. |
| Existing `layrate_testing` | PASS — preserved | Schema exists with 40 tables, 86 migration records and 16 settings rows. It was not assumed disposable and was left unchanged. |
| Disposable test schema | PASS — schema-isolated | `layrate_qa_20261027` is distinct on same MariaDB host. Information schema showed 40 app tables, 86 migrations; users/cages/hens/production_logs empty; settings had 16 rows. |
| PHPUnit target | PASS — explicit | `.env.testing` supplies testing, MySQL, host, port, approved schema, username/password, blank URL/socket and test-only app key. Tracked `phpunit.xml` force-selects connection/host/port/schema/username and blank URL/socket; secrets/key are not stored in tracked XML. Force values prevent inherited shell DB variables from redirecting the suite. |
| Destructive setup guard | PASS — negative-tested | `Tests\TestCase::setUp()` checks testing environment, connection, exact schema, local host/port, blank DB URL and actual connected schema before Laravel's `RefreshDatabase` trait runs. Running with `APP_ENV=testing DB_DATABASE=layrate` was rejected with “Refusing RefreshDatabase” before setup. A read-only query afterward confirmed the development schema's users/cages/hens/production_logs tables remained present. |
| Credential least privilege | PARTIAL | Local root DB credentials are used. A restricted schema-specific test user was not configured. The guard/schema separation protects against common misconfiguration, but is not credential-level isolation. |

### Exact safeguard details

- Tracked PHPUnit config force-selects `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=layrate_qa_20261027`, `DB_USERNAME=root`, and empty `DB_URL`/`DB_SOCKET`. The ignored local `.env.testing` supplies DB password and test-only APP_KEY; secrets are not stored in tracked PHPUnit XML.
- The application test base rejects any non-approved test connection before `RefreshDatabase` setup. The separate `DbSanityCheckTest` checks configured and connected schema and host/port/URL.
- A negative test attempt against `layrate` exited 2 with guard rejection. It did not begin migrations. Do not run DB-backed tests under a command/config that bypasses PHPUnit XML or `Tests\TestCase` without a corresponding verified safety gate.
- No drop, truncate, reset, migrate:fresh, destructive browser action, actual farm record write, hardware operation, system-time change, commit, push or deployment was performed.

## Tests executed

| Test group | Result | Notes |
|---|---|---|
| `DbSanityCheckTest` | **PASS — 1 test / 6 assertions** | Confirms approved isolated test target. |
| `FcrCalculationTest` | **PASS — 12 tests / 32 assertions** | Deterministic fixture calculations including egg mass, FCR, timeline aggregation and empty inputs. |
| Core service units (`EnvironmentStatusServiceTest`, `ReportingDateServiceTest`, `FcrStatusServiceTest`, `OptimalRangeAndDisplayDateTest`) | **PASS — 32 tests / 59 assertions** | No `RefreshDatabase` risk for these unit classes. |
| Targeted broader regression batch | **PARTIAL/FAIL — 151 passed / 31 failed (660 assertions)** | Included egg reporting, egg size/stock, pre-orders, feed, cage position, forecast and reports. This result prevents a green-suite claim. Full output: `/tmp/opencode/layrate-targeted-phpunit-rerun.log`. |
| Focused affected classes (latest) | **PASS — 47 tests / 201 assertions** | `EggStockPoolTest`, `PreOrderTest`, `EggReportingAndHistoryTest`, `FeedReportSqlAggregationTest`; `/tmp/opencode/layrate-business-focused-rerun8.log`. Does not cover all affected classes or extension-dependent cases. |
| Extension verification | **PASS** | Standard `php -v` has no startup warnings; `php -m` lists BCMath, ZIP and GD; extension booleans true; `php --ri` reports all enabled. No temporary flags. |
| Login/auth regression suite | **PASS — 31 tests / 157 assertions** | `AuthControllerTest|UserManagementTest`; covers successful/failed login, validation, throttle, session and user management. `/tmp/opencode/layrate-login-auth-regression.log`. |
| Login failure browser checks | **PASS — controlled faults** | Synthetic 503 and network abort each produced visible feedback, restored enabled button, exactly one POST, and no page errors. No real credentials/session values captured. |
| Dashboard selected-date suite | **PASS — 36 tests / 228 assertions** | Daily totals/HDEP, date labels, ordering/tie, cage filtering, empty date, date change/future clamp and ignored legacy period parameter. `/tmp/opencode/layrate-dashboard-selected-date.log`. |
| Report summary consolidation | **PASS — 6 tests / 24 assertions** | Scoped query measurement: 2 summary queries (combined aggregate + active-hen count), independently from full-request SQL. No summary N+1 found. `/tmp/opencode/layrate-report-query-measure-final.log`. |
| Requested affected-class suite | **PASS — 207 tests / 947 assertions** | Dashboard, report, affected classes, FCR, markup and auth. `/tmp/opencode/layrate-original-affected-final-defense.log`. |
| Expanded requested suite | **PASS — 220 tests / 962 assertions** | Adds Forecast and EnvironmentStatusService coverage. 135 existing metadata deprecation warnings. `/tmp/opencode/layrate-expanded-calculation-final-defense.log`. |
| Previously blocked test subset | **PASS — 5 tests / 40 assertions** | Fresh final rerun: Feed Batch create/update, Forecast XLSX, Report XLSX and PDF chart image; `/tmp/opencode/layrate-extensions-enabled-targeted-final.log`. |
| Complete original affected-class batch | **PASS — 123 tests / 530 assertions** | Seven affected classes; `/tmp/opencode/layrate-full-affected-extensions.log`. |
| Previous expanded sweep (historical) | **SUPERSEDED** | 184 passed / 6 failed reflected obsolete Dashboard period assumptions and full-request report query counting; resolved in the current suite above. |
| Guard-negative test | **PASS** | Forced target `layrate` rejected before DB setup/migration. |
| Final focused markup suite | **PASS — 12 tests / 69 assertions** | Fresh run; PHPUnit reports 135 remaining metadata deprecations. |
| Final combined DB sentinel + FCR | **PASS — 13 tests / 38 assertions** | Fresh run against isolated guarded schema. |
| `git diff --check` | **PASS** | Fresh run during final verification. |
| Tailwind and Blade checks | **PASS** | Fresh `npm run build`, `php artisan view:cache`, `php artisan view:clear`. |

### Failed batch triage and resolution progress

The original batch included:

- Missing PHP extensions: `bcmul()`/BCMath in feed-to-finance posting, and `ZipArchive` in spreadsheet export tests.
- `FeedReportSqlAggregationTest` reports row-order disagreement between legacy expected rows and current SQL result.
- Sensor ingestion/event assertions observed no expected increments in `EggReportingAndHistoryTest`.
- `EggStockPoolTest` expected JSON 422 but received 200 for some requests, and HTML redirect 302 for an update path.
- `PreOrderTest` deterministic pool scenarios reported zero stock, producing overflow/session errors.
- `FeedBatchManagementTest` CRUD/low-stock fixtures failed expectations, including missing entries/logs and batch-count discrepancy.
- `ReportControllerTest` had a stocked-total assertion mismatch and a PDF chart-image assertion mismatch, besides missing ZipArchive.

PHP diagnostic: standard PHP CLI 8.5.11 `/usr/bin/php` now loads BCMath, ZIP and GD with no startup warnings. `extension_loaded('bcmath')`, `class_exists('ZipArchive')`, and `extension_loaded('gd')` all return true; `php --ri` confirms enabled modules (Zip 1.22.8, GD 2.3.3). Environment changes were performed manually by the user; this agent did not edit system config or install packages.

Login investigation found and fixed a client-side recovery defect: the prior catch block fell back to `form.submit()` on network/non-2xx errors, bypassing validation/interception, while a hung request had no timeout and could leave the button disabled. Failed auth/rate-limit redirects still render Laravel’s flashed error banner; server errors, network failures and timeout now show in-page feedback and restore the button. CSRF, throttling and session regeneration remain unchanged. See the resolution report for controlled browser evidence.

Investigation confirmed stale assumptions around default feed seeding, required `log_time`, markup, sensor baselines, stocked pre-order inventory and SQL same-day order; those affected classes pass. The user confirmed selected-date Cage Performance, so five obsolete period tests now assert daily contract while historical windows remain in Production History. Report summary measurement is scoped directly: two intended queries; full-request SQL is independently captured. No calculations changed and no query threshold was increased.

## Calculation and data parity

**PARTIAL.** `FcrCalculationTest` passed (12 tests / 32 assertions); expanded run also passed FCR, forecast async/backtest, egg stock, pre-order, egg report and SQL report aggregation cases. **BLOCKED/NOT VERIFIED** for end-to-end egg production/HDEP parity, stock balances against UI, feed consumption/FCR against dashboard, mortality/inventory, actual-vs-forecast chart parity, and comprehensive report filters. The actual historical production dataset intentionally ends **2026-08-27** (documented in `docs/data-check-report.md` and `HistoricalFarmDataSeeder.php`). Empty later historical windows are not defects. Synthetic later dates are permitted only in isolated QA tests. Forecast values must remain clearly labeled and separate from actual measurements. No development data was changed.

## Browser and responsive verification

- Prior authenticated read-only matrix: 16 applicable pages returned HTTP 200 in Chromium at 360×800, 390×844, 768×1024 and 1280×900, with no document/body horizontal overflow; sampled console/network checks had no JS/page errors or >=400 responses. No Finance route was accessed.
- Prior cage-grid fix: at 360px, natural tile scale and an approximately 1044px horizontal pan range were observed; no layout was saved.
- Prior keyboard subset: Dashboard/Feed/Hens/Profile tab controls, cage-info dialog Escape/focus restoration and Stocks filter-sheet initial focus/Escape were sampled. Physical keyboard/touch pan to final table column and back to first was not fully validated.
- Current authorized state: fresh attempts at Dashboard, Cages, Feed, Hardware, Stocks, Pre-Orders, Forecast, Reports, Egg Logging redirected to `/login`. No new authenticated page evidence was produced. Artifacts: `/tmp/opencode/layrate-zoom-readonly-results.json`, `/tmp/opencode/layrate-accessibility-readonly.json`.
- Browser engine installed locally: Chromium 149.0.7827.55. Existing binary was used; no browser reinstall. The attempted `documentElement.style.zoom=200%` was merely a CSS zoom proxy and ran only after auth check; it is not native 200% browser zoom and did not produce authenticated layout results. Native zoom: **BLOCKED**.
- No short-height dialog stress test, read-only filter/history workflow expansion, browser navigation/Turbo history regression, preview-vs-chart data parity, or delete review/back/cancel browser test was performed in this continuation.

## Accessibility verification

| Area | Status | Evidence / limitation |
|---|---|---|
| Keyboard sequence/focus | PARTIAL | Prior selected tabs/dialogs verified only; no complete sequential Tab/Shift+Tab audit, true-tab Home/End/arrow coverage across all widgets, keyboard scroller reachability or all hidden destinations. |
| Forms/semantics | PARTIAL | Prior DOM audit across 16 pages found no visible unnamed text/select/textarea controls and no sampled duplicate default-state IDs. Dynamic dialogs, error descriptions, live validation and label activation not exhaustively tested. |
| Screen reader | NOT TESTED | No actual screen reader was available/used; no accessibility-tree snapshot captured in this run. Do not claim screen-reader support verified. |
| Contrast | PARTIAL — representative authored pairs | Logged-out Chromium ratios: ink `#1f1f1f`/white 16.48:1; muted `#6b7280`/white 4.83:1; secondary `#615d59`/white 6.53:1; danger `#9b1c24`/white 8.12:1; white/navy `#0d47a1` 8.63:1. Dashboard tick gray `#9ca3af`/white is 2.54:1 (below 4.5:1 small text). Exhaustive rendered audit remains outstanding. |
| Reduced motion | PARTIAL | Fresh logged-out Chromium confirmed reduced-motion preference on login; source audit found dashboard reduced-motion CSS and shared chart renderer handling. Authenticated active states were not inventoried. |
| Native zoom | BLOCKED | No native browser 200% zoom evidence. |

## PHPUnit metadata deprecations

- Initial inventory found **135 doc-comment metadata occurrences in 11 project test files**.
- Converted 13 `/** @test */` annotations in `tests/Unit/Services/EnvironmentStatusServiceTest.php` to `#[Test]`; test meanings and assertions unchanged.
- Other affected feature tests retain annotations, including provider metadata where present. They were not bulk-converted because broad mechanical edits risk changing discovery/provider semantics and were not needed to diagnose the immediate regressions.
- Fresh markup and combined suite runs report **135 PHPUnit metadata deprecations** after this narrow conversion. **Status: PARTIAL; cleanup incomplete.** No dependency upgrade.

## Confirmed remediation changes / regressions

Previously confirmed and fixed: mobile cage grid collapsed to an unusable shrunken width; missing accessible names on Notes/Profile/Hens/Environment/generated date controls; and cage-info dialog focus lifecycle integration. Browser evidence for these is documented in `docs/LAYRATE_AUTHENTICATED_BROWSER_QA_REPORT.md` and `/tmp/opencode` artifacts. The newly exposed test failures are untriaged and must not be labeled remediation regressions yet. No new product-code fix was made during this continuation.

## Finance exclusion

UX-005 remains **NOT APPLICABLE — INTENTIONAL CLIENT DECISION** for the October 29 defense. Finance was not visited or added to defense navigation. Its routes, permissions, records and functionality remain out of scope for changes and must be preserved.

## Remaining blockers and readiness decision

1. Obtain refreshed authorized Playwright state and complete native 200% zoom/reflow, short-height dialog, full keyboard/scroller interaction and safe functional browser checks.
2. Perform screen-reader smoke tests (Dashboard, Egg Logging, Stocks, Feed/FCR, Forecast and dialogs) and rendered contrast calculations.
3. Obtain manually refreshed authorized browser state and complete read-only defense workflows, native 200% zoom/reflow, dense-table keyboard/touch scrolling, dialogs, chart/table parity and screen-reader checks. Do not retry credentials.
4. Review chart-tick contrast finding and conduct exhaustive rendered contrast checks, including status tones, overlays and text sizes.
5. Establish remaining live-data parity for stock pools, forecast ranges, dashboard/report totals, mortality/removal and inventory. Honor actual cutoff 2026-08-27 and distinguish forecasts from measurements.
6. Convert remaining PHPUnit metadata in a separately scoped change without changing provider semantics.
7. Validate defense workflow, role matrix and sensor status with authorized owner/environment. Keep Finance excluded.

**Final conclusion: NOT YET VERIFIED.** Requested regression suites are green at 220 tests / 962 assertions. Fresh authenticated browser verification, full accessibility, live-data parity and defense workflow rehearsal remain incomplete. Playwright CLI could not launch at its configured Chrome path; existing Chromium supported logged-out checks only. Development data and hardware were not intentionally touched.

## Related evidence

- `docs/LAYRATE_AUTHENTICATED_BROWSER_QA_REPORT.md`
- `docs/LAYRATE_UI_UX_REMEDIATION_EXECUTION_LOG.md`
- `docs/LAYRATE_UI_UX_REMEDIATION_VERIFICATION_MATRIX.md`
- `docs/LAYRATE_UI_UX_DEFENSE_READINESS.md`
- `/tmp/opencode/layrate-targeted-phpunit-rerun.log`
- `/tmp/opencode/layrate-zoom-readonly-results.json`
- `/tmp/opencode/layrate-accessibility-readonly.json`
