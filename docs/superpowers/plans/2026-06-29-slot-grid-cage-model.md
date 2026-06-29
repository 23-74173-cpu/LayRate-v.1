# Slot-Grid Cage Model Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace LayRate's flat cage model with a slot-grid model — cages have rows × slots, sensors and occupancy live on `cage_slots`, and Cage Management/Bulk Add/Egg Logging/Forecast all operate at the slot or row level instead of the cage level.

**Architecture:** A destructive schema rebuild (authorized — all current data is mock/seed) replaces `cages`/`hens`/`production_logs` with slot-aware versions and adds a new `cage_slots` table; `environmental_logs`/`feed_consumption_logs`/`mortality_logs`/`alerts` keep their existing `cage_id` shape but get truncated since their data references cage IDs that won't exist after the rebuild. A new seeder repopulates everything with real grids. Controllers and views are then updated layer by layer: models, Cage Management CRUD + slot grid UI, Bulk Add, Egg Logging, Forecast.

**Tech Stack:** Laravel 12, Blade, Tailwind (local static build — see the Pi-deployment fix already merged), vanilla JS, MySQL via XAMPP.

## Global Constraints

- All current `cages`/`hens`/`production_logs`/`environmental_logs`/`feed_consumption_logs`/`mortality_logs`/`alerts`/`forecasts` data is mock/seed data — destructive rebuilds of `cages`, `hens`, `production_logs`, and a new `cage_slots` table are explicitly authorized in this plan. This authorization is scoped to exactly the tables named in this plan's Task 1 — **no task may run `migrate:fresh`, `migrate:refresh`, `db:wipe`, or drop/truncate any table not explicitly named in its own task**. A prior migration in this project caused real data loss from an unauthorized blanket reset; this plan's destructive steps are deliberate and narrow, not a blank check.
- `total_capacity` (on `Cage`) and `status` (on `CageSlot`) are computed accessors, never stored columns — same pattern as the existing `Hen::current_age_weeks`.
- `production_logs`, `hens` key off `cage_slot_id`. `environmental_logs`, `feed_consumption_logs`, `mortality_logs`, `alerts` stay on `cage_id`, unchanged structurally.
- No PHPUnit test suite exists in this project (intentionally removed) — verify each task via `php artisan tinker` and manual route/view checks, not automated tests.
- Match existing conventions: plain Blade forms, vanilla JS (no Alpine/Vue), Tailwind utility classes with the existing palette (navy `#002D5E`/`#102A4C`, gray `#6B7280`/`#D9D9D9`, green `#D5E8D4`/`#2D6A4F`, amber for warnings), `auth()->user()`/`auth()->id()` for the current user.

---

### Task 1: Schema Rebuild — Migrations

**Files:**
- Create: `database/migrations/2026_06_29_000001_rebuild_cage_slot_grid_schema.php`
- Create: `database/migrations/2026_06_29_000002_add_row_number_to_forecasts_table.php`

**Interfaces:**
- Produces: `cages` (id, cage_code, location, rows, slots_per_row, max_chickens_per_slot, is_active, timestamps — no more `capacity`/`has_sensor`/`sensor_device_id`); `cage_slots` (id, cage_id FK cascade, row_number, column_number, slot_number, current_occupancy default 0, has_sensor default false, sensor_device_id nullable, timestamps, unique [cage_id, row_number, column_number]); `hens` (id, cage_slot_id FK cascade replacing cage_id, tag_code, date_acquired, flock_age_weeks, breed enum, placement_date, age_at_placement_weeks, is_active, timestamps); `production_logs` (id, cage_slot_id FK cascade replacing cage_id, log_date, egg_count, hen_count, hdep, recorded_by, notes, overridden_by_user_id, overridden_at, unique [cage_slot_id, log_date]); `forecasts.row_number` (nullable unsigned int, alongside existing nullable `cage_id`).

**Context:** `hens`, `production_logs`, `environmental_logs`, `feed_consumption_logs`, `alerts`, `mortality_logs`, `forecasts` all currently have a `cage_id` foreign key to `cages` with `cascadeOnDelete()`. Dropping/recreating `cages` with fresh auto-increment IDs makes every existing row in every one of those tables reference a cage that no longer exists. `hens` and `production_logs` are being structurally rebuilt anyway (their `cage_id` becomes `cage_slot_id`), but `environmental_logs`, `feed_consumption_logs`, `mortality_logs`, and `alerts` keep their current shape — their *existing rows* still need clearing out since those rows' `cage_id` values are now stale. `forecasts` also needs its rows cleared for the same reason (on top of its own additive column).

