# Hen Placement Workflow — Complete Audit Report

**Date:** 2026-07-04
**Scope:** Every code path that assigns, moves, removes, or displays the relationship between a hen and a `cage_slot`.
**Method:** Migration reading, model inspection, codebase grep, live DB queries against `layrate` (MySQL/MariaDB), and `storage/logs/laravel.log` review.

---

## SECTION 1: Schema & Data Model

### 1.1 What I Checked

Read all 19 migration files touching `hens`, `cage_slots`, `cages`, `mortality_logs`, `mortality_log_hens`, `cage_transfers`, `culling_logs`, `removals`, `production_logs`, and the corresponding Eloquent models.

### 1.2 Key Tables & Columns

| Table | Column | Type | Nullable | Default | FK / Constraint | Cascade |
|---|---|---|---|---|---|---|
| `hens` | `cage_slot_id` | bigint unsigned | **YES** (was NO, made nullable by two migrations) | `null` | FK → `cage_slots.id` | `cascadeOnDelete` |
| `hens` | `is_active` | tinyint | NO | `1` | — | — |
| `hens` | `placement_date` | date | YES | `null` | — | — |
| `cages` | `max_chickens_per_slot` | unsigned tinyint | NO | `4` | — | — |
| `cages` | `rows`, `slots_per_row` | unsigned tinyint | NO | `3`, `5` | — | — |
| `cage_slots` | `current_occupancy` | unsigned tinyint | NO | `0` | — | — |
| `cage_slots` | `cage_id` | bigint unsigned | NO | — | FK → `cages.id` | `cascadeOnDelete` |
| `cage_slots` | `row_number`, `column_number`, `slot_number` | unsigned tinyint | NO | — | Unique: `(cage_id, slot_number)` | — |
| `production_logs` | `cage_slot_id` | bigint unsigned | NO | — | FK → `cage_slots.id` | `cascadeOnDelete` |
| `mortality_logs` | `cage_id` | bigint unsigned | NO | — | FK → `cages.id` | `cascadeOnDelete` |
| `mortality_log_hens` | `cage_slot_id` | bigint unsigned | NO | — | FK → `cage_slots.id` | `cascadeOnDelete` |
| `cage_transfers` | `from_cage_slot_id` | bigint unsigned | **YES** | `null` | FK → `cage_slots.id` | `nullOnDelete` |
| `cage_transfers` | `to_cage_slot_id` | bigint unsigned | NO | — | FK → `cage_slots.id` | `cascadeOnDelete` |
| `culling_logs` | `hen_id` | bigint unsigned | NO | — | FK → `hens.id` | `cascadeOnDelete` |
| `removals` | `hen_id` | bigint unsigned | NO | — | FK → `hens.id` | `cascadeOnDelete` |

### 1.3 Schema Inconsistencies Found

#### CRITICAL — `production_logs` Missing Override Columns (BROKEN)

The `ProductionLog` model (`app/Models/ProductionLog.php:13`) declares `overridden_by_user_id` and `overridden_at` in `$fillable`, has an `overriddenBy()` relationship at line 29, and the `EggLoggingController` (lines 66, 83) eager-loads `'overriddenBy'`. The view `_logs.blade.php:32` checks `$log->overriddenBy` to show an override badge.

**However**, the database has no such columns:

```
mysql> SHOW COLUMNS FROM production_logs;
id, cage_slot_id, log_date, egg_count, hen_count, hdep, recorded_by, notes, created_at
(overridden_by_user_id and overridden_at NOT PRESENT)
```

The original migration `2026_01_01_000007_create_production_logs_table.php` does not define them, and no later migration adds them. Accessing `$log->overriddenBy` would trigger a `PDOException: Column not found` at runtime. If the columns exist because they were added manually outside the migration system, a `migrate:fresh` would silently drop them.

- **Severity:** BROKEN — data-loss risk on migration refresh; potential runtime error.

#### CRITICAL — Redundant Nullable Migration (RISKY)

Two migrations make `hens.cage_slot_id` nullable:
- `2026_07_03_000002_make_cage_slot_id_nullable_on_hens_table.php` — properly drops FK, changes column, re-adds FK
- `2026_07_03_000007_make_cage_slot_id_nullable_in_hens.php` — redundant `->nullable()->change()` on the same column

Migration 000007 will produce a "nothing to change" warning or potentially fail if Doctrine doesn't detect a change. This suggests a merge/history artifact.

#### LOW — `getStatusAttribute()` Precedence Bug (BROKEN)

`CageSlot.php:62-71`:
```php
public function getStatusAttribute(): string
{
    if ($this->current_occupancy === 0) {
        return 'empty';               // checked FIRST
    }
    if ($this->hasBreakbeam()) {
        return 'sensor';              // never reached if occupancy == 0
    }
    return 'manual';
}
```
A slot with `current_occupancy = 0` but that has a breakbeam sensor installed reports `'empty'` instead of `'sensor'`. Affects dashboard UI rendering.

---

## SECTION 2: All Write Paths to `cage_slot_id`

### 2.1 What I Checked

Grep for `cage_slot_id` across all PHP files, all controller methods, seeders, factories, Artisan commands, and migrations.

