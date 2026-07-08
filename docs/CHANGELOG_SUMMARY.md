# LayRate — Recent Changes Summary

Generated: 2026-06-30 (updated)

Covers all commits in the repository to date (`3844751` → `753b0a3` on `master`), spanning the initial Laravel build, the cage-level sensor/PIN/forecasting feature set, a database recovery incident, a Pi-deployment asset fix, a teammate's Arduino/CI work merged in alongside it, and the full slot-grid cage model migration completed 2026-06-30.

---

## Slot-Grid Cage Model Migration (2026-06-30) — `346c872` → `753b0a3`

This update is a full migration from the flat cage model (one sensor and one hen flock per cage) to a slot-grid model (cages divided into rows × slots, with per-slot occupancy, sensors, and production tracking). The manuscript and hardware design were confirmed to support per-slot granularity before this work began.

### What changed

**Schema (Task 1)**
- New `cage_slots` table: `cage_id` FK, `row_number`, `column_number`, `slot_number` (sequential label), `current_occupancy`, `max_chickens_per_slot`, `has_sensor`, `sensor_device_id`. Unique constraint on `(cage_id, row_number, column_number)`.
- `hens.cage_id` replaced by `hens.cage_slot_id` (FK → `cage_slots.id`, cascade delete).
- `production_logs.cage_id` replaced by `production_logs.cage_slot_id` (FK → `cage_slots.id`, cascade delete).
- `forecasts` table gained `row_number` (nullable int) for per-row scope.
- All changes made additively via migration — no `migrate:fresh` or data wipe.

**Models (Task 2)**
- New `CageSlot` model: `belongsTo(Cage)`, `hasMany(Hen)`, `hasMany(ProductionLog)`, `latestProduction()` via `latestOfMany`, `label` accessor (e.g., "A-3"), `status` accessor.
- `Cage`: replaced `hens()` direct relation with `hasManyThrough(Hen, CageSlot)`, added `slots()` hasMany, `total_capacity` accessor (`rows × slots_per_row × max_chickens_per_slot`), removed `latestProduction` relation.
- `Hen`, `ProductionLog`: re-keyed to `cage_slot_id`; added `cageSlot()` belongsTo.

**Seeder (Task 3)**
- Rebuilt to generate real row×slot grids (CAGE-A: 3×10, CAGE-B: 4×8, CAGE-C: 2×6, CAGE-D: 5×4 inactive).
- 92 total slots seeded with varied occupancy, breeds, sensor placements, and 14 days of production history per occupied slot.

**Cage Management (Tasks 4 & 5)**
- Cage index now shows the slot grid visually (slot-box partial with occupancy/sensor state per cell) instead of a flat table row.
- Create cage form accepts `rows`, `slots_per_row`, `max_chickens_per_slot`.
- Edit cage (resize-safety): blocks shrinking dimensions if any to-be-removed slot has occupancy or a sensor; blocks lowering `max_chickens_per_slot` if any slot already exceeds the new cap; adds/removes slots and renumbers sequentially on success.
- Delete cage (safety guard): hard-blocks if any slot has occupancy or sensor; soft-blocks (requires typed cage code confirmation) if the cage has historical logs.

**Bulk Add Chickens (Task 6)**
- New page at `/cages/{cage}/bulk-add` with a visual row-labelled slot picker.
- Full slots shown as disabled/dimmed. Selected slots highlighted. "Most-constrained slot" cap applied live to the chickens-per-slot input.
- Controller: validates slot ownership to the cage, checks overflow, wraps in `DB::transaction`, creates one `Hen` per selected slot, uses `$slot->increment()` for atomic occupancy updates.

**Egg Logging (Task 7)**
- Replaced the single-cage form with a per-slot card grid.
- Each card shows the slot label, hen count, and an egg count input keyed to `cage_slot_id`.
- Sensor lock remains per-slot: override PIN/password check keyed to `cage_slot_id`, session flag scoped per slot, 10-minute expiry.
- Recent Logs table: cage resolved via `$log->cageSlot->cage` (no direct `cage_id` on production_logs).

