@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-5">

    <x-page-header title="Dashboard" subtitle="{{ now()->format('l, F j') }} — {{ now()->format('g:i A') }}" />

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
                    <div class="text-xs" style="color: #a39e98;">Temp</div>
                    <div class="text-lg font-semibold" style="color: #1f1f1f;">{{ $avgTemp ? $avgTemp.'°C' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: #a39e98;">Humidity</div>
                    <div class="text-lg font-semibold" style="color: #1f1f1f;">{{ $avgHum ? $avgHum.'%' : '—' }}</div>
                </div>
            </div>
            <x-status-badge status="Normal" type="sensor" />
        </div>
    </div>

    {{-- ── Cage Overview: Farm Layout Canvas ── --}}
    <div>
        <h2 class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px] mb-4" style="color: #1f1f1f;">Cage Overview</h2>

        {{-- Onboarding Modal --}}
        @if($needsOnboarding)
        <div id="onboardingModal" class="fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
            <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);"></div>
            <div class="relative w-full max-w-sm rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px] mb-2" style="color: #1f1f1f;">Farm Layout Setup</h2>
                <p class="text-sm mb-4" style="color: #615d59;">Define your farm grid dimensions to visualize cage placement.</p>
                <form method="POST" action="{{ route('settings.farm-layout') }}">
                    @csrf
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Rows</label>
                            <input type="number" name="rows" value="4" min="1" max="10" required
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Columns</label>
                            <input type="number" name="cols" value="4" min="1" max="10" required
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                    </div>
                    <button type="submit"
                            class="w-full py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                            style="background-color: #0075de;"
                            onmouseover="this.style.opacity='0.85'"
                            onmouseout="this.style.opacity='1'">
                        Save Layout
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- Farm Layout Grid --}}
        <style>
            @media (max-width: 639px) { .cage-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } }
        </style>
        <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="grid gap-2 cage-grid" style="grid-template-columns: repeat({{ $gridCols }}, minmax(0, 1fr));">
                @for($r = 0; $r < $gridRows; $r++)
                    @for($c = 0; $c < $gridCols; $c++)
                    @php
                        $placedCage = $cages->firstWhere(fn($cg) => $cg->location_row === $r && $cg->location_column === $c);
                    @endphp
                    @if($placedCage)
                    <div class="farm-tile min-h-[5rem] rounded-lg border-2 p-3 flex flex-col justify-between cursor-pointer transition-all hover:shadow-md relative"
                         style="border-color: {{ $placedCage->color }}; background-color: {{ $placedCage->colorSoft }};"
                         data-cage-code="{{ $placedCage->cage_code }}"
                         data-breed="{{ $placedCage->breed }}"
                         data-hens="{{ $placedCage->hen_count }}"
                         data-hdep="{{ number_format($placedCage->today_hdep, 1) }}"
                         data-eggs="{{ $placedCage->today_eggs }}"
                         data-sensor="{{ $placedCage->has_sensor ? 'Yes' : 'No' }}"
                         onclick="openStatsModal(this)">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold" style="color: {{ $placedCage->color }};">{{ $placedCage->cage_code }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded-full absolute -top-2 -right-2 z-10" style="background-color: {{ $placedCage->color }}; color: #ffffff;">{{ number_format($placedCage->today_hdep, 0) }}%</span>
                        </div>
                        <div class="text-xs truncate" style="color: #615d59;">{{ Str::limit($placedCage->breed, 16) }}</div>
                    </div>
                    @else
                    <div class="min-h-[5rem] rounded-lg border p-3 flex items-center justify-center" style="border-color: #e6e6e6; background-color: #f9fafb;">
                        <span class="text-xs" style="color: #d1d5db;">{{ $r + 1 }}-{{ $c + 1 }}</span>
                    </div>
                    @endif
                    @endfor
                @endfor
            </div>

            {{-- Unplaced Cages --}}
            @php $unplaced = $cages->filter(fn($cg) => is_null($cg->location_row)); @endphp
            @if($unplaced->count() > 0)
            <div class="mt-6 pt-4 border-t" style="border-color: #e6e6e6;">
                <h3 class="text-xs font-semibold tracking-[0.05em] uppercase mb-3" style="color: #615d59;">Unplaced Cages</h3>
                <div class="flex flex-wrap gap-3">
                    @foreach($unplaced as $uc)
                    <div class="farm-tile min-h-[3.5rem] rounded-lg border-2 px-4 py-2 flex flex-col justify-center relative"
                         style="border-color: {{ $uc->color }}; background-color: {{ $uc->colorSoft }};"
                         data-cage-code="{{ $uc->cage_code }}"
                         data-breed="{{ $uc->breed }}"
                         data-hens="{{ $uc->hen_count }}"
                         data-hdep="{{ number_format($uc->today_hdep, 1) }}"
                         data-eggs="{{ $uc->today_eggs }}"
                         data-sensor="{{ $uc->has_sensor ? 'Yes' : 'No' }}"
                         onclick="openStatsModal(this)">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold" style="color: {{ $uc->color }};">{{ $uc->cage_code }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded-full whitespace-nowrap absolute -top-2 -right-2 z-10" style="background-color: {{ $uc->color }}; color: #ffffff;">{{ number_format($uc->today_hdep, 0) }}%</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ─ Stats Modal (vanilla JS) ── --}}
    <div id="statsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeStatsModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <h3 id="statsCageCode" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;"></h3>
                <button onclick="closeStatsModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between text-sm">
                    <span style="color: #615d59;">Breed</span>
                    <span id="statsBreed" class="font-medium" style="color: #1f1f1f;"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: #615d59;">Hens</span>
                    <span id="statsHens" class="font-medium" style="color: #1f1f1f;"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: #615d59;">Today's HDEP</span>
                    <span id="statsHdep" class="font-medium" style="color: #1f1f1f;"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: #615d59;">Eggs Collected</span>
                    <span id="statsEggs" class="font-medium" style="color: #1f1f1f;"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span style="color: #615d59;">Sensor</span>
                    <span id="statsSensor" class="font-medium" style="color: #1f1f1f;"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openStatsModal(el) {
        document.getElementById('statsCageCode').textContent = el.dataset.cageCode;
        document.getElementById('statsBreed').textContent = el.dataset.breed;
        document.getElementById('statsHens').textContent = el.dataset.hens;
        document.getElementById('statsHdep').textContent = el.dataset.hdep + '%';
        document.getElementById('statsEggs').textContent = el.dataset.eggs;
        document.getElementById('statsSensor').textContent = el.dataset.sensor;
        document.getElementById('statsModal').classList.remove('hidden');
        lucide.createIcons();
    }
    function closeStatsModal() {
        document.getElementById('statsModal').classList.add('hidden');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeStatsModal();
    });
    </script>

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



</div>
@endsection