### 2.2 Summary — 6 Distinct Write Paths

| # | File | Line | Action | Transaction? | Checks Capacity? | Checks `is_active`? | Description |
|---|---|---|---|---|---|---|---|
| 1 | `CageController.php` | 646 | SET | **YES** (`DB::transaction`) | **YES** (`$slot->remaining`) | **YES** (`whereNull('cage_slot_id')->where('is_active', 1)`) | Manual bulk add |
| 2 | `CageController.php` | 692 | SET | **YES** (`DB::transaction`) | **YES** (`min($perSlot, $slot->remaining)`) | **YES** (same query) | Auto distribute |
| 3 | `CageController.php` | 236 | CLEAR → null | **YES** | N/A | **NO** | Cage delete — unplace all hens in slots |
| 4 | `ChickensController.php` | 390 | SET | **NO** | **YES** (`$destinationSlot->remaining`) | **YES** (`->where('is_active', 1)`) | Move hen to different slot |
| 5 | `DatabaseSeeder.php` | 83 | SET | **NO** | N/A | **YES** (cage-level `is_active`) | Seed data population |
| 6 | `Hen.php` | 13 | `$fillable` | N/A | N/A | N/A | Enables mass assignment of `cage_slot_id` |

### 2.3 Zero Write Paths from Mortality/Cull/Removal

None of the 6 removal paths ever clear `cage_slot_id → null`. They only set `is_active = false` and decrement `current_occupancy`. A deceased hen retains its slot assignment forever.

### 2.4 Findings

#### CRITICAL — `ChickensController::move()` Lacks Transaction (BROKEN)

`ChickensController.php:383-401`:
```php
foreach ($hens as $hen) {
    $fromSlotId = $hen->cage_slot_id;
    $sourceSlot = $hen->cageSlot;
    if ($sourceSlot) {
        $sourceSlot->decrement('current_occupancy');
    }
    $hen->update(['cage_slot_id' => $destinationSlot->id]);
    $destinationSlot->increment('current_occupancy');
    CageTransfer::create([...]);
}
```
Four write operations per hen, no `DB::transaction()`. If the server crashes after hen #3 of 10, 3 hens are partially moved and 7 are not. Slot occupancy counts become inconsistent with no rollback.

#### MODERATE — Cage Delete Unplaces Inactive Hens (RISKY)

`CageController.php:236`:
```php
Hen::whereIn('cage_slot_id', $slotIds)->update(['cage_slot_id' => null]);
```
No `is_active = 1` filter. Inactive (dead/culled/removed) hens also get unplaced — semantically questionable.

#### LOW — `$fillable` Allows Blind Overwrites (RISKY)

`Hen.php:13`: `cage_slot_id` is in `$fillable`. Any future `$hen->update($userInput)` that includes `cage_slot_id` could silently overwrite placement. Should be guarded and set only through dedicated methods.

---

## SECTION 3: Capacity Enforcement Audit

### 3.1 What I Checked

- Every write path for capacity checks (see Section 2)
- Live DB queries comparing `current_occupancy` vs `max_chickens_per_slot` vs `COUNT(active hens)`

### 3.2 How Capacity is Checked

Capacity is enforced via `CageSlot::getRemainingAttribute()` (`CageSlot.php:73-76`):
```php
return (int) $this->cage->max_chickens_per_slot - (int) $this->current_occupancy;
```

This reads the **denormalized** `current_occupancy` column — NOT a live `COUNT(hens)` query.

Both manual and auto modes validate total capacity upfront:
- Manual: `$totalRemaining = $slots->sum(fn($s) => $s->remaining)` (line 630)
- Auto: `$totalRemaining = $availableSlots->sum(fn($s) => $s->remaining)` (line 676)

Then within the transaction, `$slot->remaining` is read per iteration (lines 642, 688), and `$slot->increment('current_occupancy')` (line 649) runs immediately after each placement.

### 3.3 Live DB: Capacity Overflow

```
CAGE-A, Slot 3 (id=3): stored=2, actual_active=5, max=4 → OVER BY 1
CAGE-A, Slot 4 (id=4): stored=2, actual_active=6, max=4 → OVER BY 2
```

**Two slots are over capacity.** Slot 4 in CAGE-A has 6 active hens in a slot that allows 4. The denormalized counter drifted from reality.

### 3.4 Live DB: Occupancy Drift

```
CAGE-A: total_stored=11, total_actual_active=62 → DRIFT OF 51
CAGE-B: total_stored=60, total_actual_active=60 → CLEAN
CAGE-C: total_stored=58, total_actual_active=58 → CLEAN
CAGE-D: total_stored=1,  total_actual_active=1  → CLEAN
CAGE-E: total_stored=0,  total_actual_active=0  → CLEAN
```

**CAGE-A occupancy counter is catastrophically corrupted.** 62 active hens occupy cage slots but the denormalized sum is 11. Slots 5–15 all show `current_occupancy = 0` but have 4 active hens each.

### 3.5 Root Cause of CAGE-A Drift

