<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $cageCode = $request->get('cage', 'CAGE-A');
        $horizon  = (int) $request->get('horizon', 7);

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        // Historical data (last 14 days)
        $historical = ProductionLog::where('cage_id', $cage->id)
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        // Forecast for the selected cage
        $forecasts = Forecast::where('cage_id', $cage->id)
            ->where('forecast_date', now()->toDateString())
            ->orderBy('target_date')
            ->limit($horizon)
            ->get();

        // If no forecasts, generate simple linear projection
        if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
            $forecasts = $this->generateForecast($cage, $historical, $horizon);
        }

        $allCages = Cage::orderBy('cage_code')->get();

        return view('forecast', compact(
            'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'allCages'
        ));
    }

    public function generate(Request $request)
    {
        $cageCode = $request->get('cage', 'CAGE-A');
        $horizon  = (int) $request->get('horizon', 7);

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = ProductionLog::where('cage_id', $cage->id)
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        // Delete existing forecasts for today
        Forecast::where('cage_id', $cage->id)
            ->where('forecast_date', now()->toDateString())
            ->delete();

        $this->generateForecast($cage, $historical, $horizon, true);

        return redirect()->route('forecast', ['cage' => $cageCode, 'horizon' => $horizon])
            ->with('success', 'Forecast generated.');
    }

    private function generateForecast($cage, $historical, int $horizon, bool $save = false): \Illuminate\Support\Collection
    {
        $avgHdep = $historical->avg('hdep') ?? 85.0;
        $forecasts = collect();
        $today = now()->toDateString();

        for ($i = 1; $i <= $horizon; $i++) {
            $targetDate = now()->addDays($i)->toDateString();
            $variation  = (($i % 3) - 1) * 0.3;
            $predicted  = round(min(100, max(0, $avgHdep + $variation)), 2);

            $forecast = new Forecast([
                'cage_id'        => $cage->id,
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
