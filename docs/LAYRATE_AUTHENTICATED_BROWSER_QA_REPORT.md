# LayRate Authenticated Browser QA Report

**Assessment date:** 2026-10-27 (authenticated continuation)
**Target:** Local development checkout only (`/home/joed/LayRate-Main`)
**Defense readiness:** **NOT YET VERIFIED**

## Final defense continuation — latest check

- The current local browser is at `/login`; no account/password submission was attempted. Authenticated workflow checks, native zoom, dense-table keyboard/touch scroll, modal lifecycle, dashboard chart/table parity, and workflow rehearsal require a manually refreshed authorized session.
- The packaged Playwright CLI attempted `/opt/google/chrome/chrome` and failed because that executable is absent. No install or system change was made. Existing `/usr/bin/chromium` was driven through the repository-installed Playwright package for logged-out-only checks: `prefers-reduced-motion: reduce` matched, 200% CSS zoom proxy applied (not native browser zoom), keyboard focus moved from email to password, no JS/page errors or >=400 responses.
- Representative authored color pair ratios calculated in Chromium: ink/white 16.48:1; muted/white 4.83:1; secondary/white 6.53:1; danger/white 8.12:1; white/navy 8.63:1. Chart tick `#9CA3AF` on white is 2.54:1, a low contrast finding for small labels. Exhaustive rendered checks remain blocked.
- Fresh browser evidence does not repeat or supersede the earlier authenticated 16-route matrix; its artifacts remain historical evidence. Read-only server workflow rehearsal remains incomplete pending manual session refresh. Finance remains excluded.

## Summary

Authenticated browser QA resumed with the refreshed authorized Playwright state at `http://127.0.0.1:8000`. All 16 applicable authenticated pages returned HTTP 200 at 1280×900 and displayed their expected page heading and data-driven content. Chromium saw no JS/page errors or HTTP responses >=400. The session identity rendered as “Farm Admin / Admin”; no secrets were emitted. Finance was excluded from test navigation per client request.

Detected servers on ports 3000 and 8080 were unrelated applications (3000 returned a JSON API 404 at `/`; 8080 served a different product), and were not tested. The authenticated browser did not access Finance. UX-005 remains **NOT APPLICABLE — INTENTIONAL CLIENT DECISION** for defense scope; Finance stays excluded from defense navigation/interface, with existing feature, routes, records and permissions preserved.

## Environment and access

- `php artisan env`: `local`; `APP_DEBUG=true`; timezone UTC.
- `php artisan config:show database.default`: `mysql`.
- `.env` resolves the local runtime DB to host `127.0.0.1`, database `layrate`, username `root`; credentials intentionally omitted here. Laravel drivers show database-backed session, queue and cache. No destructive DB command was run.
- Test isolation was established for PHPUnit: `.env.testing` targets local MariaDB schema `layrate_qa_20261027`; tracked `phpunit.xml` force-sets MySQL, `127.0.0.1:3306`, schema, username and empty DB URL/socket. The ignored local `.env.testing` supplies DB password and test-only APP_KEY. `Tests\TestCase::setUp()` has a fail-closed guard verifying connection, schema, host, port, URL and actual connected schema before Laravel's `RefreshDatabase` trait starts. A negative run with forced `DB_DATABASE=layrate` failed at this guard before setup. Information-schema inspection found 40 app tables and 86 migration records; business tables users/cages/hens/production_logs were empty, settings had 16 rows. The older `layrate_testing` schema was not changed. The local root credential is used; a restricted per-schema DB user was not configured, so least-privilege credential isolation is not established.
- `$HOME/layrate-auth.json` targets `http://127.0.0.1:8000`; refreshed session was successfully loaded by Chromium. Rendered dashboard identifies the user as “Farm Admin / ADMIN”; exact authorization role matrix was not independently verified. Cookie/session values were not printed or included in artifacts.
- Server started locally with `php artisan serve --host=127.0.0.1 --port=8000 --no-reload`. No production endpoint, Raspberry Pi, relay, sensor command or hardware control was accessed.

## Routes and side-effect assessment

- Authenticated route group is protected by `auth` and `system-time-set`. `EnsureSystemTimeSet` can set session state for an admin with an unset clock; current local clock was not altered. Authenticated dashboard response rendered directly.
- `routes/web.php` includes GET/read routes and POST/PUT/PATCH/DELETE handlers. Playwright network interception allowed only same-origin GET/HEAD/OPTIONS and blocked every other method and every external-origin request. No mutation was attempted or transmitted. The tested pages’ observed requests were same-origin GETs for document, assets, dashboard data, list/live-data, and one environment relay SSE status stream. Relay stream was GET-only and no relay-control request was made. Client scripts did not issue unsafe or external requests during this run.
- Observed read requests included `/dashboard/stats/production`, dashboard analytics fragments, `/chickens/inventory-list`, egg live/list endpoints and SSE streams, `/feed/live-data`, `/environment/live-data`, `/environment/relay-stream`, `/hardware/live-data`, `/pre-orders` data/table endpoints, `/notifications/table`, `/mortality/logs`. Exact request sample is in `/tmp/opencode/layrate-matrix-results.json`. No database snapshot/diff was taken; framework/session reads may touch session/cache metadata, so this does not assert zero database writes outside user records.
- No data-changing form, alert acknowledgement, deletion, relay operation, system-time change, forecast generate/import/reset, export/print, or logout-other-devices action was run.
- Browser smoke: `/dashboard` => HTTP 200, final `/dashboard`, title `LayRate — LayRate Farm Monitor`, h1 “Dashboard”, main content with real local metrics.