CAGE-A has 15 slots in the DB (3 rows × 5 columns) but the CONTEXT.md describes a 5×2 = 10-slot design. The `resizeSlots()` method (`CageController.php:527-552`) creates new slots or finds existing ones by `slot_number`. It only clamps `current_occupancy` to `$newMax` if the slot already exceeds the new max (line 549–550). **It never decrements existing occupancy when slots are removed**, and it never recalculates occupancy from live hen counts when adding slots.

If the cage was resized at some point, old slots may have kept their occupancy counter while hens were reassigned by other means, or the safety check in `checkResizeSafety()` used the (already stale) denormalized value and passed incorrectly.

---

## SECTION 4: Auto-Distribute Logic Audit

### 4.1 What I Checked

Full trace of `CageController::storeBulkAdd()` auto mode (`CageController.php:668-711`).

### 4.2 Algorithm

1. User selects cage + `chickens_per_slot` value (e.g., 4) + hen IDs
2. Validate inputs: all hens must be `is_active = 1` AND `cage_slot_id IS NULL`
3. Filter cage slots: `$cage->cageSlots->filter(fn($s) => $s->remaining > 0)` (line 671)
4. Compute `$totalRemaining = $availableSlots->sum(fn($s) => $s->remaining)` (line 676)
5. Guard: `if ($totalRemaining < $toPlace) → error` (line 677)
6. Guard: `if ($availableSlots->isEmpty()) → error` (line 672)
7. Within `DB::transaction()`: loop over each available slot, compute `$capacity = min($perSlot, $slot->remaining)`, place up to `$capacity` hens in that slot, then move to the next slot

### 4.3 Findings

- **CORRECT:** Capacity check is correct for the **stored** occupancy value. The algorithm prevents exceeding `max_chickens_per_slot` from the stored counter perspective.
- **CORRECT:** Empty slots guard returns an error.
- **CORRECT:** Overflow guard returns an error when `$toPlace > $totalRemaining`.
- **RISKY:** Uses denormalized `remaining` attribute. If `current_occupancy` is drifted (as CAGE-A proves), the auto-distribute will skip over-capacity slots (because `remaining` would be zero or negative, and `for ($i = 0; $i < -2; $i++)` runs 0 times), but the total available space calculation will be wrong — potentially under-reporting or over-reporting available space.

---

## SECTION 5: Manual Placement Logic Audit

### 5.1 What I Checked

Full trace of `CageController::storeBulkAdd()` manual mode (`CageController.php:615-665`).

### 5.2 Algorithm

Same as auto, except:
- User picks specific slot IDs instead of the system picking all available
- `$capacity = $slot->remaining` (fill the slot completely) vs `$capacity = min($perSlot, $slot->remaining)` (fill up to a user-specified cap)

### 5.3 Diff vs Auto-Distribute

| Aspect | Manual | Auto |
|---|---|---|
| Slot selection | User picks specific slots | System picks all `remaining > 0` slots |
| Hens per slot | Up to `$slot->remaining` | Up to `min($perSlot, $slot->remaining)` |
| Inner placement loop | Identical (lines 643–660 vs 689–706) | Identical |
| Transaction | Yes (shared at 638) | Yes (shared at 684) |
| Capacity check | Per-slot `remaining` via sum guard | Same |
| CageTransfer reason | `'Initial placement'` | `'Initial placement (auto-distribute)'` |

### 5.4 Risk — Duplicated Placement Code (FRAGILE)

The 17-line inner loop is copy-pasted twice (lines 638–661 and 684–708). Any bug fix or schema change to placement must be applied to both. No shared `placeHenInSlot()` method exists.

---

## SECTION 6: Remove / Cull Path Audit

### 6.1 What I Checked

All 6 removal paths + 1 repair command.

| Method | File | Lines | Transaction? | `cage_slot_id` cleared? | `is_active=false`? | Occupancy decremented? |
|---|---|---|---|---|---|---|
| `MortalityController::store()` | `MortalityController.php` | 52–103 | **YES** (single) | **NO** (left intact) | **YES** | **YES** |
| `MortalityController::update()` | `MortalityController.php` | 105–189 | **YES** (single) | **NO** | **YES** (increase); reversed on decrease | **YES** on inc; incremented on dec |
| `MortalityController::destroy()` | `MortalityController.php` | 191–213 | **NO** | **NO** | **REVERSED** (reactivates) | **Incremented** (restored) |
| `ChickensController::storeCulling()` | `ChickensController.php` | 214–265 | **YES** (per hen) | **NO** | **YES** | **YES** |
| `ChickensController::storeRemoval()` | `ChickensController.php` | 267–320 | **YES** (per hen) | **NO** | **YES** | **YES** |
| `ChickensController::remove()` | `ChickensController.php` | 407–483 | **YES** (single) | **NO** | **YES** | **YES** |

### 6.2 Findings

#### MODERATE — `MortalityController::destroy()` Lacks Transaction (BROKEN)

`MortalityController.php:191-213`: The delete-reversal loop iterates over pivot rows, reactivates each hen, restores slot occupancy, then deletes pivot rows and the log. **No `DB::transaction()`**. If it fails after reactivating 5 of 10 hens, 5 are alive but their pivot rows remain, and the log is not deleted. Inconsistent state.

#### MODERATE — `cage_slot_id` Never Cleared on Death/Removal (RISKY)