- [ ] **Step 1: Write the schema rebuild migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::dropIfExists('production_logs');
        Schema::dropIfExists('hens');
        Schema::dropIfExists('cage_slots');
        Schema::dropIfExists('cages');

        Schema::create('cages', function (Blueprint $table) {
            $table->id();
            $table->string('cage_code', 50)->unique();
            $table->string('location', 100)->default('');
            $table->unsignedInteger('rows')->default(1);
            $table->unsignedInteger('slots_per_row')->default(1);
            $table->unsignedInteger('max_chickens_per_slot')->default(1);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('cage_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->unsignedInteger('column_number');
            $table->unsignedInteger('slot_number');
            $table->unsignedInteger('current_occupancy')->default(0);
            $table->boolean('has_sensor')->default(false);
            $table->string('sensor_device_id', 100)->nullable();
            $table->timestamps();
            $table->unique(['cage_id', 'row_number', 'column_number']);
        });

        Schema::create('hens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_slot_id')->constrained()->cascadeOnDelete();
            $table->string('tag_code', 50)->nullable()->unique();
            $table->date('date_acquired')->nullable();
            $table->unsignedInteger('flock_age_weeks')->default(0);
            $table->enum('breed', [
                'ISA Brown',
                'Lohmann Brown-Classic',
                'Dekalb White',
                'Hy-Line Brown',
                'Novogen Brown',
            ])->default('ISA Brown');
            $table->date('placement_date')->nullable();
            $table->unsignedInteger('age_at_placement_weeks')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_slot_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->unsignedInteger('egg_count')->default(0);
            $table->unsignedInteger('hen_count')->default(0);
            $table->decimal('hdep', 5, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cage_slot_id', 'log_date']);
        });

        DB::table('environmental_logs')->truncate();
        DB::table('feed_consumption_logs')->truncate();
        DB::table('alerts')->truncate();
        DB::table('mortality_logs')->truncate();
        DB::table('forecasts')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::dropIfExists('production_logs');
        Schema::dropIfExists('hens');
        Schema::dropIfExists('cage_slots');
        Schema::dropIfExists('cages');

        Schema::create('cages', function (Blueprint $table) {
            $table->id();
            $table->string('cage_code', 50)->unique();
            $table->string('location', 100)->default('');
            $table->unsignedInteger('capacity')->default(120);
            $table->tinyInteger('is_active')->default(1);
            $table->boolean('has_sensor')->default(false);
            $table->string('sensor_device_id', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('hens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->string('tag_code', 50)->nullable()->unique();
            $table->date('date_acquired')->nullable();
            $table->unsignedInteger('flock_age_weeks')->default(0);
            $table->enum('breed', [
                'ISA Brown', 'Lohmann Brown-Classic', 'Dekalb White', 'Hy-Line Brown', 'Novogen Brown',
            ])->default('ISA Brown');
            $table->date('placement_date')->nullable();
            $table->unsignedInteger('age_at_placement_weeks')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->unsignedInteger('egg_count')->default(0);
            $table->unsignedInteger('hen_count')->default(0);
            $table->decimal('hdep', 5, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cage_id', 'log_date']);
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
```

Note: `down()` restores the pre-migration *structure* only (matching what existed right before this migration), not any data — consistent with this being a mock-data project where the spec explicitly says no backfill is needed.

- [ ] **Step 2: Write the forecasts row_number migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->unsignedInteger('row_number')->nullable()->after('cage_id');
        });
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropColumn('row_number');
        });
    }
};
```

- [ ] **Step 3: Run the migrations**

Run: `php artisan migrate`
Expected: both new migrations run with no errors. The 19 pre-existing migrations show as already-run (unaffected — this plan only adds new migration files, it never edits old ones).

- [ ] **Step 4: Verify the new schema**

Run: `php artisan tinker --no-interaction`
```php
\Illuminate\Support\Facades\Schema::getColumnListing('cages');
// expect: id, cage_code, location, rows, slots_per_row, max_chickens_per_slot, is_active, created_at, updated_at
\Illuminate\Support\Facades\Schema::getColumnListing('cage_slots');
// expect: id, cage_id, row_number, column_number, slot_number, current_occupancy, has_sensor, sensor_device_id, created_at, updated_at
\Illuminate\Support\Facades\Schema::getColumnListing('hens');
// expect: cage_slot_id present, cage_id absent
\Illuminate\Support\Facades\Schema::getColumnListing('production_logs');
// expect: cage_slot_id present, cage_id absent
\App\Models\Cage::count(); // expect 0 (table was just rebuilt, empty)
\Illuminate\Support\Facades\DB::table('environmental_logs')->count(); // expect 0 (truncated)
\Illuminate\Support\Facades\DB::table('forecasts')->count(); // expect 0 (truncated)
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_29_000001_rebuild_cage_slot_grid_schema.php database/migrations/2026_06_29_000002_add_row_number_to_forecasts_table.php
git commit -m "Rebuild cage schema for slot-grid model (cage_slots, hens/production_logs re-keyed)"
```

---

### Task 2: Models — Cage, CageSlot, Hen, ProductionLog

**Files:**
- Modify: `app/Models/Cage.php`
- Create: `app/Models/CageSlot.php`
- Modify: `app/Models/Hen.php`
- Modify: `app/Models/ProductionLog.php`
- Modify: `app/Models/Forecast.php`

**Interfaces:**
- Consumes: schema from Task 1.
- Produces: `Cage::slots()` (HasMany CageSlot), `Cage->total_capacity` (int accessor), `CageSlot::cage()` (BelongsTo), `CageSlot::hens()` (HasMany Hen), `CageSlot::productionLogs()` (HasMany ProductionLog), `CageSlot->status` (string accessor: `empty`|`partial`|`full`), `CageSlot->label` (string accessor, e.g. `"A-03"`), `Hen::cageSlot()` (BelongsTo, replacing `cage()`), `ProductionLog::cageSlot()` (BelongsTo, replacing `cage()`).

- [ ] **Step 1: Replace `app/Models/Cage.php` entirely**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Cage extends Model
{
    protected $fillable = ['cage_code', 'location', 'rows', 'slots_per_row', 'max_chickens_per_slot', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function slots(): HasMany
    {
        return $this->hasMany(CageSlot::class)->orderBy('slot_number');
    }

    public function hens(): HasManyThrough
    {
        return $this->hasManyThrough(Hen::class, CageSlot::class);
    }

    public function environmentalLogs(): HasMany
    {
        return $this->hasMany(EnvironmentalLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function feedConsumptionLogs(): HasMany
    {
        return $this->hasMany(FeedConsumptionLog::class);
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(Forecast::class);
    }

    public function latestEnvironment()
    {
        return $this->hasOne(EnvironmentalLog::class)->latestOfMany('recorded_at');
    }

    public function getTotalCapacityAttribute(): int
    {
        return $this->rows * $this->slots_per_row * $this->max_chickens_per_slot;
    }

    public function getColorAttribute(): string
    {
        return match($this->cage_code) {
            'CAGE-A' => '#2D7D46',
            'CAGE-B' => '#1D4E8F',
            'CAGE-C' => '#C2703E',
            'CAGE-D' => '#6B4C8A',
            default  => '#6B7280',
        };
    }

    public function getPrimaryHenAttribute(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }
}
```

Note: `latestProduction()`, `getHdepColorAttribute()`, `getHdepTextColorAttribute()` are removed from `Cage` — HDEP is now a per-slot concept (via `CageSlot::productionLogs()`), not a per-cage one. Task 4 and Task 7 add cage-level HDEP aggregation where the views need it (averaged/summed across the cage's slots), rather than relying on a single "latest production" relation on `Cage` itself.

- [ ] **Step 2: Create `app/Models/CageSlot.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CageSlot extends Model
{
    protected $fillable = [
        'cage_id', 'row_number', 'column_number', 'slot_number',
        'current_occupancy', 'has_sensor', 'sensor_device_id',
    ];

    protected $casts = ['has_sensor' => 'boolean'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function hens(): HasMany
    {
        return $this->hasMany(Hen::class);
    }

    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function latestProduction()
    {
        return $this->hasOne(ProductionLog::class)->latestOfMany('log_date');
    }

    public function getPrimaryHenAttribute(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }

    public function getStatusAttribute(): string
    {
        if ($this->current_occupancy <= 0) {
            return 'empty';
        }

        if ($this->current_occupancy >= $this->cage->max_chickens_per_slot) {
            return 'full';
        }

        return 'partial';
    }

    public function getLabelAttribute(): string
    {
        $rowLetter = chr(64 + $this->row_number); // 1 -> A, 2 -> B, 3 -> C ...
        return "{$rowLetter}-" . str_pad($this->column_number, 2, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 3: Update `app/Models/Hen.php`**

Find:
```php
    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }
```
Replace with:
```php
    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }
```

Find the `$fillable` array:
```php
    protected $fillable = [
        'cage_id', 'tag_code', 'date_acquired', 'flock_age_weeks',
        'placement_date', 'age_at_placement_weeks', 'breed', 'is_active',
    ];
```
Replace with:
```php
    protected $fillable = [
        'cage_slot_id', 'tag_code', 'date_acquired', 'flock_age_weeks',
        'placement_date', 'age_at_placement_weeks', 'breed', 'is_active',
    ];
```

The `getCurrentAgeWeeksAttribute()` accessor is unchanged — it doesn't reference `cage_id` at all.

- [ ] **Step 4: Update `app/Models/ProductionLog.php`**

Find:
```php
    protected $fillable = ['cage_id', 'log_date', 'egg_count', 'hen_count', 'hdep', 'recorded_by', 'notes', 'overridden_by_user_id', 'overridden_at'];
```
Replace with:
```php
    protected $fillable = ['cage_slot_id', 'log_date', 'egg_count', 'hen_count', 'hdep', 'recorded_by', 'notes', 'overridden_by_user_id', 'overridden_at'];
```

Find:
```php
    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }
```
Replace with:
```php
    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }
```

- [ ] **Step 5: Update `app/Models/Forecast.php`**

Find:
```php
    protected $fillable = ['cage_id', 'forecast_date', 'target_date', 'predicted_hdep'];
```
Replace with:
```php
    protected $fillable = ['cage_id', 'row_number', 'forecast_date', 'target_date', 'predicted_hdep'];
```

- [ ] **Step 6: Verify via tinker**

```php
$cage = \App\Models\Cage::create(['cage_code' => 'TEST-CAGE', 'location' => 'Test', 'rows' => 2, 'slots_per_row' => 3, 'max_chickens_per_slot' => 5]);
echo $cage->total_capacity; // expect 30
$slot = \App\Models\CageSlot::create(['cage_id' => $cage->id, 'row_number' => 1, 'column_number' => 1, 'slot_number' => 1]);
echo $slot->status; // expect "empty"
echo $slot->label; // expect "A-01"
$slot->current_occupancy = 3;
echo $slot->status; // expect "partial"
$slot->current_occupancy = 5;
echo $slot->status; // expect "full"
$hen = \App\Models\Hen::create(['cage_slot_id' => $slot->id, 'breed' => 'ISA Brown', 'placement_date' => now(), 'age_at_placement_weeks' => 2]);
echo $hen->cageSlot->cage->cage_code; // expect "TEST-CAGE"
echo $cage->hens->count(); // expect 1 (via hasManyThrough)
$slot->delete();
$cage->delete();
echo \App\Models\Cage::where('cage_code', 'TEST-CAGE')->exists() ? 'still exists (bad)' : 'cleaned up (good)';
```

- [ ] **Step 7: Commit**

```bash
git add app/Models/Cage.php app/Models/CageSlot.php app/Models/Hen.php app/Models/ProductionLog.php app/Models/Forecast.php
git commit -m "Add CageSlot model, re-key Hen/ProductionLog relations to slot level"
```

---

### Task 3: Seeder Rewrite

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `Cage`, `CageSlot`, `Hen`, `ProductionLog`, `Forecast` from Task 2.
- Produces: seeded data other tasks' manual verification steps rely on — 4 cages, real grids, some slots occupied, 2 slots flagged `has_sensor=true` (in `CAGE-A` row 1 slot 3, and `CAGE-B` row 1 slot 5), 14 days of production history for every occupied slot.

- [ ] **Step 1: Replace `database/seeders/DatabaseSeeder.php` entirely**

```php
<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Forecast;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@layrate.local'],
            ['name' => 'Farm Admin', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        $user = User::firstOrCreate(
            ['email' => 'operator@layrate.local'],
            ['name' => 'Farm Operator', 'password' => Hash::make('password'), 'role' => 'operator']
        );

        // ── Cages, each with a real row x slot grid ──────────────────
        $cageDefs = [
            'CAGE-A' => ['location' => 'North Wing', 'rows' => 3, 'slots_per_row' => 10, 'max_chickens_per_slot' => 4, 'is_active' => 1],
            'CAGE-B' => ['location' => 'East Wing',  'rows' => 3, 'slots_per_row' => 10, 'max_chickens_per_slot' => 4, 'is_active' => 1],
            'CAGE-C' => ['location' => 'South Wing', 'rows' => 2, 'slots_per_row' => 8,  'max_chickens_per_slot' => 3, 'is_active' => 1],
            'CAGE-D' => ['location' => 'West Wing',  'rows' => 2, 'slots_per_row' => 8,  'max_chickens_per_slot' => 3, 'is_active' => 0],
        ];

        $cages = [];
        foreach ($cageDefs as $code => $def) {
            $cages[$code] = Cage::create(array_merge(['cage_code' => $code], $def));
        }

        // ── Slots for every cage (full grid, most left empty) ────────
        $slotsByCage = [];
        foreach ($cages as $code => $cage) {
            $slotsByCage[$code] = [];
            $slotNumber = 1;
            for ($row = 1; $row <= $cage->rows; $row++) {
                for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                    $slotsByCage[$code]["{$row}-{$col}"] = CageSlot::create([
                        'cage_id'       => $cage->id,
                        'row_number'    => $row,
                        'column_number' => $col,
                        'slot_number'   => $slotNumber++,
                    ]);
                }
            }
        }

        // ── Which slots are occupied, and which have a sensor ────────
        $occupied = [
            'CAGE-A' => [
                ['key' => '1-1', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '1-2', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '1-3', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4, 'sensor' => true],
                ['key' => '1-4', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 3],
                ['key' => '1-5', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '1-6', 'breed' => 'ISA Brown',             'age' => 28, 'occ' => 4],
                ['key' => '2-1', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
                ['key' => '2-2', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
                ['key' => '2-3', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 3],
                ['key' => '2-4', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
                ['key' => '2-5', 'breed' => 'Lohmann Brown-Classic', 'age' => 20, 'occ' => 4],
            ],
            'CAGE-B' => [
                ['key' => '1-1', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-2', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-3', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-4', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 3],
                ['key' => '1-5', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4, 'sensor' => true],
                ['key' => '1-6', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-7', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 4],
                ['key' => '1-8', 'breed' => 'Dekalb White', 'age' => 34, 'occ' => 3],
            ],
            'CAGE-C' => [
                ['key' => '1-1', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-2', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-3', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 2],
                ['key' => '1-4', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-5', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 3],
                ['key' => '1-6', 'breed' => 'Hy-Line Brown', 'age' => 52, 'occ' => 2],
            ],
            'CAGE-D' => [], // inactive cage, kept empty
        ];

        $placementDate = now()->subWeeks(4);
        $occupiedSlotModels = []; // for production log seeding below

        foreach ($occupied as $code => $entries) {
            foreach ($entries as $entry) {
                $slot = $slotsByCage[$code][$entry['key']];
                $slot->update([
                    'current_occupancy' => $entry['occ'],
                    'has_sensor'        => $entry['sensor'] ?? false,
                ]);

                Hen::create([
                    'cage_slot_id'           => $slot->id,
                    'breed'                  => $entry['breed'],
                    'placement_date'         => $placementDate,
                    'age_at_placement_weeks' => $entry['age'],
                    'flock_age_weeks'        => $entry['age'],
                    'date_acquired'          => $placementDate,
                    'is_active'              => 1,
                ]);

                $occupiedSlotModels[] = ['slot' => $slot->fresh(), 'occ' => $entry['occ']];
            }
        }

        // ── 14 days of production history for every occupied slot ────
        for ($i = 0; $i < 14; $i++) {
            $date = now()->subDays($i)->toDateString();
            foreach ($occupiedSlotModels as $row) {
                $slot = $row['slot'];
                $hens = $row['occ'];
                $baseRate = 0.8; // eggs per hen per day, roughly
                $variation = (($i % 4) - 2) * 0.03;
                $eggs = (int) round($hens * max(0.4, $baseRate + $variation));

                ProductionLog::firstOrCreate(
                    ['cage_slot_id' => $slot->id, 'log_date' => $date],
                    [
                        'egg_count'   => $eggs,
                        'hen_count'   => $hens,
                        'hdep'        => round(($eggs / max(1, $hens)) * 100, 2),
                        'recorded_by' => $user->id,
                        'notes'       => $slot->has_sensor ? 'IR sensor synced' : 'Manual check',
                    ]
                );
            }
        }

        // ── Feed batches (unchanged from before) ──────────────────────
        $feedBatches = [
            ['batch_code' => 'F-001', 'crude_protein' => 17.50, 'date_received' => '2026-03-01', 'notes' => 'Layer mash - standard'],
            ['batch_code' => 'F-002', 'crude_protein' => 16.80, 'date_received' => '2026-03-15', 'notes' => 'Layer pellet - supplier B'],
            ['batch_code' => 'F-003', 'crude_protein' => 18.00, 'date_received' => '2026-03-28', 'notes' => 'Protein-boosted mix'],
        ];
        foreach ($feedBatches as $fb) {
            FeedBatch::firstOrCreate(['batch_code' => $fb['batch_code']], $fb);
        }
        $batches = FeedBatch::orderBy('date_received')->get()->keyBy('batch_code');

        // ── Environmental logs (cage-level, unchanged shape) ──────────
        for ($h = 0; $h < 24; $h++) {
            $ts = now()->subHours($h * 2);
            $envBase = [
                'CAGE-A' => ['temp' => 28.9, 'hum' => 68.1],
                'CAGE-B' => ['temp' => 28.7, 'hum' => 70.0],
                'CAGE-C' => ['temp' => 29.2, 'hum' => 71.0],
                'CAGE-D' => ['temp' => 27.9, 'hum' => 66.6],
            ];
            foreach ($envBase as $code => $base) {
                $v = ($h % 5) * 0.2;
                EnvironmentalLog::create([
                    'cage_id'       => $cages[$code]->id,
                    'recorded_at'   => $ts,
                    'temperature_c' => round($base['temp'] - $v, 1),
                    'humidity_pct'  => round($base['hum']  - $v * 0.5, 1),
                ]);
            }
        }

        // ── Feed consumption (cage-level, unchanged shape) ────────────
        $feedConsumption = ['CAGE-A' => 11.4, 'CAGE-B' => 12.4, 'CAGE-C' => 13.4];
        for ($i = 0; $i < 7; $i++) {
            $date     = now()->subDays($i)->toDateString();
            $batchKey = $i < 3 ? 'F-003' : ($i < 5 ? 'F-002' : 'F-001');
            foreach ($feedConsumption as $code => $kg) {
                FeedConsumptionLog::firstOrCreate(
                    ['cage_id' => $cages[$code]->id, 'log_date' => $date],
                    [
                        'feed_batch_id'    => $batches[$batchKey]->id,
                        'feed_consumed_kg' => round($kg + ($i % 3) * 0.2, 1),
                        'recorded_by'      => $user->id,
                    ]
                );
            }
        }

        // ── Alerts (cage-level, unchanged shape) ──────────────────────
        Alert::create([
            'cage_id' => $cages['CAGE-C']->id, 'alert_type' => 'humidity_high',
            'message' => 'Humidity at 71% — above 70% threshold', 'is_read' => 0, 'triggered_at' => now()->subHours(2),
        ]);
        Alert::create([
            'cage_id' => $cages['CAGE-B']->id, 'alert_type' => 'humidity_watch',
            'message' => 'Humidity at 70% — at threshold boundary', 'is_read' => 0, 'triggered_at' => now()->subHours(5),
        ]);

        // ── Mortality (cage-level, unchanged shape) ───────────────────
        $mortalitySamples = [
            ['cage' => 'CAGE-C', 'days_ago' => 0, 'count' => 1, 'reason' => 'Heat Stress', 'notes' => 'Found near water trough, high temp recorded that day'],
            ['cage' => 'CAGE-A', 'days_ago' => 1, 'count' => 1, 'reason' => 'Unknown',     'notes' => null],
            ['cage' => 'CAGE-C', 'days_ago' => 2, 'count' => 2, 'reason' => 'Disease',     'notes' => 'Respiratory symptoms observed in surrounding hens'],
            ['cage' => 'CAGE-B', 'days_ago' => 3, 'count' => 1, 'reason' => 'Injury',      'notes' => 'Likely pecking injury — isolated others'],
        ];
        foreach ($mortalitySamples as $ms) {
            MortalityLog::create([
                'cage_id'  => $cages[$ms['cage']]->id,
                'log_date' => now()->subDays($ms['days_ago'])->toDateString(),
                'reason'   => $ms['reason'],
                'count'    => $ms['count'],
                'notes'    => $ms['notes'],
                'recorded_by' => $user->id,
            ]);
        }

        // ── A whole-farm forecast seed (per-cage/per-row forecasts are generated on demand) ──
        $today = now()->toDateString();
        for ($i = 1; $i <= 7; $i++) {
            $v = (($i % 3) - 1) * 0.3;
            Forecast::create([
                'cage_id' => null,
                'row_number' => null,
                'forecast_date' => $today,
                'target_date' => now()->addDays($i)->toDateString(),
                'predicted_hdep' => round(78.0 + $v, 2),
            ]);
        }
    }
}
```

- [ ] **Step 2: Run the seeder**

Run: `php artisan db:seed --force`
Expected: completes with no errors.

- [ ] **Step 3: Verify**

```php
\App\Models\Cage::count(); // expect 4
\App\Models\CageSlot::count(); // expect (3*10)+(3*10)+(2*8)+(2*8) = 92
\App\Models\CageSlot::where('has_sensor', true)->count(); // expect 2
\App\Models\Hen::count(); // expect 11 + 8 + 6 = 25
\App\Models\ProductionLog::count(); // expect 25 * 14 = 350
$cageA = \App\Models\Cage::where('cage_code', 'CAGE-A')->first();
echo $cageA->total_capacity; // expect 3*10*4 = 120
echo $cageA->hens->count(); // expect 11 (via hasManyThrough)
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "Rewrite seeder for slot-grid cage model with real row x slot grids"
```

---

### Task 4: Cage Management — Slot-Box Partial, Grid Display, Create Flow

**Files:**
- Create: `resources/views/partials/slot-box.blade.php`
- Modify: `app/Http/Controllers/CageController.php` (`index()`, `store()`)
- Modify: `resources/views/cages/index.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `Cage`, `CageSlot` from Task 2.
- Produces: `@include('partials.slot-box', ['slot' => $slot])` — reused by Task 5 (Edit's Layout Preview) and Task 6 (Bulk Add's slot picker). Route `cages.flock` (`POST /cages/{cage}/flock`) is **not** added here — flock assignment is Task 6's Bulk Add modal, not part of cage creation itself, since occupancy now belongs to slots, not the cage record.

**Context:** The old Cage Management view showed a flat table with Edit/Delete per cage and a simple Add/Edit modal (cage_code, location, breed, capacity). This task replaces creation with the Battery Cage Configuration modal (Cage Name, Rows, Slots per Row, Max Chickens per Slot, live Configuration Summary, Layout Preview) and replaces the flat table with a grid view per cage using the new slot-box partial. Editing and deleting are Task 5.

- [ ] **Step 1: Create the shared slot-box partial**

```blade
@php
    $slotStatusBg = match($slot->status) {
        'full'    => 'bg-[#F8D7DA] border-red-200',
        'partial' => 'bg-[#FFF3CD] border-amber-200',
        default   => 'bg-[#F5F6F8] border-[#D9D9D9]',
    };
@endphp
<div class="relative {{ $slotStatusBg }} border rounded text-center text-[10px] py-2 px-1 leading-tight">
    @if($slot->has_sensor)
    <span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-emerald-500" title="Sensor-equipped"></span>
    @endif
    <div class="font-medium text-[#333333]">{{ $slot->label }}</div>
    <div class="text-[#6B7280]">{{ $slot->current_occupancy }}/{{ $slot->cage->max_chickens_per_slot }}</div>
</div>
```

- [ ] **Step 2: Replace `CageController::index()` and `store()`**

Find:
```php
    public function index()
    {
        $cages = Cage::with([
            'latestProduction',
            'hens' => fn($q) => $q->where('is_active', 1)->orderBy('id'),
        ])->orderBy('cage_code')->get();

        return view('cages.index', compact('cages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_code'              => 'required|string|max:50|unique:cages',
            'location'               => 'nullable|string|max:100',
            'capacity'               => 'nullable|integer|min:1',
            'breed'                  => 'nullable|string',
            'age_at_placement_weeks' => 'nullable|integer|min:0',
        ]);

        $cage = Cage::create([
            'cage_code' => strtoupper($data['cage_code']),
            'location'  => $data['location'] ?? '',
            'capacity'  => $data['capacity'] ?? 120,
            'is_active' => 1,
        ]);

        if (! empty($data['breed'])) {
            Hen::create([
                'cage_id'                 => $cage->id,
                'placement_date'          => now(),
                'age_at_placement_weeks'  => $data['age_at_placement_weeks'] ?? 0,
                'flock_age_weeks'         => 0,
                'breed'                   => $data['breed'],
            ]);
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} added.");
    }
```

Replace with:
```php
    public function index()
    {
        $cages = Cage::with(['slots' => fn($q) => $q->orderBy('slot_number')])
            ->orderBy('cage_code')
            ->get();

        return view('cages.index', compact('cages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_code'             => 'required|string|max:50|unique:cages',
            'location'              => 'nullable|string|max:100',
            'rows'                  => 'required|integer|min:1|max:26',
            'slots_per_row'         => 'required|integer|min:1|max:50',
            'max_chickens_per_slot' => 'required|integer|min:1|max:20',
        ]);

        $cage = Cage::create([
            'cage_code'             => strtoupper($data['cage_code']),
            'location'              => $data['location'] ?? '',
            'rows'                  => $data['rows'],
            'slots_per_row'         => $data['slots_per_row'],
            'max_chickens_per_slot' => $data['max_chickens_per_slot'],
            'is_active'             => 1,
        ]);

        $slotNumber = 1;
        for ($row = 1; $row <= $cage->rows; $row++) {
            for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                CageSlot::create([
                    'cage_id'       => $cage->id,
                    'row_number'    => $row,
                    'column_number' => $col,
                    'slot_number'   => $slotNumber++,
                ]);
            }
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} created with {$cage->slots()->count()} slots. Add chickens to it from the cage's \"Bulk Add Chickens\" button below.");
    }
```

Add `use App\Models\CageSlot;` to the top of the file; the `use App\Models\Hen;` import can stay (still used by `update()` until Task 5 changes it) and `use App\Models\ProductionLog;` is no longer used by this controller after this step — leave it for now, Task 5 will clean up unused imports once `update()`/`destroy()` are finalized.

- [ ] **Step 3: Replace `resources/views/cages/index.blade.php` entirely**

```blade
@extends('layouts.app')
@section('title', 'Cage Management')

@section('content')
<main class="p-5 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-medium text-[#333333]">Cage Management</h1>
        <button onclick="document.getElementById('addCageModal').classList.remove('hidden')"
                class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Cage
        </button>
    </div>

    @forelse($cages as $cage)
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <span class="text-base font-medium" style="color:{{ $cage->color }}">{{ $cage->cage_code }}</span>
                <span class="text-sm text-[#6B7280] ml-2">{{ $cage->location ?: '—' }}</span>
                <span class="text-xs px-2.5 py-1 rounded-full ml-2 {{ $cage->is_active ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                    {{ $cage->is_active ? 'active' : 'inactive' }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('bulk-add.show', $cage) }}" class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                    <i data-lucide="users" class="w-3 h-3"></i> Bulk Add Chickens
                </a>
                <button onclick='openEditModal(@json(["id" => $cage->id, "cage_code" => $cage->cage_code, "location" => $cage->location, "rows" => $cage->rows, "slots_per_row" => $cage->slots_per_row, "max_chickens_per_slot" => $cage->max_chickens_per_slot, "is_active" => $cage->is_active]))'
                        class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                    <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                </button>
                <form method="POST" action="{{ route('cages.destroy', $cage) }}" onsubmit="return confirm('Delete {{ $cage->cage_code }}? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="flex items-center justify-center w-8 h-8 border border-red-200 text-red-400 rounded hover:bg-red-50">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="text-xs text-[#6B7280] mb-3">
            {{ $cage->rows }} rows × {{ $cage->slots_per_row }} slots, max {{ $cage->max_chickens_per_slot }}/slot — total capacity {{ $cage->total_capacity }}
        </div>

        <div class="space-y-1.5 overflow-x-auto">
            @for($row = 1; $row <= $cage->rows; $row++)
            <div class="flex items-center gap-1">
                <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ chr(64 + $row) }}</div>
                @foreach($cage->slots->where('row_number', $row) as $slot)
                <div class="flex-1 min-w-[44px]">
                    @include('partials.slot-box', ['slot' => $slot])
                </div>
                @endforeach
            </div>
            @endfor
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-8 text-center text-sm text-[#6B7280]">No cages yet. Click "+ Add Cage" to get started.</div>
    @endforelse

</main>

{{-- ── Add Cage Modal (Battery Cage Configuration) ── --}}
<div id="addCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-medium">Battery Cage Configuration</h2>
            <button onclick="document.getElementById('addCageModal').classList.add('hidden')" class="text-[#6B7280] hover:text-[#333333]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('cages.store') }}">
            @csrf
            <label class="block text-sm text-[#333333] mb-1.5">Cage Name</label>
            <input name="cage_code" id="cfgCageCode" placeholder="e.g. CAGE-E" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Location</label>
            <input name="location" placeholder="e.g. North Wing"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Rows</label>
                    <input name="rows" id="cfgRows" type="number" min="1" max="26" value="3" required oninput="updateConfigPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Slots/Row</label>
                    <input name="slots_per_row" id="cfgSlotsPerRow" type="number" min="1" max="50" value="10" required oninput="updateConfigPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Max/Slot</label>
                    <input name="max_chickens_per_slot" id="cfgMaxPerSlot" type="number" min="1" max="20" value="4" required oninput="updateConfigPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <div class="bg-[#F5F6F8] border border-[#D9D9D9] rounded-lg p-3 mb-4 text-xs text-[#333333]">
                <div class="font-medium mb-1">Configuration Summary</div>
                <div id="cfgSummary">30 slots · 120 total capacity</div>
            </div>

            <div class="mb-5">
                <div class="text-sm text-[#333333] mb-1.5">Layout Preview</div>
                <div id="cfgPreview" class="space-y-1 overflow-x-auto"></div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('addCageModal').classList.add('hidden')"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8]">Cancel</button>
                <button type="submit" class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Create Battery Cage System</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
lucide.createIcons();

function updateConfigPreview() {
    const rows = parseInt(document.getElementById('cfgRows').value) || 0;
    const slotsPerRow = parseInt(document.getElementById('cfgSlotsPerRow').value) || 0;
    const maxPerSlot = parseInt(document.getElementById('cfgMaxPerSlot').value) || 0;
    const totalSlots = rows * slotsPerRow;
    const totalCapacity = totalSlots * maxPerSlot;

    document.getElementById('cfgSummary').textContent = `${totalSlots} slots · ${totalCapacity} total capacity`;

    const preview = document.getElementById('cfgPreview');
    preview.innerHTML = '';
    for (let r = 1; r <= rows; r++) {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'flex items-center gap-1';
        const label = document.createElement('div');
        label.className = 'w-6 text-[11px] text-[#6B7280] shrink-0';
        label.textContent = String.fromCharCode(64 + r);
        rowDiv.appendChild(label);
        for (let c = 1; c <= slotsPerRow; c++) {
            const cell = document.createElement('div');
            cell.className = 'flex-1 min-w-[28px] h-6 rounded bg-[#F5F6F8] border border-[#D9D9D9]';
            rowDiv.appendChild(cell);
        }
        preview.appendChild(rowDiv);
    }
}

window.addEventListener('DOMContentLoaded', updateConfigPreview);
</script>
@endpush
```

Note: the Edit modal and its `openEditModal()` function are added in Task 5 — this task's view intentionally references `openEditModal(...)` even though that function doesn't exist yet; Task 5 adds it in the same file. Running the app between Task 4 and Task 5 will show a working "Add Cage" flow with a harmless console error if Edit is clicked — acceptable for an interim state between two tasks in the same plan, but flag it in Task 4's report so the reviewer knows it's expected.

- [ ] **Step 4: Verify**

Run: `php artisan route:list --name=cages` — expect the same 4 routes as before (`cages.index`, `cages.store`, `cages.update`, `cages.destroy`).

In the browser: visit `/cages`, confirm all 4 seeded cages render with real grids, sensor markers visible on `CAGE-A` slot A-03 and `CAGE-B` slot A-05, occupancy badges showing real counts. Click "Add Cage," type into Rows/Slots per Row/Max per Slot, confirm the Configuration Summary and Layout Preview update live. Submit a test cage, confirm it's created with the right number of slots, then delete it via the existing delete button (no safety guard yet — that's Task 5) to clean up.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CageController.php resources/views/cages/index.blade.php resources/views/partials/slot-box.blade.php
git commit -m "Add Battery Cage Configuration create flow and slot-grid display to Cage Management"
```

---

### Task 5: Cage Management — Edit (Resize-Safety) & Delete (Safety Guard)

**Files:**
- Modify: `app/Http/Controllers/CageController.php` (`update()`, `destroy()`)
- Modify: `resources/views/cages/index.blade.php`

**Interfaces:**
- Consumes: `Cage`, `CageSlot` from Task 2; the create-flow view structure from Task 4 (this task adds the Edit modal and `openEditModal()` referenced but not yet defined there).
- Produces: nothing new consumed by later tasks — this closes out Cage Management.

**Context:** Shrinking a grid or lowering max-per-slot can orphan real chickens/sensors; deleting a cage can silently destroy its entire history. Both get explicit, named-slot safety checks instead of a generic confirm dialog. Given the scope of this plan, the delete confirmation uses a native `prompt()` typed-code check rather than a full custom modal showing itemized counts — this satisfies the "require explicit confirmation" requirement without adding another modal component; it's a deliberate scope-trim, flag it if a fuller counts-display modal turns out to matter later.

- [ ] **Step 1: Replace `CageController::update()`**

Find the entire existing `update()` method and replace it with:

```php
    public function update(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'cage_code'             => 'required|string|max:50|unique:cages,cage_code,' . $cage->id,
            'location'              => 'nullable|string|max:100',
            'rows'                  => 'required|integer|min:1|max:26',
            'slots_per_row'         => 'required|integer|min:1|max:50',
            'max_chickens_per_slot' => 'required|integer|min:1|max:20',
            'is_active'             => 'nullable|boolean',
        ]);

        $newRows         = $data['rows'];
        $newSlotsPerRow  = $data['slots_per_row'];
        $newMaxPerSlot   = $data['max_chickens_per_slot'];

        $slotsToRemove = $cage->slots()
            ->where(function ($q) use ($newRows, $newSlotsPerRow) {
                $q->where('row_number', '>', $newRows)
                  ->orWhere('column_number', '>', $newSlotsPerRow);
            })
            ->get();

        $blockedRemove = $slotsToRemove->filter(fn($s) => $s->current_occupancy > 0 || $s->has_sensor);

        if ($blockedRemove->isNotEmpty()) {
            $names = $blockedRemove->map(fn($s) => "{$s->label} ({$s->current_occupancy} chickens" . ($s->has_sensor ? ', sensor' : '') . ')')->implode(', ');
            return back()->withInput()->withErrors([
                'rows' => "Cannot shrink grid — slot(s) {$names} have chickens or a sensor. Remove or reassign them first.",
            ]);
        }

        if ($newMaxPerSlot < $cage->max_chickens_per_slot) {
            $overCapacity = $cage->slots()->where('current_occupancy', '>', $newMaxPerSlot)->get();
            if ($overCapacity->isNotEmpty()) {
                $names = $overCapacity->map(fn($s) => "{$s->label} ({$s->current_occupancy} chickens)")->implode(', ');
                return back()->withInput()->withErrors([
                    'max_chickens_per_slot' => "Cannot lower max per slot — slot(s) {$names} already exceed that count.",
                ]);
            }
        }

        $cage->slots()
            ->where(function ($q) use ($newRows, $newSlotsPerRow) {
                $q->where('row_number', '>', $newRows)
                  ->orWhere('column_number', '>', $newSlotsPerRow);
            })
            ->delete();

        $existingPositions = $cage->slots()->get()->map(fn($s) => "{$s->row_number}-{$s->column_number}")->flip();
        for ($row = 1; $row <= $newRows; $row++) {
            for ($col = 1; $col <= $newSlotsPerRow; $col++) {
                if (! isset($existingPositions["{$row}-{$col}"])) {
                    CageSlot::create(['cage_id' => $cage->id, 'row_number' => $row, 'column_number' => $col, 'slot_number' => 0]);
                }
            }
        }

        // Renumber every remaining slot sequentially (row-major) so slot_number stays consistent after any resize.
        $n = 1;
        foreach ($cage->slots()->orderBy('row_number')->orderBy('column_number')->get() as $slot) {
            $slot->update(['slot_number' => $n++]);
        }

        $cage->update([
            'cage_code'             => strtoupper($data['cage_code']),
            'location'              => $data['location'] ?? '',
            'rows'                  => $newRows,
            'slots_per_row'         => $newSlotsPerRow,
            'max_chickens_per_slot' => $newMaxPerSlot,
            'is_active'             => $request->boolean('is_active'),
        ]);

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }
```

- [ ] **Step 2: Replace `CageController::destroy()`**

```php
    public function destroy(Request $request, Cage $cage)
    {
        $hardBlocked = $cage->slots()
            ->where(fn($q) => $q->where('current_occupancy', '>', 0)->orWhere('has_sensor', true))
            ->exists();

        if ($hardBlocked) {
            return back()->withErrors([
                'cage' => "Cannot delete {$cage->cage_code} — it has occupied or sensor-equipped slots. Clear them first.",
            ]);
        }

        $slotIds = $cage->slots()->pluck('id');
        $hasHistory = ProductionLog::whereIn('cage_slot_id', $slotIds)->exists()
            || EnvironmentalLog::where('cage_id', $cage->id)->exists()
            || FeedConsumptionLog::where('cage_id', $cage->id)->exists()
            || MortalityLog::where('cage_id', $cage->id)->exists()
            || Alert::where('cage_id', $cage->id)->exists();

        if ($hasHistory && $request->input('confirm_code') !== $cage->cage_code) {
            return back()->withErrors([
                'cage' => "{$cage->cage_code} has historical records. Type its exact code to confirm deletion.",
            ]);
        }

        $cage->delete();

        return redirect()->route('cages.index')->with('success', 'Cage deleted.');
    }
```

Add these imports to the top of `CageController.php`: `use App\Models\Alert;`, `use App\Models\EnvironmentalLog;`, `use App\Models\FeedConsumptionLog;`, `use App\Models\MortalityLog;`, `use App\Models\ProductionLog;`. Remove `use App\Models\Hen;` — it's no longer referenced anywhere in this controller after Task 4 and this step.

- [ ] **Step 3: Compute a `has_history` flag per cage in `index()`**

Find:
```php
    public function index()
    {
        $cages = Cage::with(['slots' => fn($q) => $q->orderBy('slot_number')])
            ->orderBy('cage_code')
            ->get();

        return view('cages.index', compact('cages'));
    }
```
Replace with:
```php
    public function index()
    {
        $cages = Cage::with(['slots' => fn($q) => $q->orderBy('slot_number')])
            ->orderBy('cage_code')
            ->get()
            ->each(function ($cage) {
                $slotIds = $cage->slots->pluck('id');
                $cage->has_history = ProductionLog::whereIn('cage_slot_id', $slotIds)->exists()
                    || EnvironmentalLog::where('cage_id', $cage->id)->exists()
                    || FeedConsumptionLog::where('cage_id', $cage->id)->exists()
                    || MortalityLog::where('cage_id', $cage->id)->exists()
                    || Alert::where('cage_id', $cage->id)->exists();
            });

        return view('cages.index', compact('cages'));
    }
```

- [ ] **Step 4: Add the Edit modal and JS to `resources/views/cages/index.blade.php`**

Find:
```blade
                <form method="POST" action="{{ route('cages.destroy', $cage) }}" onsubmit="return confirm('Delete {{ $cage->cage_code }}? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="flex items-center justify-center w-8 h-8 border border-red-200 text-red-400 rounded hover:bg-red-50">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
```
Replace with:
```blade
                <form method="POST" action="{{ route('cages.destroy', $cage) }}" onsubmit="return confirmDelete(this, '{{ $cage->cage_code }}', {{ $cage->has_history ? 'true' : 'false' }})">
                    @csrf @method('DELETE')
                    <button type="submit" class="flex items-center justify-center w-8 h-8 border border-red-200 text-red-400 rounded hover:bg-red-50">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
```

Find the closing `@endforelse` immediately before `</main>`, and insert the Edit modal right after `</main>` and before the closing `@endsection` (i.e. immediately after the Add Cage modal's closing `</div>` from Task 4):

```blade
{{-- ── Edit Cage Modal (Battery Cage Configuration) ── --}}
<div id="editCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-medium">Edit Battery Cage Configuration</h2>
            <button onclick="document.getElementById('editCageModal').classList.add('hidden')" class="text-[#6B7280] hover:text-[#333333]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg px-3 py-2 mb-4">{{ $errors->first() }}</div>
        @endif
        <form id="editCageForm" method="POST">
            @csrf @method('PUT')
            <label class="block text-sm text-[#333333] mb-1.5">Cage Name</label>
            <input id="editCageCode" name="cage_code" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Location</label>
            <input id="editLocation" name="location"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Rows</label>
                    <input id="editRows" name="rows" type="number" min="1" max="26" required onchange="updateEditPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Slots/Row</label>
                    <input id="editSlotsPerRow" name="slots_per_row" type="number" min="1" max="50" required onchange="updateEditPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Max/Slot</label>
                    <input id="editMaxPerSlot" name="max_chickens_per_slot" type="number" min="1" max="20" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <label class="flex items-center gap-2 mb-4 cursor-pointer">
                <input id="editActive" name="is_active" type="checkbox" value="1" class="w-4 h-4">
                <span class="text-sm text-[#333333]">Active</span>
            </label>

            <div class="mb-5">
                <div class="text-sm text-[#333333] mb-1.5">Layout Preview <span class="text-xs text-[#6B7280]">(shows current occupancy/sensors — rows or columns beyond the new size you set above are highlighted red if they can't be removed)</span></div>
                <div id="editPreview" class="space-y-1 overflow-x-auto"></div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('editCageModal').classList.add('hidden')"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8]">Cancel</button>
                <button type="submit" class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Save Changes</button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 5: Add `openEditModal()`, `updateEditPreview()`, and `confirmDelete()` to the `@push('scripts')` block**

Find:
```blade
window.addEventListener('DOMContentLoaded', updateConfigPreview);
</script>
@endpush
```
Replace with:
```blade
window.addEventListener('DOMContentLoaded', updateConfigPreview);

let editCageSlots = [];

function openEditModal(cage) {
    document.getElementById('editCageForm').action = '/cages/' + cage.id;
    document.getElementById('editCageCode').value  = cage.cage_code;
    document.getElementById('editLocation').value  = cage.location;
    document.getElementById('editRows').value         = cage.rows;
    document.getElementById('editSlotsPerRow').value  = cage.slots_per_row;
    document.getElementById('editMaxPerSlot').value   = cage.max_chickens_per_slot;
    document.getElementById('editActive').checked     = !!cage.is_active;
    editCageSlots = cage.slots;
    updateEditPreview();
    document.getElementById('editCageModal').classList.remove('hidden');
}

function updateEditPreview() {
    const newRows = parseInt(document.getElementById('editRows').value) || 0;
    const newSlotsPerRow = parseInt(document.getElementById('editSlotsPerRow').value) || 0;
    const maxRow = Math.max(newRows, ...editCageSlots.map(s => s.row_number));
    const maxCol = Math.max(newSlotsPerRow, ...editCageSlots.map(s => s.column_number));

    const preview = document.getElementById('editPreview');
    preview.innerHTML = '';
    for (let r = 1; r <= maxRow; r++) {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'flex items-center gap-1';
        const label = document.createElement('div');
        label.className = 'w-6 text-[11px] text-[#6B7280] shrink-0';
        label.textContent = String.fromCharCode(64 + r);
        rowDiv.appendChild(label);
        for (let c = 1; c <= maxCol; c++) {
            const slot = editCageSlots.find(s => s.row_number === r && s.column_number === c);
            const cell = document.createElement('div');
            const wouldBeRemoved = r > newRows || c > newSlotsPerRow;
            const occupied = slot && (slot.current_occupancy > 0 || slot.has_sensor);
            let bg = 'bg-[#F5F6F8] border-[#D9D9D9]';
            if (wouldBeRemoved && occupied) bg = 'bg-red-100 border-red-300';
            else if (wouldBeRemoved) bg = 'bg-gray-100 border-gray-300 opacity-50';
            else if (slot && slot.current_occupancy > 0) bg = 'bg-[#FFF3CD] border-amber-200';
            cell.className = `flex-1 min-w-[28px] h-6 rounded border text-[8px] flex items-center justify-center ${bg}`;
            if (slot) cell.textContent = slot.current_occupancy;
            rowDiv.appendChild(cell);
        }
        preview.appendChild(rowDiv);
    }
}

function confirmDelete(form, cageCode, hasHistory) {
    if (hasHistory) {
        const typed = prompt(`"${cageCode}" has historical records. Type its exact code to confirm deletion:`);
        if (typed !== cageCode) return false;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'confirm_code';
        input.value = typed;
        form.appendChild(input);
        return true;
    }
    return confirm(`Delete ${cageCode}? This cannot be undone.`);
}
</script>
@endpush
```

- [ ] **Step 6: Verify**

Run: `php artisan route:list --name=cages` — same 4 routes, unchanged signatures (`update`/`destroy` already accepted a `Request` param via Laravel's automatic injection before this step too).

In tinker — shrink-safety:
```php
$cage = \App\Models\Cage::where('cage_code', 'CAGE-A')->first();
$occupiedSlot = $cage->slots()->where('current_occupancy', '>', 0)->first();
echo $occupiedSlot->row_number . '-' . $occupiedSlot->column_number; // note this position

$req = \Illuminate\Http\Request::create("/cages/{$cage->id}", 'PUT', [
    'cage_code' => $cage->cage_code, 'location' => $cage->location,
    'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => $cage->max_chickens_per_slot, 'is_active' => 1,
]);
\Illuminate\Support\Facades\Auth::loginUsingId(1);
app()->instance('request', $req);
$response = app(\App\Http\Controllers\CageController::class)->update($req, $cage);
echo $response->getSession()->get('errors') ? 'BLOCKED as expected' : 'NOT blocked (bug)';
$cage->refresh();
echo $cage->rows; // expect unchanged (still original value, update was blocked)
```

In the browser: open Edit on `CAGE-C` (no sensors, fewer occupied slots — slots 1-1 through 1-6 occupied), try shrinking Slots/Row from 8 to 2, confirm the Layout Preview highlights occupied slots in red and the save is blocked with a named-slot error. Then try a safe shrink (e.g. Slots/Row to 7, which only removes empty slot 1-7... wait `CAGE-C` only has 6 occupied out of 8 in row 1 — shrinking to 7 removes slot 1-8 only, which is empty) and confirm it saves successfully. Test deleting an empty test cage with no history (simple confirm) versus a seeded cage with history (typed-code prompt).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CageController.php resources/views/cages/index.blade.php
git commit -m "Add resize-safety to cage edit and historical-data guard to cage delete"
```

---

### Task 6: Bulk Add Chickens

**Files:**
- Create: `app/Http/Controllers/BulkAddController.php`
- Create: `resources/views/bulk-add.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `Cage`, `CageSlot`, `Hen`, `partials.slot-box` from earlier tasks. Consumes the `route('bulk-add.show', $cage)` link already added to `cages/index.blade.php` in Task 4.
- Produces: routes `bulk-add.show` (GET `/cages/{cage}/bulk-add`), `bulk-add.store` (POST, same URL).

**Context:** This is a dedicated page (not another modal stacked on Cage Management) since it has enough of its own state — a clickable slot grid, breed/age inputs, live capacity feedback — to warrant its own view.

- [ ] **Step 1: Create `app/Http/Controllers/BulkAddController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use Illuminate\Http\Request;

class BulkAddController extends Controller
{
    public function show(Cage $cage)
    {
        $slots = $cage->slots()->orderBy('slot_number')->get();

        return view('bulk-add', compact('cage', 'slots'));
    }

    public function store(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'slot_ids'                  => 'required|array|min:1',
            'slot_ids.*'                => 'integer|exists:cage_slots,id',
            'breed'                     => 'required|string',
            'age_at_placement_weeks'    => 'required|integer|min:0',
            'chickens_per_slot'         => 'required|integer|min:1',
        ]);

        $slots = CageSlot::whereIn('id', $data['slot_ids'])->where('cage_id', $cage->id)->get();

        if ($slots->count() !== count($data['slot_ids'])) {
            return back()->withInput()->withErrors(['slot_ids' => 'One or more selected slots do not belong to this cage.']);
        }

        $perSlot = $data['chickens_per_slot'];
        $overflow = $slots->filter(fn($s) => $s->current_occupancy + $perSlot > $cage->max_chickens_per_slot);

        if ($overflow->isNotEmpty()) {
            $names = $overflow->map(fn($s) => "{$s->label} ({$s->current_occupancy}/{$cage->max_chickens_per_slot})")->implode(', ');
            return back()->withInput()->withErrors([
                'chickens_per_slot' => "{$perSlot} per slot would overflow: {$names}. Lower the count or deselect those slots.",
            ]);
        }

        $placementDate = now();

        foreach ($slots as $slot) {
            Hen::create([
                'cage_slot_id'           => $slot->id,
                'breed'                  => $data['breed'],
                'placement_date'         => $placementDate,
                'age_at_placement_weeks' => $data['age_at_placement_weeks'],
                'flock_age_weeks'        => $data['age_at_placement_weeks'],
                'is_active'              => 1,
            ]);

            $slot->update(['current_occupancy' => $slot->current_occupancy + $perSlot]);
        }

        return redirect()->route('cages.index')->with('success', "Added chickens to " . $slots->count() . " slot(s) in {$cage->cage_code}.");
    }
}
```

- [ ] **Step 2: Add routes**

Find the `cages` route block in `routes/web.php`:
```php
    Route::get('/cages',               [CageController::class, 'index'])->name('cages.index');
    Route::post('/cages',              [CageController::class, 'store'])->name('cages.store');
    Route::put('/cages/{cage}',        [CageController::class, 'update'])->name('cages.update');
    Route::delete('/cages/{cage}',     [CageController::class, 'destroy'])->name('cages.destroy')->middleware('admin');
```
Replace with:
```php
    Route::get('/cages',               [CageController::class, 'index'])->name('cages.index');
    Route::post('/cages',              [CageController::class, 'store'])->name('cages.store');
    Route::put('/cages/{cage}',        [CageController::class, 'update'])->name('cages.update');
    Route::delete('/cages/{cage}',     [CageController::class, 'destroy'])->name('cages.destroy')->middleware('admin');

    Route::get('/cages/{cage}/bulk-add',  [BulkAddController::class, 'show'])->name('bulk-add.show');
    Route::post('/cages/{cage}/bulk-add', [BulkAddController::class, 'store'])->name('bulk-add.store');
```

Add `use App\Http\Controllers\BulkAddController;` to the top `use` block.

- [ ] **Step 3: Create `resources/views/bulk-add.blade.php`**

```blade
@extends('layouts.app')
@section('title', 'Bulk Add Chickens')

@section('content')
<main class="p-5 space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('cages.index') }}" class="text-[#6B7280] hover:text-[#333333]">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <h1 class="text-xl font-medium text-[#333333]">Bulk Add Chickens — {{ $cage->cage_code }}</h1>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('bulk-add.store', $cage) }}" id="bulkAddForm">
        @csrf

        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4">1. Select Slots <span id="selectedCount" class="text-[#6B7280] font-normal">(0 selected)</span></h2>
            <div class="space-y-1.5 overflow-x-auto">
                @for($row = 1; $row <= $cage->rows; $row++)
                <div class="flex items-center gap-1">
                    <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ chr(64 + $row) }}</div>
                    @foreach($slots->where('row_number', $row) as $slot)
                    @php $isFull = $slot->current_occupancy >= $cage->max_chickens_per_slot; @endphp
                    <label class="flex-1 min-w-[44px] {{ $isFull ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}"
                           title="{{ $isFull ? "Full — {$slot->current_occupancy}/{$cage->max_chickens_per_slot}" : '' }}">
                        <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}"
                               data-occupancy="{{ $slot->current_occupancy }}"
                               class="slot-checkbox sr-only" {{ $isFull ? 'disabled' : '' }}
                               onchange="onSlotToggle(this)">
                        <div class="slot-visual border rounded text-center text-[10px] py-2 px-1 leading-tight bg-[#F5F6F8] border-[#D9D9D9]">
                            @if($slot->has_sensor)
                            <span class="block w-1.5 h-1.5 rounded-full bg-emerald-500 ml-auto"></span>
                            @endif
                            <div class="font-medium text-[#333333]">{{ $slot->label }}</div>
                            <div class="text-[#6B7280]">{{ $slot->current_occupancy }}/{{ $cage->max_chickens_per_slot }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
                @endfor
            </div>
        </div>

        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4">2. Flock Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Breed</label>
                    <select name="breed" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                        <option>ISA Brown</option>
                        <option>Lohmann Brown-Classic</option>
                        <option>Dekalb White</option>
                        <option>Hy-Line Brown</option>
                        <option>Novogen Brown</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Age at Placement (weeks)</label>
                    <input type="number" name="age_at_placement_weeks" min="0" value="0" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Placement Date</label>
                    <input type="text" value="{{ now()->format('Y-m-d') }} (auto)" readonly
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-[#F5F6F8] text-[#6B7280]">
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm text-[#333333] mb-1.5">Chickens per Slot</label>
                <input type="number" name="chickens_per_slot" id="chickensPerSlot" min="1" value="{{ $cage->max_chickens_per_slot }}" required
                       class="w-full max-w-xs border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                <p id="capacityNote" class="text-xs text-[#6B7280] mt-1.5"></p>
            </div>
        </div>

        <button type="submit" class="bg-[#002D5E] text-white px-6 py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            Add Chickens
        </button>
    </form>

</main>
@endsection

@push('scripts')
<script>
lucide.createIcons();

const maxPerSlot = {{ $cage->max_chickens_per_slot }};

function onSlotToggle(checkbox) {
    const visual = checkbox.nextElementSibling;
    visual.classList.toggle('bg-blue-100', checkbox.checked);
    visual.classList.toggle('border-blue-400', checkbox.checked);

    const checked = document.querySelectorAll('.slot-checkbox:checked');
    document.getElementById('selectedCount').textContent = `(${checked.length} selected)`;

    let minRemaining = maxPerSlot;
    checked.forEach(cb => {
        const remaining = maxPerSlot - parseInt(cb.dataset.occupancy, 10);
        if (remaining < minRemaining) minRemaining = remaining;
    });

    const perSlotInput = document.getElementById('chickensPerSlot');
    perSlotInput.max = minRemaining;
    if (parseInt(perSlotInput.value, 10) > minRemaining) perSlotInput.value = minRemaining;

    document.getElementById('capacityNote').textContent = checked.length > 0
        ? `Most-constrained selected slot has ${minRemaining} spot(s) left — capped to that.`
        : '';
}
</script>
@endpush
```

- [ ] **Step 4: Verify**

Run: `php artisan route:list --name=bulk-add` — expect both `bulk-add.show` and `bulk-add.store`.

In the browser: from Cage Management, click "Bulk Add Chickens" on `CAGE-A`, confirm the grid shows real occupancy (slots A-01 through A-06 and B-01 through B-05 occupied from the seeder, rest empty), full slots are visually disabled. Select a few empty slots (e.g. row C), set breed/age, submit, confirm redirect back to Cage Management with a success message and the slots now show updated occupancy.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/BulkAddController.php resources/views/bulk-add.blade.php routes/web.php
git commit -m "Add Bulk Add Chickens slot-picker page"
```

---

### Task 7: Egg Logging — Per-Slot Cards & Re-Keyed Sensor Override

**Files:**
- Modify: `app/Http/Controllers/EggLoggingController.php` (full replace)
- Modify: `resources/views/egg-logging.blade.php` (full replace)
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `CageSlot`, `ProductionLog::cageSlot()` from Task 2.
- Produces: nothing new consumed by later tasks.

**Context:** The existing sensor-override mechanism (PIN-first, password fallback, server-side session flag with a 10-minute TTL, today-only lock, rate-limited verify endpoint) is unchanged in *mechanism* — every place it keyed off `cage_id`/a single cage-wide form now keys off `cage_slot_id`/one form per slot card. Multiple sensor-equipped slots can be on screen at once, so the override modal now takes the target slot id as a parameter instead of relying on one global variable.

- [ ] **Step 1: Replace `app/Http/Controllers/EggLoggingController.php` entirely**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EggLoggingController extends Controller
{
    public function index(Request $request)
    {
        $date       = $request->get('date', now()->toDateString());
        $cageFilter = $request->get('cage', 'all');

        $cages = Cage::where('is_active', 1)->orderBy('cage_code')->get();

        $slotsQuery = CageSlot::with(['cage', 'hens' => fn($q) => $q->where('is_active', 1)])
            ->where('current_occupancy', '>', 0)
            ->whereHas('cage', fn($q) => $q->where('is_active', 1));

        if ($cageFilter !== 'all') {
            $slotsQuery->whereHas('cage', fn($q) => $q->where('cage_code', $cageFilter));
        }

        $slots = $slotsQuery->get()
            ->sortBy([['cage.cage_code', 'asc'], ['slot_number', 'asc']])
            ->map(function ($slot) use ($date) {
                $log = ProductionLog::where('cage_slot_id', $slot->id)->where('log_date', $date)->first();
                $slot->today_egg_count = $log?->egg_count ?? 0;
                return $slot;
            });

        $logs = ProductionLog::with(['cageSlot.cage', 'overriddenBy', 'recorder'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('egg-logging', compact('cages', 'slots', 'date', 'cageFilter', 'logs'));
    }

    public function verifyOverride(Request $request)
    {
        $data = $request->validate([
            'slot_id'  => 'required|exists:cage_slots,id',
            'pin'      => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $user = auth()->user();

        if ($user->override_pin_hash !== null) {
            if (! $data['pin'] || ! Hash::check($data['pin'], $user->override_pin_hash)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect PIN.'], 422);
            }
        } else {
            if (! $data['password'] || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect password.'], 422);
            }
        }

        session()->put("override_verified.{$data['slot_id']}", now()->timestamp);

        return response()->json([
            'ok'              => true,
            'needs_pin_setup' => $user->override_pin_hash === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'log_date'     => 'required|date',
            'cage_slot_id' => 'required|exists:cage_slots,id',
            'egg_count'    => 'required|integer|min:0',
            'hen_count'    => 'required|integer|min:1',
            'notes'        => 'nullable|string',
        ]);

        $slot = CageSlot::find($data['cage_slot_id']);
        $existing = ProductionLog::where('cage_slot_id', $data['cage_slot_id'])
            ->where('log_date', $data['log_date'])
            ->first();

        $payload = [
            'egg_count'   => $data['egg_count'],
            'hen_count'   => $data['hen_count'],
            'hdep'        => round(($data['egg_count'] / $data['hen_count']) * 100, 2),
            'recorded_by' => auth()->id(),
            'notes'       => $data['notes'] ?? 'Manual entry',
        ];

        if ($slot->has_sensor && $data['log_date'] === now()->toDateString()) {
            $valueChanged = ! $existing || (int) $existing->egg_count !== (int) $data['egg_count'];
            $verifiedAt   = session("override_verified.{$data['cage_slot_id']}");
            $stillValid   = $verifiedAt && (now()->timestamp - $verifiedAt) <= 600;

            if ($valueChanged && ! $stillValid) {
                return back()->withInput()->withErrors([
                    'egg_count' => "This slot's reading is sensor-locked. Use the override option to change it.",
                ]);
            }

            if ($valueChanged) {
                $payload['overridden_by_user_id'] = auth()->id();
                $payload['overridden_at']          = now();
                session()->forget("override_verified.{$data['cage_slot_id']}");
            }
        }

        ProductionLog::updateOrCreate(
            ['cage_slot_id' => $data['cage_slot_id'], 'log_date' => $data['log_date']],
            $payload
        );

        return redirect()->route('egg-logging', request()->only('date', 'cage'))->with('success', 'Production log saved.');
    }

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();
        return redirect()->route('egg-logging')->with('success', 'Log deleted.');
    }
}
```

- [ ] **Step 2: Update the verify-override route**

Find:
```php
    Route::post('/egg-logging/verify-override',       [EggLoggingController::class, 'verifyOverride'])->name('egg-logging.verify-override')->middleware('throttle:6,1');
```
(unchanged — same route, the controller method's validation now expects `slot_id` instead of `cage_id`, no route signature change needed.)

- [ ] **Step 3: Replace `resources/views/egg-logging.blade.php` entirely**

```blade
@extends('layouts.app')
@section('title', 'Egg Logging')

@section('content')
<main class="p-5 space-y-5">

    {{-- ── Date / Cage Filter ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
        <form method="GET" action="{{ route('egg-logging') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                <input type="date" name="date" value="{{ $date }}"
                       class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
            </div>
            <div>
                <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                <select name="cage" class="border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E] min-w-[160px]">
                    <option value="all" {{ $cageFilter === 'all' ? 'selected' : '' }}>All Cages</option>
                    @foreach($cages as $c)
                    <option value="{{ $c->cage_code }}" {{ $cageFilter === $c->cage_code ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42]">Apply</button>
        </form>
    </div>

    {{-- ── Per-Slot Cards ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($slots as $slot)
        @php $hen = $slot->hens->first(); @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color:{{ $slot->cage->color }}">{{ $slot->cage->cage_code }} · {{ $slot->label }}</span>
                @if($slot->has_sensor)
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Sensor</span>
                @endif
            </div>
            <div class="text-xs text-[#6B7280] mb-3">{{ $hen?->breed ?? '—' }} · {{ $hen?->current_age_weeks ?? 0 }} wks · {{ $slot->current_occupancy }} hens</div>

            <form method="POST" action="{{ route('egg-logging.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="log_date" value="{{ $date }}">
                <input type="hidden" name="cage_slot_id" value="{{ $slot->id }}">
                <input type="hidden" name="hen_count" value="{{ $slot->current_occupancy }}">

                <div>
                    <label class="block text-xs text-[#333333] mb-1">Egg Count</label>
                    <input type="number" name="egg_count" id="eggCount-{{ $slot->id }}" min="0" required
                           value="{{ $slot->has_sensor && $date === now()->toDateString() ? $slot->today_egg_count : old('egg_count', '') }}"
                           {{ $slot->has_sensor && $date === now()->toDateString() ? 'readonly' : '' }}
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                    @if($slot->has_sensor && $date === now()->toDateString())
                    <button type="button" onclick="openOverrideModal({{ $slot->id }})" class="mt-1 text-xs text-amber-700 flex items-center gap-1">
                        🔒 Sensor reading — click to override
                    </button>
                    @endif
                </div>

                <div>
                    <label class="block text-xs text-[#333333] mb-1">Notes (optional)</label>
                    <input type="text" name="notes" class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>

                <button type="submit" class="w-full bg-[#002D5E] text-white py-2 rounded-lg text-sm hover:bg-[#001F42]">Save</button>
            </form>
        </div>
        @empty
        <div class="md:col-span-2 lg:col-span-3 bg-white rounded-lg border border-[#D9D9D9] p-8 text-center text-sm text-[#6B7280]">
            No occupied slots match this filter. Use Bulk Add Chickens from Cage Management to assign flocks to slots first.
        </div>
        @endforelse
    </div>

    {{-- ── Recent Logs ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#D9D9D9]">
            <h2 class="text-base font-medium text-[#333333]">Recent Logs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Date</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Slot</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Eggs</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Hens</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">HDEP</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Logged By</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Notes</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Override</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->log_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-sm font-medium font-mono" style="color:{{ $log->cageSlot->cage->color }}">{{ $log->cageSlot->cage->cage_code }} · {{ $log->cageSlot->label }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->egg_count }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $log->hen_count }}</td>
                        <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ number_format($log->hdep,1) }}%</td>
                        <td class="px-6 py-3 text-sm text-[#333333]">{{ $log->recorder?->name ?? 'Farm Operator' }}</td>
                        <td class="px-6 py-3 text-sm text-[#6B7280] max-w-[200px] truncate">{{ $log->notes ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if($log->overriddenBy)
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                Manually overridden by {{ $log->overriddenBy->name }}
                            </span>
                            @else
                            <span class="text-xs text-[#6B7280]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <form method="POST" action="{{ route('egg-logging.destroy', $log) }}" onsubmit="return confirm('Delete this log?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-6 py-8 text-center text-sm text-[#6B7280]">No logs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Sensor Override Modal --}}
    <div id="overrideModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
        <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-medium">Override Sensor Reading</h2>
                <button onclick="closeOverrideModal()" class="text-[#6B7280] hover:text-[#333333]">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="overridePinSection">
                <label class="block text-sm text-[#333333] mb-1.5">Enter your Override PIN</label>
                <input type="text" id="overridePinInput" inputmode="numeric" maxlength="6"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-2 focus:outline-none focus:border-[#002D5E]">
            </div>
            <div id="overridePasswordSection" class="hidden">
                <p class="text-xs text-[#6B7280] mb-2">No override PIN set — verify with your login password instead.</p>
                <input type="password" id="overridePasswordInput"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm mb-2 focus:outline-none focus:border-[#002D5E]">
            </div>
            <p id="overrideError" class="hidden text-[11px] text-red-500 mb-3"></p>
            <button type="button" onclick="submitOverride()" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">
                Unlock Field
            </button>
        </div>
    </div>

</main>
@endsection

@push('scripts')
<script>
lucide.createIcons();

let currentSensorSlotId = null;

function openOverrideModal(slotId) {
    currentSensorSlotId = slotId;
    document.getElementById('overrideError').classList.add('hidden');
    document.getElementById('overridePinInput').value = '';
    document.getElementById('overridePinSection').classList.remove('hidden');
    document.getElementById('overridePasswordSection').classList.add('hidden');
    document.getElementById('overrideModal').classList.remove('hidden');
}

function closeOverrideModal() {
    document.getElementById('overrideModal').classList.add('hidden');
}

function submitOverride() {
    const pin = document.getElementById('overridePinInput').value;
    const password = document.getElementById('overridePasswordInput').value;

    fetch('{{ route("egg-logging.verify-override") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ slot_id: currentSensorSlotId, pin: pin, password: password }),
    })
    .then(r => r.json().then(body => ({ status: r.status, body })))
    .then(({ status, body }) => {
        if (status === 200 && body.ok) {
            document.getElementById('eggCount-' + currentSensorSlotId).readOnly = false;
            closeOverrideModal();
            if (body.needs_pin_setup) {
                alert('No override PIN set yet — please set one in Account Settings.');
            }
        } else {
            const errEl = document.getElementById('overrideError');
            errEl.textContent = body.error || 'Verification failed.';
            errEl.classList.remove('hidden');
            const noPinYet = (body.error || '').includes('password');
            document.getElementById('overridePinSection').classList.toggle('hidden', noPinYet);
            document.getElementById('overridePasswordSection').classList.toggle('hidden', !noPinYet);
        }
    });
}
</script>
@endpush
```

- [ ] **Step 4: Verify**

Run: `php artisan route:list --name=egg-logging` — same 4 routes as before, unchanged signatures.

In the browser: visit `/egg-logging`, confirm cards render for every occupied seeded slot (11 + 8 + 6 = 25 cards), `CAGE-A` slot A-03 and `CAGE-B` slot A-05 show the 🔒 lock (today's date, sensor-equipped). Test the override flow on one locked card exactly as before (wrong PIN → password fallback → success → field unlocks → save → "Manually overridden by" badge appears in Recent Logs, scoped to that one slot — confirm a *different* sensor-equipped card is unaffected and still locked).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/EggLoggingController.php resources/views/egg-logging.blade.php
git commit -m "Switch Egg Logging to per-slot cards, re-key sensor override to cage_slot_id"
```

---

### Task 8: Forecast — Add Per Row Scope

**Files:**
- Modify: `app/Http/Controllers/ForecastController.php` (full replace)
- Modify: `resources/views/forecast.blade.php` (full replace)

**Interfaces:**
- Consumes: `Forecast.row_number`, `ProductionLog::cageSlot()`, `CageSlot.row_number` from Tasks 1-2.
- Produces: nothing new consumed by later tasks — this is the last task in the plan.

**Context:** `production_logs` no longer has a direct `cage_id` — cage-level and row-level aggregation both now join through `cage_slots`. Whole-farm aggregation (already built) is unaffected since it already worked directly off `production_logs` regardless of cage.

- [ ] **Step 1: Replace `app/Http/Controllers/ForecastController.php` entirely**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $scope    = $request->get('scope', 'cage');
        $cageCode = $request->get('cage', 'CAGE-A');
        $row      = $request->filled('row') ? (int) $request->get('row') : null;
        $horizon  = (int) $request->get('horizon', 7);
        $allCages = Cage::orderBy('cage_code')->get();

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::whereNull('cage_id')->whereNull('row_number')
                ->where('forecast_date', now()->toDateString())
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast(null, null, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cageCode', 'row', 'horizon', 'historical', 'forecasts', 'allCages'))
                ->with('cage', null);
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        if ($scope === 'row' && $row !== null) {
            $historical = $this->rowHistorical($cage, $row);
            $forecasts  = Forecast::where('cage_id', $cage->id)->where('row_number', $row)
                ->where('forecast_date', now()->toDateString())
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast($cage, $row, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cage', 'cageCode', 'row', 'horizon', 'historical', 'forecasts', 'allCages'));
        }

        $historical = $this->cageHistorical($cage);
        $forecasts  = Forecast::where('cage_id', $cage->id)->whereNull('row_number')
            ->where('forecast_date', now()->toDateString())
            ->orderBy('target_date')->limit($horizon)->get();

        if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
            $forecasts = $this->generateForecast($cage, null, $historical, $horizon);
        }

        return view('forecast', compact('scope', 'cage', 'cageCode', 'row', 'horizon', 'historical', 'forecasts', 'allCages'));
    }

    public function generate(Request $request)
    {
        $scope    = $request->get('scope', 'cage');
        $cageCode = $request->get('cage', 'CAGE-A');
        $row      = $request->filled('row') ? (int) $request->get('row') : null;
        $horizon  = (int) $request->get('horizon', 7);

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            Forecast::whereNull('cage_id')->whereNull('row_number')->where('forecast_date', now()->toDateString())->delete();
            $this->generateForecast(null, null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'farm', 'horizon' => $horizon])
                ->with('success', 'Whole-farm forecast generated.');
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        if ($scope === 'row' && $row !== null) {
            $historical = $this->rowHistorical($cage, $row);
            Forecast::where('cage_id', $cage->id)->where('row_number', $row)->where('forecast_date', now()->toDateString())->delete();
            $this->generateForecast($cage, $row, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'row', 'cage' => $cageCode, 'row' => $row, 'horizon' => $horizon])
                ->with('success', 'Per-row forecast generated.');
        }

        $historical = $this->cageHistorical($cage);
        Forecast::where('cage_id', $cage->id)->whereNull('row_number')->where('forecast_date', now()->toDateString())->delete();
        $this->generateForecast($cage, null, $historical, $horizon, true);

        return redirect()->route('forecast', ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon])
            ->with('success', 'Forecast generated.');
    }

    private function farmHistorical(): Collection
    {
        return ProductionLog::selectRaw('log_date, SUM(egg_count) as egg_count, SUM(hen_count) as hen_count')
            ->groupBy('log_date')->orderByDesc('log_date')->limit(14)->get()
            ->map(fn($row) => $this->withHdep($row))->reverse()->values();
    }

    private function cageHistorical(Cage $cage): Collection
    {
        return ProductionLog::join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->where('cage_slots.cage_id', $cage->id)
            ->selectRaw('production_logs.log_date, SUM(production_logs.egg_count) as egg_count, SUM(production_logs.hen_count) as hen_count')
            ->groupBy('production_logs.log_date')
            ->orderByDesc('production_logs.log_date')
            ->limit(14)
            ->get()
            ->map(fn($row) => $this->withHdep($row))->reverse()->values();
    }

    private function rowHistorical(Cage $cage, int $rowNumber): Collection
    {
        return ProductionLog::join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->where('cage_slots.cage_id', $cage->id)
            ->where('cage_slots.row_number', $rowNumber)
            ->selectRaw('production_logs.log_date, SUM(production_logs.egg_count) as egg_count, SUM(production_logs.hen_count) as hen_count')
            ->groupBy('production_logs.log_date')
            ->orderByDesc('production_logs.log_date')
            ->limit(14)
            ->get()
            ->map(fn($row) => $this->withHdep($row))->reverse()->values();
    }

    private function withHdep($row)
    {
        $row->hdep = $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0;
        return $row;
    }

    private function generateForecast(?Cage $cage, ?int $row, Collection $historical, int $horizon, bool $save = false): Collection
    {
        $avgHdep   = $historical->avg('hdep') ?? 85.0;
        $forecasts = collect();
        $today     = now()->toDateString();

        for ($i = 1; $i <= $horizon; $i++) {
            $targetDate = now()->addDays($i)->toDateString();
            $variation  = (($i % 3) - 1) * 0.3;
            $predicted  = round(min(100, max(0, $avgHdep + $variation)), 2);

            $forecast = new Forecast([
                'cage_id'        => $cage?->id,
                'row_number'     => $row,
                'forecast_date'  => $today,
                'target_date'    => $targetDate,
                'predicted_hdep' => $predicted,
            ]);

            if ($save) $forecast->save();
            $forecasts->push($forecast);
        }

        return $forecasts;
    }
}
```

- [ ] **Step 2: Replace `resources/views/forecast.blade.php` entirely**

```blade
@extends('layouts.app')
@section('title', 'Forecast')

@section('content')
<main class="p-5 space-y-5">

    @php
        $cageColor  = $scope === 'farm' ? '#102A4C' : match($cageCode){'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#2D7D46'};
        $scopeLabel = match($scope) {
            'farm' => 'Whole Farm',
            'row'  => $cageCode . ' — Row ' . ($row ? chr(64 + $row) : '?'),
            default => $cageCode,
        };
        $cageRowsCount = isset($cage) && $cage ? $cage->rows : 0;
    @endphp

    <h1 class="text-xl font-medium text-[#333333]">Forecast</h1>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Inputs Panel ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-4">FORECAST INPUTS</div>
            <form method="POST" action="{{ route('forecast.generate') }}">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}">

                <label class="block text-sm text-[#333333] mb-2">Scope</label>
                <div class="flex gap-2 mb-4">
                    <a href="{{ route('forecast', ['scope'=>'farm','horizon'=>$horizon]) }}"
                       class="flex-1 text-center py-2 rounded-lg text-xs border {{ $scope === 'farm' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280]' }}">
                        Whole Farm
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'cage','cage'=>$cageCode,'horizon'=>$horizon]) }}"
                       class="flex-1 text-center py-2 rounded-lg text-xs border {{ $scope === 'cage' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280]' }}">
                        Per Cage
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'row','cage'=>$cageCode,'row'=>$row ?? 1,'horizon'=>$horizon]) }}"
                       class="flex-1 text-center py-2 rounded-lg text-xs border {{ $scope === 'row' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280]' }}">
                        Per Row
                    </a>
                </div>

                @if($scope === 'cage' || $scope === 'row')
                <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                <select name="cage" onchange="this.form.submit()" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allCages as $c)
                    <option value="{{ $c->cage_code }}" {{ $c->cage_code === $cageCode ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>
                @else
                <input type="hidden" name="cage" value="{{ $cageCode }}">
                @endif

                @if($scope === 'row')
                <label class="block text-sm text-[#333333] mb-2">Select Row</label>
                <select name="row" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @for($r = 1; $r <= $cageRowsCount; $r++)
                    <option value="{{ $r }}" {{ $row == $r ? 'selected' : '' }}>Row {{ chr(64 + $r) }}</option>
                    @endfor
                </select>
                @endif

                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#333333]">{{ $scopeLabel }}</span></p>

                <label class="block text-sm text-[#333333] mb-2">Forecast horizon</label>
                <div class="flex gap-4 mb-5">
                    @foreach([7,14,30] as $h)
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="radio" name="horizon" value="{{ $h }}" {{ $horizon == $h ? 'checked' : '' }} class="accent-[#002D5E]">
                        {{ $h }} days
                    </label>
                    @endforeach
                </div>

                <button type="submit" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
                    Generate Forecast
                </button>
            </form>
        </div>

        {{-- ── Chart Panel ── --}}
        <div class="xl:col-span-2 bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-4">HISTORICAL VS FORECAST HDEP</div>
            <canvas id="forecastChart" height="160"></canvas>
        </div>
    </div>

    {{-- ── Forecast Table ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Date</th>
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Predicted HDEP</th>
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Confidence</th>
                </tr>
            </thead>
            <tbody>
                @forelse($forecasts as $f)
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                    <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $f->target_date->format('Y-m-d') }}</td>
                    <td class="px-6 py-3 text-sm text-[#333333]">{{ number_format($f->predicted_hdep,1) }}%</td>
                    <td class="px-6 py-3">
                        <span class="text-xs px-2.5 py-1 rounded-full" style="background:{{ $f->confidenceColor }}">
                            {{ $f->confidence }}%
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-[#6B7280]">
                    No forecast generated yet. Click "Generate Forecast" above.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</main>
@endsection

@push('scripts')
<script>
const historical = @json($historical->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'hdep'=>$l->hdep]));
const forecasts  = @json($forecasts->map(fn($f) => ['date'=>$f->target_date->format('Y-m-d'),'hdep'=>$f->predicted_hdep]));
const cageColor  = '{{ $cageColor }}';

const histLabels = historical.map(h => 'H-' + (historical.length - historical.indexOf(h)));
const fcLabels   = forecasts.map((_, i) => 'F+' + (i+1));
const allLabels  = [...histLabels, ...fcLabels];

const histData = [...historical.map(h => h.hdep), ...Array(fcLabels.length).fill(null)];
const fcData   = [...Array(histLabels.length).fill(null), ...forecasts.map(f => f.hdep)];

new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels: allLabels,
        datasets: [
            { label: 'Historical', data: histData, borderColor: '#333333', backgroundColor: 'transparent', tension: 0.3, pointRadius: 3, borderWidth: 2 },
            { label: 'Forecast', data: fcData, borderColor: '#C2703E', backgroundColor: '#C2703E22', tension: 0.3, borderDash: [5,3], pointRadius: 3, fill: true, borderWidth: 2 },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } } },
        scales: {
            x: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } } },
            y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, min: 0, max: 100 },
        }
    }
});
</script>
@endpush
```

- [ ] **Step 3: Verify**

Run: `php artisan view:cache` — no compile errors.

In tinker:
```php
\Illuminate\Support\Facades\Auth::loginUsingId(1);
$req = \Illuminate\Http\Request::create('/forecast?scope=row&cage=CAGE-A&row=1&horizon=7', 'GET');
app()->instance('request', $req);
$html = app(\App\Http\Controllers\ForecastController::class)->index($req)->render();
echo str_contains($html, 'CAGE-A — Row A') ? 'OK: row scope renders' : 'FAIL';
```
Expected: `OK: row scope renders`.

In the browser: visit `/forecast`, click "Per Row," select `CAGE-A`, select Row A (the row with 6 occupied/logged slots from the seeder), click "Generate Forecast," confirm a chart and table render using that row's aggregated history. Switch the cage dropdown to `CAGE-A` and Row B — confirm the historical line changes (different slots' data).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ForecastController.php resources/views/forecast.blade.php
git commit -m "Add Per Row forecasting scope"
```

