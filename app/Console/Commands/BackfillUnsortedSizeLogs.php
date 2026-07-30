<?php

namespace App\Console\Commands;

use App\Models\EggSizeLog;
use App\Models\EggStockBatch;
use App\Models\ProductionLog;
use Illuminate\Console\Command;

class BackfillUnsortedSizeLogs extends Command
{
    protected $signature = 'layrate:backfill-unsorted-size-logs
        {--dry-run : Report what would be created without making changes}';

    protected $description = 'Create egg_size_logs entries with egg_size=unsorted for all production_logs that have no associated egg_size_logs records. Makes historically unsized production available to the Unsorted stock pool.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->line('── Backfill Unsorted Size Logs ────────────────');
        $this->line('Finding production_logs with zero egg_size_logs entries...');
        $this->line('');

        $candidates = ProductionLog::whereDoesntHave('eggSizeLogs')
            ->where('egg_count', '>', 0)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('All production logs already have size logs. Nothing to backfill.');
            return self::SUCCESS;
        }

        $totalLogs = $candidates->count();
        $totalEggs = $candidates->sum('egg_count');

        $this->line("Production logs without size logs: {$totalLogs}");
        $this->line("Total unsized eggs to backfill:   {$totalEggs}");
        $this->line('');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes made.');
            $this->line('Would create 1 EggSizeLog(unsorted) per production log:');
            $this->table(
                ['Log ID', 'Date', 'Cage Slot', 'Egg Count'],
                $candidates->map(fn($l) => [$l->id, $l->log_date->toDateString(), $l->cage_slot_id, $l->egg_count])->take(20)
            );
            if ($totalLogs > 20) {
                $this->line('... and ' . ($totalLogs - 20) . ' more.');
            }
            return self::SUCCESS;
        }

        $created = 0;
        $bar = $this->output->createProgressBar($totalLogs);
        $bar->start();

        foreach ($candidates as $log) {
            EggSizeLog::create([
                'production_log_id' => $log->id,
                'egg_size' => 'unsorted',
                'count' => $log->egg_count,
            ]);
            $created++;
            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');

        $unsortedLogged = EggSizeLog::where('egg_size', 'unsorted')->sum('count');
        $unsortedStocked = EggStockBatch::where('egg_size', 'unsorted')->sum('count');

        $this->info("Done. Created {$created} EggSizeLog(unsorted) records for {$totalEggs} eggs.");
        $this->line("Unsorted pool: {$unsortedLogged} logged - {$unsortedStocked} stocked = " . max(0, $unsortedLogged - $unsortedStocked) . " available to stock.");

        return self::SUCCESS;
    }
}
