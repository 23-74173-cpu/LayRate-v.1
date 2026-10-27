# Original targeted regression failure inventory (2026-10-27)

Original source: `/tmp/opencode/layrate-targeted-phpunit-rerun.log` — 151 passed / 31 failed (660 assertions). After extensions and corrections, current expanded requested suite `/tmp/opencode/layrate-expanded-calculation-final-defense.log` passes 220 tests / 962 assertions. Dashboard selected-date tests and summary-only report instrumentation resolved the six historically reported failures. Overall defense readiness remains NOT YET VERIFIED because browser workflows/accessibility/parity are incomplete.

## Original 31 failures

| # | Test class::method | Original expected vs actual / exception | Root cause and current status |
|---:|---|---|---|
| 1 | `EggReportingAndHistoryTest::recent_logs_combined_filters_narrow_results` | Expected one row matching old exact `<tr class=...` string; found 0 | Assertion used obsolete markup; changed to stable `mobile-log-row`. Corrected; class passes. |
| 2 | `EggReportingAndHistoryTest::override_then_sensor_events_count_up_from_override_without_audit_inflation` | Expected count 2; actual 0 | First cumulative reading is baseline, not trigger. Fixture uses baseline, true delta and monotonic readings. Corrected; class passes. |
| 3 | `EggReportingAndHistoryTest::override_to_zero_then_sensor_event_counts_up_from_zero` | Expected count 1; actual 0 | Fixture now uses override slot and persisted baseline; first sensor reading remains baseline-only. Corrected; class passes. |
| 4 | `EggReportingAndHistoryTest::sensor_event_migration_path_creates_persisted_event_and_production_log` | Expected sensor event 1; actual 0 | First reading intentionally baseline-only. Fixture stores baseline 0 then sends true delta. Corrected; 1 test / 3 assertions passed. |
| 5 | `EggStockPoolTest::store_rejects_over_limit` | Expected rejection; actual 200 via reclassification | Controller explicitly auto-reclassifies eligible unsorted `EggSizeLog` rows; UI offers classification. Test aligned to contract; class passes. |
| 6 | `EggStockPoolTest::store_rejects_when_pool_exhausted` | Expected 422; actual 200 | Fixture pool/reclassification state did not match controller path. Corrected to exercise intended contract; class passes. |
| 7 | `EggStockPoolTest::update_rejects_increase_exceeding_available_pool` | Expected JSON 422; got 302 with session error | HTML form route redirects and reopens edit modal on validation. Test aligned to route contract; class passes. |
| 8 | `FeedBatchManagementTest::batch_code_auto_generates_sequential` | Expected 6 codes; got 3 | Default deployment-safe seeder creates no feed batches; test now counts the three it creates. Corrected; passes. |
| 9 | `FeedBatchManagementTest::low_stock_alert_created_when_threshold_crossed` | Expected remaining 5; actual 50 | Request omitted required `log_time`; valid fixture time supplied. Corrected; passes. |
| 10 | `FeedBatchManagementTest::low_stock_alert_dedup_same_day` | Expected one alert; actual zero | Request fixtures omitted required `log_time`; corrected. Passes. |
| 11 | `FeedBatchManagementTest::low_stock_alert_created_on_subsequent_day` | Expected one alert; actual zero | Same missing required time in subsequent requests; corrected. Passes. |
| 12 | `FeedBatchManagementTest::store_consumption_without_time_treats_it_as_unspecified` | Expected stored row; table empty | Controller and visible `type=time` input require time; test asserts validation rejection and no row. Required-time contract confirmed; passes. |
| 13 | `FeedBatchManagementTest::destroy_consumption_deletes_log` | Expected row before delete; got null | Creation fixture omitted required `log_time`; corrected. Passes. |
| 14 | `FeedBatchManagementTest::store_batch_via_http_persists_all_fields` | Expected success; formerly undefined `App\Services\bcmul()` | Passes with standard CLI BCMath enabled. |
| 15 | `FeedBatchManagementTest::update_batch_via_http_updates_fields` | Expected success; formerly undefined `bcmul()` | Passes with standard CLI BCMath enabled. |
| 16 | `FeedBatchManagementTest::whole_farm_distribution_uses_largest_remainder_for_rounding` | Earlier undefined array key `id` | Current valid time/stock fixture passes. Resolved. |
| 17 | `FeedBatchManagementTest::farm_entry_omitting_unit_cost_falls_back_to_batch_cost` | Earlier entry missing | Request omitted required `log_time`; corrected. Passes. |
| 18 | `FeedBatchManagementTest::zero_hen_cage_is_excluded_from_distribution` | Earlier undefined array key `id` | Current run passes with valid request/distribution setup. Resolved. |
| 19 | `FeedBatchManagementTest::all_active_cages_zero_hens_creates_no_logs` | Earlier expected farm entry, got zero | Current run passes; farm entry can exist without cage distribution rows. Resolved. |
| 20 | `FeedBatchManagementTest::update_farm_feed_entry_cascades_to_distributed_rows` | Earlier expected updated total 60, got 100 | Corrected valid-time setup; current run passes. |
| 21 | `FeedBatchManagementTest::delete_farm_feed_entry_cascades_to_distributed_rows` | Earlier entry had no related logs | Corrected farm-entry request; current run passes. |
| 22 | `FeedBatchManagementTest::distributed_row_cannot_be_edited_or_deleted_directly` | Earlier undefined array key `id` | Current run passes. Resolved. |
| 23 | `FeedReportSqlAggregationTest::production_report_rows_match_the_old_grouping` | Grouped values matched, same-day order differed | Oracle now models deterministic ordering by each group’s first log ID, without production-query changes. Passes. |
| 24 | `ForecastBaselineTest::current_calendar_exports_download_valid_csv_excel_and_pdf` | XLSX assertion failed | Passes with standard CLI ZipArchive enabled. |
| 25 | `PreOrderTest::pool_validation_rejects_over_limit` | Expected session error missing | Pre-orders consume stocked eggs, not raw production; fixture stocks 30 and requests 51. Corrected; passes. |
| 26 | `PreOrderTest::concurrent_orders_do_not_over_commit` | Available stock was 0 despite ProductionLog(100) | Pool is `EggStockBatch` minus pending orders; fixture now stocks 100. Corrected; passes. |
| 27 | `PreOrderTest::requested_date_allows_past_dates` | Date accepted assertion failed due empty pool | Fixture now stocks 50. Corrected; passes. |
| 28 | `PreOrderTest::pool_data_endpoint_returns_pools` | Expected large=100/small=50; large=0 | Endpoint sums stocked batches; fixture stocks both sizes. Corrected; passes. |
| 29 | `ReportControllerTest::egg_stock_report_all_cages_includes_null_cage_batches` | Expected visible `1332` | Output formats total as `1,332 eggs`; All Cages includes null-cage batches. Assertion corrected; passes. |
| 30 | `ReportControllerTest::pdf_export_via_json_post_embeds_the_posted_chart_image` | Expected extra image marker; actual unchanged | PDF chart image behavior is gated on GD; GD absent. Environment blocker, not a calculation defect. |
| 31 | `ReportControllerTest::all_report_type_excel_export_with_charts_embeds_a_drawing_on_every_sheet` | XLSX parse failed | Passes with standard CLI ZipArchive enabled. |

