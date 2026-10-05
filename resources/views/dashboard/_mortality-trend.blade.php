<turbo-frame id="dashboard-mortality-trend">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <x-card-header title="Mortality Trend" subtitle="Average {{ $avgDaily }} deaths/day" icon="activity">
            <x-slot:actions>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="interp-btn">
                    <i data-lucide="sparkles"></i> Interpretation
                </button>
                <x-chart-fullscreen-button chart="mortalityTrendChart" title="Mortality Trend" />
            </x-slot:actions>
        </x-card-header>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #dcebfa; color: var(--color-info); border: 1px solid #b8d4fe;">{{ $insight }}</div>
        @if(array_sum($data) === 0)
            <div class="rounded-xl border py-8 text-center text-sm flex-1" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No mortality records for the selected period.
            </div>
        @else
            @if($peakVal > 0)
            <div class="mb-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #fce7f3; color: #db2777; border: 1px solid rgba(219,39,119,0.2);">
                    <i data-lucide="trending-up" class="w-3 h-3"></i>
                    Peak Mortality — {{ $peakVal }} deaths — {{ $peakLabel }}
                </span>
            </div>
            @endif
            <div class="relative w-full flex-1 min-h-[160px]">
                <canvas id="mortalityTrendChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>
    <script>
    (function() {
        var labels = @json($labels);
        var data = @json($data);
        if (!Array.isArray(data) || !data.some(function(v) { return v > 0; })) return;
        var config = {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Deaths',
                    data: data,
                    borderColor: LayRateChartColors.mortality,
                    backgroundColor: LayRateChartColors.alpha(LayRateChartColors.mortality, 0.15),
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, layrateCrosshair: { enabled: true }, tooltip: { backgroundColor: LayRateChartColors.tooltip, titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { label: function(c) { return c.raw + ' deaths'; } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#9CA3AF', maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF', stepSize: 1 } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('mortalityTrendChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('mortalityTrendChart', config); }
    })();
    </script>
    <script>if(window.lucide) try{ lucide.createIcons(); }catch(e){}</script>
</turbo-frame>
