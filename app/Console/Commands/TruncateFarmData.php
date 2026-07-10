<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TruncateFarmData extends Command
{
    protected $signature = 'farm:truncate {--force : Skip the confirmation prompt}';

    protected $description = 'Truncate all farm data except users, settings, cages, cage_slots, hens, production_logs, and environmental_logs.';

    public function handle(): int
    {
        $tablesToTruncate = [
            'alerts',
            'egg_size_logs',
            'egg_stock_batches',
            'feed_batches',
            'feed_consumption_logs',
            'forecasts',
            'hardware_items',
            'mortality_log_hens',
            'mortality_logs',
            'pre_orders',
            'production_logs',
            'environmental_logs',
            'hens',
            'cage_slots',
            'cages',
        ];

        $tablesToKeep = [
            'users',
            'settings',
        ];

        if (! $this->option('force') && ! $this->confirm(
            "This will truncate:\n- " . implode("\n- ", $tablesToTruncate) .
            "\n\nAnd keep:\n- " . implode("\n- ", $tablesToKeep) .
            "\n\nAre you sure you want to continue?"
        )) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tablesToTruncate as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
                $this->info("Truncated: {$table}");
            } else {
                $this->warn("Skipped (not found): {$table}");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->info('Farm data truncated successfully.');

        return self::SUCCESS;
    }
}
