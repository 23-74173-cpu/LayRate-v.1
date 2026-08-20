@extends('layouts.app')
@section('title', 'Cage Management')

@push('head')
<style>
#canvasContent { position: relative; }
.tile-bg { transition: background-color 0.1s ease; }
.cage-overlay { transition: box-shadow 0.15s ease, border-color 0.15s ease; }
#dragGhost { transition: none; }
#farmCanvas { overscroll-behavior: auto; }
#farmCanvas::-webkit-scrollbar { width: 6px; height: 6px; }
#farmCanvas::-webkit-scrollbar-track { background: transparent; }
#farmCanvas::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.staging-tile { user-select: none; }
.cage-card .slot-mini { flex-shrink: 0; }
.flipper { transform-style: preserve-3d; transition: transform 0.5s ease; }
.cage-card.is-flipped .flipper { transform: rotateY(180deg); }
.front-face, .back-face { backface-visibility: hidden; }
.back-face { transform: rotateY(180deg); }
.cage-card { box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: box-shadow 0.2s ease, transform 0.2s ease; }
.cage-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.07); transform: translateY(-1px); }
.cage-card .icon-btn { width:28px; height:28px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; transition:background-color 0.15s ease, color 0.15s ease; }
.cage-card .icon-btn:hover { background-color:rgba(0,0,0,0.06); }
</style>
@endpush
@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="space-y-5">

    <x-page-header title="Cages" subtitle="Manage battery cage configurations, slots, and sensor placement" />

    <x-fab>
        <a href="{{ route('cages.bulk-add') }}"
           class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Bulk Add Chickens</span>
            <div class="w-8 h-8 rounded-full bg-[#002D5E]/10 flex items-center justify-center">
                <i data-lucide="bird" class="w-4 h-4 text-[#002D5E]"></i>
            </div>
        </a>
        @if($isAdmin)
        <button type="button" onclick="openAddModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Add Cage</span>
            <div class="w-8 h-8 rounded-full bg-[#2D7D46]/10 flex items-center justify-center">
                <i data-lucide="plus" class="w-4 h-4 text-[#2D7D46]"></i>
            </div>
        </button>
        @endif
    </x-fab>

    {{-- ── Farm Layout Canvas (tile-based floor-plan grid, fit-to-width on small screens) ── --}}
    <div class="rounded-xl border p-4 sm:p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
            <h3 class="text-xs font-semibold tracking-[0.05em] uppercase" style="color: #615d59;">Farm Layout</h3>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <button id="clearFilterBtn" class="hidden text-xs font-medium px-3 py-1 rounded-lg transition-colors" style="color: #0075de; border: 1px solid #0075de;" onclick="clearCanvasFilter()">Show all</button>
                @if($isAdmin)
                <button onclick="openGridSettings()"
                        class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors"
                        style="color: #615d59; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    <i data-lucide="grid-3x3" class="w-3.5 h-3.5 inline-block mr-1"></i> Grid Settings
                </button>
                <button id="clearAllBtn" onclick="clearAllCages()"
                        class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors disabled:opacity-45 disabled:cursor-not-allowed"
                        style="color: #9b1c24; border: 1px solid #f0c8cb;"
                        onmouseover="if(!this.disabled)this.style.backgroundColor='#fbe4e6'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Clear All Cages
                </button>
                <div class="relative inline-flex items-center">
                    <x-button id="saveLayoutBtn" onclick="saveLayout()" disabled class="text-xs px-4 py-1.5">
                        Save Layout
                    </x-button>
                    <span id="unsavedDot" class="hidden absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full" style="background-color: #f59e0b; border: 2px solid #ffffff;"></span>
                </div>
                <span id="clearAllMsg" class="hidden text-xs whitespace-nowrap" style="color: #c2703e;">Cages cleared — click Save Layout</span>
                @endif
            </div>
        </div>

        {{-- Canvas container (tile grid auto-rendered by JS, fit-to-width) --}}
        <div id="farmCanvas" class="relative overflow-auto rounded-lg border select-none" style="border-color: #e6e6e6; background-color: #f9fafb; min-height: 120px; max-height: 60vh;">
            <div id="canvasScaler" style="transform-origin: top left;">
                <div id="canvasContent" class="relative inline-block">
                    {{-- Tile background grid --}}
                    <div id="tileGridLayer" class="absolute inset-0" style="pointer-events: none;"></div>
                    {{-- Cage footprint overlays --}}
                    <div id="cageOverlayLayer" class="relative"></div>
                </div>
            </div>
            {{-- Saving overlay --}}
            <div id="farmSaveOverlay" class="hidden absolute inset-0 z-10 items-center justify-center rounded-lg" style="background-color: rgba(255,255,255,0.7);">
                <i data-lucide="loader" class="animate-spin w-8 h-8" style="color: #0075de;"></i>
            </div>
        </div>

        {{-- Drag ghost (follows cursor during drag) --}}
        <div id="dragGhost" class="hidden fixed pointer-events-none z-50 rounded-lg border-2 flex items-center justify-center opacity-80 shadow-lg"
             style="background-color: rgba(255,255,255,0.92);"></div>

        {{-- Staging Area (unplaced cages) --}}
        @php $unplaced = $cages->filter(fn($cg) => is_null($cg->location_row)); @endphp
        <div id="stagingSection" class="mt-4 pt-4 border-t {{ $unplaced->count() > 0 ? '' : 'hidden' }}" style="border-color: #e6e6e6;">
            <h4 class="text-xs font-semibold tracking-[0.05em] uppercase mb-3" style="color: #615d59;">Unplaced Cages — drag to grid</h4>
            <div id="stagingArea" class="flex flex-wrap gap-3 min-h-[3.5rem]">
                @foreach($unplaced as $uc)
                @php
                    $isTiny = $uc->rows == 1 && $uc->slots_per_row == 1;
                    $isSmall = $uc->rows <= 2 || $uc->slots_per_row <= 2;
                @endphp
                <div class="staging-tile rounded-lg border-2 px-5 py-3 sm:px-4 sm:py-2 min-h-[3rem] flex flex-col items-center justify-center {{ $isAdmin ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer' }}"
                     style="border-color: {{ $uc->color }}; background-color: {{ $uc->colorSoft }};"
                     draggable="{{ $isAdmin ? 'true' : 'false' }}"
                     data-cage-id="{{ $uc->id }}"
                     data-cage-code="{{ $uc->cage_code }}"
                     @if($isAdmin) ondragstart="handleDragStart(event, {{ $uc->id }})" @endif>
                    @if($isTiny)
                    <span class="font-bold leading-none text-center" style="font-size:14px;color: {{ $uc->color }};overflow:hidden;text-overflow:ellipsis;max-width:100%;display:inline-block;">
                        {{ \Illuminate\Support\Str::after($uc->cage_code, 'CAGE-') }}
                    </span>
                    @elseif($isSmall)
                    <span class="text-sm font-semibold leading-tight text-center" style="color: {{ $uc->color }};overflow:hidden;text-overflow:ellipsis;word-break:break-all;max-width:100%;display:inline-block;">
                        {{ $uc->cage_code }}
                    </span>
                    @else
                    <span class="text-sm font-semibold leading-tight text-center" style="color: {{ $uc->color }};">{{ $uc->cage_code }}</span>
                    <span class="text-xs leading-tight text-center" style="color: #615d59;">{{ $uc->rows }}×{{ $uc->slots_per_row }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- Error Toast --}}
        <div id="dragErrorToast" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-50 rounded-lg px-4 py-2 text-sm font-medium text-white" style="background-color: #9b1c24;"></div>

        {{-- Cage Info Popup (positioned via JS, lives outside scaled canvas) --}}
        <style>
            #cageInfoPopup { perspective: 1000px; }
            #cageInfoPopup .icon-btn { width:28px; height:28px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; transition:background-color 0.15s ease, color 0.15s ease; }
            #cageInfoPopup .icon-btn:hover { background-color:rgba(0,0,0,0.06); }
            #cageInfoPopup .flipper { transform-style: preserve-3d; transition: transform 0.35s ease; display: grid; }
            #cageInfoPopup .flipper.flipped { transform: rotateY(180deg); }
            #cageInfoPopup .front-face,
            #cageInfoPopup .back-face { grid-area: 1 / 1; backface-visibility: hidden; background-color: #ffffff; border-radius: 11px; }
            #cageInfoPopup .back-face { transform: rotateY(180deg); }
        </style>
        <div id="cageInfoPopup" class="hidden fixed z-50 rounded-xl border bg-white shadow-lg p-0 w-64" style="border-color: #e6e6e6; max-width: calc(100vw - 2rem); max-height: calc(100vh - 1.5rem); overflow-y: auto;">
            <div id="cageInfoPopupContent"></div>
        </div>
    </div>

    {{-- ── Grid Settings Modal (resize canvas dimensions post-onboarding) ── --}}
    <div id="gridSettingsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeGridSettings()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Grid Settings</h2>
                <button onclick="closeGridSettings()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <p class="text-sm mb-4" style="color: #615d59;">Adjust the overall canvas tile dimensions. Shrinking may require moving or removing cages that extend beyond the new bounds.</p>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Rows</label>
                    <input type="number" id="gridSettingsRows" value="{{ $gridRows }}" min="1" max="50"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Columns</label>
                    <input type="number" id="gridSettingsCols" value="{{ $gridCols }}" min="1" max="50"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
            </div>
            <div id="gridSettingsWarning" class="hidden mb-4 rounded-lg p-3 text-sm" style="background-color: #fbe4e6; border: 1px solid #f3cdd0; color: #9b1c24;"></div>
            <div class="flex gap-3">
                <button type="button" onclick="closeGridSettings()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <x-button type="button" onclick="applyGridSettings()" class="flex-1 py-2.5">
                    Apply
                </x-button>
            </div>
        </div>
    </div>

    {{-- ── Tab Bar (Notion underline style, scrollable + hidden scrollbar on mobile) ── --}}
    <div class="cage-tabs flex items-center gap-0 border-b overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]" style="border-color: #e6e6e6; -webkit-overflow-scrolling: touch;">
        <button type="button" onclick="filterCage('all')" class="cage-tab px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap cursor-pointer"
                data-tab="all"
                style="border-bottom-color: #002D5E; color: #1f1f1f;">
            All
            <span class="ml-1 text-xs" style="color: #a39e98;">({{ $cages->count() }})</span>
        </button>
        @foreach($cages as $cage)
        <button type="button" onclick="filterCage('{{ $cage->cage_code }}')" class="cage-tab px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap cursor-pointer"
                data-tab="{{ $cage->cage_code }}"
                style="border-bottom-color: transparent; color: #615d59;">
            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background-color: {{ $cage->color }};"></span>
            {{ $cage->cage_code }}
            <span class="ml-1 text-xs" style="color: #a39e98;">({{ $cage->cageSlots->count() }})</span>
        </button>
        @endforeach
    </div>

    {{-- ── Cage Cards (responsive grid) ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse($cages as $cage)
        @php
            $color = $cage->color;
            $colorSoft = $cage->colorSoft;
            $slotsByRow = $cage->cageSlots->groupBy('row_number');
            $sensorCount = $cage->cageSlots->filter(fn($s) => $s->hasBreakbeam())->count();
            $occupiedCount = $cage->cageSlots->where('current_occupancy', '>', 0)->count();
            $primaryHen = $cage->hens->first();
            $slotGridMaxH = min(($cage->rows ?? 3), 4) * 36 + (min($cage->rows ?? 3, 4) - 1) * 4;
            $hasLargeGrid = ($cage->rows ?? 1) > 4 || ($cage->slots_per_row ?? 1) > 6;
            $currentHens = $cage->cageSlots->sum('current_occupancy');
            $totalSlots = $cage->cageSlots->count();
            $maxPerSlot = $cage->max_chickens_per_slot ?? 4;
            $occupancyPct = $cage->total_capacity > 0 ? round(($currentHens / $cage->total_capacity) * 100) : 0;
            // Health cue for accent bar: inactive=grey, near-cap=amber, over=red, ok=normal color
            if (!$cage->is_active) {
                $accentColor = '#a39e98';
                $healthLevel = 'inactive';
            } elseif ($occupancyPct > 100) {
                $accentColor = '#9b1c24';
                $healthLevel = 'over';
            } elseif ($occupancyPct >= 90) {
                $accentColor = '#c2703e';
                $healthLevel = 'near';
            } else {
                $accentColor = $color;
                $healthLevel = 'ok';
            }
            $sizeColors = [
                'small'    => ['bg' => '#d6f0e3', 'txt' => '#2D7D46'],
                'medium'   => ['bg' => '#dcebfa', 'txt' => '#1D4E8F'],
                'large'    => ['bg' => '#fae3d0', 'txt' => '#C2703E'],
                'jumbo'    => ['bg' => '#e9e0f5', 'txt' => '#6B4C8A'],
                'unsorted' => ['bg' => '#f0f0f0', 'txt' => '#6B7280'],
            ];
            $cageSizes = $eggSizeByCage->get($cage->id, collect());
        @endphp
        <div class="cage-card rounded-xl border w-full"
             data-cage-code="{{ $cage->cage_code }}"
             style="perspective:1000px; background-color:#ffffff; border-color:#e6e6e6;">

             <div class="flipper relative" id="flipper-{{ $cage->id }}" style="min-height:260px;">

                {{-- ═══════ FRONT FACE ═══════ --}}
                <div class="front-face absolute inset-0 flex flex-col" style="background-color:#ffffff; z-index:2;">
                    {{-- Header bar with health-aware color accent + divider --}}
                    <div class="flex items-center gap-3 px-3 pt-3 pb-2 border-b" style="background-color:#f8f8f8; border-bottom-color:#e6e6e6; border-top-left-radius:11px; border-top-right-radius:11px;">
                        {{-- Accent bar (color shifts with occupancy health) --}}
                        <div style="width:4px; align-self:stretch; background-color:{{ $accentColor }}; border-radius:3px; flex-shrink:0;"></div>
                        {{-- Left: name + badge --}}
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <span class="text-sm font-bold shrink-0" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full shrink-0 font-semibold" style="background-color:{{ $cage->is_active ? '#e8f5ec' : '#f0f0f0' }}; color:{{ $cage->is_active ? '#1f6b3a' : '#615d59' }};">
                                {{ $cage->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        {{-- Right: occupancy count (equal weight) + actions --}}
                        <div class="flex items-center gap-1 shrink-0">
                            <span id="occupancy-{{ $cage->id }}" class="text-sm font-semibold" style="color:{{ $occupancyPct >= 90 ? '#9b1c24' : ($occupancyPct >= 75 ? '#c2703e' : '#1f1f1f') }};">{{ $currentHens }}/{{ $cage->total_capacity ?? '?' }}</span>
                            <a href="{{ route('cages.bulk-add') }}?cage_id={{ $cage->id }}"
                               class="icon-btn" style="color:#0075de;" aria-label="Bulk add hens" title="Add hens">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                            </a>
                            <button onclick="flipCage({{ $cage->id }})"
                                    class="icon-btn" style="color:#615d59;" aria-label="Show details" title="Details & settings">
                                <i data-lucide="info" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Slot Grid with density shading (flex-1 fills remaining space) --}}
                    <div class="flex-1 min-h-0 px-4 pb-2 pt-2 {{ $hasLargeGrid ? 'overflow-y-auto' : '' }}" style="{{ $hasLargeGrid ? 'max-height:' . $slotGridMaxH . 'px;' : '' }}">
                        <div class="grid gap-1 slot-grid-{{ $cage->id }}" style="grid-template-columns:repeat({{ min($cage->slots_per_row ?? 3, 6) }}, 32px);justify-content:flex-start;">
                            @foreach($cage->cageSlots as $slot)
                            @php
                                $isSensor = $slot->hasBreakbeam();
                                $occupancy = $slot->current_occupancy;
                                $fillRatio = $maxPerSlot > 0 ? min(1, $occupancy / $maxPerSlot) : 0;
                                if ($isSensor) {
                                    $slotBg = '#d6f0e3';
                                    $slotBorder = '#2a9d6a';
                                    $densityHint = 'sensor';
                                } elseif ($occupancy > 0) {
                                    // Density shading: light at low fill, darker at high fill
                                    $gray = round(248 - ($fillRatio * 40));
                                    $slotBg = "rgb({$gray},{$gray},{$gray})";
                                    $slotBorder = $gray > 235 ? '#e6e6e6' : '#d1d5db';
                                    $densityHint = round($fillRatio * 100) . '%';
                                } else {
                                    $slotBg = '#ffffff';
                                    $slotBorder = '#e6e6e6';
                                    $densityHint = 'empty';
                                }
                            @endphp
                    <button type="button"
                            onclick="expandSlot({{ $slot->id }}, {{ $cage->id }}, '{{ $cage->cage_code }}')"
                            class="slot-mini w-8 h-8 rounded flex flex-col items-center justify-center text-xs transition-all relative"
                            style="background-color:{{ $slotBg }}; border:1px solid {{ $slotBorder }};"
                            title="Slot {{ $slot->row_number }}-{{ $slot->column_number }}: {{ $occupancy }} hens{{ $isSensor ? ' (sensor equipped)' : '' }}"
                            aria-label="Slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $occupancy }} hens"
                            data-slot-id="{{ $slot->id }}"
                            data-original-number="{{ $slot->slot_number }}">
                                @if($isSensor)
                                <span class="absolute top-0 right-0 w-1.5 h-1.5 rounded-bl" style="background-color:#0075de;" title="Sensor equipped"></span>
                                @endif
                                <span class="slot-reorder-number hidden text-xs font-bold" style="color:#002D5E;">{{ $slot->slot_number }}</span>
                                @if($occupancy > 0)
                                <span id="slot-occ-{{ $slot->id }}" class="text-xs font-semibold" style="color:{{ $isSensor ? '#1f6b3a' : '#1f1f1f' }};">{{ $occupancy }}</span>
                                @else
                                <span id="slot-occ-{{ $slot->id }}" class="text-xs" style="color:#d1d5db;">—</span>
                                @endif
                            </button>
                            @endforeach
                        </div>
                        {{-- Reorder bar --}}
                        <div id="reorderBar-{{ $cage->id }}" class="hidden mt-2 flex items-center justify-between text-xs" style="color:#615d59;">
                            <span>Drag slots to renumber</span>
                            <div class="flex items-center gap-2">
                                <button onclick="saveReorder({{ $cage->id }})" class="px-2 py-1 rounded text-white text-xs font-medium" style="background-color:#002D5E;">Save</button>
                                <button onclick="cancelReorder({{ $cage->id }})" class="px-2 py-1 rounded text-xs" style="background-color:#e6e6e6;">Cancel</button>
                            </div>
                        </div>
                    </div>

                    {{-- Footer: legend bar pinned to bottom --}}
                    <div class="flex items-center gap-3 px-4 py-2 border-t text-[10px] leading-none shrink-0" style="border-color:#e6e6e6; color:#a39e98;">
                        <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded" style="background-color:#d6f0e3;border:1px solid #2a9d6a;"></span> Sensor</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded" style="background-color:#f6f5f4;border:1px solid #e6e6e6;"></span> Occupied</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded" style="background-color:#ffffff;border:1px solid #e6e6e6;"></span> Empty</span>
                    </div>
                </div>

                {{-- ═══════ BACK FACE ═══════ --}}
                <div class="back-face absolute inset-0 rounded-xl flex flex-col" style="background-color:#ffffff; z-index:1;">
                    {{-- Back header with matching accent bar --}}
                    <div class="flex items-center gap-3 px-3 pt-3 pb-2 border-b" style="background-color:#f8f8f8; border-bottom-color:#e6e6e6; border-top-left-radius:11px; border-top-right-radius:11px;">
                        <div style="width:4px; align-self:stretch; background-color:{{ $accentColor }}; border-radius:3px; flex-shrink:0;"></div>
                        <span class="text-sm font-bold flex-1" style="color:{{ $color }};">{{ $cage->cage_code }}</span>
                        <button onclick="flipCage({{ $cage->id }})"
                                class="icon-btn" style="color:#615d59;" aria-label="Back to front" title="Back">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>

                    {{-- Specs (no scrollbar, generous spacing) --}}
                    <div class="flex-1 px-4 py-3 space-y-2.5">
                        <div class="grid grid-cols-[60px_1fr] gap-x-2 gap-y-1.5 text-xs">
                            <span class="font-medium" style="color:#a39e98;">Dims</span>
                            <span style="color:#1f1f1f;">{{ $cage->rows ?? '?' }}×{{ $cage->slots_per_row ?? '?' }} · {{ $totalSlots }} slots</span>

                            <span class="font-medium" style="color:#a39e98;">Cap</span>
                            <span style="color:#1f1f1f;">{{ $currentHens }} / {{ $cage->total_capacity ?? '?' }} hens</span>

                            @if($primaryHen)
                            <span class="font-medium" style="color:#a39e98;">Breed</span>
                            <span style="color:#1f1f1f;">{{ $primaryHen->breed }} · {{ $primaryHen->current_age_weeks }}w</span>
                            @endif

                            @if($sensorCount > 0)
                            <span class="font-medium" style="color:#a39e98;">Sensor</span>
                            <span style="color:#1f1f1f;">{{ $sensorCount }} slot{{ $sensorCount > 1 ? 's' : '' }}</span>
                            @endif
                        </div>

                        {{-- Egg size tags --}}
                        @if($cageSizes->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1">
                            @foreach(['small','medium','large','jumbo','unsorted'] as $sz)
                                @php $entry = $cageSizes->firstWhere('egg_size', $sz); @endphp
                                @if($entry && $entry->total > 0)
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold leading-tight"
                                      style="background:{{ $sizeColors[$sz]['bg'] }}; color:{{ $sizeColors[$sz]['txt'] }};">
                                    {{ ucfirst($sz) }} {{ number_format($entry->total) }}
                                </span>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Action buttons with tooltips --}}
                    <div class="flex items-center justify-around px-4 py-2 border-t shrink-0" style="border-color:#e6e6e6;">
                        @if($isAdmin)
                        <button onclick="openEditModal({{ $cage->id }}, '{{ $cage->cage_code }}', {{ is_null($cage->location_row) ? 'null' : $cage->location_row }}, {{ is_null($cage->location_column) ? 'null' : $cage->location_column }}, {{ $cage->rows ?? 0 }}, {{ $cage->slots_per_row ?? 0 }}, {{ $cage->max_chickens_per_slot ?? 0 }}, {{ $cage->is_active ? 1 : 0 }})"
                                class="icon-btn" style="color:#615d59;" aria-label="Edit cage" title="Edit cage">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="toggleReorderMode({{ $cage->id }})"
                                class="icon-btn reorder-toggle" style="color:#615d59;" aria-label="Renumber slots" title="Renumber slots">
                            <i data-lucide="list-ordered" class="w-3.5 h-3.5"></i>
                        </button>
                        @endif
                        <button onclick="window.open('{{ route('cages.print-label', $cage) }}', 'print-{{ $cage->id }}', 'width=900,height=700')"
                                class="icon-btn" style="color:#615d59;" aria-label="Print cage label" title="Print label">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                        </button>
                        @if($isAdmin)
                        <button onclick="openDeleteModal({{ $cage->id }}, '{{ $cage->cage_code }}')"
                                class="icon-btn" style="color:#a39e98;" aria-label="Delete cage" title="Delete cage">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Expanded Detail Panel (outside flipper, stays on front) --}}
            <div id="slotExpandPanel-{{ $cage->id }}" class="hidden border-t" style="border-color:#e6e6e6; background-color:#f6f5f4;">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span id="slotPanelTitle-{{ $cage->id }}" class="text-sm font-semibold" style="color:#1f1f1f;">Slot details</span>
                        <button onclick="closeSlotExpand({{ $cage->id }})" class="p-1.5 rounded hover:bg-black/5 transition-colors" aria-label="Close">
                            <i data-lucide="x" class="w-4 h-4" style="color:#615d59;"></i>
                        </button>
                    </div>
                    <div id="slotPanelContent-{{ $cage->id }}">
                        <div class="text-xs text-center py-4" style="color:#a39e98;">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
        {{-- @empty marker --}}
        @empty
        <div class="w-full rounded-xl border p-10 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
            No cages yet. Click "+ Add Cage" to get started.
        </div>
        @endforelse
    </div>

    {{-- ── Add Cage Modal (full complexity with live preview) ── --}}
    <div id="addCageModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddModal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl p-6 max-h-[90vh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Battery Cage Configuration</h2>
                <button onclick="closeAddModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('cages.store') }}" id="addCageForm" data-turbo="false"
                  onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Adding\u2026')">
                @csrf
                <div class="space-y-4">
                    <div class="rounded-lg p-3" style="background-color: #f0f7ff; border: 1px solid #b3d4fc;">
                        <div class="flex items-center gap-2">
                            <i data-lucide="info" class="w-4 h-4" style="color: #0075de;"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: #005baa;">Cage code will be auto-generated</p>
                                <p class="text-xs" style="color: #615d59;" id="addNextCode">Next: CAGE-{{ $nextCageCode }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                        <div class="text-xs font-semibold tracking-[0.05em] uppercase mb-1" style="color: #615d59;">Canvas Position</div>
                        <div class="text-sm" style="color: #a39e98;">Unplaced — drag to grid after creation</div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Rows</label>
                            <input type="number" name="rows" id="addRows" value="{{ old('rows', 3) }}" min="1" max="10"
                                   oninput="updateAddPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                            <x-input-error name="rows" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Slots</label>
                            <input type="number" name="slots_per_row" id="addSlotsPerRow" value="{{ old('slots_per_row', 5) }}" min="1" max="100"
                                   oninput="updateAddPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                            <x-input-error name="slots_per_row" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Max/Slot</label>
                            <input type="number" name="max_chickens_per_slot" id="addMaxPerSlot" value="{{ old('max_chickens_per_slot', 4) }}" min="1" max="10"
                                   oninput="updateAddPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                            <x-input-error name="max_chickens_per_slot" />
                        </div>
                    </div>

                    {{-- Configuration Summary --}}
                    <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                        <div class="text-xs font-semibold tracking-[0.05em] uppercase mb-2" style="color: #615d59;">Configuration Summary</div>
                        <div class="flex justify-between text-sm">
                            <span style="color: #615d59;">Total slots</span>
                            <span class="font-semibold" style="color: #1f1f1f;" id="addSummarySlots">15</span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span style="color: #615d59;">Total capacity</span>
                            <span class="font-semibold" style="color: #0075de;" id="addSummaryCapacity">60 hens</span>
                        </div>
                    </div>

                    {{-- Layout Preview --}}
                    <div>
                        <div class="text-xs font-semibold tracking-[0.05em] uppercase mb-2" style="color: #615d59;">Layout Preview</div>
                        <div id="addPreviewContainer" class="border rounded-lg p-3 overflow-hidden" style="border-color: #e6e6e6; background-color: #ffffff; max-width: 100%;">
                            <div class="flex gap-1 mb-1" id="addPreviewColHeaders"></div>
                            <div id="addPreviewGrid"></div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="button" onclick="closeAddModal()"
                            class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                            style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                            onmouseover="this.style.backgroundColor='#f6f5f4'"
                            onmouseout="this.style.backgroundColor='transparent'">
                        Cancel
                    </button>
                    <x-button type="submit" class="flex-1 py-2.5">
                        Add Cage
                    </x-button>
                </div>
            </form>
        </div>
    </div>

@if(session('reopen_add_cage'))
<x-modal-reopen modal-id="addCageModal" session-key="reopen_add_cage" guard="addCage">
    openAddModal();
</x-modal-reopen>
@endif

    {{-- ── No Unplaced Chickens Modal ────────────────────── --}}
    <div id="noChickensModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeNoChickensModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">No Unplaced Chickens</h2>
                <button onclick="closeNoChickensModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <p class="text-sm" style="color: #6B7280;">
                All registered chickens are already assigned to cages. Register new chickens before using bulk placement.
            </p>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeNoChickensModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Maybe Later
                </button>
                <button type="button" onclick="closeNoChickensModal(); openRegisterModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #ffffff; background-color: #002D5E;"
                        onmouseover="this.style.backgroundColor='#0a3d7a'"
                        onmouseout="this.style.backgroundColor='#002D5E'">
                    Register Chickens
                </button>
            </div>
        </div>
    </div>

    {{-- ── Edit Cage Modal — with per-slot sensor config ── --}}
    <div id="editCageModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditModal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl p-6 max-h-[90vh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Cage — <span id="editCageCode"></span></h2>
                <button onclick="closeEditModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <form method="POST" action="" id="editCageForm" data-turbo="false" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
                @csrf @method('PUT')

                <div id="editResizeError" class="hidden mb-4 rounded-lg p-3" style="background-color: #fbe4e6; border: 1px solid #f3cdd0;">
                    <div class="flex items-start gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0" style="color: #9b1c24;"></i>
                        <p class="text-sm" style="color: #9b1c24;" id="editResizeErrorText"></p>
                    </div>
                </div>

                <div id="editResizeCollisionWarning" class="hidden mb-4 rounded-lg p-3" style="background-color: #fef3cd; border: 1px solid #f59e0b;">
                    <div class="flex items-start gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0" style="color: #92400e;"></i>
                        <p class="text-sm" style="color: #92400e;" id="editResizeCollisionText"></p>
                    </div>
                </div>

                @if($errors->has('rows') || $errors->has('slots_per_row') || $errors->has('max_chickens_per_slot') || $errors->has('is_active') || $errors->has('slots') || $errors->has('dht22_count') || $errors->has('resize'))
                <div class="mb-4 rounded-lg p-3" style="background-color: #fbe4e6; border: 1px solid #f3cdd0;">
                    <div class="space-y-1 text-sm" style="color: #9b1c24;">
                        @foreach(['rows','slots_per_row','max_chickens_per_slot','is_active','slots','dht22_count','resize'] as $errKey)
                        @error($errKey)
                        <p>{{ $message }}</p>
                        @enderror
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="space-y-4">
                    <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                        <div class="text-xs font-semibold tracking-[0.05em] uppercase mb-1" style="color: #615d59;">Canvas Position</div>
                        <div id="editCanvasPosition" class="text-sm" style="color: #31302e;">—</div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Rows</label>
                            <input type="number" name="rows" id="editRows" value="{{ old('rows', 3) }}" min="1" max="10"
                                   oninput="updateEditPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Slots</label>
                            <input type="number" name="slots_per_row" id="editSlotsPerRow" value="{{ old('slots_per_row', 5) }}" min="1" max="100"
                                   oninput="updateEditPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Max/Slot</label>
                            <input type="number" name="max_chickens_per_slot" id="editMaxPerSlot" value="{{ old('max_chickens_per_slot', 4) }}" min="1" max="10"
                                   oninput="updateEditPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="editActive" name="is_active" type="checkbox" value="1" class="w-4 h-4 rounded" style="accent-color: #0075de;" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label for="editActive" class="text-sm" style="color: #31302e;">Active</label>
                    </div>

                    {{-- Configuration Summary (shared preview component) --}}
                    <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                        <div class="text-xs font-semibold tracking-[0.05em] uppercase mb-2" style="color: #615d59;">Configuration Summary</div>
                        <div class="flex justify-between text-sm">
                            <span style="color: #615d59;">Total slots</span>
                            <span class="font-semibold" style="color: #1f1f1f;" id="editSummarySlots">15</span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span style="color: #615d59;">Total capacity</span>
                            <span class="font-semibold" style="color: #0075de;" id="editSummaryCapacity">60 hens</span>
                        </div>
                    </div>

                    {{-- Layout Preview (shared component reused from Add Cage) --}}
                    <div>
                        <div class="text-xs font-semibold tracking-[0.05em] uppercase mb-2" style="color: #615d59;">Layout Preview</div>
                        <div id="editPreviewContainer" class="border rounded-lg p-3 overflow-hidden" style="border-color: #e6e6e6; background-color: #ffffff; max-width: 100%;">
                            <div class="flex gap-1 mb-1" id="editPreviewColHeaders"></div>
                            <div id="editPreviewGrid"></div>
                        </div>
                    </div>

                    {{-- Counting sensor (IR break beam) — per slot (items 15, 21, 23, 24) --}}
                    <div class="border-t pt-4" style="border-color: #e6e6e6;">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <i data-lucide="scan-line" class="w-4 h-4" style="color: #0075de;"></i>
                                <span class="text-xs font-semibold tracking-[0.05em] uppercase" style="color: #615d59;">Counting sensor (IR break beam)</span>
                            </div>
                            <span id="irAvailability" class="text-xs" style="color: #a39e98;"></span>
                        </div>
                        <p class="text-xs mb-3" style="color: #a39e98;">Device IDs are assigned automatically on save.</p>
                        <div id="editSlotSensors" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <p class="text-xs text-center py-3" style="color: #a39e98;">Loading slots...</p>
                        </div>
                    </div>

                    {{-- Temperature & Humidity sensor (DHT22) — cage level (items 21, 23, 24) --}}
                    <div class="border-t pt-4" style="border-color: #e6e6e6;">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <i data-lucide="thermometer" class="w-4 h-4" style="color: #0075de;"></i>
                                <span class="text-xs font-semibold tracking-[0.05em] uppercase" style="color: #615d59;">Temperature &amp; Humidity sensor (DHT22)</span>
                            </div>
                            <span id="dhtAvailability" class="text-xs" style="color: #a39e98;"></span>
                        </div>
                        <input type="hidden" name="dht22_count" id="dht22CountInput" value="">
                        <div id="editDhtList" class="space-y-2">
                            <p class="text-xs text-center py-3" style="color: #a39e98;">Loading…</p>
                        </div>
                        <button type="button" id="addDhtBtn" onclick="addDht22()"
                                class="mt-2 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors disabled:opacity-45 disabled:cursor-not-allowed"
                                style="color: #0075de; border: 1px solid #0075de;">
                            + Add DHT22
                        </button>
                        <p id="dhtLimitMsg" class="hidden text-xs mt-1" style="color: #9b1c24;"></p>
                    </div>
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="button" onclick="closeEditModal()"
                            class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                            style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                            onmouseover="this.style.backgroundColor='#f6f5f4'"
                            onmouseout="this.style.backgroundColor='transparent'">
                        Cancel
                    </button>
                    <x-button type="submit" class="flex-1 py-2.5">
                        Save Changes
                    </x-button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
var cagesBase = '{{ url('cages') }}';

// ── Tab Filter ────────────────────────────────────────────
function filterCage(code) {
    if (window.__cagesActiveTab === code) return;
    window.__cagesActiveTab = code;

    const cageColors = @json(\App\Models\Cage::getColorMap());
    document.querySelectorAll('.cage-tab').forEach(tab => {
        if (tab.dataset.tab === code) {
            tab.style.borderBottomColor = code === 'all' ? '#002D5E' : (cageColors[code] || '#002D5E');
            tab.style.color = '#1f1f1f';
        } else {
            tab.style.borderBottomColor = 'transparent';
            tab.style.color = '#615d59';
        }
    });
    document.querySelectorAll('.cage-card').forEach(card => {
        card.style.display = (code === 'all' || card.dataset.cageCode === code) ? '' : 'none';
    });
}

// ── Slot Expand Panel ────────────────────────────────────
function expandSlot(slotId, cageId, cageCode) {
    const panel = document.getElementById('slotExpandPanel-' + cageId);
    const content = document.getElementById('slotPanelContent-' + cageId);
    const title = document.getElementById('slotPanelTitle-' + cageId);
    panel.classList.remove('hidden');
    fetch(`${cagesBase}/slots/${slotId}/hens-json`)
        .then(r => r.json())
        .then(data => {
            title.textContent = cageCode + ' — Slot ' + data.slot.row_number + '-' + data.slot.column_number + ' (#' + data.slot.slot_number + ')';

            // Slot summary: hen count + today's egg status + cage notes (item 16)
            let summary = '<div class="flex flex-wrap items-center gap-2 mb-3 text-xs">';
            summary += '<span class="px-2 py-1 rounded-lg" style="background-color: #ffffff; border: 1px solid #e6e6e6; color: #31302e;">' + data.slot.current_occupancy + ' hen(s)</span>';
            summary += '<span class="px-2 py-1 rounded-lg" style="background-color: #ffffff; border: 1px solid #e6e6e6; color: #31302e;"><span style="color:#615d59;">Eggs today:</span> ' + data.slot.egg_status + '</span>';
            summary += '</div>';
            let notesHtml = '';
            if (data.notes && data.notes.length > 0) {
                notesHtml += '<div class="mt-3 pt-3 border-t" style="border-color: #e6e6e6;">';
                notesHtml += '<div class="text-xs font-semibold mb-1.5" style="color: #615d59;">Notes tagged to ' + cageCode + '</div>';
                data.notes.forEach(function(n) {
                    notesHtml += '<div class="text-xs mb-1.5" style="color: #31302e;">' +
                        '<span style="color:#a39e98;">' + n.created_at + ' — </span>' +
                        n.body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>';
                });
                notesHtml += '</div>';
            }

            if (data.hens.length === 0) {
                content.innerHTML = summary + '<p class="text-xs text-center py-3" style="color: #a39e98;">No hens in this slot.</p>' + notesHtml;
                return;
            }
            let html = summary + '<div class="space-y-1.5">';
            data.hens.forEach(hen => {
                html += '<div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded border px-3 py-2 text-xs" style="background-color: #ffffff; border-color: #e6e6e6;">';
                html += '<span class="w-24 font-mono" style="color: #615d59;">' + (hen.tag_code || '—') + '</span>';
                html += '<span class="w-32" style="color: #31302e;">' + hen.breed + '</span>';
                html += '<span class="w-12" style="color: #615d59;">' + hen.current_age_weeks + 'w</span>';
                html += '<span class="flex-1">';
                html += '<span class="text-xs px-1.5 py-0.5 rounded-full" style="background-color: ' + (hen.is_active ? '#e8f5ec' : '#f0f0f0') + '; color: ' + (hen.is_active ? '#1f6b3a' : '#615d59') + ';">';
                html += (hen.is_active ? 'Active' : 'Inactive') + '</span></span>';
                html += '<div class="flex items-center gap-1">';
                html += '<button type="button" onclick="openMoveModal(\'' + hen.id + '\', 1, \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + hen.breed + '\')" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Move hen"><i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></button>';
                html += '<button type="button" onclick="openRemoveModal(\'' + hen.id + '\', 1, \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + hen.breed + '\')" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #9b1c24;" aria-label="Remove hen"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>';
                html += '</div></div>';
            });
            html += '</div>';
            html += '<div class="mt-3 flex items-center gap-2">';
            const ids = data.hens.map(h => h.id).join(',');
            html += '<button type="button" onclick="openMoveModal(\'' + ids + '\', ' + data.hens.length + ', \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + (data.hens[0]?.breed || '') + '\')" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Move all (' + data.hens.length + ')"><i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></button>';
            html += '<button type="button" onclick="openRemoveModal(\'' + ids + '\', ' + data.hens.length + ', \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + (data.hens[0]?.breed || '') + '\')" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #9b1c24;" aria-label="Remove all (' + data.hens.length + ')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>';
            html += '</div>';
            content.innerHTML = html + notesHtml;
            lucide.createIcons();
        })
        .catch(() => {
            content.innerHTML = '<p class="text-xs text-center py-3" style="color: #9b1c24;">Failed to load hens.</p>';
        });
}

function closeSlotExpand(cageId) {
    document.getElementById('slotExpandPanel-' + cageId).classList.add('hidden');
}

// ── Cage Info Popup slot expansion (exact copy of the cage card's expandSlot) ──
function expandSlotInPopup(btn) {
    var slotId = parseInt(btn.dataset.slotId);
    var cageId = parseInt(btn.dataset.cageId);
    var cageCode = btn.dataset.cageCode;
    const panel = document.getElementById('slotExpandPanel-popup');
    const content = document.getElementById('slotPanelContent-popup');
    const title = document.getElementById('slotPanelTitle-popup');
    panel.classList.remove('hidden');

    // Remember which slot is expanded so the popup can re-open it after a move
    var anchorPopup = document.getElementById('cageInfoPopup');
    if (anchorPopup) {
        anchorPopup._openSlotId = slotId;
        anchorPopup._openSlotCageId = cageId;
    }

    // Widen the popup so the panel renders like the cage cards below
    var popup = document.getElementById('cageInfoPopup');
    if (popup) {
        popup.style.width = 'min(32rem, calc(100vw - 2rem))';
        if (popup._cageInfoBtnEl) positionCageInfoPopup(popup, popup._cageInfoBtnEl.getBoundingClientRect());
    }

    fetch(`${cagesBase}/slots/${slotId}/hens-json`)
        .then(r => r.json())
        .then(data => {
            title.textContent = cageCode + ' — Slot ' + data.slot.row_number + '-' + data.slot.column_number + ' (#' + data.slot.slot_number + ')';

            // Slot summary: hen count + today's egg status + cage notes (item 16)
            let summary = '<div class="flex flex-wrap items-center gap-2 mb-3 text-xs">';
            summary += '<span class="px-2 py-1 rounded-lg" style="background-color: #ffffff; border: 1px solid #e6e6e6; color: #31302e;">' + data.slot.current_occupancy + ' hen(s)</span>';
            summary += '<span class="px-2 py-1 rounded-lg" style="background-color: #ffffff; border: 1px solid #e6e6e6; color: #31302e;"><span style="color:#615d59;">Eggs today:</span> ' + data.slot.egg_status + '</span>';
            summary += '</div>';
            let notesHtml = '';
            if (data.notes && data.notes.length > 0) {
                notesHtml += '<div class="mt-3 pt-3 border-t" style="border-color: #e6e6e6;">';
                notesHtml += '<div class="text-xs font-semibold mb-1.5" style="color: #615d59;">Notes tagged to ' + cageCode + '</div>';
                data.notes.forEach(function(n) {
                    notesHtml += '<div class="text-xs mb-1.5" style="color: #31302e;">' +
                        '<span style="color:#a39e98;">' + n.created_at + ' — </span>' +
                        n.body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>';
                });
                notesHtml += '</div>';
            }

            if (data.hens.length === 0) {
                content.innerHTML = summary + '<p class="text-xs text-center py-3" style="color: #a39e98;">No hens in this slot.</p>' + notesHtml;
                return;
            }
            let html = summary + '<div class="space-y-1.5">';
            data.hens.forEach(hen => {
                html += '<div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded border px-3 py-2 text-xs" style="background-color: #ffffff; border-color: #e6e6e6;">';
                html += '<span class="w-24 font-mono" style="color: #615d59;">' + (hen.tag_code || '—') + '</span>';
                html += '<span class="w-32" style="color: #31302e;">' + hen.breed + '</span>';
                html += '<span class="w-12" style="color: #615d59;">' + hen.current_age_weeks + 'w</span>';
                html += '<span class="flex-1">';
                html += '<span class="text-xs px-1.5 py-0.5 rounded-full" style="background-color: ' + (hen.is_active ? '#e8f5ec' : '#f0f0f0') + '; color: ' + (hen.is_active ? '#1f6b3a' : '#615d59') + ';">';
                html += (hen.is_active ? 'Active' : 'Inactive') + '</span></span>';
                html += '<div class="flex items-center gap-1">';
                html += '<button type="button" onclick="openMoveModal(\'' + hen.id + '\', 1, \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + hen.breed + '\')" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Move hen"><i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></button>';
                html += '<button type="button" onclick="openRemoveModal(\'' + hen.id + '\', 1, \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + hen.breed + '\')" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #9b1c24;" aria-label="Remove hen"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>';
                html += '</div></div>';
            });
            html += '</div>';
            html += '<div class="mt-3 flex items-center gap-2">';
            const ids = data.hens.map(h => h.id).join(',');
            html += '<button type="button" onclick="openMoveModal(\'' + ids + '\', ' + data.hens.length + ', \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + (data.hens[0]?.breed || '') + '\')" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Move all (' + data.hens.length + ')"><i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></button>';
            html += '<button type="button" onclick="openRemoveModal(\'' + ids + '\', ' + data.hens.length + ', \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + (data.hens[0]?.breed || '') + '\')" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #9b1c24;" aria-label="Remove all (' + data.hens.length + ')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>';
            html += '</div>';
            content.innerHTML = html + notesHtml;
            lucide.createIcons();
        })
        .catch(() => {
            content.innerHTML = '<p class="text-xs text-center py-3" style="color: #9b1c24;">Failed to load hens.</p>';
        })
        .finally(repositionCageInfoPopup);
}

function closeSlotExpandPopup() {
    document.getElementById('slotExpandPanel-popup').classList.add('hidden');
}

// ── Flip Card ─────────────────────────────────────────────
function flipCage(cageId) {
    var card = document.querySelector('.cage-card[data-cage-code]')?.closest('.cage-card');
    // Find the card by looking for the flipper
    var flipper = document.getElementById('flipper-' + cageId);
    if (!flipper) return;
    var card = flipper.closest('.cage-card');
    if (!card) return;
    card.classList.toggle('is-flipped');
}

// ── Add Modal ────────────────────────────────────────────
function openAddModal() {
    document.getElementById('addCageModal').style.display = 'flex';
    updateAddPreview();
}

function closeAddModal() {
    document.getElementById('addCageModal').style.display = 'none';
}

// ── No Chickens Modal ───────────────────────────────
function openNoChickensModal() {
    document.getElementById('noChickensModal').style.display = 'flex';
    lucide.createIcons();
}

function closeNoChickensModal() {
    document.getElementById('noChickensModal').style.display = 'none';
}

function renderSlotPreview(prefix) {
    const rows = parseInt(document.getElementById(prefix + 'Rows').value) || 1;
    const slotsPerRow = parseInt(document.getElementById(prefix + 'SlotsPerRow').value) || 1;
    const maxPerSlot = parseInt(document.getElementById(prefix + 'MaxPerSlot').value) || 1;
    const totalSlots = rows * slotsPerRow;
    const totalCapacity = totalSlots * maxPerSlot;
    document.getElementById(prefix + 'SummarySlots').textContent = totalSlots;
    document.getElementById(prefix + 'SummaryCapacity').textContent = totalCapacity + ' hens';

    const container = document.getElementById(prefix + 'PreviewContainer');
    const grid = document.getElementById(prefix + 'PreviewGrid');
    const colHeaders = document.getElementById(prefix + 'PreviewColHeaders');
    const gap = 3;

    const containerPad = 24;
    const availW = container.clientWidth - containerPad;
    const rowLabelW = 20;
    const totalGaps = (slotsPerRow - 1) * gap;
    let cellSize = Math.floor((availW - rowLabelW - totalGaps) / slotsPerRow);
    cellSize = Math.max(18, Math.min(cellSize, 36));

    const showCellNumbers = cellSize >= 24;
    const needsScroll = cellSize <= 18;

    if (needsScroll) {
        container.style.overflowX = 'auto';
        container.style.overflowY = 'hidden';
        grid.style.minWidth = '';
        colHeaders.style.minWidth = '';
        cellSize = 18;
    } else {
        container.style.overflowX = 'hidden';
        container.style.overflowY = 'hidden';
        grid.style.minWidth = 'max-content';
        colHeaders.style.minWidth = 'max-content';
    }

    const axisFontSize = cellSize < 20 ? '8px' : (cellSize < 26 ? '9px' : '11px');

    colHeaders.innerHTML = '';
    colHeaders.style.display = 'flex';
    colHeaders.style.gap = gap + 'px';
    colHeaders.style.marginBottom = gap + 'px';
    const spacer = document.createElement('div');
    spacer.style.width = rowLabelW + 'px';
    spacer.style.flexShrink = '0';
    spacer.style.fontSize = axisFontSize;
    spacer.style.color = '#a39e98';
    spacer.style.display = 'flex';
    spacer.style.alignItems = 'center';
    spacer.style.justifyContent = 'center';
    colHeaders.appendChild(spacer);
    for (let c = 1; c <= slotsPerRow; c++) {
        const d = document.createElement('div');
        d.style.width = cellSize + 'px';
        d.style.height = '14px';
        d.style.flexShrink = '0';
        d.style.textAlign = 'center';
        d.style.fontSize = axisFontSize;
        d.style.lineHeight = '14px';
        d.style.color = '#a39e98';
        d.textContent = String(c);
        colHeaders.appendChild(d);
    }

    let html = '';
    for (let r = 1; r <= rows; r++) {
        html += '<div style="display: flex; gap: ' + gap + 'px; margin-bottom: ' + gap + 'px;">';
        html += '<div style="width: ' + rowLabelW + 'px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: ' + axisFontSize + '; color: #a39e98; font-weight: 500;">' + r + '</div>';
        for (let c = 1; c <= slotsPerRow; c++) {
            const num = (r - 1) * slotsPerRow + c;
            html += '<div style="width: ' + cellSize + 'px; height: ' + cellSize + 'px; flex-shrink: 0; border-radius: ' + (cellSize > 24 ? '4px' : '2px') + '; border: 1px solid #e6e6e6; display: flex; align-items: center; justify-content: center; background-color: #f6f5f4; transition: none;"';
            if (!showCellNumbers) {
                html += ' title="Slot ' + num + '"';
            }
            html += '>';
            if (showCellNumbers) {
                html += '<span style="font-size: ' + (cellSize < 22 ? '9px' : '11px') + '; font-family: monospace; color: #a39e98;">' + num + '</span>';
            }
            html += '</div>';
        }
        html += '</div>';
    }
    grid.innerHTML = html;
}

function updateAddPreview() { renderSlotPreview('add'); }
function updateEditPreview() { renderSlotPreview('edit'); checkEditResizeCollision(); }

// ── Resize collision detection (proactive UX, not save-time validation) ──
var _editCollisionCageId = null;
var _editCollisionConflicts = [];

function checkEditResizeCollision() {
    if (!_editCollisionCageId) return;

    var newRows = parseInt(document.getElementById('editRows').value) || 1;
    var newSlots = parseInt(document.getElementById('editSlotsPerRow').value) || 1;
    var cageId = _editCollisionCageId;

    var pos = pendingMoves[cageId] || savedPositions[cageId] || {};
    var originRow = pos.location_row;
    var originCol = pos.location_column;

    var warningEl = document.getElementById('editResizeCollisionWarning');
    var warningText = document.getElementById('editResizeCollisionText');

    clearResizeCollisionHighlight();

    if (originRow === null || originCol === null) {
        warningEl.classList.add('hidden');
        return;
    }

    var candidate = { col: parseInt(originCol), row: parseInt(originRow), w: newSlots, h: newRows };

    var outOfBounds = (candidate.col + candidate.w > GRID_COLS) || (candidate.row + candidate.h > GRID_ROWS);
    var conflicts = [];

    for (var id in placedCages) {
        if (parseInt(id) === cageId) continue;
        var p = placedCages[id];
        var other = { col: p.origin_col, row: p.origin_row, w: p.width, h: p.height };
        if (rectsOverlap(candidate, other)) {
            conflicts.push({ id: parseInt(id), code: p.code });
        }
    }

    _editCollisionConflicts = conflicts;

    if (conflicts.length > 0 || outOfBounds) {
        highlightConflictingCages(conflicts);
        var code = cageMeta[cageId] ? cageMeta[cageId].code : 'This cage';
        var msg = 'Resizing ' + code + ' to ' + newSlots + '\u00d7' + newRows + ' will ';
        if (outOfBounds && conflicts.length > 0) {
            msg += 'go out of bounds and overlap ' + conflicts.map(function(c) { return c.code; }).join(', ');
        } else if (outOfBounds) {
            msg += 'extend beyond the canvas bounds';
        } else {
            msg += 'overlap ' + conflicts.map(function(c) { return c.code; }).join(', ');
        }
        warningText.textContent = msg;
        warningEl.classList.remove('hidden');
    } else {
        warningEl.classList.add('hidden');
    }
}

function highlightConflictingCages(conflicts) {
    conflicts.forEach(function(c) {
        var el = document.querySelector('.cage-overlay[data-cage-id="' + c.id + '"]');
        if (el) {
            el.style.borderColor = '#dc2626';
            el.style.borderWidth = '3px';
            el.style.boxShadow = '0 0 0 3px rgba(220,38,38,0.25)';
        }
    });
}

function clearResizeCollisionHighlight() {
    _editCollisionConflicts.forEach(function(c) {
        var el = document.querySelector('.cage-overlay[data-cage-id="' + c.id + '"]');
        if (el && parseInt(el.dataset.cageId) !== selectedCageId) {
            var p = placedCages[c.id];
            if (p) {
                el.style.borderColor = p.color;
                el.style.borderWidth = '2px';
                el.style.boxShadow = 'none';
            }
        }
    });
    _editCollisionConflicts = [];
}

// ── Edit Modal ───────────────────────────────────────────
function openEditModal(id, cageCode, locationRow, locationCol, rows, slotsPerRow, maxPerSlot, isActive) {
    document.getElementById('editCageForm').action = cagesBase + '/' + id;
    document.getElementById('editCageCode').textContent = cageCode;
    document.getElementById('editRows').value = rows;
    document.getElementById('editSlotsPerRow').value = slotsPerRow;
    document.getElementById('editMaxPerSlot').value = maxPerSlot;
    document.getElementById('editActive').checked = isActive === 1;
    document.getElementById('editResizeError').classList.add('hidden');
    document.getElementById('editResizeCollisionWarning').classList.add('hidden');
    _editCollisionCageId = id;
    clearResizeCollisionHighlight();

    // Canvas position display — positions saved this session (without a page
    // refresh) override the values baked into the server-rendered edit button.
    if (Object.prototype.hasOwnProperty.call(savedPositions, id)) {
        locationRow = savedPositions[id].location_row;
        locationCol = savedPositions[id].location_column;
    }
    var posEl = document.getElementById('editCanvasPosition');
    if (locationRow !== null && locationCol !== null) {
        posEl.textContent = 'Row ' + (parseInt(locationRow) + 1) + ', Column ' + (parseInt(locationCol) + 1);
        posEl.style.color = '';
    } else {
        posEl.textContent = 'Unplaced — drag to grid';
        posEl.style.color = '#a39e98';
    }

    const sensorContainer = document.getElementById('editSlotSensors');
    sensorContainer.innerHTML = '<p class="text-xs text-center py-3" style="color: #a39e98;">Loading slots...</p>';
    document.getElementById('editDhtList').innerHTML = '<p class="text-xs text-center py-3" style="color: #a39e98;">Loading…</p>';

    // Inventory state for this edit session (item 21): spares minus pending additions
    sensorInv = { spareIR: 0, spareDHT: 0, irBaseline: {}, dhtBaseline: 0 };

    Promise.all([
        fetch(cagesBase + '/' + id + '/slots-json').then(r => r.json()),
        fetch(cagesBase + '/' + id + '/sensor-info').then(r => r.json()),
    ])
        .then(function(results) {
            var slots = results[0];
            var info = results[1];
            sensorInv.spareIR = info.spare.IR_breakbeam;
            sensorInv.spareDHT = info.spare.DHT22;

            // ── IR break beam per slot ──
            if (slots.length === 0) {
                sensorContainer.innerHTML = '<p class="text-xs text-center py-3" style="color: #a39e98;">No slots in this cage.</p>';
            } else {
                var html = '';
                slots.forEach(function(slot) {
                    sensorInv.irBaseline[slot.id] = !!slot.has_sensor;
                    var checked = slot.has_sensor ? 'checked' : '';
                    var serial = slot.sensor_device_id || '';
                    html += '<div class="flex items-center gap-3 rounded-lg px-3 py-2" style="background-color: #f6f5f4;">';
                    html += '<span class="text-xs font-medium w-16" style="color: #31302e;">Slot ' + slot.row_number + '-' + slot.column_number + '</span>';
                    html += '<label class="flex items-center gap-1.5 cursor-pointer">';
                    // Hidden 0 ensures unchecked state is actually posted (item 15 fix)
                    html += '<input type="hidden" name="slots[' + slot.id + '][has_sensor]" value="0">';
                    html += '<input type="checkbox" class="ir-sensor-box w-4 h-4 rounded" name="slots[' + slot.id + '][has_sensor]" value="1" ' + checked + ' data-slot-id="' + slot.id + '" onchange="updateIrAvailability()" style="accent-color: #0075de;">';
                    html += '<span class="text-xs" style="color: #615d59;">Sensor</span>';
                    html += '</label>';
                    // Device ID is auto-generated + read-only (item 24)
                    html += '<span class="flex-1 text-xs font-mono text-right" id="irSerial-' + slot.id + '" style="color: ' + (serial ? '#31302e' : '#a39e98') + ';">' + (serial || (slot.has_sensor ? 'assigned' : '—')) + '</span>';
                    html += '</div>';
                });
                sensorContainer.innerHTML = html;
            }
            updateIrAvailability();

            // ── DHT22 cage level ──
            sensorInv.dhtBaseline = info.dht22.length;
            dhtCurrent = info.dht22.map(function(d) { return d.serial_number; });
            renderDhtList();
        })
        .catch(function() {
            sensorContainer.innerHTML = '<p class="text-xs text-center py-3" style="color: #9b1c24;">Failed to load sensor data.</p>';
            document.getElementById('editDhtList').innerHTML = '';
        });

    document.getElementById('editCageModal').style.display = 'flex';
    updateEditPreview();
}

// ── Sensor inventory tracking (items 21, 23, 24) ─────────
var sensorInv = { spareIR: 0, spareDHT: 0, irBaseline: {}, dhtBaseline: 0 };
var dhtCurrent = [];

function updateIrAvailability() {
    var boxes = document.querySelectorAll('.ir-sensor-box');
    var newlyChecked = 0;
    boxes.forEach(function(b) {
        if (b.checked && !sensorInv.irBaseline[b.dataset.slotId]) newlyChecked++;
    });
    var remaining = sensorInv.spareIR - newlyChecked;
    var label = document.getElementById('irAvailability');
    label.textContent = 'Available in inventory: ' + remaining;
    label.style.color = remaining <= 0 ? '#9b1c24' : '#a39e98';
    // Block assigning beyond stock: disable the unchecked, not-baseline boxes
    boxes.forEach(function(b) {
        if (!b.checked && !sensorInv.irBaseline[b.dataset.slotId]) {
            b.disabled = remaining <= 0;
            b.title = remaining <= 0 ? 'No IR break-beam sensors left in inventory' : '';
        }
    });
}

function renderDhtList() {
    var list = document.getElementById('editDhtList');
    document.getElementById('dht22CountInput').value = dhtCurrent.length;
    if (dhtCurrent.length === 0) {
        list.innerHTML = '<p class="text-xs py-1" style="color: #a39e98;">No DHT22 assigned to this cage.</p>';
    } else {
        var html = '';
        dhtCurrent.forEach(function(serial, i) {
            html += '<div class="flex items-center justify-between rounded-lg px-3 py-2" style="background-color: #f6f5f4;">';
            html += '<span class="text-xs font-mono" style="color: #31302e;">' + (serial || 'assigned on save') + '</span>';
            html += '<button type="button" class="text-xs font-medium" style="color: #9b1c24;" onclick="removeDht22(' + i + ')">Remove</button>';
            html += '</div>';
        });
        list.innerHTML = html;
    }
    var added = Math.max(0, dhtCurrent.length - sensorInv.dhtBaseline);
    var remaining = sensorInv.spareDHT - added;
    var label = document.getElementById('dhtAvailability');
    label.textContent = 'Available in inventory: ' + remaining;
    label.style.color = remaining <= 0 ? '#9b1c24' : '#a39e98';
    var btn = document.getElementById('addDhtBtn');
    btn.disabled = remaining <= 0;
    var msg = document.getElementById('dhtLimitMsg');
    if (remaining <= 0) {
        msg.textContent = 'No DHT22 sensors left in inventory — add stock in Hardware first.';
        msg.classList.remove('hidden');
    } else {
        msg.classList.add('hidden');
    }
}

function addDht22() {
    dhtCurrent.push(null); // serial assigned server-side on save (item 24)
    renderDhtList();
}

function removeDht22(index) {
    dhtCurrent.splice(index, 1);
    renderDhtList();
}

function closeEditModal() {
    document.getElementById('editCageModal').style.display = 'none';
    _editCollisionCageId = null;
    clearResizeCollisionHighlight();
}

// ── Keyboard: Escape closes modals (bind once) ───────────
// Guarded on window, not a local var, because Turbo re-parses this inline
// script on every visit to this page — a local flag would reset each time
// and a new listener would stack on top of the previous ones.
(function() {
    if (window.__cagesEscapeBound) return;
    window.__cagesEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
            closeMoveModal();
            closeRemoveModal();
            if (typeof closeDeleteModal === 'function') closeDeleteModal();
            if (typeof closeGridSettings === 'function') closeGridSettings();
        }
    });
})();

