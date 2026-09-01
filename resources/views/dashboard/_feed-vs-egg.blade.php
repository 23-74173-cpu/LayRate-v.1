<turbo-frame id="dashboard-feed-vs-egg">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 h-full flex flex-col">
        <div class="flex items-start gap-3 mb-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: #fef3cd; color: #b45309;">
                <i data-lucide="wheat" class="w-4 h-4"></i>
            </span>
            <div>
                <div class="text-sm font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Feed vs Egg Production</div>
                <div class="text-xs mt-0.5" style="color: #9CA3AF;">Feed consumption vs egg output per cage-day</div>
            </div>
        </div>
        @if(count($scatterData) < 3)
            <div class="rounded-xl border py-8 text-center text-sm flex-1" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                Insufficient data — need at least 3 observations.
            </div>
        @else
            <div class="mb-2 text-xs px-1" style="color: #6B7280;">{{ $insight }}</div>
            <div class="relative w-full flex-1 min-h-[220px]">
                <canvas id="feedVsEggChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>
    <script>
    (function() {
        var data = @json($scatterData);
        if (data.length < 3) return;
        var config = {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Cage-Days',
                    data: data,
                    backgroundColor: 'rgba(180, 83, 9, 0.5)',
                    borderColor: 'rgb(180, 83, 9)',
                    borderWidth: 1,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#102A4C', titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { label: function(c) { return c.raw.x + ' kg feed — ' + c.raw.y + ' eggs'; } } } },
                scales: {
                    x: { title: { display: true, text: 'Feed Consumed (kg)', color: '#9CA3AF', font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF' } },
                    y: { title: { display: true, text: 'Eggs Produced', color: '#9CA3AF', font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF' } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('feedVsEggChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('feedVsEggChart', config); }
    })();
    </script>
</turbo-frame>
