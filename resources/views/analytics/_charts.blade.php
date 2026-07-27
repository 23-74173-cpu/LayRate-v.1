<turbo-frame id="analytics-charts">
    @php
        $hasLogs = $logs->isNotEmpty();
        $hasFeedOverlap = $logs->isNotEmpty() && $feedLogs->isNotEmpty();
        $displayLabel = $isAll ? 'FARM' : $cageCode;
    @endphp

    {{-- ── HDEP Trend Chart ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-8">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">
            <span id="chart-title-period">{{ strtoupper($period === 'week' ? '7' : ($period === 'month' ? '30' : '90')) }}</span>-DAY HDEP TREND — <span id="chart-title-label-hdep">{{ $displayLabel }}</span>
        </div>
        <div id="hdepChartWrap" class="relative w-full h-[310px]" style="display:{{ $hasLogs ? '' : 'none' }}">
            <canvas id="hdepChart" style="width: 100%; height: 100%; display: block;"></canvas>
        </div>
        <div id="hdepChartEmpty" class="h-[100px] flex items-center justify-center text-sm" style="color: #a39e98; display:{{ $hasLogs ? 'none' : '' }};">No production data for this period.</div>
    </div>

    {{-- ── Two small charts ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-5">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">EGGS COLLECTED PER DAY — <span id="chart-title-label-eggs">{{ $displayLabel }}</span></div>
            <div id="eggsChartWrap" class="relative w-full h-[189px]" style="display:{{ $hasLogs ? '' : 'none' }}">
                <canvas id="eggsChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
            <div id="eggsChartEmpty" class="h-[130px] flex items-center justify-center text-sm" style="color: #a39e98; display:{{ $hasLogs ? 'none' : '' }};">No production data for this period.</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FEED VS HDEP — <span id="chart-title-label-feed">{{ $displayLabel }}</span></div>
            <div id="feedHdepChartWrap" class="relative w-full h-[189px]" style="display:{{ $hasFeedOverlap ? '' : 'none' }}">
                <canvas id="feedHdepChart" style="width: 100%; height: 100%; display: block;"></canvas>
            </div>
            <div id="feedHdepChartEmpty" class="h-[130px] flex items-center justify-center text-sm" style="color: #a39e98; display:{{ $hasFeedOverlap ? 'none' : '' }};">No overlapping feed and production data for this period.</div>
        </div>
    </div>

    <script>
    (function() {
        var logs = @json($logs->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'hdep'=>(float)$l->hdep,'eggs'=>(int)$l->egg_count]));
        var feedLogs = @json($feedLogs->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'kg'=>(float)$l->feed_consumed_kg]));
        var cageColor = '{{ $isAll ? '#002D5E' : $cage->color }}';
        var isAll = {{ $isAll ? 'true' : 'false' }};
        var cageCode = '{{ $cageCode }}';

        // Every render — including this frame's own initial one (covers landing here via
        // the sidebar) — rebuilds from a clean Chart.js module first, not just when a
        // chart is detected broken. If a tab click's own rebuild (analytics.blade.php's
        // analyticsFetch) starts while this one is still reloading, LayRateChart's
        // generation counter (bumped synchronously by prepareForRender(), before the
        // async reload) makes the stale one here no-op instead of overwriting the
        // click's result — same race prepareForRender() replaced the old setTimeout(0)
        // fix for, still handled, just via generation ordering instead of timing.
        function doRender() {
            if (typeof window.renderAnalyticsCharts === 'function') {
                window.renderAnalyticsCharts(logs, feedLogs, cageColor, isAll, cageCode);
            }
        }
        if (window.LayRateChart && typeof window.LayRateChart.prepareForRender === 'function') {
            var pending = window.LayRateChart.prepareForRender();
            var genAtStart = window.LayRateChart._generation;
            pending.then(function() {
                if (window.LayRateChart._generation !== genAtStart) return; // superseded
                doRender();
            });
        } else {
            doRender();
        }
    })();
    </script>
</turbo-frame>
