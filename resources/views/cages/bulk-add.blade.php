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
        No unplaced hens available. <a href="{{ route('chickens.index') }}" class="text-[#002D5E] underline">Register new hens</a> first.
    </div>
    @else

    {{-- Server-side validation errors --}}
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Form --}}
    <form id="placementForm" method="POST" action="{{ route('cages.bulk-add.store') }}" class="space-y-5"
          data-confirm="Place the selected hens into their assigned slots?"
          data-confirm-action="Place Hens" data-confirm-severity="neutral"
          data-loading="Placing hens and updating slot occupancy..."
          data-loading-title="Placing Hens">
        @csrf

        {{-- Hidden inputs --}}
        <input type="hidden" name="mode" id="modeInput" value="{{ old('mode', 'manual') }}">
        <input type="hidden" name="slot_ids" id="slotIdsInput" value="">
        <input type="hidden" name="hen_ids" id="henIdsInput" value="">

        {{-- ── Step 1: Select Hens ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3"
                 style="background: #F0F4FF; border-bottom: 1px solid #CCDDFF;">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-[#1D4E8F]">Step 1: Select Hens</span>
                    <label class="flex items-center gap-1 text-xs text-[#6B7280] cursor-pointer">
                        <input type="checkbox" id="selectAllHens" onchange="toggleSelectAll()"
                               class="w-3 h-3 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]">
                        Select all
                    </label>
                    <span class="text-xs text-[#6B7280]">
                        <strong id="henCount" class="text-[#002D5E]">0</strong> selected
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <select id="henBreedFilter" class="border border-[#D9D9D9] rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="filterUnplaced()">
                        <option value="">All breeds ({{ $unplacedHens->count() }})</option>
                        @foreach($unplacedBreeds as $b)
                        <option value="{{ $b }}">{{ $b }} ({{ $unplacedBreedCounts[$b] ?? 0 }} available)</option>
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
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <label class="block text-xs font-semibold text-[#1D4E8F] mb-2">Step 2: Choose Cage</label>
            <select name="cage_id" id="cageSelect" required data-slots-url-base="{{ url('cages') }}"
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
        <div id="step3Mode" class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            <div id="step3ModePicker" class="flex items-center gap-4 px-5 py-3 border-b border-[#D9D9D9]" style="background: #FAFAFA;">
                <span class="text-xs font-semibold text-[#1D4E8F]">Step 3: Placement Mode</span>
                <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                    <input type="radio" name="mode_radio" value="manual" {{ old('mode', 'manual') === 'manual' ? 'checked' : '' }} onchange="switchMode('manual')"
                           class="w-3.5 h-3.5 text-[#002D5E]">
                    Manual slot pick
                </label>
                <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                    <input type="radio" name="mode_radio" value="auto" {{ old('mode') === 'auto' ? 'checked' : '' }} onchange="switchMode('auto')"
                           class="w-3.5 h-3.5 text-[#002D5E]">
                    Auto-distribute
                </label>
            </div>

            {{-- Placement mode info --}}
            <div class="px-5 py-3 border-b border-[#D9D9D9]" style="background:#F0F4FF;">
                <div class="flex gap-3 items-start">
                    <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5" style="color:#1D4E8F;"></i>
                    <div class="text-xs leading-relaxed" style="color:#1D4E8F;">
                        <div class="font-semibold mb-1">Choose a placement mode:</div>
                        <div><strong>Manual slot pick</strong> — you click the exact slot cells where hens go. Click or click-and-drag across cells to select several.</div>
                        <div class="mt-1"><strong>Auto-distribute</strong> — the system spreads the selected hens evenly across the cage's available slots for you.</div>
                    </div>
                </div>
            </div>

            {{-- Manual mode --}}
            <div id="manualMode" class="{{ old('mode') === 'auto' ? 'hidden' : '' }} p-5">
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
            <div id="autoMode" class="{{ old('mode') === 'auto' ? '' : 'hidden' }} p-5">
            <div class="flex items-center gap-4">
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Hens per slot</label>
                        <input type="number" id="chickensPerSlot" name="chickens_per_slot" min="1" max="10" value="{{ old('chickens_per_slot', 4) }}"
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
                <span>Hens: <strong id="summaryHens" class="text-[#002D5E]">0</strong></span>
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
    let slotsLoadFailed = false;
    let currentMaxPerSlot = 0;
    let currentMode = '{{ old('mode', 'manual') }}';

    // Hens start unselected (no deep-link pre-check); sync count/hidden input.
    updateHenSelection();

    if (!window.__bulkAddTurboLoadBound) {
        window.__bulkAddTurboLoadBound = true;
        document.addEventListener('turbo:load', function() {
            var cageSelect = document.getElementById('cageSelect');
            if (cageSelect && cageSelect.value) {
                loadCageSlots();
            }
        });
    }

    // ── Hen Selection ────────────────────────────────────
    function toggleSelectAll() {
        const checked = document.getElementById('selectAllHens').checked;
        document.querySelectorAll('.hen-row').forEach(row => {
            if (row.style.display !== 'none') {
                row.querySelector('.hen-checkbox').checked = checked;
            }
        });
        updateHenSelection();
    }
    window.toggleSelectAll = toggleSelectAll;

    function filterUnplaced() {
        const breed = document.getElementById('henBreedFilter').value;
        document.querySelectorAll('.hen-row').forEach(row => {
            row.style.display = (!breed || row.dataset.breed === breed) ? '' : 'none';
        });
        syncSelectAll();
        updateHenSelection();
    }
    window.filterUnplaced = filterUnplaced;

    function syncSelectAll() {
        const allCheckboxes = document.querySelectorAll('.hen-row:not([style*=\"display: none\"]) .hen-checkbox');
        const allChecked = allCheckboxes.length > 0 && Array.from(allCheckboxes).every(cb => cb.checked);
        const selectAll = document.getElementById('selectAllHens');
        if (selectAll) selectAll.checked = allChecked;
    }

    function updateHenSelection() {
        const checked = document.querySelectorAll('.hen-checkbox:checked');
        const count = checked.length;
        const ids = Array.from(checked).map(el => el.value).join(',');
        const henCountEl = document.getElementById('henCount');
        const henIdsInput = document.getElementById('henIdsInput');
        if (henCountEl) henCountEl.textContent = count;
        if (henIdsInput) henIdsInput.value = ids;
        syncSelectAll();
        updateAutoSummary();
        validateForm();
    }
    window.updateHenSelection = updateHenSelection;

    // ── Mode Switch ───────────────────────────────────────
    function switchMode(mode) {
        currentMode = mode;
        const modeInput = document.getElementById('modeInput');
        if (modeInput) modeInput.value = mode;
        const manualMode = document.getElementById('manualMode');
        const autoMode = document.getElementById('autoMode');
        if (manualMode) manualMode.classList.toggle('hidden', mode !== 'manual');
        if (autoMode) autoMode.classList.toggle('hidden', mode !== 'auto');
        if (mode === 'auto') updateAutoSummary();
        validateForm();
    }
    window.switchMode = switchMode;

    // ── Cage / Slot Grid (manual) ─────────────────────────
    function loadCageSlots() {
        const select = document.getElementById('cageSelect');
        if (!select) return;
        if (!select.value) {
            const container = document.getElementById('slotGridContainer');
            if (container) container.innerHTML = '<p class="text-sm text-[#9CA3AF] py-8 text-center">Select a cage to see available slots.</p>';
            updateAutoSummary();
            return;
        }
        const option = select.options[select.selectedIndex];
        if (!option) return;
        const container = document.getElementById('slotGridContainer');
        if (!container) return;

        selectedSlots.clear();
        const slotIdsInput = document.getElementById('slotIdsInput');
        if (slotIdsInput) slotIdsInput.value = '';
        validateForm();

        container.innerHTML = '<p class="text-sm text-[#9CA3AF] py-8 text-center">Loading slots...</p>';

        const rows = parseInt(option.dataset.rows);
        const slotsPerRow = parseInt(option.dataset.slots);
        currentMaxPerSlot = parseInt(option.dataset.max);

        slotsLoadFailed = false;
        const cageId = select.value;
        // Absolute "/cages/..." breaks under a subfolder deployment (e.g.
        // XAMPP's /LayRate/public) — build from the select's own base URL.
        const slotsUrlBase = select.dataset.slotsUrlBase || '/cages';
        fetch(`${slotsUrlBase}/${cageId}/slots-json`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(slots => {
                cageSlots = slots;
                renderGrid(rows, slotsPerRow);
                updateAutoSummary();
            })
            .catch(err => {
                slotsLoadFailed = true;
                console.error('loadCageSlots fetch error:', err);
                container.innerHTML = '<p class="text-sm text-red-500 py-8 text-center">Failed to load slots (' + err.message + ').</p>';
                updateAutoSummary();
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
                    <span class="text-xs text-[#9CA3AF]">${occupancy}/${currentMaxPerSlot}</span>
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
    }

    // Register mouseup once — not inside renderGrid which runs multiple times
    if (!window.__bulkAddMouseUpBound) {
        window.__bulkAddMouseUpBound = true;
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
        const slotIdsInput = document.getElementById('slotIdsInput');
        if (slotIdsInput) slotIdsInput.value = Array.from(selectedSlots).join(',');
        validateForm();
    }

    // ── Auto-distribute summary ───────────────────────────
    function updateAutoSummary() {
        // Every branch below fully replaces #autoSummary's innerHTML, so
        // #autoHenCount (only present in the static placeholder markup) gets
        // destroyed on the very first call and never exists again — do not
        // guard on it or the function permanently no-ops after first render.
        const henCount = document.querySelectorAll('.hen-checkbox:checked').length;

        const select = document.getElementById('cageSelect');
        if (!select || !select.value) {
            const autoSummary = document.getElementById('autoSummary');
            if (autoSummary) autoSummary.innerHTML =
                'Will distribute <strong>' + henCount + '</strong> hens across available slots. <span class="text-[#9CA3AF]">Select a cage first.</span>';
            return;
        }

        const option = select.options[select.selectedIndex];
        if (!option) return;
        const maxPerSlot = parseInt(option.dataset.max);

        const autoSummary = document.getElementById('autoSummary');
        if (!autoSummary) return;

        if (cageSlots.length === 0) {
            if (slotsLoadFailed) {
                autoSummary.innerHTML = '<span class="text-red-500">Failed to load slot data.</span>';
            } else {
                autoSummary.innerHTML =
                    'Will distribute <strong>' + henCount + '</strong> hens across available slots. <span class="text-[#9CA3AF]">Loading slots...</span>';
            }
            return;
        }

        const perSlotInput = document.getElementById('chickensPerSlot');
        const perSlot = perSlotInput ? parseInt(perSlotInput.value) || 1 : 1;
        const available = cageSlots.filter(s => (s.current_occupancy || 0) < maxPerSlot);

        if (available.length === 0) {
            window.autoModeFits = henCount === 0;
            autoSummary.innerHTML =
                '<span class="text-red-500">No available slots in this cage.</span>';
            validateForm();
            return;
        }

        let totalCapacity = 0;
        available.forEach(s => { totalCapacity += Math.min(perSlot, maxPerSlot - (s.current_occupancy || 0)); });

        const fits = totalCapacity >= henCount;
        window.autoModeFits = fits;
        autoSummary.innerHTML =
            'Will distribute <strong>' + henCount + '</strong> hens across <strong>' + available.length + '</strong> available slot(s)' +
            (fits ? '.' : '. <span class="text-red-500">Only ' + totalCapacity + ' space(s) available — select fewer hens or reduce per-slot count.</span>');
        validateForm();
    }
    window.updateAutoSummary = updateAutoSummary;

    // ── Validation ────────────────────────────────────────
    function validateForm() {
        const henCount = document.querySelectorAll('.hen-checkbox:checked').length;
        const submitBtn = document.getElementById('submitBtn');
        const errorEl = document.getElementById('summaryError');
        const summarySlots = document.getElementById('summarySlots');
        const summaryHens = document.getElementById('summaryHens');
        const cageSelect = document.getElementById('cageSelect');

        if (!submitBtn) return;
        if (summaryHens) summaryHens.textContent = henCount;

        let valid = true;
        let errorMsg = '';

        if (henCount === 0) {
            valid = false;
            errorMsg = 'Select at least one hen.';
        }

        const cageId = cageSelect ? cageSelect.value : '';
        if (!cageId) {
            valid = false;
            if (!errorMsg) errorMsg = 'Select a cage.';
        }

        if (currentMode === 'manual') {
            const slotCount = selectedSlots.size;
            if (summarySlots) summarySlots.textContent = slotCount;
            if (slotCount === 0) {
                valid = false;
                if (!errorMsg) errorMsg = 'Select at least one slot.';
            }
        } else {
            if (summarySlots) summarySlots.textContent = 'auto';
            // window.autoModeFits is set by updateAutoSummary() — false when
            // the selected hens exceed the selected cage's available capacity.
            if (henCount > 0 && window.autoModeFits === false) {
                valid = false;
                if (!errorMsg) errorMsg = 'Selected hens exceed available slot capacity in this cage.';
            }
        }

        submitBtn.disabled = !valid;
        if (errorEl) {
            if (errorMsg) {
                errorEl.textContent = errorMsg;
                errorEl.classList.remove('hidden');
            } else {
                errorEl.classList.add('hidden');
            }
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
    var initialCage = document.getElementById('cageSelect');
    if (initialCage && initialCage.value) {
        loadCageSlots();
    }
})();
</script>
@endpush

@push('scripts')
{{-- Guided Walkthrough #2 (phase 2) — Assign hens to cages (User Manual Step 2) --}}
<script>
(function() {
    // Only run when the chickens-page walkthrough sent us here with a phase flag.
    if (sessionStorage.getItem('wt2_phase') !== 'bulkadd') return;

    var startAt = parseInt(sessionStorage.getItem('wt2_step') || '0', 10);
    sessionStorage.setItem('wt2_active', '1');

    // ── Overlay DOM ──
    if (!document.getElementById('wt2bOverlay')) {
        var ov = document.createElement('div');
        ov.id = 'wt2bOverlay';
        ov.style.cssText = 'position:fixed;inset:0;z-index:90;display:none;pointer-events:none;';
        ov.innerHTML =
            '<div id="wt2bSpotlight" style="position:fixed;border-radius:12px;border:3px solid #0075de;background:transparent;transition:all .2s ease;pointer-events:none;z-index:91;"></div>'
            + '<div id="wt2bDimT" style="position:fixed;left:0;top:0;right:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2bDimB" style="position:fixed;left:0;bottom:0;right:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2bDimL" style="position:fixed;left:0;top:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2bDimR" style="position:fixed;right:0;top:0;background:rgba(15,20,35,0.55);pointer-events:auto;z-index:90;display:none;"></div>'
            + '<div id="wt2bTooltip" style="position:fixed;max-width:340px;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px 18px;box-shadow:0 20px 50px rgba(0,0,0,0.3);z-index:92;pointer-events:none;">'
            + '<div id="wt2bStepLabel" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#0075de;margin-bottom:6px;"></div>'
            + '<div id="wt2bStepText" style="font-size:14px;line-height:1.5;color:#1f1f1f;"></div>'
            + '<button id="wt2bNext" style="display:none;margin-top:12px;padding:8px 16px;font-size:12px;font-weight:600;color:#fff;background:#002D5E;border:0;border-radius:8px;cursor:pointer;pointer-events:auto;">Next</button>'
            + '</div>'
            + '<div id="wt2bDone" style="display:none;position:fixed;inset:0;z-index:95;background:rgba(15,20,35,0.72);align-items:center;justify-content:center;pointer-events:auto;">'
            + '<div style="max-width:380px;width:calc(100% - 2rem);background:#fff;border-radius:20px;padding:32px 28px;text-align:center;box-shadow:0 30px 70px rgba(0,0,0,0.45);">'
            + '<div style="width:64px;height:64px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f5ec;color:#1f6b3a;"><i data-lucide="check" style="width:34px;height:34px;"></i></div>'
            + '<div style="font-size:20px;font-weight:700;color:#1f1f1f;">Chickens placed!</div>'
            + '<div style="font-size:14px;color:#6B7280;margin-top:8px;">Your hens are now assigned to the selected cage.</div>'
            + '<button id="wt2bDoneBtn" style="margin-top:24px;padding:11px 28px;font-size:14px;font-weight:600;color:#fff;background:#002D5E;border:0;border-radius:10px;cursor:pointer;">Done</button>'
            + '</div>'
            + '</div>'
            + '<div id="wt2bSkip" style="position:fixed;top:16px;right:16px;z-index:93;background:#fff;border:1px solid #e6e6e6;color:#615d59;font-size:13px;padding:8px 14px;border-radius:999px;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,0.15);pointer-events:auto;">End tutorial</div>';
        document.body.appendChild(ov);
    }

    var spotlight = document.getElementById('wt2bSpotlight');
    var tooltip = document.getElementById('wt2bTooltip');
    var skipBtn = document.getElementById('wt2bSkip');
    var overlay = document.getElementById('wt2bOverlay');
    var stepLabel = document.getElementById('wt2bStepLabel');
    var stepText = document.getElementById('wt2bStepText');
    var nextBtn = document.getElementById('wt2bNext');
    var doneOverlay = document.getElementById('wt2bDone');
    var doneBtn = document.getElementById('wt2bDoneBtn');
    var dimT = document.getElementById('wt2bDimT');
    var dimB = document.getElementById('wt2bDimB');
    var dimL = document.getElementById('wt2bDimL');
    var dimR = document.getElementById('wt2bDimR');
    var dims = [dimT, dimB, dimL, dimR];

    function step1Card() {
        var rows = document.querySelectorAll('.hen-row');
        var card = rows.length ? rows[0].closest('.bg-white') : null;
        return card || document.getElementById('henList') || document.getElementById('selectAllHens');
    }

    var steps = [
        {
            text: '<strong>Step 1: Select hens.</strong><br>Tick one or more hens to place, or click <em>Select all</em> to pick them all. Take your time to select multiple, then tap <em>Next</em>.',
            target: step1Card,
            done: function() { return false; },
            canProceed: function() {
                return document.querySelectorAll('.hen-checkbox:checked').length > 0;
            },
            manual: true,
            interactive: true
        },
        {
            text: '<strong>Step 2: Choose a cage.</strong><br>Select a destination cage from the dropdown.',
            target: function() {
                return document.getElementById('cageSelect');
            },
            done: function() {
                var s = document.getElementById('cageSelect');
                return !!(s && s.value);
            }
        },
        {
            text: '<strong>Step 3: Placement mode.</strong><br>Choose how to place the hens: <em>Manual slot pick</em> (you pick exact slot cells) or <em>Auto-distribute</em> (the system spreads them evenly).<br><br>Select a mode above, then tap <em>Next</em>.',
            target: function() {
                return document.getElementById('step3ModePicker')
                    || document.getElementById('step3Mode');
            },
            done: function() { return false; },
            canProceed: function() {
                return document.querySelector('input[name="mode_radio"]:checked') !== null;
            },
            manual: true,
            interactive: true
        },
        {
            text: '<strong>Assign the hens.</strong><br>Click or click-and-drag the cage slot cells to select where the hens go — select as many as you need, then tap <em>Next</em>.',
            target: function() {
                return document.getElementById('slotGridContainer') || document.getElementById('manualMode');
            },
            done: function() { return false; },
            // Only relevant in Manual slot pick mode. If Auto-distribute is
            // selected, skip this step and go straight to the next one.
            skipIf: function() {
                var auto = document.getElementById('autoMode');
                return !!(auto && !auto.classList.contains('hidden'));
            },
            canProceed: function() {
                return document.querySelectorAll('[data-slot-id].ring-2').length > 0;
            },
            manual: true,
            interactive: true
        },
        {
            text: '<strong>Place the hens.</strong><br>Review your selection, then click <em>Place Hens</em>. A confirmation popup will appear.',
            target: function() {
                return document.getElementById('submitBtn');
            },
            done: function() {
                // Advances once the confirmation modal opens.
                var m = document.getElementById('confirm-modal');
                return !!(m && (m.classList.contains('flex') || !m.classList.contains('hidden')));
            },
            // Manual so the user reviews before advancing; Next then lights the
            // confirmation step. The lit hole keeps the button clickable.
            manual: true,
            interactive: true
        },
        {
            text: '<strong>Confirm the placement.</strong><br>Review the message and click <em>Place Hens</em> to confirm, or <em>Cancel</em> to go back.',
            target: function() {
                var m = document.getElementById('confirm-modal');
                if (!m || m.classList.contains('hidden')) return null;
                return m.querySelector('.relative') || m;
            },
            done: function() { return false; },
            // Only let the user continue to the congratulations after the
            // placement is actually confirmed.
            canProceed: function() {
                return window.__wt2bPlaced === true;
            },
            manual: true,
            interactive: true
        }
    ];

    var idx = Math.min(startAt, steps.length - 1);
    var pollTimer = null;

    function positionOverlay() {
        var s = steps[idx];
        if (!s) return;
        // Skip steps that are not applicable to the current mode (e.g. the slot
        // pick step in Auto-distribute mode). Walk forward until a non-skipped
        // step is found.
        if (typeof s.skipIf === 'function' && s.skipIf()) {
            idx++;
            if (idx >= steps.length) { outro(); return; }
            sessionStorage.setItem('wt2_step', String(idx));
            positionOverlay();
            return;
        }
        var el = s.target();
        if (!el) {
            tooltip.style.display = 'none'; spotlight.style.display = 'none';
            dims.forEach(function(d) { d.style.display = 'none'; });
            return;
        }
        var r = el.getBoundingClientRect();
        var pad = 6;
        var left = r.left - pad, top = r.top - pad;
        var right = r.right + pad, bottom = r.bottom + pad;

        spotlight.style.display = 'block';
        spotlight.style.left = left + 'px'; spotlight.style.top = top + 'px';
        spotlight.style.width = (r.width + pad * 2) + 'px';
        spotlight.style.height = (r.height + pad * 2) + 'px';

        if (s.manual && !s.interactive) {
            dimT.style.display = 'block';
            dimT.style.left = '0px'; dimT.style.right = '0px'; dimT.style.top = '0px'; dimT.style.height = window.innerHeight + 'px';
            dimB.style.display = 'none'; dimL.style.display = 'none'; dimR.style.display = 'none';
        } else {
            dimT.style.display = 'block'; dimT.style.left = '0px'; dimT.style.right = '0px';
            dimT.style.top = '0px'; dimT.style.height = Math.max(0, top) + 'px';
            dimB.style.display = 'block'; dimB.style.left = '0px'; dimB.style.right = '0px';
            dimB.style.bottom = '0px'; dimB.style.height = Math.max(0, window.innerHeight - bottom) + 'px';
            dimL.style.display = 'block'; dimL.style.left = '0px'; dimL.style.top = top + 'px';
            dimL.style.width = Math.max(0, left) + 'px'; dimL.style.height = Math.max(0, bottom - top) + 'px';
            dimR.style.display = 'block'; dimR.style.right = '0px'; dimR.style.top = top + 'px';
            dimR.style.width = Math.max(0, window.innerWidth - right) + 'px';
            dimR.style.height = Math.max(0, bottom - top) + 'px';
        }

        tooltip.style.display = 'block';
        stepLabel.textContent = 'Step ' + (idx + 1) + ' of ' + steps.length;
        stepText.innerHTML = s.text;
        nextBtn.style.display = s.manual ? 'inline-block' : 'none';
        // Manual steps still need completion before proceeding (e.g. Step 1
        // requires at least one hen selected). Disable Next until satisfied.
        if (s.manual && typeof s.canProceed === 'function') {
            nextBtn.disabled = !s.canProceed();
            nextBtn.style.opacity = nextBtn.disabled ? '0.5' : '1';
            nextBtn.style.cursor = nextBtn.disabled ? 'not-allowed' : 'pointer';
        } else {
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        }
        var ttH = tooltip.offsetHeight;
        var ttTop = bottom + 14;
        if (ttTop + ttH > window.innerHeight - 20) { ttTop = Math.max(12, top - ttH - 14); }
        tooltip.style.left = Math.max(12, Math.min(left, window.innerWidth - tooltip.offsetWidth - 12)) + 'px';
        tooltip.style.top = ttTop + 'px';
    }

    function show() {
        overlay.style.display = 'block';
        skipBtn.style.display = 'block';
        positionOverlay();
        initPolling();
    }

    function advance() {
        idx++;
        if (idx >= steps.length) {
            outro();
        } else {
            sessionStorage.setItem('wt2_step', String(idx));
            positionOverlay();
        }
    }

    function outro() {
        stopPolling();
        overlay.querySelectorAll('#wt2bSpotlight, #wt2bTooltip, #wt2bSkip').forEach(function(n) { n.style.display = 'none'; });
        try { window.lucide.createIcons(); } catch (e) {}
        doneOverlay.style.display = 'flex';
    }

    function finish() {
        stopPolling();
        overlay.style.display = 'none';
        skipBtn.style.display = 'none';
        doneOverlay.style.display = 'none';
        sessionStorage.removeItem('wt2_active');
        sessionStorage.removeItem('wt2_step');
        sessionStorage.removeItem('wt2_phase');
        try {
            history.replaceState({}, '', window.location.pathname);
            if (window.showToast) showToast('Walkthrough complete', true);
        } catch (e) {}
    }

    function initPolling() {
        stopPolling();
        pollTimer = setInterval(function() {
            var s = steps[idx];
            if (!s) return;
            var el = s.target();
            if (!el) { positionOverlay(); return; }
            positionOverlay();
            if (!s.manual && s.done() && !window.__wt2bPlaced) advance();
        }, 600);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    skipBtn.addEventListener('click', function() { finish(); });
    nextBtn.addEventListener('click', function() { advance(); });
    doneBtn.addEventListener('click', function() { finish(); });
    window.addEventListener('resize', positionOverlay);
    window.addEventListener('scroll', positionOverlay, true);

    // The dim/blocking bars intercept wheel events, so forward them to the page
    // scroll container to let the user scroll while the walkthrough is open.
    document.addEventListener('wheel', function(e) {
        if (sessionStorage.getItem('wt2_active') !== '1') return;
        var sc = document.querySelector('.page-wrapper') || document.scrollingElement || document.documentElement;
        if (!sc) return;
        sc.scrollTop = Math.max(0, Math.min(sc.scrollHeight, sc.scrollTop + e.deltaY));
        e.preventDefault();
    }, { passive: false });

    // Mark placed when the user confirms in the confirmation modal. Note: the
    // confirm modal submits the placement form via native form.submit(), which
    // bypasses the submit event, so we hook the action button instead.
    var placeBtn = document.getElementById('confirm-modal-action');
    if (placeBtn && !placeBtn.__wt2bBound) {
        placeBtn.__wt2bBound = true;
        placeBtn.addEventListener('click', function() {
            window.__wt2bPlaced = true;
            // Show the congratulations right after confirming (the placement
            // then submits and the page reloads).
            outro();
            // Placement submits and the page reloads; clear tutorial state so the
            // reloaded page doesn't restart/resume the walkthrough.
            sessionStorage.removeItem('wt2_active');
            sessionStorage.removeItem('wt2_step');
            sessionStorage.removeItem('wt2_phase');
        });
    }
    // If the user cancels the confirmation, step back to the Place-hens step so
    // they can retry instead of leaving the walkthrough stuck on the modal.
    var cancelBtn = document.getElementById('confirm-modal-cancel');
    if (cancelBtn && !cancelBtn.__wt2bBound) {
        cancelBtn.__wt2bBound = true;
        cancelBtn.addEventListener('click', function() {
            if (window.__wt2bPlaced) return;
            // Revert to the "Place the hens" step (index of submitBtn step).
            var backIdx = steps.findIndex(function(s) {
                return (s.target && s.target() && s.target().id === 'submitBtn');
            });
            if (backIdx >= 0 && backIdx < idx) {
                idx = backIdx;
                sessionStorage.setItem('wt2_step', String(idx));
                positionOverlay();
            }
        });
    }

    var start = function() { show(); };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        setTimeout(start, 400);
    }
})();
</script>
@endpush
