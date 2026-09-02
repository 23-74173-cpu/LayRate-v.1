<turbo-frame id="dashboard-hum-vs-hdep">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 h-full flex flex-col">
        <div class="flex items-start gap-3 mb-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: #dbeafe; color: #2563eb;">
                <i data-lucide="droplets" class="w-4 h-4"></i>
            </span>
            <div>
                <div class="text-sm font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Humidity vs HDEP</div>
                <div class="text-xs mt-0.5" style="color: #9CA3AF;">Does humidity affect production?</div>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full transition-all hover:opacity-80" style="color: #6366f1; background-color: rgba(99,102,241,0.08);">
                    <i data-lucide="sparkles" class="w-2.5 h-2.5"></i> Interpretation
                </button>
            </div>
        </div>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>
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
                    backgroundColor: 'rgba(37, 99, 235, 0.5)',
                    borderColor: 'rgb(37, 99, 235)',
                    borderWidth: 1,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#102A4C', titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { label: function(c) { return c.raw.x + '% Hum — ' + c.raw.y + '% HDEP'; } } } },
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
