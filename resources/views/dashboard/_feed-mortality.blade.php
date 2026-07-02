<turbo-frame id="dashboard-feed-mortality">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Feed Today --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <h3 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px] mb-4" style="color: #1f1f1f;">Feed Today</h3>
            @forelse($feedToday as $cageCode => $feed)
            @php
                $fColor = $feed->cage?->color ?? '#6B7280';
                $total = 48;
                $consumed = $feed->feed_consumed_kg;
                $pct = min(100, round(($consumed/$total)*100));
            @endphp
            <div class="mb-4">
                <div class="flex justify-between items-center mb-1.5">
                    <x-cage-color :cage="$feed->cage" />
                    <span class="text-xs" style="color: #615d59;">{{ number_format($consumed, 0) }} / {{ $total }} kg</span>
                </div>
                <div class="w-full h-1.5 rounded-full overflow-hidden" style="background-color: #f0f0f0;">
                    <div class="h-full rounded-full" style="width: {{ $pct }}%; background-color: {{ $fColor }};"></div>
                </div>
                <div class="text-xs mt-1 {{ $pct < 80 ? 'text-amber-600' : '' }}" style="color: {{ $pct >= 80 ? '#a39e98' : '' }};">{{ $pct }}% consumed</div>
            </div>
            @empty
            <p class="text-sm" style="color: #a39e98;">No feed data for today.</p>
            @endforelse
            <div class="pt-3 border-t flex justify-between text-xs mt-3" style="border-color: #e6e6e6;">
                <span style="color: #615d59;">Total consumed</span>
                <span class="font-semibold" style="color: #1f1f1f;">{{ number_format($feedToday->sum('feed_consumed_kg'), 0) }} kg</span>
            </div>
        </div>

        {{-- Mortality Today --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Mortality Today</h3>
                <x-status-badge :status="$mortalityTodayTotal > 0 ? 'Alert' : 'Normal'" type="general" />
            </div>
            @foreach($cages as $cage)
            @php
                $fColor = $cage->color;
                $mCount = $mortalityToday[$cage->cage_code] ?? 0;
            @endphp
            <div class="flex items-center justify-between py-2 border-b" style="border-color: #e6e6e6;">
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
            <div class="pt-3 mt-3">
                <a href="{{ route('mortality.index') }}" class="text-sm font-medium hover:underline" style="color: #0075de;">View full mortality log →</a>
            </div>
        </div>
    </div>
</turbo-frame>
