@extends('layouts.app')
@section('title', 'Forecast')

@section('content')
<div class="space-y-5">

    @php
        $cageColorMap = ['CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A'];
        $cageColor = $scope === 'farm' ? '#102A4C' : ($cageColorMap[$cageCode] ?? '#2D7D46');
        $scopeLabel = match($scope) {
            'farm' => 'Whole Farm',
            'breed' => $breed ?? '',
            default => $cageCode,
        };
        $showForecast = session('forecast_generated', false);
        $chartTitle = $showForecast ? 'HISTORICAL DATA VS FORECASTED EGG COUNT' : 'HISTORICAL EGG COUNT';
    @endphp

    <x-page-header title="Forecast" subtitle="Project egg production based on historical egg count trends" />

    @if(!($hasEnoughData ?? true))
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
        <div>
            <div class="text-sm font-medium text-amber-800">Insufficient forecast data</div>
            <div class="text-sm text-amber-700 mt-1">
                The forecast input table must contain at least 90 days of production records before generating a forecast.
                Please download the forecast input sheet, fill it out, and import the data.
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Inputs Panel ── --}}
        <x-card>
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FORECAST INPUTS</div>
            <form method="POST" action="{{ route('forecast.generate') }}" id="forecastForm" data-turbo="false">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}" id="formScope">
                <input type="hidden" name="cage" value="{{ $cageCode }}" id="formCage">

                <label class="block text-sm text-[#333333] mb-2">Scope</label>
                <div class="flex flex-col gap-2 mb-4">
                    <a href="{{ route('forecast', ['scope'=>'farm','horizon'=>$horizon]) }}"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'farm' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="globe" class="w-4 h-4 shrink-0"></i> Whole Farm
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'cage','cage'=>$cageCode,'horizon'=>$horizon]) }}"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'cage' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="box" class="w-4 h-4 shrink-0"></i> Per Cage
                    </a>
                    <a href="{{ route('forecast', ['scope'=>'breed','breed'=>$allBreeds->first() ?? 'ISA Brown','horizon'=>$horizon]) }}"
                       class="flex items-center justify-center gap-2 overflow-hidden py-2 rounded-lg text-sm border whitespace-nowrap {{ $scope === 'breed' ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <i data-lucide="bird" class="w-4 h-4 shrink-0"></i> Per Breed
                    </a>
                </div>

                @if($scope === 'cage')
                <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                <select name="cage" onchange="this.form.submit()"
                        class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allCages as $c)
                    <option value="{{ $c->cage_code }}" {{ $c->cage_code === $cageCode ? 'selected' : '' }}>{{ $c->cage_code }}</option>
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

                <button type="submit" id="generateForecastBtn" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <span id="btnText">Generate Forecast</span>
                </button>

                <a href="{{ route('forecast.template') }}" class="mt-3 w-full flex items-center justify-center gap-2 border border-[#D9D9D9] text-[#333333] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    <i data-lucide="download" class="w-4 h-4 shrink-0"></i> Download Forecast Input Sheet
                </a>
            </form>

            {{-- ── Import Production Data ── --}}
            <div class="mt-5 pt-5 border-t border-[#F0F0F0]">
                <div class="text-xs tracking-wider text-[#6B7280] mb-3">IMPORT DATA</div>

                {{-- Inline import feedback messages --}}
                <div id="importFeedback" class="hidden mb-3 rounded-lg p-3 text-sm">
                    <div class="flex items-start gap-2">
                        <i data-lucide="" id="importFeedbackIcon" class="w-4 h-4 shrink-0 mt-0.5"></i>
                        <span id="importFeedbackMessage"></span>
                    </div>
                </div>

                <form method="POST" action="{{ route('forecast.import') }}" id="forecastImportForm" enctype="multipart/form-data">
                    @csrf
                    <label class="block text-sm text-[#333333] mb-2">Forecast input file (.xlsx)</label>
                    <input type="file" name="forecast_file" accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                           class="w-full text-sm text-[#333333] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-[#F5F6F8] file:text-[#002D5E] hover:file:bg-[#E5E7EB] border border-[#D9D9D9] rounded-lg cursor-pointer mb-3">
                    @error('forecast_file')
                    <p class="text-xs text-red-600 mb-2">{{ $message }}</p>
                    @enderror
                    <button type="submit" id="importForecastBtn" class="w-full bg-[#2D7D46] text-white py-2.5 rounded-lg text-sm hover:bg-[#226537] transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <span id="importBtnText">Import Production Data</span>
                    </button>
                </form>
            </div>
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
                        <th class="text-left text-xs font-medium text-[#6B7280] px-4 py-3">Metric</th>
                        <th class="text-right text-xs font-medium text-[#6B7280] px-4 py-3">SARIMA</th>
                        <th class="text-right text-xs font-medium text-[#6B7280] px-4 py-3">XGBoost Regression</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#F0F0F0]">
                        <td class="px-4 py-3 text-[#333333]">MAE</td>
                        <td class="px-4 py-3 text-right font-mono {{ $maeWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($sarima['MAE'] ?? 0, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono {{ $maeWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($xgboost['MAE'] ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr class="border-b border-[#F0F0F0]">
                        <td class="px-4 py-3 text-[#333333]">RMSE</td>
                        <td class="px-4 py-3 text-right font-mono {{ $rmseWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($sarima['RMSE'] ?? 0, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono {{ $rmseWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($xgboost['RMSE'] ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-[#333333]">MAPE</td>
                        <td class="px-4 py-3 text-right font-mono {{ $mapeWinner === 'SARIMA' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
                            {{ number_format($sarima['MAPE'] ?? 0, 2) }}%
                        </td>
                        <td class="px-4 py-3 text-right font-mono {{ $mapeWinner === 'XGBoost' ? 'font-semibold text-[#1F5F35] bg-[#D5E8D4]/30' : 'text-[#333333]' }}">
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

    {{-- ── Forecast Table ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Date</th>
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Predicted Eggs</th>
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Confidence</th>
                </tr>
            </thead>
            <tbody>
                @forelse($forecasts as $f)
                <tr class="border-b border-[#F0F0F0] hover:bg-[#F5F6F8]">
                    <td class="px-6 py-3 text-sm text-[#333333]">
                        <span class="font-medium">{{ $f->target_date->format('l') }}</span>
                        <span class="text-xs text-[#6B7280] ml-1">{{ $f->target_date->format('M j') }}</span>
                    </td>
                    <td class="px-6 py-3 text-sm text-[#333333]">{{ number_format($f->predicted_egg_count,0) }}</td>
                    <td class="px-6 py-3">
                        <span class="text-xs px-2.5 py-1 rounded-full" style="background:{{ $f->confidenceColor }}">
                            {{ $f->confidence }}%
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-[#6B7280]">
                    No forecast generated yet. Click "Generate Forecast" above.
                </td></tr>
                @endforelse
            </tbody>
        </table>

</div>

{{-- Forecast generation loading overlay --}}
<div id="forecastLoadingOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl p-8 max-w-sm w-full mx-4 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#002D5E]/10 mb-4">
            <svg class="animate-spin h-6 w-6 text-[#002D5E]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-[#333333] mb-1">Generating Forecast</h3>
        <p class="text-sm text-[#6B7280] mb-4">Please wait while the model trains and produces predictions...</p>
        <div class="w-full bg-[#F0F0F0] rounded-full h-2.5 overflow-hidden">
            <div id="forecastProgressBar" class="bg-[#002D5E] h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <p id="forecastProgressText" class="text-xs text-[#6B7280] mt-2">0%</p>
    </div>
</div>

{{-- Forecast import loading overlay --}}
<div id="forecastImportLoadingOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl p-8 max-w-sm w-full mx-4 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#2D7D46]/10 mb-4">
            <svg class="animate-spin h-6 w-6 text-[#2D7D46]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-[#333333] mb-1">Importing Production Data</h3>
        <p class="text-sm text-[#6B7280] mb-4">Please wait while the spreadsheet is being imported...</p>
        <div class="w-full bg-[#F0F0F0] rounded-full h-2.5 overflow-hidden">
            <div id="importProgressBar" class="bg-[#2D7D46] h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <p id="importProgressText" class="text-xs text-[#6B7280] mt-2">Uploading: 0%</p>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
const historical = @json($historical->map(fn($l) => ['date'=> is_object($l->log_date) ? $l->log_date->format('Y-m-d') : $l->log_date,'egg_count'=>$l->egg_count]));
const showForecast = @json($showForecast);
const forecasts  = showForecast
    ? @json($forecasts->map(fn($f) => ['date'=> is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date,'egg_count'=>(int) $f->predicted_egg_count]))
    : [];
const cageColor  = '{{ $cageColor }}';

const recentHistorical = historical.slice(-14);
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

document.addEventListener('turbo:load', function() {
    const form = document.getElementById('forecastForm');
    const overlay = document.getElementById('forecastLoadingOverlay');
    const progressBar = document.getElementById('forecastProgressBar');
    const progressText = document.getElementById('forecastProgressText');
    const btn = document.getElementById('generateForecastBtn');
    const btnText = document.getElementById('btnText');

    if (form && overlay && btn) {
        form.addEventListener('submit', function() {
            overlay.classList.remove('hidden');
            btn.disabled = true;
            if (btnText) {
                btnText.textContent = 'Generating...';
            }

            // Animate progress while the server processes the forecast.
            let progress = 0;
            progressBar.style.width = '0%';
            progressText.textContent = '0%';

            const interval = setInterval(function() {
                if (progress < 90) {
                    progress += Math.random() * 3;
                    if (progress > 90) progress = 90;
                    progressBar.style.width = progress + '%';
                    progressText.textContent = Math.round(progress) + '%';
                }
            }, 1000);

            // Allow the normal form submission to proceed. The overlay stays
            // visible until the redirect response renders the results page.
            setTimeout(function() {
                clearInterval(interval);
            }, 300000);
        });
    }
});

document.addEventListener('turbo:load', function() {
    const form = document.getElementById('forecastImportForm');
    const overlay = document.getElementById('forecastImportLoadingOverlay');
    const progressBar = document.getElementById('importProgressBar');
    const progressText = document.getElementById('importProgressText');
    const btn = document.getElementById('importForecastBtn');
    const btnText = document.getElementById('importBtnText');
    const feedback = document.getElementById('importFeedback');
    const feedbackMessage = document.getElementById('importFeedbackMessage');
    const feedbackIcon = document.getElementById('importFeedbackIcon');

    function showImportFeedback(type, message) {
        if (!feedback || !feedbackMessage || !feedbackIcon) return;
        feedback.classList.remove('hidden', 'bg-green-50', 'border', 'border-green-200', 'text-green-800', 'bg-red-50', 'border-red-200', 'text-red-800');
        feedbackIcon.setAttribute('data-lucide', type === 'success' ? 'check-circle' : 'alert-triangle');
        if (type === 'success') {
            feedback.classList.add('bg-green-50', 'border', 'border-green-200', 'text-green-800');
            feedbackIcon.classList.add('text-green-600');
            feedbackIcon.classList.remove('text-red-600');
        } else {
            feedback.classList.add('bg-red-50', 'border', 'border-red-200', 'text-red-800');
            feedbackIcon.classList.add('text-red-600');
            feedbackIcon.classList.remove('text-green-600');
        }
        feedbackMessage.textContent = message;
        if (window.lucide) lucide.createIcons();
    }

    function hideImportFeedback() {
        if (feedback) feedback.classList.add('hidden');
    }

    if (form && overlay && btn) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            hideImportFeedback();
            overlay.classList.remove('hidden');
            btn.disabled = true;
            if (btnText) {
                btnText.textContent = 'Importing...';
            }

            progressBar.style.width = '0%';
            progressText.textContent = 'Uploading: 0%';

            const xhr = new XMLHttpRequest();
            const formData = new FormData(form);

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressText.textContent = 'Uploading: ' + percent + '%';
                }
            });

            xhr.addEventListener('load', function() {
                progressBar.style.width = '100%';
                progressText.textContent = 'Processing...';

                let response = {};
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (e) {
                    response = { success: false, message: 'Unexpected server response.' };
                }

                if (xhr.status >= 200 && xhr.status < 300 && response.success) {
                    window.location.reload();
                    return;
                }

                overlay.classList.add('hidden');
                btn.disabled = false;
                if (btnText) {
                    btnText.textContent = 'Import Production Data';
                }

                let message = response.message || 'Import failed. Please check the file and try again.';
                if (response.errors && typeof response.errors === 'object') {
                    message = Object.values(response.errors).flat().join(' ');
                }
                showImportFeedback('error', message);
            });

            xhr.addEventListener('error', function() {
                overlay.classList.add('hidden');
                btn.disabled = false;
                if (btnText) {
                    btnText.textContent = 'Import Production Data';
                }
                showImportFeedback('error', 'Import failed. Please check the file and try again.');
            });

            xhr.open('POST', form.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    }
});
})();
</script>
@endpush
