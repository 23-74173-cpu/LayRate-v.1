# Pi Schema Drift Fix — 2026-07-20

Run the **Verification** section first on the live Pi database.  
Then run only the **Fix** sections that the verification shows are needed.  
Do not run anything from "Backfill Investigation" without explicit sign-off.

---

## Target Schemas (Expected Final State)

### `production_logs` — Expected Columns

| # | Column | Type | Nullable | Default | FK / Notes |
|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned PK | NO | auto_increment | |
| 2 | `cage_slot_id` | bigint(20) unsigned | **YES** | NULL | FK → `cage_slots(id)` ON DELETE CASCADE (made nullable by `2026_07_05_000001`) |
| 3 | `log_date` | date | NO | | |
| 4 | `egg_count` | int(10) unsigned | NO | 0 | |
| 5 | `hen_count` | int(10) unsigned | NO | 0 | |
| 6 | `hdep` | decimal(5,2) | NO | 0 | |
| 7 | `recorded_by` | bigint(20) unsigned | YES | NULL | FK → `users(id)` ON DELETE SET NULL |
| 8 | `overridden_by_user_id` | bigint(20) unsigned | YES | NULL | FK → `users(id)` ON DELETE SET NULL (added by `2026_07_04_000001`) |
| 9 | `overridden_at` | timestamp | YES | NULL | Added by `2026_07_04_000001` |
| 10 | `notes` | text | YES | NULL | |
| 11 | `logged_via` | enum('manual','sensor','unknown') | NO | 'unknown' | Added by `2026_07_07_113825` |
| 12 | `created_at` | timestamp | NO | CURRENT_TIMESTAMP | |

Unique: (`cage_slot_id`, `log_date`)

**Source migrations (in order):**
1. `2026_01_01_000007_create_production_logs_table.php`
2. `2026_07_04_000001_add_override_columns_to_production_logs_table.php`
3. `2026_07_05_000001_make_log_fk_columns_nullable.php` (makes `cage_slot_id` nullable)
4. `2026_07_07_113825_add_logged_via_to_production_logs_table.php`

---

### `hens` — Expected Columns

| # | Column | Type | Nullable | Default | FK / Notes |
|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned PK | NO | auto_increment | |
| 2 | `chicken_id` | varchar(20) | YES | NULL | UNIQUE (added by `2026_07_03_000001`) |
| 3 | `cage_slot_id` | bigint(20) unsigned | **YES** | NULL | FK → `cage_slots(id)` ON DELETE CASCADE (made nullable by `2026_07_03_000002`) |
| 4 | `tag_code` | varchar(50) | YES | NULL | UNIQUE |
| 5 | `date_acquired` | date | YES | NULL | |
| 6 | `placement_date` | date | YES | NULL | |
| 7 | `age_at_placement_weeks` | int(10) unsigned | YES | NULL | |
| 8 | `flock_age_weeks` | int(10) unsigned | NO | 0 | |
| 9 | `breed` | enum('ISA Brown','Lohmann Brown-Classic','Dekalb White','Hy-Line Brown','Novogen Brown') | NO | 'ISA Brown' | |
| 10 | `sex` | enum('hen','cockerel','unknown') | NO | 'hen' | Added by `2026_07_03_000001` |
| 11 | `source` | varchar(200) | YES | NULL | Added by `2026_07_03_000001` |
| 12 | `initial_health_status` | varchar(100) | YES | NULL | Added by `2026_07_03_000001` |
| 13 | `notes` | text | YES | NULL | Added by `2026_07_03_000001` |
| 14 | `is_active` | tinyint(4) | NO | 1 | |
| 15 | `deactivation_cause` | varchar(30) | YES | NULL | Added by `2026_07_04_000002` |
| 16 | `created_at` | timestamp | YES | NULL | |
| 17 | `updated_at` | timestamp | YES | NULL | |

**Source migrations (in order):**
1. `2026_01_01_000004_create_hens_table.php`
2. `2026_07_03_000001_add_lifecycle_fields_to_hens_table.php` (chicken_id, sex, source, initial_health_status, notes)
3. `2026_07_03_000002_make_cage_slot_id_nullable_on_hens_table.php` (makes cage_slot_id nullable)
4. `2026_07_03_000007_make_cage_slot_id_nullable_in_hens.php` (intentionally empty — placeholder)
5. `2026_07_04_000002_add_deactivation_cause_to_hens_table.php`

