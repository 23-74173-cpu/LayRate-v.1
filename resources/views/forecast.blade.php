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
    @endphp

    <x-page-header title="Forecast" subtitle="Project egg production based on historical egg count trends" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Inputs Panel ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FORECAST INPUTS</div>
            <form method="POST" action="{{ route('forecast.generate') }}" id="forecastForm">
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

                <button type="submit" class="w-full bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
                    Generate Forecast
                </button>

                <a href="{{ route('forecast.template') }}" class="mt-3 w-full flex items-center justify-center gap-2 border border-[#D9D9D9] text-[#333333] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8] transition-colors">
                    <i data-lucide="download" class="w-4 h-4 shrink-0"></i> Download Empty Template
                </a>
            </form>
        </div>

        {{-- ── Chart Panel ── --}}
        <div class="xl:col-span-2 bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">HISTORICAL VS FORECAST EGG COUNT — {{ $scopeLabel }}</div>
            <canvas id="forecastChart" height="160"></canvas>
        </div>
    </div>

    {{-- ── Model Metrics ── --}}
    @if($metrics ?? false || $recommendedModel ?? false)
    @php
        $metrics = $metrics ?? [];
        $recommended = $recommendedModel ?? null;
        $sarima = $metrics['sarima'] ?? null;
        $xgboost = $metrics['xgboost'] ?? null;
    @endphp
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">MODEL PERFORMANCE</div>
        <div class="flex flex-wrap items-center gap-4 mb-4">
            @if($recommended)
            <div class="flex items-center gap-2 px-4 py-2 rounded-lg bg-[#D5E8D4] text-[#1F5F35] text-sm font-medium">
                <i data-lucide="award" class="w-4 h-4"></i>
                Recommended: {{ $recommended }}
            </div>
            @endif
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @if($sarima)
            <div class="p-4 rounded-lg bg-[#F9F9F7] border border-[#F0F0EC]">
                <div class="text-xs text-[#6B7280] mb-1">SARIMA MAE</div>
                <div class="text-lg font-semibold text-[#333333]">{{ number_format($sarima['MAE'] ?? 0, 2) }}</div>
            </div>
            <div class="p-4 rounded-lg bg-[#F9F9F7] border border-[#F0F0EC]">
                <div class="text-xs text-[#6B7280] mb-1">SARIMA RMSE</div>
                <div class="text-lg font-semibold text-[#333333]">{{ number_format($sarima['RMSE'] ?? 0, 2) }}</div>
            </div>
            <div class="p-4 rounded-lg bg-[#F9F9F7] border border-[#F0F0EC]">
                <div class="text-xs text-[#6B7280] mb-1">SARIMA MAPE</div>
                <div class="text-lg font-semibold text-[#333333]">{{ number_format($sarima['MAPE'] ?? 0, 2) }}%</div>
            </div>
            @endif
            @if($xgboost)
            <div class="p-4 rounded-lg bg-[#F9F9F7] border border-[#F0F0EC]">
                <div class="text-xs text-[#6B7280] mb-1">XGBoost MAE</div>
                <div class="text-lg font-semibold text-[#333333]">{{ number_format($xgboost['MAE'] ?? 0, 2) }}</div>
            </div>
            <div class="p-4 rounded-lg bg-[#F9F9F7] border border-[#F0F0EC]">
                <div class="text-xs text-[#6B7280] mb-1">XGBoost RMSE</div>
                <div class="text-lg font-semibold text-[#333333]">{{ number_format($xgboost['RMSE'] ?? 0, 2) }}</div>
            </div>
            <div class="p-4 rounded-lg bg-[#F9F9F7] border border-[#F0F0EC]">
                <div class="text-xs text-[#6B7280] mb-1">XGBoost MAPE</div>
                <div class="text-lg font-semibold text-[#333333]">{{ number_format($xgboost['MAPE'] ?? 0, 2) }}%</div>
            </div>
            @endif
        </div>
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
                    <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $f->target_date->format('Y-m-d') }}</td>
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

</div>
@endsection

@push('scripts')
<script>
const historical = @json($historical->map(fn($l) => ['date'=> is_object($l->log_date) ? $l->log_date->format('Y-m-d') : $l->log_date,'egg_count'=>$l->egg_count]));
const forecasts  = @json($forecasts->map(fn($f) => ['date'=> is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date,'egg_count'=>$f->predicted_egg_count]));
const cageColor  = '{{ $cageColor }}';

const histLabels = historical.map((h, i) => 'H-' + (historical.length - i));
const fcLabels   = forecasts.map((_, i) => 'F+' + (i+1));
const allLabels  = [...histLabels, ...fcLabels];

const histData = [...historical.map(h => h.egg_count), ...Array(fcLabels.length).fill(null)];
const fcData   = [...Array(histLabels.length).fill(null), ...forecasts.map(f => f.egg_count)];

document.addEventListener('turbo:load', function() {
    if (window.forecastChart) window.forecastChart.destroy();
    window.forecastChart = new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels: allLabels,
        datasets: [
            {
                label: 'Historical',
                data: histData,
                borderColor: '#333333',
                backgroundColor: 'transparent',
                tension: 0.3,
                pointRadius: 3,
                borderWidth: 2,
            },
            {
                label: 'Forecast',
                data: fcData,
                borderColor: cageColor,
                backgroundColor: cageColor + '22',
                tension: 0.3,
                borderDash: [5,3],
                pointRadius: 3,
                fill: true,
                borderWidth: 2,
            },
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } }
        },
        scales: {
            x: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } } },
            y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, min: 0 },
        }
    }
});
});
</script>
@endpush
