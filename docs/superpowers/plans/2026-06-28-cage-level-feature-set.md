# Cage-Level Feature Set Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add forecasting scope (Whole Farm/Per Cage), cage-level IR sensor flagging with PIN-gated egg-count override, flock placement-date tracking, and an Account Settings page with Override PIN management — all at cage granularity, matching the capstone manuscript (no slot/row model).

**Architecture:** Five additive schema changes (all nullable/safe-default columns, zero breaking changes to existing data) followed by controller/view changes per feature area. No new tables. No `doctrine/dbal` dependency — confirmed not installed, so any column alteration uses raw `DB::statement()`.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS (CDN), vanilla JS (no Alpine/Vue in this codebase), MySQL via XAMPP.

## Global Constraints

- No `cage_slots` / `chickens` tables — slot/row model is explicitly out of scope (see spec)
- Every new column is nullable or has a safe default — the 4 existing seeded cages and their hens/production_logs/users rows must keep working unmodified
- `doctrine/dbal` is NOT installed (verified via `vendor/doctrine/dbal` absence) — do not use `->change()` on existing columns; use `DB::statement()` raw SQL instead
- No PHPUnit test suite exists in this project (intentionally removed) — verify each task via `php artisan tinker` and manual route/UI checks, not automated tests
- Match existing conventions: plain Blade forms (no Alpine/Vue/fetch-heavy patterns except where this plan explicitly adds one small fetch call), Tailwind utility classes using the existing palette (navy `#002D5E`/`#102A4C`, gray `#6B7280`/`#D9D9D9`, green `#D5E8D4`/`#2D6A4F`), `auth()->user()`/`auth()->id()` for the current user — never hardcoded user IDs
- PIN values are hashed via `Hash::make()` and never displayed back to anyone, including admins

---

### Task 1: Schema Migrations

**Files:**
- Create: `database/migrations/2026_06_28_000001_add_has_sensor_to_cages_table.php`
- Create: `database/migrations/2026_06_28_000002_add_placement_fields_to_hens_table.php`
- Create: `database/migrations/2026_06_28_000003_add_override_fields_to_production_logs_table.php`
- Create: `database/migrations/2026_06_28_000004_add_override_pin_hash_to_users_table.php`
- Create: `database/migrations/2026_06_28_000005_make_cage_id_nullable_on_forecasts_table.php`

**Interfaces:**
- Produces: `cages.has_sensor` (bool, default false), `cages.sensor_device_id` (string, nullable); `hens.placement_date` (date, nullable), `hens.age_at_placement_weeks` (unsigned int, nullable); `production_logs.overridden_by_user_id` (nullable FK → users.id, null on delete), `production_logs.overridden_at` (nullable timestamp); `users.override_pin_hash` (string, nullable); `forecasts.cage_id` becomes nullable (was required).

- [ ] **Step 1: Write migration 1 — cages**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cages', function (Blueprint $table) {
            $table->boolean('has_sensor')->default(false)->after('is_active');
            $table->string('sensor_device_id', 100)->nullable()->after('has_sensor');
        });
    }

    public function down(): void
    {
        Schema::table('cages', function (Blueprint $table) {
            $table->dropColumn(['has_sensor', 'sensor_device_id']);
        });
    }
};
```

- [ ] **Step 2: Write migration 2 — hens**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->date('placement_date')->nullable()->after('date_acquired');
            $table->unsignedInteger('age_at_placement_weeks')->nullable()->after('placement_date');
        });
    }

    public function down(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->dropColumn(['placement_date', 'age_at_placement_weeks']);
        });
    }
};
```

- [ ] **Step 3: Write migration 3 — production_logs**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->foreignId('overridden_by_user_id')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable()->after('overridden_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overridden_by_user_id');
            $table->dropColumn('overridden_at');
        });
    }
};
```

- [ ] **Step 4: Write migration 4 — users**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('override_pin_hash')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('override_pin_hash');
        });
    }
};
```

- [ ] **Step 5: Write migration 5 — forecasts (raw SQL, no doctrine/dbal needed)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE forecasts MODIFY cage_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE forecasts MODIFY cage_id BIGINT UNSIGNED NOT NULL');
    }
};
```

- [ ] **Step 6: Run the migrations**

Run: `php artisan migrate`
Expected: all 5 new migrations listed as `Migrating` then `Migrated`, no errors (specifically no "doctrine/dbal" error on migration 5, since it uses raw SQL).

- [ ] **Step 7: Verify columns exist**

Run: `php artisan tinker --no-interaction`
```php
\Illuminate\Support\Facades\Schema::hasColumn('cages', 'has_sensor');
\Illuminate\Support\Facades\Schema::hasColumn('cages', 'sensor_device_id');
\Illuminate\Support\Facades\Schema::hasColumn('hens', 'placement_date');
\Illuminate\Support\Facades\Schema::hasColumn('hens', 'age_at_placement_weeks');
\Illuminate\Support\Facades\Schema::hasColumn('production_logs', 'overridden_by_user_id');
\Illuminate\Support\Facades\Schema::hasColumn('production_logs', 'overridden_at');
\Illuminate\Support\Facades\Schema::hasColumn('users', 'override_pin_hash');
\Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM forecasts WHERE Field = 'cage_id'")[0]->Null;
```
Expected: every `hasColumn` call returns `true`; the last line returns `"YES"`.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_06_28_*.php
git commit -m "Add schema for cage sensors, flock placement dates, log overrides, PIN, and farm-wide forecasts"
```

---

### Task 2: Hen Computed Age Accessor

**Files:**
- Modify: `app/Models/Hen.php`
- Modify: `resources/views/dashboard.blade.php:81`
- Modify: `resources/views/egg-logging.blade.php:30`
- Modify: `resources/views/cages/index.blade.php:50`
- Modify: `resources/views/analytics.blade.php:78`

**Interfaces:**
- Consumes: `hens.placement_date`, `hens.age_at_placement_weeks` (from Task 1)
- Produces: `Hen::getCurrentAgeWeeksAttribute()` → accessed as `$hen->current_age_weeks` (int). All 4 view call sites switch from `$hen->flock_age_weeks` to `$hen->current_age_weeks`.

