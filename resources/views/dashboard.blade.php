@extends('layouts.app')
@section('title', 'Dashboard')
@section('header-clock', now()->format('l, F j') . ' — ' . now()->format('g:i A'))

@section('content')
<div class="space-y-5">

    {{-- ── Dashboard Header with Integrated Tabs ── --}}
    <div class="relative overflow-hidden rounded-lg flex items-center justify-between" id="dashHeader" style="min-height: 72px; background: linear-gradient(to right, #2e4a9e, #213183, #1a2342); transition: background 0.35s ease;">
        {{-- Decorative egg bubbles --}}
        <div class="page-header-egg-decor" aria-hidden="true"
             style="position:absolute; inset:0; z-index:0; pointer-events:none; color:#9ca3af;"></div>

        <script>
        (function () {
            var decor = document.querySelector('.page-header-egg-decor');
            if (!decor || decor.getAttribute('data-eggs')) return;
            decor.setAttribute('data-eggs', '1');
            var opacities = ['0.30', '0.38', '0.46', '0.55'];
            var count = 34;
            for (var i = 0; i < count; i++) {
                var egg = document.createElement('div');
                egg.style.position = 'absolute';
                egg.style.left = (45 + Math.random() * 55).toFixed(2) + '%';
                egg.style.top = (Math.random() * 100).toFixed(2) + '%';
                egg.style.width = (12 + Math.random() * 24).toFixed(1) + 'px';
                egg.style.height = (12 + Math.random() * 24).toFixed(1) + 'px';
                egg.style.opacity = opacities[i % opacities.length];
                egg.style.transform = 'rotate(' + (Math.random() * 360).toFixed(0) + 'deg)';
                egg.innerHTML = '<i data-lucide="egg" class="w-full h-full"></i>';
                decor.appendChild(egg);
            }
            decor.style.webkitMaskImage = 'linear-gradient(to right, transparent 0%, black 40%)';
            decor.style.maskImage = 'linear-gradient(to right, transparent 0%, black 40%)';
            if (window.lucide) lucide.createIcons();
        })();
        </script>

        {{-- Left: Dashboard tab (active) + subtitle --}}
        <div class="relative z-[1] py-4" style="padding-left: 35px;">
            <button type="button" onclick="switchDashTab('dashboard')" class="dash-header-tab active" data-header-tab="dashboard">
                Dashboard
            </button>
        </div>

        {{-- Right: Analytics tab --}}
        <div class="relative z-[1] py-4" style="padding-right: 35px;">
            <button type="button" onclick="switchDashTab('analytics')" class="dash-header-tab" data-header-tab="analytics">
                Analytics
            </button>
        </div>
    </div>

    <style>
        .dash-header-tab {
            font-size: 20px;
            font-weight: 700;
            color: rgba(255,255,255,0.45);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px 16px;
            border-radius: 10px;
            transition: all 0.25s ease;
            line-height: 1.4;
        }
        .dash-header-tab:hover {
            transform: scale(1.08);
        }
        .dash-header-tab:hover:not(.active) {
            color: rgba(255,255,255,0.7);
        }
        .dash-header-tab.active {
            color: #fff;
        }
        .dash-tab-panel { display: none; }
        .dash-tab-panel.active { display: block; }
    </style>

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
        <button type="button" onclick="filterDashboard('all')" class="dashboard-tab px-4 py-2 text-sm font-semibold whitespace-nowrap rounded-full transition-all duration-200 ease-out hover:scale-[1.04] hover:shadow-md active:scale-[0.97]"
                data-tab="all"
                style="background-color: #0d47a1; color: #ffffff; border: 1px solid #0d47a1;">
            All
            <span class="ml-1 text-xs opacity-80">({{ $cages->count() }})</span>
        </button>
        @foreach($cages as $cage)
        <button type="button" onclick="filterDashboard('{{ $cage->cage_code }}')" class="dashboard-tab px-4 py-2 text-sm font-medium whitespace-nowrap rounded-full transition-all duration-200 ease-out hover:scale-[1.04] hover:shadow-md active:scale-[0.97]"
                data-tab="{{ $cage->cage_code }}"
                style="background-color: #ffffff; color: #615d59; border: 1px solid #e6e6e6;">
            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background-color: {{ $cage->colorSoft }}; border: 1px solid {{ $cage->color }};"></span>
            {{ $cage->cage_code }}
        </button>
        @endforeach
    </div>

    {{-- Tab 1: Dashboard (KPI Cards) --}}
    <div class="dash-tab-panel active" data-tab-panel="dashboard">
        <div class="space-y-5">
            <turbo-frame id="dashboard-stats" src="{{ route('dashboard.stats') }}" loading="lazy" class="block">
                @include('dashboard._metric-cards-skeleton')
            </turbo-frame>
            @include('dashboard._data-checklist')
        </div>
    </div>

    {{-- Tab 2: Analytics (Charts + Checklist) --}}
    <div class="dash-tab-panel" data-tab-panel="analytics">
                <div class="space-y-6">
            @include('dashboard._data-checklist')

            {{-- Section Filter Tabs --}}
            <div class="flex items-center gap-2 mb-6 pt-4 flex-wrap">
                <button type="button" onclick="filterAnalytics('production')" class="analytics-section-tab px-3 py-1.5 text-sm font-bold uppercase tracking-[0.125px] rounded-md transition-all inline-flex items-center gap-1.5" data-section="production">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Production Performance
                </button>
                <button type="button" onclick="filterAnalytics('environmental')" class="analytics-section-tab px-3 py-1.5 text-sm font-bold uppercase tracking-[0.125px] rounded-md transition-all text-[#6B7280] hover:text-[#374151] hover:bg-[#e5e7eb] inline-flex items-center gap-1.5" data-section="environmental">
                    <i data-lucide="thermometer" class="w-4 h-4"></i> Environmental Analytics
                </button>
                <button type="button" onclick="filterAnalytics('feed')" class="analytics-section-tab px-3 py-1.5 text-sm font-bold uppercase tracking-[0.125px] rounded-md transition-all text-[#6B7280] hover:text-[#374151] hover:bg-[#e5e7eb] inline-flex items-center gap-1.5" data-section="feed">
                    <i data-lucide="wheat" class="w-4 h-4"></i> Feed Analytics
                </button>
                <button type="button" onclick="filterAnalytics('flock')" class="analytics-section-tab px-3 py-1.5 text-sm font-bold uppercase tracking-[0.125px] rounded-md transition-all text-[#6B7280] hover:text-[#374151] hover:bg-[#e5e7eb] inline-flex items-center gap-1.5" data-section="flock">
                    <i data-lucide="heart-pulse" class="w-4 h-4"></i> Flock Analytics
                </button>
            </div>

            <style>
                .analytics-section-tab.active {
                    color: #111827 !important;
                    font-size: 15px;
                    font-weight: 800;
                    letter-spacing: 0.125px;
                }
                .analytics-section-tab.active i {
                    width: 16px;
                    height: 16px;
                }
                .analytics-section { display: none !important; }
                .analytics-section.active-section { display: block !important; }
            </style>

            <script>
            function filterAnalytics(section) {
                document.querySelectorAll('.analytics-section-tab').forEach(function(btn) {
                    btn.classList.remove('active');
                    btn.style.color = '';
                    btn.style.fontSize = '';
                    btn.style.fontWeight = '';
                    btn.style.letterSpacing = '';
                    var icon = btn.querySelector('i');
                    if (icon) {
                        icon.style.width = '';
                        icon.style.height = '';
                    }
                    if (btn.dataset.section === section) {
                        btn.classList.add('active');
                        btn.style.color = '#111827';
                        btn.style.fontSize = '15px';
                        btn.style.fontWeight = '800';
                        btn.style.letterSpacing = '0.125px';
                        if (icon) {
                            icon.style.width = '16px';
                            icon.style.height = '16px';
                        }
                    }
                });
                document.querySelectorAll('.analytics-section').forEach(function(el) {
                    el.classList.toggle('active-section', el.dataset.analyticsSection === section);
                });
                if (window.lucide) lucide.createIcons();
            }
            window.filterAnalytics = filterAnalytics;

            document.addEventListener('DOMContentLoaded', function() {
                filterAnalytics('production');
            });
            </script>

            {{-- ═══ SECTION 1 — Production Performance ═══ --}}
            <div class="analytics-section active-section" data-analytics-section="production">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
                    <div class="flex flex-col gap-8">
                        <turbo-frame id="dashboard-cage-performance" src="{{ route('dashboard.cage-performance') }}" loading="lazy" class="block">
                            @include('dashboard._cage-performance-skeleton')
                        </turbo-frame>

                        <turbo-frame id="dashboard-heat-stress" src="{{ route('dashboard.heat-stress') }}" loading="lazy" class="block">
                            <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                                <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                <div class="h-[280px] bg-gray-100 rounded-xl"></div>
                            </div>
                        </turbo-frame>
                    </div>

                    <div class="flex flex-col gap-8">
                        <turbo-frame id="dashboard-production-history" src="{{ route('dashboard.production-history') }}" loading="lazy" class="block flex-[2]">
                            @include('dashboard._production-history-skeleton')
                        </turbo-frame>

                        <turbo-frame id="dashboard-egg-collection-time" src="{{ route('dashboard.egg-collection-time') }}" loading="lazy" class="block flex-1">
                            <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                                <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                <div class="h-[260px] bg-gray-100 rounded-xl"></div>
                            </div>
                        </turbo-frame>

                        <turbo-frame id="dashboard-hen-age-layrate" src="{{ route('dashboard.hen-age-layrate') }}" loading="lazy" class="block flex-1">
                            <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                                <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                <div class="h-[260px] bg-gray-100 rounded-xl"></div>
                            </div>
                        </turbo-frame>
                    </div>
                </div>
            </div>

            {{-- ═══ SECTION 2 — Environmental Analytics ═══ --}}
            <div class="analytics-section" data-analytics-section="environmental">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
                    <turbo-frame id="dashboard-temp-vs-hdep" src="{{ route('dashboard.temp-vs-hdep') }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[220px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-hum-vs-hdep" src="{{ route('dashboard.hum-vs-hdep') }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[220px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>
                </div>
            </div>

            {{-- ═══ SECTION 3 — Feed Analytics ═══ --}}
            <div class="analytics-section" data-analytics-section="feed">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
                    <turbo-frame id="dashboard-feed-by-cage" src="{{ route('dashboard.feed-by-cage') }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[220px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-feed-vs-egg" src="{{ route('dashboard.feed-vs-egg') }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[220px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>
                </div>
            </div>

            {{-- ═══ SECTION 4 — Flock Analytics ═══ --}}
            <div class="analytics-section" data-analytics-section="flock">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
                    <turbo-frame id="dashboard-breed-analytics" src="{{ route('dashboard.breed-analytics') }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[160px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-mortality-by-cause" src="{{ route('dashboard.mortality-by-cause') }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[160px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-mortality-trend" src="{{ route('dashboard.mortality-trend') }}" loading="lazy" class="block lg:col-span-2">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-5 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[180px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>
                </div>
            </div>
        </div>
    </div>

    {{-- ─ Stats Modal ── --}}
    <div id="statsModal" data-modal data-close="closeStatsModal" style="display: none;" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
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


    {{-- ── KPI Breakdown Modal (shared by all metric cards — item 9) ── --}}
    <div id="kpiModal" data-modal  data-close="closeKpiModal" style="display: none;" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
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

    {{-- ── Yesterday's Production Record Popup ── --}}
    <div id="yesterdaySummaryModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeYesterdaySummary()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-[18px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Yesterday's Production Record</h3>
                    <p class="text-xs mt-0.5" style="color: #a39e98;">{{ \Carbon\Carbon::yesterday()->format('l, F j, Y') }}</p>
                </div>
                <button onclick="closeYesterdaySummary()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <div class="space-y-3">
                {{-- Eggs Collected --}}
                <div class="flex items-center justify-between py-2.5 px-3 rounded-xl" style="background-color: #f9f7f4;">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: #e8f4fd;">
                            <i data-lucide="egg" class="w-4 h-4" style="color: #0075de;"></i>
                        </span>
                        <span class="text-sm font-medium" style="color: #615d59;">Eggs Collected</span>
                    </div>
                    <span class="text-[18px] font-bold" style="color: #1f1f1f;">{{ number_format($eggsYesterday) }}</span>
                </div>

                {{-- HDEP --}}
                <div class="flex items-center justify-between py-2.5 px-3 rounded-xl" style="background-color: #f9f7f4;">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: #fef3e2;">
                            <i data-lucide="percent" class="w-4 h-4" style="color: #e09c00;"></i>
                        </span>
                        <span class="text-sm font-medium" style="color: #615d59;">HDEP</span>
                    </div>
                    <span class="text-[18px] font-bold" style="color: #1f1f1f;">{{ $yesterdayHdep }}%</span>
                </div>

                {{-- Feed Consumed --}}
                <div class="flex items-center justify-between py-2.5 px-3 rounded-xl" style="background-color: #f9f7f4;">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: #e6f6ee;">
                            <i data-lucide="wheat" class="w-4 h-4" style="color: #16a34a;"></i>
                        </span>
                        <span class="text-sm font-medium" style="color: #615d59;">Feed Consumed</span>
                    </div>
                    <span class="text-[18px] font-bold" style="color: #1f1f1f;">{{ number_format($yesterdayFeedTotal, 2) }} kg</span>
                </div>

                {{-- Mortality --}}
                <div class="flex items-center justify-between py-2.5 px-3 rounded-xl" style="background-color: #f9f7f4;">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: {{ $yesterdayMortalityTotal > 0 ? '#fde8e8' : '#e6f6ee' }};">
                            <i data-lucide="heart-crack" class="w-4 h-4" style="color: {{ $yesterdayMortalityTotal > 0 ? '#dc2626' : '#16a34a' }};"></i>
                        </span>
                        <span class="text-sm font-medium" style="color: #615d59;">Mortality</span>
                    </div>
                    <span class="text-[18px] font-bold" style="color: {{ $yesterdayMortalityTotal > 0 ? '#dc2626' : '#1f1f1f' }};">{{ $yesterdayMortalityTotal }} {{ Str::plural('hen', $yesterdayMortalityTotal) }}</span>
                </div>
            </div>

            <button onclick="closeYesterdaySummary()" class="w-full mt-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors" style="background-color: #0075de;">
                Got it
            </button>
        </div>
    </div>

    {{-- ── Day Production Complete Popup ── --}}
    <div id="dayCompleteModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeDayComplete()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 text-center" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background-color: #e6f6ee;">
                <i data-lucide="check-circle" class="w-8 h-8" style="color: #16a34a;"></i>
            </div>
            <h3 class="text-lg font-semibold mb-1" style="color: #1f1f1f;">Day Production Complete</h3>
            <p class="text-sm mb-5" style="color: #a39e98;">Reporting day has ended. Data for today is locked.</p>
            <button onclick="closeDayComplete()" class="w-full py-2.5 rounded-xl text-sm font-medium text-white transition-colors" style="background-color: #16a34a;">
                Got it
            </button>
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

        if (data.subtitle) {
            var sub = document.createElement('p');
            sub.className = 'text-xs mb-3';
            sub.style.color = '#a39e98';
            sub.textContent = data.subtitle;
            container.appendChild(sub);
        }

        if (key === 'mortality') {
            var filterWrap = document.createElement('div');
            filterWrap.className = 'inline-flex items-center gap-1 rounded-lg p-1 mb-3';
            filterWrap.style.backgroundColor = '#f3f4f6';
            [1, 7, 14, 30].forEach(function(d) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.mortDays = d;
                btn.className = 'px-3 py-1.5 text-xs font-semibold rounded-md transition-all';
                var isActive = (window.__dashboardMortalityDays || 1) === d;
                if (isActive) {
                    btn.style.backgroundColor = '#C2405C';
                    btn.style.color = '#ffffff';
                    btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.1)';
                } else {
                    btn.classList.add('text-[#6B7280]');
                    btn.onmouseover = function() { this.style.backgroundColor = '#e5e7eb'; };
                    btn.onmouseout = function() { this.style.backgroundColor = ''; };
                }
                btn.textContent = d === 1 ? 'Today' : d + 'D';
                btn.onclick = function() { setMortalityDays(d); };
                filterWrap.appendChild(btn);
            });
            container.appendChild(filterWrap);
        }

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
            dot.style.backgroundColor = row.bgColor || row.color;
            dot.style.border = '1px solid ' + row.color;
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

    // ── Shared dashboard chart renderer ──
    // Multiple lazy Turbo Frames on the dashboard load incrementally. Each frame
    // used to call LayRateChart.prepareForRender() on its own, but that helper is
    // global: it destroys every existing chart instance. Calling it for every new
    // frame would wipe out charts that rendered earlier (e.g. Cage Performance's
    // bar/pie charts disappear as soon as Production History loads). We therefore
    // prepare Chart.js once for the whole dashboard, then render each subsequent
    // chart individually. A recovery hook lets LayRateChart re-render everything
    // if it ever has to reload Chart.js to clear a stuck-paint state.
    window.DashboardChartRenderer = (function () {
        var queue = [];
        var preparePromise = null;
        var prepared = false;
        var knownConfigs = {};

        function renderAll() {
            Object.keys(knownConfigs).forEach(function (id) {
                LayRateChart.create(id, knownConfigs[id]);
            });
        }

        function flushQueue() {
            queue.forEach(function (item) {
                LayRateChart.create(item.id, item.config);
            });
            queue = [];
        }

        function runBatch() {
            if (prepared) {
                // Chart.js is already fresh; render only the newly requested chart(s)
                // without touching the other dashboard charts.
                flushQueue();
                return;
            }
            if (!preparePromise) {
                preparePromise = window.LayRateChart.prepareForRender();
                var myGen = window.LayRateChart._generation;
                preparePromise.then(function () {
                    preparePromise = null;
                    if (myGen !== window.LayRateChart._generation) return;
                    prepared = true;
                    flushQueue();
                    // Register a recovery hook so stuck-paint recovery can redraw
                    // every dashboard chart against the freshly loaded module.
                    window.LayRateChart.registerRecoveryHook(function () {
                        window.LayRateChart.prepareForRender().then(function () {
                            renderAll();
                        });
                    });
                });
            }
        }

        return {
            render: function (id, config) {
                knownConfigs[id] = config;
                queue.push({ id: id, config: config });
                runBatch();
            },
            recover: renderAll
        };
    })();

    // ── Track the active cage and period filters so sub-filter buttons can rebuild URLs ──
    window.__dashboardCage = 'all';
    window.__dashboardHistoryDays = 7;
    window.__dashboardHistoryCompare = false;
    window.__dashboardPerfDays = 1;
    window.__dashboardMortalityDays = {{ $mortalityDays ?? 1 }};

    function buildFrameUrl(base, params) {
        var query = Object.keys(params).map(function (k) {
            var v = params[k];
            if (v === null || v === undefined || v === '' || v === false) return '';
            return encodeURIComponent(k) + '=' + encodeURIComponent(v);
        }).filter(Boolean).join('&');
        return query ? base + '?' + query : base;
    }

    // Reload a Turbo Frame without scrolling the page back to the frame.
    function reloadFramePreservingScroll(frameId, url) {
        var frame = document.getElementById(frameId);
        if (!frame) return;

        var scrollRoot = document.querySelector('.page-wrapper') || document.documentElement;
        var savedScroll = scrollRoot.scrollTop || window.scrollY || 0;

        function restoreScroll() {
            if (scrollRoot.scrollTop !== undefined) scrollRoot.scrollTop = savedScroll;
            window.scrollTo(0, savedScroll);
        }

        frame.addEventListener('turbo:frame-load', function handler() {
            frame.removeEventListener('turbo:frame-load', handler);
            restoreScroll();
            requestAnimationFrame(restoreScroll);
            setTimeout(restoreScroll, 0);
        }, { once: true });

        // Setting .src triggers the frame reload in Turbo (src attribute change).
        // Only fall back to reload() when the URL is unchanged, so the frame
        // never loads twice (a second turbo:frame-load would sidestep the once
        // handler above and let unrelated scroll handling run again).
        if (frame.getAttribute('src') === url) {
            frame.reload();
        } else {
            frame.src = url;
        }
    }

    function setButtonActive(btn, active) {
        if (active) {
            btn.classList.remove('text-[#6B7280]', 'hover:bg-[#e5e7eb]');
            btn.style.backgroundColor = '#0075de';
            btn.style.color = '#ffffff';
            btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.1)';
        } else {
            btn.classList.add('text-[#6B7280]', 'hover:bg-[#e5e7eb]');
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.style.boxShadow = '';
        }
    }

    window.setProductionHistoryDays = function(days) {
        window.__dashboardHistoryDays = days;

        var frame = document.getElementById('dashboard-production-history');
        if (frame) {
            frame.querySelectorAll('[data-history-days]').forEach(function(btn) {
                setButtonActive(btn, parseInt(btn.dataset.historyDays, 10) === days);
            });
        }

        var url = buildFrameUrl('{{ route('dashboard.production-history') }}', {
            days: days === 7 ? null : days,
            compare: window.__dashboardHistoryCompare ? 1 : null
        });

        reloadFramePreservingScroll('dashboard-production-history', url);
    };

    window.toggleProductionHistoryCompare = function() {
        var nextCompare = !window.__dashboardHistoryCompare;
        window.__dashboardHistoryCompare = nextCompare;

        var frame = document.getElementById('dashboard-production-history');
        if (frame) {
            frame.querySelectorAll('[data-history-compare]').forEach(function(btn) {
                setButtonActive(btn, nextCompare);
            });
        }

        var url = buildFrameUrl('{{ route('dashboard.production-history') }}', {
            days: window.__dashboardHistoryDays === 7 ? null : window.__dashboardHistoryDays,
            compare: nextCompare ? 1 : null
        });

        reloadFramePreservingScroll('dashboard-production-history', url);
    };

    window.setCagePerformanceDays = function(days) {
        window.__dashboardPerfDays = days;

        var frame = document.getElementById('dashboard-cage-performance');
        if (frame) {
            frame.querySelectorAll('[data-perf-days]').forEach(function(btn) {
                setButtonActive(btn, parseInt(btn.dataset.perfDays, 10) === days);
            });
        }

        var url = buildFrameUrl('{{ route('dashboard.cage-performance') }}', {
            cage: window.__dashboardCage === 'all' ? null : window.__dashboardCage,
            days: days === 1 ? null : days
        });

        reloadFramePreservingScroll('dashboard-cage-performance', url);
    };

    window.setMortalityDays = function(days) {
        window.__dashboardMortalityDays = days;

        var url = buildFrameUrl('{{ route('dashboard.stats') }}', {
            cage: window.__dashboardCage === 'all' ? null : window.__dashboardCage,
            mortality_days: days === 1 ? null : days
        });

        var frame = document.getElementById('dashboard-stats');
        if (frame) {
            frame.addEventListener('turbo:frame-load', function handler() {
                frame.removeEventListener('turbo:frame-load', handler);
                setTimeout(function() { openKpiModal('mortality'); }, 50);
            }, { once: true });
        }

        reloadFramePreservingScroll('dashboard-stats', url);
    };

    // ── Cage filter: reloads Turbo Frames with ?cage=CODE ──
    window.filterDashboard = function(code) {
        window.__dashboardCage = code;

        document.querySelectorAll('.dashboard-tab').forEach(function(tab) {
            if (tab.dataset.tab === code) {
                tab.classList.add('dashboard-tab-active');
                tab.style.backgroundColor = '#0d47a1';
                tab.style.color = '#ffffff';
                tab.style.borderColor = '#0d47a1';
            } else {
                tab.classList.remove('dashboard-tab-active');
                tab.style.backgroundColor = '#ffffff';
                tab.style.color = '#615d59';
                tab.style.borderColor = '#e6e6e6';
            }
        });

        var cageParam = code === 'all' ? null : code;
        var statsUrl   = buildFrameUrl('{{ route('dashboard.stats') }}',           { cage: cageParam, mortality_days: window.__dashboardMortalityDays === 1 ? null : window.__dashboardMortalityDays });
        var feedUrl    = buildFrameUrl('{{ route('dashboard.feed-mortality') }}',  { cage: cageParam });
        var perfUrl    = buildFrameUrl('{{ route('dashboard.cage-performance') }}', { cage: cageParam, days: window.__dashboardPerfDays === 1 ? null : window.__dashboardPerfDays });
        var historyUrl = buildFrameUrl('{{ route('dashboard.production-history') }}', {
            days: window.__dashboardHistoryDays === 7 ? null : window.__dashboardHistoryDays,
            compare: window.__dashboardHistoryCompare ? 1 : null
        });

        reloadFramePreservingScroll('dashboard-stats', statsUrl);
        reloadFramePreservingScroll('dashboard-feed-mortality', feedUrl);
        reloadFramePreservingScroll('dashboard-cage-performance', perfUrl);
        reloadFramePreservingScroll('dashboard-production-history', historyUrl);
    };

    // ── Yesterday's Production Record popup ──
    // Shows once per reporting day. Uses localStorage keyed by the server's
    // reporting date so the popup reappears after the daily rollover.
    function closeYesterdaySummary() {
        var m = document.getElementById('yesterdaySummaryModal');
        if (m) m.style.display = 'none';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeYesterdaySummary();
    });

    (function() {
        var reportingDate = '{{ $today }}';
        var storageKey = 'yesterdaySummaryShown_' + reportingDate;
        if (localStorage.getItem(storageKey)) return;

        var eggsYesterday = {{ (int) $eggsYesterday }};
        var yesterdayMortalityTotal = {{ (int) $yesterdayMortalityTotal }};
        var yesterdayHdep = {{ (float) $yesterdayHdep }};
        var yesterdayFeedTotal = {{ (float) $yesterdayFeedTotal }};

        var hasAnyData = eggsYesterday > 0 || yesterdayMortalityTotal > 0 || yesterdayHdep > 0 || yesterdayFeedTotal > 0;
        if (!hasAnyData) return;

        setTimeout(function() {
            var m = document.getElementById('yesterdaySummaryModal');
            if (m) {
                m.style.display = 'flex';
                lucide.createIcons();
                localStorage.setItem(storageKey, '1');
            }
        }, 800);
    })();

    // ── Day Production Complete popup ──
    function closeDayComplete() {
        var m = document.getElementById('dayCompleteModal');
        if (m) m.style.display = 'none';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDayComplete();
    });

    (function() {
        var dayComplete = {{ $dayComplete ? 'true' : 'false' }};
        if (!dayComplete) return;

        var reportingDate = '{{ $today }}';
        var storageKey = 'dayCompleteShown_' + reportingDate;
        if (localStorage.getItem(storageKey)) return;

        setTimeout(function() {
            var m = document.getElementById('dayCompleteModal');
            if (m) {
                m.style.display = 'flex';
                lucide.createIcons();
                localStorage.setItem(storageKey, '1');
            }
        }, 1200);
    })();

    // ── Dashboard tabs ──
    function switchDashTab(tab) {
        document.querySelectorAll('.dash-header-tab').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.dash-tab-panel').forEach(function(p) { p.classList.remove('active'); });
        var btn = document.querySelector('[data-header-tab="' + tab + '"]');
        var panel = document.querySelector('[data-tab-panel="' + tab + '"]');
        if (btn) btn.classList.add('active');
        if (panel) panel.classList.add('active');
        var header = document.getElementById('dashHeader');
        if (header) {
            header.style.background = tab === 'analytics'
                ? 'linear-gradient(to right, #1a2342, #213183, #2e4a9e)'
                : 'linear-gradient(to right, #2e4a9e, #213183, #1a2342)';
        }
        if (tab === 'analytics' && typeof filterAnalytics === 'function') {
            var activeBtn = document.querySelector('.analytics-section-tab.active');
            filterAnalytics(activeBtn ? activeBtn.dataset.section : 'production');
        }
        lucide.createIcons();
    }
    </script>

</div>
@endsection