// ── Tile-based Farm Layout Canvas ──────────────────────────────────
var IS_ADMIN = {{ $isAdmin ? 'true' : 'false' }};
var STORED_GRID_ROWS = {{ (int) \App\Models\Setting::get('farm_grid_rows', 6) }};
var STORED_GRID_COLS = {{ (int) \App\Models\Setting::get('farm_grid_cols', 10) }};
var GRID_ROWS = {{ $gridRows }};
var GRID_COLS = {{ $gridCols }};
var TILE_SIZE = 42;
var TILE_GAP = 2;
var GRID_PAD = 10;

var draggedCageId = null;
var draggedFromCanvas = false;
var grabOffsetCol = 0;
var grabOffsetRow = 0;
var selectedCageId = null;
var activeFilterId = null;
var pendingMoves = {};
var savedPositions = {};
var cageMeta = {!! $cages->mapWithKeys(fn($c) => [$c->id => [
    'id' => $c->id,
    'code' => $c->cage_code,
    'color' => $c->color,
    'colorSoft' => $c->colorSoft,
    'breed' => \Illuminate\Support\Str::limit($c->hens->first()?->breed ?? '—', 16),
    'primary_age_weeks' => $c->hens->first()?->current_age_weeks ?? null,
    'rows' => (int) ($c->rows ?? 1),
    'slots_per_row' => (int) ($c->slots_per_row ?? 1),
    'max_chickens_per_slot' => (int) ($c->max_chickens_per_slot ?? 4),
    'location_row' => $c->location_row,
    'location_col' => $c->location_column,
    'is_active' => (bool) $c->is_active,
    'total_capacity' => (int) ($c->total_capacity ?? 0),
    'current_occupancy' => (int) $c->cageSlots->sum('current_occupancy'),
    'slots' => $c->cageSlots->map(fn($s) => [
        'id' => $s->id,
        'row' => (int) $s->row_number,
        'col' => (int) $s->column_number,
        'number' => (int) $s->slot_number,
        'occupancy' => (int) $s->current_occupancy,
        'has_sensor' => $s->hasBreakbeam(),
    ])->values(),
    'bulk_add_url' => route('cages.bulk-add') . '?cage_id=' . $c->id,
    'print_label_url' => route('cages.print-label', $c),
]])->toJson(JSON_UNESCAPED_UNICODE) !!};

