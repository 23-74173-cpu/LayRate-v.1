@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-5">

    <x-page-header title="Dashboard" subtitle="{{ now()->format('l, F j') }} — {{ now()->format('g:i A') }}" subtitle-id="dashboardClock" />

    {{-- ── Metric Cards ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Hens --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Cages"
             data-nav="{{ route('cages.index') }}" data-kpi="hens">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('hens')" aria-label="Hens per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Total Hens</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ number_format($totalHens) }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">across {{ $cages->count() }} cages</div>
        </div>

        {{-- HDEP --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Egg Logging"
             data-nav="{{ route('eggs.logging') }}" data-kpi="hdep">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('hdep')" aria-label="HDEP per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Today's HDEP</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ $todayHdep }}%</div>
            <div class="text-xs mt-2" style="color: {{ $hdepDelta >= 0 ? '#1f6b3a' : '#9b1c24' }};">
                {{ $hdepDelta >= 0 ? '▲' : '▼' }} {{ abs($hdepDelta) }}% vs yesterday
            </div>
        </div>

        {{-- Eggs Collected --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Egg Logging"
             data-nav="{{ route('eggs.logging') }}" data-kpi="eggs">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('eggs')" aria-label="Eggs per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Eggs Collected</div>
            <div class="text-4xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ number_format($eggsToday) }}</div>
            <div class="text-xs mt-2" style="color: #a39e98;">manual entry · logged by operator</div>
        </div>

        {{-- Coop Environment --}}
        <div class="kpi-card relative rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Environment"
             data-nav="{{ route('environment') }}" data-kpi="env">
            <button type="button" class="absolute top-4 right-4 p-1 rounded-full hover:bg-black/5 transition-colors"
                    onclick="event.stopPropagation(); openKpiModal('env')" aria-label="Environment per cage breakdown">
                <i data-lucide="info" class="w-4 h-4" style="color: #a39e98;"></i>
            </button>
            <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-2" style="color: #615d59;">Coop Environment</div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div>
                    <div class="text-xs" style="color: #a39e98;">Temp</div>
                    <div class="text-3xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ $avgTemp ? $avgTemp.'°C' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: #a39e98;">Humidity</div>
                    <div class="text-3xl font-bold leading-none tracking-[-0.5px]" style="color: #1f1f1f;">{{ $avgHum ? $avgHum.'%' : '—' }}</div>
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
                    <div class="farm-tile min-h-[5rem] rounded-lg border-2 p-3 flex flex-col justify-between cursor-pointer transition-all hover:shadow-md"
                         style="border-color: {{ $placedCage->color }}; background-color: {{ $placedCage->colorSoft }};"
                         data-cage-code="{{ $placedCage->cage_code }}"
                         data-breed="{{ $placedCage->breed }}"
                         data-hens="{{ $placedCage->hen_count }}"
                         data-hdep="{{ number_format($placedCage->today_hdep, 1) }}"
                         data-eggs="{{ $placedCage->today_eggs }}"
                         data-sensor="{{ $placedCage->sensor_status }}"
                         onclick="openStatsModal(this)">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold truncate" style="color: {{ $placedCage->color }};">{{ $placedCage->cage_code }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full shrink-0" style="background-color: {{ $placedCage->color }}; color: #ffffff;">{{ number_format($placedCage->today_hdep, 0) }}%</span>
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
                    <div class="farm-tile min-h-[3.5rem] rounded-lg border-2 px-4 py-2 flex flex-col justify-center"
                         style="border-color: {{ $uc->color }}; background-color: {{ $uc->colorSoft }};"
                         data-cage-code="{{ $uc->cage_code }}"
                         data-breed="{{ $uc->breed }}"
                         data-hens="{{ $uc->hen_count }}"
                         data-hdep="{{ number_format($uc->today_hdep, 1) }}"
                         data-eggs="{{ $uc->today_eggs }}"
                         data-sensor="{{ $uc->sensor_status }}"
                         onclick="openStatsModal(this)">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold truncate" style="color: {{ $uc->color }};">{{ $uc->cage_code }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap shrink-0" style="background-color: {{ $uc->color }}; color: #ffffff;">{{ number_format($uc->today_hdep, 0) }}%</span>
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
                <div class="flex justify-between gap-4 text-sm">
                    <span class="shrink-0" style="color: #615d59;">Sensors</span>
                    <span id="statsSensor" class="font-medium text-right" style="color: #1f1f1f;"></span>
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
        if (e.key === 'Escape') { closeStatsModal(); closeKpiModal(); }
    });
    </script>

    {{-- ── KPI Breakdown Modal (shared by all metric cards) ── --}}
    <div id="kpiModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeKpiModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <h3 id="kpiModalTitle" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;"></h3>
                <button onclick="closeKpiModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <div id="kpiModalRows" class="space-y-3 max-h-80 overflow-y-auto"></div>
        </div>
    </div>

    <script>
    var KPI_DATA = {
        hens: {
            title: 'Hens per Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'value' => number_format($c->hen_count) . ' hens · ' . $c->breed])->values()->toJson() !!}
        },
        hdep: {
            title: "Today's HDEP by Cage",
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'value' => number_format($c->today_hdep, 1) . '%'])->values()->toJson() !!}
        },
        eggs: {
            title: 'Eggs Collected by Cage',
            rows: {!! $cages->map(fn($c) => ['label' => $c->cage_code, 'color' => $c->color, 'value' => number_format($c->today_eggs) . ' eggs'])->values()->toJson() !!}
        },
        env: {
            title: 'Environment by Cage',
            rows: {!! $liveReadings->map(fn($r) => ['label' => $r->cage, 'color' => $r->color, 'value' => $r->temp . ' · ' . $r->hum . ' · ' . $r->status])->values()->toJson() !!}
        }
    };

    function openKpiModal(key) {
        var data = KPI_DATA[key];
        if (!data) return;
        document.getElementById('kpiModalTitle').textContent = data.title;
        var container = document.getElementById('kpiModalRows');
        container.innerHTML = '';
        if (data.rows.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'text-sm';
            empty.style.color = '#a39e98';
            empty.textContent = 'No data available.';
            container.appendChild(empty);
        }
        data.rows.forEach(function(row) {
            var line = document.createElement('div');
            line.className = 'flex justify-between items-center gap-4 text-sm';
            var left = document.createElement('div');
            left.className = 'flex items-center gap-2 shrink-0';
            var dot = document.createElement('span');
            dot.className = 'w-2 h-2 rounded-full inline-block';
            dot.style.backgroundColor = row.color;
            var label = document.createElement('span');
            label.style.color = '#615d59';
            label.textContent = row.label;
            left.appendChild(dot);
            left.appendChild(label);
            var value = document.createElement('span');
            value.className = 'font-medium text-right';
            value.style.color = '#1f1f1f';
            value.textContent = row.value;
            line.appendChild(left);
            line.appendChild(value);
            container.appendChild(line);
        });
        document.getElementById('kpiModal').classList.remove('hidden');
        lucide.createIcons();
    }
    function closeKpiModal() {
        var m = document.getElementById('kpiModal');
        if (m) m.classList.add('hidden');
    }

    // ── Live clock (item 6): local timezone, ticks every second ──
    (function() {
        function tick() {
            var el = document.getElementById('dashboardClock');
            if (!el) return;
            var now = new Date();
            el.textContent = now.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })
                + ' — ' + now.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
        tick();
        if (window.__dashboardClockTimer) clearInterval(window.__dashboardClockTimer);
        window.__dashboardClockTimer = setInterval(tick, 1000);
    })();

    </script>

    {{-- ── Feed Today / Mortality Today ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Feed Today --}}
        <div class="kpi-card rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Feeds"
             data-nav="{{ route('feed') }}">
            <h3 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px] mb-4" style="color: #1f1f1f;">Feed Today</h3>
            <div class="max-h-60 overflow-y-auto pr-1 scrollbar-thin">
                @forelse($feedToday as $cageCode => $feed)
                @php
                    $fColor = $feed->cage?->color ?? '#6B7280';
                    $total = 48;
                    $consumed = $feed->feed_consumed_kg;
                    $pct = min(100, round(($consumed/$total)*100));
                @endphp
                <div class="mb-4 rounded-lg -mx-1 px-1 hover:bg-black/[0.03] transition-colors"
                     data-row-nav="{{ route('feed') }}?cage_id={{ $feed->cage?->id }}">
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
            </div>
            <div class="pt-3 border-t flex justify-between text-xs mt-3" style="border-color: #e6e6e6;">
                <span style="color: #615d59;">Total consumed</span>
                <span class="font-semibold" style="color: #1f1f1f;">{{ number_format($feedToday->sum('feed_consumed_kg'), 0) }} kg</span>
            </div>
        </div>

        {{-- Mortality Today --}}
        <div class="kpi-card rounded-xl border p-6 cursor-pointer transition-shadow hover:shadow-md"
             style="background-color: #ffffff; border-color: #e6e6e6;"
             role="link" tabindex="0" aria-label="Go to Mortality"
             data-nav="{{ route('mortality.index') }}">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Mortality Today</h3>
                <x-status-badge :status="$mortalityTodayTotal > 0 ? 'Alert' : 'Normal'" type="general" />
            </div>
            <div class="max-h-60 overflow-y-auto pr-1 scrollbar-thin">
                @foreach($cages as $cage)
                @php
                    $fColor = $cage->color;
                    $mCount = $mortalityToday[$cage->cage_code] ?? 0;
                @endphp
                <div class="flex items-center justify-between py-2 border-b rounded-lg -mx-1 px-1 hover:bg-black/[0.03] transition-colors" style="border-color: #e6e6e6;"
                     data-row-nav="{{ route('mortality.index') }}?cage_id={{ $cage->id }}">
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
            <div class="pt-3 mt-3">
                <a href="{{ route('mortality.index') }}" class="text-sm font-medium hover:underline" style="color: #0075de;" onclick="event.stopPropagation()">View full mortality log →</a>
            </div>
        </div>
    </div>

    {{-- Bound at the end of the page so every .kpi-card / [data-row-nav] element exists --}}
    <script>
    // ── KPI card navigation (item 8) + long-press breakdown (item 9) ──
    document.querySelectorAll('.kpi-card').forEach(function(card) {
        var longPressFired = false;
        var timer = null;

        card.addEventListener('click', function(e) {
            if (longPressFired) { longPressFired = false; return; }
            if (e.target.closest('[data-row-nav]')) return; // cage rows handle their own nav
            Turbo.visit(card.dataset.nav);
        });
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                Turbo.visit(card.dataset.nav);
            }
        });
        card.addEventListener('touchstart', function() {
            if (!card.dataset.kpi) return;
            timer = setTimeout(function() {
                longPressFired = true;
                openKpiModal(card.dataset.kpi);
            }, 500);
        }, { passive: true });
        ['touchend', 'touchmove', 'touchcancel'].forEach(function(ev) {
            card.addEventListener(ev, function() { clearTimeout(timer); }, { passive: true });
        });
    });

    // Cage rows inside cards: navigate with the cage pre-selected
    document.querySelectorAll('[data-row-nav]').forEach(function(row) {
        row.addEventListener('click', function(e) {
            e.stopPropagation();
            Turbo.visit(row.dataset.rowNav);
        });
    });
    </script>
</div>
@endsection