- [ ] **Step 1: Replace `app/Models/Hen.php` entirely**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hen extends Model
{
    protected $fillable = [
        'cage_id', 'tag_code', 'date_acquired', 'flock_age_weeks',
        'placement_date', 'age_at_placement_weeks', 'breed', 'is_active',
    ];

    protected $casts = [
        'date_acquired'  => 'date',
        'placement_date' => 'date',
        'is_active'      => 'boolean',
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function getCurrentAgeWeeksAttribute(): int
    {
        if ($this->placement_date !== null) {
            $weeksElapsed = (int) floor($this->placement_date->diffInWeeks(now()));
            return (int) $this->age_at_placement_weeks + $weeksElapsed;
        }

        return $this->flock_age_weeks;
    }
}
```

Note: the old `getFlockAgeLabelAttribute()` accessor is removed — grep confirmed zero usages of `flockAgeLabel`/`flock_age_label` anywhere in the codebase, so it was dead code.

- [ ] **Step 2: Update `resources/views/dashboard.blade.php:81`**

Change:
```blade
<span>{{ $hen->flock_age_weeks }} wks</span>
```
To:
```blade
<span>{{ $hen->current_age_weeks }} wks</span>
```

- [ ] **Step 3: Update `resources/views/egg-logging.blade.php:30`**

Change:
```blade
data-age="{{ $cage->hens->first()?->flock_age_weeks ?? 0 }} weeks"
```
To:
```blade
data-age="{{ $cage->hens->first()?->current_age_weeks ?? 0 }} weeks"
```

- [ ] **Step 4: Update `resources/views/cages/index.blade.php:50`**

Change:
```blade
{{ $hen ? $hen->flock_age_weeks . ' weeks' : '—' }}
```
To:
```blade
{{ $hen ? $hen->current_age_weeks . ' weeks' : '—' }}
```

- [ ] **Step 5: Update `resources/views/analytics.blade.php:78`**

Change:
```blade
<div class="text-sm text-[#333333]">{{ $cage->hens->first() ? $cage->hens->first()->flock_age_weeks . ' wks' : '—' }}</div>
```
To:
```blade
<div class="text-sm text-[#333333]">{{ $cage->hens->first() ? $cage->hens->first()->current_age_weeks . ' wks' : '—' }}</div>
```

- [ ] **Step 6: Verify via tinker**

```php
$hen = \App\Models\Hen::first();
$hen->placement_date = now()->subWeeks(5);
$hen->age_at_placement_weeks = 2;
$hen->save();
$hen->refresh()->current_age_weeks; // expect 7
$hen->placement_date = null;
$hen->flock_age_weeks = 12;
$hen->save();
$hen->refresh()->current_age_weeks; // expect 12 (fallback)
```
Expected: first call returns `7`, second returns `12`.

- [ ] **Step 7: Confirm no remaining references to the old accessor**

Run: `grep -rn "flock_age_weeks" resources/ app/ --include="*.php" --include="*.blade.php"`
Expected: zero matches (all 4 view call sites updated; the migration/model field itself still exists as a column name, which is fine — only the accessor usages needed to change).

- [ ] **Step 8: Commit**

```bash
git add app/Models/Hen.php resources/views/dashboard.blade.php resources/views/egg-logging.blade.php resources/views/cages/index.blade.php resources/views/analytics.blade.php
git commit -m "Compute hen age from placement_date instead of a static stored value"
```

---

### Task 3: Dashboard — Real Sensor Badge & Remove Decorative Slot Grid

**Files:**
- Create: `resources/views/partials/cage-sensor-badge.blade.php`
- Modify: `resources/views/dashboard.blade.php:70-113`

**Interfaces:**
- Consumes: `cages.has_sensor` (from Task 1)
- Produces: `@include('partials.cage-sensor-badge', ['cage' => $cage])` — a reusable badge partial. Task 4 reuses this same partial in Cage Management.

**Context:** `dashboard.blade.php` currently renders a non-functional decorative slot grid (rows A/B/C × columns 1-10, lines 87-101) that isn't wired to any real data, plus a badge at lines 83-85 that shows `"2 sensor"` / `"No sensor"` driven by `$cage->is_active` — that's a pre-existing bug (sensor status was never a real field; the text was hardcoded against the wrong condition). This task removes both and replaces them with the real thing.

- [ ] **Step 1: Create the shared partial**

```blade
@php $hasSensor = $cage->has_sensor ?? false; @endphp
<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] {{ $hasSensor ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $hasSensor ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
    {{ $hasSensor ? 'Sensor' : 'No sensor' }}
</span>
```

Save as `resources/views/partials/cage-sensor-badge.blade.php`.

- [ ] **Step 2: Replace the fake badge and remove the decorative grid in `dashboard.blade.php`**

Find (around line 78-101):
```blade
                <div class="text-[11px] text-[#6B7280] mb-3 flex gap-3">
                    <span>{{ $cage->capacity }} hens</span>
                    @if($hen)
                    <span>{{ $hen->current_age_weeks }} wks</span>
                    @endif
                    <span class="px-1.5 py-0.5 rounded text-[10px] {{ $cage->is_active ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                        {{ $cage->is_active ? '2 sensor' : 'No sensor' }}
                    </span>
                </div>
                {{-- Slot grid --}}
                <div class="mb-3 overflow-x-auto">
                    <div class="min-w-[360px] space-y-1">
                        @foreach($rows as $row)
                        <div class="flex items-center gap-1">
                            <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ $row }}</div>
                            @foreach($cols as $col)
                            <div class="flex-1 text-center text-[10px] py-1.5 rounded bg-[#F5F6F8] text-[#6B7280] hover:bg-[#EAF0F8] cursor-pointer">
                                {{ $row }}{{ $col }}
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                </div>
```

Replace with:
```blade
                <div class="text-[11px] text-[#6B7280] mb-3 flex items-center gap-3">
                    <span>{{ $cage->capacity }} hens</span>
                    @if($hen)
                    <span>{{ $hen->current_age_weeks }} wks</span>
                    @endif
                    @include('partials.cage-sensor-badge', ['cage' => $cage])
                </div>
