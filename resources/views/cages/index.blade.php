@extends('layouts.app')
@section('title', 'Cage Management')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="space-y-5">

    <x-page-header title="Cages" subtitle="Manage battery cage configurations, slots, and sensor placement">
        <x-slot:actions>
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                <a href="{{ route('cages.bulk-add') }}"
                   class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 text-xs sm:text-sm font-medium rounded-full transition-colors"
                   style="color: #0075de; border: 1px solid #0075de;"
                   onmouseover="this.style.backgroundColor='#dcebfa'"
                   onmouseout="this.style.backgroundColor='transparent'">
                    <i data-lucide="bird" class="w-4 h-4"></i> Bulk Add Chickens
                </a>
                @if($isAdmin)
                <x-button onclick="openAddModal()">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add Cage
                </x-button>
                @endif
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Farm Layout Canvas (drag-and-drop) ── --}}
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
            <h3 class="text-xs font-semibold tracking-[0.05em] uppercase" style="color: #615d59;">Farm Layout</h3>
            <div class="flex items-center gap-2">
                <button id="clearFilterBtn" class="hidden text-xs font-medium px-3 py-1 rounded-lg transition-colors" style="color: #0075de; border: 1px solid #0075de;" onclick="clearCanvasFilter()">Show all</button>
                {{-- Layout flow toggle removed per audit decision --}}
                @if($isAdmin)
                <button id="clearAllBtn" onclick="clearAllCages()"
                        class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors disabled:opacity-45 disabled:cursor-not-allowed"
                        style="color: #9b1c24; border: 1px solid #f0c8cb;"
                        onmouseover="if(!this.disabled)this.style.backgroundColor='#fbe4e6'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Clear All Cages
                </button>
                <x-button id="saveLayoutBtn" onclick="saveLayout()" disabled class="text-xs px-4 py-1.5">
                    Save Layout
                </x-button>
                @endif
            </div>
        </div>
        <div id="farmCanvas" class="relative">
        <div id="farmSaveOverlay" class="hidden absolute inset-0 z-10 items-center justify-center rounded-lg" style="background-color: rgba(255,255,255,0.7);">
            <i data-lucide="loader" class="animate-spin w-8 h-8" style="color: #0075de;"></i>
        </div>
        <div id="farmGrid" class="grid gap-2" style="grid-template-columns: repeat({{ $gridCols }}, minmax(0, 1fr));">
            @for($r = 0; $r < $gridRows; $r++)
                @for($c = 0; $c < $gridCols; $c++)
                @php
                    $placedCage = $cages->firstWhere(fn($cg) => $cg->location_row === $r && $cg->location_column === $c);
                @endphp
                @if($placedCage)
                <div class="farm-cell min-h-[5rem] rounded-lg border-2 p-3 flex flex-col justify-between transition-all group relative"
                     style="border-color: {{ $placedCage->color }}; background-color: {{ $placedCage->colorSoft }};"
                     data-row="{{ $r }}" data-col="{{ $c }}"
                     ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, {{ $r }}, {{ $c }})">
                    @if($isAdmin)
                    <button onclick="event.stopPropagation(); confirmRemoveCell({{ $r }}, {{ $c }}, '{{ $placedCage->cage_code }}')"
                            class="remove-cell-btn absolute top-0.5 right-0.5 w-7 h-7 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                            style="background-color: rgba(155,28,36,0.85); color: #fff; font-size: 12px; line-height: 1;"
                            title="Remove {{ $placedCage->cage_code }} from canvas" aria-label="Remove {{ $placedCage->cage_code }}">
                        ×
                    </button>
                    @endif
                    <div class="farm-tile {{ $isAdmin ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer' }}"
                         draggable="{{ $isAdmin ? 'true' : 'false' }}"
                         data-cage-id="{{ $placedCage->id }}"
                         data-cage-code="{{ $placedCage->cage_code }}"
                         @if($isAdmin) ondragstart="handleDragStart(event, {{ $placedCage->id }})" @endif
                         onclick="handleTileClick(event, {{ $placedCage->id }}, '{{ $placedCage->cage_code }}')">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold" style="color: {{ $placedCage->color }};">{{ $placedCage->cage_code }}</span>
                        </div>
                        <div class="text-xs truncate" style="color: #615d59;">{{ Str::limit($placedCage->hens->first()?->breed ?? '—', 16) }}</div>
                    </div>
                </div>
                @else
                <div class="farm-cell min-h-[5rem] rounded-lg border p-3 flex items-center justify-center transition-all group relative"
                     style="border-color: #e6e6e6; background-color: #f9fafb;"
                     data-row="{{ $r }}" data-col="{{ $c }}"
                     ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, {{ $r }}, {{ $c }})">
                    @if($isAdmin)
                    <button onclick="event.stopPropagation(); confirmRemoveCell({{ $r }}, {{ $c }})"
                            class="remove-cell-btn absolute top-0.5 right-0.5 w-7 h-7 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                            style="background-color: rgba(155,28,36,0.85); color: #fff; font-size: 12px; line-height: 1;"
                            title="Remove empty cell" aria-label="Remove empty cell">
                        ×
                    </button>
                    @endif
                    <span class="text-xs" style="color: #d1d5db;">{{ $r + 1 }}-{{ $c + 1 }}</span>
                </div>
                @endif
                @endfor
            @endfor
        </div>

        {{-- Staging Area (unplaced cages) — always rendered so tiles can be moved here client-side --}}
        @php $unplaced = $cages->filter(fn($cg) => is_null($cg->location_row)); @endphp
        <div id="stagingSection" class="mt-4 pt-4 border-t {{ $unplaced->count() > 0 ? '' : 'hidden' }}" style="border-color: #e6e6e6;">
            <h4 class="text-xs font-semibold tracking-[0.05em] uppercase mb-3" style="color: #615d59;">Unplaced Cages — drag to grid</h4>
            <div id="stagingArea" class="flex flex-wrap gap-3 min-h-[3.5rem]"
                 ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleStagingDrop(event)">
                @foreach($unplaced as $uc)
                <div class="farm-tile min-h-[3.5rem] rounded-lg border-2 px-4 py-2 flex flex-col justify-center {{ $isAdmin ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer' }}"
                     style="border-color: {{ $uc->color }}; background-color: {{ $uc->colorSoft }};"
                     draggable="{{ $isAdmin ? 'true' : 'false' }}"
                     data-cage-id="{{ $uc->id }}"
                     data-cage-code="{{ $uc->cage_code }}"
                     @if($isAdmin) ondragstart="handleDragStart(event, {{ $uc->id }})" @endif
                     onclick="handleTileClick(event, {{ $uc->id }}, '{{ $uc->cage_code }}')">
                    <span class="text-sm font-semibold" style="color: {{ $uc->color }};">{{ $uc->cage_code }}</span>
                </div>
                @endforeach
            </div>
        </div>
        </div>{{-- /#farmCanvas --}}

        {{-- Error Toast --}}
        <div id="dragErrorToast" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-50 rounded-lg px-4 py-2 text-sm font-medium text-white" style="background-color: #9b1c24;">
        </div>
    </div>

    {{-- ── Tab Bar (Notion underline style) ── --}}
    <div class="flex items-center gap-0 border-b overflow-x-auto" style="border-color: #e6e6e6;">
        <button type="button" onclick="filterCage('all')" class="cage-tab px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                data-tab="all"
                style="border-bottom-color: #0075de; color: #1f1f1f;">
            All
            <span class="ml-1 text-xs" style="color: #a39e98;">({{ $cages->count() }})</span>
        </button>
        @foreach($cages as $cage)
        <button type="button" onclick="filterCage('{{ $cage->cage_code }}')" class="cage-tab px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                data-tab="{{ $cage->cage_code }}"
                style="border-bottom-color: transparent; color: #615d59;">
            <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background-color: {{ $cage->color }};"></span>
            {{ $cage->cage_code }}
            <span class="ml-1 text-xs" style="color: #a39e98;">({{ $cage->cageSlots->count() }})</span>
        </button>
        @endforeach
    </div>

    {{-- ── Cage Cards ── --}}
    <div class="flex flex-wrap gap-4">
        @forelse($cages as $cage)
        @php
            $color = $cage->color;
            $colorSoft = $cage->colorSoft;
            $slotsByRow = $cage->cageSlots->groupBy('row_number');
            $sensorCount = $cage->cageSlots->filter(fn($s) => $s->hasBreakbeam())->count();
            $occupiedCount = $cage->cageSlots->where('current_occupancy', '>', 0)->count();
            $primaryHen = $cage->hens->first();
            $cardMinWidth = max(240, $cage->slots_per_row * 30 + 40);
        @endphp
        <div class="cage-card rounded-xl border overflow-hidden transition-all flex-grow"
             data-cage-code="{{ $cage->cage_code }}"
              style="background-color: #ffffff; border-color: #e6e6e6; border-left: 3px solid {{ $color }}; flex: 1 1 {{ $cardMinWidth }}px; min-width: min({{ $cardMinWidth }}px, 100%); max-width: 100%;">

            {{-- Cage Header --}}
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold" style="color: {{ $color }}">{{ $cage->cage_code }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: {{ $cage->is_active ? '#e8f5ec' : '#f0f0f0' }}; color: {{ $cage->is_active ? '#1f6b3a' : '#615d59' }};">
                        {{ $cage->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <button onclick="window.open('{{ route('cages.print-label', $cage) }}', 'print-{{ $cage->id }}', 'width=900,height=700')"
                            class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Print cage label">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    </button>
                    <a href="{{ route('cages.bulk-add') }}?cage_id={{ $cage->id }}"
                       class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Bulk add hens">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                    </a>
                    @if($isAdmin)
                    <button onclick="toggleReorderMode({{ $cage->id }})"
                            class="p-1.5 rounded hover:bg-black/5 transition-colors reorder-toggle" style="color: #615d59;" aria-label="Renumber slots">
                        <i data-lucide="list-ordered" class="w-3.5 h-3.5"></i>
                    </button>
                    <button onclick="openEditModal({{ $cage->id }}, '{{ $cage->cage_code }}', {{ is_null($cage->location_row) ? 'null' : $cage->location_row }}, {{ is_null($cage->location_column) ? 'null' : $cage->location_column }}, {{ $cage->rows }}, {{ $cage->slots_per_row }}, {{ $cage->max_chickens_per_slot }}, {{ $cage->is_active ? 1 : 0 }})"
                            class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Edit cage">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>
                    <button onclick="openDeleteModal({{ $cage->id }}, '{{ $cage->cage_code }}')"
                            class="p-1.5 rounded hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete cage">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Meta strip --}}
            <div class="flex items-center gap-4 px-4 pb-2 text-xs" style="color: #615d59;">
                <span>{{ $cage->rows }}×{{ $cage->slots_per_row }}</span>
                <span>{{ $cage->total_capacity }} capacity</span>
                <span>{{ $occupiedCount }} occupied</span>
                @if($sensorCount > 0)
                <span>{{ $sensorCount }} sensor{{ $sensorCount > 1 ? 's' : '' }}</span>
                @endif
                @if($primaryHen)
                <span>{{ $primaryHen->breed }} · {{ $primaryHen->current_age_weeks }}w</span>
                @endif
            </div>

            {{-- Mini Slot Grid --}}
            <div class="px-4 pb-3">
                <div class="grid gap-1 slot-grid-{{ $cage->id }}" style="grid-template-columns: repeat({{ $cage->slots_per_row }}, 1fr);">
                    @foreach($cage->cageSlots as $slot)
                    @php
                        $isSensor = $slot->hasBreakbeam();
                        $occupancy = $slot->current_occupancy;
                        $slotBg = $isSensor ? '#d6f0e3' : ($occupancy > 0 ? '#f6f5f4' : '#ffffff');
                        $slotBorder = $isSensor ? '#2a9d6a' : '#e6e6e6';
                    @endphp
                    <button type="button"
                            onclick="expandSlot({{ $slot->id }}, {{ $cage->id }}, '{{ $cage->cage_code }}')"
                            class="slot-mini aspect-square rounded flex flex-col items-center justify-center text-xs transition-all relative"
                            style="background-color: {{ $slotBg }}; border: 1px solid {{ $slotBorder }};"
                            title="Slot {{ $slot->row_number }}-{{ $slot->column_number }}: {{ $occupancy }} hens"
                            aria-label="Slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $occupancy }} hens"
                            data-slot-id="{{ $slot->id }}"
                            data-original-number="{{ $slot->slot_number }}">
                        @if($isSensor)
                        <span class="absolute top-0 right-0 w-1.5 h-1.5 rounded-bl" style="background-color: #0075de;"></span>
                        @endif
                        <span class="slot-reorder-number hidden text-xs font-bold" style="color: #002D5E;">{{ $slot->slot_number }}</span>
                        @if($occupancy > 0)
                        <span class="text-xs font-semibold" style="color: #1f1f1f;">{{ $occupancy }}</span>
                        @else
                        <span class="text-xs" style="color: #d1d5db;">—</span>
                        @endif
                    </button>
                    @endforeach
                </div>
                {{-- Reorder control bar --}}
                <div id="reorderBar-{{ $cage->id }}" class="hidden mt-2 flex items-center justify-between text-xs" style="color: #615d59;">
                    <span>Drag slots to renumber</span>
                    <div class="flex items-center gap-2">
                        <button onclick="saveReorder({{ $cage->id }})" class="px-2 py-1 rounded text-white text-xs font-medium" style="background-color: #002D5E;">Save</button>
                        <button onclick="cancelReorder({{ $cage->id }})" class="px-2 py-1 rounded text-xs" style="background-color: #e6e6e6;">Cancel</button>
                    </div>
                </div>
            </div>

            {{-- Expanded Detail Panel (inside cage-card for proper flex containment) --}}
            <div id="slotExpandPanel-{{ $cage->id }}" class="hidden border-t" style="border-color: #e6e6e6; background-color: #f6f5f4;">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span id="slotPanelTitle-{{ $cage->id }}" class="text-sm font-semibold" style="color: #1f1f1f;">Slot details</span>
                        <button onclick="closeSlotExpand({{ $cage->id }})" class="p-1.5 rounded hover:bg-black/5 transition-colors" aria-label="Close">
                            <i data-lucide="x" class="w-4 h-4" style="color: #615d59;"></i>
                        </button>
                    </div>
                    <div id="slotPanelContent-{{ $cage->id }}">
                        <div class="text-xs text-center py-4" style="color: #a39e98;">Loading...</div>
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

            <form method="POST" action="{{ route('cages.store') }}" id="addCageForm" data-turbo="false" onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Adding\u2026')">
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
                            <input type="number" name="rows" id="addRows" value="3" min="1" max="10"
                                   oninput="updateAddPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Slots</label>
                            <input type="number" name="slots_per_row" id="addSlotsPerRow" value="5" min="1" max="100"
                                   oninput="updateAddPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Max/Slot</label>
                            <input type="number" name="max_chickens_per_slot" id="addMaxPerSlot" value="4" min="1" max="10"
                                   oninput="updateAddPreview()"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
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

                <div class="space-y-4">
                    <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                        <div class="text-xs font-semibold tracking-[0.05em] uppercase mb-1" style="color: #615d59;">Canvas Position</div>
                        <div id="editCanvasPosition" class="text-sm" style="color: #31302e;">—</div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Rows</label>
                            <input type="number" name="rows" id="editRows" value="3" min="1" max="10"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Slots</label>
                            <input type="number" name="slots_per_row" id="editSlotsPerRow" value="5" min="1" max="100"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Max/Slot</label>
                            <input type="number" name="max_chickens_per_slot" id="editMaxPerSlot" value="4" min="1" max="10"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="editActive" name="is_active" type="checkbox" value="1" class="w-4 h-4 rounded" style="accent-color: #0075de;">
                        <label for="editActive" class="text-sm" style="color: #31302e;">Active</label>
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

    {{-- ── Delete Cage Modal (items 19 + 20) ── --}}
    <div id="deleteCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeDeleteModal()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="mb-4 flex items-center justify-center w-10 h-10 rounded-full" style="background-color: #fbe4e6;">
                <i data-lucide="trash-2" class="w-5 h-5" style="color: #9b1c24;"></i>
            </div>
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Delete <span id="deleteCageCode"></span>?</h2>
            <p class="mt-1 text-sm mb-4" style="color: #615d59;">Choose what the deletion includes:</p>

            <div class="space-y-3 text-sm">
                {{-- Hens --}}
                <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                    <div class="font-medium mb-2" style="color: #31302e;">Hens in this cage (<span id="delHenCount">0</span> active)</div>
                    <label class="flex items-center gap-2 cursor-pointer mb-1.5">
                        <input type="radio" name="delHensAction" value="move" checked class="w-4 h-4" style="accent-color: #0075de;">
                        <span style="color: #615d59;">Move to unplaced (return to chicken inventory)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="delHensAction" value="delete" class="w-4 h-4" style="accent-color: #9b1c24;">
                        <span style="color: #615d59;">Delete permanently</span>
                    </label>
                </div>

                {{-- Sensors --}}
                <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="delReturnSensors" checked class="w-4 h-4 rounded" style="accent-color: #0075de;">
                        <span style="color: #615d59;">Return <span id="delSensorCount">0</span> assigned sensor(s) to inventory</span>
                    </label>
                    <p class="text-xs mt-1 ml-6" style="color: #a39e98;">If unchecked, the sensors are deleted with the cage.</p>
                </div>

                {{-- Historical records — per-type preservation checkboxes --}}
                <div class="rounded-lg p-3" style="background-color: #f6f5f4;">
                    <div class="font-medium mb-2" style="color: #31302e;">Preserve historical records</div>
                    <p class="text-xs mb-2" style="color: #a39e98;">Checked records survive deletion (FK removed). Unchecked ones are permanently deleted.</p>
                    <label class="flex items-center gap-2 cursor-pointer mb-1.5">
                        <input type="checkbox" id="delPreserveProduction" checked class="w-4 h-4 rounded" style="accent-color: #0075de;">
                        <span style="color: #615d59;">Egg production logs (<span class="del-log-count" data-type="production">0</span>)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer mb-1.5">
                        <input type="checkbox" id="delPreserveMortality" checked class="w-4 h-4 rounded" style="accent-color: #0075de;">
                        <span style="color: #615d59;">Mortality records (<span class="del-log-count" data-type="mortality">0</span>)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer mb-1.5">
                        <input type="checkbox" id="delPreserveFeed" checked class="w-4 h-4 rounded" style="accent-color: #0075de;">
                        <span style="color: #615d59;">Feed consumption logs (<span class="del-log-count" data-type="feed">0</span>)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="delPreserveEnvironment" checked class="w-4 h-4 rounded" style="accent-color: #0075de;">
                        <span style="color: #615d59;">Environment logs (<span class="del-log-count" data-type="env">0</span>)</span>
                    </label>
                </div>

                {{-- Delete Permanently (no salvage) — admin-only destructive path --}}
                @if($isAdmin)
                <div class="border-t border-dashed pt-3 mt-1" style="border-color: #e6e6e6;">
                    <a href="#" id="delPermanentLink"
                       class="text-xs font-medium transition-colors"
                       style="color: #9b1c24;"
                       onmouseover="this.style.color='#7a161d'"
                       onmouseout="this.style.color='#9b1c24'">
                        Delete Permanently (no salvage) →
                    </a>
                </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 mt-5">
                <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <button type="button" id="confirmDeleteCageBtn" onclick="confirmCageDelete()"
                        class="px-5 py-2 text-sm font-medium rounded-lg text-white transition-colors disabled:opacity-45 disabled:cursor-not-allowed"
                        style="background-color: #9b1c24;"
                        onmouseover="if(!this.disabled)this.style.backgroundColor='#7a161d'"
                        onmouseout="this.style.backgroundColor='#9b1c24'">
                    Delete Cage
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// ── Tab Filter ────────────────────────────────────────────
function filterCage(code) {
    const cageColors = @json(\App\Models\Cage::getColorMap());
    document.querySelectorAll('.cage-tab').forEach(tab => {
        if (tab.dataset.tab === code) {
            tab.style.borderBottomColor = code === 'all' ? '#0075de' : (cageColors[code] || '#0075de');
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
    fetch(`/cages/slots/${slotId}/hens-json`)
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
                html += '<div class="flex items-center gap-3 rounded border px-3 py-2 text-xs" style="background-color: #ffffff; border-color: #e6e6e6;">';
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

// ── Add Modal ────────────────────────────────────────────
function openAddModal() {
    document.getElementById('addCageModal').style.display = 'flex';
    updateAddPreview();
}

function closeAddModal() {
    document.getElementById('addCageModal').style.display = 'none';
}

function updateAddPreview() {
    const rows = parseInt(document.getElementById('addRows').value) || 1;
    const slotsPerRow = parseInt(document.getElementById('addSlotsPerRow').value) || 1;
    const maxPerSlot = parseInt(document.getElementById('addMaxPerSlot').value) || 1;
    const totalSlots = rows * slotsPerRow;
    const totalCapacity = totalSlots * maxPerSlot;
    document.getElementById('addSummarySlots').textContent = totalSlots;
    document.getElementById('addSummaryCapacity').textContent = totalCapacity + ' hens';

    const container = document.getElementById('addPreviewContainer');
    const grid = document.getElementById('addPreviewGrid');
    const colHeaders = document.getElementById('addPreviewColHeaders');
    const gap = 3;

    // Available width inside container (excl. padding)
    const containerPad = 24; // p-3 = 12px each side
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

    // Column headers (always visible)
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

    // Grid rows (row labels always visible, per-cell numbers conditional)
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

// ── Edit Modal ───────────────────────────────────────────
function openEditModal(id, cageCode, locationRow, locationCol, rows, slotsPerRow, maxPerSlot, isActive) {
    document.getElementById('editCageForm').action = '/cages/' + id;
    document.getElementById('editCageCode').textContent = cageCode;
    document.getElementById('editRows').value = rows;
    document.getElementById('editSlotsPerRow').value = slotsPerRow;
    document.getElementById('editMaxPerSlot').value = maxPerSlot;
    document.getElementById('editActive').checked = isActive === 1;
    document.getElementById('editResizeError').classList.add('hidden');

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
        fetch('/cages/' + id + '/slots-json').then(r => r.json()),
        fetch('/cages/' + id + '/sensor-info').then(r => r.json()),
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
        }
    });
})();

// ── Farm Layout: Drag-and-Drop + Click Filter ────────────
var IS_ADMIN = {{ $isAdmin ? 'true' : 'false' }};
var draggedCageId = null;
var dragMoved = false;
var activeFilterId = null;

// cageId -> { location_row, location_column } (nulls = staging). Applied on Save Layout.
var pendingMoves = {};
// Positions persisted this session without a page refresh; overrides the
// server-rendered values baked into each cage card's edit button.
var savedPositions = {};
var cageMeta = {!! $cages->mapWithKeys(fn($c) => [$c->id => [
    'code' => $c->cage_code,
    'color' => $c->color,
    'colorSoft' => $c->colorSoft,
    'breed' => \Illuminate\Support\Str::limit($c->hens->first()?->breed ?? '—', 16),
]])->toJson(JSON_UNESCAPED_UNICODE) !!};

function hasPendingChanges() {
    return Object.keys(pendingMoves).length > 0;
}

function updateSaveButton() {
    var btn = document.getElementById('saveLayoutBtn');
    if (btn) btn.disabled = !hasPendingChanges();
}

function updateStagingVisibility() {
    var section = document.getElementById('stagingSection');
    var area = document.getElementById('stagingArea');
    if (section && area) section.classList.toggle('hidden', area.children.length === 0);
}

function bindTileEvents(tile, cageId, cageCode) {
    if (IS_ADMIN) {
        tile.addEventListener('dragstart', function(e) { handleDragStart(e, cageId); });
    }
    tile.addEventListener('click', function(e) { handleTileClick(e, cageId, cageCode); });
}

function makeGridTile(cageId) {
    var meta = cageMeta[cageId];
    var tile = document.createElement('div');
    tile.className = 'farm-tile ' + (IS_ADMIN ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer');
    tile.draggable = IS_ADMIN;
    tile.dataset.cageId = cageId;
    tile.dataset.cageCode = meta.code;
    tile.innerHTML =
        '<div class="flex items-center justify-between">' +
            '<span class="text-sm font-semibold"></span>' +
        '</div>' +
        '<div class="text-xs truncate" style="color: #615d59;"></div>';
    var code = tile.querySelector('span');
    code.style.color = meta.color;
    code.textContent = meta.code;
    tile.querySelector('.truncate').textContent = meta.breed;
    bindTileEvents(tile, cageId, meta.code);
    return tile;
}

function addTileToStaging(cageId) {
    var meta = cageMeta[cageId];
    var tile = document.createElement('div');
    tile.className = 'farm-tile min-h-[3.5rem] rounded-lg border-2 px-4 py-2 flex flex-col justify-center ' + (IS_ADMIN ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer');
    tile.draggable = IS_ADMIN;
    tile.dataset.cageId = cageId;
    tile.dataset.cageCode = meta.code;
    tile.style.borderColor = meta.color;
    tile.style.backgroundColor = meta.colorSoft;
    tile.innerHTML = '<span class="text-sm font-semibold"></span>';
    var code = tile.querySelector('span');
    code.style.color = meta.color;
    code.textContent = meta.code;
    bindTileEvents(tile, cageId, meta.code);
    document.getElementById('stagingArea').appendChild(tile);
    updateStagingVisibility();
}

function setCellOccupied(cell, cageId) {
    var meta = cageMeta[cageId];
    cell.className = 'farm-cell min-h-[5rem] rounded-lg border-2 p-3 flex flex-col justify-between transition-all group relative';
    cell.style.borderColor = meta.color;
    cell.style.backgroundColor = meta.colorSoft;
    cell.innerHTML = '';
    cell.appendChild(makeGridTile(cageId));
    addCellRemoveButton(cell, parseInt(cell.dataset.row), parseInt(cell.dataset.col), meta.code);
}

function setCellEmpty(cell) {
    cell.className = 'farm-cell min-h-[5rem] rounded-lg border p-3 flex items-center justify-center transition-all group relative';
    cell.style.borderColor = '#e6e6e6';
    cell.style.backgroundColor = '#f9fafb';
    var r = parseInt(cell.dataset.row), c = parseInt(cell.dataset.col);
    cell.innerHTML = '<span class="text-xs" style="color: #d1d5db;">' + (r + 1) + '-' + (c + 1) + '</span>';
    addCellRemoveButton(cell, r, c);
}

function addCellRemoveButton(cell, row, col, cageCode) {
    if (!IS_ADMIN) return;
    var btn = document.createElement('button');
    btn.innerHTML = '×';
    btn.className = 'remove-cell-btn absolute top-0.5 right-0.5 w-7 h-7 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity';
    btn.style.cssText = 'background-color: rgba(155,28,36,0.85); color: #fff; font-size: 16px; line-height: 1;';
    btn.title = cageCode ? 'Remove ' + cageCode + ' from canvas' : 'Remove empty cell';
    btn.setAttribute('aria-label', btn.title);
    btn.onclick = function(e) {
        e.stopPropagation();
        confirmRemoveCell(row, col, cageCode);
    };
    cell.appendChild(btn);
}

function confirmRemoveCell(row, col, cageCode) {
    if (cageCode) {
        confirmModal(
            'Remove <strong>' + cageCode + '</strong> from this canvas position?<br><br>' +
            'The cage and all its data (slots, hens, sensors) will remain completely untouched — ' +
            'it will just be moved back to the unplaced area. You must save the layout to persist this change.',
            { submit: function() { doRemoveCell(row, col); } },
            'Remove from Canvas'
        );
    } else {
        // Empty cell removal — attempt grid shrink
        doRemoveCell(row, col);
    }
}

function doRemoveCell(row, col) {
    var cell = document.querySelector('.farm-cell[data-row="' + row + '"][data-col="' + col + '"]');
    if (!cell) return;
    var tile = cell.querySelector('.farm-tile');

    if (tile) {
        var cageId = parseInt(tile.dataset.cageId);
        tile.remove();
        setCellEmpty(cell);
        addTileToStaging(cageId);
        pendingMoves[cageId] = { location_row: null, location_column: null };
        updateSaveButton();
        applyRowSpanning();
    } else {
        // Remove empty cell: POST to backend to attempt grid shrink
        fetch('/cages/remove-cell', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({ row: row, col: col }),
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(res) {
            if (res.ok && res.data.success) {
                location.reload();
            } else {
                showDragError(res.data.message || 'Cannot remove this cell');
            }
        })
        .catch(function() {
            showDragError('Failed to remove cell');
        });
    }
}

function handleDragStart(e, cageId) {
    draggedCageId = cageId;
    dragMoved = false;
    e.dataTransfer.setData('text/plain', cageId);
    e.dataTransfer.effectAllowed = 'move';
    // Expose every cell as a drop target while dragging (item 18)
    resetRowSpanning();
    setTimeout(function() { e.target.style.opacity = '0.4'; }, 0);
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.style.boxShadow = 'inset 0 0 0 2px #0075de';
}

function handleDragLeave(e) {
    e.currentTarget.style.boxShadow = '';
}

function handleDrop(e, row, col) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.boxShadow = '';
    var cageId = parseInt(e.dataTransfer.getData('text/plain'));
    if (!cageId) return;

    var cell = e.currentTarget;
    var tile = document.querySelector('.farm-tile[data-cage-id="' + cageId + '"]');
    if (tile) tile.style.opacity = '1';

    var existing = cell.querySelector('.farm-tile');
    if (existing) {
        if (parseInt(existing.dataset.cageId) === cageId) return; // dropped on its own cell

        // ── Swap (item 14): dropping onto an occupied cell swaps the two cages ──
        var otherId = parseInt(existing.dataset.cageId);
        var sourceCell = tile ? tile.closest('.farm-cell') : null;
        if (!sourceCell) {
            // Dragged from staging onto an occupied cell — nothing to swap into
            showDragError('Cell occupied — drop on an empty cell');
            return;
        }
        tile.remove();
        existing.remove();
        setCellOccupied(cell, cageId);
        setCellOccupied(sourceCell, otherId);
        pendingMoves[cageId] = { location_row: row, location_column: col };
        pendingMoves[otherId] = { location_row: parseInt(sourceCell.dataset.row), location_column: parseInt(sourceCell.dataset.col) };
        updateSaveButton();
        applyRowSpanning();
        return;
    }

    var sourceCell = tile ? tile.closest('.farm-cell') : null;
    if (tile) tile.remove();
    if (sourceCell) setCellEmpty(sourceCell);
    setCellOccupied(cell, cageId);
    updateStagingVisibility();

    pendingMoves[cageId] = { location_row: row, location_column: col };
    updateSaveButton();
    applyRowSpanning();
}

function handleStagingDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.boxShadow = '';
    var cageId = parseInt(e.dataTransfer.getData('text/plain'));
    if (!cageId) return;

    var tile = document.querySelector('.farm-tile[data-cage-id="' + cageId + '"]');
    if (!tile) return;
    tile.style.opacity = '1';

    var sourceCell = tile.closest('.farm-cell');
    if (!sourceCell) return; // already in staging

    tile.remove();
    setCellEmpty(sourceCell);
    addTileToStaging(cageId);

    pendingMoves[cageId] = { location_row: null, location_column: null };
    updateSaveButton();
    applyRowSpanning();
}

function clearAllCages() {
    if (document.querySelectorAll('#farmGrid .farm-tile').length === 0) return;
    confirmModal(
        'Move all cages back to the staging area? This is not applied until you click Save Layout.',
        { submit: doClearAll },
        'Clear All'
    );
}

function doClearAll() {
    document.querySelectorAll('#farmGrid .farm-tile').forEach(function(tile) {
        var cageId = parseInt(tile.dataset.cageId);
        var cell = tile.closest('.farm-cell');
        tile.remove();
        if (cell) setCellEmpty(cell);
        addTileToStaging(cageId);
        pendingMoves[cageId] = { location_row: null, location_column: null };
    });
    updateSaveButton();
    applyRowSpanning();
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

    fetch('/cages/batch-position', {
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
            // The grid DOM already matches what was saved, so no page refresh
            // is needed — just remember the persisted positions for the edit modal.
            Object.assign(savedPositions, pendingMoves);
            pendingMoves = {};
            setSavingState(false);
            showToast('Layout saved', true);
        } else {
            setSavingState(false);
            showDragError(res.data.message || 'Failed to save layout');
        }
    })
    .catch(function() {
        setSavingState(false);
        showDragError('Failed to save layout');
    });
}

function handleTileClick(e, cageId, cageCode) {
    if (dragMoved) { dragMoved = false; return; }
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
            tab.style.borderBottomColor = '#0075de';
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

(function() {
    if (window.__cagesDragendBound) return;
    window.__cagesDragendBound = true;
    document.addEventListener('dragend', function(e) {
        if (e.target.classList && e.target.classList.contains('farm-tile')) {
            e.target.style.opacity = '1';
        }
        dragMoved = true;
        // Re-apply full-row spanning once the drag interaction ends (item 18)
        if (typeof applyRowSpanning === 'function') applyRowSpanning();
    });
})();

// ── Row-spanning removed per audit (Item 1): lone cages no longer stretch.
// All cells always render at their normal width; empty placeholders visible.
function applyRowSpanning() {
    resetRowSpanning();
}

function resetRowSpanning() {
    document.querySelectorAll('#farmGrid .farm-cell').forEach(function(c) {
        c.style.gridColumn = '';
        c.style.display = '';
    });
}

applyRowSpanning();

// ── Delete Cage Modal (items 19 + 20) ─────────────────────
var deleteTargetId = null;

function openDeleteModal(id, code) {
    deleteTargetId = id;
    document.getElementById('deleteCageCode').textContent = code;
    document.getElementById('delHenCount').textContent = '…';
    document.getElementById('delSensorCount').textContent = '…';
    document.querySelector('input[name="delHensAction"][value="move"]').checked = true;
    document.getElementById('delReturnSensors').checked = true;
    document.querySelectorAll('#deleteCageModal .del-log-count').forEach(function(el) { el.textContent = '…'; });
    document.getElementById('deleteCageModal').classList.remove('hidden');
    lucide.createIcons();

    var link = document.getElementById('delPermanentLink');
    if (link) link.href = '/cages/' + id + '/confirm-delete';

    fetch('/cages/' + id + '/delete-info')
        .then(function(r) { return r.json(); })
        .then(function(info) {
            document.getElementById('delHenCount').textContent = info.hens;
            document.getElementById('delSensorCount').textContent = info.sensors;
            var typeMap = { production: info.production_logs, mortality: info.mortality_logs, feed: info.feed_logs, env: info.env_logs };
            document.querySelectorAll('#deleteCageModal .del-log-count').forEach(function(el) {
                el.textContent = typeMap[el.dataset.type] || 0;
            });
        })
        .catch(function() {
            document.querySelectorAll('#deleteCageModal .del-log-count').forEach(function(el) {
                el.textContent = '?';
            });
        });
}

function closeDeleteModal() {
    deleteTargetId = null;
    document.getElementById('deleteCageModal').classList.add('hidden');
}

function confirmCageDelete() {
    if (!deleteTargetId) return;
    var btn = document.getElementById('confirmDeleteCageBtn');
    btn.disabled = true;

    fetch('/cages/' + deleteTargetId, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({
            hens_action: document.querySelector('input[name="delHensAction"]:checked')?.value ?? 'delete',
            return_sensors: document.getElementById('delReturnSensors').checked,
            preserve_production: document.getElementById('delPreserveProduction').checked,
            preserve_mortality: document.getElementById('delPreserveMortality').checked,
            preserve_feed: document.getElementById('delPreserveFeed').checked,
            preserve_environment: document.getElementById('delPreserveEnvironment').checked,
        }),
    })
    .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
    .then(function(res) {
        btn.disabled = false;
        if (res.ok && res.data.success) {
            closeDeleteModal();
            showToast(res.data.message || 'Cage deleted', true);
            // Refresh the cage list in place — never navigates away (item 19)
            Turbo.visit(window.location.href, { action: 'replace' });
        } else {
            showDragError(res.data.message || 'Failed to delete cage');
        }
    })
    .catch(function() {
        btn.disabled = false;
        showDragError('Failed to delete cage');
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

    fetch('/cages/' + cageId + '/slots/reorder', {
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
document.addEventListener('turbo:load', function() {
    openEditModal(
        {{ $editCage->id }},
        '{{ $editCage->cage_code }}',
        {{ is_null($editCage->location_row) ? 'null' : $editCage->location_row }},
        {{ is_null($editCage->location_column) ? 'null' : $editCage->location_column }},
        {{ $editCage->rows }},
        {{ $editCage->slots_per_row }},
        {{ $editCage->max_chickens_per_slot }},
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
@endif

</script>
@endpush

{{-- Move + Remove Modals ──────────────────────────────────── --}}
@include('chickens.partials.move-modal')
@include('chickens.partials.remove-modal')