---

---

### Task 9: Fix Dashboard, Analytics, and Reports — Removed Relations/Columns

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php` (full replace)
- Modify: `app/Http/Controllers/AnalyticsController.php` (full replace)
- Modify: `app/Http/Controllers/ReportController.php` (`buildSummary()`'s production case, `productionReport()`)

**Interfaces:**
- Consumes: `Cage`, `CageSlot`, `ProductionLog::cageSlot()` from Task 2.
- Produces: nothing new — this is a compatibility fix, not a new feature.

**Context:** This was caught during this plan's own self-review, not by a task that already ran. `DashboardController` eager-loads a `latestProduction` relation that Task 2 removes from `Cage` (querying it afterward returns `null` silently — Eloquent doesn't error on an unknown dynamic relation access, it just returns nothing, so this would produce wrong-looking-fine numbers, not a crash). `AnalyticsController` and `ReportController`'s production-report code query `production_logs.cage_id` directly, a column Task 1 renames to `cage_slot_id` — these **do** crash with a SQL "Unknown column" error. Every other part of `ReportController` (feed/environment/mortality reports) queries tables this plan doesn't touch and needs no change.

- [ ] **Step 1: Replace `app/Http/Controllers/DashboardController.php` entirely**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $cages = Cage::with(['latestEnvironment', 'slots'])->get();

        // Total hens = sum of real occupancy across every slot, not a per-cage all-or-nothing guess.
        $totalHens = CageSlot::sum('current_occupancy');

        // Today's average HDEP (joins through cage_slots since production_logs no longer has cage_id directly).
        $todayLogs = ProductionLog::whereDate('log_date', $today)->get();
        $todayHdep = $todayLogs->count() ? round($todayLogs->avg('hdep'), 1) : 0;

        // Yesterday comparison
        $yesterdayLogs = ProductionLog::whereDate('log_date', now()->subDay()->toDateString())->get();
        $yesterdayHdep = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
        $hdepDelta     = round($todayHdep - $yesterdayHdep, 1);

        // Eggs collected today
        $eggsToday = $todayLogs->sum('egg_count');

        // Coop environment averages (environmental_logs is unaffected — still cage_id directly)
        $latestEnv = EnvironmentalLog::whereIn('cage_id', $cages->pluck('id'))
            ->orderByDesc('recorded_at')
            ->limit($cages->count())
            ->get();
        $avgTemp = $latestEnv->count() ? round($latestEnv->avg('temperature_c'), 1) : null;
        $avgHum  = $latestEnv->count() ? round($latestEnv->avg('humidity_pct'), 1) : null;

        // Feed today (unaffected — feed_consumption_logs is still cage_id directly)
        $feedToday = FeedConsumptionLog::with('cage')
            ->whereDate('log_date', $today)
            ->orWhereDate('log_date', now()->subDay()->toDateString())
            ->orderByDesc('log_date')
            ->get()
            ->groupBy(fn($f) => $f->cage->cage_code)
            ->map(fn($g) => $g->first());

        // Mortality today (unaffected — mortality_logs is still cage_id directly)
        $mortalityToday = MortalityLog::with('cage')
            ->whereDate('log_date', $today)
            ->get()
            ->groupBy(fn($l) => $l->cage->cage_code)
            ->map(fn($g) => $g->sum('count'));
        $mortalityTodayTotal = $mortalityToday->sum();

        // Alerts (unaffected — alerts is still cage_id directly)
        $alertCount   = Alert::where('is_read', false)->count();
        $recentAlerts = Alert::with('cage')
            ->orderByRaw('is_read ASC')
            ->orderByDesc('triggered_at')
            ->limit(4)
            ->get();

        // Live readings per cage (unaffected — latestEnvironment/color are still on Cage)
        $liveReadings = $cages->map(function ($cage) {
            $env = $cage->latestEnvironment;
            if (! $env) return null;
            $status = 'Normal';
            if ($env->temperature_c > 30 || $env->humidity_pct > 70) $status = 'Alert';
            elseif ($env->temperature_c > 28.5 || $env->humidity_pct >= 70) $status = 'Watch';
            return (object) [
                'cage'   => $cage->cage_code,
                'color'  => $cage->color,
                'temp'   => $env->temperature_c . '°C',
                'hum'    => $env->humidity_pct . '%',
                'status' => $status,
            ];
        })->filter();

        return view('dashboard', compact(
            'cages', 'totalHens', 'todayHdep', 'hdepDelta',
            'eggsToday', 'avgTemp', 'avgHum', 'feedToday',
            'mortalityToday', 'mortalityTodayTotal',
            'alertCount', 'recentAlerts', 'liveReadings', 'today'
        ));
    }
}
```