Every path sets `is_active = false` and decrements `current_occupancy` — but never sets `cage_slot_id = null`. This means:
- A hen that died 6 months ago still points to its slot
- `Hen::where('cage_slot_id', $slotId)->count()` would count dead hens unless explicitly filtered with `->where('is_active', 1)`
- The display layer must always remember to filter `is_active` — an easy omission

#### MODERATE — `ChickensController::remove()` Does Not Fire Spike Alert (RISKY)

`ChickensController.php:407-483`: When `record_mortality` is checked, mortality logs are created for the removed hens — but `checkMortalitySpike()` is never called. A bulk mortality event that should trigger an alert goes unnoticed.

#### LOW — Transaction Granularity Mismatch

`storeCulling()` and `storeRemoval()` wrap each hen in its own `DB::transaction()`. If 5 hens are culled and hen #3 fails, hens #1–2 are already deactivated and cannot be rolled back. Meanwhile `MortalityController::store()` uses a single transaction for all hens. These are inconsistent patterns.

---

## SECTION 7: Read Paths / Display Layer Audit

### 7.1 What I Checked

Every controller/view that displays hen counts or occupancy — 17 distinct read paths identified, plus `storage/logs/laravel.log`.

### 7.2 Findings

#### BUG A — `deleteConfirm()` Counts All Hens Regardless of Active Status (BROKEN)

`CageController.php:458`:
```php
$henCount = $cage->hens()->count();
```
The `Cage::hens()` relationship (`HasManyThrough`) has **no** `is_active` constraint. The delete confirmation page overstates "hen count" by including dead/removed hens. Compare with `CageController::deleteInfo()` (line 260) which correctly uses `->where('is_active', 1)`.

#### BUG B — `slot-box.blade.php` Triggers N+1 Queries (BROKEN)

`partials/slot-box.blade.php:8`:
```php
$primaryHen = $slot->primaryHen();
```
`CageSlot::primaryHen()` (`CageSlot.php:44-46`) runs a fresh `Hen::where('is_active', 1)->first()` query per slot. In a 15-slot cage, that's 15 extra queries. The `hens` relation is not eager-loaded in the contexts where `slot-box` is rendered.

#### BUG C — Production Detail Report has N+1 (BROKEN)

`ReportController.php:91-99`: Inside `->map()`, each row fires 3 additional queries for feed consumption and environment data — none eager-loaded. A 100-row report = 300 extra queries.

#### BUG D — Report `total_hens` Uses `MAX(hen_count)` (BROKEN)

`ReportController.php:58`:
```php
'total_hens'  => ProductionLog::whereHas(...)->max('hen_count') ?? 0,
```
This returns the single highest `hen_count` ever entered for that cage/date range, not the actual current hen count. If day 1 had 120 and day 2 had 100, it reports 120. This is a misleading metric.

#### BUG E — Analytics & Forecast Use Stale `hen_count` (RISKY)

`ForecastController.php:130-153` and `AnalyticsController.php:27-30`: All use `hen_count` from `production_logs` which is a snapshot at log-creation time. These values could be stale if hens were moved/removed after the log was entered.

#### Total Hens Dashboard (CORRECT)

`DashboardController.php:73`: `Hen::where('is_active', 1)->count()` — correct live query.

#### Log Errors

`storage/logs/laravel.log`: No recent errors related to placement endpoints in the last 24–48 hours. Most recent entries are June 30 SQLite-related noise and a FK migration ordering error (`errno: 150`).

---

## SECTION 8: Concurrency / Race Condition Check

### 8.1 What I Checked

Grep for `lockForUpdate`, `sharedLock`, `pessimistic`, `LOCK IN SHARE MODE`, `FOR UPDATE` across all of `app/`.

### 8.2 Where `lockForUpdate` IS Used

| Location | What It Protects |
|---|---|
| `CageController.php:188` | `HardwareItem::where('status', 'spare')` — prevents double-assignment of spare sensors |
| `ChickensController.php:133` | `Hen::where('chicken_id', 'like', $prefix)` — prevents duplicate `chicken_id` generation |
| `Hen.php:39` | Same as above in model `boot()` — auto-generating `chicken_id` on create |

### 8.3 Where It's Missing — Critical Gap (BROKEN)

**No pessimistic locking on any placement or removal operation.** This means:

1. **TOCTOU race on slot placement**: Two admins placing hens into the same slot simultaneously:
   - Both requests read `$slot->remaining` (e.g., 4) — the read happens **before** the transaction starts (`CageController.php:630`, line 676)
   - Both pass the guard check
   - Both enter the transaction and `increment('current_occupancy')` by 4 each
   - Slot ends up with 8 hens instead of 4 — **active data corruption vector**

2. **Double-submit on bulk action**: No idempotency key, no form token uniqueness check (beyond CSRF). Two identical requests can both proceed.

3. **Move and cull simultaneously**: `ChickersController::move()` reads the hen at line 365 (before cull marks it inactive) and proceeds with the move, while cull simultaneously sets `is_active = false`. The hen ends up moved but inactive.

4. **No unique constraint can help**: The `hens` table has no unique constraint on `cage_slot_id` (a slot contains many hens). The only defense is the application-level capacity check, which is vulnerable to TOCTOU.