```

(Note: this also removes the now-unused `$rows`/`$cols` PHP variables a few lines above — they were only used by the deleted grid. Remove the lines `$rows = ['A','B','C'];` and `$cols = range(1,10);` from the `@php` block at the top of this `@foreach($cages as $cage)` loop.)

- [ ] **Step 3: Verify**

Run: `php artisan view:clear && php artisan view:cache`
Expected: no Blade compile errors (would surface immediately as a `ParseError`/`ErrorException` if the grid removal left a dangling `@endforeach` or similar).

Then in tinker:
```php
$cage = \App\Models\Cage::first();
$cage->has_sensor = true;
$cage->save();
```
Then visit `/` in the browser (logged in) and confirm the first cage card shows a green "Sensor" badge instead of the old grid, with no leftover blank space where the grid used to be.

- [ ] **Step 4: Commit**

```bash
git add resources/views/partials/cage-sensor-badge.blade.php resources/views/dashboard.blade.php
git commit -m "Replace fake sensor badge and decorative slot grid with real has_sensor indicator"
```

---

### Task 4: Cage Management — Sensor Toggle & Flock Capture

**Files:**
- Modify: `app/Http/Controllers/CageController.php`
- Modify: `resources/views/cages/index.blade.php`

**Interfaces:**
- Consumes: `cages.has_sensor`, `cages.sensor_device_id`, `hens.placement_date`, `hens.age_at_placement_weeks` (Task 1); `partials.cage-sensor-badge` (Task 3)
- Produces: `CageController::update()` now accepts `has_sensor`, `sensor_device_id`, `breed`, `age_at_placement_weeks`; `CageController::store()` now accepts `age_at_placement_weeks` and sets `placement_date` on the created `Hen`.

**Context:** `CageController::store()` already optionally creates one `Hen` record per cage (if a breed is given), but hardcodes `flock_age_weeks => 0` and has no way to update flock info after the cage exists. The existing Edit modal only edits `location`/`capacity`/`is_active`. This task extends both the create flow and the existing Edit modal to also capture flock placement data and toggle the sensor flag — no new routes needed, both reuse `cages.store` / `cages.update`.

- [ ] **Step 1: Update `CageController::store()`**

Replace:
```php
    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_code' => 'required|string|max:50|unique:cages',
            'location'  => 'nullable|string|max:100',
            'capacity'  => 'nullable|integer|min:1',
            'breed'     => 'nullable|string',
        ]);

        $cage = Cage::create([
            'cage_code' => strtoupper($data['cage_code']),
            'location'  => $data['location'] ?? '',
            'capacity'  => $data['capacity'] ?? 120,
            'is_active' => 1,
        ]);

        if (! empty($data['breed'])) {
            Hen::create([
                'cage_id'         => $cage->id,
                'flock_age_weeks' => 0,
                'breed'           => $data['breed'],
            ]);
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} added.");
    }
```

With:
```php
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

- [ ] **Step 2: Update `CageController::update()`**

Replace:
```php
    public function update(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'location' => 'nullable|string|max:100',
            'capacity' => 'nullable|integer|min:1',
            'is_active'=> 'nullable|boolean',
        ]);

        $cage->update($data);

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }
```

With:
```php
    public function update(Request $request, Cage $cage)
    {
        $data = $request->validate([
            'location'               => 'nullable|string|max:100',
            'capacity'               => 'nullable|integer|min:1',
            'is_active'              => 'nullable|boolean',
            'has_sensor'             => 'nullable|boolean',
            'sensor_device_id'       => 'nullable|string|max:100',
            'breed'                  => 'nullable|string',
            'age_at_placement_weeks' => 'nullable|integer|min:0',
        ]);

        $cage->update(array_intersect_key($data, array_flip(['location', 'capacity', 'is_active', 'has_sensor', 'sensor_device_id'])));

        if ($request->filled('breed')) {
            $hen = Hen::firstOrNew(['cage_id' => $cage->id, 'is_active' => 1]);
            if (! $hen->exists) {
                $hen->placement_date = now();
            }
            $hen->cage_id                 = $cage->id;
            $hen->is_active                = 1;
            $hen->breed                    = $data['breed'];
            $hen->age_at_placement_weeks   = $data['age_at_placement_weeks'] ?? 0;
            $hen->save();
        }

        return redirect()->route('cages.index')->with('success', "Cage {$cage->cage_code} updated.");
    }
```

Note: `array_intersect_key` is not a real PHP function — the correct name is `array_intersect_key`... actually PHP's real function is `array_intersect_key($array1, $array2)` which DOES exist and works exactly as needed here (keeps only keys from `$data` that also exist in the flipped allow-list array). Confirmed correct PHP builtin name, no typo.

- [ ] **Step 3: Add sensor toggle + flock fields to `resources/views/cages/index.blade.php`**

First, remove the decorative expandable detail row entirely. Find (around lines 41-95):
```blade
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8] cursor-pointer"
                    onclick="toggleCageDetail('cage-detail-{{ $cage->id }}')">
```
Replace with:
```blade
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
```

Find the closing `{{-- Expandable cage detail / slot grid --}}` block (lines 78-95):
```blade
                {{-- Expandable cage detail / slot grid --}}
                <tr id="cage-detail-{{ $cage->id }}" class="hidden bg-[#F9F9F7]">
                    <td colspan="8" class="px-5 py-4">
                        <div class="text-[11px] text-[#6B7280] mb-2">Click a row to view its cage layout below.</div>
                        <div class="space-y-1.5">
                            @foreach(['A','B','C'] as $row)
                            <div class="flex items-center gap-1">
                                <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ $row }}</div>
                                @for($col = 1; $col <= 10; $col++)
                                <div class="flex-1 text-center text-[10px] py-2 rounded bg-[#F5F6F8] text-[#6B7280] hover:bg-[#EAF0F8] cursor-pointer border border-[#E8E8E4]">
                                    {{ $row }}{{ $col }}
                                </div>
                                @endfor
                            </div>
                            @endforeach
                        </div>
                    </td>
                </tr>
```
Delete this block entirely (no replacement).

Also remove the now-stale footer line right after `</table>`:
```blade
        <p class="text-[11px] text-[#6B7280] px-5 py-3 border-t border-[#D9D9D9]">Click a row to view its cage layout below.</p>
```
Delete this line.

Next, add the sensor badge + toggle next to the Cage Code column. Find:
```blade
                    <td class="px-5 py-3.5">
                        <span class="text-sm font-medium" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                    </td>
```
Replace with:
```blade
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                            @include('partials.cage-sensor-badge', ['cage' => $cage])
                        </div>
                    </td>
```

Next, add a sensor-toggle button in the Actions cell. Find:
```blade
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2" onclick="event.stopPropagation()">
                            <button onclick="openEditModal({{ $cage->id }}, '{{ $cage->location }}', {{ $cage->capacity }}, {{ $cage->is_active ? 1 : 0 }})"
                                    class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                                <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                            </button>
                            <form method="POST" action="{{ route('cages.destroy', $cage) }}"
                                  onsubmit="return confirm('Delete {{ $cage->cage_code }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex items-center justify-center w-8 h-8 border border-red-200 text-red-400 rounded hover:bg-red-50">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
```
Replace with:
```blade
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <button onclick="openEditModal({{ $cage->id }}, '{{ $cage->location }}', {{ $cage->capacity }}, {{ $cage->is_active ? 1 : 0 }}, {{ $cage->has_sensor ? 1 : 0 }}, '{{ $hen?->breed ?? '' }}', {{ $hen?->age_at_placement_weeks ?? 0 }})"
                                    class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                                <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                            </button>
                            <form method="POST" action="{{ route('cages.destroy', $cage) }}"
                                  onsubmit="return confirm('Delete {{ $cage->cage_code }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex items-center justify-center w-8 h-8 border border-red-200 text-red-400 rounded hover:bg-red-50">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
```

