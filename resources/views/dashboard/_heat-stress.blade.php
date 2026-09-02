<turbo-frame id="dashboard-heat-stress">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 h-full flex flex-col">
        <div class="flex items-start gap-3 mb-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: #fee2e2; color: #dc2626;">
                <i data-lucide="thermometer-sun" class="w-4 h-4"></i>
            </span>
            <div>
                <div class="text-sm font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Heat Stress Analytics</div>
                <div class="text-xs mt-0.5" style="color: #9CA3AF;">HDEP by temperature level (threshold: {{ $tempMax }}°C)</div>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full transition-all hover:opacity-80" style="color: #6366f1; background-color: rgba(99,102,241,0.08);">
                    <i data-lucide="sparkles" class="w-2.5 h-2.5"></i> Interpretation
                </button>
            </div>
        </div>
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>

        {{-- KPI Row --}}
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="rounded-lg p-3 text-center" style="background-color: #fef2f2;">
                <div class="text-lg font-bold" style="color: #dc2626;">{{ $highEvents }}</div>
                <div class="text-[10px] font-semibold uppercase" style="color: #9CA3AF;">High Stress Events</div>
            </div>
            <div class="rounded-lg p-3 text-center" style="background-color: #fef2f2;">
                <div class="text-lg font-bold" style="color: #dc2626;">{{ $highAvgHdep !== null ? $highAvgHdep . '%' : '—' }}</div>
                <div class="text-[10px] font-semibold uppercase" style="color: #9CA3AF;">Avg HDEP During Stress</div>
            </div>
            <div class="rounded-lg p-3 text-center" style="background-color: #fef2f2;">
                <div class="text-lg font-bold" style="color: #dc2626;">{{ $peakTemp > 0 ? $peakTemp . '°C' : '—' }}</div>
                <div class="text-[10px] font-semibold uppercase" style="color: #9CA3AF;">Peak Temperature</div>
            </div>
        </div>

        {{-- Summary Table --}}
        <div class="rounded-xl border border-[#e6e6e6] overflow-hidden mb-4">
            <table class="w-full text-xs">
                <thead>
                    <tr style="background-color: #f7f7f5;">
                        <th class="px-3 py-2 text-left font-semibold text-[#6B7280]">Level</th>
                        <th class="px-3 py-2 text-right font-semibold text-[#6B7280]">Days</th>
                        <th class="px-3 py-2 text-right font-semibold text-[#6B7280]">Avg HDEP</th>
                        <th class="px-3 py-2 text-right font-semibold text-[#6B7280]">Mortality</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary as $level => $s)
                    <tr class="border-t" style="border-color: #e6e6e6;">
                        <td class="px-3 py-2 font-medium" style="color: #333;">
                            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background-color: {{ $level === 'Normal' ? '#059669' : ($level === 'Moderate' ? '#f59e0b' : '#dc2626') }}"></span>
                            {{ $level }}
                        </td>
                        <td class="px-3 py-2 text-right" style="color: #6B7280;">{{ $s['count'] }}</td>
                        <td class="px-3 py-2 text-right" style="color: #6B7280;">{{ $s['avg_hdep'] !== null ? $s['avg_hdep'] . '%' : '—' }}</td>
                        <td class="px-3 py-2 text-right" style="color: #6B7280;">{{ $s['mortality'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Bar Chart --}}
        @php
            $barLabels = array_keys($summary);
            $barData = array_map(fn ($s) => $s['avg_hdep'] ?? 0, $summary);
        @endphp
        @if(array_sum($barData) > 0)
        <div class="relative w-full min-h-[180px]">
            <canvas id="heatStressChart" style="width: 100%; height: 100%; display: block;"></canvas>
        </div>
        @endif
    </div>
    <script>
    (function() {
        var labels = @json($barLabels);
        var data = @json($barData);
        if (!Array.isArray(data) || !data.some(function(v) { return v > 0; })) return;
        var colors = ['rgba(5,150,105,0.6)', 'rgba(245,158,11,0.6)', 'rgba(220,38,38,0.6)'];
        var borderColors = ['#059669', '#f59e0b', '#dc2626'];
        var config = {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: borderColors,
                    borderWidth: 1.5,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#102A4C', titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 }, padding: { top: 8, bottom: 8, left: 12, right: 12 }, cornerRadius: 8, callbacks: { label: function(c) { return c.raw > 0 ? c.raw + '% avg HDEP' : 'No data'; } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10, weight: '500' }, color: '#333' } },
                    y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, color: '#9CA3AF', callback: function(v) { return v + '%'; } } }
                }
            }
        };
        if (typeof window.DashboardChartRenderer !== 'undefined') { window.DashboardChartRenderer.render('heatStressChart', config); }
        else if (window.LayRateChart) { LayRateChart.create('heatStressChart', config); }
    })();
    </script>
    <script>if(window.lucide) try{ lucide.createIcons(); }catch(e){}</script>
</turbo-frame>
