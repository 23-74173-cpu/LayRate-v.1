# LayRate — Audit Fix Backlog

Prompts for OpenCode/Claude Code, in run order. Each is self-contained — safe to run
in separate sessions. Sequencing notes are at the bottom.

For every prompt, after completing the work, append a dated entry to
`/audit/fix-log.md` (create it if it doesn't exist) summarizing: what was found,
what was changed (files + line ranges), how it was verified, and any open
questions left for a follow-up pass. Don't wait to be asked — do this as the
last step of every prompt below, right before handing control back.

---

## Prompt 1 — Fix dead `stock_depletion` alert branch (COMPLETED)

> Status: done and verified. Kept here for the fix-log record only — no need to re-run.

```
Fix the dead `stock_depletion` alert branch in PreOrderController.

Root cause (confirmed by prior audit, do not re-investigate):
`PreOrderController::index()` computes `'available' => max(0, $pool)` before passing
the summary array into `runDepletionCheck($summary)`. `runDepletionCheck` then checks
`if ($data['available'] < 0)` — which can never be true because `available` was already
clamped to a minimum of 0 upstream. The alert has never fired in production.

The array already separately computes `'deficit' => $pool < 0 ? abs($pool) : 0'` in
the same `index()` method — this field is the one that should drive the check, or the
check needs the pre-clamped `$pool` value, not the clamped `available`.

Fix:
1. Change `runDepletionCheck` to check the actual depletion signal — either pass it
   the unclamped `$pool` value directly, or check `$data['deficit'] > 0` instead of
   `$data['available'] < 0`. Pick whichever requires touching fewer call sites and
   preserves the existing `available` field's meaning for the UI (don't un-clamp
   `available` itself if the UI depends on it never going negative — check
   `eggs/pre-orders.blade.php` for that before deciding).
2. Do NOT touch the dedup key (`ReportingDateString()`) or timezone logic in this
   pass — that's a separate fix, out of scope here.
3. Do NOT touch `checkMortalitySpike`, `EnvironmentAlertService`, or any of the other
   5 dedup implementations — out of scope.
4. After the fix, write a quick manual test plan (not full test suite yet) for how to
   verify the alert now actually fires: what pre-order/stock state to set up, what
   GET request triggers `index()`, and what to check in the `alerts` table afterward.
5. Show me the diff before running anything against real data — I want to confirm
   the `available` vs `deficit` UI question is resolved correctly first.
```

---

## Prompt 2 — Alert dedup: fix the timezone/calendar mismatch (COMPLETED)

```
Prior audit (do not re-investigate) found: 3 of 6 alert-dedup checks use an
Asia/Manila "reporting date" (ReportingDateService::reportingDateString(),
06:00 reset) as their dedup key, while `triggered_at` is always stamped with
UTC `now()`. This means during Manila 06:00–08:00 (UTC 22:00–24:00), the key
never matches the stamp, so dedup silently fails and duplicate alerts can be
created. Affected: SensorIngestionController::createSensorResetAlert,
PreOrderController::runDepletionCheck (just fixed to be reachable — see
prior fix), and Controller::checkMortalitySpike (whose two callers,
MortalityController and ChickensController, additionally disagree with
each other about what date key to pass).

Decide and implement ONE consistent rule:
Option A: Store `triggered_at` in the same calendar the dedup key uses
(i.e., stamp using ReportingDateService's reporting date/timezone instead
of raw `now()`) for these 3 alert types specifically.
Option B: Change these 3 dedup keys to use the app's UTC calendar day
(`today()`) instead of the Manila reporting date, matching the other 3
implementations (EnvironmentAlertService, createOccupancyMismatchAlert,
both low_stock checks).

Recommend Option B unless you find a farm-operations reason (e.g. reports
or other user-facing "today" already means Manila reporting-date) that
Option A better matches what a person on the farm would expect "today" to mean.
Check how ReportingDateService is used elsewhere in the app before deciding —
if it's the convention used for user-facing date displays/reports, that's
a vote for Option A.

Also fix the disagreement between MortalityController (passes user log_date)
and ChickensController:608 (passes reportingDateString()) for the same
mortality_spike alert type — both callers must use the same key definition.

