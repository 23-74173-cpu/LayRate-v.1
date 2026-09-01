<turbo-frame id="dashboard-mortality-by-cause">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 h-full flex flex-col">
        <div class="flex items-start gap-3 mb-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: #fce7f3; color: #db2777;">
                <i data-lucide="skull" class="w-4 h-4"></i>
            </span>
            <div>
                <div class="text-sm font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Mortality by Cause</div>
                <div class="text-xs mt-0.5" style="color: #9CA3AF;">{{ number_format($totalDeaths) }} total deaths</div>
            </div>
        </div>
        @if(empty($data))
            <div class="rounded-xl border py-8 text-center text-sm flex-1" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No mortality records for the selected period.
            </div>
        @else
            @if($topCause)
            <div class="mb-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #fce7f3; color: #db2777; border: 1px solid rgba(219,39,119,0.2);">
                    Highest Cause — {{ $topCause->reason }} — {{ number_format($topCause->total) }} deaths ({{ $totalDeaths > 0 ? round($topCause->total / $totalDeaths * 100) : 0 }}%)
                </span>
            </div>
            @endif
            <div class="relative w-full flex-1 min-h-[200px]">
                <canvas id="mortalityByCauseChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>
    <script>
    (function() {
        var labels = @json($labels);
        var data = @json($data);
        if (!data.length) return;
        var colors = ['#db2777','#f59e0b','#ef4444','#6366f1','#9ca3af','#78716c'];
        var bgColors = data.map(function(_, i) { return colors[i % colors.length] + '33'; });
        var borderColors = data.map(function(_, i) { return colors[i % colors.length]; });
        var config = {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: 1.5,
                    borderRadius: 4,
                    barPercentage: 0.65
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#102A4C', titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { label: function(c) { return c.raw + ' deaths'; } } } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF', stepSize: 1 } },
                    y: { grid: { display: false }, ticks: { font: { size: 10, weight: '500' }, color: '#333' } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('mortalityByCauseChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('mortalityByCauseChart', config); }
    })();
    </script>
</turbo-frame>
