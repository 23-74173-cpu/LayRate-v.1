<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Services\EnvironmentStatusService;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Mandatory first-run setup (admin only): send to the wizard until it
        // has run once. Guarded by the zero-cage check so once setup is done
        // (or cages already exist) users are never blocked.
        if (auth()->user()?->isAdmin()
            && Setting::get('setup_completed') != '1'
            && Cage::count() === 0) {
            return redirect()->route('setup');
        }

        $data = $this->buildDashboardData();

        return view('dashboard', $data);
    }

    public function stats()
    {
        $data = $this->buildDashboardData(request('cage'), (int) request('mortality_days', 1));

        return view('dashboard._metric-cards', $data);
    }

    public function feedMortality()
    {
        $data = $this->buildDashboardData(request('cage'));

        return view('dashboard._feed-mortality', $data);
    }

    public function calendar()
    {
        $cageCode = request('cage');
        $now = ReportingDateService::now();
        $month = max(1, min(12, (int) request('month', $now->month)));
        $year = max(2000, (int) request('year', $now->year));
        $calendarMonth = $now->copy()->setDate($year, $month, 1)->startOfMonth();

        $monthStart = $calendarMonth->copy()->startOfMonth();
        $monthEnd = $calendarMonth->copy()->endOfMonth();
        // Leading/trailing days spill into adjacent months, so fetch a little
        // margin either side to cover every rendered cell.
        $rangeStart = $monthStart->copy()->subDays(6);
        $rangeEnd = $monthEnd->copy()->addDays(6);

        $scope = fn ($q) => $q->when($cageCode, fn ($cq) => $cq->whereHas('cageSlot.cage', fn ($c) => $c->where('cage_code', $cageCode)));

        $logs = ProductionLog::query()
            ->whereBetween('log_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->where($scope)
            ->get()
            ->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))
            ->map(fn ($g) => (object) [
                'eggs' => $g->sum('egg_count'),
                'logs' => $g->count(),
            ]);

        $monthLogs = ProductionLog::query()
            ->whereBetween('log_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where($scope)
            ->get();
        $monthTotalEggs = $monthLogs->sum('egg_count');
        $monthLoggedDays = $monthLogs->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))->count();

        // Year options for navigation: earliest recorded log .. next year.
        $firstLogDate = ProductionLog::query()->orderBy('log_date')->value('log_date');
        $firstYear = $firstLogDate ? (int) date('Y', strtotime($firstLogDate)) : $now->year;
        $yearOptions = range(max($firstYear, $now->year - 10), $now->year + 1);

        $cageOptions = Cage::query()->orderBy('cage_code')->get(['id', 'cage_code']);

        return view('dashboard._calendar', compact(
            'calendarMonth', 'logs', 'monthTotalEggs', 'monthLoggedDays', 'yearOptions', 'cageOptions', 'cageCode'
        ));
    }

    public function cagePerformance()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 1);
        if (! in_array($days, [1, 7, 14, 30])) {
            $days = 1;
        }

        $data = $this->buildDashboardData($cageCode);

        // Override per-cage stats for the selected period.
        $reportingDate = ReportingDateService::reportingDate();
        $endDate = $reportingDate->toDateString();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();

        $cageIds = $data['cages']->pluck('id');

        $periodStats = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->whereBetween('production_logs.log_date', [$startDate, $endDate])
            ->selectRaw('cage_slots.cage_id as cage_id, SUM(production_logs.egg_count) as total_eggs, AVG(production_logs.hdep) as avg_hdep')
            ->groupBy('cage_slots.cage_id')
            ->get()
            ->keyBy('cage_id');

        $data['cages']->each(function ($cage) use ($periodStats, $days) {
            $stats = $periodStats->get($cage->id);
            $cage->period_eggs = (int) ($stats?->total_eggs ?? 0);
            $cage->period_hdep = $cage->hen_count > 0 && $days > 0
                ? round($cage->period_eggs / ($cage->hen_count * $days) * 100, 1)
                : 0;
            // Fall back to stored avg_hdep if available and computed value is zero but eggs exist.
            if ($cage->period_hdep === 0.0 && $stats?->avg_hdep !== null) {
                $cage->period_hdep = round((float) $stats->avg_hdep, 1);
            }
        });

        $data['days'] = $days;
        $data['cageCode'] = $cageCode;

        return view('dashboard._cage-performance', $data);
    }

    public function productionHistory()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 7);
        $compare = request('compare', false);
        if (! in_array($days, [7, 14, 30])) {
            $days = 7;
        }

        $reportingDate = ReportingDateService::reportingDate();
        $endDate = $reportingDate->toDateString();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();

        // Build date labels and data points, filling missing days with 0.
        $labels = collect(range(0, $days - 1))
            ->map(fn ($i) => $reportingDate->copy()->subDays($days - 1 - $i)->format('M j'))
            ->values()
            ->toArray();

        $dateKeys = collect(range(0, $days - 1))
            ->map(fn ($i) => $reportingDate->copy()->subDays($days - 1 - $i)->toDateString())
            ->values();

        if ($compare) {
            // Multi-line comparison: one dataset per active cage.
            $cages = Cage::query()
                ->when($cageCode, fn ($q) => $q->where('cage_code', $cageCode))
                ->where('is_active', 1)
                ->orderBy('cage_code')
                ->get();

            $logs = ProductionLog::query()
                ->with('cageSlot.cage')
                ->whereBetween('log_date', [$startDate, $endDate])
                ->when($cageCode, fn ($q) => $q->whereHas('cageSlot.cage', fn ($c) => $c->where('cage_code', $cageCode)))
                ->get()
                ->groupBy(fn ($l) => $l->cageSlot?->cage?->cage_code ?? 'Unknown')
                ->map(fn ($group) => $group->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))->map(fn ($g) => (int) $g->sum('egg_count')));

            $datasets = $cages->map(function ($cage) use ($dateKeys, $logs) {
                $cageLogs = $logs->get($cage->cage_code, collect());
                $color = $cage->color;
                $softColor = $cage->colorSoft;
                return [
                    'label' => $cage->cage_code,
                    'data' => $dateKeys->map(fn ($date) => $cageLogs->get($date, 0))->values()->toArray(),
                    'borderColor' => $color,
                    'backgroundColor' => $softColor,
                    'pointBorderColor' => $color,
                    'pointBorderWidth' => 2,
                    'tension' => 0.4,
                    'borderWidth' => 2.5,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 5,
                    'fill' => true,
                ];
            })->values()->toArray();

            $title = $cageCode ? $cageCode . ' Production Comparison' : 'Cage Production Comparison';
        } else {
            // Same query pattern as the egg-management calendar (proven reliable).
            $scope = fn ($q) => $q->when($cageCode, fn ($cq) => $cq->whereHas('cageSlot.cage', fn ($c) => $c->where('cage_code', $cageCode)));

            $logs = ProductionLog::query()
                ->whereBetween('log_date', [$startDate, $endDate])
                ->where($scope)
                ->get()
                ->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))
                ->map(fn ($g) => (int) $g->sum('egg_count'));

            $dataPoints = $dateKeys->map(fn ($date) => $logs->get($date, 0))->values()->toArray();

            $title = $cageCode ? $cageCode . ' Production' : 'Total Production';

            $datasets = [[
                'label' => $title,
                'data' => $dataPoints,
                'borderColor' => '#102A4C',
                'backgroundColor' => 'rgba(16, 42, 76, 0.1)',
                'tension' => 0.3,
                'borderWidth' => 3,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'fill' => true,
            ]];
        }

        $chartData = [
            'labels' => $labels,
            'datasets' => $datasets,
        ];

        $insight = 'No production data available for analysis.';
        if (! empty($datasets)) {
            $allPoints = [];
            foreach ($datasets as $ds) {
                foreach ($ds['data'] as $v) {
                    if ($v > 0) $allPoints[] = $v;
                }
            }
            if (count($allPoints) >= 2) {
                $total = array_sum($allPoints);
                $peak = max($allPoints);
                $avg = round($total / count($allPoints));
                $min = min($allPoints);
                $firstHalf = array_slice($allPoints, 0, intdiv(count($allPoints), 2));
                $secondHalf = array_slice($allPoints, intdiv(count($allPoints), 2));
                $firstAvg = count($firstHalf) > 0 ? round(array_sum($firstHalf) / count($firstHalf)) : 0;
                $secondAvg = count($secondHalf) > 0 ? round(array_sum($secondHalf) / count($secondHalf)) : 0;

                $insight = "Over the last {$days} days, daily egg production averaged {$avg} eggs (peak: {$peak}).";
                if ($secondAvg > $firstAvg * 1.1 && $firstAvg > 0) {
                    $insight .= " Production is trending upward — a positive signal.";
                } elseif ($firstAvg > $secondAvg * 1.1 && $secondAvg > 0) {
                    $insight .= " Production is declining in the recent period — investigate possible causes.";
                } else {
                    $insight .= " Production levels have been relatively stable.";
                }
                if ($peak > 0 && $min > 0) {
                    $range = round(($peak - $min) / $peak * 100);
                    if ($range > 30) {
                        $insight .= " Day-to-day variation is high ({$range}% range), suggesting inconsistent collection or production.";
                    }
                }
            } elseif (count($allPoints) === 1) {
                $insight = "Only one day of data recorded ({$allPoints[0]} eggs).";
            }
        }

        return view('dashboard._production-history', compact('chartData', 'days', 'cageCode', 'title', 'compare', 'insight'));
    }

    public function eggCollectionTime()
    {
        $reportingDate = ReportingDateService::reportingDate();
        $days = 7;

        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        // Group egg counts by hour of day using created_at timestamp
        $hourly = ProductionLog::whereBetween('log_date', [$startDate, $endDate])
            ->selectRaw('HOUR(created_at) as hour, SUM(egg_count) as total_eggs')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('total_eggs', 'hour')
            ->toArray();

        // Build 24-hour labels (12AM–11PM)
        $labels = [];
        $data = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = $h === 0 ? '12AM' : ($h < 12 ? $h . 'AM' : ($h === 12 ? '12PM' : ($h - 12) . 'PM'));
            $data[] = $hourly[$h] ?? 0;
        }

        $chartData = [
            'labels' => $labels,
            'data' => $data,
            'total' => array_sum($data),
        ];

        $insight = 'No collection data available for analysis.';
        if (array_sum($data) > 0) {
            $peakVal = max($data);
            $peakIdx = array_search($peakVal, $data);
            $peakHour = $labels[$peakIdx];
            $pctAtPeak = round($peakVal / array_sum($data) * 100, 1);
            $morningTotal = array_sum(array_slice($data, 5, 7));
            $morningPct = round($morningTotal / array_sum($data) * 100, 1);
            $insight = "Peak collection is at {$peakHour} with {$pctAtPeak}% of total eggs. Morning hours (6AM–12PM) account for {$morningPct}% of daily production.";
            if ($pctAtPeak > 25) {
                $insight .= " Eggs are heavily concentrated at one time — consider spreading collection across more hours to reduce breakage.";
            }
        }

        return view('dashboard._egg-collection-time', compact('chartData', 'days', 'insight'));
    }

    public function henAgeLayrate()
    {
        $reportingDate = ReportingDateService::reportingDate();
        $days = 90;
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        // Get production logs with their cage_slot's hens
        $logs = ProductionLog::query()
            ->with('cageSlot.hens')
            ->whereBetween('log_date', [$startDate, $endDate])
            ->where('hen_count', '>', 0)
            ->where('hdep', '>', 0)
            ->get();

        // Group by hen age in weeks → average HDEP
        $ageData = [];
        foreach ($logs as $log) {
            $hens = $log->cageSlot->hens ?? collect();
            $activeHens = $hens->where('is_active', true);
            if ($activeHens->isEmpty()) continue;

            foreach ($activeHens as $hen) {
                $placementDate = $hen->placement_date ?? $hen->date_acquired;
                if (! $placementDate) continue;

                $ageWeeks = (int) floor($placementDate->copy()->diffInWeeks($log->log_date));
                $ageWeeks = max(0, $ageWeeks + (int) ($hen->age_at_placement_weeks ?? 0));

                if ($ageWeeks < 1 || $ageWeeks > 120) continue;

                $ageData[$ageWeeks][] = (float) $log->hdep;
            }
        }

        // Build chart data: age weeks → average HDEP
        $allLabels = [];
        $allData = [];
        $allCounts = [];
        ksort($ageData);
        foreach ($ageData as $ageWeek => $hdeps) {
            $allLabels[] = $ageWeek;
            $allData[] = round(array_sum($hdeps) / count($hdeps), 1);
            $allCounts[] = count($hdeps);
        }

        // Find peak
        $peakIdx = $allData ? array_search(max($allData), $allData) : null;
        $peakAge = $peakIdx !== null ? $allLabels[$peakIdx] : null;

        // Crop to ±15 weeks around peak, but show all data if narrow range
        $margin = 15;
        if ($peakAge !== null && (max($allLabels) - min($allLabels)) > $margin * 2) {
            $minAge = max(0, $peakAge - $margin);
            $maxAge = $peakAge + $margin;
        } else {
            $minAge = $allLabels ? min($allLabels) : 0;
            $maxAge = $allLabels ? max($allLabels) : 60;
        }

        $labels = [];
        $data = [];
        $counts = [];
        foreach ($allLabels as $i => $ageWeek) {
            if ($ageWeek < $minAge || $ageWeek > $maxAge) continue;
            $labels[] = 'Wk ' . $ageWeek;
            $data[] = $allData[$i];
            $counts[] = $allCounts[$i];
        }

        // Recalculate peak index in cropped array
        $croppedPeakIdx = $data ? array_search(max($data), $data) : null;

        $chartData = [
            'labels' => $labels,
            'data' => $data,
            'counts' => $counts,
            'peak_age' => $croppedPeakIdx,
            'peak_hdep' => $data ? max($data) : 0,
            'peak_label' => $peakAge !== null ? 'Week ' . $peakAge : '—',
            'all_ages_count' => count($allLabels),
        ];

        $insight = 'No age data available for analysis.';
        if (! empty($data) && $peakAge !== null) {
            $insight = "Production peaks at Week {$peakAge} with {$chartData['peak_hdep']}% HDEP, based on {$chartData['all_ages_count']} age weeks tracked.";
            if ($peakAge >= 25 && $peakAge <= 35) {
                $insight .= " This is within the typical peak production window (25–35 weeks).";
            } elseif ($peakAge < 25) {
                $insight .= " The early peak may indicate younger hens are outperforming expectations.";
            } elseif ($peakAge > 40) {
                $insight .= " The late peak suggests older hens are still productive — monitor for upcoming decline.";
            }
            if (count($data) >= 3) {
                $afterPeak = array_slice($data, $croppedPeakIdx + 1);
                if (! empty($afterPeak)) {
                    $decline = round($chartData['peak_hdep'] - max($afterPeak), 1);
                    if ($decline > 10) {
                        $insight .= " Production drops by {$decline} points after the peak — plan flock rotation accordingly.";
                    }
                }
            }
        }

        return view('dashboard._hen-age-layrate', compact('chartData', 'insight'));
    }

    public function tempVsHdep()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 30);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $cageIds = Cage::query()
            ->when($cageCode, fn ($q) => $q->where('cage_code', $cageCode))
            ->pluck('id');

        $points = EnvironmentalLog::select('cage_id', DB::raw('DATE(recorded_at) as log_date'), DB::raw('AVG(temperature_c) as avg_temp'))
            ->whereIn('cage_id', $cageIds)
            ->whereBetween(DB::raw('DATE(recorded_at)'), [$startDate, $endDate])
            ->groupBy('cage_id', 'log_date')
            ->get();

        $prodByCageDate = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->whereBetween('production_logs.log_date', [$startDate, $endDate])
            ->where('hdep', '>', 0)
            ->selectRaw('cage_slots.cage_id as cage_id, production_logs.log_date as log_date, AVG(production_logs.hdep) as avg_hdep')
            ->groupBy('cage_slots.cage_id', 'production_logs.log_date')
            ->get()
            ->keyBy(fn ($r) => $r->cage_id . '|' . $r->log_date);

        $scatterData = [];
        foreach ($points as $p) {
            $key = $p->cage_id . '|' . $p->log_date;
            $prod = $prodByCageDate->get($key);
            if ($prod && $prod->avg_hdep > 0) {
                $scatterData[] = ['x' => round((float) $p->avg_temp, 1), 'y' => round((float) $prod->avg_hdep, 1)];
            }
        }

        $insight = 'Insufficient data for analysis.';
        if (count($scatterData) >= 5) {
            $temps = array_column($scatterData, 'x');
            $hdeps = array_column($scatterData, 'y');
            $n = count($scatterData);
            $sumX = array_sum($temps); $sumY = array_sum($hdeps);
            $sumXY = 0; $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) { $sumXY += $temps[$i] * $hdeps[$i]; $sumX2 += $temps[$i] * $temps[$i]; }
            $denom = $n * $sumX2 - $sumX * $sumX;
            $r = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / sqrt($denom * ($n * array_sum(array_map(fn($v) => $v * $v, $hdeps)) - $sumY * $sumY)) : 0;
            if ($r < -0.3) $insight = 'HDEP tends to decrease as temperature increases.';
            elseif ($r > 0.3) $insight = 'HDEP tends to increase with temperature.';
            else $insight = 'No clear relationship detected between temperature and HDEP.';
        }

        return view('dashboard._temp-vs-hdep', compact('scatterData', 'insight'));
    }

    public function humVsHdep()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 30);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $cageIds = Cage::query()
            ->when($cageCode, fn ($q) => $q->where('cage_code', $cageCode))
            ->pluck('id');

        $points = EnvironmentalLog::select('cage_id', DB::raw('DATE(recorded_at) as log_date'), DB::raw('AVG(humidity_pct) as avg_hum'))
            ->whereIn('cage_id', $cageIds)
            ->whereBetween(DB::raw('DATE(recorded_at)'), [$startDate, $endDate])
            ->groupBy('cage_id', 'log_date')
            ->get();

        $prodByCageDate = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->whereBetween('production_logs.log_date', [$startDate, $endDate])
            ->where('hdep', '>', 0)
            ->selectRaw('cage_slots.cage_id as cage_id, production_logs.log_date as log_date, AVG(production_logs.hdep) as avg_hdep')
            ->groupBy('cage_slots.cage_id', 'production_logs.log_date')
            ->get()
            ->keyBy(fn ($r) => $r->cage_id . '|' . $r->log_date);

        $scatterData = [];
        foreach ($points as $p) {
            $key = $p->cage_id . '|' . $p->log_date;
            $prod = $prodByCageDate->get($key);
            if ($prod && $prod->avg_hdep > 0) {
                $scatterData[] = ['x' => round((float) $p->avg_hum, 1), 'y' => round((float) $prod->avg_hdep, 1)];
            }
        }

        $insight = 'Insufficient data for analysis.';
        if (count($scatterData) >= 5) {
            $hums = array_column($scatterData, 'x');
            $hdeps = array_column($scatterData, 'y');
            $n = count($scatterData);
            $sumX = array_sum($hums); $sumY = array_sum($hdeps);
            $sumXY = 0; $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) { $sumXY += $hums[$i] * $hdeps[$i]; $sumX2 += $hums[$i] * $hums[$i]; }
            $denom = $n * $sumX2 - $sumX * $sumX;
            $r = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / sqrt($denom * ($n * array_sum(array_map(fn($v) => $v * $v, $hdeps)) - $sumY * $sumY)) : 0;
            if ($r < -0.3) $insight = 'HDEP tends to decrease as humidity increases.';
            elseif ($r > 0.3) $insight = 'HDEP tends to increase with humidity.';
            else $insight = 'No clear relationship detected between humidity and HDEP.';
        }

        return view('dashboard._hum-vs-hdep', compact('scatterData', 'insight'));
    }

    public function breedAnalytics()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 30);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $breedData = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->join('hens', 'hens.cage_slot_id', '=', 'cage_slots.id')
            ->where('hens.is_active', 1)
            ->whereBetween('production_logs.log_date', [$startDate, $endDate])
            ->where('production_logs.hdep', '>', 0)
            ->when($cageCode, fn ($q) => $q->whereHas('cageSlot.cage', fn ($c) => $c->where('cage_code', $cageCode)))
            ->selectRaw('hens.breed as breed, AVG(production_logs.hdep) as avg_hdep, COUNT(*) as log_count')
            ->groupBy('hens.breed')
            ->orderByDesc('avg_hdep')
            ->get()
            ->filter(fn ($r) => $r->avg_hdep > 0);

        $labels = $breedData->pluck('breed')->values()->toArray();
        $data = $breedData->pluck('avg_hdep')->map(fn ($v) => round($v, 1))->values()->toArray();
        $bestBreed = $breedData->first();

        $insight = 'No breed data available for analysis.';
        if (count($breedData) === 1) {
            $insight = "Only one breed ({$bestBreed->breed}) is represented in the data with an average HDEP of {$bestBreed->avg_hdep}%.";
        } elseif (count($breedData) >= 2) {
            $worst = $breedData->last();
            $gap = round($bestBreed->avg_hdep - $worst->avg_hdep, 1);
            $insight = "{$bestBreed->breed} is the top performer at {$bestBreed->avg_hdep}% HDEP, outperforming {$worst->breed} by {$gap} percentage points.";
            if ($gap > 10) {
                $insight .= " The large gap suggests {$worst->breed} may need management adjustments.";
            }
        }

        return view('dashboard._breed-analytics', compact('labels', 'data', 'bestBreed', 'insight'));
    }

    public function mortalityByCause()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 30);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $causeData = MortalityLog::query()
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->whereBetween('log_date', [$startDate, $endDate])
            ->selectRaw('reason, SUM(count) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get()
            ->filter(fn ($r) => $r->total > 0);

        $labels = $causeData->pluck('reason')->values()->toArray();
        $data = $causeData->pluck('total')->values()->toArray();
        $totalDeaths = array_sum($data);
        $topCause = $causeData->first();

        $insight = 'No mortality data available for analysis.';
        if ($totalDeaths > 0 && $topCause) {
            $topPct = round($topCause->total / $totalDeaths * 100);
            $insight = "{$topCause->reason} is the leading cause of death, accounting for {$topPct}% of all losses ({$topCause->total} of {$totalDeaths}).";
            if (count($causeData) >= 2) {
                $second = $causeData[1];
                if ($topPct >= 60) {
                    $insight .= " Focus prevention efforts on {$topCause->reason} for the greatest impact.";
                } else {
                    $insight .= " Losses are spread across multiple causes, so a broad approach to prevention is recommended.";
                }
            }
        }

        return view('dashboard._mortality-by-cause', compact('labels', 'data', 'totalDeaths', 'topCause', 'insight'));
    }

    public function mortalityTrend()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 30);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $labels = collect(range(0, $days - 1))
            ->map(fn ($i) => $reportingDate->copy()->subDays($days - 1 - $i)->format('M j'))
            ->values()->toArray();

        $dateKeys = collect(range(0, $days - 1))
            ->map(fn ($i) => $reportingDate->copy()->subDays($days - 1 - $i)->toDateString())
            ->values();

        $dailyCounts = MortalityLog::query()
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->whereBetween('log_date', [$startDate, $endDate])
            ->selectRaw('log_date, SUM(count) as total')
            ->groupBy('log_date')
            ->pluck('total', 'log_date');

        $data = $dateKeys->map(fn ($d) => (int) ($dailyCounts->get($d, 0)))->values()->toArray();
        $peakVal = max($data);
        $peakIdx = array_search($peakVal, $data);
        $peakLabel = $labels[$peakIdx] ?? '';
        $avgDaily = $days > 0 ? round(array_sum($data) / $days, 1) : 0;

        $insight = 'No mortality data available for analysis.';
        if (array_sum($data) > 0) {
            $firstHalf = array_slice($data, 0, intdiv(count($data), 2));
            $secondHalf = array_slice($data, intdiv(count($data), 2));
            $firstAvg = count($firstHalf) > 0 ? round(array_sum($firstHalf) / count($firstHalf), 1) : 0;
            $secondAvg = count($secondHalf) > 0 ? round(array_sum($secondHalf) / count($secondHalf), 1) : 0;

            $insight = "Average daily mortality is {$avgDaily} deaths over the last {$days} days.";
            if ($peakVal > $avgDaily * 2 && $peakVal > 0) {
                $insight .= " A spike of {$peakVal} deaths on {$peakLabel} was well above average — investigate potential causes.";
            }
            if ($secondAvg > $firstAvg * 1.3 && $firstAvg > 0) {
                $insight .= " Mortality is trending upward in the recent half.";
            } elseif ($firstAvg > $secondAvg * 1.3 && $secondAvg > 0) {
                $insight .= " Mortality is trending downward — a positive sign.";
            } else {
                $insight .= " Mortality levels have been relatively stable.";
            }
        }

        return view('dashboard._mortality-trend', compact('labels', 'data', 'peakVal', 'peakLabel', 'avgDaily', 'insight'));
    }

    public function feedVsEgg()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 30);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $feedByCageDate = FeedConsumptionLog::query()
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->whereBetween('log_date', [$startDate, $endDate])
            ->selectRaw('cage_id, log_date, SUM(feed_consumed_kg) as feed_kg')
            ->groupBy('cage_id', 'log_date')
            ->get()
            ->keyBy(fn ($r) => $r->cage_id . '|' . $r->log_date);

        $prodByCageDate = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->when($cageCode, fn ($q) => $q->whereHas('cage_slots.cage', fn ($c) => $c->where('cage_code', $cageCode)))
            ->whereBetween('production_logs.log_date', [$startDate, $endDate])
            ->selectRaw('cage_slots.cage_id as cage_id, production_logs.log_date as log_date, SUM(production_logs.egg_count) as eggs')
            ->groupBy('cage_slots.cage_id', 'production_logs.log_date')
            ->get()
            ->keyBy(fn ($r) => $r->cage_id . '|' . $r->log_date);

        $scatterData = [];
        foreach ($feedByCageDate as $key => $f) {
            $p = $prodByCageDate->get($key);
            if ($p && $f->feed_kg > 0 && $p->eggs > 0) {
                $scatterData[] = ['x' => round((float) $f->feed_kg, 2), 'y' => (int) $p->eggs];
            }
        }

        $insight = 'Insufficient data for analysis.';
        if (count($scatterData) >= 5) {
            $xs = array_column($scatterData, 'x');
            $ys = array_column($scatterData, 'y');
            $n = count($scatterData);
            $sumX = array_sum($xs); $sumY = array_sum($ys);
            $sumXY = 0; $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) { $sumXY += $xs[$i] * $ys[$i]; $sumX2 += $xs[$i] * $xs[$i]; }
            $denom = $n * $sumX2 - $sumX * $sumX;
            $r = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / sqrt($denom * ($n * array_sum(array_map(fn($v) => $v * $v, $ys)) - $sumY * $sumY)) : 0;
            if ($r > 0.3) $insight = 'Feed consumption shows a positive relationship with egg production.';
            elseif ($r < -0.3) $insight = 'Feed consumption shows a negative relationship with egg production.';
            else $insight = 'No clear relationship detected between feed consumption and egg production.';
        }

        return view('dashboard._feed-vs-egg', compact('scatterData', 'insight'));
    }

    public function feedByCage()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 7);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $feedData = FeedConsumptionLog::query()
            ->join('cages', 'cages.id', '=', 'feed_consumption_logs.cage_id')
            ->when($cageCode, fn ($q) => $q->where('cages.cage_code', $cageCode))
            ->whereBetween('feed_consumption_logs.log_date', [$startDate, $endDate])
            ->selectRaw('cages.cage_code as cage_code, cages.id as cage_id, SUM(feed_consumption_logs.feed_consumed_kg) as total_feed, COUNT(DISTINCT feed_consumption_logs.log_date) as days_logged')
            ->groupBy('cages.id', 'cages.cage_code')
            ->orderBy('cages.cage_code')
            ->get()
            ->map(function ($r) use ($days) {
                $r->avg_daily = $r->days_logged > 0 ? round($r->total_feed / $r->days_logged, 2) : 0;
                $r->hen_count = Cage::find($r->cage_id)?->hens->where('is_active', 1)->count() ?? 0;
                return $r;
            });

        $labels = $feedData->pluck('cage_code')->values()->toArray();
        $data = $feedData->pluck('avg_daily')->values()->toArray();
        $highest = $feedData->sortByDesc('avg_daily')->first();

        $insight = 'No feed data available for analysis.';
        if ($feedData->isNotEmpty() && $highest) {
            $avgFeed = round($data ? array_sum($data) / count($data) : 0, 2);
            $insight = "{$highest->cage_code} has the highest feed consumption at {$highest->avg_daily} kg/day.";
            if ($highest->hen_count > 0) {
                $perHen = round($highest->avg_daily / $highest->hen_count * 1000, 1);
                $insight .= " That's {$perHen}g per hen per day.";
            }
            if (count($feedData) >= 2) {
                $pctAbove = $avgFeed > 0 ? round(($highest->avg_daily - $avgFeed) / $avgFeed * 100) : 0;
                if ($pctAbove > 20) {
                    $insight .= " This is {$pctAbove}% above the cage average — check for overfeeding or waste.";
                } else {
                    $insight .= " Consumption is fairly balanced across cages.";
                }
            }
        }

        return view('dashboard._feed-by-cage', compact('labels', 'data', 'highest', 'feedData', 'insight'));
    }

    public function heatStress()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 30);
        $reportingDate = ReportingDateService::reportingDate();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();
        $endDate = $reportingDate->toDateString();

        $thresholds = Setting::thresholds();
        $tempMax = (float) ($thresholds['temp_max'] ?? 30);

        $cageIds = Cage::query()
            ->when($cageCode, fn ($q) => $q->where('cage_code', $cageCode))
            ->pluck('id');

        $envByCageDate = EnvironmentalLog::select('cage_id', 'recorded_at', DB::raw('AVG(temperature_c) as avg_temp'), DB::raw('AVG(humidity_pct) as avg_hum'))
            ->whereIn('cage_id', $cageIds)
            ->whereBetween(DB::raw('DATE(recorded_at)'), [$startDate, $endDate])
            ->groupBy('cage_id', 'recorded_at')
            ->get();

        $prodByCageDate = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->whereBetween('production_logs.log_date', [$startDate, $endDate])
            ->where('hdep', '>', 0)
            ->selectRaw('cage_slots.cage_id as cage_id, production_logs.log_date as log_date, AVG(production_logs.hdep) as avg_hdep')
            ->groupBy('cage_slots.cage_id', 'production_logs.log_date')
            ->get()
            ->keyBy(fn ($r) => $r->cage_id . '|' . $r->log_date);

        $mortByDate = MortalityLog::query()
            ->whereIn('cage_id', $cageIds)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->selectRaw('log_date, SUM(count) as total')
            ->groupBy('log_date')
            ->pluck('total', 'log_date');

        $levels = ['Normal' => [], 'Moderate' => [], 'High' => []];
        $peakTemp = 0;

        foreach ($envByCageDate as $e) {
            $temp = (float) $e->avg_temp;
            if ($temp > $peakTemp) $peakTemp = $temp;

            $level = 'Normal';
            if ($temp > $tempMax + 4) $level = 'High';
            elseif ($temp > $tempMax) $level = 'Moderate';

            $reportingDate = ReportingDateService::reportingDateFor($e->recorded_at)->toDateString();
            $key = $e->cage_id . '|' . $reportingDate;
            $prod = $prodByCageDate->get($key);
            $hdep = $prod ? (float) $prod->avg_hdep : null;
            $mort = (int) ($mortByDate->get($reportingDate, 0));

            $levels[$level][] = ['hdep' => $hdep, 'mortality' => $mort, 'temp' => $temp];
        }

        $summary = [];
        foreach ($levels as $label => $rows) {
            $hdeps = array_filter(array_column($rows, 'hdep'), fn ($v) => $v > 0);
            $summary[$label] = [
                'count' => count($rows),
                'avg_hdep' => count($hdeps) > 0 ? round(array_sum($hdeps) / count($hdeps), 1) : null,
                'mortality' => array_sum(array_column($rows, 'mortality')),
            ];
        }

        $highEvents = count($levels['High']);
        $highHdeps = array_filter(array_column($levels['High'], 'hdep'), fn ($v) => $v > 0);
        $highAvgHdep = count($highHdeps) > 0 ? round(array_sum($highHdeps) / count($highHdeps), 1) : null;

        $insight = 'No temperature data available for analysis.';
        if (count($envByCageDate) > 0) {
            $normalHdeps = array_filter(array_column($levels['Normal'], 'hdep'), fn ($v) => $v > 0);
            $normalAvg = count($normalHdeps) > 0 ? round(array_sum($normalHdeps) / count($normalHdeps), 1) : null;
            $insight = "Analyzed " . count($envByCageDate) . " cage-day temperature records against the {$tempMax}°C threshold.";
            if ($highEvents > 0 && $highAvgHdep !== null && $normalAvg !== null) {
                $diff = round($normalAvg - $highAvgHdep, 1);
                $insight .= " During high heat stress events, HDEP dropped to {$highAvgHdep}% compared to {$normalAvg}% under normal conditions — a {$diff} point reduction.";
                $highMort = array_sum(array_column($levels['High'], 'mortality'));
                if ($highMort > 0) {
                    $insight .= " {$highMort} deaths also occurred during high stress days.";
                }
            } elseif ($highEvents === 0) {
                $insight .= " No high heat stress events were recorded during this period.";
            }
        }

        return view('dashboard._heat-stress', compact('summary', 'highEvents', 'highAvgHdep', 'peakTemp', 'tempMax', 'insight'));
    }

    public function buildDashboardData(?string $cageCode = null, int $mortalityDays = 1): array
    {
        $today = ReportingDateService::reportingDateString();
        $yesterday = ReportingDateService::reportingDate()->copy()->subDay()->toDateString();
        $thresholds = Setting::thresholds();

        $needsOnboarding = Setting::where('key', 'farm_grid_rows')->doesntExist()
            || Setting::where('key', 'farm_grid_cols')->doesntExist();

        // 'productionLogs' used to be eager-loaded here unconditionally — every
        // dashboard view (the most-visited page in the app) pulled every
        // production_logs row ever recorded, for every cage, into PHP memory,
        // just to filter it back down to "today's rows" per cage a few lines
        // below. That grows without bound for the life of the deployment.
        // Replaced with a single grouped SQL query below (today's per-cage
        // totals) plus, for the cage-scoped branch, two more small aggregate
        // queries (yesterday, lifetime) — nothing here loads a production_logs
        // row into PHP anymore; only pre-aggregated SUM/AVG/COUNT results do.
        $cagesQuery = Cage::with([
            'latestEnvironmentLog',
            'cageSlots.hardwareItems',
            'hardwareItems',
            'hens' => fn ($q) => $q->where('is_active', 1),
            'feedConsumptionLogs',
        ]);

        if ($cageCode) {
            $cagesQuery->where('cage_code', $cageCode);
        }

        $cages = $cagesQuery->get();
        $cageIds = $cages->pluck('id');

        $todayEggsByCage = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->whereDate('production_logs.log_date', $today)
            ->selectRaw('cage_slots.cage_id as cage_id, SUM(production_logs.egg_count) as total')
            ->groupBy('cage_slots.cage_id')
            ->pluck('total', 'cage_id');

        // Attach today's stats to each cage
        $cages->each(function ($cage) use ($todayEggsByCage) {
            $cage->today_eggs = (int) ($todayEggsByCage[$cage->id] ?? 0);
            $cage->hen_count = $cage->hens->count();
            // HDEP today = eggs collected again ÷ hens in the cage, as a percentage
            $cage->today_hdep = $cage->hen_count > 0 ? round($cage->today_eggs / $cage->hen_count * 100, 1) : 0;
            $cage->breed = $cage->hens->first()?->breed ?? '—';
            $cage->has_sensor = $cage->cageSlots->contains(fn ($s) => $s->hasBreakbeam()) || $cage->hasDht22();
            $cage->sensor_status = $this->sensorStatusText($cage);
        });

        // Total active hens
        if ($cageCode) {
            $totalHens = $cages->sum('hen_count');
            // HDEP today = eggs collected today ÷ all hens in the selected cages, as a percentage
            $todayHdep = $totalHens > 0 ? round($cages->sum('today_eggs') / $totalHens * 100, 1) : 0;

            $yesterdayStats = ProductionLog::query()
                ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
                ->whereIn('cage_slots.cage_id', $cageIds)
                ->whereDate('production_logs.log_date', $yesterday)
                ->selectRaw('COUNT(*) as log_count, AVG(production_logs.hdep) as avg_hdep, SUM(production_logs.egg_count) as total_eggs')
                ->first();
            $yesterdayHdep = $yesterdayStats->log_count ? round((float) $yesterdayStats->avg_hdep, 1) : 0;
            $eggsYesterday = (int) ($yesterdayStats->total_eggs ?? 0);

            $hdepDelta = round($todayHdep - $yesterdayHdep, 1);
            // Same source as today_eggs above (today's per-cage sum) — no
            // longer two independently-computed values with one as a
            // fallback for the other, since both used to derive from the
            // same eager-loaded collection anyway.
            $eggsToday = $cages->sum('today_eggs');
            $lifetimeEggs = ProductionLog::query()
                ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
                ->whereIn('cage_slots.cage_id', $cageIds)
                ->sum('production_logs.egg_count');
        } else {
            $totalHens = \App\Models\Hen::where('is_active', 1)->count();
            // Only logs still tied to a live cage_slot count toward global totals.
            // When a cage is deleted and its production history preserved, its
            // logs keep their data but their cage_slot_id is nulled, leaving
            // them un-attributable to any remaining cage. Excluding them here
            // keeps the global overview consistent with the per-cage branch
            // above (which joins cage_slots and therefore already skips them),
            // preventing deleted-cage eggs from inflating eggsToday/HDEP.
            $todayLogs = ProductionLog::whereDate('log_date', $today)->whereNotNull('cage_slot_id')->get();
            // HDEP today = eggs collected today ÷ all hens in the cages, as a percentage
            $todayHdep = $totalHens > 0 ? round($todayLogs->sum('egg_count') / $totalHens * 100, 1) : 0;
            $yesterdayLogs = ProductionLog::whereDate('log_date', $yesterday)->whereNotNull('cage_slot_id')->get();
            $yesterdayHdep = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
            $hdepDelta = round($todayHdep - $yesterdayHdep, 1);
            $eggsToday = $todayLogs->sum('egg_count')
                ?: $cages->sum(fn ($c) => $c->today_eggs);
            $eggsYesterday = $yesterdayLogs->sum('egg_count');
            $lifetimeEggs = ProductionLog::whereNotNull('cage_slot_id')->sum('egg_count');
        }

        $eggsDelta = round($eggsToday - $eggsYesterday);

        // Coop environment averages
        $latestEnv = EnvironmentalLog::whereIn('cage_id', $cages->pluck('id'))
            ->orderByDesc('recorded_at')
            ->limit($cages->count())
            ->get();
        $avgTemp = $latestEnv->count() ? round($latestEnv->avg('temperature_c'), 1) : null;
        $avgHum = $latestEnv->count() ? round($latestEnv->avg('humidity_pct'), 1) : null;

        // Feed today — sum ALL of today's logs per cage (not just the most recent)
        $feedPerHenKg = (float) Setting::get('feed_per_hen_daily', 0.12);

        $feedToday = FeedConsumptionLog::with('cage')
            ->whereDate('log_date', $today)
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->orderByDesc('log_date')
            ->get()
            ->groupBy(fn ($f) => $f->cage?->cage_code ?? 'Deleted Cage')
            ->map(fn ($g) => (object) [
                'cage' => $g->first()->cage,
                'feed_consumed_kg' => round($g->sum('feed_consumed_kg'), 2),
                'hen_count' => $g->first()->cage?->hens->where('is_active', 1)->count() ?? 0,
                'feed_target_kg' => round($g->first()->cage?->hens->where('is_active', 1)->count() ?? 0, 2) * $feedPerHenKg,
            ]);

        // Total feed consumed (lifetime)
        $totalFeedConsumed = FeedConsumptionLog::sum('feed_consumed_kg');

        // Feed & Nutrition summary cards
        $allBatches = FeedBatch::orderByDesc('date_received')->get();
        $avgCp = round($allBatches->avg('crude_protein') ?? 0, 1);

        $totalFeedWeek = FeedConsumptionLog::where('log_date', '>=', now()->subDays(7)->toDateString())
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->sum('feed_consumed_kg');

        $feedTodayKg = FeedConsumptionLog::where('log_date', $today)
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->sum('feed_consumed_kg');

        $feedWeekByCage = FeedConsumptionLog::where('log_date', '>=', now()->subDays(7)->toDateString())
            ->join('cages', 'feed_consumption_logs.cage_id', '=', 'cages.id')
            ->when($cageCode, fn ($q) => $q->where('cages.cage_code', $cageCode))
            ->selectRaw('cages.id as cage_id, cages.cage_code, ROUND(SUM(feed_consumption_logs.feed_consumed_kg), 2) as feed_kg')
            ->groupBy('cages.id', 'cages.cage_code')
            ->orderBy('cages.cage_code')
            ->get()
            ->map(function ($row) {
                $cage = Cage::find($row->cage_id);
                $row->color = $cage->color;
                $row->color_soft = $cage->color_soft;
                return $row;
            });

        $activeCagesCount = Cage::where('is_active', 1)->count();
        $avgFeedPerCage = $activeCagesCount
            ? round($totalFeedWeek / max($activeCagesCount, 1) / 7, 1)
            : 0;

        $totalFeedCostMonth = FeedConsumptionLog::where('feed_consumption_logs.log_date', '>=', now()->startOfMonth()->toDateString())
            ->join('feed_batches', 'feed_consumption_logs.feed_batch_id', '=', 'feed_batches.id')
            ->join('cages', 'feed_consumption_logs.cage_id', '=', 'cages.id')
            ->selectRaw('SUM(feed_consumption_logs.feed_consumed_kg * feed_batches.unit_cost) as total')
            ->whereNotNull('feed_batches.unit_cost')
            ->when($cageCode, fn ($q) => $q->where('cages.cage_code', $cageCode))
            ->value('total');

        $feedCostToday = FeedConsumptionLog::where('feed_consumption_logs.log_date', $today)
            ->join('feed_batches', 'feed_consumption_logs.feed_batch_id', '=', 'feed_batches.id')
            ->whereNotNull('feed_batches.unit_cost')
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->selectRaw('ROUND(SUM(feed_consumption_logs.feed_consumed_kg * feed_batches.unit_cost), 2) as total')
            ->value('total') ?? 0;

        $feedCostByCage = FeedConsumptionLog::where('feed_consumption_logs.log_date', '>=', now()->startOfMonth()->toDateString())
            ->join('feed_batches', 'feed_consumption_logs.feed_batch_id', '=', 'feed_batches.id')
            ->join('cages', 'feed_consumption_logs.cage_id', '=', 'cages.id')
            ->whereNotNull('feed_batches.unit_cost')
            ->when($cageCode, fn ($q) => $q->where('cages.cage_code', $cageCode))
            ->selectRaw('cages.id as cage_id, cages.cage_code, ROUND(SUM(feed_consumption_logs.feed_consumed_kg * feed_batches.unit_cost), 2) as cost')
            ->groupBy('cages.id', 'cages.cage_code')
            ->orderBy('cages.cage_code')
            ->get()
            ->map(function ($row) {
                $cage = Cage::find($row->cage_id);
                $row->color = $cage->color;
                $row->color_soft = $cage->color_soft;
                return $row;
            });

        // Mortality (today or period)
        $mortalityStartDate = $mortalityDays === 1
            ? $today
            : ReportingDateService::reportingDate()->copy()->subDays($mortalityDays - 1)->toDateString();
        $mortalityToday = MortalityLog::with('cage')
            ->whereDate('log_date', '>=', $mortalityStartDate)
            ->whereDate('log_date', '<=', $today)
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->get()
            ->groupBy(fn ($l) => $l->cage?->cage_code ?? 'Deleted Cage')
            ->map(fn ($g) => $g->sum('count'));
        $mortalityTodayTotal = $mortalityToday->sum();

        // Yesterday's mortality
        $yesterdayMortalityTotal = MortalityLog::whereDate('log_date', $yesterday)
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->sum('count');

        // Yesterday's feed consumed
        $yesterdayFeedTotal = FeedConsumptionLog::whereDate('log_date', $yesterday)
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->sum('feed_consumed_kg');

        // Live readings per cage
        $liveReadings = $cages->map(function ($cage) use ($thresholds) {
            $env = $cage->latestEnvironmentLog;
            if (! $env) {
                return null;
            }

            $status = EnvironmentStatusService::summary(
                $env->temperature_c,
                $env->humidity_pct,
                $thresholds
            );

            return (object) [
                'cage' => $cage->cage_code,
                'color' => $cage->color,
                'colorSoft' => $cage->colorSoft,
                'temp' => $env->temperature_c . '°C',
                'hum' => $env->humidity_pct . '%',
                'status' => $status,
            ];
        })->filter();

        // Daily data completeness — check which active cages have logged data
        // for the current reporting day across the 4 data types needed for
        // forecast_input_records sync.
        $activeCageCodes = $cages->pluck('cage_code')->values();
        $totalActiveCages = $activeCageCodes->count();

        $cagesWithEggs = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->join('cages', 'cages.id', '=', 'cage_slots.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->whereDate('production_logs.log_date', $today)
            ->distinct('cages.cage_code')
            ->count('cages.cage_code');

        // Env logs store recorded_at as calendar datetime, but we need to
        // match by reporting date. Fetch the range and convert in PHP.
        $envLogsForRange = EnvironmentalLog::query()
            ->join('cages', 'cages.id', '=', 'environmental_logs.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->whereBetween(DB::raw('DATE(environmental_logs.recorded_at)'), [$yesterday, now('Asia/Manila')->toDateString()])
            ->select('cages.cage_code', 'environmental_logs.recorded_at')
            ->get()
            ->map(fn ($r) => [
                'cage_code' => $r->cage_code,
                'reporting_date' => ReportingDateService::reportingDateFor($r->recorded_at)->toDateString(),
            ]);

        $cagesWithEnv = $envLogsForRange->where('reporting_date', $today)
            ->pluck('cage_code')->unique()->count();

        // Feed logs now store log_date as reporting date (like production_logs).
        $cagesWithFeed = FeedConsumptionLog::query()
            ->join('cages', 'cages.id', '=', 'feed_consumption_logs.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->whereDate('feed_consumption_logs.log_date', $today)
            ->distinct()
            ->pluck('cages.cage_code')
            ->count();

        $dataCompleteness = [
            'eggs'        => ['logged' => $cagesWithEggs, 'total' => $totalActiveCages, 'complete' => $cagesWithEggs >= $totalActiveCages && $totalActiveCages > 0],
            'environment' => ['logged' => $cagesWithEnv,  'total' => $totalActiveCages, 'complete' => $cagesWithEnv >= $totalActiveCages && $totalActiveCages > 0],
            'feed'        => ['logged' => $cagesWithFeed, 'total' => $totalActiveCages, 'complete' => $cagesWithFeed >= $totalActiveCages && $totalActiveCages > 0],
            'mortality'   => ['logged' => $mortalityTodayTotal, 'total' => 0, 'complete' => true], // optional, always OK
        ];

        $dayComplete = true;

        return compact(
            'cages', 'totalHens', 'todayHdep', 'hdepDelta',
            'eggsToday', 'eggsDelta', 'lifetimeEggs', 'avgTemp', 'avgHum', 'feedToday',
            'mortalityToday', 'mortalityTodayTotal', 'mortalityDays',
            'totalFeedConsumed', 'avgCp', 'avgFeedPerCage',             'totalFeedWeek', 'totalFeedCostMonth', 'feedTodayKg', 'feedCostToday',
            'feedWeekByCage', 'feedCostByCage', 'allBatches',
            'yesterdayHdep', 'eggsYesterday', 'yesterdayMortalityTotal', 'yesterdayFeedTotal',
            'liveReadings', 'today', 'dataCompleteness',
            'needsOnboarding', 'dayComplete'
        );
    }

    /**
     * Human-readable sensor status for a cage.
     * IR break-beam sensors have no heartbeat data, so "reporting" is based on
     * their active/faulty status; DHT22 recency comes from environmental logs.
     */
    private function sensorStatusText(Cage $cage): string
    {
        $assigned = $cage->cageSlots
            ->flatMap(fn ($s) => $s->hardwareItems)
            ->merge($cage->hardwareItems)
            ->whereIn('status', ['active', 'faulty']);

        $total = $assigned->count();
        if ($total === 0) {
            return 'No sensors installed';
        }

        $reporting = $assigned->where('health_state', 'online')->count();
        $text = "{$reporting} of {$total} sensor" . ($total > 1 ? 's' : '') . ' reporting';

        if ($cage->hasDht22()) {
            $env = $cage->latestEnvironmentLog;
            if ($env && $env->recorded_at->gt(ReportingDateService::now()->subMinutes(60))) {
                $text .= ' · DHT22 online — last reading ' . $env->recorded_at->diffForHumans();
            } elseif ($env) {
                $since = $env->recorded_at->isToday()
                    ? $env->recorded_at->format('g:i A')
                    : $env->recorded_at->format('M j, g:i A');
                $text .= " · DHT22 offline — no data since {$since}";
            } else {
                $text .= ' · DHT22 offline — no data yet';
            }
        }

        return $text;
    }
}
