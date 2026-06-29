# Slot-Grid Cage Model Migration — Design

**Date:** 2026-06-29
**Project:** LayRate Poultry Farm Management System
**Scope:** Replace the flat cage model (one row per cage, one sensor per cage) with a slot-grid model (rows × slots per cage, sensors and occupancy tracked per slot) — reversing the cage-level descoping decision made earlier in the project.

---

## Why this is happening now

The cage-level model was chosen because the capstone manuscript documented one IR break-beam sensor per cage, at a single egg-collection point, with HDEP computed per cage. Building a per-slot model on top of that would have made the working system contradict the manuscript and the actual wiring. That blocker is now resolved: the team has approved updating both the manuscript's hardware/methodology section and the physical sensor wiring to match a per-slot design. This spec covers the software side of that change.

**Data context:** all cages, hens, and logs currently in the database are mock/seed data — no real farm data exists yet. This authorizes a destructive drop-and-rebuild migration approach for the affected tables, rather than a careful reversible `ALTER`-based migration. This is a deliberate, authorized choice for this specific migration — it does not relax the standing rule against running unscoped destructive database commands in general (that rule stays in force everywhere else, following the incident earlier in this project where an unauthorized `migrate:fresh` permanently destroyed real accumulated data).

---

## Schema

### `cages` (modified)

- Keep: `cage_code`, `location`
- Add: `rows` (unsigned int), `slots_per_row` (unsigned int), `max_chickens_per_slot` (unsigned int)
- Remove: `capacity`, `has_sensor`, `sensor_device_id` (all move to slot level), `is_active` is kept (cage-level active/inactive still makes sense)
- `total_capacity` is **not a stored column** — a model accessor: `rows * slots_per_row * max_chickens_per_slot`. Matches the existing `Hen::current_age_weeks` pattern of computing rather than storing derivable values, so it can never drift out of sync.

### `cage_slots` (new)

- `cage_id` (FK → cages, cascade delete)
- `row_number` (unsigned int, 1-based)
- `column_number` (unsigned int, 1-based)
- `slot_number` (unsigned int, sequential across the whole grid — row 1 gets 1..slots_per_row, row 2 continues from there, etc.)
- `current_occupancy` (unsigned int, default 0)
- `has_sensor` (boolean, default false)
- `sensor_device_id` (nullable string, max 100) — a plain identifier, not a separate `sensors` table (same approach already used for the cage-level sensor field; no evidence yet that individual sensor metadata beyond an identifier is needed)
- `status` is **not a stored column** — a model accessor: `empty` (`current_occupancy == 0`), `full` (`current_occupancy >= cage.max_chickens_per_slot`), otherwise `partial`
- Unique constraint on (`cage_id`, `row_number`, `column_number`)

### `hens` (modified)

