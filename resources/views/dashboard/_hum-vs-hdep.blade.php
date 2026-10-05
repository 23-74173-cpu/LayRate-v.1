<turbo-frame id="dashboard-hum-vs-hdep">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <x-card-header title="Humidity vs HDEP" subtitle="Does humidity affect production?" icon="droplets">
            <x-slot:actions>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="interp-btn">
                    <i data-lucide="sparkles"></i> Interpretation
                </button>
                <x-chart-fullscreen-button chart="humVsHdepChart" title="Humidity vs HDEP" />
            </x-slot:actions>
        </x-card-header>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #dcebfa; color: var(--color-info); border: 1px solid #b8d4fe;">{{ $insight }}</div>
        @if(count($scatterData) < 3)
            <div class="rounded-xl border py-8 text-center text-sm flex-1" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                Insufficient data — need at least 3 observations.
            </div>
        @else
            <div class="relative w-full flex-1 min-h-[220px]">
                <canvas id="humVsHdepChart" style="width: 100%; height: 100%; display: block;"></canvas>
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
                    backgroundColor: LayRateChartColors.alpha(LayRateChartColors.humidity, 0.5),
                    borderColor: LayRateChartColors.humidity,
                    borderWidth: 1,
                    pointRadius: data.length >= 100 ? 2 : 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: LayRateChartColors.tooltip, titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { label: function(c) { return c.raw.x + '% Hum — ' + c.raw.y + '% HDEP'; } } } },
                scales: {
                    x: { title: { display: true, text: 'Humidity (%)', color: '#9CA3AF', font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF' } },
                    y: { title: { display: true, text: 'HDEP (%)', color: '#9CA3AF', font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF' } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('humVsHdepChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('humVsHdepChart', config); }
    })();
    </script>
    <script>if(window.lucide) try{ lucide.createIcons(); }catch(e){}</script>
</turbo-frame>