// ── Initialize placed cages state from server data ──
var placedCages = {};
var initialPositions = {};
Object.keys(cageMeta).forEach(function(id) {
    var m = cageMeta[id];
    if (m.location_row !== null && m.location_col !== null) {
        placedCages[id] = {
            origin_row: parseInt(m.location_row),
            origin_col: parseInt(m.location_col),
            width: m.slots_per_row,
            height: m.rows,
            color: m.color,
            colorSoft: m.colorSoft,
            code: m.code,
        };
        initialPositions[id] = { location_row: m.location_row, location_col: m.location_col };
    }
});

// ── Tile coordinate helpers ──
function tileLeft(col) { return GRID_PAD + col * (TILE_SIZE + TILE_GAP); }
function tileTop(row) { return GRID_PAD + row * (TILE_SIZE + TILE_GAP); }
function canvasW() { return GRID_PAD * 2 + GRID_COLS * (TILE_SIZE + TILE_GAP) - TILE_GAP; }
function canvasH() { return GRID_PAD * 2 + GRID_ROWS * (TILE_SIZE + TILE_GAP) - TILE_GAP; }

function getCanvasScale() {
    var scaler = document.getElementById('canvasScaler');
    if (!scaler) return 1;
    var m = scaler.style.transform.match(/scale\(([^)]+)\)/);
    return m ? parseFloat(m[1]) : 1;
}

