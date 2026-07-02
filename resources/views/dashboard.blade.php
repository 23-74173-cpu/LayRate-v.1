@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-5">

    <x-page-header title="Dashboard" subtitle="{{ now()->format('l, F j') }} — {{ now()->format('g:i A') }}" />

    {{-- Onboarding Modal --}}
    @if($needsOnboarding)
        <div id="onboardingModal" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);"></div>
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

    {{-- ── Metric Cards (lazy) ── --}}
    <turbo-frame id="dashboard-stats" src="{{ route('dashboard.stats') }}" loading="lazy" class="mb-8 block">
        @include('dashboard._metric-cards-skeleton')
    </turbo-frame>

    {{-- ── Cage Overview: Farm Layout Canvas (lazy) ── --}}
    <turbo-frame id="dashboard-cage-overview" src="{{ route('dashboard.cage-overview') }}" loading="lazy" class="mb-5 block">
        @include('dashboard._cage-overview-skeleton')
    </turbo-frame>

    {{-- ─ Stats Modal (vanilla JS) ── --}}
    <div id="statsModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeStatsModal()"></div>
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

    {{-- ── Feed Today / Mortality Today (lazy) ── --}}
    <turbo-frame id="dashboard-feed-mortality" src="{{ route('dashboard.feed-mortality') }}" loading="lazy">
        @include('dashboard._feed-mortality-skeleton')
    </turbo-frame>

</div>
@endsection
