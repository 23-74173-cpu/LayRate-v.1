@extends('layouts.app')
@section('title', 'Cage Management')

@section('content')
<main class="p-6 space-y-6" style="background-color: #f6f5f4; min-height: 100vh;">

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.125px] uppercase mb-1" style="color: #615d59;">Cage Management</p>
            <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px]" style="color: #1f1f1f;">Cages</h1>
        </div>
        <button onclick="openAddModal()"
                class="flex items-center gap-2 px-6 py-2 text-sm font-medium rounded-full text-white transition-opacity"
                style="background-color: #0075de;"
                onmouseover="this.style.opacity='0.85'"
                onmouseout="this.style.opacity='1'">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Cage
        </button>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @forelse($cages as $cage)
        @php
            $color = $cage->color;
            $colorSoft = $cage->colorSoft;
            $slotsByRow = $cage->cageSlots->groupBy('row_number');
            $sensorCount = $cage->cageSlots->where('has_sensor', true)->count();
            $occupiedCount = $cage->cageSlots->where('current_occupancy', '>', 0)->count();
            $primaryHen = $cage->hens->first();
        @endphp
        <div class="cage-card rounded-xl border overflow-hidden transition-all"
             data-cage-code="{{ $cage->cage_code }}"
             style="background-color: #ffffff; border-color: #e6e6e6; border-left: 3px solid {{ $color }};">

            {{-- Cage Header --}}
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold" style="color: {{ $color }}">{{ $cage->cage_code }}</span>
                    <span class="text-xs" style="color: #615d59;">{{ $cage->location ?: 'No location' }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: {{ $cage->is_active ? '#e8f5ec' : '#f0f0f0' }}; color: {{ $cage->is_active ? '#1f6b3a' : '#615d59' }};">
                        {{ $cage->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <button onclick="openEditModal({{ $cage->id }}, '{{ $cage->cage_code }}', '{{ addslashes($cage->location) }}', {{ $cage->rows }}, {{ $cage->slots_per_row }}, {{ $cage->max_chickens_per_slot }}, {{ $cage->is_active ? 1 : 0 }})"
                            class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Edit cage">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>
                    <a href="{{ route('cages.bulk-add') }}?cage_id={{ $cage->id }}"
                       class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Bulk add hens">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                    </a>
                    <a href="{{ route('cages.confirm-delete', $cage) }}"
                       class="p-1.5 rounded hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete cage">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </a>
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
                <div class="grid gap-1" style="grid-template-columns: repeat({{ $cage->slots_per_row }}, 1fr);">
                    @foreach($cage->cageSlots as $slot)
                    @php
                        $isSensor = $slot->has_sensor;
                        $occupancy = $slot->current_occupancy;
                        $slotBg = $isSensor ? '#d6f0e3' : ($occupancy > 0 ? '#f6f5f4' : '#ffffff');
                        $slotBorder = $isSensor ? '#2a9d6a' : '#e6e6e6';
                    @endphp
                    <button type="button"
                            onclick="expandSlot({{ $slot->id }}, {{ $cage->id }}, '{{ $cage->cage_code }}')"
                            class="slot-mini aspect-square rounded flex flex-col items-center justify-center text-xs transition-all relative"
                            style="background-color: {{ $slotBg }}; border: 1px solid {{ $slotBorder }};"
                            title="Slot {{ $slot->row_number }}-{{ $slot->column_number }}: {{ $occupancy }} hens"
                            aria-label="Slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $occupancy }} hens">

                        @if($isSensor)
                        <span class="absolute top-0 right-0 w-1.5 h-1.5 rounded-bl" style="background-color: #0075de;"></span>
                        @endif

                        @if($occupancy > 0)
                        <span class="text-[10px] font-semibold" style="color: #1f1f1f;">{{ $occupancy }}</span>
                        @else
                        <span class="text-[10px]" style="color: #d1d5db;">—</span>
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Expanded Detail Panel --}}
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
        @empty
        <div class="col-span-2 rounded-xl border p-10 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
            No cages yet. Click "+ Add Cage" to get started.
        </div>
        @endforelse
    </div>

    {{-- ── Add Cage Modal (full complexity with live preview) ── --}}
    <div id="addCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddModal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl p-6 max-h-[90vh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Battery Cage Configuration</h2>
                <button onclick="closeAddModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('cages.store') }}" id="addCageForm">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Cage Name</label>
                        <input name="cage_code" id="addCageCode" placeholder="e.g. CAGE-E" required
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Location</label>
                        <input name="location" id="addLocation" placeholder="e.g. North Wing"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
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
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Slots/Row</label>
                            <input type="number" name="slots_per_row" id="addSlotsPerRow" value="5" min="1" max="10"
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
                        <div class="border rounded-lg p-3 overflow-x-auto" style="border-color: #e6e6e6; background-color: #ffffff;">
                            <div class="flex gap-1 mb-1 pl-6" id="addPreviewColHeaders">
                                @for($c = 1; $c <= 5; $c++)
                                    <div class="w-8 text-center text-[9px]" style="color: #a39e98;">{{ $c }}</div>
                                @endfor
                            </div>
                            <div id="addPreviewGrid" class="space-y-1">
                                @for($r = 1; $r <= 3; $r++)
                                    <div class="flex gap-1">
                                        <div class="w-5 flex items-center justify-center text-[9px]" style="color: #a39e98;">{{ $r }}</div>
                                        @for($c = 1; $c <= 5; $c++)
                                            <div class="w-8 h-8 rounded border flex items-center justify-center" style="border-color: #e6e6e6; background-color: #f6f5f4;">
                                                <span class="text-[9px] font-mono" style="color: #a39e98;">{{ ($r - 1) * 5 + $c }}</span>
                                            </div>
                                        @endfor
                                    </div>
                                @endfor
                            </div>
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
                    <button type="submit"
                            class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                            style="background-color: #0075de;"
                            onmouseover="this.style.opacity='0.85'"
                            onmouseout="this.style.opacity='1'">
                        Add Cage
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Edit Cage Modal (simplified — no live preview) ── --}}
    <div id="editCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditModal()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Cage — <span id="editCageCode"></span></h2>
                <button onclick="closeEditModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <form method="POST" action="" id="editCageForm">
                @csrf @method('PUT')

                <div id="editResizeError" class="hidden mb-4 rounded-lg p-3" style="background-color: #fbe4e6; border: 1px solid #f3cdd0;">
                    <div class="flex items-start gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0" style="color: #9b1c24;"></i>
                        <p class="text-sm" style="color: #9b1c24;" id="editResizeErrorText"></p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Location</label>
                        <input name="location" id="editLocation"
                               class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                               style="border-color: #e6e6e6; color: #1f1f1f;">
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Rows</label>
                            <input type="number" name="rows" id="editRows" value="3" min="1" max="10"
                                   class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Slots/Row</label>
                            <input type="number" name="slots_per_row" id="editSlotsPerRow" value="5" min="1" max="10"
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
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="button" onclick="closeEditModal()"
                            class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                            style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                            onmouseover="this.style.backgroundColor='#f6f5f4'"
                            onmouseout="this.style.backgroundColor='transparent'">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                            style="background-color: #0075de;"
                            onmouseover="this.style.opacity='0.85'"
                            onmouseout="this.style.opacity='1'">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Confirm Modal (for delete) ── --}}
    <x-confirm-modal />

