<turbo-frame id="dashboard-egg-collection-time">
    <style>
        @keyframes chartFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .ect-fade-in { animation: chartFadeIn 0.35s ease-out both; }
    </style>
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0" style="background-color: #fef3cd; color: #b45309;">
                    <i data-lucide="chart-line" class="w-3 h-3"></i>
                </span>
                <div>
                    <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Eggs Collected by Time</div>
                    <div class="text-xs mt-0.5" style="color: #9CA3AF;">{{ number_format($chartData['total']) }} eggs across {{ $days > 0 ? $days . ' days' : 'all time' }}</div>
                    <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full transition-all hover:opacity-80" style="color: #6366f1; background-color: rgba(99,102,241,0.08);">
                        <i data-lucide="sparkles" class="w-2.5 h-2.5"></i> Interpretation
                    </button>
                </div>
            </div>
            @include('dashboard._days-filter', ['days' => $days, 'frameId' => 'dashboard-egg-collection-time', 'routeName' => 'dashboard.egg-collection-time'])
        </div>

        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>

        @if(array_sum($chartData['data']) === 0)
            <div class="rounded-xl border py-8 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No production data for the selected period.
            </div>
        @else
            @php
                $peakEctVal = max($chartData['data']);
                $peakEctIdx = array_search($peakEctVal, $chartData['data']);
                $peakEctLabel = $chartData['labels'][$peakEctIdx] ?? '';
            @endphp
            @if($peakEctVal > 0)
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #fef3cd; color: #b45309; border: 1px solid rgba(180,83,9,0.2);">
                    <i data-lucide="clock" class="w-3 h-3"></i>
                    Peak Collection — {{ $peakEctLabel }} — {{ number_format($peakEctVal) }} eggs
                </span>
            </div>
            @endif
            <div class="relative w-full flex-1 min-h-[260px] ect-fade-in">
                <canvas id="eggCollectionTimeChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>

    <script data-lucide-init>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }

    var ectData = @json($chartData);

    // Gradient fill plugin
    var ectGradientPlugin = {
        id: 'ectGradient',
        beforeDatasetsDraw: function(chart) {
            var ctx = chart.ctx;
            var yAxis = chart.scales.y;
            var meta = chart.getDatasetMeta(0);
            if (!meta || !meta.data) return;
            ctx.save();
            var gradient = ctx.createLinearGradient(0, yAxis.top, 0, yAxis.bottom);
            gradient.addColorStop(0, 'rgba(180, 83, 9, 0.35)');
            gradient.addColorStop(1, 'rgba(180, 83, 9, 0.02)');
            chart.data.datasets[0].backgroundColor = gradient;
            ctx.restore();
        }
    };

    // Soft horizontal grid
    var ectGridPlugin = {
        id: 'ectGrid',
        beforeDraw: function(chart) {
            var ctx = chart.ctx;
            var yAxis = chart.scales.y;
            var xAxis = chart.scales.x;
            ctx.save();
            ctx.strokeStyle = 'rgba(0,0,0,0.04)';
            ctx.lineWidth = 1;
            yAxis.ticks.forEach(function(tick) {
                var y = yAxis.getPixelForValue(tick.value);
                ctx.beginPath();
                ctx.moveTo(xAxis.left, y);
                ctx.lineTo(xAxis.right, y);
                ctx.stroke();
            });
            ctx.restore();
        }
    };

    var maxVal = Math.max.apply(null, ectData.data);

    var ectChartConfig = {
        type: 'line',
        data: {
            labels: ectData.labels,
            datasets: [{
                data: ectData.data,
                borderColor: 'rgb(180, 83, 9)',
                backgroundColor: 'rgba(180, 83, 9, 0.2)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#ffffff',
                pointHoverBorderWidth: 2,
                pointBorderColor: 'rgb(180, 83, 9)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 600,
                easing: 'easeOutQuart',
            },
            layout: { padding: { top: 8, right: 4, bottom: 0, left: 0 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#102A4C',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 12, weight: '500' },
                    padding: { top: 10, bottom: 10, left: 14, right: 14 },
                    cornerRadius: 10,
                    boxPadding: 6,
                    callbacks: {
                        title: function(items) {
                            return items[0].label;
                        },
                        label: function(context) {
                            var pct = ectData.total > 0 ? ((context.raw / ectData.total) * 100).toFixed(1) : 0;
                            return ' ' + context.raw.toLocaleString() + ' eggs (' + pct + '%)';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: '#9CA3AF',
                        font: { size: 9, weight: '500' },
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 12
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: '#9CA3AF',
                        font: { size: 10, weight: '500' },
                        padding: 8,
                        stepSize: maxVal > 0 ? Math.max(1, Math.ceil(maxVal / 5)) : 1
                    }
                }
            }
        },
        plugins: [ectGradientPlugin, ectGridPlugin]
    };

    if (Array.isArray(ectData.data) && ectData.data.some(function(v) { return v > 0; })) {
        if (typeof window.DashboardChartRenderer !== 'undefined') {
            window.DashboardChartRenderer.render('eggCollectionTimeChart', ectChartConfig);
        } else if (window.LayRateChart) {
            LayRateChart.create('eggCollectionTimeChart', ectChartConfig);
        }
    }
    </script>
</turbo-frame>
