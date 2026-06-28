# Cage-Level Feature Set: Forecasting Scope, Sensor Override, Flock Tracking & PIN

**Date:** 2026-06-28
**Project:** LayRate Poultry Farm Management System
**Scope:** Scaled-down adaptation of a 5-feature spec, re-grounded against the actual schema and the capstone manuscript

---

## Background

A 5-feature spec was proposed (forecasting scope, egg logging sensor override, bulk chicken assignment, IR sensor flagging, override PIN) written against a `cage_slots` / `chickens` data model (rows × slots per cage, one IR sensor per slot). That model does not exist in LayRate and contradicts the capstone manuscript, which documents:

- **One IR break-beam sensor per cage** ("automated detection and tallying of eggs **per cage**")
- **HDEP computed per cage**, not per slot/row
- No mention of slots, rows, battery cage configuration, or PINs anywhere in the manuscript

This spec re-implements the same 5 feature intents at **cage-level granularity**, matching what the manuscript already documents and what the current schema actually supports.

---

## 1. Forecasting Scope — Whole Farm / Per Cage

**Current state:** `ForecastController` only supports single-cage forecasting via a `cage` query param (default `CAGE-A`). `forecast.blade.php` has a cage dropdown and horizon input — no scope toggle exists today.

**Change:**
- Add a 2-pill toggle: **Whole Farm** / **Per Cage** (default: Per Cage, to preserve current behavior/links).
- **Per Cage** (existing behavior, unchanged): cage dropdown visible, forecast generated per selected cage.
- **Whole Farm** (new): cage dropdown hidden. Historical data = `ProductionLog` summed across *all* cages, grouped by `log_date`, for the last 14 days. Farm-wide HDEP per date = `SUM(egg_count) / SUM(hen_count) * 100` (not an average of per-cage HDEP — avoids skew when cages have different hen counts). Forecast projection reuses the existing `generateForecast()` linear-variation logic, applied to the farm-wide aggregate series.
- `forecasts.cage_id` foreign key becomes **nullable** — a `null` `cage_id` represents a farm-wide forecast row. Requires a migration to drop and re-add the FK as nullable (SQLite/MySQL: modify column).
- View: when scope is Whole Farm, the forecast chart/table labels the series "Whole Farm" instead of a cage code.

**Out of scope:** Per-row forecasting (no rows exist).

---

## 2. Daily Egg Production Logging — Sensor Override

**Current state:** `egg-logging.blade.php` is a simple form (cage select, date, egg count, hen count, notes) writing to `production_logs` (unique per `cage_id` + `log_date`). No sensor concept exists on `cages` today.

**Schema additions:**
- `cages.has_sensor` (boolean, default `false`)
- `cages.sensor_device_id` (string, nullable) — free-text identifier for the physical sensor unit, no separate `sensors` table (YAGNI — manuscript describes a 1:1 sensor-to-cage relationship)
- `production_logs.overridden_by_user_id` (nullable FK → `users.id`, null on delete)
- `production_logs.overridden_at` (nullable timestamp)
- `users.override_pin_hash` (nullable string, hashed via `Hash::make()` — never stored plaintext)

**Behavior:**
- When the selected cage has `has_sensor = true`, the Egg Count field renders **read-only**, displaying whatever value currently exists in `production_logs.egg_count` for that cage/date (0 if no entry yet), with a 🔒 "Sensor reading — click to override" label.
- Clicking it opens a PIN modal (4-6 digit numeric input). Submits to a new endpoint that checks `Hash::check($pin, auth()->user()->override_pin_hash)`.
  - **Correct PIN:** field unlocks for manual edit (JS removes `readonly`). On form submit, `overridden_by_user_id` = current user, `overridden_at` = now.
  - **Incorrect PIN:** inline error, field stays locked. No retry lockout (v1).
  - **No PIN set on the account:** modal instead asks for the account's login password, verified via `Hash::check($password, auth()->user()->password)`. On success, same unlock behavior, plus a flash message prompting the user to set a PIN in Account Settings.
- Recent logs table: rows with `overridden_by_user_id` set show a small badge — "Manually overridden by {name}".

**Explicit dependency / out of scope:** This spec does **not** include the Arduino → Raspberry Pi → `production_logs` data ingestion pipeline. That is a separate hardware-integration task. This feature only governs what happens to a value *once it's in the database* — whether it's locked for editing and how an override is authorized and recorded. Until the ingestion pipeline exists, a sensor-flagged cage's field will simply show `0` (or whatever was last written) until overridden.

**Bundled fix (same area, low risk):** `hens.flock_age_weeks` is currently a static number that goes stale. Add `hens.placement_date` (date, nullable) and `hens.age_at_placement_weeks` (unsigned int, nullable). Add a `getCurrentAgeWeeksAttribute()` accessor on `Hen`: if `placement_date` is set, compute `age_at_placement_weeks + floor(weeks elapsed since placement_date)`; otherwise fall back to the legacy static `flock_age_weeks` (for records that predate this change). Every view currently displaying `flock_age_weeks` directly switches to this computed accessor.