</main>
@endsection

@push('scripts')
<script>
// ── Tab Filter ────────────────────────────────────────────
function filterCage(code) {
    const cageColors = { 'CAGE-A': '#2D7D46', 'CAGE-B': '#1D4E8F', 'CAGE-C': '#C2703E', 'CAGE-D': '#6B4C8A' };
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
            if (data.hens.length === 0) {
                content.innerHTML = '<p class="text-xs text-center py-3" style="color: #a39e98;">No hens in this slot.</p>';
                return;
            }
            let html = '<div class="space-y-1.5">';
            data.hens.forEach(hen => {
                html += '<div class="flex items-center gap-3 rounded border px-3 py-2 text-xs" style="background-color: #ffffff; border-color: #e6e6e6;">';
                html += '<span class="w-24 font-mono" style="color: #615d59;">' + (hen.tag_code || '—') + '</span>';
                html += '<span class="w-32" style="color: #31302e;">' + hen.breed + '</span>';
                html += '<span class="w-12" style="color: #615d59;">' + hen.current_age_weeks + 'w</span>';
                html += '<span class="flex-1">';
                html += '<span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background-color: ' + (hen.is_active ? '#e8f5ec' : '#f0f0f0') + '; color: ' + (hen.is_active ? '#1f6b3a' : '#615d59') + ';">';
                html += (hen.is_active ? 'Active' : 'Inactive') + '</span></span>';
                html += '<div class="flex items-center gap-1">';
                html += '<button type="button" onclick="openMoveModal(\'' + hen.id + '\', 1, \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + hen.breed + '\')" class="px-1.5 py-0.5 text-[10px] border rounded hover:bg-black/5" style="border-color: #e6e6e6; color: #615d59;">Move</button>';
                html += '<button type="button" onclick="openRemoveModal(\'' + hen.id + '\', 1, \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + hen.breed + '\')" class="px-1.5 py-0.5 text-[10px] border rounded hover:bg-red-50" style="border-color: #f3cdd0; color: #9b1c24;">Remove</button>';
                html += '</div></div>';
            });
            html += '</div>';
            html += '<div class="mt-3 flex items-center gap-2">';
            const ids = data.hens.map(h => h.id).join(',');
            html += '<button type="button" onclick="openMoveModal(\'' + ids + '\', ' + data.hens.length + ', \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + (data.hens[0]?.breed || '') + '\')" class="px-3 py-1.5 text-xs border rounded transition-colors" style="border-color: #0075de; color: #0075de;" onmouseover="this.style.backgroundColor=\'#f0f7ff\'" onmouseout="this.style.backgroundColor=\'transparent\'">Move All (' + data.hens.length + ')</button>';
            html += '<button type="button" onclick="openRemoveModal(\'' + ids + '\', ' + data.hens.length + ', \'' + cageCode + ' slot ' + data.slot.slot_number + '\', \'' + (data.hens[0]?.breed || '') + '\')" class="px-3 py-1.5 text-xs border rounded hover:bg-red-50" style="border-color: #9b1c24; color: #9b1c24;">Remove All (' + data.hens.length + ')</button>';
            html += '</div>';
            content.innerHTML = html;
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
    const grid = document.getElementById('addPreviewGrid');
    const colHeaders = document.getElementById('addPreviewColHeaders');
    colHeaders.innerHTML = '';
    for (let c = 1; c <= slotsPerRow; c++) {
        const d = document.createElement('div');
        d.className = 'w-8 text-center text-[9px]';
        d.style.color = '#a39e98';
        d.textContent = c;
        colHeaders.appendChild(d);
    }
    let html = '';
    for (let r = 1; r <= rows; r++) {
        html += '<div class="flex gap-1 mb-1">';
        html += '<div class="w-5 flex items-center justify-center text-[9px]" style="color: #a39e98;">' + r + '</div>';
        for (let c = 1; c <= slotsPerRow; c++) {
            const num = (r - 1) * slotsPerRow + c;
            html += '<div class="w-8 h-8 rounded border flex items-center justify-center" style="border-color: #e6e6e6; background-color: #f6f5f4;">';
            html += '<span class="text-[9px] font-mono" style="color: #a39e98;">' + num + '</span>';
            html += '</div>';
        }
        html += '</div>';
    }
    grid.innerHTML = html;
}

