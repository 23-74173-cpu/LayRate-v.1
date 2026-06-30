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
        $row      = $request->filled('row') ? (int) $request->get('row') : null;
        $horizon  = (int) $request->get('horizon', 7);
        $allCages = Cage::orderBy('cage_code')->get();

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::whereNull('cage_id')->whereNull('row_number')
                ->where('forecast_date', now()->toDateString())
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast(null, null, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cageCode', 'row', 'horizon', 'historical', 'forecasts', 'allCages'))
                ->with('cage', null);
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        if ($scope === 'row' && $row !== null) {
            $historical = $this->rowHistorical($cage, $row);
            $forecasts  = Forecast::where('cage_id', $cage->id)->where('row_number', $row)
                ->where('forecast_date', now()->toDateString())
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $forecasts = $this->generateForecast($cage, $row, $historical, $horizon);
            }

            return view('forecast', compact('scope', 'cage', 'cageCode', 'row', 'horizon', 'historical', 'forecasts', 'allCages'));
        }

        $historical = $this->cageHistorical($cage);
        $forecasts  = Forecast::where('cage_id', $cage->id)->whereNull('row_number')
            ->where('forecast_date', now()->toDateString())
            ->orderBy('target_date')->limit($horizon)->get();

        if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
            $forecasts = $this->generateForecast($cage, null, $historical, $horizon);
        }

        return view('forecast', compact('scope', 'cage', 'cageCode', 'row', 'horizon', 'historical', 'forecasts', 'allCages'));
    }

    public function generate(Request $request)
    {
        $scope    = $request->get('scope', 'cage');
        $cageCode = $request->get('cage', 'CAGE-A');
        $row      = $request->filled('row') ? (int) $request->get('row') : null;
        $horizon  = (int) $request->get('horizon', 7);

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            Forecast::whereNull('cage_id')->whereNull('row_number')->where('forecast_date', now()->toDateString())->delete();
            $this->generateForecast(null, null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'farm', 'horizon' => $horizon])
                ->with('success', 'Whole-farm forecast generated.');
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        if ($scope === 'row' && $row !== null) {
            abort_if($row < 1 || $row > $cage->rows, 422, 'Invalid row number.');
            $historical = $this->rowHistorical($cage, $row);
            Forecast::where('cage_id', $cage->id)->where('row_number', $row)->where('forecast_date', now()->toDateString())->delete();
            $this->generateForecast($cage, $row, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'row', 'cage' => $cageCode, 'row' => $row, 'horizon' => $horizon])
                ->with('success', 'Per-row forecast generated.');
        }

        $historical = $this->cageHistorical($cage);
        Forecast::where('cage_id', $cage->id)->whereNull('row_number')->where('forecast_date', now()->toDateString())->delete();
        $this->generateForecast($cage, null, $historical, $horizon, true);

        return redirect()->route('forecast', ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon])
            ->with('success', 'Forecast generated.');
    }

    private function farmHistorical(): Collection
    {
        return ProductionLog::selectRaw('log_date, SUM(egg_count) as egg_count, SUM(hen_count) as hen_count')
            ->groupBy('log_date')->orderByDesc('log_date')->limit(14)->get()
            ->map(fn($row) => $this->withHdep($row))->reverse()->values();
    }

    private function cageHistorical(Cage $cage): Collection
    {
        return ProductionLog::join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->where('cage_slots.cage_id', $cage->id)
            ->selectRaw('production_logs.log_date, SUM(production_logs.egg_count) as egg_count, SUM(production_logs.hen_count) as hen_count')
            ->groupBy('production_logs.log_date')
            ->orderByDesc('production_logs.log_date')
            ->limit(14)
            ->get()
            ->map(fn($row) => $this->withHdep($row))->reverse()->values();
    }

    private function rowHistorical(Cage $cage, int $rowNumber): Collection
    {
        return ProductionLog::join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->where('cage_slots.cage_id', $cage->id)
            ->where('cage_slots.row_number', $rowNumber)
            ->selectRaw('production_logs.log_date, SUM(production_logs.egg_count) as egg_count, SUM(production_logs.hen_count) as hen_count')
            ->groupBy('production_logs.log_date')
            ->orderByDesc('production_logs.log_date')
            ->limit(14)
            ->get()
            ->map(fn($row) => $this->withHdep($row))->reverse()->values();
    }

    private function withHdep($row)
    {
        $row->hdep = $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0;
        return $row;
    }

    private function generateForecast(?Cage $cage, ?int $row, Collection $historical, int $horizon, bool $save = false): Collection
    {
        $avgHdep   = $historical->avg('hdep') ?? 85.0;
        $forecasts = collect();
        $today     = now()->toDateString();

        for ($i = 1; $i <= $horizon; $i++) {
            $targetDate = now()->addDays($i)->toDateString();
            $variation  = (($i % 3) - 1) * 0.3;
            $predicted  = round(min(100, max(0, $avgHdep + $variation)), 2);

            $forecast = new Forecast([
                'cage_id'        => $cage?->id,
                'row_number'     => $row,
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
