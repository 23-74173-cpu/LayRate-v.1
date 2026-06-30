<div id="moveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <form id="moveForm" method="POST" action="{{ route('chickens.move') }}">
            @csrf

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-[#D9D9D9] bg-[#F5F6F8]">
                <h3 class="text-sm font-semibold text-[#333]">Move Chickens</h3>
                <button type="button" onclick="closeMoveModal()" class="text-[#9CA3AF] hover:text-[#333]" aria-label="Close">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-4">
                <p class="text-sm text-[#6B7280]">
                    Moving <strong id="moveCount" class="text-[#002D5E]">0</strong> hen(s)
                </p>

                {{-- Source (read-only) --}}
                <div id="moveSourceInfo" class="hidden p-3 bg-[#F5F6F8] rounded border border-[#E5E7EB] text-xs space-y-1">
                    <div class="flex gap-2">
                        <span class="text-[#9CA3AF] w-16">Source:</span>
                        <span id="moveSourceText" class="text-[#333] font-medium"></span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-[#9CA3AF] w-16">Breed:</span>
                        <span id="moveSourceBreed" class="text-[#333]"></span>
                    </div>
                </div>

                {{-- Destination Cage --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Destination Cage <span class="text-red-500">*</span></label>
                    <select id="destCageSelect" required
                            class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]"
                            onchange="loadDestSlots()">
                        <option value="">Select cage...</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}"
                                data-max="{{ $c->max_chickens_per_slot }}">
                            {{ $c->cage_code }} — {{ $c->location ?: 'No location' }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Destination Slot --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Destination Slot <span class="text-red-500">*</span></label>
                    <select name="destination_slot_id" id="destSlotSelect" required disabled
                            class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] disabled:bg-[#F5F6F8] disabled:text-[#9CA3AF]">
                        <option value="">Select cage first...</option>
                    </select>
                </div>

                {{-- Availability indicator --}}
                <div id="moveAvailability" class="hidden text-xs font-medium"></div>

                {{-- Error --}}
                <div id="moveError" class="hidden text-xs text-red-500"></div>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-3 border-t border-[#D9D9D9] flex items-center justify-end gap-3 bg-[#F5F6F8]">
                <button type="button" onclick="closeMoveModal()"
                        class="px-4 py-2 text-sm border border-[#D9D9D9] rounded hover:bg-[#E5E7EB]">
                    Cancel
                </button>
                <button type="submit" id="moveSubmitBtn" disabled
                        class="px-5 py-2 text-sm bg-[#002D5E] text-white rounded hover:bg-[#001F42] disabled:opacity-40 disabled:cursor-not-allowed">
                    Move Chickens
                </button>
            </div>

            <input type="hidden" name="hen_ids" id="moveHenIds" value="">
        </form>
    </div>
</div>

@push('scripts')
<script>
function openMoveModal(henIds, count, sourceInfo, breed) {
    document.getElementById('moveCount').textContent = count;
    document.getElementById('moveHenIds').value = henIds;

    if (sourceInfo) {
        document.getElementById('moveSourceInfo').classList.remove('hidden');
        document.getElementById('moveSourceText').textContent = sourceInfo;
        document.getElementById('moveSourceBreed').textContent = breed || '';
    } else {
        document.getElementById('moveSourceInfo').classList.add('hidden');
    }

    document.getElementById('destCageSelect').selectedIndex = 0;
    document.getElementById('destSlotSelect').innerHTML = '<option value="">Select cage first...</option>';
    document.getElementById('destSlotSelect').disabled = true;
    document.getElementById('moveAvailability').classList.add('hidden');
    document.getElementById('moveError').classList.add('hidden');
    document.getElementById('moveSubmitBtn').disabled = true;

    document.getElementById('moveModal').classList.remove('hidden');
}

function closeMoveModal() {
    document.getElementById('moveModal').classList.add('hidden');
}

function loadDestSlots() {
    const cageSelect = document.getElementById('destCageSelect');
    const slotSelect = document.getElementById('destSlotSelect');
    const availabilityEl = document.getElementById('moveAvailability');
    const submitBtn = document.getElementById('moveSubmitBtn');
    const errorEl = document.getElementById('moveError');
    const option = cageSelect.options[cageSelect.selectedIndex];
    const toMove = parseInt(document.getElementById('moveCount').textContent) || 0;

    slotSelect.innerHTML = '<option value="">Loading...</option>';
    slotSelect.disabled = true;
    availabilityEl.classList.add('hidden');
    submitBtn.disabled = true;
    errorEl.classList.add('hidden');

    if (!cageSelect.value) {
        slotSelect.innerHTML = '<option value="">Select cage first...</option>';
        return;
    }

    const cageId = cageSelect.value;
    fetch(`/cages/${cageId}/slots-json`)
        .then(r => r.json())
        .then(slots => {
            let html = '<option value="">Select slot...</option>';
            slots.forEach(slot => {
                const remaining = slot.current_occupancy !== undefined
                    ? (parseInt(option.dataset.max) - slot.current_occupancy)
                    : 0;
                const canFit = remaining >= toMove;
                html += `<option value="${slot.id}" data-remaining="${remaining}" class="${canFit ? '' : 'text-red-400'}">
                    Slot ${slot.row_number}-${slot.column_number} (#${slot.slot_number}) — ${remaining} space${remaining !== 1 ? 's' : ''} ${canFit ? '' : '(insufficient)'}
                </option>`;
            });
            slotSelect.innerHTML = html;
            slotSelect.disabled = false;
            slotSelect.onchange = checkMoveAvailability;
        })
        .catch(() => {
            slotSelect.innerHTML = '<option value="">Failed to load slots</option>';
        });
}

function checkMoveAvailability() {
    const slotSelect = document.getElementById('destSlotSelect');
    const option = slotSelect.options[slotSelect.selectedIndex];
    const availabilityEl = document.getElementById('moveAvailability');
    const submitBtn = document.getElementById('moveSubmitBtn');
    const errorEl = document.getElementById('moveError');

    availabilityEl.classList.add('hidden');
    errorEl.classList.add('hidden');

    if (!slotSelect.value) {
        submitBtn.disabled = true;
        return;
    }

    const remaining = parseInt(option.dataset.remaining) || 0;
    const toMove = parseInt(document.getElementById('moveCount').textContent) || 0;

    if (remaining >= toMove) {
        availabilityEl.classList.remove('hidden');
        availabilityEl.className = 'text-xs font-medium text-green-600';
        availabilityEl.textContent = `${remaining} space${remaining !== 1 ? 's' : ''} available — ready to move.`;
        submitBtn.disabled = false;
    } else {
        availabilityEl.classList.remove('hidden');
        availabilityEl.className = 'text-xs font-medium text-red-500';
        availabilityEl.textContent = `Insufficient capacity. Only ${remaining} space${remaining !== 1 ? 's' : ''} available but ${toMove} needed.`;
        submitBtn.disabled = true;
    }
}

window.openMoveModal = openMoveModal;
window.closeMoveModal = closeMoveModal;
</script>
@endpush
