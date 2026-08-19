<?php

namespace App\Http\Controllers;

use App\Models\EnvironmentalLog;
use App\Models\ProductionLog;
use App\Services\ReportingDateService;
use Illuminate\Http\JsonResponse;

class MobileAppController extends Controller
{
    public function dashboardStatus(): JsonResponse
    {
        $today = ReportingDateService::reportingDateString();

        $env = EnvironmentalLog::whereDate('recorded_at', $today)
            ->selectRaw('AVG(temperature_c) as avg_temp, AVG(humidity_pct) as avg_humidity')
            ->first();

        $prod = ProductionLog::whereDate('log_date', $today)
            ->sum('egg_count');

        return response()->json([
            'temperature' => $env && $env->avg_temp ? round((float) $env->avg_temp, 1) : 0.0,
            'humidity'    => $env && $env->avg_humidity ? round((float) $env->avg_humidity, 1) : 0.0,
            'egg_count'   => (int) $prod,
        ]);
    }
}
