@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-5">

    <x-page-header title="Dashboard" subtitle="{{ now()->format('l, F j') }} — {{ now()->format('g:i A') }}" subtitle-id="dashboardClock" subtitle-class="whitespace-nowrap text-xs sm:text-sm" />

    {{-- Onboarding Modal --}}
    @if($needsOnboarding)
        <div id="onboardingModal" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px] mb-2" style="color: #1f1f1f;">Farm Layout Setup</h2>
            <p class="text-sm mb-4" style="color: #615d59;">Define your farm grid dimensions to visualize cage placement.</p>
            <form method="POST" action="{{ route('settings.farm-layout') }}">
                @csrf
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Rows</label>
                        <input type="number" name="rows" value="4" min="1" max="50" required
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Columns</label>
                        <input type="number" name="cols" value="4" min="1" max="50" required
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>
                </div>
                <x-button type="submit" class="w-full py-2.5">
                    Save Layout
                </x-button>
            </form>
        </div>
    </div>
    @endif

    {{-- ── Cage Filter Tabs ── --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 dash-rise" style="animation-delay: 40ms;">
        <button type="button" onclick="filterDashboard('all')" class="dashboard-tab dashboard-tab-active px-4 py-2 text-sm font-semibold whitespace-nowrap"
                data-tab="all"
                style="background-color: #0075de; color: #ffffff;">
            All
            <span class="ml-1 text-xs opacity-80">({{ $cages->count() }})</span>
        </button>
        @foreach($cages as $cage)
        <button type="button" onclick="filterDashboard('{{ $cage->cage_code }}')" class="dashboard-tab px-4 py-2 text-sm font-medium whitespace-nowrap"
                data-tab="{{ $cage->cage_code }}"
                style="background-color: #ffffff; color: #615d59; border: 1px solid #e6e6e6;">
            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background-color: {{ $cage->color }};"></span>
            {{ $cage->cage_code }}
        </button>
        @endforeach
    </div>

    {{-- ── Metric Cards (lazy) ── --}}
    <turbo-frame id="dashboard-stats" src="{{ route('dashboard.stats') }}" loading="lazy" class="mb-8 block">
        @include('dashboard._metric-cards-skeleton')
    </turbo-frame>

    {{-- ─ Stats Modal (vanilla JS) ── --}}
    <div id="statsModal" style="display: none;" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeStatsModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
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
        document.getElementById('statsModal').style.display = 'flex';
        lucide.createIcons();
    }
    function closeStatsModal() {
        document.getElementById('statsModal').style.display = 'none';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeStatsModal();
    });
    </script>

    {{-- ── Daily Egg Logs Calendar (wider) + Feed / Mortality (narrow) ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2">
            <turbo-frame id="dashboard-calendar" src="{{ route('dashboard.calendar') }}" loading="lazy" class="block">
                @include('dashboard._calendar-skeleton')
            </turbo-frame>
        </div>
        <div class="lg:col-span-1">
            <turbo-frame id="dashboard-feed-mortality" src="{{ route('dashboard.feed-mortality') }}" loading="lazy" class="block">
                @include('dashboard._feed-mortality-skeleton')
            </turbo-frame>
        </div>
    </div>

    {{-- ── KPI Breakdown Modal (shared by all metric cards — item 9) ── --}}
    <div id="kpiModal" style="display: none;" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeKpiModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
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
    // KPI_DATA is populated by the lazily loaded metric-cards frame.
    window.KPI_DATA = window.KPI_DATA || {};

    function openKpiModal(key) {
        var data = window.KPI_DATA[key];
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
        document.getElementById('kpiModal').style.display = 'flex';
        lucide.createIcons();
    }
    function closeKpiModal() {
        var m = document.getElementById('kpiModal');
        if (m) m.style.display = 'none';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeKpiModal();
    });

    // ── Card navigation (item 8) + long-press breakdown (item 9) ──
    // Called by each lazily loaded frame once its cards are in the DOM.
    function bindKpiCards(root) {
        (root || document).querySelectorAll('.kpi-card:not([data-kpi-bound])').forEach(function(card) {
            card.dataset.kpiBound = '1';
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
        (root || document).querySelectorAll('[data-row-nav]:not([data-row-bound])').forEach(function(row) {
            row.dataset.rowBound = '1';
            row.addEventListener('click', function(e) {
                e.stopPropagation();
                Turbo.visit(row.dataset.rowNav);
            });
        });
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

    // ── Cage filter: reloads Turbo Frames with ?cage=CODE ──
    window.filterDashboard = function(code) {
        document.querySelectorAll('.dashboard-tab').forEach(function(tab) {
            if (tab.dataset.tab === code) {
                tab.classList.add('dashboard-tab-active');
                tab.style.backgroundColor = '#0075de';
                tab.style.color = '#ffffff';
                tab.style.borderColor = '#0075de';
            } else {
                tab.classList.remove('dashboard-tab-active');
                tab.style.backgroundColor = '#ffffff';
                tab.style.color = '#615d59';
                tab.style.borderColor = '#e6e6e6';
            }
        });

        var statsUrl = '{{ route('dashboard.stats') }}';
        var feedUrl  = '{{ route('dashboard.feed-mortality') }}';
        var calendarUrl = '{{ route('dashboard.calendar') }}';
        if (code !== 'all') {
            statsUrl += '?cage=' + encodeURIComponent(code);
            feedUrl  += '?cage=' + encodeURIComponent(code);
            calendarUrl += '?cage=' + encodeURIComponent(code);
        }

        var statsFrame = document.getElementById('dashboard-stats');
        var feedFrame  = document.getElementById('dashboard-feed-mortality');
        var calendarFrame = document.getElementById('dashboard-calendar');
        if (statsFrame) { statsFrame.src = statsUrl; statsFrame.reload(); }
        if (feedFrame)  { feedFrame.src  = feedUrl;  feedFrame.reload();  }
        if (calendarFrame) { calendarFrame.src = calendarUrl; calendarFrame.reload(); }
    };
    </script>

</div>
@endsection