Show me your Option A vs B decision and reasoning FIRST, before writing any
code. Wait for my go-ahead.
```

---

## Prompt 3 — Alert dedup: DB-level unique constraint (COMPLETED)

```
The `alerts` table has zero unique constraints — dedup is 100% application-level
exists-then-create with no transaction/lock, across all 6 alert-creation call
sites (prior audit confirmed this). This is the root cause of every theoretical
race condition found.

Add a real DB-level guard:
1. Design a generated/computed column or approach that lets a unique index
   express "one alert per (cage_id, alert_type, day)" — accounting for
   cage_id being nullable (some alert types, like low_stock, use cage_id=null)
   and for whatever "day" definition was settled on in the timezone fix (see
   separate prompt — do this AFTER that fix lands, not before).
2. Write the migration. Handle SQLite/MySQL differences if this app supports
   both (check config/database.php).
3. Update all 6 call sites to catch/handle the unique constraint violation
   gracefully (e.g. catch QueryException, treat as "alert already exists,"
   don't bubble a 500 to the user) instead of relying solely on the
   exists-check.
4. Do NOT remove the application-level exists-check — keep it as the fast
   path, with the DB constraint as the backstop.

Show me the migration and the constraint design before applying it — I want
to confirm the uniqueness key matches what "duplicate" actually means for
low_stock's cage_id=null + size-in-message case, which doesn't fit a clean
column-based key as easily as the others.
```

---

## Prompt 4 — Alert dedup: fix low_stock cross-suppression (COMPLETED)

```
FeedController::checkLowStock and EggStockBatch::checkLowStock both create
alerts with alert_type='low_stock' and cage_id=null, but they mean different
things (feed-batch kg running low vs egg-count pool running low for a size). 
FeedController's exists-check has no size/message filter, so:
- If EggStockBatch's alert fires first today, FeedController's exists-check
  incorrectly matches it and Feed's warning never fires that day.
- If FeedController's fires first, EggStockBatch still fires per size (its
  message LIKE filter doesn't match Feed's message), so both show up.

Fix: give these two alert types distinct `alert_type` values (e.g.
'low_stock_feed' and 'low_stock_eggs') so their dedup keys can never collide.
Check every place `alert_type='low_stock'` is read (frontend badge rendering,
notification lists, any other query) and update those to handle both new
types correctly — don't just rename in the two creation sites and miss a
consumer. Write a migration to backfill existing 'low_stock' rows to the
correct new type based on whether their message contains a size name.

Show me the full list of files that reference 'low_stock' before making
changes.
```

---

## Prompt 5 — Bug: manual sensor override value not reflected after fetch (COMPLETED)

```
Bug: when I manually override a sensor reading (manual override mode), the
new override value is saved but is NOT reflected back — the UI/next fetch
still shows the old sensor-derived value instead of the override value.

Investigate end to end:
1. Find where manual override is submitted (controller/route) and confirm
   the override value is actually persisted correctly (check the DB row
   directly, not just the response).
2. Find every read path that displays or consumes this sensor's current
   value — API endpoint(s), any caching layer, SSE/polling on the frontend,
   and the relay/fan AUTO-mode logic that reads thresholds/values — and
   check whether each one prioritizes the override value over the live
   sensor value, or whether they're all still reading from the raw sensor
   field.
3. Check whether the override is stored on the same field the live sensor
   writes to (in which case the next sensor poll could be overwriting it)
   vs. a separate override field/flag that reads need to explicitly check.
4. Check for caching: query cache, HTTP response cache, or frontend state
   not being invalidated/refetched after the override is submitted.
5. Check the SSE/live-update layer specifically — is it pushing the raw
   sensor value on its own poll cycle, racing against and clobbering the
   override display.

Report exactly which of these is the root cause with the specific file and
line, then propose the fix. Don't apply the fix until I confirm the
diagnosis is right.
```

---

## Prompt 6 — Hardware disconnect/fault detection audit (DESIGN COMPLETE — IMPLEMENTATION PENDING)

> Design doc: `/audit/hardware-fault-detection-report.md` — the 4 review open
> questions (override-row interaction, cadence vs threshold, recovering-state
> alerting, relay safety boundary) were resolved in §5, and §4 locked the
> remaining decisions (health_state separate from status; Setting-backed
> thresholds). Write any implementation prompt against the resolved design.

```
I need to understand exactly how this system currently detects disconnected
or faulty hardware (sensors, relays, fan actuators) across the Raspberry
Pi/Arduino serial layer and the Laravel backend — and where it falls short.

