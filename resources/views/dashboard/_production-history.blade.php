<turbo-frame id="dashboard-production-history">
    <style>
        @keyframes chartFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .chart-fade-in {
            animation: chartFadeIn 0.35s ease-out both;
        }
    </style>
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-auto flex flex-col lg:h-full">
        <x-card-header title="Egg Production History" subtitle="{{ isset($rangeLabel) ? 'Daily HDEP % · ' . $rangeLabel : 'Eggs per day · ' . ($days > 0 ? 'last ' . $days . ' days' : 'all time') }}" icon="chart-line">
            <x-slot:actions>
                <button type="button" onclick="this.closest('.bg-white').querySelector('.interpretation-panel').classList.toggle('hidden')" class="interp-btn">
                    <i data-lucide="sparkles"></i> Interpretation
                </button>
                <span hidden data-production-history-fullscreen-controls>
                    <button type="button"
                       data-history-compare
                       onclick="toggleProductionHistoryCompare()"
                       class="history-compare-btn px-3 py-1.5 text-xs font-semibold rounded-md transition-all {{ $compare ? 'history-compare-active' : 'text-[#6B7280] hover:bg-[#e5e7eb]' }}"
                       {{ $compare ? 'style="background-color: #0d47a1; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"' : '' }}>
                        Compare
                    </button>
                    <span class="w-px h-3 mx-1" style="background-color: #d1d5db;"></span>
                    <button type="button"
                       id="forecastToggleBtn"
                       data-forecast-toggle
                       onclick="toggleForecastOverlay()"
                       class="forecast-toggle-btn px-3 py-1.5 text-xs font-semibold rounded-md transition-all text-[#6B7280] hover:bg-[#e5e7eb]">
                        Show Forecast
                    </button>
                </span>
                <x-chart-fullscreen-button chart="dashProductionHistoryChart" title="Egg Production History" />
            </x-slot:actions>
        </x-card-header>

        @php $tips = $tips ?? [['title' => null, 'lines' => [$insight ?? '']]]; @endphp
        <div class="interpretation-panel hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed" style="background-color: #dcebfa; color: var(--color-info); border: 1px solid #b8d4fe;">
            @foreach($tips as $group)
                <div @class(['mt-2' => ! $loop->first])>
                    @if($group['title'])<p class="font-semibold">{{ $group['title'] }}</p>@endif
                    @foreach($group['lines'] as $line)<p>{{ $line }}</p>@endforeach
                </div>
            @endforeach
        </div>
        <div id="forecastVariancePanel" class="hidden mb-3 px-3 py-2.5 rounded-lg text-xs leading-relaxed flex items-center gap-2" style="background-color:#fdf3e0; color:#8a5a00; border:1px solid #f3e3bf;"><i data-lucide="trending-up" class="w-3 h-3"></i><span id="forecastVarianceText"></span></div>

        @php
            $hasChart = ! empty($chartData['datasets']) && ($hasRecords ?? true);
            $peak = null;
            $historyTableRows = [];
            if ($hasChart) {
                foreach ($chartData['datasets'] as $ds) {
                    foreach ($ds['data'] as $i => $v) {
                        if ($v !== null && ($peak === null || $v > $peak['value'])) {
                            $peak = ['value' => $v, 'label' => $chartData['labels'][$i] ?? '', 'eggs' => $ds['eggs'][$i] ?? null, 'series' => $ds['label']];
                        }
                    }
                }
                foreach ($chartData['labels'] as $i => $label) {
                    $historyTableRows[] = ['label' => $label, 'values' => array_map(fn ($dataset) => ['hdep' => $dataset['data'][$i] ?? null, 'eggs' => $dataset['eggs'][$i] ?? null], $chartData['datasets'])];
                }
            }
            $showCageFilter = isset($cageOptions) && $cageOptions->isNotEmpty();
        @endphp
        @if($showCageFilter || $peak)
        <div class="flex flex-wrap items-center gap-2 mb-3">
            @if($showCageFilter)
            {{-- Same state as the dashboard Cage filter: drives this chart, its tips and the other production charts. --}}
            <select onchange="if (window.filterDashboard) window.filterDashboard(this.value)" aria-label="Filter egg production by cage"
                    class="cursor-pointer rounded-lg border border-[#D9D9D9] bg-white py-1 pl-2 text-xs font-semibold text-[#333333] shadow-sm transition-colors hover:border-navy/40 hover:bg-[#F5F6F8] focus:outline-none focus:ring-2 focus:ring-navy/30">
                <option value="all" @selected(! $cageCode)>All Cages</option>
                @foreach($cageOptions as $optionCode)
                    <option value="{{ $optionCode }}" @selected($cageCode === $optionCode)>{{ $optionCode }}</option>
                @endforeach
            </select>
            @endif
            @if($peak)
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: #e8f3fe; color: var(--color-navy); border: 1px solid rgba(0, 45, 94,0.2);">
                <i data-lucide="trending-up" class="w-3 h-3"></i>
                Peak HDEP: {{ number_format($peak['value'], 1) }}% on {{ $peak['label'] }}{{ $compare || $peak['eggs'] !== null ? ' (' . implode(', ', array_filter([$compare ? $peak['series'] : null, $peak['eggs'] !== null ? number_format($peak['eggs']) . ' eggs' : null])) . ')' : '' }}
            </span>
            @endif
        </div>
        @endif

        @if(! $hasChart)
            <x-empty-state icon="history" :message="$emptyMessage ?? 'No production data for the selected period.'"
                             :actionUrl="route('eggs.logging')" actionLabel="Log Eggs" />
        @else
            <div class="relative w-full h-[200px] flex-none lg:h-auto lg:flex-1 lg:min-h-[240px] chart-fade-in">
                <canvas id="dashProductionHistoryChart" role="img" aria-label="Daily HDEP chart for {{ $rangeLabel ?? 'the selected period' }}" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
            <details class="mt-3 text-sm text-ink-secondary">
                <summary class="cursor-pointer font-medium">View chart data</summary>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <caption class="sr-only">Daily HDEP and eggs by date</caption>
                        <thead><tr><th scope="col" class="border-b border-hairline px-2 py-1">Date</th>
                            @foreach($chartData['datasets'] as $dataset)<th scope="col" class="border-b border-hairline px-2 py-1">{{ $dataset['label'] }}</th>@endforeach
                        </tr></thead>
                        <tbody>
                            @foreach($historyTableRows as $row)
                                <tr><th scope="row" class="border-b border-hairline px-2 py-1 font-medium">{{ $row['label'] }}</th>
                                    @foreach($row['values'] as $value)<td class="border-b border-hairline px-2 py-1">{{ $value['hdep'] === null ? '—' : number_format($value['hdep'], 1) . '%' . ($value['eggs'] !== null ? ' (' . number_format($value['eggs']) . ' eggs)' : '') }}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>

    <script data-lucide-init>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }

    var productionChartData = @json($chartData);
    var desiredHdep = @json($desiredHdep ?? null);

    // Single series uses the HDEP metric color; Compare keeps the cage colors.
    if (!{{ $compare ? 'true' : 'false' }}) {
        productionChartData.datasets.forEach(function(ds) { ds.borderColor = LayRateChartColors.hdep; });
    }

    // Dashed Desired HDEP reference line on the HDEP axis.
    var desiredHdepPlugin = {
        id: 'desiredHdepLine',
        afterDatasetsDraw: function(chart) {
            var yAxis = chart.scales.y;
            var area = chart.chartArea;
            if (desiredHdep === null || !yAxis || !area) return;
            var y = yAxis.getPixelForValue(desiredHdep);
            if (y < area.top || y > area.bottom) return;
            var ctx = chart.ctx;
            ctx.save();
            ctx.strokeStyle = '#615d59';
            ctx.lineWidth = 1;
            ctx.setLineDash([5, 4]);
            ctx.beginPath();
            ctx.moveTo(area.left, y);
            ctx.lineTo(area.right, y);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.fillStyle = '#615d59';
            ctx.font = '600 10px Inter, system-ui, sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillText('Desired HDEP ' + desiredHdep + '%', area.right - 4, y - 3);
            ctx.restore();
        }
    };

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
            layout: { padding: { top: 12, right: 12, bottom: 0, left: 4 } },
            plugins: {
                layrateCrosshair: { enabled: true },
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
                    backgroundColor: LayRateChartColors.tooltip,
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
                            if (context.raw === null || context.raw === undefined) return ' ' + context.dataset.label + ': no record';
                            var eggs = context.dataset.eggs ? context.dataset.eggs[context.dataIndex] : null;
                            return ' ' + context.dataset.label + ': ' + context.raw + '% HDEP'
                                + (eggs !== null && eggs !== undefined ? ' (' + eggs.toLocaleString() + ' eggs)' : '');
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: 10, weight: '500' },
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 10
                    }
                },
                // HDEP sits in a narrow band, so the axis fits the data (plus a
                // little grace) instead of starting at 0, and always reaches
                // the Desired HDEP so its reference line stays visible.
                y: {
                    beginAtZero: false,
                    grace: '5%',
                    suggestedMin: desiredHdep === null ? undefined : desiredHdep,
                    suggestedMax: desiredHdep === null ? undefined : desiredHdep,
                    ticks: {
                        font: { size: 10, weight: '500' },
                        padding: 8,
                        callback: function(value) {
                            return (Math.round(value * 10) / 10) + '%';
                        }
                    }
                }
            },
            elements: {
                line: {
                    tension: 0.4,
                    borderWidth: 2,
                    fill: false
                },
                point: {
                    // Days without records are gaps; a recorded day between
                    // two gaps gets a dot so it does not vanish.
                    radius: function(ctx) {
                        var d = ctx.dataset.data, i = ctx.dataIndex;
                        return d[i] != null && d[i - 1] == null && d[i + 1] == null ? 2.5 : 0;
                    },
                    hoverRadius: 5,
                    hoverBorderWidth: 2,
                    hoverBackgroundColor: '#ffffff'
                }
            }
        },
        plugins: [desiredHdepPlugin]
    };

    if (productionChartData.labels.length && document.getElementById('dashProductionHistoryChart')) {
        if (typeof window.DashboardChartRenderer !== 'undefined') {
            window.DashboardChartRenderer.render('dashProductionHistoryChart', productionChartConfig);
        } else if (window.LayRateChart) {
            LayRateChart.create('dashProductionHistoryChart', productionChartConfig);
        }
    }

    // ── Forecast overlay — reuses the same chart, no new component ──
    window.__forecastOverlayEnabled = window.__forecastOverlayEnabled || false;
    window.__originalProductionChartData = JSON.parse(JSON.stringify(productionChartData));
    window.__originalProductionChartConfig = productionChartConfig;
    window.__originalProductionChartOptions = productionChartConfig.options;

    window.toggleForecastOverlay = function() {
        var btn = document.getElementById('forecastToggleBtn');
        window.__forecastOverlayEnabled = !window.__forecastOverlayEnabled;
        if (btn) {
            if (window.__forecastOverlayEnabled) {
                btn.style.backgroundColor = '#C2703E';
                btn.style.color = '#ffffff';
                btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.1)';
                btn.classList.remove('text-[#6B7280]', 'hover:bg-[#e5e7eb]');
            } else {
                btn.style.backgroundColor = '';
                btn.style.color = '';
                btn.style.boxShadow = '';
                btn.classList.add('text-[#6B7280]', 'hover:bg-[#e5e7eb]');
            }
        }
        if (window.__forecastOverlayEnabled) {
            loadForecastOverlay();
        } else {
            hideForecastOverlay();
        }
    };

    window.hideForecastOverlay = function() {
        window.__forecastComparisonRequest = (window.__forecastComparisonRequest || 0) + 1;
        var panel = document.getElementById('forecastVariancePanel');
        if (panel) panel.classList.add('hidden');
        // Restore the original chart. LayRateChart.setData() swaps the live
        // chart's data and options in place, and Chart.js keeps the original
        // config object as its own, so rebuild from the copies kept at load.
        if (window.__originalProductionChartConfig) {
            var restoredConfig = Object.assign({}, window.__originalProductionChartConfig, {
                data: JSON.parse(JSON.stringify(window.__originalProductionChartData)),
                options: window.__originalProductionChartOptions || window.__originalProductionChartConfig.options
            });
            if (typeof window.DashboardChartRenderer !== 'undefined') {
                window.DashboardChartRenderer.render('dashProductionHistoryChart', restoredConfig);
            } else if (window.LayRateChart) {
                LayRateChart.create('dashProductionHistoryChart', restoredConfig);
            }
        }
    };

    window.loadForecastOverlay = function() {
        var cage = typeof window.__dashboardCage !== 'undefined' ? (window.__dashboardCage === 'all' ? '' : window.__dashboardCage) : '{{ $cageCode ?? '' }}';
        var isCompare = (typeof window.__dashboardHistoryCompare !== 'undefined') ? window.__dashboardHistoryCompare : {{ $compare ? 'true' : 'false' }};
        if (isCompare) {
            // Compare shows multiple cage lines; overlay a single dashed forecast for the
            // current scope would be ambiguous. Keep the forecast as an extra line anyway
            // for Phase 3 — the variance summary is still per-date and remains useful.
        }
        var bounds = new URLSearchParams();
        bounds.set('days', '{{ $days }}');
        if (cage) bounds.set('cage', cage);
        if (window.__dashboardFromDate) bounds.set('from_date', window.__dashboardFromDate);
        if (window.__dashboardToDate) bounds.set('to_date', window.__dashboardToDate);
        var requestId = window.__forecastComparisonRequest = (window.__forecastComparisonRequest || 0) + 1;
        var url = '{{ route('dashboard.forecast-overlay') }}' + '?' + bounds.toString();
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { if (!r.ok || r.redirected) throw new Error('Could not load saved forecasts.'); return r.json(); })
            .then(function(data) {
                if (!window.__forecastOverlayEnabled || requestId !== window.__forecastComparisonRequest) return;
                if (!data.hasForecast || !data.forecast || data.forecast.every(function(v){ return v===null; })) {
                    hideForecastOverlay();
                    var p = document.getElementById('forecastVariancePanel');
                    var t = document.getElementById('forecastVarianceText');
                    if (p && t) {
                        t.textContent = 'No advance forecast saved for this period.';
                        p.classList.remove('hidden');
                        if (window.lucide) try{ lucide.createIcons(); }catch(e){}
                    }
                    return;
                }
                // Build overlay dataset — dashed eggs-blue, distinct from the
                // solid actual line via dash + legend (same metric, same hue).
                var forecastDataset = {
                    label: data.comparisonLabel || 'Forecast',
                    data: data.forecast,
                    borderColor: LayRateChartColors.eggs,
                    backgroundColor: LayRateChartColors.alpha(LayRateChartColors.eggs, 0.08),
                    borderDash: [6, 4],
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: false,
                    spanGaps: true
                };
                var mergedData = JSON.parse(JSON.stringify(window.__originalProductionChartData));
                var originalKeys = mergedData.dateKeys || [];
                mergedData.labels = data.labels;
                mergedData.dateKeys = data.dateKeys;
                // Forecasts are egg counts, so while the overlay is on the
                // actual lines switch from HDEP % to eggs per day.
                if (!isCompare && mergedData.datasets[0]) {
                    mergedData.datasets[0].data = data.actual;
                    mergedData.datasets[0].label = (data.chartData && data.chartData.datasets[0] && data.chartData.datasets[0].label) || 'Actual eggs';
                }
                else mergedData.datasets.forEach(function(dataset) {
                    var eggs = dataset.eggs || dataset.data;
                    var values = new Map(originalKeys.map(function(date, index) { return [date, eggs[index]]; }));
                    dataset.data = data.dateKeys.map(function(date) { return values.has(date) ? values.get(date) : null; });
                });
                mergedData.datasets.forEach(function(dataset) { delete dataset.eggs; });
                // Keep cage comparison lines when enabled; append the saved prediction.
                mergedData.datasets = mergedData.datasets.concat([forecastDataset]);
                // Preserve tooltip callbacks and chart plugins when copying options.
                function copyConfig(value) {
                    if (Array.isArray(value)) return value.map(copyConfig);
                    if (value && typeof value === 'object') {
                        var copy = {};
                        Object.keys(value).forEach(function(key) { copy[key] = copyConfig(value[key]); });
                        return copy;
                    }
                    return value;
                }
                var mergedConfig = copyConfig(window.__originalProductionChartConfig);
                mergedConfig.data = mergedData;
                mergedConfig.options.plugins.legend.display = true;
                mergedConfig.options.plugins.desiredHdepLine = false;
                delete mergedConfig.options.scales.y.suggestedMin;
                delete mergedConfig.options.scales.y.suggestedMax;
                mergedConfig.options.scales.y.ticks.callback = function(value) {
                    return value >= 1000 ? (value / 1000).toFixed(1) + 'k' : value;
                };
                mergedConfig.options.plugins.tooltip = mergedConfig.options.plugins.tooltip || {};
                mergedConfig.options.plugins.tooltip.callbacks = mergedConfig.options.plugins.tooltip.callbacks || {};
                mergedConfig.options.plugins.tooltip.callbacks.label = function(context) {
                    if (context.raw === null || context.raw === undefined) return ' ' + context.dataset.label + ': no record';
                    return ' ' + context.dataset.label + ': ' + context.raw.toLocaleString() + ' eggs';
                };
                mergedConfig.options.plugins.tooltip.callbacks.afterBody = function(items) {
                    if (!items.length) return '';
                    var date = data.dateKeys[items[0].dataIndex];
                    var detail = data.predictionDetails && data.predictionDetails[date];
                    return detail ? 'Issued ' + detail.forecast_date + ' ? ' + detail.lead_days + ' days ahead' : '';
                };
                if (typeof window.DashboardChartRenderer !== 'undefined') {
                    window.DashboardChartRenderer.render('dashProductionHistoryChart', mergedConfig);
                } else if (window.LayRateChart) {
                    LayRateChart.create('dashProductionHistoryChart', mergedConfig);
                }
                var panel2 = document.getElementById('forecastVariancePanel');
                var text2 = document.getElementById('forecastVarianceText');
                if (panel2 && text2 && data.summary) {
                    text2.textContent = data.summary;
                    panel2.classList.remove('hidden');
                    if (window.lucide) try{ lucide.createIcons(); }catch(e){}
                } else if (panel2) {
                    panel2.classList.add('hidden');
                }
            })
            .catch(function(err) {
                if (!window.__forecastOverlayEnabled || requestId !== window.__forecastComparisonRequest) return;
                console.error('Forecast overlay failed', err);
                var p3 = document.getElementById('forecastVariancePanel');
                var t3 = document.getElementById('forecastVarianceText');
                if (p3 && t3) {
                    t3.textContent = 'Forecast overlay failed to load.';
                    p3.classList.remove('hidden');
                }
            });
    };

    // If the page was reloaded with forecast overlay previously enabled (Turbo cache),
    // reset to off — explicit opt-in per spec, not sticky.
    (function(){
        var p = document.getElementById('forecastVariancePanel');
        if (p) p.classList.add('hidden');
        var b = document.getElementById('forecastToggleBtn');
        if (b) {
            b.style.backgroundColor = '';
            b.style.color = '';
            b.style.boxShadow = '';
        }
        window.__forecastOverlayEnabled = false;
    })();
    </script>
</turbo-frame>