---

### `forecasts` — Expected Columns

| # | Column | Type | Nullable | Default | FK / Notes |
|---|---|---|---|---|---|
| 1 | `id` | bigint(20) unsigned PK | NO | auto_increment | |
| 2 | `cage_id` | bigint(20) unsigned | YES | NULL | FK → `cages(id)` ON DELETE CASCADE |
| 3 | `cage_slot_id` | bigint(20) unsigned | **YES** | NULL | FK → `cage_slots(id)` ON DELETE CASCADE |
| 4 | `breed` | varchar(100) | YES | NULL | |
| 5 | `forecast_date` | date | NO | | |
| 6 | `target_date` | date | NO | | |
| 7 | `predicted_egg_count` | decimal(10,2) | NO | | |
| 8 | `created_at` | timestamp | NO | CURRENT_TIMESTAMP | |

**Source migrations (in order):**
1. `2026_01_01_000010_create_forecasts_table.php`
2. `2026_06_30_151237_add_breed_to_forecasts_table.php` (adds `breed`; guarded — no-op if already exists)
3. `2026_07_02_000000_update_forecasts_store_egg_count.php` (adds `breed` if missing; renames `predicted_hdep` → `predicted_egg_count` if present; both guarded)

---

## Verification Script

Copy the entire block below and paste into the Pi's terminal (SSH'd in as the `layratepi` user, from `/var/www/layrate`).

Grab a coffee while it runs — then visually compare each `SHOW COLUMNS` output against the **Target Schemas** tables above.

```bash
#!/usr/bin/env bash
# =============================================================================
# Pi Schema Verification — 2026-07-20
# Run this FIRST. Compare output against the target schemas in the document.
# Then run fixes ONLY for tables where columns are missing.
# =============================================================================

echo "══════════════════════════════════════════════════════════════════════"
echo " 1. php artisan migrate:status"
echo "══════════════════════════════════════════════════════════════════════"
php artisan migrate:status

echo ""
echo "══════════════════════════════════════════════════════════════════════"
echo " 2. SHOW COLUMNS FROM production_logs"
echo "    Expected: must have cage_slot_id"
echo "══════════════════════════════════════════════════════════════════════"
php artisan tinker --execute="
DB::statement('SET sql_mode=\"\"');
\$cols = DB::select('SHOW COLUMNS FROM production_logs');
foreach (\$cols as \$c) {
    echo str_pad(\$c->Field, 28) . ' | ' . str_pad(\$c->Type, 25) . ' | ' . (\$c->Null === 'YES' ? 'YES' : 'NO ') . ' | ' . (\$c->Key ?: '') . PHP_EOL;
}
"

echo ""
echo "══════════════════════════════════════════════════════════════════════"
echo " 3. SHOW COLUMNS FROM hens"
echo "    Expected: must have cage_slot_id"
echo "══════════════════════════════════════════════════════════════════════"
php artisan tinker --execute="
DB::statement('SET sql_mode=\"\"');
\$cols = DB::select('SHOW COLUMNS FROM hens');
foreach (\$cols as \$c) {
    echo str_pad(\$c->Field, 28) . ' | ' . str_pad(\$c->Type, 25) . ' | ' . (\$c->Null === 'YES' ? 'YES' : 'NO ') . ' | ' . (\$c->Key ?: '') . PHP_EOL;
}
"

echo ""
echo "══════════════════════════════════════════════════════════════════════"
echo " 4. SHOW COLUMNS FROM forecasts"
echo "    Expected: must have cage_slot_id"
echo "══════════════════════════════════════════════════════════════════════"
php artisan tinker --execute="
DB::statement('SET sql_mode=\"\"');
\$cols = DB::select('SHOW COLUMNS FROM forecasts');
foreach (\$cols as \$c) {
    echo str_pad(\$c->Field, 28) . ' | ' . str_pad(\$c->Type, 25) . ' | ' . (\$c->Null === 'YES' ? 'YES' : 'NO ') . ' | ' . (\$c->Key ?: '') . PHP_EOL;
}
"

echo ""
echo "══════════════════════════════════════════════════════════════════════"
echo " 5. Row counts (confirm data safety after fixes)"
echo "══════════════════════════════════════════════════════════════════════"
php artisan tinker --execute="
echo 'production_logs:    ' . DB::table('production_logs')->count() . PHP_EOL;
echo 'hens:               ' . DB::table('hens')->count() . PHP_EOL;
echo 'forecasts:          ' . DB::table('forecasts')->count() . PHP_EOL;
"

echo ""
echo "══════════════════════════════════════════════════════════════════════"
echo " Done with verification."
echo " Compare each SHOW COLUMNS output against the target schemas above."
echo " Then run only the fix section(s) needed."
echo "══════════════════════════════════════════════════════════════════════"
```

