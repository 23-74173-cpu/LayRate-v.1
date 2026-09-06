<turbo-frame id="dashboard-feed-by-cage">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0" style="background-color: #fef3cd; color: #b45309;">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                </span>
                <div>
                    <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Feed Consumption by Cage</div>
                    <div class="text-xs mt-0.5" style="color: #9CA3AF;">Average daily feed (kg) per cage</div>
                    <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full transition-all hover:opacity-80" style="color: #6366f1; background-color: rgba(99,102,241,0.08);">
                        <i data-lucide="sparkles" class="w-2.5 h-2.5"></i> Interpretation
                    </button>
                </div>
            </div>
            @include('dashboard._days-filter', ['days' => $days, 'frameId' => 'dashboard-feed-by-cage', 'routeName' => 'dashboard.feed-by-cage'])
        </div>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>
        @if(empty($data) || !array_sum($data))
            <div class="rounded-xl border py-8 text-center text-sm flex-1" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No feed data for the selected period.
            </div>
        @else
            @if($highest)
            <div class="mb-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #fef3cd; color: #b45309; border: 1px solid rgba(180,83,9,0.2);">
                    <i data-lucide="trending-up" class="w-3 h-3"></i>
                    Highest — {{ $highest->cage_code }} — {{ $highest->avg_daily }} kg/day
                </span>
            </div>
            @endif
            <div class="relative w-full flex-1 min-h-[200px]">
                <canvas id="feedByCageChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>
    <script>
    (function() {
        var labels = @json($labels);
        var data = @json($data);
        if (!Array.isArray(data) || !data.length || !data.some(function(v) { return v > 0; })) return;
        var feedData = @json($feedData->pluck('hen_count')->values());
        var colors = ['#b45309','#d97706','#f59e0b','#fbbf24','#fcd34d'];
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
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#102A4C', titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { title: function(items) { var i = items[0].dataIndex; var h = feedData[i]; return items[0].label + (h ? ' (' + h + ' hens)' : ''); }, label: function(c) { return c.raw + ' kg/day avg'; } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10, weight: '500' }, color: '#333' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF', callback: function(v) { return v + ' kg'; } } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('feedByCageChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('feedByCageChart', config); }
    })();
    </script>
    <script>if(window.lucide) try{ lucide.createIcons(); }catch(e){}</script>
</turbo-frame>