- [ ] **Step 1b: Fix `dashboard.blade.php`'s Cage Overview card**

Find:
```blade
            @foreach($cages as $cage)
            @php
                $prod = $cage->latestProduction;
                $hdep = $prod?->hdep ?? 0;
                $color = $cage->color;
                $hen = $cage->hens->first();
                $hdepBg = $hdep > 70 ? '#D5E8D4' : ($hdep > 40 ? '#FFF3CD' : '#F8D7DA');
                $hdepTxt = $hdep > 70 ? '#004F9F' : ($hdep > 40 ? '#856404' : '#721C24');
            @endphp
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <span class="text-sm font-medium" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                        <span class="text-sm text-[#6B7280] ml-2">{{ $hen?->breed ?? '—' }}</span>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded" style="background:{{ $hdepBg }};color:{{ $hdepTxt }}">{{ number_format($hdep,1) }}%</span>
                </div>
                <div class="text-[11px] text-[#6B7280] mb-3 flex items-center gap-3">
                    <span>{{ $cage->capacity }} hens</span>
                    @if($hen)
                    <span>{{ $hen->current_age_weeks }} wks</span>
                    @endif
                    @include('partials.cage-sensor-badge', ['cage' => $cage])
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('cages.index') }}" class="flex-1 flex items-center justify-center gap-1.5 bg-[#F5F6F8] text-[#6B7280] py-2 rounded text-xs hover:bg-[#EAF0F8] border border-[#D9D9D9]">
                        <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                    </a>
                    <a href="{{ route('analytics', ['cage' => $cage->cage_code]) }}" class="flex-1 text-center text-white py-2 rounded text-xs" style="background-color:{{ $color }}">
                        View Detail
                    </a>
                </div>
            </div>
            @endforeach
```
Replace with:
```blade
            @foreach($cages as $cage)
            @php
                $color = $cage->color;
                $occupiedSlots = $cage->slots->where('current_occupancy', '>', 0)->count();
                $totalSlots = $cage->slots->count();
                $sensorSlots = $cage->slots->where('has_sensor', true)->count();
            @endphp
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <span class="text-sm font-medium" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                        <span class="text-sm text-[#6B7280] ml-2">{{ $occupiedSlots }}/{{ $totalSlots }} slots occupied</span>
                    </div>
                </div>
                <div class="text-[11px] text-[#6B7280] mb-3 flex items-center gap-3">
                    <span>{{ $cage->total_capacity }} max capacity</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] {{ $sensorSlots > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $sensorSlots }} sensor slot{{ $sensorSlots === 1 ? '' : 's' }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('cages.index') }}" class="flex-1 flex items-center justify-center gap-1.5 bg-[#F5F6F8] text-[#6B7280] py-2 rounded text-xs hover:bg-[#EAF0F8] border border-[#D9D9D9]">
                        <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                    </a>
                    <a href="{{ route('analytics', ['cage' => $cage->cage_code]) }}" class="flex-1 text-center text-white py-2 rounded text-xs" style="background-color:{{ $color }}">
                        View Detail
                    </a>
                </div>
            </div>
            @endforeach
```

