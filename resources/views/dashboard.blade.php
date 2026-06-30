@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<main class="p-6 space-y-6" style="background-color: #f6f5f4; min-height: 100vh;">

    {{-- ── Header: Greeting + Date + Breadcrumb ── --}}
    <div class="mb-2">
        <p class="text-sm font-medium" style="color: #615d59;">
            {{ now()->format('l, F j') }} — {{ now()->format('g:i A') }}
        </p>
        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px]" style="color: #1f1f1f;">
            Dashboard
        </h1>
    </div>

    {{-- ── Alert Banner (moved from card → full-width persistent) ── --}}
    <x-alert-banner :alerts="$recentAlerts->where('is_read', false)" />

    {{-- ── Metric Cards ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Hens --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Total Hens</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px]" style="color: #1f1f1f;">{{ number_format($totalHens) }}</div>
            <div class="text-xs mt-1" style="color: #a39e98;">across {{ $cages->count() }} cages</div>
        </div>

        {{-- HDEP --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Today's HDEP</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px]" style="color: #1f1f1f;">{{ $todayHdep }}%</div>
            <div class="text-xs mt-1" style="color: {{ $hdepDelta >= 0 ? '#1f6b3a' : '#9b1c24' }};">
                {{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday
            </div>
        </div>

        {{-- Eggs Collected --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Eggs Collected</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px]" style="color: #1f1f1f;">{{ number_format($eggsToday) }}</div>
            <div class="text-xs mt-1" style="color: #a39e98;">manual entry · logged by operator</div>
        </div>

        {{-- Coop Environment --}}
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Coop Environment</div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div>
                    <div class="text-[10px]" style="color: #a39e98;">Temp</div>
                    <div class="text-lg font-semibold" style="color: #1f1f1f;">{{ $avgTemp ? $avgTemp.'°C' : '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px]" style="color: #a39e98;">Humidity</div>
                    <div class="text-lg font-semibold" style="color: #1f1f1f;">{{ $avgHum ? $avgHum.'%' : '—' }}</div>
                </div>
            </div>
            <x-status-badge status="Normal" type="sensor" />
        </div>
    </div>

    {{-- ── Cage Overview ── --}}
    <div>
        <h2 class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px] mb-4" style="color: #1f1f1f;">Cage Overview</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($cages as $cage)
            @php
                $prod = $cage->latestProductionLog();
                $hdep = $prod?->hdep ?? 0;
                $hen = $cage->hens->first();
            @endphp
            <div class="rounded-xl border p-4" style="background-color: #ffffff; border-color: #e6e6e6; border-left: 3px solid {{ $cage->color }};">
                <div class="flex items-center justify-between mb-2">
                    <x-cage-color :cage="$cage" />
                    <x-status-badge :status="number_format($hdep, 0)" type="hdep" />
                </div>
                <div class="text-sm mb-3" style="color: #615d59;">
                    {{ $hen?->breed ?? '—' }} · {{ $cage->total_capacity }} hens
                    @if($hen)
                    · {{ $hen->current_age_weeks }} wks
                    @endif
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('cages.index') }}" class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium rounded-lg transition-colors" style="color: #1f1f1f; border: 1px solid #e6e6e6;" onmouseover="this.style.backgroundColor='#f6f5f4'" onmouseout="this.style.backgroundColor='transparent'">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                    </a>
                    <a href="{{ route('analytics', ['cage' => $cage->cage_code]) }}" class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium rounded-lg text-white transition-opacity" style="background-color: {{ $cage->color }};" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                        <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i> View
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Feed Today / Mortality Today ── --}}
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



</main>
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush
