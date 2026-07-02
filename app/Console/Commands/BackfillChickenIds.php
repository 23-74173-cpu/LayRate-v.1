<?php

namespace App\Console\Commands;

use App\Models\Hen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillChickenIds extends Command
{
    protected $signature = 'layrate:backfill-chicken-ids
        {--dry-run : Count records that would be updated without applying}';

    protected $description = 'Assign chicken_id to hens where it is currently NULL, using the year from date_acquired (fallback: created_at) and sequential numbering per year';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $hens = Hen::whereNull('chicken_id')
            ->orderBy('id')
            ->get(['id', 'date_acquired', 'created_at']);

        if ($hens->isEmpty()) {
            $this->info('All hens already have a chicken_id.');
            return self::SUCCESS;
        }

        $this->line("Found {$hens->count()} hen(s) without chicken_id.");

        // Group by year — use date_acquired year if available, fallback to created_at year
        $byYear = $hens->groupBy(fn ($h) => ($h->date_acquired?->year ?? $h->created_at->year));

        $total = 0;

        foreach ($byYear as $year => $group) {
            // Determine the next available number for this year by checking
            // existing chicken_ids that already follow the pattern
            $lastId = Hen::where('chicken_id', 'like', "CHK-{$year}-%")
                ->lockForUpdate()
                ->orderBy('chicken_id', 'desc')
                ->value('chicken_id');

            $next = $lastId ? (int) substr($lastId, -5) + 1 : 1;

            $this->line("  Year {$year}: {$group->count()} hen(s), starting at CHK-{$year}-" . str_pad($next, 5, '0', STR_PAD_LEFT));

            if ($dryRun) {
                foreach ($group as $i => $hen) {
                    $num = $next + $i;
                    $chickenId = sprintf("CHK-%s-%05d", $year, $num);
                    $this->line("    Hen #{$hen->id} → {$chickenId}");
                }
                $total += $group->count();
                continue;
            }

            // Update within a transaction so the lock is held
            DB::transaction(function () use ($group, $year) {
                // Re-acquire the lock + last ID inside the transaction
                $lastId = Hen::where('chicken_id', 'like', "CHK-{$year}-%")
                    ->lockForUpdate()
                    ->orderBy('chicken_id', 'desc')
                    ->value('chicken_id');

                $next = $lastId ? (int) substr($lastId, -5) + 1 : 1;

                foreach ($group as $hen) {
                    $chickenId = sprintf("CHK-%s-%05d", $year, $next);
                    Hen::whereKey($hen->id)->update(['chicken_id' => $chickenId]);
                    $this->line("    Hen #{$hen->id} → {$chickenId}");
                    $next++;
                }
            });
        }

        if (! $dryRun) {
            $this->info("Assigned chicken_id to {$hens->count()} hen(s).");
        }

        return self::SUCCESS;
    }
}
