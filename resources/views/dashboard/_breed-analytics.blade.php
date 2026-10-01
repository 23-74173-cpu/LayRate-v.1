<turbo-frame id="dashboard-breed-analytics">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <x-card-header title="HDEP by Breed" subtitle="Average hen-day egg production by breed" icon="award">
            <x-slot:actions>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="interp-btn">
                    <i data-lucide="sparkles"></i> Interpretation
                </button>
            </x-slot:actions>
        </x-card-header>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #dcebfa; color: var(--color-info); border: 1px solid #b8d4fe;">{{ $insight }}</div>
        @if(empty($data))
            <x-empty-state icon="bar-chart-3" message="No production data available for breed analysis."
                             :actionUrl="route('eggs.logging')" actionLabel="Log Eggs" />
        @else
            @if($bestBreed)
            <div class="mb-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #e8f5ec; color: #1f6b3a; border: 1px solid #cfe8d6;">
                    <i data-lucide="trophy" class="w-3 h-3"></i>
                    Best Performing — {{ $bestBreed->breed }} — {{ $bestBreed->avg_hdep }}% avg HDEP
                </span>
            </div>
            @endif
            <div class="relative w-full flex-1 min-h-[160px]">
                <canvas id="breedAnalyticsChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>
    <script>
    (function() {
        var labels = @json($labels);
        var data = @json($data);
        if (!data.length) return;
        var colors = ['#059669','#2563eb','#d97706','#dc2626','#7c3aed','#0891b2'];
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
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false }, tooltip: { backgroundColor: LayRateChartColors.tooltip, titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { label: function(c) { return c.raw + '% avg HDEP'; } } } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF', callback: function(v) { return v + '%'; } } },
                    y: { grid: { display: false }, ticks: { font: { size: 10, weight: '500' }, color: '#333' } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('breedAnalyticsChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('breedAnalyticsChart', config); }
    })();
    </script>
    <script>if(window.lucide) try{ lucide.createIcons(); }catch(e){}</script>
</turbo-frame>
