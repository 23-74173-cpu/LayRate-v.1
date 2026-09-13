@extends('layouts.app')
@section('title', 'Dashboard')
@section('header-clock', now()->format('l, F j') . ' ΓÇö ' . now()->format('g:i A'))

@section('content')
<div class="space-y-5">

    {{-- ΓöÇΓöÇ Dashboard Header ΓöÇΓöÇ --}}
    <div class="relative overflow-hidden bg-linear-to-br from-secondary to-sidebar-bg rounded-lg p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4" id="dashHeader" style="min-height: 72px;">
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

        <div class="relative z-[1]">
            <div class="text-xl font-bold text-white">Dashboard</div>
            <div class="text-sm text-white/75 mt-1">Farm performance overview and analytics</div>
        </div>
    </div>

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

    {{-- Global Filters: Cage + Period --}}
    <div class="dash-rise" style="animation-delay: 0ms;">
        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center flex-wrap gap-3 sm:gap-5">
            <div class="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2 sm:flex-1 sm:min-w-0">
                <label for="dashboardCageSelect" class="flex items-center gap-1.5 shrink-0 text-xs font-bold uppercase tracking-[0.125px] text-[#6B7280]">
                    <i data-lucide="warehouse" class="w-4 h-4"></i>
                    <span>Cage</span>
                </label>
                <select id="dashboardCageSelect" onchange="filterDashboard(this.value)"
                        class="w-full sm:w-44 border border-[#D9D9D9] rounded-lg px-3 py-2.5 sm:py-2 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-[#002D5E] focus:border-[#002D5E]">
                    <option value="all" {{ request('cage', 'all') === 'all' ? 'selected' : '' }}>All Cages ({{ $cages->count() }})</option>
                    @foreach($cages as $cage)
                        <option value="{{ $cage->cage_code }}" {{ request('cage') === $cage->cage_code ? 'selected' : '' }}>{{ $cage->cage_code }}</option>
                    @endforeach
                </select>
            </div>

            <div class="hidden sm:block w-px self-stretch" style="background-color: #E5E7EB;"></div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2">
                <label class="flex items-center gap-1.5 shrink-0 text-xs font-bold uppercase tracking-[0.125px] text-[#6B7280]">
                    <i data-lucide="calendar-range" class="w-4 h-4"></i>
                    <span>Period</span>
                </label>
                <div class="inline-flex items-center gap-1 rounded-lg p-1 w-full sm:w-auto" style="background-color: #f3f4f6;" id="dashboardGlobalPeriod">
                    <button type="button" data-global-days="7" onclick="setGlobalDays(7)" class="global-days-btn flex-1 sm:flex-none text-center px-3 py-2 sm:py-1.5 text-xs font-semibold rounded-md transition-all text-[#6B7280] hover:bg-[#e5e7eb]">Week</button>
                    <button type="button" data-global-days="30" onclick="setGlobalDays(30)" class="global-days-btn flex-1 sm:flex-none text-center px-3 py-2 sm:py-1.5 text-xs font-semibold rounded-md transition-all text-[#6B7280] hover:bg-[#e5e7eb]" style="background-color: #0075de; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">Month</button>
                    <button type="button" data-global-days="0" onclick="setGlobalDays(0)" class="global-days-btn flex-1 sm:flex-none text-center px-3 py-2 sm:py-1.5 text-xs font-semibold rounded-md transition-all text-[#6B7280] hover:bg-[#e5e7eb]">Full</button>
                </div>
            </div>

            <div class="hidden sm:block w-px self-stretch" style="background-color: #E5E7EB;"></div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2">
                <label for="dashboardFromDate" class="flex items-center gap-1.5 shrink-0 text-xs font-bold uppercase tracking-[0.125px] text-[#6B7280]">
                    <i data-lucide="calendar-days" class="w-4 h-4"></i>
                    <span>From</span>
                </label>
                <input type="date" id="dashboardFromDate" value="{{ request('from_date', today()->toDateString()) }}"
                       max="{{ now()->toDateString() }}"
                       onchange="setDashboardFromDate(this.value)"
                       title="Show analytics from this date to today"
                       class="w-full sm:w-44 border border-[#D9D9D9] rounded-lg px-2.5 py-2.5 sm:py-2 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-[#002D5E] focus:border-[#002D5E]">
            </div>
        </div>
    </div>

    <div class="space-y-6">
        @include('dashboard._data-checklist')

        @php
            $analyticsTabs = [
                'production' => ['label' => 'Production Performance', 'icon' => 'bar-chart-3', 'onclick' => "filterAnalytics('production')"],
                'environmental' => ['label' => 'Environmental Analytics', 'icon' => 'thermometer', 'onclick' => "filterAnalytics('environmental')"],
                'feed' => ['label' => 'Feed Analytics', 'icon' => 'wheat', 'onclick' => "filterAnalytics('feed')"],
                'flock' => ['label' => 'Flock Analytics', 'icon' => 'heart-pulse', 'onclick' => "filterAnalytics('flock')"],
            ];
            @endphp
            <x-underline-tabs :tabs="$analyticsTabs" active="production" />

            <style>
                .analytics-section { display: none !important; }
                .analytics-section.active-section { display: block !important; }
            </style>

            <script>
            function filterAnalytics(section) {
                document.querySelectorAll('button[onclick^="filterAnalytics("]').forEach(function(btn) {
                    var isActive = btn.getAttribute('onclick') === "filterAnalytics('" + section + "')";
                    btn.classList.toggle('border-navy', isActive);
                    btn.classList.toggle('text-navy', isActive);
                    btn.classList.toggle('border-transparent', !isActive);
                    btn.classList.toggle('text-ink-muted', !isActive);
                    // Tailwind also accepts hex form ΓÇö keep both in sync for robustness
                    btn.classList.toggle('border-[#002D5E]', isActive);
                    btn.classList.toggle('text-[#002D5E]', isActive);
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

            {{-- ΓòÉΓòÉΓòÉ SECTION 1 ΓÇö Production Performance ΓòÉΓòÉΓòÉ --}}
            <div class="analytics-section active-section" data-analytics-section="production">
                <div class="space-y-4">
                    <turbo-frame id="dashboard-stats-production" data-src="{{ route('dashboard.stats.production', ['cage' => request('cage'), 'from_date' => request('from_date')]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse"><div class="h-4 w-32 bg-gray-200 rounded mb-3"></div><div class="grid grid-cols-4 gap-3"><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div></div></div>
                    </turbo-frame>
                    <div id="production-charts-grid" class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start lg:items-stretch">
                        <turbo-frame id="dashboard-cage-performance" data-src="{{ route('dashboard.cage-performance') }}" loading="lazy" class="block h-full">
                            @include('dashboard._cage-performance-skeleton')
                        </turbo-frame>

                        <turbo-frame id="dashboard-production-history" data-src="{{ route('dashboard.production-history', ['days' => 30]) }}" loading="lazy" class="block self-start lg:h-full">
                            @include('dashboard._production-history-skeleton')
                        </turbo-frame>

                        <turbo-frame id="dashboard-egg-collection-time" data-src="{{ route('dashboard.egg-collection-time', ['days' => 30]) }}" loading="lazy" class="block self-start lg:h-full">
                            <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse h-auto lg:h-full">
                                <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                <div class="h-[120px] bg-gray-100 rounded-xl"></div>
                            </div>
                        </turbo-frame>

                        <turbo-frame id="dashboard-hen-age-layrate" data-src="{{ route('dashboard.hen-age-layrate', ['days' => 30]) }}" loading="lazy" class="block self-start lg:h-full">
                            <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse h-auto lg:h-full">
                                <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                <div class="h-[120px] bg-gray-100 rounded-xl"></div>
                            </div>
                        </turbo-frame>
                    </div>
                </div>
            </div>

            {{-- ΓòÉΓòÉΓòÉ SECTION 2 ΓÇö Environmental Analytics ΓòÉΓòÉΓòÉ --}}
            <div class="analytics-section" data-analytics-section="environmental">
                <div class="space-y-4">
                    <turbo-frame id="dashboard-stats-environment" data-src="{{ route('dashboard.stats.environment', ['cage' => request('cage'), 'from_date' => request('from_date')]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse"><div class="h-4 w-32 bg-gray-200 rounded mb-3"></div><div class="grid grid-cols-3 gap-3"><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div></div></div>
                    </turbo-frame>
                    <turbo-frame id="dashboard-heat-stress" data-src="{{ route('dashboard.heat-stress', ['days' => 30]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[110px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
                        <turbo-frame id="dashboard-temp-vs-hdep" data-src="{{ route('dashboard.temp-vs-hdep', ['days' => 30]) }}" loading="lazy" class="block">
                            <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                                <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                <div class="h-[110px] bg-gray-100 rounded-xl"></div>
                            </div>
                        </turbo-frame>

                        <turbo-frame id="dashboard-hum-vs-hdep" data-src="{{ route('dashboard.hum-vs-hdep', ['days' => 30]) }}" loading="lazy" class="block">
                            <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                                <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                <div class="h-[110px] bg-gray-100 rounded-xl"></div>
                            </div>
                        </turbo-frame>
                    </div>
                </div>
            </div>

            {{-- ΓòÉΓòÉΓòÉ SECTION 3 ΓÇö Feed Analytics ΓòÉΓòÉΓòÉ --}}
            <div class="analytics-section" data-analytics-section="feed">
                <div class="space-y-4">
                    <turbo-frame id="dashboard-stats-feed" data-src="{{ route('dashboard.stats.feed', ['cage' => request('cage'), 'from_date' => request('from_date')]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse"><div class="h-4 w-32 bg-gray-200 rounded mb-3"></div><div class="grid grid-cols-4 gap-3"><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div></div></div>
                    </turbo-frame>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
                    <turbo-frame id="dashboard-feed-by-cage" data-src="{{ route('dashboard.feed-by-cage', ['days' => 30]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[110px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-feed-vs-egg" data-src="{{ route('dashboard.feed-vs-egg', ['days' => 30]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[110px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>
                </div>
                </div>
            </div>

            {{-- ΓòÉΓòÉΓòÉ SECTION 4 ΓÇö Flock Analytics ΓòÉΓòÉΓòÉ --}}
            <div class="analytics-section" data-analytics-section="flock">
                <div class="space-y-4">
                    <turbo-frame id="dashboard-stats-flock" data-src="{{ route('dashboard.stats.flock', ['cage' => request('cage'), 'from_date' => request('from_date')]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse"><div class="h-4 w-32 bg-gray-200 rounded mb-3"></div><div class="grid grid-cols-3 gap-3"><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div><div class="h-20 bg-gray-100 rounded-xl"></div></div></div>
                    </turbo-frame>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
                    <turbo-frame id="dashboard-breed-analytics" data-src="{{ route('dashboard.breed-analytics', ['days' => 30]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[120px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-mortality-by-cause" data-src="{{ route('dashboard.mortality-by-cause', ['days' => 30]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[120px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-flock-age-by-cage" data-src="{{ route('dashboard.flock-age-by-cage') }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[120px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>

                    <turbo-frame id="dashboard-mortality-trend" data-src="{{ route('dashboard.mortality-trend', ['days' => 30]) }}" loading="lazy" class="block">
                        <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 animate-pulse">
                            <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                            <div class="h-[110px] bg-gray-100 rounded-xl"></div>
                        </div>
                    </turbo-frame>
                </div>
            </div>
        </div>

    {{-- ΓöÇ Stats Modal ΓöÇΓöÇ --}}
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


    {{-- ΓöÇΓöÇ KPI Breakdown Modal (shared by all metric cards ΓÇö item 9) ΓöÇΓöÇ --}}
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

    {{-- ΓöÇΓöÇ Yesterday's Production Record Popup ΓöÇΓöÇ --}}
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

    {{-- ΓöÇΓöÇ Day Production Complete Popup ΓöÇΓöÇ --}}
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

    // ΓöÇΓöÇ Card navigation (item 8) + long-press breakdown (item 9) ΓöÇΓöÇ
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

    // ΓöÇΓöÇ Live clock (item 6): local timezone, ticks every second ΓöÇΓöÇ
    (function() {
        function tick() {
            var el = document.getElementById('dashboardClock');
            if (!el) return;
            var now = new Date();
            el.textContent = now.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })
                + ' ΓÇö ' + now.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
        tick();
        if (window.__dashboardClockTimer) clearInterval(window.__dashboardClockTimer);
        window.__dashboardClockTimer = setInterval(tick, 1000);
    })();

    // ΓöÇΓöÇ Shared dashboard chart renderer ΓöÇΓöÇ
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

    // ΓöÇΓöÇ Track the active cage and period filters so sub-filter buttons can rebuild URLs ΓöÇΓöÇ
    window.__dashboardCage = new URLSearchParams(window.location.search).get('cage') || 'all';
    window.__dashboardGlobalDays = 30;
    window.__dashboardFromDate = '{{ request('from_date', '') }}';
    window.__dashboardHistoryCompare = false;
    window.__dashboardMortalityDays = {{ $mortalityDays ?? 1 }};
    document.addEventListener('DOMContentLoaded', function(){
        var sel = document.getElementById('dashboardCageSelect');
        if(sel) sel.value = window.__dashboardCage;
    });

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

    // Global Period filter — reloads every analytics card with the chosen day range.
    // The default is 30 (Month); when days === 30, the param is omitted so the server
    // default is used.  Production History additionally passes Compare if toggled on.
    window.setGlobalDays = function(days) {
        window.__dashboardGlobalDays = days;
        window.__dashboardFromDate = '';
        var dateInput = document.getElementById('dashboardFromDate');
        if (dateInput) dateInput.value = '';

        // Restyle the global pill bar
        document.querySelectorAll('#dashboardGlobalPeriod .global-days-btn').forEach(function(btn) {
            setButtonActive(btn, parseInt(btn.dataset.globalDays, 10) === days);
        });

        reloadCharts();
    };

    // Reload every analytics chart frame with the active Cage / Period / From-date filters.
    function reloadCharts() {
        var dayParam = window.__dashboardGlobalDays === 30 ? null : window.__dashboardGlobalDays;
        var cageParam = (window.__dashboardCage && window.__dashboardCage !== 'all') ? window.__dashboardCage : null;
        var hasDate = !!window.__dashboardFromDate;

        function base(route, extra) {
            var p = {};
            if (hasDate) p.from_date = window.__dashboardFromDate;
            else p.days = dayParam;
            if (extra) { for (var k in extra) p[k] = extra[k]; }
            return buildFrameUrl(route, p);
        }
        function caged(route) {
            var p = {};
            if (cageParam) p.cage = cageParam;
            return base(route, p);
        }

        // KPI cards reflect the as-of snapshot when a From date is set (otherwise
        // today's live numbers) and stay cage-filtered + mortality-window aware.
        function statsFrame(route) {
            var p = { cage: cageParam, mortality_days: window.__dashboardMortalityDays === 1 ? null : window.__dashboardMortalityDays };
            return base(route, p);
        }

        reloadFramePreservingScroll('dashboard-stats', statsFrame('{{ route('dashboard.stats') }}'));
        reloadFramePreservingScroll('dashboard-stats-production', statsFrame('{{ route('dashboard.stats.production') }}'));
        reloadFramePreservingScroll('dashboard-stats-environment', statsFrame('{{ route('dashboard.stats.environment') }}'));
        reloadFramePreservingScroll('dashboard-stats-feed', statsFrame('{{ route('dashboard.stats.feed') }}'));
        reloadFramePreservingScroll('dashboard-stats-flock', statsFrame('{{ route('dashboard.stats.flock') }}'));
        reloadFramePreservingScroll('dashboard-feed-mortality', caged('{{ route('dashboard.feed-mortality') }}'));

        // Cage Performance Overview is intentionally NOT cage-filtered — it always
        // compares all cages against each other regardless of the active cage filter.
        reloadFramePreservingScroll('dashboard-cage-performance', base('{{ route('dashboard.cage-performance') }}'));
        reloadFramePreservingScroll('dashboard-production-history', base('{{ route('dashboard.production-history') }}', {
            compare: window.__dashboardHistoryCompare ? 1 : null
        }));
        reloadFramePreservingScroll('dashboard-heat-stress', caged('{{ route('dashboard.heat-stress') }}'));
        reloadFramePreservingScroll('dashboard-temp-vs-hdep', caged('{{ route('dashboard.temp-vs-hdep') }}'));
        reloadFramePreservingScroll('dashboard-hum-vs-hdep', caged('{{ route('dashboard.hum-vs-hdep') }}'));
        reloadFramePreservingScroll('dashboard-feed-by-cage', caged('{{ route('dashboard.feed-by-cage') }}'));
        reloadFramePreservingScroll('dashboard-feed-vs-egg', caged('{{ route('dashboard.feed-vs-egg') }}'));
        reloadFramePreservingScroll('dashboard-egg-collection-time', caged('{{ route('dashboard.egg-collection-time') }}'));
        reloadFramePreservingScroll('dashboard-hen-age-layrate', caged('{{ route('dashboard.hen-age-layrate') }}'));
        reloadFramePreservingScroll('dashboard-breed-analytics', caged('{{ route('dashboard.breed-analytics') }}'));
        reloadFramePreservingScroll('dashboard-mortality-by-cause', caged('{{ route('dashboard.mortality-by-cause') }}'));
        reloadFramePreservingScroll('dashboard-mortality-trend', caged('{{ route('dashboard.mortality-trend') }}'));
    }

    // From-date filter — shows analytics from the selected day up to today.
    window.setDashboardFromDate = function(value) {
        window.__dashboardFromDate = value || '';
        var input = document.getElementById('dashboardFromDate');
        if (input && input.value !== window.__dashboardFromDate) input.value = window.__dashboardFromDate;

        document.querySelectorAll('#dashboardGlobalPeriod .global-days-btn').forEach(function(btn) {
            setButtonActive(btn, !window.__dashboardFromDate && parseInt(btn.dataset.globalDays, 10) === window.__dashboardGlobalDays);
        });

        reloadCharts();
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

        var p = { compare: nextCompare ? 1 : null };
        if (window.__dashboardFromDate) {
            p.from_date = window.__dashboardFromDate;
        } else {
            p.days = window.__dashboardGlobalDays === 30 ? null : window.__dashboardGlobalDays;
        }
        var url = buildFrameUrl('{{ route('dashboard.production-history') }}', p);

        reloadFramePreservingScroll('dashboard-production-history', url);
    };

    window.setMortalityDays = function(days) {
        window.__dashboardMortalityDays = days;

        var url = buildFrameUrl('{{ route('dashboard.stats') }}', {
            cage: window.__dashboardCage === 'all' ? null : window.__dashboardCage,
            mortality_days: days === 1 ? null : days,
            from_date: window.__dashboardFromDate || null
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

    // ── Production charts equalizer ──
    // Previously this pinned all 4 frames to the tallest card's height on every
    // breakpoint.  That inflated the two smaller chart cards (egg-collection,
    // hen-age) on desktop and all chart cards on mobile.  The CSS grid
    // (items-stretch + h-full frames + flex-1 holders) already produces
    // correct per-row equal heights, so this function now only clears any
    // leftover inline heights that the old equalizer left behind.
    window.equalizeProductionCharts = function() {
        var grid = document.getElementById('production-charts-grid');
        if (!grid) return;
        var frames = grid.querySelectorAll('turbo-frame');
        for (var k = 0; k < frames.length; k++) {
            if (frames[k].style.height) frames[k].style.height = '';
        }
    };
    document.addEventListener('turbo:frame-load', function () { window.equalizeProductionCharts(); });
    window.addEventListener('load', window.equalizeProductionCharts);
    window.addEventListener('resize', window.equalizeProductionCharts);
    window.equalizeProductionCharts();

    // ΓöÇΓöÇ Cage filter: reloads Turbo Frames with ?cage=CODE ΓöÇΓöÇ
    window.filterDashboard = function(code) {
        window.__dashboardCage = code;
        var sel = document.getElementById('dashboardCageSelect');
        if (sel && sel.value !== code) sel.value = code;
        var _url = new URL(window.location);
        if (code === 'all') _url.searchParams.delete('cage');
        else _url.searchParams.set('cage', code);
        window.history.replaceState({}, '', _url);

var cageParam = code === 'all' ? null : code;
        // All stats + chart frames pick up Cage + Period + From-date in one place.
        reloadCharts();
        // Forecast overlay is JS-driven, not a Turbo Frame — re-fetch if currently visible
        if (window.__forecastOverlayEnabled && typeof window.loadForecastOverlay === 'function') {
            window.loadForecastOverlay();
        }
    };

    // ΓöÇΓöÇ Yesterday's Production Record popup ΓöÇΓöÇ
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

    // ΓöÇΓöÇ Day Production Complete popup ΓöÇΓöÇ
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

    </script>

    {{-- Sequential frame loader + smooth 1-by-1 entrance --}}
    <style>
        @keyframes frameFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        turbo-frame.frame-fade-in { animation: frameFadeIn 0.4s ease-out both; }
        @media (prefers-reduced-motion: reduce) {
            turbo-frame.frame-fade-in { animation: none !important; }
        }
    </style>
    <script>
    (function () {
        if (window.__dashboardSequencer) return;
        window.__dashboardSequencer = 1;

        // Fade each frame in as it finishes loading so updates appear smoothly,
        // one card/panel at a time instead of everything swapping at once.
        if (!window.__dashboardFrameFade) {
            window.__dashboardFrameFade = 1;
            document.addEventListener('turbo:frame-load', function (e) {
                var frame = e.target;
                if (!frame || frame.tagName !== 'TURBO-FRAME') return;
                frame.classList.remove('frame-fade-in');
                void frame.offsetWidth; // restart the animation
                frame.classList.add('frame-fade-in');
            });
        }

        // Frames carry their URL in data-src so Turbo never auto-fires all of
        // them simultaneously. Load them strictly one at a time, in DOM order,
        // with a small gap so the server + Chart.js render serially.
        var queue = Array.prototype.slice.call(document.querySelectorAll('turbo-frame[data-src]'));
        var i = 0;
        var GAP = 280; // ms between frame starts

        function loadNext() {
            if (i >= queue.length) return;
            var f = queue[i++];
            // Skip frames already given a real src (e.g. by a filter reload).
            if (f.dataset.src && !f.getAttribute('src')) {
                f.setAttribute('src', f.dataset.src);
                // Switching lazy→eager triggers the load immediately, even for
                // frames currently off-screen.
                f.setAttribute('loading', 'eager');
            }
            setTimeout(loadNext, GAP);
        }
        setTimeout(loadNext, 300);
    })();
    </script>

</div>
@endsection