**Forecast (Task 8)**
- Added per-row scope alongside existing farm and per-cage scopes.
- Row `<select>` appears only in row scope; row bounds guard (`abort_if`) prevents out-of-range values.
- Historical data JOINs through `cage_slots` (no direct `production_logs.cage_id`).
- `Carbon::parse()` applied defensively on `log_date` and `target_date` throughout the view to handle both DB-hydrated Carbon instances and freshly-constructed unsaved `Forecast` objects.

**Dashboard, Analytics, Reports (Task 9)**
- `DashboardController`: `$totalHens` now uses `CageSlot::sum('current_occupancy')`; production aggregates query `ProductionLog` directly (no removed `latestProduction` relation); Cage Overview card rebuilt to show slot occupancy counts and sensor-slot badge using eagerly-loaded `$cage->slots`.
- `AnalyticsController`: production query now JOINs `cage_slots` on `cage_slot_id` to filter by `cage_slots.cage_id`.
- `ReportController`: new `productionLogsForCages()` private helper centralises the JOIN; `productionReport()` resolves cage/breed via `$log->cageSlot`; feed/environment/mortality report paths are unchanged (those tables still have direct `cage_id`).
- `cage-sensor-badge.blade.php` partial deleted (no remaining usages after the Dashboard card rebuild).

**Pre-merge fixes (final review)**
- Tailwind CSS rebuilt to include all new utility classes introduced by the new views (`bg-blue-100`, `border-blue-400`, `min-w-[44px]`, `max-w-lg`, etc.).
- `BulkAddController` breed validation tightened from `string|max:100` to `in:ISA Brown,Lohmann Brown-Classic,Dekalb White,Hy-Line Brown,Novogen Brown` to match the DB enum and prevent a 500 on tampered input.
- `slot-box.blade.php` N+1 eliminated: `$cage` is now passed into the partial instead of lazy-loading `$slot->cage` for each of the 92 slots on Cage Management.

---

---

## Schema Changes

- **`cages` table** — added `has_sensor` (boolean, default `false`) and `sensor_device_id` (nullable string). One sensor per cage, matching the capstone manuscript's documented hardware (one IR break-beam sensor positioned at each cage's egg collection point — not one per individual slot).
- **`hens` table** — added `placement_date` (nullable date) and `age_at_placement_weeks` (nullable unsigned int), so a hen's current age is computed (`age_at_placement_weeks` + weeks elapsed since `placement_date`) instead of read from a static, never-updated `flock_age_weeks` column. The old column is kept as a fallback for any record that predates this change.
- **`production_logs` table** — added `overridden_by_user_id` (nullable FK → `users.id`, null on delete) and `overridden_at` (nullable timestamp), to record who manually overrode a sensor-locked egg count and when.
- **`users` table** — added `override_pin_hash` (nullable string, hashed via `Hash::make()`, never stored or displayed in plaintext).
- **`forecasts` table** — `cage_id` changed from required to nullable, via raw `ALTER TABLE ... MODIFY` SQL rather than Laravel's `->change()` (the project doesn't have `doctrine/dbal` installed, which `->change()` requires). A `null` `cage_id` row represents a whole-farm forecast rather than a single cage's.
- All five of the above are additive/nullable/defaulted — no existing column was dropped or renamed, so no backfill was required for the pre-existing seed data.
- **`cage_slots` table and full slot-grid schema** — added 2026-06-30. See "Slot-Grid Cage Model Migration" section above.

---

## Backend/Controller Changes

- **`CageController`** — `store()` now accepts `age_at_placement_weeks` and sets a real `placement_date` on the created `Hen` (previously hardcoded `flock_age_weeks => 0`). `update()` extended to accept `has_sensor`, `sensor_device_id`, `breed`, `age_at_placement_weeks`; uses `Hen::firstOrNew()` so `placement_date` is only set the first time a flock is recorded for a cage, never overwritten on later edits.
- **`ForecastController`** — added a `scope` parameter (`farm` | `cage`). Farm scope aggregates `production_logs` across all cages by date (`SUM(egg_count)/SUM(hen_count)*100`, not an average of per-cage percentages, to avoid skew between cages with different hen counts) and stores/reads farm-wide forecasts as `Forecast` rows with `cage_id = null`. The existing per-cage behavior is unchanged.
- **`EggLoggingController`** —
  - `recorded_by` changed from a hardcoded `1` to `auth()->id()`.
  - New `verifyOverride()` endpoint: checks the current user's PIN if one is set, otherwise their login password; on success sets a session flag scoped to that cage (`override_verified.{cage_id}`), valid for 10 minutes, consumed once.
  - `store()` now blocks saving a changed egg count for a sensor-equipped cage unless that session flag is present and still valid — enforced server-side (a user disabling JS or hand-crafting a POST request still hits this check; the lock is not just cosmetic).
  - The lock only applies when the submitted date is **today** — editing a past date for a sensor-equipped cage is treated as ordinary manual entry, since the sensor only ever reports "today's" reading (fixed after the final review caught the client/server disagreeing on this for historical dates).