Trace and document, end to end:
1. Where sensor readings (DHT22, etc.) enter the system — the serial read
   loop, how often it polls, and what happens on a failed/timeout read.
2. The current staleness-check logic — what counts as "stale" per sensor
   type, what triggers a device to be marked disconnected/offline, and
   where that state is stored (cage_slots table or elsewhere).
3. What differentiates a "disconnected" device from a "faulty" one (e.g.
   sensor returns readings but out-of-range/implausible values vs. no
   readings at all) — and whether the system currently makes that
   distinction at all.
4. How the relay/fan safety-block state ties into this — confirm exactly
   what invalidates a DHT22 reading enough to trigger the safety block,
   and whether that logic is duplicated or centralized.
5. What the frontend/dashboard actually shows the user when a device goes
   offline vs faulty — is there a real-time indicator, does it require a
   page refresh, is it per-slot or per-cage.

Then write a gap report to /audit/hardware-fault-detection-report.md covering:
- Current detection logic as it actually exists in code (not as documented
  in the manuscript — call out any mismatch).
- Gaps: failure modes NOT currently detected (e.g. sensor stuck reporting
  last-known-good value, intermittent flapping connections, partial slot
  failures).
- A concrete proposed design for a unified device-health state machine
  (e.g. online / stale / disconnected / faulty / recovering) with the
  specific per-sensor-type thresholds and transition conditions.

Do not implement anything yet — this is investigation and design only,
I'll review before we build it.
```

---

## Prompt 7 — Fix predicted_hdep export bug (dead column reference) (COMPLETED)

```
`ForecastController::exportCsv` (around line 1327) reads column `predicted_hdep`,
but that column was renamed to `predicted_egg_count` in migration
2026_07_02_000000. Confirm this by checking the current forecasts table
schema, then fix exportCsv to read `predicted_egg_count`. Search the whole
codebase for any other reference to `predicted_hdep` (views, other exports,
API responses) and fix those too. Show me the diff before running anything.
```

---

## Prompt 8 — Decouple GenerateForecastJob from ForecastController

```
app/Jobs/GenerateForecastJob.php currently injects ForecastController and
calls its public methods directly — a queue worker depending on a web
controller. Extract the forecast generation logic that both the job and
the controller need into a ForecastService (or reuse app/Forecast/ForecastRules.php
if that's the right home — check what's already there first).

Steps:
1. Identify exactly which ForecastController methods GenerateForecastJob
   calls, and what they do.
2. Move that logic into a service class with no HTTP/controller dependencies
   (no request(), no response(), no session).
3. Update GenerateForecastJob to call the service directly.
4. Update ForecastController to call the same service instead of doing the
   work inline, so behavior doesn't diverge between the job and web paths.
5. Do not touch ForecastController's other unrelated responsibilities (the
   1501-line controller has plenty of other stuff — leave everything else
   alone in this pass).

Show me the extraction plan (what moves, what stays) before writing code.
```

---

## Prompt 9 — Fix JSON/error response contract

```
This app has 3+ inconsistent JSON response conventions across controllers:
success envelope is sometimes {ok:true}, sometimes {success:true}, sometimes
no envelope at all; errors are sometimes `errors` (validator-shaped),
sometimes `error` (string), sometimes `message`; some validation failures
return HTTP 500 instead of 422; 404s are inconsistent between JSON and raw
text responses.

Do NOT do a sweeping rewrite of every controller in one pass. Instead:
1. Define ONE standard contract in a short markdown doc (/docs/api-contract.md):
   success shape, error shape, HTTP status conventions, 404 handling.
2. Build a small helper (trait or base Controller method) that produces
   both shapes consistently — e.g. `$this->success($data)` and
   `$this->error($message, $code = 422)`.
3. Apply it to the 3 highest-traffic/most-visible endpoints only for now —
   the ones a mobile app or external integrator would hit first (check
   MobileAppController usage or whichever endpoints are hit most, if you
   can tell from route definitions or docs). Tell me which 3 you picked
   and why before changing the rest.
4. Leave the remaining controllers alone — I'll have you convert them in
   a follow-up pass once we confirm the contract is right.

