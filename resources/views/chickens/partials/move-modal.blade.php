<div id="moveModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeMoveModal()"></div>

    {{-- Card --}}
    <div class="relative w-full max-w-md rounded-2xl p-6 overflow-hidden" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form id="moveForm" method="POST" action="{{ route('chickens.move') }}" onsubmit="return sliceMoveHenIds()">
            @csrf

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Move Chickens</h2>
                <button type="button" onclick="closeMoveModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <p class="text-sm text-[#6B7280]">
                        Moving <strong id="moveCount" class="text-[#002D5E]">0</strong> selected
                    </p>
                    <label class="flex items-center gap-1 text-sm text-[#6B7280]">
                        · move
                        <input type="number" name="move_count" id="moveCountInput" value="0" min="1"
                               class="w-14 border border-[#D9D9D9] rounded px-2 py-1 text-sm text-center focus:outline-none focus:ring-1 focus:ring-[#002D5E]"
                               oninput="onMoveCountChange()">
                        hen(s)
                    </label>
                </div>

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
                    @php
                        $availableCages = $cages->filter(fn($c) => $c->cageSlots->contains(fn($s) => $s->remaining > 0));
                    @endphp
                    @if($availableCages->isEmpty())
                    <div id="moveNoCages" class="p-3 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-700">
                        No cages with available space.
                    </div>
                    <select id="destCageSelect" required disabled class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] disabled:bg-[#F5F6F8] disabled:text-[#9CA3AF] mt-2">
                        <option value="">No cages available</option>
                    </select>
                    @else
                    <select id="destCageSelect" required
                            class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]"
                            onchange="loadDestSlots()">
                        <option value="">Select cage...</option>
                        @foreach($availableCages as $c)
                        <option value="{{ $c->id }}"
                                data-max="{{ $c->max_chickens_per_slot }}">
                            {{ $c->cage_code }} — {{ $c->formatted_location }}
                        </option>
                        @endforeach
                    </select>
                    @endif
                </div>

                {{-- Destination Slot --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Destination Slot <span class="text-red-500">*</span></label>
                    <select name="destination_slot_id" id="destSlotSelect" required disabled
                            class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] disabled:bg-[#F5F6F8] disabled:text-[#9CA3AF]">
                        <option value="">Select cage first...</option>
                    </select>
                    <div id="moveNoSlots" class="hidden mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-700">
                        No slots with enough space available.
                    </div>
                </div>

                {{-- Availability indicator --}}
                <div id="moveAvailability" class="hidden text-xs font-medium"></div>

                {{-- Transfer details --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Transfer Date</label>
                        <input type="date" name="transfer_date" id="moveTransferDate" value="{{ today()->toDateString() }}"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason</label>
                        <input type="text" name="transfer_reason" placeholder="e.g. Rebalancing"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    </div>
                </div>

                {{-- Error --}}
                <div id="moveError" class="hidden text-xs text-red-500"></div>
            </div>

            {{-- Footer --}}
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeMoveModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <button type="submit" id="moveSubmitBtn" disabled
                        class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
                        style="background-color: #0075de;"
                        onmouseover="if(!this.disabled) this.style.opacity='0.85'"
                        onmouseout="this.style.opacity='1'">
                    Move Chickens
                </button>
            </div>

            <input type="hidden" name="hen_ids" id="moveHenIds" value="">
        </form>
    </div>
</div>

@push('scripts')
<script>
function getToMove() {
    const input = document.getElementById('moveCountInput');
    return Math.max(1, parseInt(input?.value) || 1);
}

function onMoveCountChange() {
    const input = document.getElementById('moveCountInput');
    const total = parseInt(document.getElementById('moveCount').textContent) || 0;
    if (input.value < 1) input.value = 1;
    if (input.value > total) input.value = total;
    loadDestSlots();
}

function openMoveModal(henIds, count, sourceInfo, breed) {
    document.getElementById('moveCount').textContent = count;
    document.getElementById('moveHenIds').value = henIds;
    const input = document.getElementById('moveCountInput');
    input.value = count;
    input.max = count;

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
    const noSlots = document.getElementById('moveNoSlots');
    if (noSlots) noSlots.classList.add('hidden');

    document.getElementById('moveModal').classList.remove('hidden');
    document.getElementById('moveModal').classList.add('flex');
}
function closeMoveModal() {
    document.getElementById('moveModal').classList.add('hidden');
    document.getElementById('moveModal').classList.remove('flex');
}

function loadDestSlots() {
    const cageSelect = document.getElementById('destCageSelect');
    const slotSelect = document.getElementById('destSlotSelect');
    const availabilityEl = document.getElementById('moveAvailability');
    const submitBtn = document.getElementById('moveSubmitBtn');
    const errorEl = document.getElementById('moveError');
    const noSlotsEl = document.getElementById('moveNoSlots');
    const option = cageSelect.options[cageSelect.selectedIndex];
    const toMove = getToMove();

    slotSelect.innerHTML = '<option value="">Loading...</option>';
    slotSelect.disabled = true;
    availabilityEl.classList.add('hidden');
    noSlotsEl?.classList.add('hidden');
    submitBtn.disabled = true;
    errorEl.classList.add('hidden');

    if (!cageSelect.value) {
        slotSelect.innerHTML = '<option value="">Select cage first...</option>';
        return;
    }

    const cageId = cageSelect.value;
    const maxPerSlot = parseInt(option.dataset.max) || 0;
    fetch(`/cages/${cageId}/slots-json`)
        .then(r => r.json())
        .then(slots => {
            const available = slots.filter(slot => {
                const remaining = maxPerSlot - (slot.current_occupancy ?? 0);
                return remaining >= toMove;
            });

            if (available.length === 0) {
                slotSelect.innerHTML = '<option value="">No slots available</option>';
                slotSelect.disabled = true;
                submitBtn.disabled = true;
                if (noSlotsEl) {
                    noSlotsEl.classList.remove('hidden');
                    noSlotsEl.textContent = toMove > 1
                        ? `No slots with ${toMove} spaces available.`
                        : 'No empty slots available.';
                }
                return;
            }

            let html = '<option value="">Select slot...</option>';
            available.forEach(slot => {
                const remaining = maxPerSlot - (slot.current_occupancy ?? 0);
                html += `<option value="${slot.id}" data-remaining="${remaining}">
                    Slot ${slot.row_number}-${slot.column_number} (#${slot.slot_number}) — ${remaining} space${remaining !== 1 ? 's' : ''}
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
    const toMove = getToMove();

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

function sliceMoveHenIds() {
    const toMove = getToMove();
    const allIds = document.getElementById('moveHenIds').value;
    const sliced = allIds.split(',').slice(0, toMove).join(',');
    document.getElementById('moveHenIds').value = sliced;
    return true;
}

window.openMoveModal = openMoveModal;
window.closeMoveModal = closeMoveModal;

// Escape key closes modal
(function() {
    if (window.__moveModalEscapeBound) return;
    window.__moveModalEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMoveModal();
    });
})();
</script>
@endpush
