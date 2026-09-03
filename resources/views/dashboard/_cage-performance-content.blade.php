@php
$showDayFilter = $showDayFilter ?? true;
$chartRenderFn = $chartRenderFn ?? 'renderDashPerformanceCharts';

$performance = $cages->map(function ($cage) {
    return [
        'cage_code' => $cage->cage_code,
        'color' => $cage->color,
        'color_soft' => $cage->colorSoft,
        'breed' => $cage->breed,
        'hdep' => $cage->period_hdep ?? $cage->today_hdep,
        'eggs' => $cage->period_eggs ?? $cage->today_eggs,
        'hen_count' => $cage->hen_count,
    ];
})->sort(function ($a, $b) {
    // Rank by production (eggs) first, then by HDEP efficiency.
    if ($a['eggs'] !== $b['eggs']) {
        return $b['eggs'] <=> $a['eggs'];
    }
    return $b['hdep'] <=> $a['hdep'];
})->values()->map(function ($item, $index) {
    $item['rank'] = $index + 1;
    return $item;
});

$totalEggs = $performance->sum('eggs');
$hasData = $totalEggs > 0 || $performance->contains(fn ($p) => $p['hdep'] > 0);
@endphp

<style>
    @keyframes perf-row-in {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes chartFadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .chart-fade-in {
        animation: chartFadeIn 0.35s ease-out both;
    }
    .perf-animate-row {
        animation: perf-row-in 0.35s ease-out both;
    }
    .perf-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .perf-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .perf-table-row {
        transition: background-color 0.15s ease;
    }
    .perf-table-row:hover {
        background-color: #FAFAF8;
    }
    .perf-progress {
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

<div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 h-full flex flex-col">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="flex items-start gap-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: #e8f3fe; color: #0075de;">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
            </span>
            <div>
                <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Cage Performance Overview</div>
            </div>
        </div>
        @if($showDayFilter)
        <div class="inline-flex items-center gap-1 rounded-lg p-1 shrink-0" style="background-color: #f3f4f6;">
            @foreach([1, 7, 14, 30] as $d)
            <button type="button"
               data-perf-days="{{ $d }}"
               onclick="setCagePerformanceDays({{ $d }})"
               class="perf-days-btn px-3 py-1.5 text-xs font-semibold rounded-md transition-all {{ $days === $d ? 'perf-days-active' : 'text-[#6B7280] hover:bg-[#e5e7eb]' }}"
               {{ $days === $d ? 'style="background-color: #0075de; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"' : '' }}>
                {{ $d === 1 ? 'Today' : $d . 'D' }}
            </button>
            @endforeach
        </div>
        @endif
    </div>

    @if(! $hasData)
        <div class="rounded-xl border py-8 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
            No production data recorded for the selected period.
        </div>
    @else
        {{-- Ranking table (top of section) --}}
        <div class="rounded-xl border border-[#D9D9D9] overflow-x-auto mb-4">
            <table class="w-full min-w-[280px] text-left border-collapse">
                <thead>
                    <tr class="text-left text-[10px] font-semibold uppercase tracking-[0.125px] text-[#6B7280]" style="background-color:#F7F7F5;">
                        <th class="px-3 py-2.5 text-left w-12">Rank</th>
                        <th class="px-3 py-2.5 text-left">Cage</th>
                        <th class="px-3 py-2.5 text-right w-12">Hens</th>
                        <th class="px-3 py-2.5 text-right w-20">HDEP</th>
                        <th class="px-3 py-2.5 text-right w-14">Eggs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($performance as $p)
                    <tr class="perf-table-row border-t perf-animate-row {{ $p['rank'] > 3 ? 'perf-row-extra' : '' }}"
                        style="border-color:#EFEFEC; animation-delay: {{ $p['rank'] * 40 }}ms; {{ $p['rank'] > 3 ? 'display:none;' : '' }}"
                        {{ $p['rank'] > 3 ? 'hidden' : '' }}>
                        <td class="px-3 py-2">
                            @if($p['rank'] === 1)
                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" style="background:{{ $p['color'] }}22; color: {{ $p['color'] }}; border: 1px solid {{ $p['color'] }}55;">
                                <i data-lucide="trophy" class="w-2.5 h-2.5"></i> 1
                            </span>
                            @else
                            <span class="text-xs text-[#333333] font-medium">{{ $p['rank'] }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[#1f1f1f]">
                                <span class="inline-block w-2 h-2 rounded-full" style="background-color: {{ $p['color'] }}"></span>{{ $p['cage_code'] }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-[#333333] text-right">{{ $p['hen_count'] }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <span class="text-xs text-[#333333] whitespace-nowrap">{{ number_format($p['hdep'], 1) }}%</span>
                                <div class="w-8 h-1.5 rounded-full overflow-hidden" style="background-color: #f0f0f0;">
                                    <div class="perf-progress h-full rounded-full" style="width: {{ min(100, $p['hdep']) }}%; background-color: {{ $p['color_soft'] }}; border: 1px solid {{ $p['color'] }};"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-[#333333] text-right font-mono font-medium">{{ number_format($p['eggs']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($performance->count() > 3)
        <div class="mb-4 flex items-center justify-between">
            <span class="text-xs text-[#6B7280]">Showing top 3 of {{ $performance->count() }} cages</span>
            <button type="button" data-perf-toggle aria-expanded="false"
                    class="inline-flex items-center gap-1 rounded-lg border border-[#D9D9D9] px-3 py-1.5 text-xs font-semibold text-[#002D5E] transition-colors hover:bg-[#F7F7F5] focus:outline-none">
                <i data-lucide="chevron-down" class="w-3 h-3"></i>
                <span data-perf-toggle-label>Show Full Ranking</span>
            </button>
        </div>
        @endif

        {{-- Comparison charts side-by-side: HDEP bar (left), Eggs pie (right) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="perf-card rounded-xl border border-[#D9D9D9] p-4 bg-white chart-fade-in">
                <div class="text-[11px] font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-2">HDEP by Cage {{ $days === 1 ? '(Today)' : '(' . $days . 'D)' }}</div>
                <div class="relative w-full h-[170px]">
                    <canvas id="dashHdepChart" style="width: 100%; height: 100%; display: block;"></canvas>
                </div>
            </div>
            <div class="perf-card rounded-xl border border-[#D9D9D9] p-4 bg-white chart-fade-in">
                <div class="text-[11px] font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-2">Eggs Distribution by Cage</div>
                <div class="relative w-full h-[170px]">
                    <canvas id="dashEggsChart" style="width: 100%; height: 100%; display: block;"></canvas>
                </div>
            </div>
        </div>

        <div class="mt-3 text-xs text-[#6B7280]">
            @if($days === 1)
                Ranked by eggs collected today, then by HDEP.
            @else
                Ranked by eggs collected over the last {{ $days }} days, then by HDEP.
            @endif
        </div>
    @endif
</div>

<script data-lucide-init>
// Ensure icons render inside this lazy frame after Turbo swaps it in.
if (window.lucide && typeof window.lucide.createIcons === 'function') {
    try { window.lucide.createIcons(); } catch (e) {}
}

// Top 3 / "Show Full Ranking" toggle (event-delegated so it survives frame swaps).
if (!window.__dashPerfToggleBound) {
    window.__dashPerfToggleBound = true;
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-perf-toggle]');
        if (!btn) return;
        e.preventDefault();
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        var frame = btn.closest('turbo-frame');
        if (!frame) return;
        var rows = frame.querySelectorAll('tr.perf-row-extra');
        var label = btn.querySelector('[data-perf-toggle-label]');
        var chev = btn.querySelector('[data-lucide]');
        if (expanded) {
            rows.forEach(function (r) { r.style.display = 'none'; r.setAttribute('hidden', ''); });
            btn.setAttribute('aria-expanded', 'false');
            if (label) label.textContent = 'Show Full Ranking';
            if (chev) { chev.setAttribute('data-lucide', 'chevron-down'); }
        } else {
            rows.forEach(function (r) {
                r.style.display = '';
                r.removeAttribute('hidden');
                // Re-trigger stagger animation for newly visible rows.
                r.style.animation = 'none';
                void r.offsetWidth;
                r.style.animation = '';
            });
            btn.setAttribute('aria-expanded', 'true');
            if (label) label.textContent = 'Show Top 3';
            if (chev) { chev.setAttribute('data-lucide', 'chevron-up'); }
        }
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            try { window.lucide.createIcons(); } catch (e2) {}
        }
    });
}

// ── Comparison bar charts (today's HDEP & eggs) with value-on-top labels ──
var perfData = @json($performance);
var perfLabels = perfData.map(function (p) { return p.cage_code; });
var perfHdeps = perfData.map(function (p) { return p.hdep; });
var perfEggs = perfData.map(function (p) { return p.eggs; });
var perfColors = perfData.map(function (p) { return p.color; });
var perfSoftColors = perfData.map(function (p) { return p.color_soft; });

function perfValueLabelPlugin() {
    return {
        id: 'perfValueLabels',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx;
            var isBar = chart.config.type === 'bar';
            chart.data.datasets.forEach(function (ds, di) {
                var meta = chart.getDatasetMeta(di);
                if (!meta.data || !meta.data.length) return;
                meta.data.forEach(function (el, i) {
                    var v = ds.data[i];
                    // Skip missing/zero values on bar charts so a stray "0" doesn't float below the baseline.
                    if (v === null || v === undefined || (isBar && v === 0)) return;
                    var pos = el.tooltipPosition ? el.tooltipPosition() : { x: el.x, y: el.y };
                    ctx.save();
                    ctx.font = '600 11px Inter, system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillStyle = perfColors[i] || '#1f1f1f';
                    ctx.fillText(String(v) + (isBar ? '%' : ''), pos.x, pos.y - 5);
                    ctx.restore();
                });
            });
        }
    };
}

function perfBarOpts() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        layout: { padding: { top: 16 } },
        plugins: { legend: { display: false } },
        scales: {
            x: {},
            y: { beginAtZero: true }
        }
    };
}

function perfPieOpts() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        layout: { padding: { top: 8, bottom: 8, left: 8, right: 8 } },
        plugins: {
            legend: {
                display: true,
                position: 'right',
                labels: {
                    generateLabels: function (chart) {
                        var data = chart.data;
                        var total = data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
                        return data.labels.map(function (label, i) {
                            var value = data.datasets[0].data[i];
                            var pct = total > 0 ? Math.round((value / total) * 100) : 0;
                            return {
                                text: label + ' · ' + value + ' (' + pct + '%)',
                                fillStyle: data.datasets[0].backgroundColor[i],
                                strokeStyle: data.datasets[0].backgroundColor[i],
                                lineWidth: 0,
                                hidden: false,
                                index: i
                            };
                        });
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function (context) {
                        var total = context.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                        var pct = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                        return context.label + ': ' + context.raw + ' eggs (' + pct + '%)';
                    }
                }
            }
        }
    };
}

window['{{ $chartRenderFn }}'] = function () {
    if (!perfData.length) return;
    if (typeof window.DashboardChartRenderer !== 'undefined') {
        window.DashboardChartRenderer.render('dashHdepChart', {
            type: 'bar',
            data: { labels: perfLabels, datasets: [{ data: perfHdeps, backgroundColor: perfSoftColors, borderColor: perfColors, borderWidth: 1, borderRadius: 3 }] },
            options: perfBarOpts(),
            plugins: [perfValueLabelPlugin()]
        });
        window.DashboardChartRenderer.render('dashEggsChart', {
            type: 'pie',
            data: { labels: perfLabels, datasets: [{ data: perfEggs, backgroundColor: perfSoftColors, borderColor: perfColors, borderWidth: 1 }] },
            options: perfPieOpts()
        });
    } else {
        // Fallback if the shared renderer is not available.
        LayRateChart.create('dashHdepChart', {
            type: 'bar',
            data: { labels: perfLabels, datasets: [{ data: perfHdeps, backgroundColor: perfSoftColors, borderColor: perfColors, borderWidth: 1, borderRadius: 3 }] },
            options: perfBarOpts(),
            plugins: [perfValueLabelPlugin()]
        });
        LayRateChart.create('dashEggsChart', {
            type: 'pie',
            data: { labels: perfLabels, datasets: [{ data: perfEggs, backgroundColor: perfSoftColors, borderColor: perfColors, borderWidth: 1 }] },
            options: perfPieOpts()
        });
    }
};

window['{{ $chartRenderFn }}']();
</script>