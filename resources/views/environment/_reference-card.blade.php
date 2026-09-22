@php
    // Fixed optimal reference range (config/environment.php) — separate from
    // the operator-configured alert threshold ($thresholds, edited via the
    // Alert Thresholds modal). Reused as-is by EnvironmentStatusService's
    // existing temp/hum status methods, which only need a temp_min/temp_max
    // (or hum_min/hum_max) shaped array.
    $optimalTempRange = ['temp_min' => $optimal['optimal_temp_min'], 'temp_max' => $optimal['optimal_temp_max']];
    $optimalHumRange  = ['hum_min' => $optimal['optimal_humidity_min'], 'hum_max' => $optimal['optimal_humidity_max']];

    $tempStatus = $avgTemp !== null ? \App\Services\EnvironmentStatusService::tempStatus((float) $avgTemp, $optimalTempRange) : null;
    $humStatus  = $avgHum !== null ? \App\Services\EnvironmentStatusService::humStatus((float) $avgHum, $optimalHumRange) : null;
@endphp

<x-card header="IR Reference: Current vs. Optimal Range" class="mb-6">
    <p class="text-xs mb-4" style="color: #6B7280;">Coop-wide average against a fixed optimal reference range (not the alert threshold you configure) — makes it obvious at a glance whether current conditions are ideal.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Temperature --}}
        <div class="rounded-lg border p-4" style="border-color: #e6e6e6;">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold tracking-wider uppercase" style="color: #6B7280;">Temperature</span>
                @if($tempStatus)
                <x-status-badge :status="$tempStatus" type="sensor" />
                @endif
            </div>
            <div class="flex items-end gap-6">
                <div>
                    <div class="text-[11px] uppercase tracking-wider" style="color: #a39e98;">Current</div>
                    <div class="text-2xl font-bold" style="color: #1f1f1f;">{{ $avgTemp !== null ? number_format($avgTemp, 1) . '°C' : '—' }}</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider" style="color: #a39e98;">Optimal</div>
                    <div class="text-base font-medium" style="color: #6B7280;">{{ number_format($optimal['optimal_temp_min'], 1) }}–{{ number_format($optimal['optimal_temp_max'], 1) }}°C</div>
                </div>
            </div>
        </div>

        {{-- Humidity --}}
        <div class="rounded-lg border p-4" style="border-color: #e6e6e6;">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold tracking-wider uppercase" style="color: #6B7280;">Humidity</span>
                @if($humStatus)
                <x-status-badge :status="$humStatus" type="sensor" />
                @endif
            </div>
            <div class="flex items-end gap-6">
                <div>
                    <div class="text-[11px] uppercase tracking-wider" style="color: #a39e98;">Current</div>
                    <div class="text-2xl font-bold" style="color: #1f1f1f;">{{ $avgHum !== null ? number_format($avgHum, 1) . '%' : '—' }}</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider" style="color: #a39e98;">Optimal</div>
                    <div class="text-base font-medium" style="color: #6B7280;">{{ number_format($optimal['optimal_humidity_min'], 1) }}–{{ number_format($optimal['optimal_humidity_max'], 1) }}%</div>
                </div>
            </div>
        </div>
    </div>
</x-card>