- Add: `cage_slot_id` (FK → cage_slots, cascade delete)
- Remove: `cage_id` (a hen's cage is reached via `cageSlot->cage`)
- Unchanged: `tag_code`, `date_acquired`, `flock_age_weeks` (legacy fallback), `placement_date`, `age_at_placement_weeks`, `breed`, `is_active`

### Logs — per-table routing

| Table | Keys off | Reasoning |
|---|---|---|
| `production_logs` | `cage_slot_id` (was `cage_id`) | Egg counts are now sensed per slot |
| `environmental_logs` | `cage_id` (unchanged) | A DHT22 reads ambient air for the whole cage — there is no physical per-slot temperature/humidity reading |
| `feed_consumption_logs` | `cage_id` (unchanged) | Feed is administered at cage level |
| `mortality_logs` | `cage_id` (unchanged) | Simpler; per-slot mortality tracking is a separate, larger feature not requested here |
| `alerts` | `cage_id` (unchanged) | Tied to environmental_logs, which stays cage-level |
| `forecasts` | `cage_id` (nullable, existing) **+ new nullable `row_number`** | `cage_id=null` → whole farm; `cage_id` set, `row_number=null` → per-cage; both set → per-row |

`production_logs`'s existing unique constraint moves from (`cage_id`, `log_date`) to (`cage_slot_id`, `log_date`).

### Migration approach

The migrations for `cages`, `cage_slots`, `hens`, and `production_logs` drop and recreate those tables directly (per the mock-data authorization above) rather than writing column-preserving `ALTER` migrations. `environmental_logs`, `feed_consumption_logs`, `mortality_logs`, `alerts` are untouched structurally (still `cage_id`); only `forecasts` gets an additive nullable `row_number` column via a normal migration (no rebuild needed there).

### Seeder

`DatabaseSeeder` is rewritten to create a small number of cages with real row × slot grids (e.g. 3 rows × 10 slots, matching the dimensions already used in the old decorative mockup grids so the visual scale feels familiar), hens assigned to specific slots with realistic breeds/ages, and 2-3 slots across different cages flagged `has_sensor = true` so the PIN-override flow has something to exercise immediately after a fresh seed.

---

## Controllers & Views

### `CageController`

- `store()` — replaces the flat cage_code/location/capacity/breed form with the Battery Cage Configuration modal: Cage Name, Rows, Slots per Row, Max Chickens per Slot, live Configuration Summary, Layout Preview. On submit: creates the cage, then bulk-inserts the full set of `cage_slots` rows for the grid.
- `update()` — same modal, pre-filled, titled "Edit Battery Cage Configuration," button reads "Save Changes." Increasing rows/slots/max-per-slot appends new empty slots (or raises capacity headroom) with no effect on existing slots. Decreasing any of the three is blocked with an inline error naming the specific affected slots (e.g. "Slot C-08 has 5 chickens and a sensor — remove or reassign them first") if any slot that would be removed or pushed over the new max has occupancy or a sensor. Edit does not trigger the "Add chickens now? / Create another cage?" flow — that stays creation-only.
- `destroy()` — gains the safety guard that was already an open gap before slots existed: before deleting, show what will be lost (hen count, slot count, sensor-equipped slot count, and counts across the cage-keyed log tables). Block deletion outright if any slot has occupancy or a sensor; require an explicit confirmation (typed cage code) otherwise if there's any historical log data.

### Shared slot-box component

One Blade partial renders a single slot: occupancy badge, green corner marker when `has_sensor`, click handlers for toggle/detail. Reused in three places: the Cage Management grid, the Layout Preview inside the Battery Cage Configuration modal (so editing shows real current state, not just the proposed new dimensions), and the Bulk Add slot picker.

### Bulk Add Chickens (new)

Modal: visual slot-grid picker (click or click-drag to select/deselect), Breed, Age at Placement (weeks), Placement Date (read-only, auto-today). Slots already at full capacity are visibly disabled with a tooltip; partially-occupied slots remain selectable with an inline note of their current count. Submission is hard-clamped to the most-constrained selected slot's remaining headroom.

### `EggLoggingController` / view

Per-slot cards replace the single per-cage form; a cage dropdown filters which slots are shown. The existing sensor-override mechanism (PIN-first, password fallback, server-side session flag scoped and time-limited, rate-limited verify endpoint, today-only lock) is unchanged in mechanism — every place it currently keys off `cage_id` switches to `cage_slot_id`.

### `ForecastController` / view

Adds a third scope alongside the existing Whole Farm / Per Cage: **Per Row**. Selecting a cage reveals a row selector; the aggregation sums `production_logs` (now slot-keyed) across that row's slots using the same `SUM(egg_count)/SUM(hen_count)*100` approach already used for whole-farm aggregation (not an average of per-slot percentages).

---

## Out of Scope

- Per-slot mortality tracking (mortality stays cage-level)
- A dedicated `sensors` table with device metadata beyond a plain identifier string
- Any change to the Account Settings / Override PIN feature — already built, unaffected by this migration
- Backfilling or preserving the current seed data through the schema change — it's mock data, the seeder is rewritten instead

---

## Risk Note

This migration is broader than the cage-level feature set built earlier in this project — that work caused a real data-loss incident during its first task (an implementer ran an unauthorized destructive reset against a shared database). This migration's destructive table rebuild is explicitly authorized this time, but every implementation task will still be dispatched with explicit, repeated boundaries on exactly which tables may be dropped/rebuilt (only the ones named in this spec) and an instruction to never run a blanket `migrate:fresh`/`db:wipe` even though some rebuilding is expected — the authorization is scoped to specific tables via specific migrations, not a blank check to reset the whole database.
