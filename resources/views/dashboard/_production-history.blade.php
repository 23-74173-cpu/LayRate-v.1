<turbo-frame id="dashboard-production-history">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 dash-rise" style="animation-delay: 180ms;">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex items-start gap-3">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: #e8f3fe; color: #0075de;">
                    <i data-lucide="chart-line" class="w-4 h-4"></i>
                </span>
                <div>
                    <div class="text-sm font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Egg Production History</div>
                    <p class="text-xs text-[#6B7280]">{{ $title }} over the last {{ $days }} days.</p>
                </div>
            </div>
            <div class="inline-flex items-center gap-1 rounded-lg p-1" style="background-color: #f3f4f6;">
                @foreach([7, 14, 30] as $d)
                <a href="{{ route('dashboard.production-history', ['days' => $d, 'cage' => $cageCode]) }}"
                   class="px-3 py-1.5 text-xs font-semibold rounded-md transition-all {{ $days === $d ? '' : 'text-[#6B7280] hover:bg-[#e5e7eb]' }}"
                   {{ $days === $d ? 'style="background-color: #0075de; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"' : '' }}
                   data-turbo-frame="dashboard-production-history">
                    {{ $d }}D
                </a>
                @endforeach
            </div>
        </div>

        @if(empty($chartData['datasets']))
            <div class="rounded-xl border py-8 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No production data for the selected period.
            </div>
        @else
            <div class="relative w-full h-[260px]">
                <canvas id="dashProductionHistoryChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>

    <script data-lucide-init>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }

    var productionChartData = @json($chartData);
    var productionChartConfig = {
        type: 'line',
        data: productionChartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            layout: { padding: { top: 10, right: 10, bottom: 0, left: 0 } },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                        padding: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + context.raw + ' eggs';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, family: 'Inter, system-ui, sans-serif' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#F0F0EC' },
                    ticks: { font: { size: 10, family: 'Inter, system-ui, sans-serif' } }
                }
            }
        }
    };

    if (productionChartData.labels.length) {
        if (typeof window.DashboardChartRenderer !== 'undefined') {
            window.DashboardChartRenderer.render('dashProductionHistoryChart', productionChartConfig);
        } else if (window.LayRateChart) {
            LayRateChart.create('dashProductionHistoryChart', productionChartConfig);
        }
    }
    </script>
</turbo-frame>
