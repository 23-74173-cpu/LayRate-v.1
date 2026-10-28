# Task: Feed/FCR, Demo Hens, and Regression Fixes

## Phases
- [x] Inspect reports, worktree, PHPUnit target guard, and original test selection/logs.
- [x] Reproduce and classify the original Dashboard/Report failures; fix confirmed root causes.
- [x] Diagnose and improve Feed/FCR filters and empty/error states with regression coverage.
- [x] Add isolated, idempotent demo hen inventory seeder and QA tests/docs.
- [x] Run the targeted test/build/syntax checks; document evidence and limitations.

## Decisions
| Decision | Rationale | Date |
|---|---|---|
| No development DB writes and no historical reimport | Explicit user safety constraints | 2026-10-28 |
| Use QA schema only for DB-writing tests | Task requires fail-closed isolation | 2026-10-28 |
| Treat historical hen totals as cage/day snapshots, not bird identities | Source CSV provides aggregate hen counts only | 2026-10-28 |

## Errors Encountered
| Error | Attempt | Resolution |
|---|---|---|
| Current Dashboard/Report run produced 13 failures (102 pass) | Ran guarded current feature classes | Classify specific failures; further inspect existing code/current edits before fixing |
| `HistoricalCsvImportTest` uncovered report row and chart aggregation differences | Reproduced with isolated QA test | Separate imported cage/day aggregate records from placeholder-slot logs; preserve slot-linked live records |
| Database-writing suites collide when run concurrently | Parallel `RefreshDatabase` classes against the same QA schema | Run PHPUnit feature files sequentially |

## Final verification

- Historical import: 1 passed / 57 assertions.
- Report SQL aggregation: 3 passed / 17 assertions.
- Report controller: 28 passed / 121 assertions (existing PHPUnit doc-comment metadata deprecation warnings).
- Dashboard: 36 passed / 229 assertions.
- Feed/FCR: 38 passed / 126 assertions.
- Demo hen seeder: 3 passed / 8 assertions.
- PHP syntax checks and `git diff --check`: passed.
- `npm run build`: passed.
- Authenticated browser verification was unavailable; no commit, push, or deployment was done.