// ── Overlap detection ──
function rectsOverlap(a, b) {
    return !(a.col + a.w <= b.col || b.col + b.w <= a.col ||
             a.row + a.h <= b.row || b.row + b.h <= a.row);
}

function checkPlacement(cageId, originRow, originCol) {
    var m = cageMeta[cageId];
    if (!m) return false;
    var w = m.slots_per_row;
    var h = m.rows;
    if (originCol + w > GRID_COLS || originRow + h > GRID_ROWS || originCol < 0 || originRow < 0) return false;
    var candidate = { col: originCol, row: originRow, w: w, h: h };
    for (var id in placedCages) {
        if (parseInt(id) === cageId) continue;
        var p = placedCages[id];
        var other = { col: p.origin_col, row: p.origin_row, w: p.width, h: p.height };
        if (rectsOverlap(candidate, other)) return false;
    }
    return true;
}

function getTilesOccupied(originRow, originCol, width, height) {
    var tiles = [];
    for (var r = originRow; r < originRow + height; r++) {
        for (var c = originCol; c < originCol + width; c++) {
            tiles.push({ row: r, col: c });
        }
    }
    return tiles;
}

// ── Canvas Rendering ──
function renderCanvas() {
    renderTileGrid();
    renderCageOverlays();
    updateCanvasSize();
    updateStagingVisibility();
    updateSaveButton();
}

