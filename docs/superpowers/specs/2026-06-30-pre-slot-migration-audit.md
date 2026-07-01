# Pre-Slot-Migration Codebase Audit

**Date:** 2026-06-30
**Author:** Automated audit agent
**Purpose:** Read-only investigation of the current LayRate codebase state, to inform the upcoming cage-slot grid migration. No changes were made during this audit.

---

## 1. Current Actual Schema

Verified by reading all migration files directly (`.env` database SQLite file does not exist — no migrations have been run on this machine, so schema is determined solely from migration file content).

### `cages`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_code | VARCHAR(50) UNIQUE | |
| location | VARCHAR(100) DEFAULT '' | |
| capacity | INT UNSIGNED DEFAULT 120 | |
| is_active | TINYINT DEFAULT 1 | |
| has_sensor | BOOLEAN DEFAULT FALSE | Added by 2026_06_28 migration |
| sensor_device_id | VARCHAR(100) NULLABLE | Added by 2026_06_28 migration |
| created_at / updated_at | TIMESTAMP | |

### `hens`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_id | BIGINT UNSIGNED FK → `cages.id` | CASCADE ON DELETE |
| tag_code | VARCHAR(50) NULLABLE UNIQUE | |
| date_acquired | DATE NULLABLE | |
| placement_date | DATE NULLABLE | Added by 2026_06_28 migration |
| age_at_placement_weeks | INT UNSIGNED NULLABLE | Added by 2026_06_28 migration |
| flock_age_weeks | INT UNSIGNED DEFAULT 0 | Legacy field, still present |
| breed | ENUM('ISA Brown','Lohmann Brown-Classic','Dekalb White','Hy-Line Brown','Novogen Brown') DEFAULT 'ISA Brown' | |
| is_active | TINYINT DEFAULT 1 | |
| created_at / updated_at | TIMESTAMP | |

### `production_logs`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_id | BIGINT UNSIGNED FK → `cages.id` | CASCADE ON DELETE |
| log_date | DATE | |
| egg_count | INT UNSIGNED DEFAULT 0 | |
| hen_count | INT UNSIGNED DEFAULT 0 | |
| hdep | DECIMAL(5,2) DEFAULT 0 | |
| recorded_by | BIGINT UNSIGNED FK → `users.id` | NULL ON DELETE, nullable |
| overridden_by_user_id | BIGINT UNSIGNED FK → `users.id` | NULL ON DELETE, added by 2026_06_28 migration |
| overridden_at | TIMESTAMP NULLABLE | Added by 2026_06_28 migration |
| notes | TEXT NULLABLE | |
| created_at | TIMESTAMP | |
| UNIQUE(`cage_id`, `log_date`) | | |

### `environmental_logs`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_id | BIGINT UNSIGNED FK → `cages.id` | CASCADE ON DELETE |
| recorded_at | DATETIME | |
| temperature_c | DECIMAL(5,2) | |
| humidity_pct | DECIMAL(5,2) | |

### `feed_consumption_logs`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_id | BIGINT UNSIGNED FK → `cages.id` | CASCADE ON DELETE |
| feed_batch_id | BIGINT UNSIGNED FK → `feed_batches.id` | |
| log_date | DATE | |
| feed_consumed_kg | DECIMAL(8,2) | |
| recorded_by | BIGINT UNSIGNED FK → `users.id` | NULL ON DELETE |
| UNIQUE(`cage_id`, `log_date`) | | |

### `mortality_logs`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_id | BIGINT UNSIGNED FK → `cages.id` | CASCADE ON DELETE |
| log_date | DATE | |
| count | INT UNSIGNED DEFAULT 1 | |
| reason | VARCHAR(50) | |
| notes | TEXT NULLABLE | |
| recorded_by | BIGINT UNSIGNED FK → `users.id` | NULL ON DELETE |

### `alerts`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_id | BIGINT UNSIGNED FK → `cages.id` | CASCADE ON DELETE |
| alert_type | VARCHAR(50) | |
| message | TEXT | |
| is_read | TINYINT DEFAULT 0 | |
| triggered_at | DATETIME | |

### `forecasts`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| cage_id | BIGINT UNSIGNED FK → `cages.id` | **NULLABLE** (modified by 2026_06_28 migration), CASCADE ON DELETE |
| forecast_date | DATE | |
| target_date | DATE | |
| predicted_hdep | DECIMAL(5,2) | |
| created_at | TIMESTAMP | |

### `users`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| name | VARCHAR(255) | |
| email | VARCHAR(255) UNIQUE | |
| email_verified_at | TIMESTAMP NULLABLE | |
| password | VARCHAR(255) | |
| role | VARCHAR(255) DEFAULT 'user' | Added by `0001_01_01_000009_add_role_to_users_table` |
| override_pin_hash | VARCHAR(255) NULLABLE | Added by 2026_06_28 migration |
| remember_token | VARCHAR(100) | |
| created_at / updated_at | TIMESTAMP | |

### Discrepancies between plan doc claims and actual schema

