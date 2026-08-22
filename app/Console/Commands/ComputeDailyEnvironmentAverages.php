<?php

namespace App\Console\Commands;

use App\Models\EnvironmentalLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ComputeDailyEnvironmentAverages extends Command
{
    protected $signature = 'environment:compute-daily-averages
        {--date= : Compute for a specific date (Y-m-d). Defaults to yesterday.}
        {--dry-run : Display results without storing.}';

    protected $description = 'Compute daily average/min/max environmental readings per cage and log summary';

    public function handle(): int
    {
        $targetDate = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))
            : now()->subDay();

        $dateStr = $targetDate->toDateString();

        $this->info("Computing daily averages for {$dateStr}...");

        $dailySummaries = EnvironmentalLog::whereDate('recorded_at', $dateStr)
            ->where('is_override', 0)
            ->selectRaw("
                cage_id,
                ROUND(AVG(temperature_c), 1) as avg_temp,
                ROUND(AVG(humidity_pct), 0) as avg_hum,
                ROUND(MIN(temperature_c), 1) as min_temp,
                ROUND(MAX(temperature_c), 1) as max_temp,
                ROUND(MIN(humidity_pct), 0) as min_hum,
                ROUND(MAX(humidity_pct), 0) as max_hum,
                COUNT(*) as reading_count
            ")
            ->groupBy('cage_id')
            ->get();

        // A manual override for the day is authoritative — never overwrite it
        // with a re-computed average (they share the same noon recorded_at key).
        $overrideCageIds = EnvironmentalLog::whereDate('recorded_at', $dateStr)
            ->where('is_override', 1)
            ->pluck('cage_id')
            ->all();

        if ($dailySummaries->isEmpty()) {
            $this->warn("No readings found for {$dateStr}.");
            return self::SUCCESS;
        }

        $this->line("Found {$dailySummaries->count()} cage(s) with readings on {$dateStr}:");

        foreach ($dailySummaries as $summary) {
            $line = "  Cage #{$summary->cage_id}: avg {$summary->avg_temp}°C / {$summary->avg_hum}% "
                  . "({$summary->min_temp}–{$summary->max_temp}°C, {$summary->min_hum}–{$summary->max_hum}%, "
                  . "{$summary->reading_count} readings)";

            if (in_array($summary->cage_id, $overrideCageIds)) {
                $this->line("  Cage #{$summary->cage_id}: day has a manual override — keeping it authoritative, skipping average.");
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("[DRY-RUN] {$line}");
            } else {
                // Store the summary as a single representative log for the day
                // using the noon timestamp so it sorts predictably
                $recordedAt = $targetDate->copy()->setHour(12)->setMinute(0)->setSecond(0);

                EnvironmentalLog::updateOrCreate(
                    [
                        'cage_id' => $summary->cage_id,
                        'recorded_at' => $recordedAt,
                    ],
                    [
                        'temperature_c' => $summary->avg_temp,
                        'humidity_pct' => $summary->avg_hum,
                    ]
                );

                $this->info("  ✓ {$line}");
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
