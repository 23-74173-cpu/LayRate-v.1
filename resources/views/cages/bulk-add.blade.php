@extends('layouts.app')
@section('title', 'Place Unplaced Hens')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('chickens.index') }}" class="text-[#6B7280] hover:text-[#002D5E]" aria-label="Back to inventory">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="text-xl font-medium text-[#333333]">Place Unplaced Hens</h1>
        </div>
        <span class="text-xs text-[#9CA3AF]">Placement date is auto-set to today</span>
    </div>

    @if($unplacedHens->isEmpty())
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#9CA3AF]">
        No unplaced hens available. <a href="{{ route('chickens.index') }}" class="text-[#002D5E] underline">Register new chickens</a> first.
    </div>
    @else

    {{-- Form --}}
    <form id="placementForm" method="POST" action="{{ route('cages.bulk-add.store') }}" class="space-y-5">
        @csrf

        {{-- Hidden inputs --}}
        <input type="hidden" name="mode" id="modeInput" value="manual">
        <input type="hidden" name="slot_ids" id="slotIdsInput" value="">
        <input type="hidden" name="hen_ids" id="henIdsInput" value="{{ implode(',', $preselectedIds) }}">

        {{-- ── Step 1: Select Hens ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5"
                 style="background: #F0F4FF; border-bottom: 1px solid #CCDDFF;">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-[#1D4E8F]">Step 1: Select Hens</span>
                    <span class="text-xs text-[#6B7280]">
                        <strong id="henCount" class="text-[#002D5E]">{{ count($preselectedIds) ?: 0 }}</strong> selected
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <select id="henBreedFilter" class="border border-[#D9D9D9] rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="filterUnplaced()">
                        <option value="">All breeds</option>
                        @foreach($unplacedBreeds as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-[#9CA3AF]">{{ $unplacedHens->count() }} unplaced</span>
                </div>
            </div>
            <div class="max-h-64 overflow-y-auto divide-y divide-[#F0F0F0]" id="henList">
                @foreach($unplacedHens as $hen)
                <label class="hen-row flex items-center gap-3 px-4 py-2 hover:bg-[#FAFAFA] text-xs cursor-pointer"
                       data-breed="{{ $hen->breed }}">
                    <input type="checkbox" class="hen-checkbox w-3.5 h-3.5 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]"
                           value="{{ $hen->id }}"
                           {{ in_array($hen->id, $preselectedIds) ? 'checked' : '' }}
                           onchange="updateHenSelection()">
                    <span class="w-28 font-mono text-[#6B7280]">{{ $hen->chicken_id ?? '—' }}</span>
                    <span class="w-32 text-[#333]">{{ $hen->breed }}</span>
                    <span class="w-12 text-[#6B7280]">{{ $hen->current_age_weeks }}w</span>
                    <span class="text-[#9CA3AF]">{{ $hen->source ?? '—' }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- ── Step 2: Choose Cage ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <label class="block text-xs font-semibold text-[#1D4E8F] mb-2">Step 2: Choose Cage</label>
            <select name="cage_id" id="cageSelect" required
                    class="w-full max-w-md border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]"
                    onchange="loadCageSlots()">
                <option value="">Select cage...</option>
                @foreach($cages as $c)
                <option value="{{ $c->id }}"
                        data-rows="{{ $c->rows }}"
                        data-slots="{{ $c->slots_per_row }}"
                        data-max="{{ $c->max_chickens_per_slot }}"
                        {{ ($selectedCage && $selectedCage->id === $c->id) ? 'selected' : '' }}>
                    {{ $c->cage_code }} — {{ $c->formatted_location }} ({{ $c->rows }}×{{ $c->slots_per_row }}, {{ $c->total_capacity }} cap)
                </option>
                @endforeach
            </select>
        </div>

        {{-- ── Step 3: Choose Mode + Slot Grid ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            <div class="flex items-center gap-4 px-4 py-2.5 border-b border-[#D9D9D9]" style="background: #FAFAFA;">
                <span class="text-xs font-semibold text-[#1D4E8F]">Step 3: Placement Mode</span>
                <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                    <input type="radio" name="mode_radio" value="manual" checked onchange="switchMode('manual')"
                           class="w-3.5 h-3.5 text-[#002D5E]">
                    Manual slot pick
                </label>
                <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                    <input type="radio" name="mode_radio" value="auto" onchange="switchMode('auto')"
                           class="w-3.5 h-3.5 text-[#002D5E]">
                    Auto-distribute
                </label>
            </div>

            {{-- Manual mode --}}
            <div id="manualMode" class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-[#6B7280] uppercase tracking-wider">Click slots to select</span>
                    <div class="flex items-center gap-3 text-xs text-[#9CA3AF]">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded border-2 border-[#002D5E] bg-[#002D5E]/10"></span> selected</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-white border border-[#D9D9D9]"></span> available</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-[#F5F6F8] border border-[#D9D9D9]"></span> occupied</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-500"></span> sensor</span>
                    </div>
                </div>
                <div id="slotGridContainer" class="flex justify-center">
                    <p class="text-sm text-[#9CA3AF] py-8 text-center">Select a cage to see available slots.</p>
                </div>
            </div>

            {{-- Auto mode --}}
            <div id="autoMode" class="hidden p-4">
                <div class="flex items-center gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Hens per slot</label>
                        <input type="number" id="chickensPerSlot" min="1" max="10" value="4"
                               class="w-24 border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]"
                               oninput="updateAutoSummary()">
                    </div>
                    <div id="autoSummary" class="text-xs text-[#6B7280] pt-5">
                        Will distribute <strong id="autoHenCount">0</strong> hens across available slots.
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Summary + Submit ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4 text-sm text-[#6B7280]">
                <span>Hens: <strong id="summaryHens" class="text-[#002D5E]">{{ count($preselectedIds) ?: 0 }}</strong></span>
                <span>Slots: <strong id="summarySlots" class="text-[#002D5E]">0</strong></span>
                <span class="text-red-500 hidden" id="summaryError"></span>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="clearAll()"
                        class="px-4 py-2 text-sm border border-[#D9D9D9] rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                    Clear
                </button>
                <a href="{{ route('chickens.index') }}" class="px-4 py-2 text-sm border border-[#D9D9D9] rounded hover:bg-[#F5F6F8]">Cancel</a>
                <button type="submit" id="submitBtn" disabled
                        class="px-5 py-2 text-sm bg-[#002D5E] text-white rounded hover:bg-[#001F42] disabled:opacity-40 disabled:cursor-not-allowed">
                    Place Hens
                </button>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function() {
    let isDragging = false;
    let selectedSlots = new Set();
    let cageSlots = [];
    let currentMaxPerSlot = 0;
    let currentMode = 'manual';

    // Pre-select hens from deep-link
    updateHenSelection();

    document.addEventListener('turbo:load', function() {
        var cageSelect = document.getElementById('cageSelect');
        if (cageSelect && cageSelect.value) {
            loadCageSlots();
        }
    });

    // ── Hen Selection ────────────────────────────────────
    function filterUnplaced() {
        const breed = document.getElementById('henBreedFilter').value;
        document.querySelectorAll('.hen-row').forEach(row => {
            row.style.display = (!breed || row.dataset.breed === breed) ? '' : 'none';
        });
        updateHenSelection();
    }
    window.filterUnplaced = filterUnplaced;

    function updateHenSelection() {
        const checked = document.querySelectorAll('.hen-checkbox:checked');
        const count = checked.length;
        const ids = Array.from(checked).map(el => el.value).join(',');
        document.getElementById('henCount').textContent = count;
        document.getElementById('henIdsInput').value = ids;
        updateAutoSummary();
        validateForm();
    }
    window.updateHenSelection = updateHenSelection;

    // ── Mode Switch ───────────────────────────────────────
    function switchMode(mode) {
        currentMode = mode;
        document.getElementById('modeInput').value = mode;
        document.getElementById('manualMode').classList.toggle('hidden', mode !== 'manual');
        document.getElementById('autoMode').classList.toggle('hidden', mode !== 'auto');
        if (mode === 'auto') updateAutoSummary();
        validateForm();
    }
    window.switchMode = switchMode;

    // ── Cage / Slot Grid (manual) ─────────────────────────
    function loadCageSlots() {
        const select = document.getElementById('cageSelect');
        const option = select.options[select.selectedIndex];
        const container = document.getElementById('slotGridContainer');

        selectedSlots.clear();
        document.getElementById('slotIdsInput').value = '';
        validateForm();

        if (!select.value) {
            container.innerHTML = '<p class="text-sm text-[#9CA3AF] py-8 text-center">Select a cage to see available slots.</p>';
            updateAutoSummary();
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
                updateAutoSummary();
            })
            .catch(() => {
                container.innerHTML = '<p class="text-sm text-red-500 py-8 text-center">Failed to load slots.</p>';
            });
    }
    window.loadCageSlots = loadCageSlots;

    function renderGrid(rows, slotsPerRow) {
        const container = document.getElementById('slotGridContainer');
        let html = '<div class="flex justify-center overflow-x-auto"><div class="inline-block min-w-full">';

        html += '<div class="flex gap-1 mb-1 pl-8">';
        for (let c = 1; c <= slotsPerRow; c++) {
            html += `<div class="w-9 text-center text-xs text-[#9CA3AF]">${c}</div>`;
        }
        html += '</div>';

        for (let r = 1; r <= rows; r++) {
            html += `<div class="flex gap-1 mb-1">`;
            html += `<div class="w-7 flex items-center justify-center text-xs text-[#9CA3AF]">${r}</div>`;
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
                             data-remaining="${remaining}"
                             title="${title}">
                    ${isSensor ? '<div class="absolute top-0 right-0 w-2 h-2 rounded-bl bg-emerald-500"></div>' : ''}
                    <span class="text-xs font-mono text-[#6B7280]">${slot.slot_number}</span>
                    <span class="text-[8px] text-[#9CA3AF]">${occupancy}/${currentMaxPerSlot}</span>
                </div>`;
            }
            html += '</div>';
        }

        html += '</div></div>';
        container.innerHTML = html;

        container.querySelectorAll('[data-slot-id]').forEach(el => {
            el.addEventListener('mousedown', onMouseDown);
            el.addEventListener('mouseenter', onMouseEnter);
        });
        document.addEventListener('mouseup', onMouseUp);
    }

    function onMouseDown(e) {
        if (e.button !== 0) return;
        const el = e.currentTarget;
        if (parseInt(el.dataset.remaining) <= 0) return;
        isDragging = true;
        toggleSlot(el);
    }

    function onMouseEnter(e) {
        if (!isDragging) return;
        const el = e.currentTarget;
        if (parseInt(el.dataset.remaining) <= 0) return;
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
        document.getElementById('slotIdsInput').value = Array.from(selectedSlots).join(',');
        validateForm();
    }

    // ── Auto-distribute summary ───────────────────────────
    function updateAutoSummary() {
        const henCount = document.querySelectorAll('.hen-checkbox:checked').length;
        document.getElementById('autoHenCount').textContent = henCount;

        const select = document.getElementById('cageSelect');
        if (!select.value) {
            document.getElementById('autoSummary').innerHTML =
                'Will distribute <strong>' + henCount + '</strong> hens across available slots. <span class="text-[#9CA3AF]">Select a cage first.</span>';
            return;
        }

        const maxPerSlot = parseInt(select.options[select.selectedIndex].dataset.max);
        const perSlot = parseInt(document.getElementById('chickensPerSlot').value) || 1;
        const available = cageSlots.filter(s => (s.current_occupancy || 0) < maxPerSlot);

        if (available.length === 0) {
            document.getElementById('autoSummary').innerHTML =
                '<span class="text-red-500">No available slots in this cage.</span>';
            return;
        }

        let totalCapacity = 0;
        available.forEach(s => { totalCapacity += Math.min(perSlot, maxPerSlot - (s.current_occupancy || 0)); });

        const fits = totalCapacity >= henCount;
        document.getElementById('autoSummary').innerHTML =
            'Will distribute <strong>' + henCount + '</strong> hens across <strong>' + available.length + '</strong> available slot(s)' +
            (fits ? '.' : '. <span class="text-red-500">Only ' + totalCapacity + ' space(s) available — select fewer hens or reduce per-slot count.</span>');
    }
    window.updateAutoSummary = updateAutoSummary;

    // ── Validation ────────────────────────────────────────
    function validateForm() {
        const henCount = document.querySelectorAll('.hen-checkbox:checked').length;
        const submitBtn = document.getElementById('submitBtn');
        const errorEl = document.getElementById('summaryError');
        const summarySlots = document.getElementById('summarySlots');

        document.getElementById('summaryHens').textContent = henCount;

        let valid = true;
        let errorMsg = '';

        if (henCount === 0) {
            valid = false;
            errorMsg = 'Select at least one hen.';
        }

        const cageId = document.getElementById('cageSelect').value;
        if (!cageId) {
            valid = false;
            if (!errorMsg) errorMsg = 'Select a cage.';
        }

        if (currentMode === 'manual') {
            const slotCount = selectedSlots.size;
            summarySlots.textContent = slotCount;
            if (slotCount === 0) {
                valid = false;
                if (!errorMsg) errorMsg = 'Select at least one slot.';
            }
        } else {
            summarySlots.textContent = 'auto';
        }

        submitBtn.disabled = !valid;
        if (errorMsg) {
            errorEl.textContent = errorMsg;
            errorEl.classList.remove('hidden');
        } else {
            errorEl.classList.add('hidden');
        }
    }
    window.validateForm = validateForm;

    // ── Clear ─────────────────────────────────────────────
    function clearAll() {
        document.querySelectorAll('.hen-checkbox:checked').forEach(el => el.checked = false);
        selectedSlots.forEach(id => {
            const el = document.querySelector(`[data-slot-id="${id}"]`);
            if (el) el.classList.remove('ring-2', 'ring-[#002D5E]', 'ring-offset-1', 'bg-[#002D5E]/10');
        });
        selectedSlots.clear();
        document.getElementById('slotIdsInput').value = '';
        updateHenSelection();
        validateForm();
    }
    window.clearAll = clearAll;

    // Pre-load if cage already selected
    if (document.getElementById('cageSelect').value) {
        loadCageSlots();
    }
})();
</script>
@endpush
