<turbo-frame id="dashboard-hen-age-layrate">
    <style>
        @keyframes chartFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hal-fade-in { animation: chartFadeIn 0.35s ease-out both; }
    </style>
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-auto flex flex-col lg:h-full">
        <x-card-header title="Hen Age vs Lay Rate" subtitle="{{ $chartData['all_ages_count'] }} age weeks tracked · focused on peak" icon="trending-up">
            <x-slot:actions>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="interp-btn">
                    <i data-lucide="sparkles"></i> Interpretation
                </button>
                @if($chartData['peak_label'] !== '—')
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                     style="background-color: #f3e8ff; color: #7c3aed; border: 1px solid rgba(124,58,237,0.2);">
                    <i data-lucide="award" class="w-3 h-3"></i>
                    Peak: {{ $chartData['peak_label'] }} at {{ $chartData['peak_hdep'] }}%
                </span>
                @endif
            </x-slot:actions>
        </x-card-header>

        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #dcebfa; color: var(--color-info); border: 1px solid #b8d4fe;">{{ $insight }}</div>

        @if(empty($chartData['data']))
            <x-empty-state icon="trending-up" message="No production data available for age analysis."
                             :actionUrl="route('eggs.logging')" actionLabel="Log Eggs" />
        @else
            <div class="relative w-full h-[160px] flex-none lg:h-[250px] lg:min-h-[250px] hal-fade-in">
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
                borderColor: LayRateChartColors.hdep,
                backgroundColor: LayRateChartColors.alpha(LayRateChartColors.hdep, 0.2),
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
                    backgroundColor: LayRateChartColors.tooltip,
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