---

## 3. Add/Update Flock (replaces "Bulk Add Chickens")

**Current state (verified in `CageController`):** `store()` already optionally creates a single `Hen` record at cage-creation time, hardcoding `flock_age_weeks = 0` if a breed is supplied. There is **no** way to add or update flock info on a cage after creation, and no capacity validation.

**Change:**
- `CageController::store()` — accept `age_at_placement_weeks` (nullable int) alongside `breed`. When creating the `Hen` record, set `placement_date = now()` and `age_at_placement_weeks` from input (default `0`), instead of hardcoding `flock_age_weeks = 0`.
- New route + action: **Add/Update Flock** on an existing cage — `POST /cages/{cage}/flock`. Form: breed (dropdown, same enum as `Hen::breed`), age at placement in weeks (numeric input). Placement date is auto-set to "now" and shown read-only ("Placement Date: {today} (auto)"). Updates the cage's active `Hen` record (creates one if none exists, matching `getPrimaryHenAttribute()`'s `is_active=1` lookup).
- Capacity check: when entering `hen_count` in Egg Logging, if `hen_count > cage.capacity`, show an inline warning (not a hard block — capacity is advisory at the cage level, there's no per-slot hard ceiling to enforce).
- Workflow: after `cages.store` succeeds, show a confirm prompt — "Add flock details to {cage_code} now?" [Yes/No]. **Yes** opens the Add/Update Flock modal, pre-selected to the new cage. **No** just closes; flock can be added later from the cage's card in Cage Management.

**Out of scope:** the slot-grid visual picker and the repeating "add more slots? / create another cage?" loop — both assume a slot model that doesn't exist.

---

## 4. Sensor Flagging — Shared Component

**Change:**
- `cages.has_sensor` (added in section 2) drives this — no separate `cage_slots` table.
- Build one shared Blade partial, e.g. `resources/views/partials/cage-card.blade.php`, that renders a cage's identity block (code, location, color) plus a small green dot in the top-right corner when `has_sensor` is true.
- Reuse this partial in:
  - Dashboard cage overview
  - Cage Management (`cages/index.blade.php`)
  - Egg Logging form (cage selector area) — this is what visually signals the lock behavior from section 2
- Toggling `has_sensor` happens from Cage Management: a small inline toggle on each cage card ("Mark as sensor-equipped" / "Remove sensor"), `PUT /cages/{cage}` already exists and gets `has_sensor` added to its validated fields.

---

## 5. Override PIN — Account Settings

**Current state:** No Account Settings page exists at all — only login/logout.

**Change:**
- `users.override_pin_hash` (added in section 2).
- New page: Account Settings (`GET /account`, new controller or added to `AuthController`). Two independent sections:
  - **Change Password** (new, doesn't exist today either — current password is only seeded, never changed in-app)
  - **Override PIN:**
    - No PIN set: "Set Override PIN" — PIN + confirm PIN inputs, 4-6 digits, numeric only.
    - PIN already set: "Change Override PIN" — requires current PIN to confirm; "forgot PIN" falls back to login password verification instead.
    - Reject trivially weak PINs via a denylist: `0000, 1111, 2222, 3333, 4444, 5555, 6666, 7777, 8888, 9999, 1234, 4321, 0123, 1212`.
- PIN is per-user, never shared/global — stored only on that user's row.
- Admin-only staff list view (e.g. on a new `/account/staff` or appended to an existing admin area): shows each user's name + a boolean "PIN set?" indicator. Never exposes the PIN value (the hash is one-way; there is nothing to "view" even if someone tried).

**No new role/permission work needed** — `users.role` enum (`admin`/`operator`) and the `EnsureAdmin` middleware already exist and already gate this correctly.

---

## Database Changes Summary

| Table | Change |
|---|---|
| `cages` | + `has_sensor` (bool, default false), + `sensor_device_id` (string, nullable) |
| `hens` | + `placement_date` (date, nullable), + `age_at_placement_weeks` (unsigned int, nullable) |
| `production_logs` | + `overridden_by_user_id` (FK → users, nullable, null on delete), + `overridden_at` (timestamp, nullable) |
| `users` | + `override_pin_hash` (string, nullable) |
| `forecasts` | `cage_id` FK changed from required to **nullable** |

No existing columns are removed. No existing seed data is invalidated — all new columns are nullable or default to a safe value, so the 4 existing cages and their hen/production records keep working unchanged.

---

## Out of Scope (for this spec)

- `cage_slots` / `chickens` tables, row/slot grid UI, visual slot picker — would require manuscript revision first
- Per-row forecasting
- Arduino/Raspberry Pi → `production_logs` sensor ingestion endpoint (separate hardware-integration task)
- Retry lockout on PIN entry (explicitly v2 per original ask)
- Multiple sensors per cage (manuscript describes 1:1)
