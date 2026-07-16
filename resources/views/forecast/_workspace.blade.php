@php
    $cageColorMap = \App\Models\Cage::getColorMap();
    $cageColor = $scope === 'farm' ? '#102A4C' : ($cageColorMap[$cageCode] ?? '#6B7280');
    $scopeLabel = match($scope) {
        'farm' => 'Whole Farm',
        'breed' => $breed ?? '',
        default => $cageCode,
    };
    $showForecast = session('forecast_generated', false);
    $chartTitle = $showForecast ? 'HISTORICAL DATA VS FORECASTED EGG COUNT' : 'HISTORICAL EGG COUNT';
@endphp

<turbo-frame id="forecast-workspace">
<div class="space-y-5">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- ── Inputs Panel ── --}}
        <x-card>
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FORECAST INPUTS</div>
            <form method="POST" action="{{ route('forecast.generate') }}" id="forecastForm" data-turbo="false">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}" id="formScope">
                <input type="hidden" name="cage" value="{{ $cageCode }}" id="formCage">

                <label class="block text-sm text-[#333333] mb-2">Scope</label>
                <div class="flex flex-col gap-2 mb-4">
                    <a href="{{ route('forecast', ['scope'=>'farm','horizon'=>$horizon]) }}" data-turbo-frame="forecast-workspace" data-turbo-action="advance"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'farm' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="globe" class="w-4 h-4 shrink-0"></i> Whole Farm
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'cage','cage'=>$cageCode,'horizon'=>$horizon]) }}" data-turbo-frame="forecast-workspace" data-turbo-action="advance"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'cage' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="box" class="w-4 h-4 shrink-0"></i> Per Cage
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'breed','breed'=>$allBreeds->first() ?? 'ISA Brown','horizon'=>$horizon]) }}" data-turbo-frame="forecast-workspace" data-turbo-action="advance"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'breed' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="bird" class="w-4 h-4 shrink-0"></i> Per Breed
                    </a>
                </div>

                @if($scope === 'cage')
                <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                <select name="cage" onchange="this.form.submit()"
                        class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allCages as $c)
                    <option value="{{ $c }}" {{ $c === $cageCode ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#333333]">{{ $cageCode }}</span></p>

                @elseif($scope === 'breed')
                <label class="block text-sm text-[#333333] mb-2">Select Breed</label>
                <select name="breed" onchange="this.form.submit()"
                        class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allBreeds as $b)
                    <option value="{{ $b }}" {{ $breed === $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#002D5E]">{{ $breed }}</span></p>

                @else
                <input type="hidden" name="cage" value="{{ $cageCode }}">
                <p class="text-xs text-[#6B7280] mb-4">Forecasting: <span class="font-medium text-[#333333]">Whole Farm</span></p>
                @endif

                <label class="block text-sm text-[#333333] mb-2">Forecast horizon</label>
                <div class="flex gap-4 mb-5">
                    @foreach([7,14,30] as $h)
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="radio" name="horizon" value="{{ $h }}" {{ $horizon == $h ? 'checked' : '' }} class="accent-[#002D5E]">
                        {{ $h }} days
                    </label>
                    @endforeach
                </div>

                @can('admin')
                <button type="submit" id="generateForecastBtn" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <span id="btnText">Generate Forecast</span>
                </button>
                @endcan
            </form>
        </x-card>

        {{-- ── Chart Panel ── --}}
        <div class="xl:col-span-2 bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">{{ $chartTitle }} — {{ $scopeLabel }}</div>
            <div class="relative h-64 w-full" style="height: 16rem;">
                <canvas id="forecastChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Model Metrics ── --}}
    @if($showForecast && (!empty($metrics) || $recommendedModel))
    @php
        $metrics = $metrics ?? [];
        $recommended = $recommendedModel ?? null;
        $sarima = $metrics['sarima'] ?? null;
        $xgboost = $metrics['xgboost'] ?? null;

        $maeWinner = null;
        $rmseWinner = null;
        $mapeWinner = null;
        if ($sarima && $xgboost) {
            $maeWinner = ($sarima['MAE'] ?? PHP_FLOAT_MAX) <= ($xgboost['MAE'] ?? PHP_FLOAT_MAX) ? 'SARIMA' : 'XGBoost';
            $rmseWinner = ($sarima['RMSE'] ?? PHP_FLOAT_MAX) <= ($xgboost['RMSE'] ?? PHP_FLOAT_MAX) ? 'SARIMA' : 'XGBoost';
            $mapeWinner = ($sarima['MAPE'] ?? PHP_FLOAT_MAX) <= ($xgboost['MAPE'] ?? PHP_FLOAT_MAX) ? 'SARIMA' : 'XGBoost';
        }
    @endphp
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">MODEL COMPARISON</div>

        @if($recommended)
        <div class="flex items-center gap-3 p-4 rounded-lg bg-[#D5E8D4] text-[#1F5F35] mb-5">
            <div class="w-10 h-10 rounded-full bg-[#1F5F35]/10 flex items-center justify-center">
                <i data-lucide="award" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-[#1F5F35]/80">Suggested Model</div>
                <div class="text-lg font-semibold">{{ $recommended }}</div>
            </div>
        </div>
        @endif

        @if($sarima || $xgboost)
        <div class="overflow-hidden rounded-lg border border-[#D9D9D9]">
            <table class="w-full text-sm">
                <thead class="bg-[#F9F9F7] border-b border-[#D9D9D9]">
                    <tr>
                        <th class="text-left text-xs font-medium text-[#6B7280] px-5 py-3">Metric</th>
                        <th class="text-right text-xs font-medium text-[#6B7280] px-5 py-3">SARIMA</th>
                        <th class="text-right text-xs font-medium text-[#6B7280] px-5 py-3">XGBoost Regression</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#F0F0F0]">
                        <td class="px-5 py-3.5 text-[#333333]">MAE</td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $maeWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($sarima['MAE'] ?? 0, 2) }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $maeWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($xgboost['MAE'] ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr class="border-b border-[#F0F0F0]">
                        <td class="px-5 py-3.5 text-[#333333]">RMSE</td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $rmseWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($sarima['RMSE'] ?? 0, 2) }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $rmseWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($xgboost['RMSE'] ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3.5 text-[#333333]">MAPE</td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $mapeWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($sarima['MAPE'] ?? 0, 2) }}%
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono {{ $mapeWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($xgboost['MAPE'] ?? 0, 2) }}%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-[#6B7280] mt-2">Lower values are better. Highlighted cells indicate the best performing model for each metric.</p>
        @endif
    </div>
    @endif
</div>

<script>
(function() {
    const historical = @json($historical->map(fn($l) => ['date'=> is_object($l->log_date) ? $l->log_date->format('Y-m-d') : $l->log_date,'egg_count'=>$l->egg_count]));
    const showForecast = @json($showForecast);
    const forecasts  = showForecast
        ? @json($forecasts->map(fn($f) => ['date'=> is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date,'egg_count'=>(int) $f->predicted_egg_count]))
        : [];
    const cageColor  = '{{ $cageColor }}';

    const recentHistorical = historical;
    const histLabels = recentHistorical.map((h) => {
        const [y, m, d] = h.date.split('-').map(Number);
        const date = new Date(Date.UTC(y, m - 1, d));
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
    });
    const fcLabels   = forecasts.map((f) => {
        const [y, m, d] = f.date.split('-').map(Number);
        const date = new Date(Date.UTC(y, m - 1, d));
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
    });
    const allLabels  = showForecast ? [...histLabels, ...fcLabels] : histLabels;

    const histData = showForecast
        ? [...recentHistorical.map(h => h.egg_count), ...Array(fcLabels.length).fill(null)]
        : recentHistorical.map(h => h.egg_count);
    const fcData   = showForecast
        ? [...Array(histLabels.length).fill(null), ...forecasts.map(f => f.egg_count)]
        : [];

    function setChartError(message) {
        const canvas = document.getElementById('forecastChart');
        if (!canvas) return;
        const wrapper = canvas.parentElement;
        if (!wrapper) return;
        const errorId = 'forecastChartError';
        let errorEl = document.getElementById(errorId);
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.id = errorId;
            errorEl.className = 'absolute inset-0 flex items-center justify-center text-xs text-red-600 bg-red-50/80 rounded';
            wrapper.appendChild(errorEl);
        }
        errorEl.textContent = message || 'Unable to load chart.';
    }

    function clearChartError() {
        const errorEl = document.getElementById('forecastChartError');
        if (errorEl) errorEl.remove();
    }

    function initForecastChart() {
        const canvas = document.getElementById('forecastChart');
        if (!canvas) {
            console.warn('[ForecastChart] Canvas element not found.');
            return;
        }

        if (typeof Chart === 'undefined') {
            console.warn('[ForecastChart] Chart.js not loaded yet.');
            return;
        }

        if (window.forecastChartInstance) {
            window.forecastChartInstance.destroy();
        }

        if (!recentHistorical || recentHistorical.length === 0) {
            console.warn('[ForecastChart] No historical data available.', historical);
            setChartError('No historical data to display.');
            return;
        }

        clearChartError();

        const datasets = [
            {
                label: 'Historical',
                data: histData,
                borderColor: '#333333',
                backgroundColor: 'transparent',
                tension: 0.3,
                pointRadius: 3,
                borderWidth: 2,
            }
        ];

        if (showForecast) {
            datasets.push({
                label: 'Forecast',
                data: fcData,
                borderColor: cageColor,
                backgroundColor: cageColor + '22',
                tension: 0.3,
                borderDash: [5,3],
                pointRadius: 3,
                fill: true,
                borderWidth: 2,
            });
        }

        console.log('[ForecastChart] Initializing with labels:', allLabels, 'datasets:', datasets);

        try {
            window.forecastChartInstance = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: allLabels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } }
                    },
                    scales: {
                        x: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 45 } },
                        y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, min: 0, beginAtZero: true },
                    }
                }
            });
            console.log('[ForecastChart] Chart initialized successfully.');
        } catch (e) {
            console.error('[ForecastChart] Failed to initialize chart:', e);
            setChartError('Chart failed to render.');
        }
    }

    function ensureForecastChart() {
        if (typeof Chart !== 'undefined') {
            initForecastChart();
        } else {
            console.warn('[ForecastChart] Chart.js not available, polling...');
            const checkChart = setInterval(function() {
                if (typeof Chart !== 'undefined') {
                    clearInterval(checkChart);
                    console.log('[ForecastChart] Chart.js became available.');
                    initForecastChart();
                }
            }, 100);
            setTimeout(function() {
                clearInterval(checkChart);
                if (typeof Chart === 'undefined') {
                    console.error('[ForecastChart] Chart.js failed to load within 10 seconds.');
                    setChartError('Chart library failed to load. Please check your connection.');
                }
            }, 10000);
        }
    }

    document.addEventListener('turbo:load', ensureForecastChart);
    document.addEventListener('DOMContentLoaded', ensureForecastChart);
    window.addEventListener('load', ensureForecastChart);
    ensureForecastChart();
})();
</script>
</turbo-frame>