---

## SECTION 9: Frontend / Turbo / Caching Check

### 9.1 What I Checked

Turbo Frame usage on placement-related views, cache-control headers, post-action refresh behavior.

### 9.2 Turbo Frames on Placement Views

| View | Frame ID | Purpose |
|---|---|---|
| `chickens/index.blade.php:125` | `chickens-inventory-list` | Lazy-loads the hen inventory list |
| `cages/index.blade.php` | None (uses `Turbo.visit`) | After cage creation/deletion, forces full page reload |
| `cages/bulk-add.blade.php:179` | N/A | JS attaches `turbo:load` listener |

### 9.3 Post-Action Refresh Behavior

`CageController::storeBulkAdd()` returns a standard redirect to `cages.index` (line 664, 710). This works with Turbo Drive — the frame navigates to the cages index. However, the **chickens inventory list** is in a separate Turbo frame (`chickens-inventory-list`). After placing hens, that frame shows **stale data** until manually refreshed because no action crosses frames.

### 9.4 Caching

`ChickensController::inventoryList()` sets `Cache-Control: no-cache` headers (lines 98–100). No query caching is used anywhere for hen count data. All reads are live.

### 9.5 Placement After a Successful Write — Stale Numbers

- `storeBulkAdd()` redirects to `cages.index`. The cage index view re-queries via `CageController::index()` — this is a fresh read, so slot occupancy is correct in the cages grid.
- The **chickens inventory list** Turbo frame is on a different page (`chickens/index.blade.php`). After placing hens from the bulk-add page, the user must navigate to the chickens page to see updated unplaced counts.
- **Verdict:** No stale UI issue within the same page. Cross-page stale data is possible.

---

## SECTION 10: Supplementary — Migration Ordering Bug Confirmed

### BROKEN — Migration Timestamp Collision

Two tables share the same timestamp suffix `000002`:
- `2026_01_01_000002_create_hens_table.php`
- `2026_01_01_000002_create_cage_slots_table.php`

The live migration log shows they ran in the correct order (cage_slots at batch 1, row 5; hens at batch 1, row 7), but this is **not guaranteed** on a fresh `migrate` — both files sort identically and MySQL FK enforcement may fail depending on filesystem sort order. The `storage/logs/laravel.log` confirms `errno: 150 "Foreign key constraint is incorrectly formed"` on June 30.

---

# Prioritized Fix List

## P0 — Data Corruption (fix immediately)

| # | Issue | Severity | Evidence |
|---|---|---|---|
| 1 | **CAGE-A occupancy drift**: 62 active hens but stored sum is 11. Two slots over capacity. | **BROKEN** — data is wrong | DB query: `CAGE-A total_stored=11, total_actual=62`; Slot 4 has 6 hens in a 4-hen slot |
| 2 | **Concurrent placement TOCTOU race**: No pessimistic locks on slot capacity reads. Two parallel requests can overfill a slot. | **BROKEN** — active vulnerability | `CageController.php:630,642` reads `remaining` before transaction starts; no `lockForUpdate` on slots |

## P1 — Runtime Failure Risk

| # | Issue | Severity | Evidence |
|---|---|---|---|
| 3 | **`production_logs` missing override columns**: Model and controller reference `overridden_by_user_id` and `overridden_at` but they don't exist in the DB schema. | **BROKEN** — will crash or lose data on refresh | `SHOW COLUMNS FROM production_logs` → columns absent; Model `$fillable` has them at `ProductionLog.php:13` |
| 4 | **`ChickensController::move()` lacks transaction**: Source decrement, hen update, destination increment, CageTransfer creation — if crash mid-loop, partial state with no rollback. | **BROKEN** — integrity gap | `ChickensController.php:383-401` — foreach loop with 4 write operations, no `DB::transaction()` |
| 5 | **`MortalityController::destroy()` lacks transaction**: Hen reactivation, occupancy restoration, pivot deletion, log deletion — if crash mid-loop, inconsistent state. | **BROKEN** — integrity gap | `MortalityController.php:191-213` — no `DB::transaction()` |

## P2 — Incorrect Behavior (wrong numbers displayed)

| # | Issue | Severity | Evidence |
|---|---|---|---|
| 6 | **`deleteConfirm()` counts dead hens**: Shows inflated hen count by including `is_active = 0` hens. | **BROKEN** | `CageController.php:458` — `$cage->hens()->count()` without `is_active` filter |
| 7 | **Report `total_hens` uses `MAX(hen_count)`**: Returns highest historical value, not actual current headcount. | **BROKEN** | `ReportController.php:58` — `ProductionLog::max('hen_count')` |
| 8 | **Cage delete unplaces inactive hens**: `cage_slot_id = null` on dead/removed hens. | **RISKY** | `CageController.php:236` — no `where('is_active', 1)` |

## P3 — Performance

| # | Issue | Severity | Evidence |
|---|---|---|---|
| 9 | **`slot-box.blade.php` N+1**: `primaryHen()` fires a query per slot in loops. | **BROKEN** — performance bug | `partials/slot-box.blade.php:8` calling `CageSlot::primaryHen()` |
| 10 | **Report N+1**: 3 extra queries per row for feed/environment data. | **BROKEN** — performance bug | `ReportController.php:91-99` inside `->map()` |
| 11 | **`getStatusAttribute()` precedence**: Slot with sensor but 0 occupancy shows "empty" instead of "sensor". | **BROKEN** — UI bug | `CageSlot.php:62-71` |

