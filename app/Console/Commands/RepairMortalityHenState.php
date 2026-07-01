<?php

namespace App\Console\Commands;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\MortalityLogHen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairMortalityHenState extends Command
{
    protected $signature = 'mortality:repair-hen-state {--apply : Actually write changes to the database}';

    protected $description = 'Backfill mortality_log_hens pivot rows for existing mortality logs that have no linked hen records';

    public function handle()
    {
        $apply = $this->option('apply');

        if (!$apply) {
            $this->warn('⚠ DRY RUN MODE — no changes will be written. Use --apply to execute.');
            $this->newLine();
        }

        $orphanedLogs = MortalityLog::doesntHave('hens')
            ->orderBy('log_date')
            ->orderBy('id')
            ->get();

        if ($orphanedLogs->isEmpty()) {
            $this->info('✓ All mortality logs already have linked hen records. Nothing to repair.');
            return Command::SUCCESS;
        }

        $this->info("Found {$orphanedLogs->count()} mortality log(s) without linked hen records.");
        $this->newLine();

        $totalDeactivated = 0;
        $tableRows = [];

        foreach ($orphanedLogs as $log) {
            $cage = $log->cage;
            $cageCode = $cage ? $cage->cage_code : 'UNKNOWN';

            $activeHensBefore = Hen::whereHas('cageSlot', fn($q) => $q->where('cage_id', $log->cage_id))
                ->where('is_active', 1)
                ->count();

            $inactiveHensBefore = Hen::whereHas('cageSlot', fn($q) => $q->where('cage_id', $log->cage_id))
                ->where('is_active', 0)
                ->count();

            $shortfall = max(0, $log->count - $inactiveHensBefore);

            $hensDeactivated = 0;
            $slotDecrements = [];
            $deactivatedHenIds = [];

            if ($shortfall > 0) {
                $candidates = Hen::whereHas('cageSlot', fn($q) => $q->where('cage_id', $log->cage_id))
                    ->where('is_active', 1)
                    ->orderBy('cage_slot_id')
                    ->orderBy('placement_date')
                    ->orderBy('id')
                    ->limit($shortfall)
                    ->get();

                if ($candidates->count() < $shortfall) {
                    $this->error("  Log #{$log->id}: Only {$candidates->count()} active hens available, need {$shortfall}. Skipping.");
                    continue;
                }

                if ($apply) {
                    DB::transaction(function () use ($log, $candidates, &$slotDecrements, &$deactivatedHenIds) {
                        foreach ($candidates as $hen) {
                            $hen->update(['is_active' => false]);

                            MortalityLogHen::create([
                                'mortality_log_id' => $log->id,
                                'hen_id'           => $hen->id,
                                'cage_slot_id'     => $hen->cage_slot_id,
                            ]);

                            $deactivatedHenIds[] = $hen->id;
                            $slotDecrements[$hen->cage_slot_id] = ($slotDecrements[$hen->cage_slot_id] ?? 0) + 1;
                        }

                        foreach ($slotDecrements as $slotId => $decrement) {
                            CageSlot::where('id', $slotId)->decrement('current_occupancy', $decrement);
                        }
                    });
                } else {
                    $deactivatedHenIds = $candidates->pluck('id')->toArray();
                }

                $hensDeactivated = $candidates->count();
                $totalDeactivated += $hensDeactivated;
            }

            $activeHensAfter = $activeHensBefore - $hensDeactivated;

            $tableRows[] = [
                $log->id,
                $cageCode,
                $log->log_date->format('Y-m-d'),
                $log->count,
                $inactiveHensBefore,
                $hensDeactivated,
                $activeHensBefore,
                $activeHensAfter,
                $hensDeactivated > 0 ? implode(', ', $deactivatedHenIds) : '—',
            ];
        }

        if (empty($tableRows)) {
            $this->info('✓ All orphaned logs have enough inactive hens already. No action needed.');
            return Command::SUCCESS;
        }

        $this->table(
            ['Log ID', 'Cage', 'Date', 'Deaths', 'Inactive Before', 'Deactivated', 'Active Before', 'Active After', 'Hen IDs'],
            $tableRows
        );

        $this->newLine();
        $this->info("Total hens to deactivate: {$totalDeactivated}");

        if (!$apply) {
            $this->newLine();
            $this->warn('This was a dry run. Run with --apply to execute these changes.');
        } else {
            $this->newLine();
            $this->info("✓ Applied. {$totalDeactivated} hen(s) deactivated across {$orphanedLogs->count()} log(s).");
        }

        return Command::SUCCESS;
    }
}
