<?php

namespace App\Console\Commands;

use App\Models\CageTransfer;
use App\Models\Hen;
use Illuminate\Console\Command;

class BackfillCageTransfers extends Command
{
    protected $signature = 'layrate:backfill-cage-transfers
        {--dry-run : Count records that would be created without inserting}';

    protected $description = 'Create missing CageTransfer records for existing placed hens (from_cage_slot_id = null → to_cage_slot_id = their current slot)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $hens = Hen::whereNotNull('cage_slot_id')
            ->whereDoesntHave('cageTransfers', fn ($q) => $q->whereNull('from_cage_slot_id'))
            ->get();

        if ($hens->isEmpty()) {
            $this->info('No hens need backfill.');
            return self::SUCCESS;
        }

        $this->line("Found {$hens->count()} hen(s) without a placement CageTransfer.");

        $missingSlot = $hens->filter(fn ($h) => is_null($h->cage_slot_id));
        if ($missingSlot->isNotEmpty()) {
            $this->warn("  WARNING: {$missingSlot->count()} hen(s) have cage_slot_id = null but are in this query. These will be skipped.");
        }

        $conflicts = $hens->filter(fn ($h) => $h->cageTransfers()->whereNull('from_cage_slot_id')->exists());
        if ($conflicts->isNotEmpty()) {
            $this->warn("  NOTE: {$conflicts->count()} hen(s) already have a placement transfer — filtered out by query.");
        }

        $valid = $hens->filter(fn ($h) => ! is_null($h->cage_slot_id));

        if ($valid->isEmpty()) {
            $this->info('No valid hens to backfill.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['Hen ID', 'Chicken ID', 'Cage Slot ID', 'Placement Date'],
                $valid->map(fn ($h) => [$h->id, $h->chicken_id, $h->cage_slot_id, $h->placement_date?->toDateString()])
            );
            $this->info("[DRY RUN] Would create {$valid->count()} CageTransfer record(s).");
            return self::SUCCESS;
        }

        $now = now();
        $created = 0;

        foreach ($valid as $hen) {
            CageTransfer::create([
                'hen_id'           => $hen->id,
                'from_cage_slot_id' => null,
                'to_cage_slot_id'   => $hen->cage_slot_id,
                'transfer_date'    => $hen->placement_date ?? $hen->created_at->toDateString(),
                'reason'           => 'Initial placement (backfill)',
                'recorded_by'      => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $created++;
        }

        $this->info("Created {$created} CageTransfer record(s).");

        return self::SUCCESS;
    }
}