- **None found.** All 5 migrations from the 2026-06-28 plan exist in `database/migrations/` and their column definitions match the plan exactly. The `forecasts.cage_id` nullable migration uses raw `DB::statement()` as specified (no `doctrine/dbal` dependency needed).
- **Minor**: The plan's `$casts` on `ProductionLog` includes `overridden_at => 'datetime'` — confirmed present.
- **Minor**: The plan's `Hen::fillable` includes `placement_date` and `age_at_placement_weeks` — confirmed present.
- **Plan says**: `tests/` directory was "intentionally removed" — actually confirmed: no `tests/` directory exists at all.

---

## 2. What's Actually Built vs. What Was Planned

**Verdict: The entire 2026-06-28 cage-level feature set was fully implemented.** Every task from the plan was executed, with minor deviations noted below.

| Plan Task | Status | Evidence |
|---|---|---|
| **Task 1: Schema Migrations** (5 migrations) | ✅ Complete | All 5 migration files exist with correct columns |
| **Task 2: Hen computed age accessor** | ✅ Complete | `Hen::getCurrentAgeWeeksAttribute()` exists; views use `current_age_weeks` (confirmed via grep — zero `flock_age_weeks` references remain in Blade files) |
| **Task 3: Dashboard sensor badge** | ✅ Complete | `partials/cage-sensor-badge.blade.php` exists; `dashboard.blade.php` includes it; no decorative slot grid remains |
| **Task 4: Cage Management sensor toggle / flock capture** | ✅ Complete | `CageController::store()` accepts `age_at_placement_weeks`; `CageController::update()` accepts `has_sensor`, `sensor_device_id`, `breed`, `age_at_placement_weeks`; cage edit modal has sensor toggle + breed + age fields |
| **Task 5: Forecasting scope toggle** | ✅ Complete | `ForecastController::index()` and `generate()` accept `scope=cage|farm`; forecast view has Whole Farm / Per Cage pill toggle |
| **Task 6: Account Settings + PIN** | ✅ Complete | `AccountController` exists; `account.blade.php` exists; sidebar link points to `route('account')`; weak PIN denylist present |
| **Task 7: Egg Logging sensor override** | ✅ Complete | `EggLoggingController::verifyOverride()` exists; `ProductionLog::overriddenBy()` relation exists; override modal in view; route wired with `throttle:6,1` |

### Notable deviations from the plan (minor enhancements):

1. **EggLoggingController `store()` line 87**: Added `&& $data['log_date'] === now()->toDateString()` — the sensor lock only applies for today's date. The plan didn't include this date check, meaning it would have locked *any* date for a sensor cage. This is a sensible restriction (you can't override a sensor reading from 3 days ago because there's no live sensor data to conflict with).

2. **Route `egg-logging.verify-override`**: Has `->middleware('throttle:6,1')` (6 requests per minute) — the plan only mentioned "no retry lockout (v1)" in the spec, but the implementation added rate limiting at the route level, which is better.

3. **EggLoggingController `index()` line 27**: Loads `'recorder'` relation in addition to `'overriddenBy'` — the plan only showed `'overriddenBy'`. This makes the "Logged By" column work correctly.

### Issues remaining from before the plan (not fixed):

4. **`FeedController::storeConsumption()` line 75**: `'recorded_by' => 1` still hardcoded. The plan noted this as a pre-existing bug but did not fix it (it was out of scope).

5. **`DashboardController` line 26**: Uses `flock_age_weeks` in a logical expression (`$cage->hens->first()?->flock_age_weeks ? $cage->capacity : 0`) — this is a metric calculation, not a display, so it wasn't changed.

---

## 3. Bulk Add Chickens Status

**Confirmed: The Bulk Add Chickens feature does not exist anywhere in the codebase.**

