<turbo-frame id="dashboard-mortality-by-cause">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <div class="flex items-start gap-3 mb-2">
            <span class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0" style="background-color: #fce7f3; color: #db2777;">
                <i data-lucide="skull" class="w-4 h-4"></i>
            </span>
            <div>
                <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Mortality by Cause</div>
                <div class="text-xs mt-0.5" style="color: #9CA3AF;">{{ number_format($totalDeaths) }} total deaths</div>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full transition-all hover:opacity-80" style="color: #6366f1; background-color: rgba(99,102,241,0.08);">
                    <i data-lucide="sparkles" class="w-2.5 h-2.5"></i> Interpretation
                </button>
            </div>
        </div>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>
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
            <div class="relative w-full flex-1 min-h-[160px]">
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
    <script>if(window.lucide) try{ lucide.createIcons(); }catch(e){}</script>
</turbo-frame>