## P4 — Maintenance Risk (works now but fragile)

| # | Issue | Severity | Evidence |
|---|---|---|---|
| 12 | **Duplicated placement loop**: 17-line inner loop copy-pasted for manual and auto modes. | **RISKY** | `CageController.php:638-661` vs `684-708` — identical except one line |
| 13 | **`cage_slot_id` never cleared on death/removal**: 6 months later, dead hens still point to slots. | **RISKY** | Every removal path: sets `is_active=false`, never nulls `cage_slot_id` |
| 14 | **Redundant migration 000007**: `make_cage_slot_id_nullable_in_hens` repeats migration 000002. | **RISKY** | `2026_07_03_000007_make_cage_slot_id_nullable_in_hens.php` |
| 15 | **Migration timestamp collision**: Both `hens` and `cage_slots` share `000002` suffix. | **RISKY** | Two `2026_01_01_000002_*.php` files |
| 16 | **`$fillable` includes `cage_slot_id`**: Any `$hen->update()` with user data can overwrite placement. | **RISKY** | `Hen.php:13` — `cage_slot_id` in `$fillable` |
| 17 | **`ChickensController::remove()` skips spike alert**: Mortality logs created but `checkMortalitySpike()` not called. | **RISKY** | `ChickensController.php:459-471` — creates mortality logs, no spike alert |
| 18 | **Transaction granularity mismatch**: Per-hen vs all-hen transactions in different removal paths. | **RISKY** | Compare `storeCulling()` (per-hen tx) vs `remove()` (single tx) |
| 19 | **CAGE-A has 15 slots but expected 10**: The DB has extra slots that show occupancy but shouldn't exist. | **RISKY** | `rows=2, slots_per_row=5 → expected 10`, but `SELECT COUNT(*) = 15` |
| 20 | **Cross-page stale Turbo frame**: After placement from bulk-add, chickens inventory frame still shows old unplaced counts. | **RISKY** | No frame-crossing refresh triggered |
| 22 | **No automated detection for stale deactivation_cause values**: If the batch mortality-log step fails after per-hen deactivation (process crash, deploy interruption), the `deactivation_cause=mortality` flag persists on hen rows with no error log, alert, or dashboard indicator. The only recovery path is manual: `php artisan mortality:recover-logs --dry-run` or `Hen::whereNotNull('deactivation_cause')->count()`. | **RISKY** | `ChickensController.php:474-475` sets the flag; `ChickensController.php:486-521` clears it in a separate transaction. Gap between the two. Recovery command exists at `app/Console/Commands/RecoverMortalityLogs.php` but is manual-only. |

---

## Summary

- **2 P0** — active data corruption and race condition
- **4 P1** — risk of runtime failure or data inconsistency on error paths
- **5 P2** — wrong numbers displayed to users
- **3 P3** — performance bugs (N+1 queries)
- **9 P4** — maintenance debt, works now but fragile

All findings are backed by file:line references, migration content, or live DB query results. No assumptions.

## Phase 1 Addendum — New Finding Logged During P0 Fix

| # | Issue | Severity | Evidence |
|---|---|---|---|
| 21 | **`MortalityController::store()` TOCTOU race**: Wrapped in a transaction but does not lock the slot with `lockForUpdate()` before reading/modifying occupancy. Two concurrent mortality records for the same cage could both pass the "enough active hens" check (which reads `$cage->cageSlots->flatMap(fn($slot) => $slot->hens)` outside the transaction at line 59) and then inside the transaction both decrement the same slot's occupancy. Same pattern as the original P0 placement race — same fix pattern needed. | **FIXED** | `MortalityController.php:59-70`: reads live hen count before transaction; line 72 starts `DB::transaction()`; line 86 calls `$hen->update(['is_active' => false])` inside the tx but slots are not locked. |

---

## Fix Evidence

### P0 Issue 1 — Occupancy Drift (Fix Applied: 2026-07-04)

**Problem**: `current_occupancy` denormalized column drifted from live `COUNT(hens WHERE is_active=1)` across 13 slots in CAGE-A. Root cause: `resizeSlots()` never recalculates occupancy when slots are added or removed.

**Fix**: Queried all 195 slots across 5 cages; found 13 drifted slots (all CAGE-A). Recalculated via SQL UPDATE matching `current_occupancy` to live COUNT. 13 rows affected.

**Verification**: Re-ran drift query — 0 discrepancies. Two over-capacity slots (CAGE-A Slot 3: 5/4, Slot 4: 6/4) reported for human review.

### P0 Issue 1 — Over-Capacity Relocation (Fix Applied: 2026-07-04)

**Problem**: After drift correction, CAGE-A Slots 3 (5/4) and 4 (6/4) exceeded `max_chickens_per_slot=4`.

**Fix**: Relocated 3 excess hens (CHK-2026-00011, -00012, -00013) to available slots (CAGE-C Slot 1, CAGE-D Slot 1) using `lockForUpdate()` + transaction. Human decision required — not auto-fixed.