Now extend the Edit modal to capture `has_sensor`, `breed`, `age_at_placement_weeks`. Find:
```blade
        <form id="editCageForm" method="POST">
            @csrf @method('PUT')
            <label class="block text-sm text-[#333333] mb-1.5">Location</label>
            <input id="editLocation" name="location"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Capacity (hens)</label>
            <input id="editCapacity" name="capacity" type="number"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="flex items-center gap-2 mb-5 cursor-pointer">
                <input id="editActive" name="is_active" type="checkbox" value="1" class="w-4 h-4">
                <span class="text-sm text-[#333333]">Active</span>
            </label>

            <div class="flex gap-3">
```
Replace with:
```blade
        <form id="editCageForm" method="POST">
            @csrf @method('PUT')
            <label class="block text-sm text-[#333333] mb-1.5">Location</label>
            <input id="editLocation" name="location"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Capacity (hens)</label>
            <input id="editCapacity" name="capacity" type="number"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Breed</label>
            <select id="editBreed" name="breed" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                <option value="">— Not set —</option>
                <option>ISA Brown</option>
                <option>Lohmann Brown-Classic</option>
                <option>Dekalb White</option>
                <option>Hy-Line Brown</option>
                <option>Novogen Brown</option>
            </select>

            <label class="block text-sm text-[#333333] mb-1.5">Age at Placement (weeks)</label>
            <input id="editAge" name="age_at_placement_weeks" type="number" min="0"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="flex items-center gap-2 mb-3 cursor-pointer">
                <input id="editActive" name="is_active" type="checkbox" value="1" class="w-4 h-4">
                <span class="text-sm text-[#333333]">Active</span>
            </label>

            <label class="flex items-center gap-2 mb-5 cursor-pointer">
                <input id="editHasSensor" name="has_sensor" type="checkbox" value="1" class="w-4 h-4">
                <span class="text-sm text-[#333333]">Sensor-equipped (IR egg counter installed)</span>
            </label>

            <div class="flex gap-3">
```

Finally, update the `openEditModal` JS function. Find:
```javascript
function openEditModal(id, location, capacity, isActive) {
    document.getElementById('editCageForm').action = '/cages/' + id;
    document.getElementById('editLocation').value  = location;
    document.getElementById('editCapacity').value  = capacity;
    document.getElementById('editActive').checked  = isActive === 1;
    document.getElementById('editCageModal').classList.remove('hidden');
}
```
Replace with:
```javascript
function openEditModal(id, location, capacity, isActive, hasSensor, breed, age) {
    document.getElementById('editCageForm').action = '/cages/' + id;
    document.getElementById('editLocation').value   = location;
    document.getElementById('editCapacity').value   = capacity;
    document.getElementById('editActive').checked    = isActive === 1;
    document.getElementById('editHasSensor').checked = hasSensor === 1;
    document.getElementById('editBreed').value       = breed;
    document.getElementById('editAge').value         = age;
    document.getElementById('editCageModal').classList.remove('hidden');
}
```

Also remove the now-unused `toggleCageDetail` JS function (its only caller was removed in this same step):
```javascript
function toggleCageDetail(id) {
    const el = document.getElementById(id);
    el.classList.toggle('hidden');
}
```
Delete this function.

- [ ] **Step 4: Verify**

Run: `php artisan route:list --name=cages` — expected: same 4 routes as before (`cages.index`, `cages.store`, `cages.update`, `cages.destroy`), no new routes needed.

Then in the browser: open Cage Management, click Edit on a cage, check "Sensor-equipped", set breed + age, Save. Confirm the page reloads with the green "Sensor" badge next to that cage's code, and re-opening Edit shows the breed/age you entered.

In tinker:
```php
$cage = \App\Models\Cage::where('cage_code', 'CAGE-A')->first();
$cage->hens->first()?->placement_date; // should be a Carbon instance, not null, if you just set a breed via the Edit modal
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CageController.php resources/views/cages/index.blade.php
git commit -m "Add sensor toggle and flock placement capture to Cage Management"
```

---

### Task 5: Forecasting Scope — Whole Farm / Per Cage

**Files:**
- Modify: `app/Http/Controllers/ForecastController.php`
- Modify: `resources/views/forecast.blade.php`

**Interfaces:**
- Consumes: `forecasts.cage_id` nullable (Task 1)
- Produces: `GET /forecast?scope=farm|cage` and `POST /forecast/generate` with a `scope` field. A `null` `Forecast.cage_id` row means a whole-farm forecast.