## Page and workflow coverage

| Area | Result | Reason / evidence |
|---|---|---|
| Dashboard and analytics | PARTIAL | HTTP 200, data rendered, responsive/console checks; no calculation baseline comparison or chart parity. |
| Cage management/layout | PARTIAL | HTTP 200, 3 cages/132 slots; fixed grid scaling/scroll region, pan verified; no moves/saves/deletion. |
| Hens inventory / mortality | PARTIAL | HTTP 200 and page content; filters not changed, no mortality writes. |
| Egg Logging / Recent Logs | PARTIAL | HTTP 200, list/data endpoints and SSE GET observed; no log actions. |
| Egg Stocks / Pre-Orders | PARTIAL | HTTP 200, pools/tables rendered; filter sheet opened/closed safely on Stocks; no order/stock changes. |
| Feed Management / FCR | PARTIAL | HTTP 200, metrics/data rendered; no filters or calculation parity fixture. |
| Environment Monitoring | PARTIAL | HTTP 200; no live sensor reading reported; read-only data/SSE GET only, no relay control. |
| Hardware inventory | PARTIAL | HTTP 200, hardware rows rendered; read-only live-data GET; no device actions. |
| Forecast / Reports | PARTIAL | HTTP 200 and scope/preview forms rendered; no generation/export/print. |
| Notes / Notifications | PARTIAL | HTTP 200; notification empty state rendered; no note write or alert acknowledgement. |
| Profile/settings | PARTIAL | HTTP 200; profile role/data rendered; no profile/settings change. |
| Finance | NOT APPLICABLE — INTENTIONAL CLIENT DECISION | Intentionally excluded from October 29 defense; not opened or added to defense interface. |

Pages were verified for rendering and sampled content, not complete feature functionality. Tab state/keyboard was checked on Dashboard, Feed, Hens and Profile. Filters were not applied because they trigger additional requests/data changes; no business calculation parity was asserted. Empty Notifications state observed. Role-aware authorization matrix remains unverified.

## Viewports, screenshots, accessibility

- All 16 pages exercised at 360×800, 390×844, 768×1024 and 1280×900 (64 viewport observations); no document/body horizontal overflow or uncontained visible element overflow observed. Screenshots captured at 360×800 for each page under `/tmp/opencode/layrate-{name}-360x800.png` and 1280×900 hydration screenshots under `/tmp/opencode/layrate-hydrated-{name}.png`. A later short-delay run captured “Loading…” while lazy frames were still hydrating; the dedicated longer-delay matrix verified actual content for all 16 pages.
- Browser context emulated `prefers-reduced-motion: reduce`; media query matched. CSS authored entrance animation rules were inspected in runtime, but all active animation states were not exhaustively observed. Loading/skeleton animations were not comprehensively tested.
- The app viewport meta allows scaling (`width=device-width, initial-scale=1.0, viewport-fit=cover`). Native Ctrl/Cmd+plus zoom was not exercised. An additional attempt using `documentElement.style.zoom=200%` redirected to `/login` because the saved auth state is now expired; this CSS proxy yielded no authenticated layout evidence. Native zoom criterion remains PARTIAL/BLOCKED pending refreshed auth.
- Keyboard: first Tab from Dashboard reaches Dashboard link with visible focus. Dashboard, Feed, Hens and Profile tabs passed ArrowRight and Home selection/panel-state checks; End passed earlier on Dashboard. Escape closed cage-info dialog and restored focus to trigger. Cage info modal title present as aria-label; focus lifecycle now opts into shared handler. Filter sheet opened, initially focused search input, and Escape closed it on Stocks. No full-page keyboard audit/screen reader.
- Visible form-name audit across rendered pages found and fixed Notes add body, Profile name/email, Hens tag/cage/breed/sort, Environment trend range, and generated date-picker text controls. `public/js/date-mdy.js` now transfers the associated native date label to its visible display input. Final audit: all 16 pages have one main landmark, no duplicate IDs in sampled default DOM, and no visible unlabeled text/select/textarea controls; hidden modal fields and dynamically opened duplicate IDs were not exhaustively checked.
- Dense Feed, Hardware, Stocks and Pre-Orders table regions are named and keyboard-focusable with visible mobile swipe guidance. At 360px scroller content widths were respectively 998, 951, 826 and 973 CSS px; horizontal pan by keyboard/touch was not physically tested. Cage grid scroller is named/focusable; keyboard scroll reachability and full slot interactions not exhaustively checked.
- A fresh accessibility/zoom rerun likewise redirected to login on all five representative routes. It confirmed Chromium 149 and `prefers-reduced-motion: reduce` matching with no active animation on the login screen only; it is not authenticated page/AT/contrast evidence. Contrast calculations, screen reader announcements, chart/table data parity, dialogs beyond tested cage/filter examples, and invalid/corrected form states remain PARTIAL/BLOCKED.

