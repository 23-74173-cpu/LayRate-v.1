<?php

namespace App\Console\Commands;

use App\Models\Cage;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\MortalityLogHen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverMortalityLogs extends Command
{
    protected $signature = 'mortality:recover-logs
        {--dry-run : Show what would be recovered without writing changes}
        {--cage= : Only recover hens in a specific cage (cage_id)}';

    protected $description = 'Find hens with stale deactivation_cause=mortality and create missing MortalityLog records';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $cageFilter = $this->option('cage');

        $query = Hen::where('deactivation_cause', 'mortality')
            ->where('is_active', 0)
            ->with('cageSlot.cage');

        if ($cageFilter) {
            $query->whereHas('cageSlot', fn($q) => $q->where('cage_id', $cageFilter));
        }

        $staleHens = $query->get();

        if ($staleHens->isEmpty()) {
            $this->info('No stale deactivation_cause=mortality records found.');
            return Command::SUCCESS;
        }

        $this->warn(sprintf('Found %d hen(s) with stale deactivation_cause=mortality.', $staleHens->count()));
        $this->newLine();

        $staleByCage = $staleHens->groupBy(fn($h) => $h->cageSlot?->cage_id ?? 'unplaced');

        if (isset($staleByCage['unplaced'])) {
            $this->error(sprintf(
                '  %d hen(s) have no cage_slot_id. These cannot be recovered as mortality (no cage to associate the log with).',
                $staleByCage['unplaced']->count()
            ));
            $unplacedIds = $staleByCage['unplaced']->pluck('id')->implode(',');
            $this->line("  Hen IDs: {$unplacedIds}");
            $this->line('  Recommendation: clear deactivation_cause manually for these hens.');
            $this->newLine();
            unset($staleByCage['unplaced']);
        }

        if ($staleByCage->isEmpty()) {
            return Command::SUCCESS;
        }

        $totalRecovered = 0;

        foreach ($staleByCage as $cageId => $cageHens) {
            $cage = $cageHens->first()->cageSlot?->cage;
            $cageCode = $cage?->cage_code ?? "Cage #{$cageId}";
            $henIds = $cageHens->pluck('id')->toArray();

            $this->line(sprintf('  %s: %d hen(s) — hen IDs: %s', $cageCode, count($henIds), implode(', ', $henIds)));

            if (!$dryRun) {
                DB::transaction(function () use ($cageId, $henIds, $cageHens, &$totalRecovered) {
                    $reason = 'Unknown';
                    $firstHen = $cageHens->first();
                    if ($firstHen->removals()->exists()) {
                        $reason = $firstHen->removals()->latest()->value('reason') ?? 'Unknown';
                    }

                    $log = MortalityLog::create([
                        'cage_id'     => $cageId,
                        'log_date'    => now()->toDateString(),
                        'count'       => count($henIds),
                        'reason'      => $reason,
                        'notes'       => 'Recovered by mortality:recover-logs — original batch step failed',
                        'recorded_by' => 1,
                    ]);

                    foreach ($cageHens as $hen) {
                        $pivot = new MortalityLogHen;
                        $pivot->mortality_log_id = $log->id;
                        $pivot->hen_id = $hen->id;
                        $pivot->cage_slot_id = $hen->cage_slot_id;
                        $pivot->save();
                    }

                    Hen::whereIn('id', $henIds)->update(['deactivation_cause' => null]);
                    $totalRecovered += count($henIds);
                });

                $this->info("    ✓ Recovered — created MortalityLog with count=" . count($henIds));
            }

            $this->newLine();
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no changes written. Re-run without --dry-run to execute.');
        } else {
            $this->info("Done. Recovered {$totalRecovered} hen(s) across " . count($staleByCage) . " cage(s).");
        }

        return Command::SUCCESS;
    }
}
