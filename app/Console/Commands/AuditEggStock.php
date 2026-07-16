<?php

namespace App\Console\Commands;

use App\Models\EggSizeLog;
use App\Models\EggStockBatch;
use App\Models\PreOrder;
use Illuminate\Console\Command;

class AuditEggStock extends Command
{
    protected $signature = 'layrate:audit-egg-stock
        {--detail : Show individual batch rows that contribute to overages}';

    protected $description = 'Check all egg_stock_batches entries against per-size production and pre-order commitment. Reports any size where total committed (stocked + pending pre-orders) exceeds total logged production. Read-only — does not modify data.';

    public function handle(): int
    {
        $sizes = ['small', 'medium', 'large', 'jumbo'];
        $showDetail = $this->option('detail');
        $exitCode = self::SUCCESS;

        $this->line('── Egg Stock Audit ──────────────────────────────');
        $this->line('Comparing: egg_size_logs (production by size) vs');
        $this->line('           egg_stock_batches (stocked by size) +');
        $this->line('           pre_orders where status=pending');
        $this->line('');

        foreach ($sizes as $size) {
            $logged = (int) EggSizeLog::where('egg_size', $size)->sum('count');
            $stocked = (int) EggStockBatch::where('egg_size', $size)->sum('count');
            $pendingPreOrders = (int) PreOrder::where('egg_size', $size)
                ->where('status', 'pending')
                ->sum('egg_count');
            $totalCommitted = $stocked + $pendingPreOrders;
            $remaining = $logged - $totalCommitted;
            $overage = $totalCommitted - $logged;

            $this->line("  <options=bold>{$size}</>");
            $this->line("    Produced (egg_size_logs):             {$logged}");
            $this->line("    Stocked (egg_stock_batches):          {$stocked}");
            $this->line("    Pending pre-orders:                    {$pendingPreOrders}");
            $this->line("    Total committed:                       {$totalCommitted}");

            if ($remaining < 0) {
                $this->error("    OVERAGE: committed exceeds production by {$overage}");
                $exitCode = self::FAILURE;

                if ($showDetail) {
                    $batches = EggStockBatch::where('egg_size', $size)
                        ->orderByDesc('created_at')
                        ->get();
                    if ($batches->isNotEmpty()) {
                        $this->line('    Stock batch detail:');
                        foreach ($batches as $b) {
                            $src = $b->sourceProductionLog
                                ? "log#{$b->sourceProductionLog->id} ({$b->sourceProductionLog->log_date})"
                                : 'no source log';
                            $this->line("      [{$b->id}] count={$b->count}, harvested={$b->harvested_date}, {$src}");
                        }
                    }
                    $preOrders = PreOrder::where('egg_size', $size)
                        ->where('status', 'pending')
                        ->orderByDesc('requested_date')
                        ->get();
                    if ($preOrders->isNotEmpty()) {
                        $this->line('    Pending pre-order detail:');
                        foreach ($preOrders as $po) {
                            $this->line("      [{$po->id}] {$po->customer_name}, count={$po->egg_count}, requested={$po->requested_date}");
                        }
                    }
                }
            } else {
                $this->line("    Remaining available:                  {$remaining}");
            }
            $this->line('');
        }

        if ($exitCode === self::SUCCESS) {
            $this->info('All sizes within production limits.');
        } else {
            $this->warn('Some sizes exceed production. Review details above.');
        }

        return $exitCode;
    }
}
