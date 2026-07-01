@extends('layouts.app')
@section('title', 'Bulk Add Chickens')

@section('content')
<main class="p-5 max-w-5xl mx-auto space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('cages.index') }}" class="text-[#6B7280] hover:text-[#002D5E]" aria-label="Back to cages">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="text-xl font-medium text-[#333333]">Bulk Add Chickens</h1>
        </div>
        <span class="text-xs text-[#9CA3AF]">Placement date is auto-set to today</span>
    </div>

    {{-- Form --}}
    <form id="bulkAddForm" method="POST" action="{{ route('cages.bulk-add.store') }}" class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        @csrf

        {{-- Config Row --}}
        <div class="p-5 border-b border-[#D9D9D9] grid grid-cols-4 gap-4">

            <div>
                <label class="block text-xs font-medium text-[#6B7280] mb-1">Cage <span class="text-red-500">*</span></label>
                <select name="cage_id" id="cageSelect" required
                        class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <option value="">Select cage...</option>
                    @foreach($cages as $c)
                    <option value="{{ $c->id }}"
                            data-rows="{{ $c->rows }}"
                            data-slots="{{ $c->slots_per_row }}"
                            data-max="{{ $c->max_chickens_per_slot }}"
                            {{ ($selectedCage && $selectedCage->id === $c->id) ? 'selected' : '' }}>
                        {{ $c->cage_code }} — {{ $c->location ?: 'No location' }} ({{ $c->rows }}×{{ $c->slots_per_row }})
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-[#6B7280] mb-1">Breed <span class="text-red-500">*</span></label>
                <select name="breed" id="breedSelect" required
                        class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <option value="">Select breed...</option>
                    @foreach(['ISA Brown', 'Lohmann Brown-Classic', 'Dekalb White', 'Hy-Line Brown', 'Novogen Brown'] as $breed)
                    <option value="{{ $breed }}">{{ $breed }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-[#6B7280] mb-1">Age at Placement (weeks) <span class="text-red-500">*</span></label>
                <input type="number" name="age_weeks" id="ageWeeks" min="0" max="200" required placeholder="e.g. 20"
                       class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
            </div>

            <div>
                <label class="block text-xs font-medium text-[#6B7280] mb-1">Chickens per Slot <span class="text-red-500">*</span></label>
                <input type="number" name="chickens_per_slot" id="chickensPerSlot" min="1" max="10" value="1" required
                       class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
            </div>

            <div>
                <label class="block text-xs font-medium text-[#6B7280] mb-1">Placement Date</label>
                <input type="text" id="placementDateDisplay" readonly
                       value="{{ now()->format('Y-m-d') }}"
                       class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm bg-[#F5F6F8] text-[#6B7280]">
                <input type="hidden" name="placement_date" value="{{ now()->format('Y-m-d') }}">
            </div>
        </div>

        {{-- Slot Grid --}}
        <div class="p-5 border-b border-[#D9D9D9]">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-[#6B7280] uppercase tracking-wider">Select Slots (click or drag to select)</span>
                <div class="flex items-center gap-4 text-[10px] text-[#9CA3AF]">
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded border-2 border-[#002D5E] bg-[#002D5E]/10"></span> selected
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded bg-white border border-[#D9D9D9]"></span> available
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded bg-[#F5F6F8] border border-[#D9D9D9]"></span> occupied
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-sm bg-emerald-500"></span> sensor
                    </span>
                </div>
            </div>

            <div id="slotGridContainer" class="flex justify-center">
                <p class="text-sm text-[#9CA3AF] py-8 text-center">Select a cage to see available slots.</p>
            </div>
        </div>

        {{-- Summary + Submit --}}
        <div class="px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-6 text-sm text-[#6B7280]">
                <span>Selected: <strong id="summarySelected" class="text-[#002D5E]">0</strong> slots</span>
                <span>Chickens to add: <strong id="summaryChickens" class="text-[#002D5E]">0</strong></span>
                <span class="text-red-500 hidden" id="summaryError"></span>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="clearChoices()"
                        class="px-4 py-2 text-sm border border-[#D9D9D9] rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                    Clear
                </button>
                <a href="{{ route('cages.index') }}" class="px-4 py-2 text-sm border border-[#D9D9D9] rounded hover:bg-[#F5F6F8]">Cancel</a>
                <button type="submit" id="submitBtn" disabled
                        class="px-5 py-2 text-sm bg-[#002D5E] text-white rounded hover:bg-[#001F42] disabled:opacity-40 disabled:cursor-not-allowed">
                    Add Chickens
                </button>
            </div>
        </div>

        <input type="hidden" name="slot_ids" id="slotIdsInput" value="">
    </form>
</main>
@endsection

@push('scripts')
<script>
(function() {
    let isDragging = false;
    let selectedSlots = new Set();
    let cageSlots = [];
    let currentMaxPerSlot = 0;

    document.addEventListener('turbo:load', function() {
        var cageSelect = document.getElementById('cageSelect');
        if (cageSelect) {
            cageSelect.addEventListener('change', loadCageSlots);
        }
    });

    function loadCageSlots() {
        const select = document.getElementById('cageSelect');
        const option = select.options[select.selectedIndex];
        const container = document.getElementById('slotGridContainer');

        selectedSlots.clear();
        document.getElementById('slotIdsInput').value = '';
        updateSummary();

        if (!select.value) {
            container.innerHTML = '<p class="text-sm text-[#9CA3AF] py-8 text-center">Select a cage to see available slots.</p>';
            return;
        }

        const rows = parseInt(option.dataset.rows);
        const slotsPerRow = parseInt(option.dataset.slots);
        currentMaxPerSlot = parseInt(option.dataset.max);

        const cageId = select.value;
        fetch(`/cages/${cageId}/slots-json`)
            .then(r => r.json())
            .then(slots => {
                cageSlots = slots;
                renderGrid(rows, slotsPerRow);
            })
            .catch(() => {
                container.innerHTML = '<p class="text-sm text-red-500 py-8 text-center">Failed to load slots.</p>';
            });
    }

    function renderGrid(rows, slotsPerRow) {
        const container = document.getElementById('slotGridContainer');
        let html = '<div class="flex justify-center overflow-x-auto"><div class="inline-block min-w-full">';

        // Column headers
        html += '<div class="flex gap-1 mb-1 pl-8">';
        for (let c = 1; c <= slotsPerRow; c++) {
            html += `<div class="w-9 text-center text-[9px] text-[#9CA3AF]">${c}</div>`;
        }
        html += '</div>';

        // Rows
        for (let r = 1; r <= rows; r++) {
            html += `<div class="flex gap-1 mb-1">`;
            html += `<div class="w-7 flex items-center justify-center text-[9px] text-[#9CA3AF]">${r}</div>`;
            for (let c = 1; c <= slotsPerRow; c++) {
                const slot = cageSlots.find(s => s.row_number === r && s.column_number === c);
                if (!slot) {
                    html += '<div class="w-9 h-9"></div>';
                    continue;
                }
                const isSensor = !!slot.has_sensor;
                const occupancy = slot.current_occupancy || 0;
                const remaining = currentMaxPerSlot - occupancy;
                const isFull = remaining <= 0;
                const bgClass = isSensor ? 'bg-emerald-50 border-emerald-200' : (isFull ? 'bg-[#F5F6F8] border-[#E5E7EB]' : 'bg-white border-[#E5E7EB]');
                const selClass = selectedSlots.has(slot.id) ? 'ring-2 ring-[#002D5E] ring-offset-1 bg-[#002D5E]/10' : '';
                const cursorClass = isFull ? 'cursor-not-allowed opacity-50' : 'cursor-pointer';
                const title = isFull ? 'Slot ' + r + '-' + c + ' (at capacity)' : 'Slot ' + r + '-' + c + ' (' + remaining + ' space)';

                html += `<div class="relative w-9 h-9 rounded border ${bgClass} ${selClass} ${cursorClass} flex flex-col items-center justify-center select-none"
                             data-slot-id="${slot.id}"
                             data-row="${r}"
                             data-col="${c}"
                             data-remaining="${remaining}"
                             data-is-sensor="${isSensor ? 1 : 0}"
                             title="${title}">
                    ${isSensor ? '<div class="absolute top-0 right-0 w-2 h-2 rounded-bl bg-emerald-500"></div>' : ''}
                    <span class="text-[9px] font-mono text-[#6B7280]">${slot.slot_number}</span>
                    <span class="text-[8px] text-[#9CA3AF]">${occupancy}/${currentMaxPerSlot}</span>
                </div>`;
            }
            html += '</div>';
        }

        html += '</div></div>';
        container.innerHTML = html;

        // Attach events
        container.querySelectorAll('[data-slot-id]').forEach(el => {
            el.addEventListener('mousedown', onMouseDown);
            el.addEventListener('mouseenter', onMouseEnter);
        });
        document.addEventListener('mouseup', onMouseUp);
    }

    function onMouseDown(e) {
        if (e.button !== 0) return;
        const el = e.currentTarget;
        if (el.dataset.remaining <= 0) return;
        isDragging = true;
        toggleSlot(el);
    }

    function onMouseEnter(e) {
        if (!isDragging) return;
        const el = e.currentTarget;
        if (el.dataset.remaining <= 0) return;
        toggleSlot(el);
    }

    function onMouseUp() {
        isDragging = false;
    }

    function toggleSlot(el) {
        const id = el.dataset.slotId;
        if (selectedSlots.has(id)) {
            selectedSlots.delete(id);
            el.classList.remove('ring-2', 'ring-[#002D5E]', 'ring-offset-1', 'bg-[#002D5E]/10');
        } else {
            selectedSlots.add(id);
            el.classList.add('ring-2', 'ring-[#002D5E]', 'ring-offset-1', 'bg-[#002D5E]/10');
        }
        updateSummary();
    }

    function clearChoices() {
        document.getElementById('breedSelect').selectedIndex = 0;
        document.getElementById('ageWeeks').value = '';
        document.getElementById('chickensPerSlot').value = 1;
        selectedSlots.forEach(id => {
            const el = document.querySelector(`[data-slot-id="${id}"]`);
            if (el) {
                el.classList.remove('ring-2', 'ring-[#002D5E]', 'ring-offset-1', 'bg-[#002D5E]/10');
            }
        });
        selectedSlots.clear();
        document.getElementById('slotIdsInput').value = '';
        updateSummary();
    }
    window.clearChoices = clearChoices;

    function updateSummary() {
        const perSlot = parseInt(document.getElementById('chickensPerSlot').value) || 1;
        let chickens = 0;
        let error = '';
        let overCapacity = false;

        if (selectedSlots.size > 0) {
            selectedSlots.forEach(id => {
                const el = document.querySelector(`[data-slot-id="${id}"]`);
                if (el) {
                    const remaining = parseInt(el.dataset.remaining) || 0;
                    chickens += Math.min(perSlot, remaining);
                    if (perSlot > remaining) overCapacity = true;
                }
            });
        }

        document.getElementById('summarySelected').textContent = selectedSlots.size;
        document.getElementById('summaryChickens').textContent = chickens;

        const submitBtn = document.getElementById('submitBtn');
        const errorEl = document.getElementById('summaryError');

        if (selectedSlots.size === 0) {
            submitBtn.disabled = true;
            errorEl.classList.add('hidden');
        } else if (overCapacity) {
            submitBtn.disabled = true;
            error = 'One or more selected slots have fewer spaces than the chickens-per-slot value.';
            errorEl.textContent = error;
            errorEl.classList.remove('hidden');
        } else {
            submitBtn.disabled = false;
            errorEl.classList.add('hidden');
        }

        document.getElementById('slotIdsInput').value = Array.from(selectedSlots).join(',');
    }

    document.getElementById('ageWeeks').addEventListener('input', updateSummary);
    document.getElementById('chickensPerSlot').addEventListener('input', updateSummary);

    // Pre-load if cage was already selected
    if (document.getElementById('cageSelect').value) {
        loadCageSlots();
    }
})();
</script>
@endpush