## Tests and static validation

- Isolated calculation suite `FcrCalculationTest`: **12 tests / 32 assertions passed**. `DbSanityCheckTest`: **1 / 6 passed**. Unit service regression set (`EnvironmentStatusServiceTest`, `ReportingDateServiceTest`, `FcrStatusServiceTest`, `OptimalRangeAndDisplayDateTest`): **32 / 59 passed**.
- Targeted business batch across egg reporting/stock/size, pre-orders, feed, cage positioning, forecast and reports: **151 passed / 31 failed (660 assertions)**. Failures include missing BCMath and ZipArchive extensions, a feed-report ordering mismatch, sensor-event assertions, Stocks response-format/status expectations, Pre-Order zero-pool fixture results, FeedBatch posting/low-stock/CRUD failures, and PDF/chart assertions. These are not attributed to UI remediation without confirmed root cause. Full output: `/tmp/opencode/layrate-targeted-phpunit-rerun.log`. Calculation/data parity remains BLOCKED.
- PHPUnit metadata inventory found 135 occurrences across 11 project test files. `EnvironmentStatusServiceTest`'s 13 `@test` annotations were converted to `#[Test]`; other metadata remains. The old 148 deprecation count is historical and a fresh aggregate count has not been established. No dependency upgrade performed.
- `php artisan view:cache` and `php artisan view:clear`: succeeded after latest Blade edits.
- `npm run build`: succeeded after latest CSS edits (Tailwind CSS 4.3.2).
- `git diff --check`: succeeded after all current changes.
- `vendor/bin/pint --test`: failed repo-wide because many existing application, migration, route and test files do not match Pint formatting; no formatter was run in fix mode. These widespread findings are outside a targeted UI regression correction and were left unchanged.
- Broader DB-backed tests were run only against `layrate_qa_20261027`, never the valuable `layrate` schema. The PHP guard was negatively tested against forced `layrate` and refused execution. Browser write flows were not run.

## Regressions and fixes

- Confirmed mobile cage layout regression: on 360×800 with an 11×30 grid, grid was scaled to 0.221 and max-content canvas collapsed to about 296px, preventing useful horizontal scrolling. Fixed `resources/views/cages/index.blade.php` to preserve tile scale, constrain canvas to available width and expose a named keyboard-focusable region. Browser retest: natural scale 1, 1338px grid in 294px viewport, 1044px horizontal scroll range; no data/layout save performed.
- Confirmed missing input names on Notes add body and Profile name/email, Hens tag/cage/breed/sort filters, Environment trend-range selector, and generated date input display controls. Added explicit associations and label transfer to the generated display input. Final rendered DOM audit found no visible unnamed text/select/textarea controls across the 16 pages.
- Cage information dialog initially lacked shared focus lifecycle integration despite a runtime Escape handler. Added `data-modal`/`data-close` opt-in and `modal:shown` signal. Browser retest: focus entered dialog, Escape closed it, focus returned to trigger; no data action.
- No other reproducible application error found. All requests blocked if non-read or external; console/page errors and >=400 responses absent in inspected page matrix.
- Preserved pre-existing `serial-bridge/test_service_config.py` modification and untracked `output/`; no commit, push, deploy, DB mutation, hardware operation, system-time change or destructive action occurred.

## Remaining blockers and next actions

1. Refresh/reauthorize Playwright state, then perform native browser 200% zoom, keyboard scrolling of dense regions, short-height dialogs, contrast calculations and a screen-reader smoke test.
2. Triage failing business tests on deterministic isolated fixtures; enable required PHP extensions only through authorized package management. Do not mutate `layrate`.
3. Complete chart/table parity, forecast inputs/results/date scopes, HDEP and production totals, mortality/removal and dashboard/report summaries.
4. Convert remaining PHPUnit metadata in a separately scoped test-maintenance change and rerun discovery/tests; preserve dataset/provider semantics.

**Assessment:** **NOT YET VERIFIED**. Previous authenticated route/render coverage and responsive/keyboard/name checks have evidence, and test DB schema isolation is guarded. Native zoom, complete accessibility/AT and functional/data parity remain incomplete; the targeted suite has unresolved failures. No development database record, calculation rule, route, permission or hardware integration was changed.