function renderTileGrid() {
    var layer = document.getElementById('tileGridLayer');
    var html = '';
    for (var r = 0; r < GRID_ROWS; r++) {
        for (var c = 0; c < GRID_COLS; c++) {
            var left = tileLeft(c);
            var top = tileTop(r);
            html += '<div class="tile-bg absolute rounded-sm" style="left:' + left + 'px;top:' + top + 'px;width:' + TILE_SIZE + 'px;height:' + TILE_SIZE + 'px;background:#f9fafb;border:1px solid #e6e6e6;"></div>';
        }
    }
    layer.innerHTML = html;
}

function renderCageOverlays() {
    var layer = document.getElementById('cageOverlayLayer');
    var html = '';
    for (var id in placedCages) {
        var c = placedCages[id];
        html += cageOverlayHtml(parseInt(id), c);
    }
    layer.innerHTML = html;
    // Re-bind click events for selection
    layer.querySelectorAll('.cage-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            selectCage(parseInt(el.dataset.cageId));
        });
    });
    // Bind remove buttons
    layer.querySelectorAll('.cage-remove-btn').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            confirmRemoveCage(parseInt(el.dataset.cageId));
        });
    });
    // Bind resize buttons (opens Edit Cage focused on size fields)
    layer.querySelectorAll('.cage-resize-btn').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            var id = parseInt(el.dataset.cageId);
            var m = cageMeta[id];
            var p = placedCages[id];
            if (!m || !p) return;
            openEditModal(id, m.code, p.origin_row, p.origin_col, m.rows, m.slots_per_row, m.max_chickens_per_slot || 4, 1);
            // Scroll to rows field after modal opens
            setTimeout(function() {
                var rowsInput = document.getElementById('editRows');
                if (rowsInput) { rowsInput.focus(); rowsInput.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            }, 300);
        });
    });
    // Bind info popup buttons (toggle: click the same cage again to close)
    layer.querySelectorAll('.cage-info-btn').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            var id = parseInt(el.dataset.cageId);
            var popup = document.getElementById('cageInfoPopup');
            if (popup && !popup.classList.contains('hidden') && popup._cageInfoCageId === id) {
                popup.classList.add('hidden');
            } else {
                openCageInfoPopup(id, el);
            }
        });
    });

    // Bind drag events
    layer.querySelectorAll('.cage-drag-handle').forEach(function(el) {
        el.addEventListener('dragstart', function(e) { handleDragStart(e, parseInt(el.dataset.cageId)); });
    });
    // Re-render lucide icons (drag/drop and select re-create the overlay markup,
    // which leaves info/action icons as empty <i> until createIcons runs)
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons({ root: layer }); } catch (e) { try { window.lucide.createIcons(); } catch (e2) {} }
    }
    updateCanvasSize();
}

// ── Cage Info Popup (mirrors the cage detail card: front slot grid + back details) ──
function renderCageInfoPopupSlotGrid(m) {
    var cols = Math.min(m.slots_per_row, 6);
    var html = '<div class="grid gap-1" style="grid-template-columns:repeat(' + cols + ', 32px);justify-content:flex-start;">';
    for (var i = 0; i < m.slots.length; i++) {
        var s = m.slots[i];
        var isSensor = s.has_sensor;
        var occupancy = s.occupancy;
        var maxPerSlot = m.max_chickens_per_slot || 4;
        var fillRatio = maxPerSlot > 0 ? Math.min(1, occupancy / maxPerSlot) : 0;
        var slotBg, slotBorder, slotContent;
        if (isSensor) {
            slotBg = '#d6f0e3';
            slotBorder = '#2a9d6a';
            slotContent = occupancy > 0 ? '<span class="text-xs font-semibold" style="color:#1f6b3a;">' + occupancy + '</span>' : '<span class="text-xs" style="color:#d1d5db;">—</span>';
        } else if (occupancy > 0) {
            var gray = Math.round(248 - (fillRatio * 40));
            slotBg = 'rgb(' + gray + ',' + gray + ',' + gray + ')';
            slotBorder = gray > 235 ? '#e6e6e6' : '#d1d5db';
            slotContent = '<span class="text-xs font-semibold" style="color:#1f1f1f;">' + occupancy + '</span>';
        } else {
            slotBg = '#ffffff';
            slotBorder = '#e6e6e6';
            slotContent = '<span class="text-xs" style="color:#d1d5db;">—</span>';
        }
        var sensorDot = isSensor ? '<span class="absolute top-0 right-0 w-1.5 h-1.5 rounded-bl" style="background-color:#0075de;"></span>' : '';
        html += '<button type="button" onclick="expandSlotInPopup(this)" class="slot-mini w-8 h-8 rounded flex flex-col items-center justify-center text-xs relative cursor-pointer transition-colors" data-cage-id="' + m.id + '" data-slot-id="' + s.id + '" data-cage-code="' + m.code.replace(/"/g, '&quot;') + '" style="background-color:' + slotBg + ';border:1px solid ' + slotBorder + ';" title="Slot ' + s.row + '-' + s.col + ': ' + occupancy + ' hens' + (isSensor ? ' (sensor equipped)' : '') + '" aria-label="Slot ' + s.row + '-' + s.col + ', ' + occupancy + ' hens">'
            + sensorDot + slotContent + '</button>';
    }
    html += '</div>';
    return html;
}

function renderCageInfoPopupContent(m) {
    var occupancyPct = m.total_capacity > 0 ? Math.round((m.current_occupancy / m.total_capacity) * 100) : 0;
    var accentColor;
    if (!m.is_active) {
        accentColor = '#a39e98';
    } else if (occupancyPct > 100) {
        accentColor = '#9b1c24';
    } else if (occupancyPct >= 90) {
        accentColor = '#c2703e';
    } else {
        accentColor = m.color;
    }
    var occupancyColor = occupancyPct >= 90 ? '#9b1c24' : (occupancyPct >= 75 ? '#c2703e' : '#1f1f1f');
    var sensorCount = m.slots.filter(function(s) { return s.has_sensor; }).length;

    var accentBar = '<div style="width:4px;align-self:stretch;background-color:' + accentColor + ';border-radius:3px;flex-shrink:0;"></div>';

    // ── FRONT FACE ──
    var frontHeader = '<div class="flex items-center gap-3 px-3 pt-3 pb-2 border-b" style="background-color:#f8f8f8;border-bottom-color:#e6e6e6;border-top-left-radius:11px;border-top-right-radius:11px;">'
        + accentBar
        + '<div class="flex items-center gap-2 min-w-0 flex-1">'
        + '<span class="text-sm font-bold shrink-0" style="color:' + m.color + '">' + m.code + '</span>'
        + '<span class="text-[10px] px-2 py-0.5 rounded-full shrink-0 font-semibold" style="background-color:' + (m.is_active ? '#e8f5ec' : '#f0f0f0') + ';color:' + (m.is_active ? '#1f6b3a' : '#615d59') + ';">' + (m.is_active ? 'Active' : 'Inactive') + '</span>'
        + '</div>'
        + '<div class="flex items-center gap-1 shrink-0">'
        + '<span class="text-sm font-semibold" style="color:' + occupancyColor + ';">' + m.current_occupancy + '/' + (m.total_capacity || '?') + '</span>'
        + '<a href="' + m.bulk_add_url + '" class="icon-btn" style="color:#0075de;" aria-label="Bulk add hens" title="Add hens"><i data-lucide="plus-circle" class="w-3.5 h-3.5"></i></a>'
        + '<button onclick="flipCageInfoPopup()" class="icon-btn" style="color:#615d59;" aria-label="Show details" title="Details & settings"><i data-lucide="info" class="w-3.5 h-3.5"></i></button>'
        + '</div>'
        + '</div>';

    var frontBody = '<div class="px-4 py-3">'
        + renderCageInfoPopupSlotGrid(m)
        + '<div id="slotExpandPanel-popup" class="hidden mt-3 border-t" style="border-color:#e6e6e6; background-color:#f6f5f4;">'
        + '<div class="p-3">'
        + '<div class="flex items-center justify-between mb-2">'
        + '<span id="slotPanelTitle-popup" class="text-sm font-semibold" style="color:#1f1f1f;">Slot details</span>'
        + '<button onclick="closeSlotExpandPopup()" class="p-1.5 rounded hover:bg-black/5 transition-colors" aria-label="Close"><i data-lucide="x" class="w-4 h-4" style="color:#615d59;"></i></button>'
        + '</div>'
        + '<div id="slotPanelContent-popup">'
        + '<div class="text-xs text-center py-2" style="color:#a39e98;">Tap a slot above to see its hens.</div>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</div>';

    var frontFooter = '<div class="flex items-center gap-3 px-4 py-2 border-t text-[10px] leading-none shrink-0" style="border-color:#e6e6e6;color:#a39e98;">'
        + '<span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded" style="background-color:#d6f0e3;border:1px solid #2a9d6a;"></span> Sensor</span>'
        + '<span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded" style="background-color:#f6f5f4;border:1px solid #e6e6e6;"></span> Occupied</span>'
        + '<span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded" style="background-color:#ffffff;border:1px solid #e6e6e6;"></span> Empty</span>'
        + '</div>';

    var frontFace = '<div class="front-face flex flex-col" style="z-index:2;">' + frontHeader + frontBody + frontFooter + '</div>';

    // ── BACK FACE ──
    var backHeader = '<div class="flex items-center gap-3 px-3 pt-3 pb-2 border-b" style="background-color:#f8f8f8;border-bottom-color:#e6e6e6;border-top-left-radius:11px;border-top-right-radius:11px;">'
        + accentBar
        + '<span class="text-sm font-bold flex-1" style="color:' + m.color + ';">' + m.code + '</span>'
        + '<button onclick="flipCageInfoPopup()" class="icon-btn" style="color:#615d59;" aria-label="Back" title="Back"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i></button>'
        + '</div>';

    var specs = '<div class="grid grid-cols-[60px_1fr] gap-x-2 gap-y-1.5 text-xs">'
        + '<span class="font-medium" style="color:#a39e98;">Dims</span><span style="color:#1f1f1f;">' + (m.rows || '?') + '×' + (m.slots_per_row || '?') + ' · ' + m.slots.length + ' slots</span>'
        + '<span class="font-medium" style="color:#a39e98;">Cap</span><span style="color:#1f1f1f;">' + m.current_occupancy + ' / ' + (m.total_capacity || '?') + ' hens</span>';
    if (m.breed && m.breed !== '—') {
        specs += '<span class="font-medium" style="color:#a39e98;">Breed</span><span style="color:#1f1f1f;">' + m.breed + (m.primary_age_weeks ? ' · ' + m.primary_age_weeks + 'w' : '') + '</span>';
    }
    if (sensorCount > 0) {
        specs += '<span class="font-medium" style="color:#a39e98;">Sensor</span><span style="color:#1f1f1f;">' + sensorCount + ' slot' + (sensorCount > 1 ? 's' : '') + '</span>';
    }
    specs += '</div>';

    var backBody = '<div class="flex-1 px-4 py-3 space-y-2.5 overflow-y-auto">' + specs + '</div>';

    var backFooter = '<div class="flex items-center justify-around px-4 py-2 border-t shrink-0" style="border-color:#e6e6e6;">';
    if (IS_ADMIN) {
        backFooter += '<button onclick="closeCageInfoPopup(); openEditModal(' + m.id + ', \'' + m.code + '\', ' + (m.location_row !== null ? m.location_row : 'null') + ', ' + (m.location_col !== null ? m.location_col : 'null') + ', ' + m.rows + ', ' + m.slots_per_row + ', ' + m.max_chickens_per_slot + ', ' + (m.is_active ? 1 : 0) + ')" class="icon-btn" style="color:#615d59;" aria-label="Edit cage" title="Edit cage"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>';
        backFooter += '<button onclick="closeCageInfoPopup(); toggleReorderMode(' + m.id + ')" class="icon-btn" style="color:#615d59;" aria-label="Renumber slots" title="Renumber slots"><i data-lucide="list-ordered" class="w-3.5 h-3.5"></i></button>';
    }
    backFooter += '<button onclick="closeCageInfoPopup(); window.open(\'' + m.print_label_url + '\', \'print-' + m.id + '\', \'width=900,height=700\')" class="icon-btn" style="color:#615d59;" aria-label="Print cage label" title="Print label"><i data-lucide="printer" class="w-3.5 h-3.5"></i></button>';
    if (IS_ADMIN) {
        backFooter += '<button onclick="closeCageInfoPopup(); openDeleteModal(' + m.id + ', \'' + m.code + '\')" class="icon-btn" style="color:#615d59;" aria-label="Delete cage" title="Delete cage"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>';
    }
    backFooter += '</div>';

    var backFace = '<div class="back-face flex flex-col" style="z-index:1;">' + backHeader + backBody + backFooter + '</div>';

    return '<div class="flipper" id="cageInfoFlipper">' + frontFace + backFace + '</div>';
}

function closeCageInfoPopup() {
    var popup = document.getElementById('cageInfoPopup');
    if (popup) {
        popup.classList.add('hidden');
        popup.style.transform = '';
        popup.style.zIndex = '';
    }
}

function flipCageInfoPopup() {
    var flipper = document.getElementById('cageInfoFlipper');
    if (flipper) flipper.classList.toggle('flipped');
    repositionCageInfoPopup();
    var popup = document.getElementById('cageInfoPopup');
    if (window.lucide && typeof window.lucide.createIcons === 'function' && popup) {
        try { window.lucide.createIcons({ root: popup }); } catch (e) {}
    }
}

function positionCageInfoPopup(popup, targetRect) {
    var margin = 8;
    var viewportW = window.innerWidth;
    var viewportH = window.innerHeight;

    if (viewportW < 768) {
        // Mobile/tablet: center it as a full-screen-style overlay above page content
        // (same approach as the Move modal), width capped to viewport.
        popup.style.width = 'calc(100vw - 2rem)';
        popup.style.maxHeight = 'calc(100vh - 2rem)';
        popup.style.left = '50%';
        popup.style.top = '50%';
        popup.style.transform = 'translate(-50%, -50%)';
        popup.style.zIndex = '90';
        return;
    }

    popup.style.transform = '';
    popup.style.zIndex = '';

    var popupRect = popup.getBoundingClientRect();
    var left = targetRect.right + margin;
    var top = targetRect.top;

    if (left + popupRect.width > viewportW - margin) {
        left = targetRect.left - popupRect.width - margin;
    }
    if (left < margin) {
        left = margin;
    }
    if (top + popupRect.height > viewportH - margin) {
        top = viewportH - popupRect.height - margin;
    }
    if (top < margin) {
        top = margin;
    }

    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
}

function repositionCageInfoPopup() {
    var popup = document.getElementById('cageInfoPopup');
    if (popup && popup._cageInfoBtnEl) {
        positionCageInfoPopup(popup, popup._cageInfoBtnEl.getBoundingClientRect());
    }
}

function openCageInfoPopup(cageId, btnEl) {
    var m = cageMeta[cageId];
    if (!m) return;
    var popup = document.getElementById('cageInfoPopup');
    var content = document.getElementById('cageInfoPopupContent');
    if (!popup || !content) return;

    content.innerHTML = renderCageInfoPopupContent(m);
    popup.classList.remove('hidden');
    popup._cageInfoBtnEl = btnEl;
    popup._cageInfoCageId = cageId;
    popup.style.width = '16rem';

    positionCageInfoPopup(popup, btnEl.getBoundingClientRect());

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }
}