## Exact remaining failures in the complete affected-class run

| Exact test | Expected | Actual / exception |
|---|---|---|
| `FeedBatchManagementTest::store_batch_via_http_persists_all_fields` | Session success and persisted batch | Undefined `bcmul()` at `app/Services/FinancePostingService.php:374` |
| `FeedBatchManagementTest::update_batch_via_http_updates_fields` | Session success and updated batch | Same undefined `bcmul()` |
| `ForecastBaselineTest::current_calendar_exports_download_valid_csv_excel_and_pdf` | XLSX archive includes expected members | `Class "ZipArchive" not found` in PhpSpreadsheet |
| `ReportControllerTest::all_report_type_excel_export_with_charts_embeds_a_drawing_on_every_sheet` | XLSX parses and each sheet has drawing | `Class "ZipArchive" not found` in PhpSpreadsheet |

## Additional failures in expanded calculation sweep — resolved (not original 31)

The historical expanded run reported 184 passed / 6 failed (840 assertions). All six have been resolved in tests/documentation without changing production formulas or query thresholds:

| Exact test | Expected | Actual | Assessment |
|---|---|---|---|
| Five former Dashboard period methods | Week/Month/Full Cage Performance | Selected-date daily snapshot | Replaced by exact selected-date tests for totals, HDEP, labels, ranking/tie, cage filter, empty day, date update/future clamp and legacy period parameter. `DashboardControllerTest`: 36 passed / 228 assertions. |
| `ReportSummaryConsolidationTest::production_summary_fields_are_all_correct` | Summary values and at most 13 full-render queries | Values matched; complete request includes non-summary SQL | Summary method now directly scoped: exactly 2 queries (one combined production aggregate plus active hen count); full request captured separately. No summary N+1 found. `ReportSummaryConsolidationTest`: 6 passed / 24 assertions. |

