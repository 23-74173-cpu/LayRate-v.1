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
        <canvas id="hdepChart" height="100" style="display:{{ $hasLogs ? '' : 'none' }}"></canvas>
        <div id="hdepChartEmpty" class="h-[100px] flex items-center justify-center text-sm" style="color: #a39e98; display:{{ $hasLogs ? 'none' : '' }};">No production data for this period.</div>
    </div>

    {{-- ── Two small charts ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-5">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">EGGS COLLECTED PER DAY — <span id="chart-title-label-eggs">{{ $displayLabel }}</span></div>
            <canvas id="eggsChart" height="130" style="display:{{ $hasLogs ? '' : 'none' }}"></canvas>
            <div id="eggsChartEmpty" class="h-[130px] flex items-center justify-center text-sm" style="color: #a39e98; display:{{ $hasLogs ? 'none' : '' }};">No production data for this period.</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FEED VS HDEP — <span id="chart-title-label-feed">{{ $displayLabel }}</span></div>
            <canvas id="feedHdepChart" height="130" style="display:{{ $hasFeedOverlap ? '' : 'none' }}"></canvas>
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

        function initCharts() {
            if (typeof window.renderAnalyticsCharts === 'function') {
                window.renderAnalyticsCharts(logs, feedLogs, cageColor, isAll, cageCode);
            }
        }

        setTimeout(initCharts, 0);
    })();
    </script>
</turbo-frame>
