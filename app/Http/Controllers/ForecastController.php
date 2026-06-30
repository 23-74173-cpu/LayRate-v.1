<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\Hen;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $scope     = $request->get('scope', 'cage');
        $cageCode  = $request->get('cage', 'CAGE-A');
        $breed     = $request->get('breed');
        $horizon   = (int) $request->get('horizon', 7);
        $allCages  = Cage::orderBy('cage_code')->get();
        $allBreeds = Hen::distinct()->pluck('breed')->filter()->sort()->values();

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast(null, null, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'allCages', 'allBreeds'))
                ->with('label', 'Whole Farm');
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->breedHistorical($breed);
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast(null, $breed, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'allCages', 'allBreeds'))
                ->with('label', $breed);
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = $cage->productionLogs()
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        $forecasts = Forecast::where('forecast_date', now()->toDateString())
            ->where('cage_id', $cage->id)->whereNull('breed')
            ->orderBy('target_date')->limit($horizon)->get();

        if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
            $forecasts = $this->generateForecast($cage, null, $historical, $horizon);
        }

        return view('forecast', compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'allCages', 'allBreeds'));
    }

    public function generate(Request $request)
    {
        $scope     = $request->get('scope', 'cage');
        $cageCode  = $request->get('cage', 'CAGE-A');
        $breed     = $request->get('breed');
        $horizon   = (int) $request->get('horizon', 7);

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();

            Forecast::whereNull('cage_id')->whereNull('breed')
                ->where('forecast_date', now()->toDateString())->delete();

            $this->generateForecast(null, null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'farm', 'horizon' => $horizon])
                ->with('success', 'Whole-farm forecast generated.');
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->breedHistorical($breed);

            Forecast::whereNull('cage_id')->where('breed', $breed)
                ->where('forecast_date', now()->toDateString())->delete();

            $this->generateForecast(null, $breed, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'breed', 'breed' => $breed, 'horizon' => $horizon])
                ->with('success', "{$breed} forecast generated.");
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = $cage->productionLogs()
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        Forecast::where('cage_id', $cage->id)->whereNull('breed')
            ->where('forecast_date', now()->toDateString())->delete();

        $this->generateForecast($cage, null, $historical, $horizon, true);

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
            ->map(fn($row) => tap(clone $row, fn($r) => $r->hdep = $r->hen_count > 0 ? round(($r->egg_count / $r->hen_count) * 100, 2) : 0))
            ->reverse()
            ->values();
    }

    private function breedHistorical(string $breed): Collection
    {
        return ProductionLog::selectRaw('production_logs.log_date, SUM(production_logs.egg_count) as egg_count, SUM(production_logs.hen_count) as hen_count')
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->join('hens', 'hens.cage_slot_id', '=', 'cage_slots.id')
            ->whereRaw('hens.id = (SELECT id FROM hens h2 WHERE h2.cage_slot_id = cage_slots.id AND h2.is_active = 1 LIMIT 1)')
            ->where('hens.breed', $breed)
            ->groupBy('production_logs.log_date')
            ->orderByDesc('production_logs.log_date')
            ->limit(14)
            ->get()
            ->map(fn($row) => tap(clone $row, fn($r) => $r->hdep = $r->hen_count > 0 ? round(($r->egg_count / $r->hen_count) * 100, 2) : 0))
            ->reverse()
            ->values();
    }

    private function generateForecast(?Cage $cage, ?string $breed, Collection $historical, int $horizon, bool $save = false): Collection
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
                'breed'          => $breed,
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