This drops the per-cage HDEP badge from this card (it required the now-removed `latestProduction` relation) in favor of slot occupancy counts — a deliberate simplification, not an oversight. Farm-wide HDEP is still shown in the metric row above, and per-cage HDEP detail is still available on the Analytics page (Task 9 Step 2 keeps that working). The `cage-sensor-badge` partial is no longer included here since a single boolean no longer represents a cage with multiple independently-sensored slots — the sensor-slot count above replaces it.

- [ ] **Step 2: Replace `app/Http/Controllers/AnalyticsController.php` entirely**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $cageCode = $request->get('cage', 'CAGE-A');
        $period   = $request->get('period', 'week');

        $cage = Cage::with(['slots.hens' => fn($q) => $q->where('is_active', 1)])
            ->where('cage_code', $cageCode)->firstOrFail();

        $days = match($period) {
            'month'   => 30,
            '3months' => 90,
            default   => 7,
        };

        $logs = ProductionLog::join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->where('cage_slots.cage_id', $cage->id)
            ->where('production_logs.log_date', '>=', now()->subDays($days))
            ->orderBy('production_logs.log_date')
            ->select('production_logs.*')
            ->get();

        $feedLogs = FeedConsumptionLog::where('cage_id', $cage->id)
            ->where('log_date', '>=', now()->subDays($days))
            ->orderBy('log_date')
            ->get();

        $avgHdep  = $logs->count() ? round($logs->avg('hdep'), 1) : 0;
        $bestDay  = $logs->count() ? round($logs->max('hdep'), 1) : 0;
        $worstDay = $logs->count() ? round($logs->min('hdep'), 1) : 0;

        $allCages = Cage::orderBy('cage_code')->get();

        return view('analytics', compact(
            'cage', 'cageCode', 'period', 'logs', 'feedLogs',
            'avgHdep', 'bestDay', 'worstDay', 'allCages'
        ));
    }
}
```

Note: `analytics.blade.php` reads `$cage->hens->first()?->current_age_weeks` (line 78, per this project's history) — `Cage::hens()` is still a valid relation (now `hasManyThrough` via `cage_slots`, defined in Task 2), so this line keeps working unchanged.

- [ ] **Step 3: Fix `ReportController::buildSummary()`'s `production` case**

Find:
```php
            'production' => (object) [
                'total_eggs'  => ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->sum('egg_count'),
                'avg_hdep'    => number_format(ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->avg('hdep') ?? 0, 1) . '%',
                'total_hens'  => ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->max('hen_count') ?? 0,
                'days'        => ProductionLog::whereIn('cage_id', $cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
            ],
```
Replace with:
```php
            'production' => (object) [
                'total_eggs'  => $this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->sum('egg_count'),
                'avg_hdep'    => number_format($this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->avg('hdep') ?? 0, 1) . '%',
                'total_hens'  => $this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->max('hen_count') ?? 0,
                'days'        => $this->productionLogsForCages($cageIds)->whereBetween('log_date', [$from, $to])->distinct('log_date')->count('log_date'),
            ],
```

- [ ] **Step 4: Fix `ReportController::productionReport()`**

Find the entire method:
```php
    private function productionReport($from, $to, $cageIds, $allCages)
    {
        return ProductionLog::with(['cage.hens'])
            ->whereIn('cage_id', $cageIds)
            ->whereBetween('log_date', [$from, $to])
            ->orderByDesc('log_date')
            ->get()
            ->map(function ($log) {
                $feed = FeedConsumptionLog::with('feedBatch')
                    ->where('cage_id', $log->cage_id)
                    ->where('log_date', $log->log_date)
                    ->first();
                $env = EnvironmentalLog::where('cage_id', $log->cage_id)
                    ->whereDate('recorded_at', $log->log_date)
                    ->avg('temperature_c');
                $hum = EnvironmentalLog::where('cage_id', $log->cage_id)
                    ->whereDate('recorded_at', $log->log_date)
                    ->avg('humidity_pct');

                return (object) [
                    'date'     => $log->log_date->format('Y-m-d'),
                    'cage'     => $log->cage->cage_code,
                    'breed'    => $log->cage->hens->first()?->breed ?? '—',
                    'eggs'     => $log->egg_count,
                    'hens'     => $log->hen_count,
                    'hdep'     => number_format($log->hdep, 1) . '%',
                    'feed_kg'  => $feed ? number_format($feed->feed_consumed_kg, 1) : '—',
                    'cp_pct'   => $feed?->feedBatch ? number_format($feed->feedBatch->crude_protein, 1) . '%' : '—',
                    'temp'     => $env ? number_format($env, 1) : '—',
                    'humidity' => $hum ? number_format($hum, 1) . '%' : '—',
                ];
            });
    }
```
Replace with:
```php
    private function productionReport($from, $to, $cageIds, $allCages)
    {
        return $this->productionLogsForCages($cageIds)
            ->with(['cageSlot.cage', 'cageSlot.hens'])
            ->whereBetween('log_date', [$from, $to])
            ->orderByDesc('log_date')
            ->get()
            ->map(function ($log) {
                $cageId = $log->cageSlot->cage_id;
                $feed = FeedConsumptionLog::with('feedBatch')
                    ->where('cage_id', $cageId)
                    ->where('log_date', $log->log_date)
                    ->first();
                $env = EnvironmentalLog::where('cage_id', $cageId)
                    ->whereDate('recorded_at', $log->log_date)
                    ->avg('temperature_c');
                $hum = EnvironmentalLog::where('cage_id', $cageId)
                    ->whereDate('recorded_at', $log->log_date)
                    ->avg('humidity_pct');

                return (object) [
                    'date'     => $log->log_date->format('Y-m-d'),
                    'cage'     => $log->cageSlot->cage->cage_code,
                    'slot'     => $log->cageSlot->label,
                    'breed'    => $log->cageSlot->hens->first()?->breed ?? '—',
                    'eggs'     => $log->egg_count,
                    'hens'     => $log->hen_count,
                    'hdep'     => number_format($log->hdep, 1) . '%',
                    'feed_kg'  => $feed ? number_format($feed->feed_consumed_kg, 1) : '—',
                    'cp_pct'   => $feed?->feedBatch ? number_format($feed->feedBatch->crude_protein, 1) . '%' : '—',
                    'temp'     => $env ? number_format($env, 1) : '—',
                    'humidity' => $hum ? number_format($hum, 1) . '%' : '—',
                ];
            });
    }
```

- [ ] **Step 5: Add the shared `productionLogsForCages()` helper**

Add this private method to `ReportController` (anywhere among the other private methods):

```php
    private function productionLogsForCages($cageIds)
    {
        return ProductionLog::join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->select('production_logs.*');
    }
```

Add `use App\Models\ProductionLog;` if not already imported (it already is, per the existing file).

- [ ] **Step 5b: Delete the now-unused `cage-sensor-badge` partial**

After Task 4 (which replaces `cages/index.blade.php`'s cage-level table with the slot grid) and this task's Step 1b (which replaces `dashboard.blade.php`'s cage-overview card), `resources/views/partials/cage-sensor-badge.blade.php` has no remaining includes anywhere — its replacement, `partials/slot-box.blade.php` (Task 4), now carries the sensor indicator at the correct slot level. Confirm with:

Run: `grep -rn "cage-sensor-badge" resources/views/`
Expected: no output (zero matches).

Then delete it:
```bash
rm resources/views/partials/cage-sensor-badge.blade.php
```

- [ ] **Step 6: Verify**

In tinker:
```php
\Illuminate\Support\Facades\Auth::loginUsingId(1);
$dashboard = app(\App\Http\Controllers\DashboardController::class)->index();
echo $dashboard->render() ? 'Dashboard OK' : 'FAIL';

$req = \Illuminate\Http\Request::create('/analytics?cage=CAGE-A', 'GET');
app()->instance('request', $req);
$analytics = app(\App\Http\Controllers\AnalyticsController::class)->index($req);
echo $analytics->render() ? 'Analytics OK' : 'FAIL';

$req2 = \Illuminate\Http\Request::create('/reports?type=production&from=2026-01-01&to=2026-12-31&cage=all', 'GET');
app()->instance('request', $req2);
$reports = app(\App\Http\Controllers\ReportController::class)->index($req2);
echo $reports->render() ? 'Reports OK' : 'FAIL';
```
Expected: all three print `... OK` with no SQL errors and no "call to member function on null" fatals.

In the browser: visit `/`, `/analytics?cage=CAGE-A`, and generate a Production report from `/reports` — confirm all three render real numbers (not all-zero) and no error page.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DashboardController.php app/Http/Controllers/AnalyticsController.php app/Http/Controllers/ReportController.php resources/views/dashboard.blade.php
git rm resources/views/partials/cage-sensor-badge.blade.php
git commit -m "Fix Dashboard, Analytics, and Reports for slot-grid cage model (cage_slot_id joins)"
```

---

## Self-Review

**Spec coverage:** All schema changes (Task 1-2), seeder (Task 3), Cage Management create/edit/delete with resize-safety and historical-data delete guard (Tasks 4-5), Bulk Add Chickens (Task 6), Egg Logging per-slot + re-keyed override (Task 7), Forecast Per Row (Task 8) — every section of the spec maps to a task. The spec's "Out of Scope" items (per-slot mortality, dedicated sensors table, Account Settings changes) have no corresponding task, correctly. Task 9 was added during self-review — it's not in the original spec, but it's a necessary consequence of the spec's own schema changes (the spec didn't enumerate every consumer of `production_logs.cage_id` across the codebase, and three controllers outside the spec's explicit scope — Dashboard, Analytics, Reports — break without it).

**Placeholder scan:** No "TBD"/"TODO" — every step shows the actual code. Verification steps use real tinker commands and named browser-click sequences with expected outcomes, not "write tests for the above."

**Type consistency:** `cage_slot_id` is used identically across Task 1's migration, Task 2's model `$fillable` arrays, Task 4/6/7's controller code, and Task 7/8's queries. `CageSlot->label`, `->status`, `Cage->total_capacity` (defined in Task 2) are consumed with matching names in Tasks 4-8. `Forecast.row_number` (Task 1) flows through `ForecastController` (Task 8) consistently. The destructive-table-list named in the Global Constraints (`cages`, `cage_slots`, `hens`, `production_logs`) matches exactly what Task 1's migration drops — no task touches `environmental_logs`/`feed_consumption_logs`/`mortality_logs`/`alerts` structurally, only truncates their rows in Task 1 as already specified.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-29-slot-grid-cage-model.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
