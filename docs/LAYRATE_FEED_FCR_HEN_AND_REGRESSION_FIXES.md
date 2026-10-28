# LayRate Feed, FCR, Hen Inventory, and Regression Fixes

## Scope and safety

This remediation preserves imported production, feed/FCR, environmental, mortality, and aggregate hen snapshot records. Historical hen totals remain cage/day aggregates and are not treated as individual birds. No production database import, reset, system-time change, push, deployment, or hardware operation was performed. Database-writing feature tests used only `layrate_qa_20261027`.

Finance was not part of this work.

## Changes

- Dashboard production summaries use the Manila reporting date and selected-date hen snapshots. Cage Performance remains a selected-date comparison, and future dates stay clamped.
- Forecast/dashboard fixtures derive farm dates from `ReportingDateService` rather than UTC `now()`.
- Historical production reporting keeps aggregate imports distinct from slot-linked live production, avoiding placeholder-slot multiplication. Report rows and chart summaries use the imported cage/day facts while retaining descriptive provenance and aggregate hen snapshots.
- Feed filters are shared across Feed and FCR; FCR remains server-rendered for its selected tab, retains filters through requests, uses common feed/production windows, and provides explicit empty, incomplete, and latest-data states.
- The demo hen seeder is isolated to the approved QA schema or an explicitly suffixed `*_demo` database, refuses production and unexpected layouts/live inventory, and is idempotent. It creates mock inventory only; it does not convert historical snapshots into individual hens.

## Verification

All test commands below were run sequentially against the QA schema:

| Suite | Result |
|---|---:|
| `HistoricalCsvImportTest.php` | 1 passed, 57 assertions |
| `FeedReportSqlAggregationTest.php` | 3 passed, 17 assertions |
| `ReportControllerTest.php` | 28 passed, 121 assertions |
| `DashboardControllerTest.php` | 36 passed, 229 assertions |
| `FeedBatchManagementTest.php` | 38 passed, 126 assertions |
| `DemoHenInventorySeederTest.php` | 3 passed, 8 assertions |

The report suite emits existing PHPUnit warnings about deprecated doc-comment metadata. Browser verification was not performed because an authorized authenticated browser session was unavailable. The earlier 19/115 report cannot be reconciled to a retained matching log; the retained regression inventory covers a different larger run.

PHP syntax checks, `git diff --check`, and `npm run build` passed after the final edits.

## Remaining caveats

- Authenticated browser verification was unavailable.
- The historical 19/115 regression run has no retained matching log.
- Unrelated pre-existing worktree changes were preserved and excluded from the remediation commit.
