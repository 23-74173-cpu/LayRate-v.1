<turbo-frame id="analytics-charts">
    @php
        $hasLogs = $logs->isNotEmpty();
        $hasFeedOverlap = $logs->isNotEmpty() && $feedLogs->isNotEmpty();
        $displayLabel = $isAll ? 'FARM' : $cageCode;
    @endphp

    {{-- ── HDEP Trend Chart ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-8">
        <div class="text-xs tracking-wider text-[#6B7280] mb-4">
            {{ strtoupper($period === 'week' ? '7' : ($period === 'month' ? '30' : '90')) }}-DAY HDEP TREND — {{ $displayLabel }}
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
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">EGGS COLLECTED PER DAY — {{ $displayLabel }}</div>
            @if($hasLogs)
            <canvas id="eggsChart" height="130"></canvas>
            @else
            <div class="h-[130px] flex items-center justify-center text-sm" style="color: #a39e98;">No production data for this period.</div>
            @endif
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <div class="text-xs tracking-wider text-[#6B7280] mb-4">FEED VS HDEP — {{ $displayLabel }}</div>
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
        const cageColor = '{{ $isAll ? '#002D5E' : $cage->color }}';
        const isAll = {{ $isAll ? 'true' : 'false' }};

        let labels, hdeps, eggs;
        if (isAll) {
            const byDate = {};
            logs.forEach(l => {
                if (!byDate[l.date]) byDate[l.date] = { eggs: 0, hdepSum: 0, hdepCount: 0 };
                byDate[l.date].eggs += l.eggs;
                byDate[l.date].hdepSum += l.hdep;
                byDate[l.date].hdepCount += 1;
            });
            const sortedDates = Object.keys(byDate).sort();
            labels = sortedDates.map(d => d.slice(5));
            hdeps = sortedDates.map(d => byDate[d].hdepCount > 0 ? Math.round(byDate[d].hdepSum / byDate[d].hdepCount * 10) / 10 : 0);
            eggs = sortedDates.map(d => byDate[d].eggs);
        } else {
            labels = logs.map(l => l.date.slice(5));
            hdeps = logs.map(l => l.hdep);
            eggs = logs.map(l => l.eggs);
        }

        const gridColor = '#F0F0EC';
        const baseOpts = {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { font: { size: 10 }, autoSkip: true, autoSkipPadding: 12, maxRotation: 45, minRotation: 0 } },
                y: { grid: { color: gridColor }, ticks: { font: { size: 10 } } },
            }
        };

        if (document.getElementById('hdepChart')) {
            LayRateChart.create('hdepChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: hdeps, borderColor: cageColor, backgroundColor: cageColor+'22',
                        tension: 0.3, pointRadius: 4, fill: true, borderWidth: 2
                    }]
                },
                options: { ...baseOpts, scales: { ...baseOpts.scales, y: { ...baseOpts.scales.y, min: 0, max: 100 } } }
            });
        }

        if (document.getElementById('eggsChart')) {
            LayRateChart.create('eggsChart', {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ data: eggs, backgroundColor: cageColor, borderRadius: 3 }]
                },
                options: baseOpts
            });
        }

        if (document.getElementById('feedHdepChart')) {
            const feedMap = {};
            feedLogs.forEach(f => feedMap[f.date] = f.kg);
            const scatter = logs
                .filter(l => feedMap[l.date] !== undefined)
                .map(l => ({ x: feedMap[l.date], y: l.hdep }));

            LayRateChart.create('feedHdepChart', {
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
    })();
    </script>
</turbo-frame>
