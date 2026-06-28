<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $scope    = $request->get('scope', 'cage');
        $cageCode = $request->get('cage', 'CAGE-A');
        $horizon  = (int) $request->get('horizon', 7);
        $allCages = Cage::orderBy('cage_code')->get();

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::whereNull('cage_id')
                ->where('forecast_date', now()->toDateString())
                ->orderBy('target_date')
                ->limit($horizon)
                ->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast(null, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'allCages'))
                ->with('cage', null);
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = ProductionLog::where('cage_id', $cage->id)
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        $forecasts = Forecast::where('cage_id', $cage->id)
            ->where('forecast_date', now()->toDateString())
            ->orderBy('target_date')
            ->limit($horizon)
            ->get();

        if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
            $forecasts = $this->generateForecast($cage, $historical, $horizon);
        }

        return view('forecast', compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'allCages'));
    }

    public function generate(Request $request)
    {
        $scope    = $request->get('scope', 'cage');
        $cageCode = $request->get('cage', 'CAGE-A');
        $horizon  = (int) $request->get('horizon', 7);

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();

            Forecast::whereNull('cage_id')
                ->where('forecast_date', now()->toDateString())
                ->delete();

            $this->generateForecast(null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'farm', 'horizon' => $horizon])
                ->with('success', 'Whole-farm forecast generated.');
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = ProductionLog::where('cage_id', $cage->id)
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        Forecast::where('cage_id', $cage->id)
            ->where('forecast_date', now()->toDateString())
            ->delete();

        $this->generateForecast($cage, $historical, $horizon, true);

        return redirect()->route('forecast', ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon])
            ->with('success', 'Forecast generated.');
    }

    private function farmHistorical(): Collection
    {
        return ProductionLog::selectRaw('log_date, SUM(egg_count) as egg_count, SUM(hen_count) as hen_count')
            ->groupBy('log_date')
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->map(function ($row) {
                $row->hdep = $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0;
                return $row;
            })
            ->reverse()
            ->values();
    }

    private function generateForecast(?Cage $cage, Collection $historical, int $horizon, bool $save = false): Collection
    {
        $avgHdep = $historical->avg('hdep') ?? 85.0;
        $forecasts = collect();
        $today = now()->toDateString();

        for ($i = 1; $i <= $horizon; $i++) {
            $targetDate = now()->addDays($i)->toDateString();
            $variation  = (($i % 3) - 1) * 0.3;
            $predicted  = round(min(100, max(0, $avgHdep + $variation)), 2);

            $forecast = new Forecast([
                'cage_id'        => $cage?->id,
                'forecast_date'  => $today,
                'target_date'    => $targetDate,
                'predicted_hdep' => $predicted,
            ]);

            if ($save) $forecast->save();
            $forecasts->push($forecast);
        }

        return $forecasts;
    }
}