---

## Conditional Fix Scripts

**Do not run these blindly.** Only run a fix block if the verification above confirmed that the corresponding column is missing.

### Fix A — `production_logs` (add `cage_slot_id`)

Run ONLY if verification shows `cage_slot_id` is absent from `production_logs`.

```bash
# =============================================================================
# FIX A: Add cage_slot_id to production_logs
# =============================================================================
# This matches what 2026_01_01_000007_create_production_logs_table.php
# originally defined, then made nullable by 2026_07_05_000001.
# We add it nullable immediately to avoid FK violations on existing rows.

php artisan tinker --execute="
DB::statement('SET sql_mode=\"\"');
DB::statement('ALTER TABLE production_logs
    ADD COLUMN cage_slot_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX production_logs_cage_slot_id_index (cage_slot_id)');
echo 'cage_slot_id added to production_logs (nullable).' . PHP_EOL;
"

# Verify
php artisan tinker --execute="
\$col = DB::select('SHOW COLUMNS FROM production_logs WHERE Field = ?', ['cage_slot_id']);
echo \$col ? 'VERIFIED: cage_slot_id exists on production_logs' : 'ERROR: cage_slot_id still missing' . PHP_EOL;
"
```

> **Note:** The FK constraint (`FOREIGN KEY (cage_slot_id) REFERENCES cage_slots(id) ON DELETE CASCADE`) is intentionally **not** added here because existing rows have no valid `cage_slot_id`. Adding a FK would fail or require all NULLs to be valid. The FK can be added after backfill (see Backfill Investigation section below).

---

### Fix B — `hens` (add `cage_slot_id`)

Run ONLY if verification shows `cage_slot_id` is absent from `hens`.

```bash
# =============================================================================
# FIX B: Add cage_slot_id to hens
# =============================================================================
# Matches 2026_01_01_000004_create_hens_table.php, made nullable by
# 2026_07_03_000002.

php artisan tinker --execute="
DB::statement('SET sql_mode=\"\"');
DB::statement('ALTER TABLE hens
    ADD COLUMN cage_slot_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX hens_cage_slot_id_index (cage_slot_id)');
echo 'cage_slot_id added to hens (nullable).' . PHP_EOL;
"

# Verify
php artisan tinker --execute="
\$col = DB::select('SHOW COLUMNS FROM hens WHERE Field = ?', ['cage_slot_id']);
echo \$col ? 'VERIFIED: cage_slot_id exists on hens' : 'ERROR: cage_slot_id still missing' . PHP_EOL;
"
```

> **Note:** FK constraint intentionally omitted for the same reason as Fix A.

---

### Fix C — `forecasts` (add `cage_slot_id`)

Run ONLY if verification shows `cage_slot_id` is absent from `forecasts`.

```bash
# =============================================================================
# FIX C: Add cage_slot_id to forecasts
# =============================================================================
# Matches 2026_01_01_000010_create_forecasts_table.php.

php artisan tinker --execute="
DB::statement('SET sql_mode=\"\"');
DB::statement('ALTER TABLE forecasts
    ADD COLUMN cage_slot_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX forecasts_cage_slot_id_index (cage_slot_id)');
echo 'cage_slot_id added to forecasts (nullable).' . PHP_EOL;
"

# Verify
php artisan tinker --execute="
\$col = DB::select('SHOW COLUMNS FROM forecasts WHERE Field = ?', ['cage_slot_id']);
echo \$col ? 'VERIFIED: cage_slot_id exists on forecasts' : 'ERROR: cage_slot_id still missing' . PHP_EOL;
"
```