Fresh rerun evidence: requested affected-class group `/tmp/opencode/layrate-original-affected-final-defense.log` — 207 passed / 947 assertions; expanded requested group `/tmp/opencode/layrate-expanded-calculation-final-defense.log` — 220 passed / 962 assertions. Both emitted 135 existing PHPUnit doc-comment metadata deprecations.

The user confirmed selected-date Cage Performance as authoritative. `docs/dashboard-capstone-presentation.md` now assigns historical periods to Production History. The report test scopes instrumentation to `buildSummary()` instead of including full-page auth/layout/render queries. Full expanded request selection passes 220 tests / 962 assertions, with 135 existing metadata deprecations.

## Data cutoff and extension evidence

Historical actual production ends **2026-08-27**, per `docs/data-check-report.md` and `database/seeders/HistoricalFarmDataSeeder.php`. September/October actual-data gaps are intentional; synthetic later dates remain QA-only. Forecast values must not be represented as actuals.

Current standard PHP 8.5.11 (`/usr/bin/php`) has no startup warnings; `php -m` lists `bcmath`, `zip`, `gd`; boolean checks for `extension_loaded('bcmath')`, `class_exists('ZipArchive')`, and `extension_loaded('gd')` all return true. `php --ri` confirms all enabled (Zip 1.22.8, GD 2.3.3). User reports manually enabling/installing them; this agent made no system changes. Fresh final previously-blocked subset including PDF chart image: 5 passed / 40 assertions (`/tmp/opencode/layrate-extensions-enabled-targeted-final.log`). Complete affected classes: 123 passed / 530 assertions. Actual data cutoff remains 2026-08-27; no development records changed.

## Login submission issue (additional investigation)

`AuthController::login()` already flashes errors for invalid credentials, required-field validation, and rate limiting; the login Blade renders that session error bag. The defect was client-side: on rejected fetch/HTTP error, the login script fell back to `form.submit()`, causing a duplicate native submission that bypassed the JS/native validation and could replace the page with a raw error response. It also had no timeout, so the disabled “Signing in…” button could persist on a stalled request. The catch path is now in-page feedback only; network/server errors and a 15-second timeout restore the button and clear the submit lock. CSRF, throttling, and session regeneration are preserved.

Verification: `AuthControllerTest|UserManagementTest` **31 passed / 157 assertions** (`/tmp/opencode/layrate-login-auth-regression.log`). A TDD markup hook failed before the fix and passed after. Controlled Playwright 503 and network-abort checks both displayed feedback, restored the enabled “Sign In” button, made exactly one POST, and produced no page errors. No credentials, cookies, CSRF tokens or real authentication submissions were recorded.
