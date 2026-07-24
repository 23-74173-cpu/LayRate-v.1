<turbo-frame id="analytics-charts">
    @php
        $hasLogs = $logs->isNotEmpty();
        $hasFeedOverlap = $logs->isNotEmpty() && $feedLogs->isNotEmpty();
    @endphp

    {{-- ── HDEP Trend Chart ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-8">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">
            {{ strtoupper($period === 'week' ? '7' : ($period === 'month' ? '30' : '90')) }}-DAY HDEP TREND — {{ $cageCode }}
        </div>
        @if($hasLogs)
        <canvas id="hdepChart" height="100"></canvas>
        @else
        <div class="h-[100px] flex items-center justify-center text-sm" style="color: #a39e98;">No production data for this period.</div>
        @endif
    </div>

    {{-- ── Two small charts ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-5">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">EGGS COLLECTED PER DAY — {{ $cageCode }}</div>
            @if($hasLogs)
            <canvas id="eggsChart" height="130"></canvas>
            @else
            <div class="h-[130px] flex items-center justify-center text-sm" style="color: #a39e98;">No production data for this period.</div>
            @endif
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FEED VS HDEP — {{ $cageCode }}</div>
            @if($hasFeedOverlap)
            <canvas id="feedHdepChart" height="130"></canvas>
            @else
            <div class="h-[130px] flex items-center justify-center text-sm" style="color: #a39e98;">No overlapping feed and production data for this period.</div>
            @endif
        </div>
    </div>

    <script>
    (function() {
        const logs = @json($logs->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'hdep'=>$l->hdep,'eggs'=>$l->egg_count]));
        const feedLogs = @json($feedLogs->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'kg'=>$l->feed_consumed_kg]));
        const cageColor = '{{ $cage->color }}';

        const labels = logs.map(l => l.date.slice(5));
        const hdeps  = logs.map(l => l.hdep);
        const eggs   = logs.map(l => l.eggs);

        const gridColor = '#F0F0EC';
        const baseOpts = {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                // autoSkip + rotation: a 90-day view has up to 90 date labels —
                // without this they overlap into unreadable text.
                x: { grid: { color: gridColor }, ticks: { font: { size: 10 }, autoSkip: true, autoSkipPadding: 12, maxRotation: 45, minRotation: 0 } },
                y: { grid: { color: gridColor }, ticks: { font: { size: 10 } } },
            }
        };

        // Chart instances live in a namespaced store — never bare window.<canvasId>
        // (e.g. window.hdepChart): the browser auto-exposes every element with an id
        // as a global pointing at the DOM node itself, which shadows the cache and
        // has no .destroy(), crashing this function before any chart renders.
        const charts = window.__analyticsCharts = window.__analyticsCharts || {};
        const destroyChart = (key) => {
            if (charts[key] && typeof charts[key].destroy === 'function') charts[key].destroy();
            charts[key] = null;
        };

        function initAnalyticsCharts() {
            const hdepCanvas = document.getElementById('hdepChart');
            if (hdepCanvas) {
                destroyChart('hdep');
                charts.hdep = new Chart(hdepCanvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            data: hdeps, borderColor: cageColor, backgroundColor: cageColor+'22',
                            tension: 0.3, pointRadius: 4, fill: true, borderWidth: 2
                        }]
                    },
                    // 0–100, not a hardcoded 50–100 floor — HDEP can genuinely
                    // fall below 50% (early lay, a bad day), and a fixed 50
                    // floor clipped those points off the chart entirely.
                    options: { ...baseOpts, scales: { ...baseOpts.scales, y: { ...baseOpts.scales.y, min: 0, max: 100 } } }
                });
            }

            const eggsCanvas = document.getElementById('eggsChart');
            if (eggsCanvas) {
                destroyChart('eggs');
                charts.eggs = new Chart(eggsCanvas, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{ data: eggs, backgroundColor: cageColor, borderRadius: 3 }]
                    },
                    options: baseOpts
                });
            }

            const feedHdepCanvas = document.getElementById('feedHdepChart');
            if (feedHdepCanvas) {
                const feedMap = {};
                feedLogs.forEach(f => feedMap[f.date] = f.kg);
                const scatter = logs
                    .filter(l => feedMap[l.date] !== undefined)
                    .map(l => ({ x: feedMap[l.date], y: l.hdep }));

                destroyChart('feedHdep');
                charts.feedHdep = new Chart(feedHdepCanvas, {
                    type: 'scatter',
                    data: {
                        datasets: [{
                            data: scatter, backgroundColor: cageColor, pointRadius: 6
                        }]
                    },
                    options: {
                        ...baseOpts,
                        scales: {
                            x: { ...baseOpts.scales.x, title: { display: true, text: 'kg', font: { size: 10 } } },
                            y: { ...baseOpts.scales.y, title: { display: true, text: '%',  font: { size: 10 } }, min: 0, max: 100 },
                        }
                    }
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
        } else {
            initAnalyticsCharts();
        }
    })();
    </script>
</turbo-frame>