---

## Post-Fix Confirmation

After running any of the fixes above, re-run the full verification script from step **Verification** to confirm:
- All expected columns now exist
- Row counts are unchanged from the pre-fix baseline

Then test the production 500 error by accessing the hotspot URL. If the error was solely the missing column, it should resolve immediately.

---

## Backfill Investigation

### Summary

**There is no migration on disk that drops `cage_id` from `production_logs`.**

The current migration file `2026_01_01_000007_create_production_logs_table.php` creates the table with `cage_slot_id` (not `cage_id`). This file was **replaced** during the slot migration — not evolved via ALTER. The old file that had `cage_id` no longer exists on disk.

This means:

1. On the Pi, the old `cage_id` column may **still exist** on `production_logs` if the table was never recreated and no DROP was ever run.
2. The verification script above will show whether `cage_id` is still present (it would appear in the `SHOW COLUMNS` output alongside any new columns).

### Recovery feasibility

If `cage_id` still exists on the Pi's `production_logs`, backfill is possible:

- Each `cage_slot` belongs to one `cage` (`cage_slots.cage_id` → `cages.id`)
- For a production_log with `cage_id = X`, look up `cage_slots WHERE cage_id = X`
- **If there is exactly 1 slot** for that cage → safe to set `cage_slot_id` to that slot
- **If there are multiple slots** per cage (battery cage: typical 5–10 slots per cage) → there is no way to know which specific slot the eggs came from without additional data. The only safe options are:
  - Leave `cage_slot_id = NULL` (loss of slot-level granularity for old records, but the app should handle NULL gracefully as "legacy data")
  - Accept an arbitrary mapping (e.g. always use slot 1) — this would produce misleading per-slot HDEP figures for historical data

### What to check on the Pi

When running verification, also look for whether `cage_id` still exists:

```bash
php artisan tinker --execute="
DB::statement('SET sql_mode=\"\"');
\$cols = DB::select('SHOW COLUMNS FROM production_logs');
foreach (\$cols as \$c) {
    echo \$c->Field . PHP_EOL;
}
"
```

If `cage_id` is NOT in the output: **data is irrecoverably lost** — no backfill possible.

If `cage_id` IS in the output: you have options (see above), but this is a **data-integrity decision you must make explicitly**. Do not implement any backfill without sign-off, because:
- A single-cage-per-slot backfill is safe but rare (only 4 sensor slots are distinct per cage)
- A multi-slot-per-cage backfill would be arbitrary and could mask real slot-level issues
- Once the FK constraint is added, old NULL rows would need to be updated before the constraint can be applied

### Recommendation

For now, leave `cage_slot_id = NULL` for existing rows. The app code should handle this:

- The `ProductionLog` model's `cageSlot()` relationship returns `null` for NULL `cage_slot_id`, and the `getCageAttribute()` accessor (`ProductionLog.php:39-41`) calls `$this->cageSlot?->cage`, which safely returns null via the nullsafe operator.
- HDEP calculations that group by `cage_slot_id` will produce a "no slot" group for legacy data, which may be noticeable in the UI but won't crash.

**Decision needed before implementing backfill:**
> "Should historical production_logs rows with no cage_slot_id be left as NULL (slot-less), or should they be assigned to the first slot of their corresponding cage (losing per-slot accuracy but gaining aggregate inclusion)?"

---

## Summary Checklist

| Step | Command | When |
|---|---|---|
| 1. Verify schema | Run the Verification Script block | Before any fixes |
| 2. Run Fix A | `production_logs` ALTER | Only if `cage_slot_id` missing |
| 3. Run Fix B | `hens` ALTER | Only if `cage_slot_id` missing |
| 4. Run Fix C | `forecasts` ALTER | Only if `cage_slot_id` missing |
| 5. Re-check | Re-run Verification Script | After fixes to confirm |
| 6. Test | Access Pi hotspot URL | Check 500 error is gone |
| 7. Decide | Backfill existing rows? | See Backfill Investigation |