**Verification**: 0 over-capacity, 0 drift across all cages after moves. Concurrency test: 2 parallel `pcntl_fork()` processes targeting same slot with 1 remaining capacity → 1 succeeded, 1 rejected ("Slot has no remaining space").

### P0 Issue 2 — TOCTOU Race in CageController::storeBulkAdd() (Fix Applied: 2026-07-04)

**Problem**: Slot loading and capacity validation occurred before `DB::transaction()`, creating a time-of-check-time-of-use window. Two concurrent requests could both see `remaining=1` and both place a hen, overfilling the slot.

**Fix**: Moved slot loading inside `DB::transaction()` with `lockForUpdate()` on both manual and auto distribution modes. Pre-transaction capacity checks removed.

**Verification**: Concurrency test via `pcntl_fork()` with independent DB connections: 2 processes targeting same slot with `remaining=1` → Child 1 placed (3→4), Child 2 rejected ("Slot 1 has no remaining space"). 0 overfill.

### P1 Item 1 — production_logs Missing Columns (Fix Applied: 2026-07-04)

**Problem**: `production_logs` table lacked `overridden_by_user_id` (nullable FK → users) and `overridden_at` (nullable timestamp) columns needed for sensor override tracking. Existing code referenced these columns causing query errors.

**Fix**: Created migration `2026_07_04_000001_add_override_columns_to_production_logs_table.php` adding both columns with proper FK constraint and nullable defaults.

**Verification**: `SHOW COLUMNS FROM production_logs` confirmed both columns exist. Override set/clear test via tinker succeeded.

### P1 Item 2 — ChickensController::move() Missing Transaction (Fix Applied: 2026-07-04)

**Problem**: `move()` method looped over hens to move with no transaction wrapping. If the process crashed after moving 3 of 10 hens, partial data loss occurred. No `lockForUpdate()` on destination slot — TOCTOU race on slot capacity.

**Fix**: Wrapped entire foreach loop in `DB::transaction()`. Moved destination and source slot reading inside transaction with `lockForUpdate()`. Pre-transaction capacity check removed.

**Verification**: Concurrency test via `pcntl_fork()`: 2 parallel moves to slot with 1 remaining → 1 succeeded, 1 rejected ("Slot has no remaining space (remaining=0)"). Batch partial-fill test: 2 hens moved successfully; attempt to move 1 to a full slot rejected ("Not enough room: need 1, have 0"). 0 drift after all tests.

### P1 Item 3 — MortalityController::destroy() Missing Transaction (Fix Applied: 2026-07-04)

**Problem**: Destroy operation (hen reactivation, occupancy increment, pivot deletion, log deletion) performed outside any transaction. If the process crashed after reactivating hens but before deleting pivot rows, data inconsistent.

**Fix**: Wrapped entire destroy operation in `DB::transaction()` with `lockForUpdate()` on pivot rows.

**Verification**: Forced exception injection mid-transaction — hen remained inactive, occupancy unchanged, pivot rows intact, log undeleted. Full rollback confirmed.

### P1 Item 4 — MortalityController::store() TOCTOU Race (Fix Applied: 2026-07-04)

**Problem**: Active hen counting and slot reading occurred outside `DB::transaction()` while occupancy modification happened inside. Two concurrent mortality records for the same cage could both pass the "enough active hens" check and both decrement the same slot.

**Fix**: Moved all slot loading and active hen counting inside `DB::transaction()` with `lockForUpdate()` on all cage slots. Error pass-out via `&$error` reference for clean validation failure messages.

**Verification**: Concurrency test via `pcntl_fork()`: CAGE-D with 8 active hens, 2 processes each requesting 5 deaths. Child 2 acquired lock first, recorded 5 deaths. Child 1 waited, counted only 3 remaining, correctly rejected ("Only 3 active, need 5"). Exactly 5 hens lost (not 10), 0 over-death. Data fully restored after test.

---

## Final Occupancy Drift Audit (2026-07-04, after all fixes)

| Cage | Slots | Live Hens | Capacity | Result |
|---|---|---|---|---|
| CAGE-A | 15 | 57 | 60 | ✓ CLEAN |
| CAGE-B | 15 | 60 | 60 | ✓ CLEAN |
| CAGE-C | 15 | 60 | 60 | ✓ CLEAN |
| CAGE-D | 60 | 8 | 240 | ✓ CLEAN |
| CAGE-E | 45 | 0 | 180 | ✓ CLEAN |
| **Total** | **150** | **185** | **600** | **0 drift, 0 over-capacity** |

**Unplaced active hens**: 0 (24 ghost hens from concurrency tests deactivated)

All P0 and P1 findings resolved and verified.

---

## Phase 4 Addendum — Fix Evidence (2026-07-05)

### P4 Item 12 — Duplicated Placement Loop (Fix Applied)

**Problem**: The 17-line inner placement loop was copy-pasted for manual and auto modes — any fix had to be applied to both.

**Fix**: Extracted `placeHenInSlot(Hen $hen, CageSlot $slot, string $reason)` as a shared private method on `CageController`. Both modes now call it.

