<turbo-frame id="dashboard-hen-age-layrate">
    <style>
        @keyframes chartFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hal-fade-in { animation: chartFadeIn 0.35s ease-out both; }
    </style>
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 h-full flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex items-start gap-3">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: #f3e8ff; color: #7c3aed;">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                </span>
                <div>
                    <div class="text-sm font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Hen Age vs Lay Rate</div>
                    <div class="text-xs mt-0.5" style="color: #9CA3AF;">{{ $chartData['all_ages_count'] }} age weeks tracked · focused on peak</div>
                    <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full transition-all hover:opacity-80" style="color: #6366f1; background-color: rgba(99,102,241,0.08);">
                        <i data-lucide="sparkles" class="w-2.5 h-2.5"></i> Interpretation
                    </button>
                </div>
            </div>
            @if($chartData['peak_label'] !== '—')
            <div class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                 style="background-color: #f3e8ff; color: #7c3aed; border: 1px solid rgba(124,58,237,0.2);">
                <i data-lucide="award" class="w-3 h-3"></i>
                Peak: {{ $chartData['peak_label'] }} at {{ $chartData['peak_hdep'] }}%
            </div>
            @endif
        </div>

        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #f0f0ff; color: #3730a3; border: 1px solid rgba(99,102,241,0.15);">{{ $insight }}</div>

        @if(empty($chartData['data']))
            <div class="rounded-xl border py-8 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                No production data available for age analysis.
            </div>
        @else
            <div class="relative w-full flex-1 min-h-[260px] hal-fade-in">
                <canvas id="henAgeLayrateChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
        @endif
    </div>

    <script data-lucide-init>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }

    var halData = @json($chartData);

    // Gradient fill plugin
    var halGradientPlugin = {
        id: 'halGradient',
        beforeDatasetsDraw: function(chart) {
            var ctx = chart.ctx;
            var yAxis = chart.scales.y;
            ctx.save();
            var gradient = ctx.createLinearGradient(0, yAxis.top, 0, yAxis.bottom);
            gradient.addColorStop(0, 'rgba(124, 58, 237, 0.35)');
            gradient.addColorStop(1, 'rgba(124, 58, 237, 0.02)');
            chart.data.datasets[0].backgroundColor = gradient;
            ctx.restore();
        }
    };

    // Soft horizontal grid
    var halGridPlugin = {
        id: 'halGrid',
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

    // Peak marker plugin
    var halPeakPlugin = {
        id: 'halPeak',
        afterDatasetsDraw: function(chart) {
            if (halData.peak_age === null) return;
            var meta = chart.getDatasetMeta(0);
            var point = meta.data[halData.peak_age];
            if (!point) return;
            var ctx = chart.ctx;
            ctx.save();
            ctx.beginPath();
            ctx.arc(point.x, point.y, 6, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(124, 58, 237, 0.2)';
            ctx.fill();
            ctx.beginPath();
            ctx.arc(point.x, point.y, 3.5, 0, Math.PI * 2);
            ctx.fillStyle = '#7c3aed';
            ctx.fill();
            ctx.restore();
        }
    };

    var halChartConfig = {
        type: 'line',
        data: {
            labels: halData.labels,
            datasets: [{
                label: 'Avg HDEP %',
                data: halData.data,
                borderColor: 'rgb(124, 58, 237)',
                backgroundColor: 'rgba(124, 58, 237, 0.2)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#ffffff',
                pointHoverBorderWidth: 2,
                pointBorderColor: 'rgb(124, 58, 237)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600, easing: 'easeOutQuart' },
            layout: { padding: { top: 12, right: 8, bottom: 0, left: 0 } },
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
                            var idx = items[0].dataIndex;
                            return 'Age: ' + halData.labels[idx];
                        },
                        label: function(context) {
                            var idx = context.dataIndex;
                            var samples = halData.counts ? halData.counts[idx] : 0;
                            return ' HDEP: ' + context.raw + '% (' + samples + ' logs)';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    title: { display: true, text: 'Hen Age (weeks)', color: '#9CA3AF', font: { size: 10, weight: '500' } },
                    ticks: { color: '#9CA3AF', font: { size: 9, weight: '500' }, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                },
                y: {
                    beginAtZero: true,
                    grid: { display: false },
                    border: { display: false },
                    title: { display: true, text: 'HDEP %', color: '#9CA3AF', font: { size: 10, weight: '500' } },
                    ticks: { color: '#9CA3AF', font: { size: 10, weight: '500' }, padding: 8 }
                }
            }
        },
        plugins: [halGradientPlugin, halGridPlugin, halPeakPlugin]
    };

    if (halData.data.length) {
        if (typeof window.DashboardChartRenderer !== 'undefined') {
            window.DashboardChartRenderer.render('henAgeLayrateChart', halChartConfig);
        } else if (window.LayRateChart) {
            LayRateChart.create('henAgeLayrateChart', halChartConfig);
        }
    }
    </script>
</turbo-frame>
