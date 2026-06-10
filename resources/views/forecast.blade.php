@extends('layouts.app')
@section('title', 'Forecast')

@section('content')
<main class="p-5 space-y-5">

    <h1 class="text-xl font-medium text-[#333333]">Forecast</h1>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Inputs Panel ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-4">FORECAST INPUTS</div>
            <form method="POST" action="{{ route('forecast.generate') }}">
                @csrf
                <label class="block text-sm text-[#333333] mb-2">Select Cage</label>
                <select name="cage" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                    @foreach($allCages as $c)
                    <option value="{{ $c->cage_code }}" {{ $c->cage_code === $cageCode ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                    @endforeach
                </select>

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
            </form>
        </div>

        {{-- ── Chart Panel ── --}}
        <div class="xl:col-span-2 bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-[10px] tracking-wider text-[#6B7280] mb-4">HISTORICAL VS FORECAST HDEP</div>
            <canvas id="forecastChart" height="160"></canvas>
        </div>
    </div>

    {{-- ── Forecast Table ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Date</th>
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Predicted HDEP</th>
                    <th class="text-left text-xs text-[#6B7280] px-6 py-3 font-medium">Confidence</th>
                </tr>
            </thead>
            <tbody>
                @forelse($forecasts as $f)
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                    <td class="px-6 py-3 text-sm text-[#333333] font-mono">{{ $f->target_date->format('Y-m-d') }}</td>
                    <td class="px-6 py-3 text-sm text-[#333333]">{{ number_format($f->predicted_hdep,1) }}%</td>
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

</main>
@endsection

@push('scripts')
<script>
const historical = @json($historical->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'hdep'=>$l->hdep]));
const forecasts  = @json($forecasts->map(fn($f) => ['date'=>$f->target_date->format('Y-m-d'),'hdep'=>$f->predicted_hdep]));
const cageColor  = '{{ match($cageCode){"CAGE-A"=>"#2D7D46","CAGE-B"=>"#1D4E8F","CAGE-C"=>"#C2703E","CAGE-D"=>"#6B4C8A",default=>"#2D7D46"} }}';

const histLabels = historical.map(h => 'H-' + (historical.length - historical.indexOf(h)));
const fcLabels   = forecasts.map((_, i) => 'F+' + (i+1));
const allLabels  = [...histLabels, ...fcLabels];

const histData = [...historical.map(h => h.hdep), ...Array(fcLabels.length).fill(null)];
const fcData   = [...Array(histLabels.length).fill(null), ...forecasts.map(f => f.hdep)];

new Chart(document.getElementById('forecastChart'), {
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
                borderColor: '#C2703E',
                backgroundColor: '#C2703E22',
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
            y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, min: 0, max: 100 },
        }
    }
});
</script>
@endpush
