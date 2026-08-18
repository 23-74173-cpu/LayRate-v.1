<turbo-frame id="dashboard-feed-mortality">
    {{-- Feed Today --}}
    <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-5 cursor-pointer h-full"
         style="background-color: #ffffff; border-color: #e6e6e6; animation-delay: 140ms;"
         role="link" tabindex="0" aria-label="Go to Feeds"
         data-nav="{{ route('feed') }}">
        <div class="relative flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <span class="kpi-chip" style="background-color: #e8f5ec; color: #1f6b3a;">
                    <i data-lucide="wheat" class="w-5 h-5"></i>
                </span>
                <h3 class="text-base font-semibold" style="color: #1f1f1f;">Feed Today</h3>
            </div>
        </div>
        @php $feedCount = $feedToday->count(); @endphp
        <div class="max-h-[340px] overflow-y-auto pr-1 scrollbar-thin">
            @forelse($feedToday as $cageCode => $feed)
            @php
                $fColor = $feed->cage?->color ?? '#6B7280';
                $total = $feed->feed_target_kg;
                $consumed = $feed->feed_consumed_kg;
                $pct = min(100, round(($total > 0 ? $consumed/$total : 0) * 100));
            @endphp
            <div class="mb-4 rounded-lg -mx-1 px-1 hover:bg-black/[0.03] transition-colors"
                 data-row-nav="{{ route('feed') }}?cage_id={{ $feed->cage?->id }}">
                <div class="flex justify-between items-center mb-1.5">
                    <x-cage-color :cage="$feed->cage" />
                    <span class="text-xs" style="color: #615d59;">{{ number_format($consumed, 0) }} / {{ $total }} kg</span>
                </div>
                <div class="w-full h-1.5 rounded-full overflow-hidden dash-bar" style="background-color: #f0f0f0;">
                    <div class="h-full rounded-full" style="width: {{ $pct }}%; background-color: {{ $fColor }};"></div>
                </div>
                <div class="text-xs mt-1 {{ $pct < 80 ? 'text-amber-600' : '' }}" style="color: {{ $pct >= 80 ? '#a39e98' : '' }};">{{ $pct }}% consumed</div>
            </div>
            @empty
            {{-- Real empty state --}}
            <div class="flex flex-col items-center text-center py-6">
                <span class="w-10 h-10 rounded-full flex items-center justify-center mb-3" style="background-color: #f3f4f6; color: #9CA3AF;">
                    <i data-lucide="wheat-off" class="w-5 h-5"></i>
                </span>
                <p class="text-sm font-medium" style="color: #31302e;">No feed logged today</p>
                <p class="text-xs mt-1" style="color: #9CA3AF;">Track consumption to monitor feed efficiency.</p>
                <a href="{{ route('feed') }}" data-turbo-frame="_top" onclick="event.stopPropagation()"
                   class="mt-3 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                   style="background-color: #0075de; color: #ffffff;">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Log feed
                </a>
            </div>
            @endforelse
        </div>
        @if($feedCount > 5)
        <div class="text-xs text-center pt-1.5" style="color: #a39e98;">+{{ $feedCount - 5 }} more cage{{ $feedCount - 5 !== 1 ? 's' : '' }}</div>
        @endif
        @if($feedCount > 0)
        <div class="pt-3 border-t flex justify-between text-xs mt-2" style="border-color: #e6e6e6;">
            <span style="color: #615d59;">Total consumed</span>
            <span class="font-semibold" style="color: #1f1f1f;">{{ number_format($feedToday->sum('feed_consumed_kg'), 0) }} kg</span>
        </div>
        @endif
    </div>

    <script>
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-feed-mortality'));
    </script>
</turbo-frame>
