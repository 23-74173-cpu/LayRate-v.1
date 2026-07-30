<?php

namespace App\Console\Commands;

use App\Models\CageSlot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileSlotOccupancy extends Command
{
    protected $signature = 'layrate:reconcile-occupancy
        {--apply : Actually write corrections to the database}';

    protected $description = 'Reconcile cage_slots.current_occupancy against the actual count of active hens per slot. Reports all discrepancies without --apply; corrects them with --apply.';

    public function handle(): int
    {
        $apply = $this->option('apply');

        if (!$apply) {
            $this->warn('DRY RUN — no changes will be written. Use --apply to execute.');
            $this->newLine();
        }

        $this->line('Reconciling slot occupancy...');
        $this->line('');

        $slots = CageSlot::withCount(['hens' => fn($q) => $q->where('is_active', 1)])
            ->orderBy('cage_id')
            ->orderBy('slot_number')
            ->get();

        $fixed = 0;
        $totalDiscrepancy = 0;

        foreach ($slots as $slot) {
            $actual = (int) $slot->hens_count;
            $stored = (int) $slot->current_occupancy;

            if ($actual !== $stored) {
                $diff = $actual - $stored;
                $cageCode = $slot->cage?->cage_code ?? '?';
                $this->line(sprintf(
                    '  Slot #%d (Cage %s, R%d-C%d): stored=%d, actual=%d (%s%d)',
                    $slot->slot_number,
                    $cageCode,
                    $slot->row_number,
                    $slot->column_number,
                    $stored,
                    $actual,
                    $diff >= 0 ? '+' : '',
                    $diff
                ));
                $totalDiscrepancy += abs($diff);
                $fixed++;

                if ($apply) {
                    DB::table('cage_slots')
                        ->where('id', $slot->id)
                        ->update(['current_occupancy' => $actual]);
                }
            }
        }

        $this->newLine();

        if ($fixed === 0) {
            $this->info('All slot occupancy values match active hen counts. No discrepancies found.');
            return self::SUCCESS;
        }

        $verb = $apply ? 'Fixed' : 'Found';
        $this->warn(sprintf('%s %d slot(s) with occupancy discrepancies (total drift: %d).', $verb, $fixed, $totalDiscrepancy));

        if (!$apply) {
            $this->newLine();
            $this->warn('Run with --apply to write corrections.');
        }

        return self::SUCCESS;
    }
}
