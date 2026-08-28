<turbo-frame id="dashboard-feed-mortality">
    {{-- Feed Today --}}
    <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-3 cursor-pointer"
         style="background-color: #ffffff; border-color: #e6e6e6; animation-delay: 140ms;"
         role="link" tabindex="0" aria-label="Go to Feeds"
         data-nav="{{ route('feed') }}">
        <div class="relative flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <span class="kpi-chip" style="background-color: #e8f5ec; color: #1f6b3a;">
                    <i data-lucide="wheat" class="w-4 h-4"></i>
                </span>
                <h3 class="text-sm font-semibold" style="color: #1f1f1f;">Feed Today</h3>
            </div>
        </div>

        @php $feedCount = $feedToday->count(); @endphp

        @if($feedCount > 0)
        {{-- Pie Chart centered --}}
        <div class="flex justify-center mb-2">
            <div style="width: 100px; height: 100px;">
                <canvas id="feedPieChart"></canvas>
            </div>
        </div>

        {{-- Legend compact --}}
        <div class="flex flex-wrap justify-center gap-x-3 gap-y-0.5 mb-2">
            @foreach($feedToday as $cageCode => $feed)
                @php $fSoftColor = $feed->cage?->colorSoft ?? '#f3f4f6'; @endphp
                <div class="flex items-center gap-1 text-[10px]">
                    <span class="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $fSoftColor }};"></span>
                    <span style="color: #31302e;">{{ $cageCode }}</span>
                    <span style="color: #a39e98;">{{ number_format($feed->feed_consumed_kg, 0) }}kg</span>
                </div>
            @endforeach
        </div>

        @php $totalConsumed = $feedToday->sum('feed_consumed_kg'); @endphp
        <div class="pt-1.5 border-t flex justify-between text-[11px]" style="border-color: #e6e6e6;">
            <span style="color: #615d59;">Total consumed</span>
            <span class="font-semibold" style="color: #1f1f1f;">{{ number_format($totalConsumed, 0) }} kg</span>
        </div>

        @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center text-center py-3">
            <span class="w-8 h-8 rounded-full flex items-center justify-center mb-2" style="background-color: #f3f4f6; color: #9CA3AF;">
                <i data-lucide="wheat-off" class="w-4 h-4"></i>
            </span>
            <p class="text-xs font-medium" style="color: #31302e;">No feed logged today</p>
            <p class="text-[10px] mt-0.5" style="color: #9CA3AF;">Track consumption to monitor feed efficiency.</p>
            <a href="{{ route('feed') }}" data-turbo-frame="_top" onclick="event.stopPropagation()"
               class="mt-2 inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[10px] font-semibold transition-colors"
               style="background-color: #0075de; color: #ffffff;">
                <i data-lucide="plus" class="w-3 h-3"></i>
                Log feed
            </a>
        </div>
        @endif
    </div>

    @if($feedCount > 0)
    <script>
    (function() {
        var canvas = document.getElementById('feedPieChart');
        if (!canvas || typeof Chart === 'undefined') return;
        var existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        var labels = @json($feedToday->keys()->toArray());
        var consumed = @json($feedToday->pluck('feed_consumed_kg')->toArray());
        var softColors = @json($feedToday->pluck('cage.colorSoft')->toArray());

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: consumed,
                    backgroundColor: softColors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f1f1f',
                        titleFont: { size: 11 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': ' + ctx.parsed + ' kg';
                            }
                        }
                    }
                }
            }
        });
    })();
    </script>
    @endif

    <script>
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-feed-mortality'));
    </script>
</turbo-frame>