// Close the cage info popup with Escape (keeps it open otherwise, so it does not
// vanish when switching tabs, opening the move/remove modals, or navigating)
if (!window.__cageInfoEscBound) {
    window.__cageInfoEscBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        var popup = document.getElementById('cageInfoPopup');
        if (popup) popup.classList.add('hidden');
    });
}

// Close the popup (and its open slot-details tab) when clicking outside it —
// but not while interacting with the filter tabs or an open modal/menu.
if (!window.__cageInfoOutsideBound) {
    window.__cageInfoOutsideBound = true;
    document.addEventListener('click', function(e) {
        var popup = document.getElementById('cageInfoPopup');
        if (!popup || popup.classList.contains('hidden')) return;
        // Use composedPath() — createIcons() swaps icon <i> nodes mid-click, which
        // detaches e.target and breaks popup.contains(). composedPath() is captured
        // at dispatch time, so it still reflects the click's original route.
        var inside = (typeof e.composedPath === 'function' && e.composedPath().includes(popup))
            || popup.contains(e.target);
        if (inside) return;
        if (e.target.closest('.cage-tab, .cage-tabs')) return;
        var dialog = e.target.closest('[role="dialog"]');
        if (dialog && window.getComputedStyle(dialog).display !== 'none') return;
        closeCageInfoPopup();
    });
}

// ── Live update after a hen move ────────────────────────────────
function recomputeCageMeta(id) {
    var m = cageMeta[id];
    if (!m) return;
    m.current_occupancy = m.slots.reduce(function(t, s) { return t + (s.occupancy || 0); }, 0);
}

function updateCageCardDom(cageId) {
    var m = cageMeta[cageId];
    if (!m) return;
    var card = document.querySelector('.cage-card[data-cage-code="' + m.code + '"]');
    if (!card) return;

    var occSpan = document.getElementById('occupancy-' + cageId);
    if (occSpan) {
        occSpan.textContent = m.current_occupancy + '/' + (m.total_capacity || '?');
        var pct = m.total_capacity > 0 ? Math.round((m.current_occupancy / m.total_capacity) * 100) : 0;
        occSpan.style.color = pct >= 90 ? '#9b1c24' : (pct >= 75 ? '#c2703e' : '#1f1f1f');
    }

    var grid = card.querySelector('.slot-grid-' + cageId);
    if (!grid) return;
    grid.querySelectorAll('.slot-mini').forEach(function(btn) {
        var sid = parseInt(btn.dataset.slotId);
        var s = m.slots.find(function(x) { return x.id === sid; });
        if (!s) return;
        var maxPerSlot = m.max_chickens_per_slot || 4;
        var fillRatio = maxPerSlot > 0 ? Math.min(1, s.occupancy / maxPerSlot) : 0;
        var bg, border;
        if (s.has_sensor) {
            bg = '#d6f0e3';
            border = '#2a9d6a';
        } else if (s.occupancy > 0) {
            var gray = Math.round(248 - (fillRatio * 40));
            bg = 'rgb(' + gray + ',' + gray + ',' + gray + ')';
            border = gray > 235 ? '#e6e6e6' : '#d1d5db';
        } else {
            bg = '#ffffff';
            border = '#e6e6e6';
        }
        btn.style.backgroundColor = bg;
        btn.style.borderColor = border;
        if (s.has_sensor) btn.style.color = '#1f6b3a';
        var occ = document.getElementById('slot-occ-' + sid);
        if (occ) {
            occ.textContent = s.occupancy > 0 ? String(s.occupancy) : '—';
            occ.className = s.occupancy > 0 ? 'text-xs font-semibold' : 'text-xs';
            occ.style.color = s.has_sensor ? '#1f6b3a' : (s.occupancy > 0 ? '#1f1f1f' : '#d1d5db');
        }
    });
}

function refreshPopupAfterMove() {
    var popup = document.getElementById('cageInfoPopup');
    if (!popup || popup.classList.contains('hidden')) return;
    var content = document.getElementById('cageInfoPopupContent');
    if (!content) return;
    var cageId = popup._cageInfoCageId;
    var openSlotId = popup._openSlotId;
    var btnEl = popup._cageInfoBtnEl;
    var m = cageMeta[cageId];
    if (!m) return;

    content.innerHTML = renderCageInfoPopupContent(m);
    if (btnEl && btnEl.isConnected) positionCageInfoPopup(popup, btnEl.getBoundingClientRect());
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        try { window.lucide.createIcons(); } catch (e) {}
    }
    if (openSlotId && openSlotId !== null) {
        var btn = content.querySelector('.slot-mini[data-slot-id="' + openSlotId + '"]');
        if (btn) expandSlotInPopup(btn);
    }
}

function refreshCagesAfterMove(d) {
    if (!d) return;
    var srcCageId = null;
    for (var id in cageMeta) {
        var m = cageMeta[id];
        if (m.code === d.srcCageCode && d.srcSlotNumber) {
            srcCageId = parseInt(id);
            m.slots.forEach(function(s) {
                if (s.number === d.srcSlotNumber) s.occupancy = Math.max(0, (s.occupancy || 0) - d.count);
            });
        }
        if (parseInt(id) === d.destCageId && d.destSlotId) {
            m.slots.forEach(function(s) {
                if (s.id === d.destSlotId) s.occupancy = (s.occupancy || 0) + d.count;
            });
        }
    }
    if (srcCageId) recomputeCageMeta(srcCageId);
    recomputeCageMeta(d.destCageId);

    var affected = [];
    if (srcCageId) affected.push(srcCageId);
    if (srcCageId !== d.destCageId && d.destCageId) affected.push(d.destCageId);
    affected.forEach(updateCageCardDom);

    refreshPopupAfterMove();
}

window.refreshCagesAfterMove = refreshCagesAfterMove;

function cageLabelHtml(code, w, h, color) {
    var isTiny = (w === 1 && h === 1);
    var isSmall = (w <= 2 || h <= 2);
    var shortCode = code.indexOf('CAGE-') === 0 ? code.slice(5) : code;

    if (isTiny) {
        return '<span class="font-bold leading-none text-center" style="font-size:18px;color:' + color + ';overflow:hidden;text-overflow:ellipsis;max-width:100%;display:inline-block;">' + shortCode + '</span>';
    }
    if (isSmall) {
        return '<span class="font-semibold leading-tight text-center" style="font-size:13px;color:' + color + ';overflow:hidden;text-overflow:ellipsis;word-break:break-all;max-width:100%;display:inline-block;">' + code + '</span>';
    }
    return '<span class="font-semibold leading-tight text-center" style="font-size:14px;color:' + color + ';">' + code + '</span>'
        + '<span class="text-xs leading-tight text-center" style="color:#615d59;">' + w + '×' + h + '</span>';
}

function cageOverlayHtml(cageId, c) {
    var left = tileLeft(c.origin_col);
    var top = tileTop(c.origin_row);
    var w = c.width * (TILE_SIZE + TILE_GAP) - TILE_GAP;
    var h = c.height * (TILE_SIZE + TILE_GAP) - TILE_GAP;
    var isSelected = (selectedCageId === cageId);
    var borderColor = isSelected ? '#002D5E' : c.color;
    var borderWidth = isSelected ? 3 : 2;
    var shadow = isSelected ? '0 0 0 2px rgba(0,45,94,0.25)' : 'none';
    var btns = '';
    // Info icon on the bottom-right, inside the cage card (transparent bg, cage color)
    btns += '<button class="cage-info-btn w-6 h-6 rounded-full flex items-center justify-center z-20" data-cage-id="' + cageId + '" style="position:absolute; bottom:2px; right:2px; background-color:transparent; color:' + c.color + '; line-height:1;" title="Cage info" aria-label="Cage info">'
        + '<i data-lucide="info" class="w-3 h-3"></i></button>';
    if (isSelected && IS_ADMIN) {
        // Remove (×) on the top-right while dragging/selected
        btns += '<button class="cage-remove-btn absolute -top-2 -right-2 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white z-10" data-cage-id="' + cageId + '" style="background-color:#9b1c24;line-height:1;" title="Remove from canvas">×</button>';
    }
    return '<div class="cage-overlay absolute rounded-lg flex items-center justify-center" data-cage-id="' + cageId + '" style="left:' + left + 'px;top:' + top + 'px;width:' + w + 'px;height:' + h + 'px;border:' + borderWidth + 'px solid ' + borderColor + ';background:' + c.colorSoft + ';box-shadow:' + shadow + ';cursor:pointer;z-index:1;" title="' + c.code + ' — ' + c.width + '×' + c.height + '">'
        + btns
        + '<div class="cage-drag-handle absolute inset-0 rounded-lg flex flex-col items-center justify-center gap-0.5" draggable="' + IS_ADMIN + '" data-cage-id="' + cageId + '" style="cursor:grab;">'
        + cageLabelHtml(c.code, c.width, c.height, c.color)
        + '</div>'
        + '</div>';
}

function updateCanvasSize() {
    var content = document.getElementById('canvasContent');
    if (content) {
        content.style.width = canvasW() + 'px';
        content.style.height = canvasH() + 'px';
    }
    fitCanvasToWidth();
}

function fitCanvasToWidth() {
    var scaler = document.getElementById('canvasScaler');
    var canvas = document.getElementById('farmCanvas');
    if (!scaler || !canvas) return;
    var contentW = canvasW();
    var containerW = canvas.clientWidth - 4;
    if (containerW < 1) return;
    var scale = Math.min(1, containerW / contentW);
    // On narrow viewports, keep tiles readable and scroll horizontally instead of
    // shrinking everything to an unusable size.
    var minScale = window.innerWidth < 640 ? 0.65 : (window.innerWidth < 1024 ? 0.85 : 1);
    scale = Math.max(minScale, scale);
    scaler.style.transform = 'scale(' + scale + ')';
    scaler.style.width = (contentW * scale) + 'px';
    scaler.style.height = (canvasH() * scale) + 'px';
}

// Fit canvas on window resize
var _fitCanvasTimer = null;
window.addEventListener('resize', function() {
    if (_fitCanvasTimer) clearTimeout(_fitCanvasTimer);
    _fitCanvasTimer = setTimeout(fitCanvasToWidth, 150);
});

// ── Grid extent recomputation (after add/remove) ──
function recomputeGridExtent() {
    var maxR = GRID_ROWS;
    var maxC = GRID_COLS;
    for (var id in placedCages) {
        var c = placedCages[id];
        var r = c.origin_row + c.height;
        var cl = c.origin_col + c.width;
        if (r > maxR) maxR = r;
        if (cl > maxC) maxC = cl;
    }
    GRID_ROWS = maxR;
    GRID_COLS = maxC;
}

// ── Selection ──
function selectCage(cageId) {
    if (selectedCageId === cageId) { deselectAll(); return; }
    selectedCageId = cageId;
    renderCageOverlays();
}

function deselectAll() {
    if (selectedCageId === null) return;
    selectedCageId = null;
    renderCageOverlays();
}

