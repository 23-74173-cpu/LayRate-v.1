<turbo-frame id="dashboard-feed-mortality">
    <div class="grid grid-cols-1 gap-4">

        {{-- Feed Today --}}
        <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-6 cursor-pointer"
             style="background-color: #ffffff; border-color: #e6e6e6; animation-delay: 140ms;"
             role="link" tabindex="0" aria-label="Go to Feeds"
             data-nav="{{ route('feed') }}">
            <i data-lucide="wheat" class="kpi-watermark"></i>
            <div class="relative flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="kpi-chip w-10 h-10">
                        <i data-lucide="wheat" class="w-4 h-4"></i>
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
                <p class="text-sm" style="color: #a39e98;">No feed data for today.</p>
                @endforelse
            </div>
            @if($feedCount > 5)
            <div class="text-xs text-center pt-1.5" style="color: #a39e98;">+{{ $feedCount - 5 }} more cage{{ $feedCount - 5 !== 1 ? 's' : '' }}</div>
            @endif
            <div class="pt-3 border-t flex justify-between text-xs mt-2" style="border-color: #e6e6e6;">
                <span style="color: #615d59;">Total consumed</span>
                <span class="font-semibold" style="color: #1f1f1f;">{{ number_format($feedToday->sum('feed_consumed_kg'), 0) }} kg</span>
            </div>
        </div>

        {{-- Mortality Today --}}
        <div class="kpi-card dash-rise relative overflow-hidden rounded-2xl border p-6 cursor-pointer"
             style="background-color: #ffffff; border-color: #e6e6e6; animation-delay: 200ms;"
             role="link" tabindex="0" aria-label="Go to Mortality"
             data-nav="{{ route('chickens.index', ['tab' => 'mortality']) }}">
            <i data-lucide="heart-crack" class="kpi-watermark"></i>
            <div class="relative flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="kpi-chip w-10 h-10">
                        <i data-lucide="heart-crack" class="w-4 h-4"></i>
                    </span>
                    <h3 class="text-base font-semibold" style="color: #1f1f1f;">Mortality Today</h3>
                </div>
                <x-status-badge :status="$mortalityTodayTotal > 0 ? 'Alert' : 'Normal'" type="general" />
            </div>
            @php $mortalityCount = $cages->count(); @endphp
            <div class="max-h-[185px] overflow-y-auto pr-1 scrollbar-thin">
                @foreach($cages as $cage)
                @php
                    $fColor = $cage->color;
                    $mCount = $mortalityToday[$cage->cage_code] ?? 0;
                @endphp
                <div class="flex items-center justify-between py-2 border-b rounded-lg -mx-1 px-1 hover:bg-black/[0.03] transition-colors" style="border-color: #e6e6e6;"
                     data-row-nav="{{ route('chickens.index', ['tab' => 'mortality', 'cage_id' => $cage->id]) }}">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full" style="background-color: {{ $fColor }};"></div>
                        <span class="text-sm" style="color: #31302e;">{{ $cage->cage_code }}</span>
                    </div>
                    @if($mCount > 0)
                    <span class="text-sm font-semibold" style="color: #9b1c24;">{{ $mCount }} {{ Str::plural('hen', $mCount) }}</span>
                    @else
                    <span class="text-sm" style="color: #a39e98;">None</span>
                    @endif
                </div>
                @endforeach
            </div>
            @if($mortalityCount > 5)
            <div class="text-xs text-center pt-1.5" style="color: #a39e98;">+{{ $mortalityCount - 5 }} more cage{{ $mortalityCount - 5 !== 1 ? 's' : '' }}</div>
            @endif
            <div class="pt-3 mt-2">
                <a href="{{ route('chickens.index', ['tab' => 'mortality']) }}" data-turbo-frame="_top" class="text-sm font-medium hover:underline" style="color: #0075de;" onclick="event.stopPropagation()">View full mortality log →</a>
            </div>
        </div>
    </div>

    <script>
    if (typeof bindKpiCards === 'function') bindKpiCards(document.getElementById('dashboard-feed-mortality'));
    </script>
</turbo-frame>