**Context:** `ForecastController` currently only supports single-cage forecasting (`cage` query param, default `CAGE-A`). Confirmed via grep that `forecast.blade.php` has no existing scope toggle — this is fully new, not modifying an existing 2-pill control.

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
        $horizon  = (int) $request->get('horizon', 7);
        $allCages = Cage::orderBy('cage_code')->get();

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::whereNull('cage_id')
                ->where('forecast_date', now()->toDateString())
                ->orderBy('target_date')
                ->limit($horizon)
                ->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast(null, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'allCages'))
                ->with('cage', null);
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = ProductionLog::where('cage_id', $cage->id)
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        $forecasts = Forecast::where('cage_id', $cage->id)
            ->where('forecast_date', now()->toDateString())
            ->orderBy('target_date')
            ->limit($horizon)
            ->get();

        if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
            $forecasts = $this->generateForecast($cage, $historical, $horizon);
        }

        return view('forecast', compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'allCages'));
    }

    public function generate(Request $request)
    {
        $scope    = $request->get('scope', 'cage');
        $cageCode = $request->get('cage', 'CAGE-A');
        $horizon  = (int) $request->get('horizon', 7);

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();

            Forecast::whereNull('cage_id')
                ->where('forecast_date', now()->toDateString())
                ->delete();

            $this->generateForecast(null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'farm', 'horizon' => $horizon])
                ->with('success', 'Whole-farm forecast generated.');
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = ProductionLog::where('cage_id', $cage->id)
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        Forecast::where('cage_id', $cage->id)
            ->where('forecast_date', now()->toDateString())
            ->delete();

        $this->generateForecast($cage, $historical, $horizon, true);

        return redirect()->route('forecast', ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon])
            ->with('success', 'Forecast generated.');
    }

    private function farmHistorical(): Collection
    {
        return ProductionLog::selectRaw('log_date, SUM(egg_count) as egg_count, SUM(hen_count) as hen_count')
            ->groupBy('log_date')
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->map(function ($row) {
                $row->hdep = $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0;
                return $row;
            })
            ->reverse()
            ->values();
    }

    private function generateForecast(?Cage $cage, Collection $historical, int $horizon, bool $save = false): Collection
    {
        $avgHdep = $historical->avg('hdep') ?? 85.0;
        $forecasts = collect();
        $today = now()->toDateString();

        for ($i = 1; $i <= $horizon; $i++) {
            $targetDate = now()->addDays($i)->toDateString();
            $variation  = (($i % 3) - 1) * 0.3;
            $predicted  = round(min(100, max(0, $avgHdep + $variation)), 2);

            $forecast = new Forecast([
                'cage_id'        => $cage?->id,
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

Note: `farmHistorical()` uses `ProductionLog::selectRaw(...)` — confirmed `ProductionLog`'s `$casts = ['log_date' => 'date', ...]` still applies when accessing `$row->log_date` even on a raw-selected aggregate row, since Eloquent casts are evaluated lazily at attribute-access time, not at hydration time. The `$row->hdep` set inside the `map()` is a plain ad-hoc attribute (no cast needed, it's already a float from `round()`).

- [ ] **Step 2: Add the scope toggle to `resources/views/forecast.blade.php`**

Find the top of the Blade file (after `@section('content')`):
```blade
@section('content')
<main class="p-5 space-y-5">

    <h1 class="text-xl font-medium text-[#333333]">Forecast</h1>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Inputs Panel ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-4">FORECAST INPUTS</div>
            <form method="POST" action="{{ route('forecast.generate') }}">
                @csrf
                <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                <select name="cage" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allCages as $c)
                    <option value="{{ $c->cage_code }}" {{ $c->cage_code === $cageCode ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>
```

Replace with:
```blade
@section('content')
<main class="p-5 space-y-5">

    @php
        $cageColor  = $scope === 'farm' ? '#102A4C' : match($cageCode){'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#2D7D46'};
        $scopeLabel = $scope === 'farm' ? 'Whole Farm' : $cageCode;
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
                       class="flex-1 text-center py-2 rounded-lg text-sm border {{ $scope === 'farm' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280]' }}">
                        Whole Farm
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'cage','cage'=>$cageCode,'horizon'=>$horizon]) }}"
                       class="flex-1 text-center py-2 rounded-lg text-sm border {{ $scope === 'cage' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280]' }}">
                        Per Cage
                    </a>
                </div>

                @if($scope === 'cage')
                <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                <select name="cage" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allCages as $c)
                    <option value="{{ $c->cage_code }}" {{ $c->cage_code === $cageCode ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>
                @else
                <input type="hidden" name="cage" value="{{ $cageCode }}">
                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#333333]">{{ $scopeLabel }}</span></p>
                @endif
```

Note the form's closing structure (horizon radios + submit button) is unchanged — only the block between `@csrf` and the existing `<label class="block text-sm text-[#333333] mb-2">Forecast horizon</label>` is being replaced as shown above.

- [ ] **Step 3: Update the chart JS to use the computed `$cageColor`**

Find:
```blade
const historical = @json($historical->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'hdep'=>$l->hdep]));
const forecasts  = @json($forecasts->map(fn($f) => ['date'=>$f->target_date->format('Y-m-d'),'hdep'=>$f->predicted_hdep]));
const cageColor  = '{{ match($cageCode){"CAGE-A"=>"#2D7D46","CAGE-B"=>"#1D4E8F","CAGE-C"=>"#C2703E","CAGE-D"=>"#6B4C8A",default=>"#2D7D46"} }}';
```
Replace with:
```blade
const historical = @json($historical->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'hdep'=>$l->hdep]));
const forecasts  = @json($forecasts->map(fn($f) => ['date'=>$f->target_date->format('Y-m-d'),'hdep'=>$f->predicted_hdep]));
const cageColor  = '{{ $cageColor }}';
```

- [ ] **Step 4: Verify**

Run: `php artisan view:cache` — expected: no Blade compile errors.

In tinker:
```php
$req = \Illuminate\Http\Request::create('/forecast?scope=farm&horizon=7', 'GET');
app()->instance('request', $req);
\Illuminate\Support\Facades\Auth::loginUsingId(1);
$html = app(\App\Http\Controllers\ForecastController::class)->index($req)->render();
echo str_contains($html, 'Whole Farm') ? 'OK: farm scope renders' : 'FAIL';
```
Expected: `OK: farm scope renders`.

In the browser: visit `/forecast`, click "Whole Farm", confirm the cage dropdown disappears and "Forecasting: Whole Farm" shows, click "Generate Forecast", confirm a forecast table appears. Click "Per Cage", confirm the cage dropdown reappears with the previous behavior intact.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ForecastController.php resources/views/forecast.blade.php
git commit -m "Add Whole Farm / Per Cage forecasting scope toggle"
```

---

### Task 6: Override PIN — Account Settings Page

**Files:**
- Create: `app/Http/Controllers/AccountController.php`
- Create: `resources/views/account.blade.php`
- Modify: `app/Models/User.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php` (wire the existing Settings sidebar link)

**Interfaces:**
- Consumes: `users.override_pin_hash` (Task 1)
- Produces: routes `account` (GET), `account.password` (POST), `account.pin` (POST). This is the only place `override_pin_hash` gets written. Task 7 only reads it (via `Hash::check`).

**Context:** No Account Settings page exists today — only login/logout. This task builds it from scratch.

- [ ] **Step 1: Update `app/Models/User.php`**

Find:
```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];
```
Replace with:
```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'override_pin_hash',
    ];
```

Find:
```php
    protected $hidden = [
        'password',
        'remember_token',
    ];
```
Replace with:
```php
    protected $hidden = [
        'password',
        'remember_token',
        'override_pin_hash',
    ];
```

- [ ] **Step 2: Create `app/Http/Controllers/AccountController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    private const WEAK_PINS = ['0000','1111','2222','3333','4444','5555','6666','7777','8888','9999','1234','4321','0123','1212'];

    public function show()
    {
        $staff = auth()->user()->isAdmin()
            ? User::orderBy('name')->get(['id', 'name', 'role', 'override_pin_hash'])
                ->map(fn ($u) => (object) [
                    'name'    => $u->name,
                    'role'    => $u->role,
                    'pin_set' => $u->override_pin_hash !== null,
                ])
            : null;

        return view('account', compact('staff'));
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        auth()->user()->update(['password' => Hash::make($data['password'])]);

        return redirect()->route('account')->with('success', 'Password updated.');
    }

    public function updatePin(Request $request)
    {
        $request->validate([
            'pin'               => 'required|digits_between:4,6|confirmed',
            'current_pin'       => 'nullable|string',
            'current_password'  => 'nullable|string',
        ]);

        $pin  = $request->input('pin');
        $user = auth()->user();

        if (in_array($pin, self::WEAK_PINS, true)) {
            return back()->withErrors(['pin' => 'This PIN is too easy to guess. Choose a different one.']);
        }

        if ($user->override_pin_hash !== null) {
            $verifiedByPin      = $request->filled('current_pin') && Hash::check($request->input('current_pin'), $user->override_pin_hash);
            $verifiedByPassword = $request->filled('current_password') && Hash::check($request->input('current_password'), $user->password);

            if (! $verifiedByPin && ! $verifiedByPassword) {
                return back()->withErrors(['current_pin' => 'Current PIN (or account password) is incorrect.']);
            }
        }

        $user->update(['override_pin_hash' => Hash::make($pin)]);

        return redirect()->route('account')->with('success', 'Override PIN saved.');
    }
}
```

- [ ] **Step 3: Add routes to `routes/web.php`**

Add `use App\Http\Controllers\AccountController;` to the top `use` block.

Inside the existing `Route::middleware('auth')->group(function () { ... })`, add (next to the `forecast` routes, for example):
```php
    Route::get('/account',           [AccountController::class, 'show'])->name('account');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/pin',      [AccountController::class, 'updatePin'])->name('account.pin');