// ── Edit Modal ───────────────────────────────────────────
function openEditModal(id, cageCode, location, rows, slotsPerRow, maxPerSlot, isActive) {
    document.getElementById('editCageForm').action = '/cages/' + id;
    document.getElementById('editCageCode').textContent = cageCode;
    document.getElementById('editLocation').value = location || '';
    document.getElementById('editRows').value = rows;
    document.getElementById('editSlotsPerRow').value = slotsPerRow;
    document.getElementById('editMaxPerSlot').value = maxPerSlot;
    document.getElementById('editActive').checked = isActive === 1;
    document.getElementById('editResizeError').classList.add('hidden');
    document.getElementById('editCageModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editCageModal').style.display = 'none';
}

// ── Move + Remove Modals ─────────────────────────────────
function openMoveModal(henIds, count, sourceInfo, breed) {
    document.getElementById('moveCount').textContent = count;
    document.getElementById('moveHenIds').value = henIds;
    document.getElementById('moveSourceInfo').classList.toggle('hidden', !sourceInfo);
    if (sourceInfo) {
        document.getElementById('moveSourceText').textContent = sourceInfo;
        document.getElementById('moveSourceBreed').textContent = breed || '';
    }
    document.getElementById('destCageSelect').selectedIndex = 0;
    document.getElementById('destSlotSelect').innerHTML = '<option value="">Select cage first...</option>';
    document.getElementById('destSlotSelect').disabled = true;
    document.getElementById('moveAvailability').classList.add('hidden');
    document.getElementById('moveSubmitBtn').disabled = true;
    document.getElementById('moveModal').style.display = 'flex';
}
function closeMoveModal() {
    document.getElementById('moveModal').style.display = 'none';
}
function loadDestSlots() {
    const cageSelect = document.getElementById('destCageSelect');
    const slotSelect = document.getElementById('destSlotSelect');
    const option = cageSelect.options[cageSelect.selectedIndex];
    const toMove = parseInt(document.getElementById('moveCount').textContent) || 0;
    slotSelect.innerHTML = '<option value="">Loading...</option>';
    slotSelect.disabled = true;
    if (!cageSelect.value) { slotSelect.innerHTML = '<option value="">Select cage first...</option>'; return; }
    fetch('/cages/' + cageSelect.value + '/slots-json')
        .then(r => r.json())
        .then(slots => {
            let html = '<option value="">Select slot...</option>';
            slots.forEach(slot => {
                const remaining = parseInt(option.dataset.max) - slot.current_occupancy;
                const canFit = remaining >= toMove;
                html += '<option value="' + slot.id + '" data-remaining="' + remaining + '" class="' + (canFit ? '' : 'text-red-400') + '">';
                html += 'Slot ' + slot.row_number + '-' + slot.column_number + ' (#' + slot.slot_number + ') — ' + remaining + ' space' + (remaining !== 1 ? 's' : '') + (canFit ? '' : ' (insufficient)');
                html += '</option>';
            });
            slotSelect.innerHTML = html;
            slotSelect.disabled = false;
            slotSelect.onchange = checkMoveAvailability;
        });
}
function checkMoveAvailability() {
    const slotSelect = document.getElementById('destSlotSelect');
    const option = slotSelect.options[slotSelect.selectedIndex];
    const remaining = parseInt(option.dataset.remaining) || 0;
    const toMove = parseInt(document.getElementById('moveCount').textContent) || 0;
    const availEl = document.getElementById('moveAvailability');
    const submitBtn = document.getElementById('moveSubmitBtn');
    availEl.classList.remove('hidden');
    if (remaining >= toMove) {
        availEl.className = 'text-xs font-medium';
        availEl.style.color = '#1f6b3a';
        availEl.textContent = remaining + ' space' + (remaining !== 1 ? 's' : '') + ' available — ready to move.';
        submitBtn.disabled = false;
    } else {
        availEl.className = 'text-xs font-medium';
        availEl.style.color = '#9b1c24';
        availEl.textContent = 'Insufficient capacity. Only ' + remaining + ' space' + (remaining !== 1 ? 's' : '') + ' available but ' + toMove + ' needed.';
        submitBtn.disabled = true;
    }
}
function openRemoveModal(henIds, count, sourceInfo, breed) {
    document.getElementById('removeCount').textContent = count;
    document.getElementById('removeHenIds').value = henIds;
    document.getElementById('removeSourceInfo').classList.toggle('hidden', !sourceInfo);
    if (sourceInfo) {
        document.getElementById('removeSourceText').textContent = sourceInfo;
        document.getElementById('removeSourceBreed').textContent = breed || '';
    }
    document.getElementById('recordMortality').checked = true;
    document.getElementById('mortalityFields').classList.remove('hidden');
    document.getElementById('removeReason').value = '';
    document.getElementById('removeModal').style.display = 'flex';
}
function closeRemoveModal() {
    document.getElementById('removeModal').style.display = 'none';
}

// ── Keyboard: Escape closes modals ───────────────────────
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
        closeMoveModal();
        closeRemoveModal();
    }
});

// ── Auto-open edit modal on resize error ─────────────────
@if(session('edit_cage_id') && isset($editCage))
document.addEventListener('DOMContentLoaded', function() {
    openEditModal(
        {{ $editCage->id }},
        '{{ $editCage->cage_code }}',
        '{{ addslashes($editCage->location) }}',
        {{ $editCage->rows }},
        {{ $editCage->slots_per_row }},
        {{ $editCage->max_chickens_per_slot }},
        {{ $editCage->is_active ? 1 : 0 }}
    );
    @if(session('errors') && session('errors')->has('resize'))
    const errEl = document.getElementById('editResizeError');
    const errText = document.getElementById('editResizeErrorText');
    errEl.classList.remove('hidden');
    errText.textContent = '{{ addslashes(session('errors')->first('resize')) }}';
    lucide.createIcons();
    @endif
});
@endif

lucide.createIcons();
</script>
@endpush

{{-- Move + Remove Modals ──────────────────────────────────── --}}
@include('chickens.partials.move-modal')
@include('chickens.partials.remove-modal')