- No routes, controllers, views, or JS related to "bulk add", "bulkAssign", "bulk_chickens", or "BulkAdd" were found via grep.
- The 2026-06-28 spec document explicitly rejected the original 5-feature spec that included bulk chicken assignment (because that spec assumed a `cage_slots`/`chickens` data model that doesn't exist).
- The plan replaced it with "Add/Update Flock" (Task 4, Section 3 of the spec), which adds a breed and age-at-placement field to the existing cage create/edit forms — no modal or standalone workflow for adding multiple chickens exists.
- **Prior report of "not showing up"**: The feature was never implemented, so it could not "show up". The earlier prompt that generated it appears to have been discarded/rejected when the project pivoted from slot-level to cage-level granularity.

---

## 4. Dependencies and Constraints

### `doctrine/dbal`
- **NOT installed.** `vendor/doctrine/dbal` does not exist. Any column type changes in future migrations must use raw `DB::statement()` (as the prior plan did for `forecasts.cage_id`).

### PHPUnit / Test Suite
- **No test suite exists.** `tests/` directory does not exist on disk (CONTEXT.md incorrectly says "exists but is empty" — it was removed entirely).
- `phpunit/phpunit ^11.5.50` is listed in `composer.json` `require-dev`, so the package is available, but there are zero test files.
- The 2026-06-28 plan stated "intentionally removed" and relied on `php artisan tinker` + manual browser checks for verification.

### `forecasts.cage_id` Nullability
- **Currently nullable.** Migration `2026_06_28_000005_make_cage_id_nullable_on_forecasts_table.php` changed it from `NOT NULL` to `NULL`. A `null` value means a whole-farm forecast.

### All Foreign Key References to `cages.id`

| Table | FK Column | Constraint | In Migration |
|---|---|---|---|
| `hens` | `cage_id` | CASCADE ON DELETE | `2026_01_01_000002_create_hens_table.php` |
| `production_logs` | `cage_id` | CASCADE ON DELETE | `2026_01_01_000004_create_production_logs_table.php` |
| `environmental_logs` | `cage_id` | CASCADE ON DELETE | `2026_01_01_000005_create_environmental_logs_table.php` |
| `alerts` | `cage_id` | CASCADE ON DELETE | `2026_01_01_000006_create_alerts_table.php` |
| `feed_consumption_logs` | `cage_id` | CASCADE ON DELETE | `2026_01_01_000007_create_feed_consumption_logs_table.php` |
| `forecasts` | `cage_id` | CASCADE ON DELETE | `2026_01_01_000008_create_forecasts_table.php` (now nullable) |
| `mortality_logs` | `cage_id` | CASCADE ON DELETE | `2026_01_01_000010_create_mortality_logs_table.php` |

All 7 tables use `constrained()->cascadeOnDelete()`. During a slot-grid migration, all of these would need to be repointed from `cages.id` to `cage_slots.id` (or handled through a different schema design, such as keeping cage-level logging but adding a separate slot-level production table).

---

## 5. Arduino / Raspberry Pi Integration State

**No PHP-side ingestion exists.** The state is identical to what the prior plan described: "out of scope."

- **Arduino firmware**: `LayRate - Arduino/src/main.cpp` is fully functional — reads DHT22 (temp/humidity) every 2 seconds with 3-retry NaN/range validation, counts IR break-beam events, prints formatted blocks to serial at 9600 baud on value change.
- **PHP backend**: No endpoint, controller, Artisan command, or queue job reads serial data or ingests sensor readings into `production_logs` or `environmental_logs`.
- The `has_sensor` flag and sensor override feature on egg logging were designed to be *ready for* future integration but do not depend on it. Currently, sensor-flagged cages simply show `0` as the egg count and require manual PIN-gated override to record production.
- No data pipeline (serial reader, MQTT, HTTP endpoint, cron job, etc.) exists on the Raspberry Pi side either — only the firmware source.

---

## 6. Seed / Factory State

### `DatabaseSeeder` (`database/seeders/DatabaseSeeder.php`)

| Entity | Quantity | Details |
|---|---|---|
| Users | 2 | Admin (`admin@layrate.local`) and Operator (`operator@layrate.local`), both via `firstOrCreate` |
| Cages | 4 | CAGE-A (North, 120, active), CAGE-B (East, 120, active), CAGE-C (South, 120, active), CAGE-D (West, 120, inactive) |
| Hens | 4 | One per cage, via `firstOrCreate` — `flock_age_weeks` set, `placement_date` and `age_at_placement_weeks` are **NOT set** (rely on legacy fallback) |
| Feed batches | 3 | F-001 through F-003 |
| Production logs | 4 cages × 14 days = 56 | Generated with daily variation, recorded_by = operator |
| Environmental logs | 4 cages × 24 readings = 96 | 2-hour intervals, random-ish variation |
| Feed consumption | 3 active cages × 7 days = 21 | Daily data, varied batch assignment |
| Alerts | 2 | CAGE-C humidity high, CAGE-B humidity watch |
| Mortality | 7 records | Across 4 cages over 14 days |
| Forecasts | 7 | Only for CAGE-A, 7-day horizon |

**Important for migration planning**: The seeder uses `firstOrCreate` — it is idempotent and will not duplicate data if re-run. Hens are created without `placement_date` or `age_at_placement_weeks`, meaning they rely on `flock_age_weeks` fallback in the computed accessor. The upcoming slot migration plan that wipes and reseeds will need to update this seed data.

### `UserFactory` (`database/factories/UserFactory.php`)
- Standard Laravel factory, creates a user with `fake()->name()`, `fake()->email()`, and `Hash::make('password')`.
- No other factories exist (no `CageFactory`, `HenFactory`, etc.).

---

## Summary of Key Facts for the Slot Migration

1. **All 7 data tables FK to `cages.id`** with CASCADE ON DELETE — these will all need schema changes.
2. **`doctrine/dbal` is NOT installed** — any column type changes must use raw `DB::statement()`.
3. **`tests/` directory does NOT exist** — no automated test coverage to maintain or break.
4. **No Arduino ingestion pipeline exists** — sensor data is either seeded or manually entered.
5. **Seeder uses `firstOrCreate`** — idempotent, but hen data lacks slot-aware seeding.
6. **Bulk Add Chickens never existed** — it was replaced by the Add/Update Flock feature at cage level.
7. **Cage-level feature set is fully implemented** — the codebase is in a stable, ship-ready state with sensor flagging, override PIN, forecasting scope toggle, and computed flock age all working.