```

- [ ] **Step 4: Create `resources/views/account.blade.php`**

```blade
@extends('layouts.app')
@section('title', 'Account Settings')

@section('content')
<main class="p-5 space-y-5 max-w-2xl">

    <h1 class="text-xl font-medium text-[#333333]">Account Settings</h1>

    {{-- Change Password --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <h2 class="text-base font-medium text-[#333333] mb-4">Change Password</h2>
        <form method="POST" action="{{ route('account.password') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Current Password</label>
                <input type="password" name="current_password" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('current_password')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">New Password</label>
                <input type="password" name="password" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('password')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Confirm New Password</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
            </div>
            <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Update Password</button>
        </form>
    </div>

    {{-- Override PIN --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <h2 class="text-base font-medium text-[#333333] mb-1">{{ auth()->user()->override_pin_hash ? 'Change Override PIN' : 'Set Override PIN' }}</h2>
        <p class="text-xs text-[#6B7280] mb-4">Used to manually override a sensor-locked egg count in Egg Logging.</p>
        <form method="POST" action="{{ route('account.pin') }}" class="space-y-4">
            @csrf
            @if(auth()->user()->override_pin_hash)
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Current PIN</label>
                <input type="text" name="current_pin" inputmode="numeric" maxlength="6"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]"
                       placeholder="Leave blank to verify with password instead">
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Or Current Password</label>
                <input type="password" name="current_password"
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('current_pin')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">New PIN (4-6 digits)</label>
                <input type="text" name="pin" inputmode="numeric" maxlength="6" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
                @error('pin')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-[#333333] mb-1.5">Confirm New PIN</label>
                <input type="text" name="pin_confirmation" inputmode="numeric" maxlength="6" required
                       class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#002D5E]">
            </div>
            <button type="submit" class="bg-[#002D5E] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Save PIN</button>
        </form>
    </div>

    {{-- Admin: staff PIN status --}}
    @if($staff)
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-6">
        <h2 class="text-base font-medium text-[#333333] mb-4">Staff Override PIN Status</h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#D9D9D9]">
                    <th class="text-left text-xs text-[#6B7280] py-2 font-medium">Name</th>
                    <th class="text-left text-xs text-[#6B7280] py-2 font-medium">Role</th>
                    <th class="text-left text-xs text-[#6B7280] py-2 font-medium">PIN Set?</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staff as $member)
                <tr class="border-b border-[#D9D9D9]">
                    <td class="py-2">{{ $member->name }}</td>
                    <td class="py-2 capitalize">{{ $member->role }}</td>
                    <td class="py-2">
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $member->pin_set ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                            {{ $member->pin_set ? 'Set' : 'Not set' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</main>
@endsection
```

- [ ] **Step 5: Wire the sidebar Settings link in `resources/views/layouts/app.blade.php`**

Find:
```blade
        <a href="#" class="group flex items-center gap-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors w-10 h-10 justify-center mx-auto" title="Settings">
            <i data-lucide="settings" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden hidden">Settings</span>
        </a>
```
Replace with:
```blade
        <a href="{{ route('account') }}" class="group flex items-center gap-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors w-10 h-10 justify-center mx-auto" title="Settings">
            <i data-lucide="settings" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden hidden">Settings</span>
        </a>
```

- [ ] **Step 6: Verify**

Run: `php artisan route:list --name=account` — expected 3 routes listed (`account`, `account.password`, `account.pin`).

In tinker:
```php
$user = \App\Models\User::first();
$user->update(['override_pin_hash' => \Illuminate\Support\Facades\Hash::make('9173')]);
\Illuminate\Support\Facades\Hash::check('9173', $user->refresh()->override_pin_hash); // expect true
\Illuminate\Support\Facades\Hash::check('1234', $user->override_pin_hash); // expect false
```

In the browser: click the Settings icon in the sidebar, confirm it opens `/account` (not a dead `#` link), set a PIN with value `0000`, confirm it's rejected with "too easy to guess", set a real PIN like `7421`, confirm success, log in as the admin user and confirm the "Staff Override PIN Status" table appears showing your own row as "Set".

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AccountController.php resources/views/account.blade.php app/Models/User.php routes/web.php resources/views/layouts/app.blade.php
git commit -m "Add Account Settings page with password change and Override PIN management"
```

---

### Task 7: Egg Logging — Sensor-Locked Override

**Files:**
- Modify: `app/Http/Controllers/EggLoggingController.php`
- Modify: `app/Models/ProductionLog.php`
- Modify: `resources/views/egg-logging.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `cages.has_sensor` (Task 1), `users.override_pin_hash` (Task 6)
- Produces: route `egg-logging.verify-override` (POST, JSON). Server-side enforcement: if a cage has `has_sensor = true` and the submitted `egg_count` differs from what's already stored for that date, the save is rejected unless a recent (within 10 minutes) successful override verification exists in the session for that cage.

**Context:** This task does NOT include the Arduino → Raspberry Pi → `production_logs` ingestion pipeline — that's a separate hardware-integration task. This only governs what happens to a value once it's in the database: whether the field is locked for editing, and how an override gets authorized and recorded.

- [ ] **Step 1: Add the `overriddenBy` relation to `app/Models/ProductionLog.php`**

Find:
```php
    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
```
Replace with:
```php
    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_user_id');
    }
```

Add `use App\Models\User;` is not needed since `User::class` resolves via global namespace fully-qualified reference already used by the existing `recorder()` method in the same way — no new import required (the existing file already references `User::class` without a `use` import, relying on it being in the same `App\Models` namespace).

- [ ] **Step 2: Replace `app/Http/Controllers/EggLoggingController.php` entirely**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EggLoggingController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $cages = Cage::with([
            'latestProduction',
            'hens' => fn($q) => $q->where('is_active', 1),
        ])->where('is_active', 1)->orderBy('cage_code')->get()
          ->map(function ($cage) use ($today) {
              $cage->today_egg_count = ProductionLog::where('cage_id', $cage->id)
                  ->where('log_date', $today)
                  ->value('egg_count') ?? 0;
              return $cage;
          });

        $logs = ProductionLog::with(['cage', 'overriddenBy'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('egg-logging', compact('cages', 'logs'));
    }

    public function verifyOverride(Request $request)
    {
        $data = $request->validate([
            'cage_id'  => 'required|exists:cages,id',
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

        session()->put("override_verified.{$data['cage_id']}", now()->timestamp);

        return response()->json([
            'ok'              => true,
            'needs_pin_setup' => $user->override_pin_hash === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'log_date'  => 'required|date',
            'cage_id'   => 'required|exists:cages,id',
            'egg_count' => 'required|integer|min:0',
            'hen_count' => 'required|integer|min:1',
            'notes'     => 'nullable|string',
        ]);

        $cage = Cage::find($data['cage_id']);
        $existing = ProductionLog::where('cage_id', $data['cage_id'])
            ->where('log_date', $data['log_date'])
            ->first();

        $payload = [
            'egg_count'   => $data['egg_count'],
            'hen_count'   => $data['hen_count'],
            'hdep'        => round(($data['egg_count'] / $data['hen_count']) * 100, 2),
            'recorded_by' => auth()->id(),
            'notes'       => $data['notes'] ?? 'Manual entry',
        ];

        if ($cage->has_sensor) {
            $valueChanged = ! $existing || (int) $existing->egg_count !== (int) $data['egg_count'];
            $verifiedAt   = session("override_verified.{$data['cage_id']}");
            $stillValid   = $verifiedAt && (now()->timestamp - $verifiedAt) <= 600;

            if ($valueChanged && ! $stillValid) {
                return back()->withInput()->withErrors([
                    'egg_count' => "This cage's reading is sensor-locked. Use the override option to change it.",
                ]);
            }

            if ($valueChanged) {
                $payload['overridden_by_user_id'] = auth()->id();
                $payload['overridden_at']          = now();
                session()->forget("override_verified.{$data['cage_id']}");
            }
        }

        ProductionLog::updateOrCreate(
            ['cage_id' => $data['cage_id'], 'log_date' => $data['log_date']],
            $payload
        );

        return redirect()->route('egg-logging')->with('success', 'Production log saved.');
    }

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();
        return redirect()->route('egg-logging')->with('success', 'Log deleted.');
    }
}
```

Note: `recorded_by` now uses `auth()->id()` instead of the previously hardcoded `1` — fixed in the same line being touched for the override feature.

- [ ] **Step 3: Add the verify-override route to `routes/web.php`**

Find:
```php
    Route::get('/egg-logging',                        [EggLoggingController::class, 'index'])->name('egg-logging');
    Route::post('/egg-logging',                       [EggLoggingController::class, 'store'])->name('egg-logging.store');
    Route::delete('/egg-logging/{productionLog}',     [EggLoggingController::class, 'destroy'])->name('egg-logging.destroy')->middleware('admin');
```
Replace with:
```php
    Route::get('/egg-logging',                        [EggLoggingController::class, 'index'])->name('egg-logging');
    Route::post('/egg-logging',                       [EggLoggingController::class, 'store'])->name('egg-logging.store');
    Route::post('/egg-logging/verify-override',       [EggLoggingController::class, 'verifyOverride'])->name('egg-logging.verify-override');
    Route::delete('/egg-logging/{productionLog}',     [EggLoggingController::class, 'destroy'])->name('egg-logging.destroy')->middleware('admin');
```

- [ ] **Step 4: Update `resources/views/egg-logging.blade.php`**

Find the cage `<select>` options (the `@foreach($cages as $cage)` block):
```blade
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}"
                                data-hens="{{ $cage->capacity }}"
                                data-hdep="{{ number_format($cage->latestProduction?->hdep ?? 0, 1) }}"
                                data-age="{{ $cage->hens->first()?->current_age_weeks ?? 0 }} weeks"
                                data-breed="{{ $cage->hens->first()?->breed ?? '—' }}">
                            {{ $cage->cage_code }} — {{ $cage->hens->first()?->breed ?? '—' }}
                        </option>
                        @endforeach
```
Replace with:
```blade
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}"
                                data-hens="{{ $cage->capacity }}"
                                data-hdep="{{ number_format($cage->latestProduction?->hdep ?? 0, 1) }}"
                                data-age="{{ $cage->hens->first()?->current_age_weeks ?? 0 }} weeks"
                                data-breed="{{ $cage->hens->first()?->breed ?? '—' }}"
                                data-has-sensor="{{ $cage->has_sensor ? 1 : 0 }}"
                                data-today-egg-count="{{ $cage->today_egg_count }}">
                            {{ $cage->cage_code }} — {{ $cage->hens->first()?->breed ?? '—' }}
                        </option>
                        @endforeach
```

Find the Egg Count field:
```blade
                {{-- Egg Count --}}
                <div class="mb-4">
                    <label class="block text-sm text-[#333333] mb-1.5">Egg Count (IR sensor auto-count or manual entry)</label>
                    <input type="number" name="egg_count" id="eggCount" min="0" required
                           oninput="computeHdep()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
```
Replace with:
```blade
                {{-- Egg Count --}}
                <div class="mb-4">
                    <label class="block text-sm text-[#333333] mb-1.5">Egg Count (IR sensor auto-count or manual entry)</label>
                    <input type="number" name="egg_count" id="eggCount" min="0" required
                           oninput="computeHdep()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                    <button type="button" id="overrideLabel" onclick="openOverrideModal()"
                            class="hidden mt-1.5 text-xs text-amber-700 flex items-center gap-1">
                        🔒 Sensor reading — click to override
                    </button>
                </div>
```

Find the recent logs table header row:
```blade
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Notes</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Actions</th>
```
Replace with:
```blade
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Notes</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Override</th>
                        <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Actions</th>
```

Find the recent logs table row's Notes cell:
```blade
                        <td class="px-6 py-3 text-sm text-[#6B7280] max-w-[200px] truncate">{{ $log->notes ?? '—' }}</td>
```
Replace with:
```blade
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
```

Find the `@forelse` empty-state colspan (it currently spans 8 columns; one new column was added, making it 9):
```blade
                    <tr><td colspan="8" class="px-6 py-8 text-center text-sm text-[#6B7280]">No logs yet. Save the first record above.</td></tr>
```
Replace with:
```blade
                    <tr><td colspan="9" class="px-6 py-8 text-center text-sm text-[#6B7280]">No logs yet. Save the first record above.</td></tr>
```

Add the override PIN modal right before `</main>`:
```blade
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
```

Finally, update the `@push('scripts')` block. Find:
```blade
@push('scripts')
<script>
lucide.createIcons();

// Populate cage info bar on load
window.addEventListener('DOMContentLoaded', () => updateCageInfo(document.getElementById('cageSelect')));

function updateCageInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('cageAge').textContent  = opt.dataset.age  || '—';
    document.getElementById('cageHdep').textContent = (opt.dataset.hdep || '—') + '%';
    document.getElementById('cageHens').textContent = opt.dataset.hens || '—';
    document.getElementById('henCount').value = opt.dataset.hens || 120;
    computeHdep();
}

function computeHdep() {
    const eggs = parseInt(document.getElementById('eggCount').value) || 0;
    const hens = parseInt(document.getElementById('henCount').value) || 1;
    const hdep = ((eggs / hens) * 100).toFixed(1);
    const el   = document.getElementById('hdepDisplay');
    el.textContent = 'HDEP:  ' + hdep + '%';
    el.className = 'mt-2 inline-block border rounded-lg px-4 py-2 text-sm font-mono '
        + (eggs > hens ? 'bg-red-50 border-red-200 text-red-700' : 'bg-[#F5F6F8] border-[#D9D9D9] text-[#333333]');
}
</script>
@endpush
```
Replace with:
```blade
@push('scripts')
<script>
lucide.createIcons();

let currentSensorCageId = null;

window.addEventListener('DOMContentLoaded', () => updateCageInfo(document.getElementById('cageSelect')));

function updateCageInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('cageAge').textContent  = opt.dataset.age  || '—';
    document.getElementById('cageHdep').textContent = (opt.dataset.hdep || '—') + '%';
    document.getElementById('cageHens').textContent = opt.dataset.hens || '—';
    document.getElementById('henCount').value = opt.dataset.hens || 120;

    const eggInput      = document.getElementById('eggCount');
    const overrideLabel = document.getElementById('overrideLabel');
    const hasSensor      = opt.dataset.hasSensor === '1';

    if (hasSensor) {
        eggInput.value    = opt.dataset.todayEggCount || 0;
        eggInput.readOnly = true;
        overrideLabel.classList.remove('hidden');
        currentSensorCageId = opt.value;
    } else {
        eggInput.readOnly = false;
        overrideLabel.classList.add('hidden');
        currentSensorCageId = null;
    }

    computeHdep();
}

function computeHdep() {
    const eggs = parseInt(document.getElementById('eggCount').value) || 0;
    const hens = parseInt(document.getElementById('henCount').value) || 1;
    const hdep = ((eggs / hens) * 100).toFixed(1);
    const el   = document.getElementById('hdepDisplay');
    el.textContent = 'HDEP:  ' + hdep + '%';
    el.className = 'mt-2 inline-block border rounded-lg px-4 py-2 text-sm font-mono '
        + (eggs > hens ? 'bg-red-50 border-red-200 text-red-700' : 'bg-[#F5F6F8] border-[#D9D9D9] text-[#333333]');
}

function openOverrideModal() {
    document.getElementById('overrideError').classList.add('hidden');
    document.getElementById('overridePinInput').value = '';
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
        body: JSON.stringify({ cage_id: currentSensorCageId, pin: pin, password: password }),
    })
    .then(r => r.json().then(body => ({ status: r.status, body })))
    .then(({ status, body }) => {
        if (status === 200 && body.ok) {
            document.getElementById('eggCount').readOnly = false;
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

- [ ] **Step 5: Verify**

Run: `php artisan route:list --name=egg-logging` — expected 4 routes now (`egg-logging`, `egg-logging.store`, `egg-logging.verify-override`, `egg-logging.destroy`).

In tinker, simulate the full override flow:
```php
$cage = \App\Models\Cage::where('cage_code', 'CAGE-A')->first();
$cage->update(['has_sensor' => true]);
$user = \App\Models\User::first();
$user->update(['override_pin_hash' => \Illuminate\Support\Facades\Hash::make('5566')]);

\Illuminate\Support\Facades\Auth::loginUsingId($user->id);
session()->put("override_verified.{$cage->id}", now()->timestamp);

$req = \Illuminate\Http\Request::create('/egg-logging', 'POST', [
    'log_date' => now()->toDateString(),
    'cage_id'  => $cage->id,
    'egg_count'=> 77,
    'hen_count'=> 120,
]);
$req->setLaravelSession(session());
app()->instance('request', $req);
app(\App\Http\Controllers\EggLoggingController::class)->store($req);

$log = \App\Models\ProductionLog::where('cage_id', $cage->id)->where('log_date', now()->toDateString())->first();
echo $log->egg_count . ' / overridden_by_user_id=' . $log->overridden_by_user_id;
```
Expected: `77 / overridden_by_user_id=1` (or whatever the first user's ID is) — confirms the override was recorded when the session flag was present.

Then in the browser: mark a cage as sensor-equipped (via Task 4's Edit modal), visit Egg Logging, select that cage, confirm the Egg Count field is read-only with the 🔒 label visible, click it, enter a wrong PIN (expect inline error), enter the right PIN, confirm the field unlocks, change the value, Save Record, confirm the recent logs table shows "Manually overridden by {your name}" on that row.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EggLoggingController.php app/Models/ProductionLog.php resources/views/egg-logging.blade.php routes/web.php
git commit -m "Add PIN-gated sensor override to Egg Logging"
```

---

## Self-Review

**Spec coverage:** All 5 spec sections map to tasks — forecasting scope → Task 5; egg logging sensor override → Task 7; flock age fix (bundled in spec section 2) → Task 2; Add/Update Flock → Task 4; sensor flagging shared component → Tasks 3+4; Override PIN/Account Settings → Task 6. No spec requirement is missing a task.

**Placeholder scan:** No "TBD"/"TODO" strings, no "add validation" hand-waves — every step shows the actual code/Blade diff. Verification steps use real tinker commands with stated expected output, not "write tests for the above."

**Type consistency:** `current_age_weeks` (Task 2) is used identically in Tasks 3, 4 view edits. `has_sensor` (Task 1) flows through unchanged as boolean from migration → `CageController` validation (Task 4) → `cage-sensor-badge` partial (Task 3) → `egg-logging.blade.php` data attribute (Task 7). `overridden_by_user_id`/`overridden_at` (Task 1) match exactly between the migration and `EggLoggingController::store()` (Task 7) and the `overriddenBy()` relation (Task 7, Step 1). Route name `egg-logging.verify-override` matches between the route definition (Step 3) and the JS `fetch()` call (Step 4) and the tinker verification (Step 5).

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-28-cage-level-feature-set.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