// ── Canvas click to deselect ──
document.addEventListener('click', function(e) {
    if (selectedCageId !== null && !e.target.closest('.cage-overlay') && !e.target.closest('.staging-tile')) {
        deselectAll();
    }
});

// ── Drag and Drop ──
function handleDragStart(e, cageId) {
    if (!IS_ADMIN) return;
    draggedCageId = cageId;
    // Transparent drag image (hide browser default)
    var hiddenCanvas = document.createElement('canvas');
    hiddenCanvas.width = 1; hiddenCanvas.height = 1;
    e.dataTransfer.setDragImage(hiddenCanvas, 0, 0);
    e.dataTransfer.setData('text/plain', String(cageId));
    e.dataTransfer.effectAllowed = 'move';

    draggedFromCanvas = e.target.closest('.cage-overlay') !== null || e.target.closest('.cage-drag-handle') !== null;

    // Calculate grab offset: where within the cage footprint the user grabbed
    var m = cageMeta[cageId];
    var cellW = TILE_SIZE + TILE_GAP;
    var cellH = TILE_SIZE + TILE_GAP;

    if (draggedFromCanvas) {
        var overlay = document.querySelector('.cage-overlay[data-cage-id="' + cageId + '"]');
        if (overlay) overlay.style.opacity = '0.35';
        // Grab offset relative to cage overlay in pixels
        var overlayRect = overlay.getBoundingClientRect();
        var grabPxX = e.clientX - overlayRect.left;
        var grabPxY = e.clientY - overlayRect.top;
        grabOffsetCol = grabPxX / cellW;
        grabOffsetRow = grabPxY / cellH;
    } else {
        // Dragging from staging tile — grab from center of tile
        var tile = e.target.closest('.staging-tile');
        if (tile) {
            var tileRect = tile.getBoundingClientRect();
            var grabPxX = e.clientX - tileRect.left;
            var grabPxY = e.clientY - tileRect.top;
            grabOffsetCol = grabPxX / cellW;
            grabOffsetRow = grabPxY / cellH;
        } else {
            grabOffsetCol = (m.slots_per_row * cellW) / 2 / cellW;
            grabOffsetRow = (m.rows * cellH) / 2 / cellH;
        }
    }
    // Clamp grab offset so it stays within the cage footprint
    grabOffsetCol = Math.max(0, Math.min(grabOffsetCol, m.slots_per_row));
    grabOffsetRow = Math.max(0, Math.min(grabOffsetRow, m.rows));

    bindDragListeners();
}

var dragListenersBound = false;
function bindDragListeners() {
    if (dragListenersBound) return;
    dragListenersBound = true;
    var canvas = document.getElementById('farmCanvas');
    document.addEventListener('dragover', function(e) { e.preventDefault(); showGhost(e); });
    document.addEventListener('dragend', function(e) { hideGhost(); unbindDragListeners(); resetDragState(); });
    document.addEventListener('drop', function(e) { e.preventDefault(); hideGhost(); handleCanvasDrop(e); });
}

function unbindDragListeners() {
    dragListenersBound = false;
}

function showGhost(e) {
    var ghost = document.getElementById('dragGhost');
    if (!ghost) return;
    var cageId = draggedCageId;
    if (!cageId || !cageMeta[cageId]) { ghost.classList.add('hidden'); return; }
    var m = cageMeta[cageId];
    var w = m.slots_per_row;
    var h = m.rows;
    var cellW = TILE_SIZE + TILE_GAP;
    var cellH = TILE_SIZE + TILE_GAP;

    // Calculate snapped position relative to canvas content origin
    var canvas = document.getElementById('farmCanvas');
    var rect = canvas.getBoundingClientRect();
    var scale = getCanvasScale();
    var mx = (e.clientX - rect.left + canvas.scrollLeft) / scale - GRID_PAD;
    var my = (e.clientY - rect.top + canvas.scrollTop) / scale - GRID_PAD;

    // Floor-based snap from grab-offset-adjusted cursor position
    var snapCol = Math.floor((mx - grabOffsetCol * cellW) / cellW);
    var snapRow = Math.floor((my - grabOffsetRow * cellH) / cellH);
    // Clamp so the entire footprint stays within grid bounds
    snapCol = Math.max(0, Math.min(snapCol, GRID_COLS - w));
    snapRow = Math.max(0, Math.min(snapRow, GRID_ROWS - h));

    // Ghost positioned at snapped tile (viewport coordinates)
    var ghostLeft = rect.left - canvas.scrollLeft + tileLeft(snapCol) * scale;
    var ghostTop = rect.top - canvas.scrollTop + tileTop(snapRow) * scale;
    var ghostW = (w * cellW - TILE_GAP) * scale;
    var ghostH = (h * cellH - TILE_GAP) * scale;

    var valid = checkPlacement(cageId, snapRow, snapCol);

    // Check if the snap position actually changed — avoids redundant DOM updates
    var snapChanged = (window._lastSnap === undefined ||
                       window._lastSnap.row !== snapRow ||
                       window._lastSnap.col !== snapCol);
    if (!snapChanged && ghost.classList.contains('hidden') === false) return;

    window._lastSnap = { row: snapRow, col: snapCol };

    ghost.style.left = ghostLeft + 'px';
    ghost.style.top = ghostTop + 'px';
    ghost.style.width = ghostW + 'px';
    ghost.style.height = ghostH + 'px';
    ghost.style.borderColor = valid ? m.color : '#9b1c24';
    ghost.style.backgroundColor = valid ? m.colorSoft : '#fbe4e6';
    ghost.innerHTML = cageLabelHtml(m.code, m.slots_per_row, m.rows, valid ? m.color : '#9b1c24');
    ghost.classList.remove('hidden');

    // Highlight target tiles
    clearGridHighlights();
    if (valid) {
        for (var r = snapRow; r < snapRow + h; r++) {
            for (var c = snapCol; c < snapCol + w; c++) {
                var tiles = document.querySelectorAll('.tile-bg');
                var idx = r * GRID_COLS + c;
                if (idx >= 0 && idx < tiles.length) {
                    tiles[idx].style.backgroundColor = '#dcebfa';
                }
            }
        }
    }
}

function hideGhost() {
    var ghost = document.getElementById('dragGhost');
    if (ghost) ghost.classList.add('hidden');
    clearGridHighlights();
    window._lastSnap = undefined;
}

function clearGridHighlights() {
    document.querySelectorAll('.tile-bg').forEach(function(t) {
        t.style.backgroundColor = '#f9fafb';
    });
}

function handleCanvasDrop(e) {
    var cageId = draggedCageId;
    if (!cageId) return;
    var m = cageMeta[cageId];
    if (!m) return;

    var cellW = TILE_SIZE + TILE_GAP;
    var cellH = TILE_SIZE + TILE_GAP;
    var w = m.slots_per_row;
    var h = m.rows;

    // Compute snap position at drop (same logic as showGhost for consistency)
    var canvas = document.getElementById('farmCanvas');
    var rect = canvas.getBoundingClientRect();
    var scale = getCanvasScale();
    var mx = (e.clientX - rect.left + canvas.scrollLeft) / scale - GRID_PAD;
    var my = (e.clientY - rect.top + canvas.scrollTop) / scale - GRID_PAD;
    var snapCol = Math.floor((mx - grabOffsetCol * cellW) / cellW);
    var snapRow = Math.floor((my - grabOffsetRow * cellH) / cellH);
    snapCol = Math.max(0, Math.min(snapCol, GRID_COLS - w));
    snapRow = Math.max(0, Math.min(snapRow, GRID_ROWS - h));

    // Reject if any part of the footprint would be outside grid bounds
    if (snapCol < 0 || snapRow < 0 || snapCol + w > GRID_COLS || snapRow + h > GRID_ROWS) {
        showDragError('Cannot place here — cage extends beyond the grid edge');
        return;
    }

    // Reject overlap with any other placed cage
    if (!checkPlacement(cageId, snapRow, snapCol)) {
        showDragError('Cannot place here — tiles overlap with another cage');
        return;
    }

    // Place the cage
    placeCage(cageId, snapRow, snapCol);
}

function placeCage(cageId, originRow, originCol) {
    var m = cageMeta[cageId];
    if (!m) return;
    // Remove from staging if present
    removeStagingTile(cageId);
    // Add to placed map
    placedCages[cageId] = {
        origin_row: originRow,
        origin_col: originCol,
        width: m.slots_per_row,
        height: m.rows,
        color: m.color,
        colorSoft: m.colorSoft,
        code: m.code,
    };
    pendingMoves[cageId] = { location_row: originRow, location_column: originCol };
    // Track for edit modal positioning
    savedPositions[cageId] = { location_row: originRow, location_column: originCol };

    recomputeGridExtent();
    renderCanvas();
    selectCage(cageId);
    updateSaveButton();
    updateStagingVisibility();
    document.getElementById('clearAllMsg').classList.add('hidden');
}

function removeStagingTile(cageId) {
    var tile = document.querySelector('.staging-tile[data-cage-id="' + cageId + '"]');
    if (tile) tile.remove();
}

function resetDragState() {
    if (draggedFromCanvas && draggedCageId) {
        var overlay = document.querySelector('.cage-overlay[data-cage-id="' + draggedCageId + '"]');
        if (overlay) overlay.style.opacity = '1';
    }
    draggedCageId = null;
    draggedFromCanvas = false;
}

// ── Remove from Canvas ──
function confirmRemoveCage(cageId) {
    var m = cageMeta[cageId];
    if (!m) return;
    confirmModal(
        'Remove <strong>' + m.code + '</strong> from this canvas position?<br><br>' +
        'The cage and all its data (slots, hens, sensors) will remain completely untouched ' +
        'it will just be moved back to the unplaced area. You must save the layout to persist this change.',
        { submit: function() { doRemoveCage(cageId); } },
        'Remove from Canvas', 'neutral'
    );
}

function doRemoveCage(cageId) {
    delete placedCages[cageId];
    pendingMoves[cageId] = { location_row: null, location_column: null };
    addStagingTile(cageId);
    deselectAll();
    recomputeGridExtent();
    renderCanvas();
    updateSaveButton();
    updateStagingVisibility();
}

function addStagingTile(cageId) {
    var m = cageMeta[cageId];
    if (!m) return;
    var area = document.getElementById('stagingArea');
    var tile = document.createElement('div');
    tile.className = 'staging-tile rounded-lg border-2 px-4 py-2 flex items-center justify-center ' + (IS_ADMIN ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer');
    tile.draggable = IS_ADMIN;
    tile.dataset.cageId = cageId;
    tile.dataset.cageCode = m.code;
    tile.style.borderColor = m.color;
    tile.style.backgroundColor = m.colorSoft;
    tile.innerHTML = '<span class="text-sm font-semibold" style="color:' + m.color + ';">' + m.code + '</span>';
    tile.addEventListener('dragstart', function(e) { handleDragStart(e, cageId); });
    area.appendChild(tile);
}

// ── Clear All ──
function clearAllCages() {
    if (Object.keys(placedCages).length === 0) return;
    confirmModal(
        'Move all cages back to the staging area? This is not applied until you click Save Layout.',
        { submit: doClearAll },
        'Clear All', 'neutral'
    );
}

function doClearAll() {
    var ids = Object.keys(placedCages);
    ids.forEach(function(id) {
        var cageId = parseInt(id);
        addStagingTile(cageId);
        pendingMoves[cageId] = { location_row: null, location_column: null };
        delete placedCages[cageId];
    });
    deselectAll();
    recomputeGridExtent();
    renderCanvas();
    updateSaveButton();
    updateStagingVisibility();
    document.getElementById('clearAllMsg').classList.remove('hidden');
}

// ── Save Layout ──
function hasPendingChanges() {
    return Object.keys(pendingMoves).length > 0;
}

function updateSaveButton() {
    var btn = document.getElementById('saveLayoutBtn');
    var dot = document.getElementById('unsavedDot');
    var dirty = hasPendingChanges();
    if (btn) btn.disabled = !dirty;
    if (dot) dot.classList.toggle('hidden', !dirty);
}

// Suppress beforeunload for intentional form submissions (add/edit cage forms,
// which use data-turbo="false" full-page POSTs unrelated to canvas layout state).
['addCageForm', 'editCageForm'].forEach(function(id) {
    var form = document.getElementById(id);
    if (form) {
        form.addEventListener('submit', function() {
            window.__intentionalFormSubmit = true;
        });
    }
});

// Warn before leaving with unsaved canvas changes
window.addEventListener('beforeunload', function(e) {
    if (window.__intentionalFormSubmit) return;
    if (hasPendingChanges()) {
        e.preventDefault();
        e.returnValue = '';
    }
});

function updateStagingVisibility() {
    var section = document.getElementById('stagingSection');
    var area = document.getElementById('stagingArea');
    if (section && area) section.classList.toggle('hidden', area.children.length === 0);
}

function setSavingState(saving) {
    var overlay = document.getElementById('farmSaveOverlay');
    var saveBtn = document.getElementById('saveLayoutBtn');
    var clearBtn = document.getElementById('clearAllBtn');
    if (overlay) {
        overlay.classList.toggle('hidden', !saving);
        overlay.classList.toggle('flex', saving);
    }
    if (clearBtn) clearBtn.disabled = saving;
    if (saveBtn) saveBtn.disabled = saving || !hasPendingChanges();
}

function saveLayout() {
    var ids = Object.keys(pendingMoves);
    if (ids.length === 0) return;

    var positions = ids.map(function(id) {
        return {
            id: parseInt(id),
            location_row: pendingMoves[id].location_row,
            location_column: pendingMoves[id].location_column,
        };
    });

    setSavingState(true);

    fetch(cagesBase + '/batch-position', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ positions: positions }),
    })
    .then(function(r) {
        return r.json().then(function(data) { return { ok: r.ok, data: data }; });
    })
    .then(function(res) {
        if (res.ok && res.data.success) {
            Object.assign(savedPositions, pendingMoves);
            pendingMoves = {};
            setSavingState(false);
            document.getElementById('clearAllMsg').classList.add('hidden');
            showToast('Layout saved', true);
        } else {
            setSavingState(false);
            showDragError(res.data.message || 'Failed to save layout');
            resyncPositionsFromServer();
        }
    })
    .catch(function() {
        setSavingState(false);
        showDragError('Failed to save layout');
    });
}

// Re-sync client-side placement state from savedPositions (last-known-good
// server state) after a failed save, avoiding a full page reload that would
// trigger the beforeunload "unsaved changes" dialog.
function resyncPositionsFromServer() {
    placedCages = {};
    Object.keys(savedPositions).forEach(function(id) {
        var m = cageMeta[id];
        if (!m) return;
        var pos = savedPositions[id];
        if (pos.location_row !== null && pos.location_column !== null) {
            placedCages[id] = {
                origin_row: pos.location_row,
                origin_col: pos.location_column,
                width: m.slots_per_row,
                height: m.rows,
                color: m.color,
                colorSoft: m.colorSoft,
                code: m.code,
            };
        }
    });

    pendingMoves = {};

    Object.keys(cageMeta).forEach(function(id) {
        if (placedCages[id]) {
            removeStagingTile(id);
        } else {
            addStagingTile(id);
        }
    });

    recomputeGridExtent();
    renderCanvas();
    updateSaveButton();
    updateStagingVisibility();
}

