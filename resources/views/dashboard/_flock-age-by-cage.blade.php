<turbo-frame id="dashboard-flock-age-by-cage">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <x-card-header title="Flock Age by Cage" subtitle="Average flock age (weeks) per cage · as of {{ $reportingDate->format('m/d/Y') }}" icon="timer">
            <x-slot:actions>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="interp-btn">
                    <i data-lucide="sparkles"></i> Interpretation
                </button>
            </x-slot:actions>
        </x-card-header>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>
        @if(empty($data) || !array_sum($data))
            <div class="rounded-xl border py-8 text-center text-sm flex-1" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No flock age data available for the selected cage(s).
            </div>
        @else
            @if($oldest)
            <div class="mb-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #ede9fe; color: #7c3aed; border: 1px solid rgba(124,58,237,0.2);">
                    <i data-lucide="hourglass" class="w-3 h-3"></i>
                    Oldest — {{ $oldest->cage_code }} — {{ $oldest->flock_age_weeks }} wks
                </span>
            </div>
            @endif
            <div class="relative w-full flex-1 min-h-[200px]">
                <canvas id="flockAgeByCageChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>
    <script>
    (function() {
        var labels = @json($labels);
        var data = @json($data);
        if (!Array.isArray(data) || !data.length || !data.some(function(v) { return v > 0; })) return;
        var henData = @json($ageData->pluck('hen_count')->values());
        var colors = ['#7c3aed','#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe'];
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
                plugins: { legend: { display: false }, tooltip: { backgroundColor: LayRateChartColors.tooltip, titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { title: function(items) { var i = items[0].dataIndex; var h = henData[i]; return items[0].label + (h ? ' (' + h + ' hens)' : ''); }, label: function(c) { return c.raw + ' wks avg'; } } } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF', callback: function(v) { return v + ' wks'; } } },
                    y: { grid: { display: false }, ticks: { font: { size: 10, weight: '500' }, color: '#333' } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('flockAgeByCageChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('flockAgeByCageChart', config); }
    })();
    </script>
    <script>if(window.lucide) try{ lucide.createIcons(); }catch(e){}</script>
</turbo-frame>