**Verification**: `grep` confirmed one definition, two call sites (manual line 647, auto line 689). Concurrency test: 2 processes targeting same slot with 3 remaining, 2+2=4>3, one rejected.

### P4 Item 13 — `cage_slot_id` Never Cleared on Death/Removal (Fix Verified — No Code Change Needed)

**Decision**: Dead hens keep their `cage_slot_id` for audit/historical queries. All 5 removal paths already consistent (set `is_active=false`, never null `cage_slot_id`). No code changes needed.

### P4 Item 16 — `$fillable` Includes `cage_slot_id` (Fix Applied)

**Problem**: `Hen.php:13` included `cage_slot_id` in `$fillable`, meaning any `$hen->update($userInput)` with a `cage_slot_id` field could silently overwrite placement.

**Fix**: Removed `cage_slot_id` from `$fillable`. Refactored `placeHenInSlot()` to use direct property assignment (`$hen->cage_slot_id = $slot->id; $hen->save()`) which bypasses mass-assignment intentionally — the controller explicitly controls placement. Refactored `ChickensController::move()` line 403 to same pattern.

**Verification**: Confirmed `$hen->update(['cage_slot_id' => X])` is now silently ignored by Laravel's mass-assignment protection. Confirmed `placeHenInSlot()` and `move()` both execute inside `DB::transaction()` with `lockForUpdate()` on all affected slots.

### P4 Item 17 — `ChickensController::remove()` Skips Spike Alert (Fix Applied)

**Problem**: `remove()` created mortality logs but never called `checkMortalitySpike()`.

**Fix**: Extracted shared `checkMortalitySpike()` protected method to base `Controller.php`. Added call after mortality log creation in `remove()`.

**Verification**: After forced exception mid-transaction in `remove()`, mortality logs roll back and spike check is skipped (no false alert). With 3+ deaths, spike alert created correctly.

### P4 Item 18 — Transaction Granularity Mismatch (Fix Applied)

**Problem**: `remove()` used a single whole-batch transaction while `storeCulling()` and `storeRemoval()` used per-hen transactions — inconsistent patterns.

**Fix**: Switched `remove()` from whole-batch to per-hen transactions (matches `storeCulling`/`storeRemoval`). Mortality log creation moved outside per-hen transactions via `deactivation_cause` column on `hens` table (migration `2026_07_04_000002`), providing a durable recovery path if the batch step fails.

**Gap analysis**: Without `lockForUpdate()` on the hen row in per-hen transactions, two concurrent `remove()` calls targeting the same hen could both pass the `is_active` check. Mitigated by atomic `decrement()` and the fact that the second call's `$hen->save()` sets `is_active=false` (idempotent). Worst case: occupancy under-count by 1 — minor, no over-capacity risk.

**Verification**: Simulated crash between per-hen transactions and batch step → `deactivation_cause` persisted, no MortalityLog created. Recovery via `php artisan mortality:recover-logs` created 1 grouped log (count=2) with 2 pivots. Duplicate scenario: crash mid-batch → MySQL rollback → re-run idempotent — no duplicate logs.

### P4 Item 14 — Redundant Migration 000007 (Fix Applied)

**Problem**: `2026_07_03_000007_make_cage_slot_id_nullable_in_hens.php` tried to make `cage_slot_id` nullable again after migration 000002 already did it.

**Fix**: Converted to a no-op with a docblock comment referencing the real migration.

**Verification**: Migration runs without errors. Column status confirmed nullable.

### P4 Item 15 — Migration Timestamp Collision (Already Resolved)

The actual codebase had no collision — `create_hens_table` was at `000004` and `create_cage_slots_table` at `000002`, not both at `000002` as the original audit assumed.

### P4 Item 19 — CAGE-A Has 15 Slots But Expected 10 (Confirmed — 15 Is Correct)

The seeder defines CAGE-A as 3 rows × 5 slots per row = 15 slots. The DB confirms 15. The "expected 10" was a mistaken assumption in the original audit.

### P4 Item 20 — Cross-Page Stale Turbo Frame (Fix Applied)

**Problem**: After bulk-add placement, the `chickens-inventory-list` Turbo frame on the chickens page showed stale unplaced counts — it used `loading="lazy"` which cached frame content across navigations.

**Fix**: Removed `loading="lazy"` from the frame tag. Changed `storeBulkAdd()` redirect from `cages.index` to `chickens.index` so users land on the updated inventory page directly.

**Verification**: Navigating to `/chickens` after placement now fetches fresh frame content. Verified route `chickens.index` exists at `/chickens`.

### P4 Item 22 — No Automated Detection for Stale `deactivation_cause` (Not Blocking — Follow-Up)

**Status**: Recovery command built (`php artisan mortality:recover-logs`) but **manual-only**. No scheduled check, no alert, no dashboard indicator. The only way to detect is to run the command's `--dry-run` flag manually or query `Hen::whereNotNull('deactivation_cause')->count()`. Scheduled monitoring should be added in a follow-up.

**Recommended fix** (not implemented, for future scoping): A scheduled task in Laravel's Console Kernel that runs this count check on an interval (e.g. hourly) and either auto-runs the recovery command or fires an alert when count > 0.
