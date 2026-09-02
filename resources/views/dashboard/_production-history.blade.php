<turbo-frame id="dashboard-production-history">
    <style>
        @keyframes chartFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .chart-fade-in {
            animation: chartFadeIn 0.35s ease-out both;
        }
    </style>
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-full flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0" style="background-color: #e8f3fe; color: #0075de;">
                    <i data-lucide="chart-line" class="w-3 h-3"></i>
                </span>
                <div>
                    <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Egg Production History</div>
                    <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full transition-all hover:opacity-80" style="color: #6366f1; background-color: rgba(99,102,241,0.08);">
                        <i data-lucide="sparkles" class="w-2.5 h-2.5"></i> Interpretation
                    </button>
                </div>
            </div>
            <div class="inline-flex items-center gap-1 rounded-lg p-1" style="background-color: #f3f4f6;">
                @foreach([7, 14, 30] as $d)
                <button type="button"
                   data-history-days="{{ $d }}"
                   onclick="setProductionHistoryDays({{ $d }})"
                   class="history-days-btn px-3 py-1.5 text-xs font-semibold rounded-md transition-all {{ $days === $d ? 'history-days-active' : 'text-[#6B7280] hover:bg-[#e5e7eb]' }}"
                   {{ $days === $d ? 'style="background-color: #0d47a1; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"' : '' }}>
                    {{ $d }}D
                </button>
                @endforeach
                <span class="w-px h-3 mx-1" style="background-color: #d1d5db;"></span>
                <button type="button"
                   data-history-compare
                   onclick="toggleProductionHistoryCompare()"
                   class="history-compare-btn px-3 py-1.5 text-xs font-semibold rounded-md transition-all {{ $compare ? 'history-compare-active' : 'text-[#6B7280] hover:bg-[#e5e7eb]' }}"
                   {{ $compare ? 'style="background-color: #0d47a1; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"' : '' }}>
                    Compare
                </button>
            </div>
        </div>

        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>

        @if(empty($chartData['datasets']))
            <div class="rounded-xl border py-8 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No production data for the selected period.
            </div>
        @else
            @php
                $peakVal = 0; $peakLabel = '';
                foreach ($chartData['datasets'] as $ds) {
                    foreach ($ds['data'] as $i => $v) {
                        if ($v > $peakVal) { $peakVal = $v; $peakLabel = $chartData['labels'][$i] ?? ''; }
                    }
                }
            @endphp
            @if($peakVal > 0)
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #e8f3fe; color: #0075de; border: 1px solid rgba(0,117,222,0.2);">
                    <i data-lucide="trending-up" class="w-3 h-3"></i>
                    Peak Production — {{ number_format($peakVal) }} eggs — {{ $peakLabel }}
                </span>
            </div>
            @endif
            <div class="relative w-full flex-1 min-h-[300px] chart-fade-in">
                <canvas id="dashProductionHistoryChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>

    <script data-lucide-init>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }

    var productionChartData = @json($chartData);

    // Gradient fill plugin — creates vertical gradients from each dataset's borderColor to transparent
    var gradientFillPlugin = {
        id: 'gradientFill',
        beforeDatasetsDraw: function(chart) {
            var ctx = chart.ctx;
            var yAxis = chart.scales.y;
            chart.data.datasets.forEach(function(ds, i) {
                var meta = chart.getDatasetMeta(i);
                if (!meta.visible || !ds._fillGradient) return;
                var color = ds.borderColor || '#102A4C';
                ctx.save();
                var gradient = ctx.createLinearGradient(0, yAxis.top, 0, yAxis.bottom);
                gradient.addColorStop(0, ds._fillGradient);
                gradient.addColorStop(1, 'rgba(255,255,255,0)');
                ds.backgroundColor = gradient;
                ctx.restore();
            });
        }
    };

    // Soft gridlines plugin
    var softGridPlugin = {
        id: 'softGrid',
        beforeDraw: function(chart) {
            var ctx = chart.ctx;
            var xAxis = chart.scales.x;
            var yAxis = chart.scales.y;
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

    // Tag each dataset with a gradient fill color (rgba derived from borderColor)
    productionChartData.datasets.forEach(function(ds) {
        var c = ds.borderColor || '#102A4C';
        // Convert hex to rgba for gradient
        if (c.charAt(0) === '#') {
            var r = parseInt(c.slice(1,3), 16);
            var g = parseInt(c.slice(3,5), 16);
            var b = parseInt(c.slice(5,7), 16);
            ds._fillGradient = 'rgba(' + r + ',' + g + ',' + b + ',0.45)';
            ds.borderColor = 'rgb(' + r + ',' + g + ',' + b + ')';
        } else if (c.indexOf('rgb(') === 0) {
            ds._fillGradient = c.replace('rgb(', 'rgba(').replace(')', ',0.45)');
        } else {
            ds._fillGradient = 'rgba(16,42,76,0.45)';
        }
        ds.fill = true;
    });

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
            animation: {
                duration: 600,
                easing: 'easeOutQuart',
            },
            layout: { padding: { top: 12, right: 12, bottom: 0, left: 4 } },
            plugins: {
                legend: {
                    display: productionChartData.datasets.length > 1,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: 16,
                        font: { size: 11, weight: '600' },
                        color: '#6B7280'
                    }
                },
                tooltip: {
                    backgroundColor: '#102A4C',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 12, weight: '500' },
                    padding: { top: 10, bottom: 10, left: 14, right: 14 },
                    cornerRadius: 10,
                    boxPadding: 6,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    callbacks: {
                        label: function (context) {
                            return ' ' + context.dataset.label + ': ' + context.raw.toLocaleString() + ' eggs';
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
                        font: { size: 10, weight: '500' },
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 10
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
                        callback: function(value) {
                            return value >= 1000 ? (value / 1000).toFixed(1) + 'k' : value;
                        }
                    }
                }
            },
            elements: {
                line: {
                    tension: 0.4,
                    borderWidth: 2.5,
                    fill: true
                },
                point: {
                    radius: 0,
                    hoverRadius: 5,
                    hoverBorderWidth: 2,
                    hoverBackgroundColor: '#ffffff'
                }
            }
        },
        plugins: [gradientFillPlugin, softGridPlugin]
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
