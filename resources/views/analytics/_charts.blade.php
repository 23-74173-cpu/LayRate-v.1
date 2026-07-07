<turbo-frame id="analytics-charts">
    {{-- ── HDEP Trend Chart ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-8">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">
            {{ strtoupper($period === 'week' ? '7' : ($period === 'month' ? '30' : '90')) }}-DAY HDEP TREND — {{ $cageCode }}
        </div>
        <canvas id="hdepChart" height="100"></canvas>
    </div>

    {{-- ── Two small charts ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-5">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">EGGS COLLECTED PER DAY — {{ $cageCode }}</div>
            <canvas id="eggsChart" height="130"></canvas>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FEED VS HDEP — {{ $cageCode }}</div>
            <canvas id="feedHdepChart" height="130"></canvas>
        </div>
    </div>

    {{-- ── Summary Row ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">
            @php $cColor = match($cageCode){'CAGE-A'=>'#2D7D46','CAGE-B'=>'#1D4E8F','CAGE-C'=>'#C2703E','CAGE-D'=>'#6B4C8A',default=>'#6B7280'}; @endphp
            <div>
                <div class="text-xs text-[#6B7280] mb-1">CAGE</div>
                <div class="text-sm font-semibold" style="color:{{ $cColor }}">{{ $cageCode }}</div>
            </div>
            <div>
                <div class="text-xs text-[#6B7280] mb-1">BREED</div>
                <div class="text-sm text-[#333333]">{{ $cage->hens->first()?->breed ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs text-[#6B7280] mb-1">AVG HDEP</div>
                <div class="text-sm text-[#333333]">{{ $avgHdep }}%</div>
            </div>
            <div>
                <div class="text-xs text-[#6B7280] mb-1">BEST DAY</div>
                <div class="text-sm text-[#333333]">{{ $bestDay }}%</div>
            </div>
            <div>
                <div class="text-xs text-[#6B7280] mb-1">WORST DAY</div>
                <div class="text-sm text-[#333333]">{{ $worstDay }}%</div>
            </div>
            <div>
                <div class="text-xs text-[#6B7280] mb-1">FLOCK AGE</div>
                <div class="text-sm text-[#333333]">{{ $cage->hens->first() ? $cage->hens->first()->current_age_weeks . ' wks' : '—' }}</div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const logs = @json($logs->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'hdep'=>$l->hdep,'eggs'=>$l->egg_count]));
        const feedLogs = @json($feedLogs->map(fn($l) => ['date'=>$l->log_date->format('Y-m-d'),'kg'=>$l->feed_consumed_kg]));
        const cageColor = '{{ match($cageCode){"CAGE-A"=>"#2D7D46","CAGE-B"=>"#1D4E8F","CAGE-C"=>"#C2703E","CAGE-D"=>"#6B4C8A",default=>"#2D7D46"} }}';

        const labels = logs.map(l => l.date.slice(5));
        const hdeps  = logs.map(l => l.hdep);
        const eggs   = logs.map(l => l.eggs);

        const gridColor = '#F0F0EC';
        const baseOpts = {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { font: { size: 10 } } },
                y: { grid: { color: gridColor }, ticks: { font: { size: 10 } } },
            }
        };

        function initAnalyticsCharts() {
            if (window.hdepChart) window.hdepChart.destroy();
            window.hdepChart = new Chart(document.getElementById('hdepChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: hdeps, borderColor: cageColor, backgroundColor: cageColor+'22',
                        tension: 0.3, pointRadius: 4, fill: true, borderWidth: 2
                    }]
                },
                options: { ...baseOpts, scales: { ...baseOpts.scales, y: { ...baseOpts.scales.y, min: 50, max: 100 } } }
            });

            if (window.eggsChart) window.eggsChart.destroy();
            window.eggsChart = new Chart(document.getElementById('eggsChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ data: eggs, backgroundColor: cageColor, borderRadius: 3 }]
                },
                options: baseOpts
            });

            const feedMap = {};
            feedLogs.forEach(f => feedMap[f.date] = f.kg);
            const scatter = logs.map(l => ({ x: feedMap[l.date] || 0, y: l.hdep }));

            if (window.feedHdepChart) window.feedHdepChart.destroy();
            window.feedHdepChart = new Chart(document.getElementById('feedHdepChart'), {
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

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
        } else {
            initAnalyticsCharts();
        }
    })();
    </script>
</turbo-frame>
