<turbo-frame id="forecast-results">
    @php
        $cageColorMap = ['CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A'];
        $cageColor = $scope === 'farm' ? '#102A4C' : ($cageColorMap[$cageCode ?? ''] ?? '#2D7D46');
        $scopeLabel = match($scope) {
            'farm' => 'Whole Farm',
            'breed' => $breed ?? '',
            default => $cageCode ?? '',
        };
    @endphp

    {{-- ── Chart Panel ── --}}
    <div class="xl:col-span-2 bg-white rounded-lg border border-[#D9D9D9] p-5 mb-8">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">HISTORICAL VS FORECAST HDEP — {{ $scopeLabel }}</div>
        <canvas id="forecastChart" height="160"></canvas>
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
                <tr class="border-b border-[#F0F0F0] hover:bg-[#F5F6F8]">
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
                    No forecast generated yet.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
    (function() {
        const historical = @json($historical->map(fn($l) => ['date'=> is_object($l->log_date) ? $l->log_date->format('Y-m-d') : $l->log_date,'hdep'=>$l->hdep]));
        const forecasts  = @json($forecasts->map(fn($f) => ['date'=> is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date,'hdep'=>$f->predicted_hdep]));
        const cageColor  = '{{ $cageColor }}';

        const histLabels = historical.map((h, i) => 'H-' + (historical.length - i));
        const fcLabels   = forecasts.map((_, i) => 'F+' + (i+1));
        const allLabels  = [...histLabels, ...fcLabels];

        const histData = [...historical.map(h => h.hdep), ...Array(fcLabels.length).fill(null)];
        const fcData   = [...Array(histLabels.length).fill(null), ...forecasts.map(f => f.hdep)];

        function initForecastChart() {
            const canvas = document.getElementById('forecastChart');
            if (!canvas) return;
            if (window.forecastChart) window.forecastChart.destroy();
            window.forecastChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: allLabels,
                    datasets: [
                        { label: 'Historical', data: histData, borderColor: '#333333', backgroundColor: 'transparent', tension: 0.3, pointRadius: 3, borderWidth: 2 },
                        { label: 'Forecast', data: fcData, borderColor: cageColor, backgroundColor: cageColor + '22', tension: 0.3, borderDash: [5,3], pointRadius: 3, fill: true, borderWidth: 2 },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } } },
                    scales: {
                        x: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } } },
                        y: { grid: { color: '#F0F0EC' }, ticks: { font: { size: 10 } }, min: 0, max: 100 },
                    }
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initForecastChart);
        } else {
            initForecastChart();
        }
    })();
    </script>
</turbo-frame>