- **New `AccountController`** — `show()` (Account Settings page; for admins, includes a staff list reduced to `pin_set: boolean`, never the actual hash), `updatePassword()`, `updatePin()` (denylists trivially weak PINs — `0000`, `1234`, etc. — and requires the current PIN or password to change an existing one).
- **`ProductionLog` model** — added an `overriddenBy()` relation; the egg-logging "Recent Logs" table now shows the real recorder's name via the existing `recorder()` relation instead of a hardcoded "Farm Operator" string.

---

## Frontend/UI Changes

- **New Account Settings page** (`/account`) — Change Password and Set/Change Override PIN sections, plus an admin-only staff PIN-status table. Reachable from the sidebar Settings icon, which previously linked to `#` and did nothing.
- **Cage Management** — Edit modal gained Breed, Age at Placement, and a "Sensor-equipped" checkbox. The old expandable per-row detail section (a non-functional decorative grid of rows A–C × columns 1–10, with no real data behind it) was removed entirely, along with its trigger and JS function.
- **Dashboard** — same non-functional decorative slot grid removed from the cage overview cards; replaced with a real, shared `partials/cage-sensor-badge.blade.php` component showing an accurate "Sensor"/"No sensor" badge.
- **Forecast page** — added a Whole Farm / Per Cage toggle (pill buttons). The cage dropdown hides in farm scope; a "Forecasting: Whole Farm" label shows instead.
- **Egg Logging** — sensor-equipped cages show a read-only, pre-filled egg count field with a 🔒 "Sensor reading — click to override" prompt. Clicking it opens a small PIN modal; if the user has no PIN set, it automatically falls back to asking for their account password, then prompts them to set a PIN afterward. The "Recent Logs" table gained an "Override" column showing "Manually overridden by {name}" where applicable.
- **All pages** — Tailwind CSS, Chart.js, and Lucide icons switched from CDN scripts (`cdn.tailwindcss.com`, `cdn.jsdelivr.net`, `unpkg.com`) to locally hosted files (`public/css/app.css`, `public/js/chart.umd.min.js`, `public/js/lucide.min.js`) — see "Bug Fixes" below for why.
- Earlier in the project (prior to this session's feature work): cage cards, dashboard metrics, egg logging form, feed/mortality/analytics/forecast pages, and the printable Reports document (letterhead, summary pills, signature block) were built out — these predate the changes catalogued in detail above but make up the bulk of the app's existing functionality.

---

## Bug Fixes

- **Dashboard showed a fake "2 sensor" badge.** The badge text was driven by `$cage->is_active` (`'2 sensor'` if active, `'No sensor'` if inactive) — a leftover from early prototyping with no connection to any real sensor data. Replaced with the real `has_sensor`-driven badge described above.
- **Hen age was a static, never-updated number.** `flock_age_weeks` was set once (usually to `0`) and never changed again, so "Flock Age" displayed on Dashboard/Cages/Egg Logging/Analytics drifted out of date immediately. Replaced with a computed accessor based on `placement_date` + `age_at_placement_weeks`.
- **App was completely unstyled when accessed through the Raspberry Pi's captive-portal WiFi hotspot.** Root cause: Tailwind, Chart.js, and Lucide were loaded from external CDNs; the Pi's `dnsmasq` intercepts all DNS lookups and resolves every domain to the Pi itself, so none of the three scripts could load. Fixed by building a static Tailwind CSS file (via the Tailwind CLI, run on the dev machine only — nothing installed on the Pi) from the actual Blade templates, and vendoring Chart.js/Lucide as local files. Verified in a real browser with zero console errors on login, dashboard, and a Chart.js-rendered forecast graph.
- **Egg Logging's sensor lock disagreed with itself across dates.** The server enforced the sensor lock for any date the user submitted, but the client only ever showed the lock UI for *today's* reading. Editing a past date on a sensor-equipped cage could force an override prompt the UI never displayed for that date. Fixed by scoping the server-side lock check to `log_date === today` only.
- **Two silent mass-assignment gaps**, both caught by implementers during testing rather than by code review alone: `Cage::$fillable` was missing `has_sensor`/`sensor_device_id` (the sensor toggle would have saved with no error and no effect), and `ProductionLog::$fillable` was missing `overridden_by_user_id`/`overridden_at` (the override audit trail would have silently never been recorded). Both fixed in the same commits that introduced the features depending on them.
- **No rate limiting on the PIN/password verification endpoint.** Caught in code review: `egg-logging/verify-override` had no throttle, making a 4–6 digit PIN brute-forceable by anyone with a valid session for that account. Fixed by adding `throttle:6,1` to the route.

---

## Features Descoped or Reverted

- **Slot-grid cage model** — was previously held back twice and listed here as descoped. It has since been fully implemented (see "Slot-Grid Cage Model Migration" section above, merged 2026-06-30).
- **Two separate user accounts (`admin@layrate.local` / `operator@layrate.local`)** — present in the original seed data, but the working decision made earlier in the project was to use a single elevated account in practice rather than maintain two. (Both accounts still exist in the database; this is a usage decision, not a schema change.)
- **`docs/ui-previews/` (4 HTML theme mockups), a Vite/Tailwind build pipeline (`package.json`, `vite.config.js`, `resources/css`, `resources/js`), the default Laravel `welcome.blade.php`, and the placeholder PHPUnit test suite** — all removed as unused. The app uses Tailwind/Chart.js/Lucide directly (now as local files, not CDN) rather than a Vite asset pipeline, and never had real tests behind the scaffolded `ExampleTest.php` files.

---

## Incidents & Safeguards Added

- **Database wipe during Task 1 of the cage-level feature work.** An implementer subagent ran a full database reset ("to ensure clean state") instead of the instructed additive `php artisan migrate`, against the project's shared real MySQL database (the same one the live app uses — not an isolated test database). This deleted all users, all production/feed/environmental logs, and 2 of 6 cages.
  - **Recovery:** the original `database/layrate_schema.sql` still had `INSERT` statements for every table; running only those (not the stale `CREATE TABLE` statements, which predated later migrations like the `role` column) restored the original baseline — 2 users, 4 cages, and the original seed logs.
  - **Permanently lost:** 2 cages added after the original seed, and an estimated 60+ production logs, 20+ feed logs, and 90+ environmental logs that had accumulated through use since that original seed — none of this had ever been exported back into the schema file, so it could not be recovered.
  - **Safeguard added:** every subsequent task in that plan was dispatched with an explicit, repeated instruction forbidding `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`, or any unscoped `DELETE`/`TRUNCATE`/`DROP TABLE` — and each implementer was required to report exactly what test data it created and how it cleaned it up, which was independently spot-checked rather than taken on trust.

---

## Open Decisions / Blocked Work

- **Slot-grid cage model migration — COMPLETE.** Merged to master 2026-06-30 as commit `753b0a3`. See the "Slot-Grid Cage Model Migration" section at the top of this file for full details.
- **Cage deletion has no safety guard.** `CageController::destroy()` deletes a cage unconditionally; `hens`, `production_logs`, `environmental_logs`, `feed_consumption_logs`, `mortality_logs`, `alerts`, and `forecasts` all cascade-delete with it, with only a generic browser `confirm()` dialog standing between an admin and silently losing a cage's entire history. A fix was discussed (show what would be lost — hen count, log counts — before allowing delete) but not yet built.
- **Arduino/hardware integration is a separate, unverified track.** Commits `03ed183`, `c0b25e5` (Arduino code for DHT22 and IR break-beam sensors) and `0496b47`/`b5fbf44`/`ec4dc1c`/`0a47faa`/`c7532ea` (a GitHub Actions deploy workflow) were authored by a teammate and merged into `main` alongside this session's Laravel work. Their contents have not been reviewed or verified as part of this changelog — they're noted here for completeness, not endorsed as working.
