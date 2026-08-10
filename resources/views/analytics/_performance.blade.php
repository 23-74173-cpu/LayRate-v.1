<turbo-frame id="analytics-charts">
    @php
        $dayCount = $period === 'week' ? 7 : ($period === 'month' ? 30 : 90);
        $daysLabel = strtoupper($dayCount);
        $topPerformer = $performance->first();
        $topEggs = $performance->firstWhere('cage_code', $topEggsCage);
    @endphp

    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">
            CAGE PERFORMANCE — {{ $daysLabel }}-DAY RANKING BY HDEP &amp; EGGS COLLECTED
        </div>

        {{-- 📈 Top 3 + "view all" ranking table --}}
        @php $totalRanks = $performance->count(); @endphp
        @if($performance->isEmpty())
            <div class="h-[100px] flex items-center justify-center text-sm" style="color: #a39e98;">No production data for this period.</div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div class="rounded-lg border border-[#D9D9D9] p-4">
                    <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Top Performer</div>
                    <div class="flex items-center gap-3">
                        <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $topPerformer['color'] }}"></span>
                        <div>
                            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#1f1f1f]">{{ $topPerformer['cage_code'] }}</div>
                            <div class="text-xs text-[#6B7280] mt-1">Avg HDEP {{ $topPerformer['avg_hdep'] }}% &middot; {{ $topPerformer['total_eggs'] }} eggs collected</div>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-[#D9D9D9] p-4">
                    <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Most Eggs Collected</div>
                    <div class="flex items-center gap-3">
                        <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $topEggs ? $topEggs['color'] : 'transparent' }}"></span>
                        <div>
                            <div class="text-2xl font-bold leading-tight tracking-[-0.5px] text-[#333333]">{{ $topEggs ? $topEggs['cage_code'] : '—' }}</div>
                            <div class="text-xs text-[#6B7280] mt-1">{{ $topEggs ? $topEggs['total_eggs'] . ' eggs' : '' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Comparison bar charts with value-on-top labels ── --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-5">
                <div class="rounded-lg border border-[#D9D9D9] p-5">
                    <div class="text-xs tracking-wider text-[#6B7280] mb-4">AVG HDEP BY CAGE — {{ $daysLabel }}-DAY</div>
                    <div class="relative w-full h-[220px]">
                        <canvas id="perfHdepChart" style="width: 100%; height: 100%; display: block;"></canvas>
                    </div>
                </div>
                <div class="rounded-lg border border-[#D9D9D9] p-5">
                    <div class="text-xs tracking-wider text-[#6B7280] mb-4">TOTAL EGGS COLLECTED BY CAGE — {{ $daysLabel }}-DAY</div>
                    <div class="relative w-full h-[220px]">
                        <canvas id="perfEggsChart" style="width: 100%; height: 100%; display: block;"></canvas>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-[#D9D9D9]">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.125px] text-[#6B7280]" style="background-color:#F7F7F5; color:#6B7280;">
                            <th class="px-5 py-3.5 text-left">Rank</th>
                            <th class="px-5 py-3.5 text-left">Cage</th>
                            <th class="px-5 py-3.5 text-left">Breed</th>
                            <th class="px-5 py-3.5 text-right">Avg HDEP</th>
                            <th class="px-5 py-3.5 text-right">Total Eggs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($performance as $p)
                        <tr class="border-t {{ $p['rank'] > 3 ? 'perf-row-extra' : '' }}" style="border-color:#EFEFEC; {{ $p['rank'] > 3 ? 'display:none;' : '' }}" {{ $p['rank'] > 3 ? 'hidden' : '' }}>
                            <td class="px-5 py-3.5">
                                @if($p['rank'] === 1)
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold" style="background:{{ $p['color'] }}22; color: {{ $p['color'] }}; border: 1px solid {{ $p['color'] }}55;">
                                    <i data-lucide="trophy" class="w-3 h-3"></i> #{{ $p['rank'] }}
                                </span>
                                @else
                                <span class="text-sm text-[#333333]">{{ $p['rank'] }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-[#1f1f1f]">
                                    <span class="inline-block w-2 h-2 rounded-full" style="background-color: {{ $p['color'] }}"></span>{{ $p['cage_code'] }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $p['breed'] }}</td>
                            <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $p['avg_hdep'] === null ? '—' : $p['avg_hdep'] . '%' }}</td>
                            <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $p['total_eggs'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($totalRanks > 3)
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-[#6B7280]">Showing top 3 of {{ $totalRanks }} cages</span>
                <button type="button" data-perf-toggle aria-expanded="false"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-[#D9D9D9] px-3.5 py-2 text-xs font-semibold text-[#002D5E] transition-colors hover:bg-[#F7F7F5] focus:outline-none">
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                    <span data-perf-toggle-label>Show Full Ranking</span>
                </button>
            </div>
            @endif
            <div class="mt-3 text-xs text-[#6B7280]">Ranked by average HDEP, then by total eggs collected over the {{ $daysLabel }}-day period.</div>
        @endif
    </div>

    <script data-lucide-init>
    // Ensure icons (e.g. trophy) render inside this lazy frame after Turbo swaps it in.
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }
    // ── Top 3 / "Show Full Ranking" toggle (event-delegated so it survives frame swaps) ──
    if (!window.__analyticsPerfToggleBound) {
        window.__analyticsPerfToggleBound = true;
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
                if (chev) { chev.setAttribute('data-lucide', 'chevron-down'); chev.classList.add('rotate-0'); }
            } else {
                rows.forEach(function (r) { r.style.display = ''; r.removeAttribute('hidden'); });
                btn.setAttribute('aria-expanded', 'true');
                if (label) label.textContent = 'Show Top 3';
                if (chev) { chev.setAttribute('data-lucide', 'chevron-up'); }
            }
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                try { window.lucide.createIcons(); } catch (e2) {}
            }
        });
    }

    // ── Comparison bar charts (avg HDEP & total eggs) with value-on-top labels ──
    var perfData = @json($performance);
    var perfLabels = perfData.map(function (p) { return p.cage_code; });
    var perfHdeps = perfData.map(function (p) { return p.avg_hdep; });
    var perfEggs = perfData.map(function (p) { return p.total_eggs; });
    var perfColors = perfData.map(function (p) { return p.color; });

    // Inline Chart.js plugin that draws each bar's value right above it.
    function perfValueLabelPlugin() {
        return {
            id: 'perfValueLabels',
            afterDatasetsDraw: function (chart) {
                var ctx = chart.ctx;
                chart.data.datasets.forEach(function (ds, di) {
                    var meta = chart.getDatasetMeta(di);
                    if (!meta.data || !meta.data.length) return;
                    meta.data.forEach(function (bar, i) {
                        var v = ds.data[i];
                        if (v === null || v === undefined) return;
                        ctx.save();
                        ctx.font = '600 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillStyle = '#1f1f1f';
                        ctx.fillText(String(v), bar.x, bar.y - 5);
                        ctx.restore();
                    });
                });
            }
        };
    }

    var perfGridColor = '#F0F0EC';
    function perfBarOpts() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 16 } },
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { beginAtZero: true, grid: { color: perfGridColor }, ticks: { font: { size: 10 } } }
            }
        };
    }

    // Global so LayRateChart self-heal (layouts/app.blade.php) can rebuild these when a
    // bar chart fails to paint (recovery already reloaded the Chart.js library).
    window.renderPerformanceCharts = function () {
        LayRateChart.create('perfHdepChart', {
            type: 'bar',
            data: { labels: perfLabels, datasets: [{ data: perfHdeps, backgroundColor: perfColors, borderRadius: 3 }] },
            options: perfBarOpts(),
            plugins: [perfValueLabelPlugin()]
        });
        LayRateChart.create('perfEggsChart', {
            type: 'bar',
            data: { labels: perfLabels, datasets: [{ data: perfEggs, backgroundColor: perfColors, borderRadius: 3 }] },
            options: perfBarOpts(),
            plugins: [perfValueLabelPlugin()]
        });
    };

    // Initial render through the standard prepareForRender path (generation-guarded).
    function doPerfRender() {
        if (typeof window.renderPerformanceCharts === 'function') window.renderPerformanceCharts();
    }
    if (window.LayRateChart && typeof window.LayRateChart.prepareForRender === 'function') {
        var perfPending = window.LayRateChart.prepareForRender();
        var perfGen = window.LayRateChart._generation;
        perfPending.then(function () {
            if (window.LayRateChart._generation !== perfGen) return; // superseded
            doPerfRender();
        });
    } else {
        doPerfRender();
    }
    </script>
</turbo-frame>