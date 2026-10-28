<?php

namespace Database\Seeders;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * DEMONSTRATION ONLY. Creates individual mock hens only in isolated QA/local
 * environments; never use these records as historical flock snapshots.
 */
class DemoHenInventorySeeder extends Seeder
{
    private const TAG_PREFIX = 'DEMO-HEN-';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoHenInventorySeeder is forbidden in production.');
        }
        $database = DB::connection()->getDatabaseName();
        if ($database !== 'layrate_qa_20261027' && ! str_ends_with((string) $database, '_demo')) {
            throw new RuntimeException('DemoHenInventorySeeder requires layrate_qa_20261027 or an explicitly suffixed *_demo database.');
        }
        if (! Schema::hasColumn('hens', 'source') || ! Schema::hasColumn('hens', 'notes')) {
            throw new RuntimeException('Demo hen provenance columns are required.');
        }

        DB::transaction(function (): void {
            $layouts = ['CAGE-A' => 60, 'CAGE-B' => 36, 'CAGE-C' => 36];
            foreach ($layouts as $code => $slotCount) {
                $cage = Cage::where('cage_code', $code)->where('is_active', true)->first();
                if (! $cage || $cage->cageSlots()->count() !== $slotCount) {
                    throw new RuntimeException("Expected active {$code} with {$slotCount} existing slots; no changes made.");
                }
                if (Hen::whereHas('cageSlot.cage', fn ($q) => $q->where('cage_code', $code))
                    ->where('is_active', true)->where('tag_code', 'not like', self::TAG_PREFIX.'%')->exists()) {
                    throw new RuntimeException("{$code} contains live inventory; refusing to mix demo hens into a populated cage.");
                }
            }

            foreach ($layouts as $code => $slotCount) {
                $cage = Cage::where('cage_code', $code)->firstOrFail();
                $slots = CageSlot::where('cage_id', $cage->id)->orderBy('slot_number')->get();
                foreach ($slots as $slot) {
                    $existingDemoCount = Hen::where('cage_slot_id', $slot->id)
                        ->where('tag_code', 'like', self::TAG_PREFIX.$code.'-S'.str_pad((string) $slot->slot_number, 3, '0', STR_PAD_LEFT).'-%')->count();
                    $otherActiveCount = Hen::where('cage_slot_id', $slot->id)
                        ->where('is_active', true)->where('tag_code', 'not like', self::TAG_PREFIX.'%')->count();
                    $capacity = (int) $cage->max_chickens_per_slot;
                    $demoLimit = max(0, $capacity - $otherActiveCount);
                    for ($number = $existingDemoCount + 1; $number <= $demoLimit; $number++) {
                        $hen = Hen::firstOrCreate(
                            ['tag_code' => self::TAG_PREFIX.$code.'-S'.str_pad((string) $slot->slot_number, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)],
                            [
                                'breed' => in_array($cage->breed_snapshot, ['ISA Brown', 'Lohmann Brown-Classic', 'Dekalb White', 'Hy-Line Brown', 'Novogen Brown'], true)
                                    ? $cage->breed_snapshot
                                    : 'ISA Brown',
                                'flock_age_weeks' => 30,
                                'date_acquired' => '2026-01-01',
                                'placement_date' => '2026-01-01',
                                'age_at_placement_weeks' => 0,
                                'source' => 'DEMO ONLY — DemoHenInventorySeeder',
                                'notes' => 'DEMONSTRATION ONLY — not an individual historical farm record.',
                                'is_active' => true,
                            ]
                        );
                        $hen->cage_slot_id = $slot->id;
                        $hen->save();
                    }

                    $activeCount = Hen::where('cage_slot_id', $slot->id)->where('is_active', true)->count();
                    $slot->update(['current_occupancy' => $activeCount]);
                }
            }
        });

        $this->command?->info('Demonstration hen inventory seeded idempotently in A/B/C slots.');
    }
}