Show me the contract doc and the helper design before touching any controller.
```

---

## Prompt 10 — Author DESIGN-SYSTEM.md + fix the 3 shared components

```
DESIGN-SYSTEM.md is referenced by resources/css/app.css comments and by
components/status-badge.blade.php and environment/_live-data.blade.php,
but the file does not exist anywhere in the repo. Meanwhile the actual
de-facto tokens live in the `@theme` block of resources/css/app.css.

1. Read the full @theme block and write DESIGN-SYSTEM.md documenting every
   token that exists today: colors, radius scale, spacing scale, typography.
   Document it as it actually is, don't invent new tokens yet.
2. Then audit components/card.blade.php, components/button.blade.php, and
   components/underline-tabs.blade.php — these hardcode raw hex values
   (#D9D9D9, #F9F9F7, #002D5E, #6B7280, #333333) instead of using the
   tokens that already exist. Rewrite these 3 components to use the
   defined tokens.
3. One value has no home yet: navy shows up as #002D5E, #213183, #0075de,
   and #001F42/#102A4C (legacy) in different places with no single token.
   Propose a `--color-navy` (or similar) token addition and tell me which
   of the existing navy values should become the canonical one before adding it.
4. Do NOT touch any other Blade view in this pass — just the 3 shared
   components and the new doc. Migrating individual pages onto the fixed
   components is a separate, later pass.

Show me the DESIGN-SYSTEM.md draft and the navy-token decision before
touching the component files.
```

---

## Prompt 11 — Delete verified dead code

```
Prior audit confirmed these are dead code with zero references (verify
each yourself before deleting, don't trust this list blindly):
- app/Http/Controllers/MobileAppController.php (unreachable — no route
  references it except a test)
- app/Models/IncubatorStatus.php (table created then dropped in consecutive
  migrations, zero model references)
- app/Http/Middleware/ApiAuth.php (unused, DeviceAuth is the one actually used)
- ForecastController::respondAfterGenerate() (~line 1078-1097, superseded
  by respondQueued, never called)
- resources/views/cages/label.blade.php (unrouted duplicate of print-label.blade.php)
- MortalityController.php:272-276 (trailing empty doc-comment function stub)

For each one: confirm zero references with a real search (not just grep
the class name — check route files, service providers, config, and tests
too), then delete it. If ANY of these turns out to have a live reference
you find during verification, stop and tell me instead of deleting it.
Show me the list of what you're about to delete and your verification
method before actually deleting anything.
```

---

---

## Prompt 12 — Fix forecast chart axis mismatch

```
`forecast/_results.blade.php`'s chart plots Historical HDEP% and Forecast
egg-count on the same axis/scale — a unit mismatch flagged during Prompt 7
but out of scope for that pass. Investigate whether this needs a dual-axis
chart, a unit conversion, or separate charts, and fix it. Independent of the
other prompts — safe to run standalone.
```

## Prompt 13 — Security: gate the unauthenticated /_reset-opcache route (COMPLETED)

```
routes/web.php's GET /_reset-opcache called opcache_reset() with no auth
(temporary debug leftover from 744af8a). Gated behind the existing
'auth' + 'admin' middleware (EnsureAdmin, same pattern as other admin-only
routes); FEATURE-INVENTORY updated. Verified: guest→login, non-admin→403,
admin→200; suite baseline unchanged.
```

## Sequencing notes

- **Prompts 1–5, 7 are COMPLETED**; **Prompt 6** is design-complete (see report
  §5) and awaiting an implementation prompt against the resolved design.
- **Prompt 2 → 3 → 4 dependency** (alert-dedup): run in that resolved order —
  already executed, kept here for the record. **Prompt 5** resolved the
  override-vs-live precedence and landed with the dedup work; **Prompt 7**
  (predicted_hdep) is done.
- **Prompt 8 is now unblocked** — Prompt 7 finished first (both touch
  ForecastController). Still run 8 after 7 (done) to avoid same-file conflicts.
- **Prompt 12 is independent** of the alert-dedup work and of the other
  prompts — safe to run standalone.
- **Prompts 9, 10, 11** are pending and independent of each other and of the
  alert-dedup work — safe to run in parallel across separate sessions.
- **Prompt 9** is the most open-ended (picks which 3 endpoints to convert
  first) — expect back-and-forth before it writes any code.
- Every prompt ends with "show me before touching/running anything" —
  don't skip reviewing those checkpoints even if you're running several
  back-to-back.