// ── Toast ──
function showToast(msg, isSuccess) {
    var toast = document.getElementById('dragErrorToast');
    toast.textContent = msg;
    toast.style.backgroundColor = isSuccess ? '#1f6b3a' : '#9b1c24';
    toast.classList.remove('hidden');
    setTimeout(function() { toast.classList.add('hidden'); }, 3000);
}

function showDragError(msg) {
    showToast(msg, false);
}

// ── Grid Settings (post-onboarding canvas resize) ─────────
function openGridSettings() {
    document.getElementById('gridSettingsRows').value = GRID_ROWS;
    document.getElementById('gridSettingsCols').value = GRID_COLS;
    document.getElementById('gridSettingsWarning').classList.add('hidden');
    document.getElementById('gridSettingsModal').style.display = 'flex';
    lucide.createIcons();
}

function closeGridSettings() {
    document.getElementById('gridSettingsModal').style.display = 'none';
}

function applyGridSettings() {
    var newRows = parseInt(document.getElementById('gridSettingsRows').value);
    var newCols = parseInt(document.getElementById('gridSettingsCols').value);
    if (!newRows || !newCols || newRows < 1 || newCols < 1) {
        showDragError('Rows and columns must be at least 1');
        return;
    }

    // Validate: check if any placed cage extends beyond new dimensions
    var affected = [];
    for (var id in placedCages) {
        var c = placedCages[id];
        if (c.origin_row + c.height > newRows || c.origin_col + c.width > newCols) {
            affected.push(c.code);
        }
    }

    if (affected.length > 0) {
        var warn = document.getElementById('gridSettingsWarning');
        warn.innerHTML = '<strong>Cannot shrink the grid</strong> — the following cage(s) extend beyond the new dimensions:<br><br>'
            + affected.map(function(code) { return '&bull; ' + code; }).join('<br>')
            + '<br><br>Move or remove these cages first, or increase the grid dimensions.';
        warn.classList.remove('hidden');
        return;
    }

    // Persist to server, then update canvas in-place
    fetch('{{ route('settings.farm-layout') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ rows: newRows, cols: newCols }),
    })
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function() {
        // Update local variables
        STORED_GRID_ROWS = newRows;
        STORED_GRID_COLS = newCols;
        GRID_ROWS = newRows;
        GRID_COLS = newCols;
        // Re-render the tile grid and reposition overlays
        renderTileGrid();
        renderCageOverlays();
        closeGridSettings();
        showToast('Grid dimensions updated.', true);
    })
    .catch(function() {
        showDragError('Failed to update grid dimensions');
    });
}

// ── Filter / Tab (kept from original) ──
function handleTileClick(e, cageId, cageCode) {
    e.stopPropagation();
    if (activeFilterId === cageId) {
        clearCanvasFilter();
        return;
    }
    activeFilterId = cageId;
    document.querySelectorAll('.cage-card').forEach(function(card) {
        card.style.display = card.dataset.cageCode === cageCode ? '' : 'none';
    });
    document.getElementById('clearFilterBtn').classList.remove('hidden');
    document.querySelectorAll('.cage-tab').forEach(function(tab) {
        if (tab.dataset.tab === cageCode) {
            tab.style.borderBottomColor = '#002D5E';
            tab.style.color = '#1f1f1f';
        } else {
            tab.style.borderBottomColor = 'transparent';
            tab.style.color = '#615d59';
        }
    });
}

function clearCanvasFilter() {
    activeFilterId = null;
    document.querySelectorAll('.cage-card').forEach(function(card) {
        card.style.display = '';
    });
    document.getElementById('clearFilterBtn').classList.add('hidden');
    filterCage('all');
}

// ── Init ──
renderCanvas();

// ── Delete Cage (uses shared confirmModal) ─────────────────
var deleteTargetId = null;

function openDeleteModal(id, code) {
    deleteTargetId = id;
    var form = {
        submit: function() {
            if (!deleteTargetId) return;
            var data = {};
            document.querySelectorAll('#del-options input, #del-options select').forEach(function(el) {
                if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
                else if (el.type === 'checkbox') { data[el.id] = el.checked; }
                else { data[el.name || el.id] = el.value; }
            });
            fetch(cagesBase + '/' + deleteTargetId, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    hens_action: data.delHensAction || 'delete',
                    return_sensors: !!data.delReturnSensors,
                    preserve_production: !!data.delPreserveProduction,
                    preserve_mortality: !!data.delPreserveMortality,
                    preserve_feed: !!data.delPreserveFeed,
                    preserve_environment: !!data.delPreserveEnvironment,
                }),
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(res) {
                deleteTargetId = null;
                if (res.ok && res.data.success) {
                    showToast(res.data.message || 'Cage deleted', true);
                    Turbo.visit(window.location.href, { action: 'replace' });
                } else {
                    showDragError(res.data.message || 'Failed to delete cage');
                }
            })
            .catch(function() {
                deleteTargetId = null;
                showDragError('Failed to delete cage');
            });
        }
    };
    var msg = '<strong>Delete ' + code + '?</strong><br><br>' +
        '<div id="del-options">' +
        '<div style="background:#f6f5f4;border-radius:8px;padding:12px;margin-bottom:12px;">' +
        '<div style="font-weight:500;margin-bottom:8px;color:#31302e;">Hens in this cage (<span id="delHenCount">…</span> active)</div>' +
        '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;color:#615d59;">' +
        '<input type="radio" name="delHensAction" value="move" checked style="accent-color:#0075de;">' +
        'Move to unplaced (return to chicken inventory)</label>' +
        '<label style="display:flex;align-items:center;gap:8px;color:#615d59;">' +
        '<input type="radio" name="delHensAction" value="delete" style="accent-color:#9b1c24;">' +
        'Delete permanently</label>' +
        '</div>' +
        '<div style="background:#f6f5f4;border-radius:8px;padding:12px;margin-bottom:12px;">' +
        '<label style="display:flex;align-items:center;gap:8px;color:#615d59;">' +
        '<input type="checkbox" id="delReturnSensors" checked style="accent-color:#0075de;">' +
        'Return <span id="delSensorCount">…</span> sensor(s) to inventory</label>' +
        '<p style="font-size:12px;margin-top:4px;margin-left:24px;color:#a39e98;">If unchecked, sensors are deleted with the cage.</p>' +
        '</div>' +
        '<div style="background:#f6f5f4;border-radius:8px;padding:12px;margin-bottom:12px;">' +
        '<div style="font-weight:500;margin-bottom:8px;color:#31302e;">Preserve historical records</div>' +
        '<p style="font-size:12px;margin-bottom:8px;color:#a39e98;">Checked records survive deletion (FK removed). Unchecked are permanently deleted.</p>' +
        '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;color:#615d59;">' +
        '<input type="checkbox" id="delPreserveProduction" checked style="accent-color:#0075de;">' +
        'Egg production logs (<span class="del-log-count" data-type="production">…</span>)</label>' +
        '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;color:#615d59;">' +
        '<input type="checkbox" id="delPreserveMortality" checked style="accent-color:#0075de;">' +
        'Mortality records (<span class="del-log-count" data-type="mortality">…</span>)</label>' +
        '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;color:#615d59;">' +
        '<input type="checkbox" id="delPreserveFeed" checked style="accent-color:#0075de;">' +
        'Feed consumption logs (<span class="del-log-count" data-type="feed">…</span>)</label>' +
        '<label style="display:flex;align-items:center;gap:8px;color:#615d59;">' +
        '<input type="checkbox" id="delPreserveEnvironment" checked style="accent-color:#0075de;">' +
        'Environment logs (<span class="del-log-count" data-type="env">…</span>)</label>' +
        '</div></div>';
    confirmModal(msg, form, 'Delete', 'destructive');

    fetch(cagesBase + '/' + id + '/delete-info')
        .then(function(r) { return r.json(); })
        .then(function(info) {
            var el;
            el = document.getElementById('delHenCount'); if (el) el.textContent = info.hens;
            el = document.getElementById('delSensorCount'); if (el) el.textContent = info.sensors;
            var typeMap = { production: info.production_logs, mortality: info.mortality_logs, feed: info.feed_logs, env: info.env_logs };
            document.querySelectorAll('#del-options .del-log-count').forEach(function(el) {
                el.textContent = typeMap[el.dataset.type] || 0;
            });
        })
        .catch(function() {
            document.querySelectorAll('#del-options .del-log-count').forEach(function(el) { el.textContent = '?'; });
        });
}

// ── Slot Reorder (Item 1: drag-and-drop renumbering) ────
var reorderState = {};  // { cageId: { slotId: newNumber, ... } }

function toggleReorderMode(cageId) {
    var state = reorderState[cageId];
    if (state && state.active) {
        cancelReorder(cageId);
        return;
    }
    var bar = document.getElementById('reorderBar-' + cageId);
    var slots = document.querySelectorAll('.slot-grid-' + cageId + ' .slot-mini');
    var numberLabels = document.querySelectorAll('.slot-grid-' + cageId + ' .slot-reorder-number');

    // Initialize reorder state for this cage
    reorderState[cageId] = { active: true };
    slots.forEach(function(btn) {
        var id = parseInt(btn.dataset.slotId);
        reorderState[cageId][id] = {
            current: parseInt(btn.dataset.originalNumber),
            original: parseInt(btn.dataset.originalNumber),
        };
    });

    // Show reorder bar
    if (bar) bar.classList.remove('hidden');

    // Hide occupancy numbers, show reorder numbers
    slots.forEach(function(btn) {
        var id = parseInt(btn.dataset.slotId);
        var numSpan = btn.querySelector('.slot-reorder-number');
        var occSpan = btn.querySelector('.text-xs.font-semibold');
        var dashSpan = btn.querySelector('.text-xs');
        if (numSpan) {
            numSpan.textContent = reorderState[cageId][id].current;
            numSpan.classList.remove('hidden');
        }
        // Hide occupancy/dash during reorder
        if (occSpan && !occSpan.classList.contains('slot-reorder-number')) occSpan.style.display = 'none';
        if (dashSpan && !dashSpan.classList.contains('slot-reorder-number') && !dashSpan.classList.contains('absolute')) dashSpan.style.display = 'none';
        // Make draggable
        btn.draggable = true;
        btn.classList.add('cursor-grab', 'active\:cursor-grabbing');
    });

    // Setup drag events
    setupReorderDrag(cageId);
    lucide.createIcons();
}

function setupReorderDrag(cageId) {
    var slots = document.querySelectorAll('.slot-grid-' + cageId + ' .slot-mini');
    var draggedId = null;

    slots.forEach(function(btn) {
        btn.addEventListener('dragstart', function(e) {
            draggedId = parseInt(this.dataset.slotId);
            this.classList.add('opacity-50');
            e.dataTransfer.effectAllowed = 'move';
        });

        btn.addEventListener('dragend', function(e) {
            this.classList.remove('opacity-50');
        });

        btn.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        btn.addEventListener('drop', function(e) {
            e.preventDefault();
            var targetId = parseInt(this.dataset.slotId);
            if (!draggedId || draggedId === targetId) return;
            swapSlotNumbers(cageId, draggedId, targetId);
            draggedId = null;
        });
    });
}

function swapSlotNumbers(cageId, idA, idB) {
    var state = reorderState[cageId];
    if (!state || !state[idA] || !state[idB]) return;

    var temp = state[idA].current;
    state[idA].current = state[idB].current;
    state[idB].current = temp;

    // Update displayed numbers
    var slots = document.querySelectorAll('.slot-grid-' + cageId + ' .slot-mini');
    slots.forEach(function(btn) {
        var id = parseInt(btn.dataset.slotId);
        var numSpan = btn.querySelector('.slot-reorder-number');
        if (numSpan && state[id]) {
            numSpan.textContent = state[id].current;
        }
    });
}

function saveReorder(cageId) {
    var state = reorderState[cageId];
    if (!state || !state.active) return;

    var slots = [];
    var changed = false;
    Object.keys(state).forEach(function(key) {
        if (key === 'active') return;
        var id = parseInt(key);
        if (state[id].current !== state[id].original) {
            changed = true;
        }
        slots.push({ id: id, slot_number: state[id].current });
    });

    if (!changed) {
        cancelReorder(cageId);
        return;
    }

    var saveBtn = document.querySelector('#reorderBar-' + cageId + ' button');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026'; }

    fetch(cagesBase + '/' + cageId + '/slots/reorder', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ slots: slots }),
    })
    .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
    .then(function(res) {
        if (res.ok && res.data.success) {
            showToast('Slot numbers updated', true);
            Turbo.visit(window.location.href, { action: 'replace' });
        } else {
            showDragError(res.data.message || 'Failed to reorder slots');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
        }
    })
    .catch(function() {
        showDragError('Failed to reorder slots');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
    });
}

function cancelReorder(cageId) {
    var state = reorderState[cageId];
    if (!state) return;

    var bar = document.getElementById('reorderBar-' + cageId);
    var slots = document.querySelectorAll('.slot-grid-' + cageId + ' .slot-mini');

    if (bar) bar.classList.add('hidden');

    slots.forEach(function(btn) {
        var numSpan = btn.querySelector('.slot-reorder-number');
        var occSpan = btn.querySelector('.text-xs.font-semibold');
        var dashSpan = btn.querySelector('.text-xs');
        if (numSpan) numSpan.classList.add('hidden');
        if (occSpan && !occSpan.classList.contains('slot-reorder-number')) occSpan.style.display = '';
        if (dashSpan && !dashSpan.classList.contains('slot-reorder-number') && !dashSpan.classList.contains('absolute')) dashSpan.style.display = '';
        btn.draggable = false;
        btn.classList.remove('cursor-grab', 'active\\:cursor-grabbing');
    });

    delete reorderState[cageId];
    lucide.createIcons();
}

// ── Auto-open edit modal on resize error ─────────────────
@if(session('edit_cage_id') && isset($editCage))
if (!window.__cagesAutoEditBound) {
    window.__cagesAutoEditBound = true;
    document.addEventListener('turbo:load', function() {
        openEditModal(
            {{ $editCage->id }},
            '{{ $editCage->cage_code }}',
            {{ is_null($editCage->location_row) ? 'null' : $editCage->location_row }},
            {{ is_null($editCage->location_column) ? 'null' : $editCage->location_column }},
            {{ $editCage->rows ?? 0 }},
            {{ $editCage->slots_per_row ?? 0 }},
            {{ $editCage->max_chickens_per_slot ?? 0 }},
            {{ $editCage->is_active ? 1 : 0 }}
        );
        @if(session('errors') && session('errors')->has('resize'))
        const errEl = document.getElementById('editResizeError');
        const errText = document.getElementById('editResizeErrorText');
        if (errEl) errEl.classList.remove('hidden');
        if (errText) errText.textContent = '{{ addslashes(session('errors')->first('resize')) }}';
        lucide.createIcons();
        @endif
    });
}
@endif

// ── Auto-open no-chickens modal ───────────────────────────
// Use DOMContentLoaded (not a persistent turbo:load listener) so the
// modal only opens on the initial page load when the session flag is
// set, not on subsequent Turbo navigations where the flag may be gone.
@if(session('show_no_chickens_modal'))
(function() {
    if (window.__cagesNoChickensFired) return;
    window.__cagesNoChickensFired = true;
    var f = function() { openNoChickensModal(); };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', f);
    } else {
        f();
    }
})();
@endif

</script>
@endpush

{{-- Move + Remove + Register Modals ─────────────────────────── --}}
@include('chickens.partials.move-modal')
@include('chickens.partials.remove-modal')
@include('chickens.partials.register-modal')
