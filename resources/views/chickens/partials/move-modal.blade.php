<div id="moveModal" data-modal  data-close="closeMoveModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
    {{-- Backdrop --}}
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="event.stopPropagation(); closeMoveModal()"></div>

    {{-- Card --}}
    <div class="move-modal-card relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form id="moveForm" method="POST" action="{{ route('chickens.move') }}" data-turbo="false">
            @csrf

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Move Chickens</h2>
                <button type="button" onclick="event.stopPropagation(); closeMoveModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
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
                    <div class="flex gap-2">
                        <select name="destination_slot_id" id="destSlotSelect" required disabled
                                class="flex-1 border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] disabled:bg-[#F5F6F8] disabled:text-[#9CA3AF]">
                            <option value="">Select cage first...</option>
                        </select>
                        <button type="button" id="autoAssignBtn" onclick="autoAssignSlot()" disabled
                                class="text-xs px-3 py-2 rounded-lg font-medium text-white transition-colors disabled:opacity-50"
                                style="background-color:#0075de;" title="Auto-assign to best available slot">Auto</button>
                    </div>
                    <x-input-error name="destination_slot_id" />
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
                        <input type="date" name="transfer_date" id="moveTransferDate" value="{{ old('transfer_date', today()->toDateString()) }}"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <x-input-error name="transfer_date" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason</label>
                        <input type="text" name="transfer_reason" value="{{ old('transfer_reason') }}" placeholder="e.g. Rebalancing"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <x-input-error name="transfer_reason" />
                    </div>
                </div>

                {{-- Error --}}
                <div id="moveError" class="hidden text-xs text-red-500"></div>
            </div>

            {{-- Footer --}}
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="event.stopPropagation(); closeMoveModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">
                    Cancel
                </button>
                <x-button type="button" id="moveSubmitBtn" disabled onclick="submitMove(this.form)" class="flex-1 py-2.5">
                    Move Chickens
                </x-button>
            </div>

            <input type="hidden" name="hen_ids" id="moveHenIds" value="">
        </form>
    </div>
</div>

@push('scripts')
<script>
function getToMove() {
    var input = document.getElementById('moveCountInput');
    return Math.max(1, parseInt(input?.value) || 1);
}

function onMoveCountChange() {
    var input = document.getElementById('moveCountInput');
    var total = parseInt(document.getElementById('moveCount').textContent) || 0;
    if (input.value < 1) input.value = 1;
    if (input.value > total) input.value = total;
    loadDestSlots();
}

function openMoveModal(henIds, count, sourceInfo, breed) {
    document.getElementById('moveCount').textContent = count;
    document.getElementById('moveHenIds').value = henIds;
    var input = document.getElementById('moveCountInput');
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
    var noSlots = document.getElementById('moveNoSlots');
    if (noSlots) noSlots.classList.add('hidden');

    document.getElementById('moveModal').style.display = 'flex';
    positionMoveModalNearCagePopup();
}

// When opened from the cage info popup, anchor the move modal to the RIGHT of the
// popup instead of centering over it, so the cage slot panel stays visible.
function positionMoveModalNearCagePopup() {
    var modal = document.getElementById('moveModal');
    var popup = document.getElementById('cageInfoPopup');
    var card = modal ? modal.querySelector('.move-modal-card') : null;
    if (!modal || !card) return;
    resetMoveModalPosition();
    if (!popup || popup.classList.contains('hidden')) return;

    var vw = window.innerWidth;
    if (vw < 768) {
        // Mobile/tablet: show as a normal centered modal, lifted above the popup so
        // it is never tucked below or behind the cage slot panel.
        modal.style.zIndex = '95';
        return;
    }
    modal.style.zIndex = '';

    var pr = popup.getBoundingClientRect();
    var margin = 12;
    var vh = window.innerHeight;
    var width = Math.min(448, vw - margin * 2);

    card.style.position = 'fixed';
    card.style.width = width + 'px';
    card.style.maxHeight = (vh - margin * 2) + 'px';

    var left = pr.right + margin;
    if (left + width > vw - margin) left = pr.left - width - margin;
    if (left < margin) left = margin;

    var top = pr.top;
    if (top + card.offsetHeight > vh - margin) top = vh - card.offsetHeight - margin;
    if (top < margin) top = margin;

    card.style.left = left + 'px';
    card.style.top = top + 'px';
}

function resetMoveModalPosition() {
    var modal = document.getElementById('moveModal');
    if (modal) modal.style.zIndex = '';
    var card = document.querySelector('#moveModal .move-modal-card');
    if (!card) return;
    card.style.position = '';
    card.style.left = '';
    card.style.top = '';
    card.style.width = '';
    card.style.maxHeight = '';
}

function closeMoveModal() {
    document.getElementById('moveModal').style.display = 'none';
    resetMoveModalPosition();
}

function loadDestSlots() {
    var cageSelect = document.getElementById('destCageSelect');
    var slotSelect = document.getElementById('destSlotSelect');
    var availabilityEl = document.getElementById('moveAvailability');
    var submitBtn = document.getElementById('moveSubmitBtn');
    var errorEl = document.getElementById('moveError');
    var noSlotsEl = document.getElementById('moveNoSlots');
    var option = cageSelect.options[cageSelect.selectedIndex];
    var toMove = getToMove();

    slotSelect.innerHTML = '<option value="">Loading...</option>';
    slotSelect.disabled = true;
    availabilityEl.classList.add('hidden');
    if (noSlotsEl) noSlotsEl.classList.add('hidden');
    submitBtn.disabled = true;
    errorEl.classList.add('hidden');
    document.getElementById('autoAssignBtn').disabled = true;

    if (!cageSelect.value) {
        slotSelect.innerHTML = '<option value="">Select cage first...</option>';
        return;
    }

    var cageId = cageSelect.value;
    var maxPerSlot = parseInt(option.dataset.max) || 0;
    fetch('/cages/' + cageId + '/slots-json')
        .then(function(r) { return r.json(); })
        .then(function(slots) {
            var available = slots.filter(function(slot) {
                var remaining = maxPerSlot - (slot.current_occupancy ?? 0);
                return remaining >= toMove;
            });

            if (available.length === 0) {
                slotSelect.innerHTML = '<option value="">No slots available</option>';
                slotSelect.disabled = true;
                submitBtn.disabled = true;
                if (noSlotsEl) {
                    noSlotsEl.classList.remove('hidden');
                    noSlotsEl.textContent = toMove > 1
                        ? 'No slots with ' + toMove + ' spaces available.'
                        : 'No empty slots available.';
                }
                return;
            }

            var html = '<option value="">Select slot...</option>';
            available.forEach(function(slot) {
                var remaining = maxPerSlot - (slot.current_occupancy ?? 0);
                html += '<option value="' + slot.id + '" data-remaining="' + remaining + '">'
                    + 'Slot ' + slot.row_number + '-' + slot.column_number + ' (#' + slot.slot_number + ') \u2014 ' + remaining + ' space' + (remaining !== 1 ? 's' : '')
                    + '</option>';
            });
            slotSelect.innerHTML = html;
            slotSelect.disabled = false;
            slotSelect.onchange = checkMoveAvailability;
            document.getElementById('autoAssignBtn').disabled = false;
        })
        .catch(function() {
            slotSelect.innerHTML = '<option value="">Failed to load slots</option>';
        });
}

function checkMoveAvailability() {
    var slotSelect = document.getElementById('destSlotSelect');
    var option = slotSelect.options[slotSelect.selectedIndex];
    var availabilityEl = document.getElementById('moveAvailability');
    var submitBtn = document.getElementById('moveSubmitBtn');
    var errorEl = document.getElementById('moveError');

    availabilityEl.classList.add('hidden');
    errorEl.classList.add('hidden');

    if (!slotSelect.value) {
        submitBtn.disabled = true;
        return;
    }

    var remaining = parseInt(option.dataset.remaining) || 0;
    var toMove = getToMove();

    if (remaining >= toMove) {
        availabilityEl.classList.remove('hidden');
        availabilityEl.className = 'text-xs font-medium text-green-600';
        availabilityEl.textContent = remaining + ' space' + (remaining !== 1 ? 's' : '') + ' available \u2014 ready to move.';
        submitBtn.disabled = false;
    } else {
        availabilityEl.classList.remove('hidden');
        availabilityEl.className = 'text-xs font-medium text-red-500';
        availabilityEl.textContent = 'Insufficient capacity. Only ' + remaining + ' space' + (remaining !== 1 ? 's' : '') + ' available but ' + toMove + ' needed.';
        submitBtn.disabled = true;
    }
}

function autoAssignSlot() {
    var slotSelect = document.getElementById('destSlotSelect');
    var options = slotSelect.options;
    var bestIdx = -1;
    var bestRemaining = -1;
    for (var i = 1; i < options.length; i++) {
        var rem = parseInt(options[i].dataset.remaining) || 0;
        if (rem > bestRemaining) {
            bestRemaining = rem;
            bestIdx = i;
        }
    }
    if (bestIdx > 0) {
        slotSelect.selectedIndex = bestIdx;
        checkMoveAvailability();
    }
}

function sliceMoveHenIds() {
    var toMove = getToMove();
    var allIds = document.getElementById('moveHenIds').value;
    var sliced = allIds.split(',').slice(0, toMove).join(',');
    document.getElementById('moveHenIds').value = sliced;
    return sliced;
}

function submitMove(form) {
    var toMove = getToMove();
    var destSelect = document.getElementById('destSlotSelect');
    var destOption = destSelect.options[destSelect.selectedIndex];
    var destText = destOption ? destOption.text : 'selected slot';
    var cageSelect = document.getElementById('destCageSelect');
    var cageText = cageSelect.options[cageSelect.selectedIndex]?.text?.split(' \u2014')[0] || 'selected cage';
    var sourceText = document.getElementById('moveSourceText').textContent || 'source';

    confirmModal(
        'Move ' + toMove + ' hen(s) from ' + sourceText + ' to ' + cageText + ' ' + destText + '?',
        { submit: function() {
            sliceMoveHenIds();
            ajaxMove(form);
        } },
        'Move', 'neutral'
    );
}

function ajaxMove(form) {
    form.querySelectorAll('.move-error').forEach(function(el) { el.remove(); });
    form.querySelectorAll('.has-move-error').forEach(function(el) {
        el.classList.remove('has-move-error');
    });

    var formData = new FormData(form);
    var data = {};
    formData.forEach(function(v, k) { data[k] = v; });

    fetch(form.action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(j) { return { ok: r.ok, json: j }; });
    }).then(function(result) {
        if (result.ok) {
            closeMoveModal();
            if (typeof showNotification === 'function') {
                showNotification(result.json.message, 'success');
            }
            var frame = document.getElementById('chickens-inventory-list');
            if (frame) {
                var src = frame.src;
                frame.src = '';
                frame.src = src;
            }
            // Live-update the cages page (cards + open cage info popup) without a refresh
            if (typeof window.refreshCagesAfterMove === 'function') {
                var srcText = document.getElementById('moveSourceText').textContent || '';
                var srcParts = srcText.split(' slot ');
                var srcCode = srcParts.length === 2 ? srcParts[0].trim() : '';
                var srcSlot = srcParts.length === 2 ? parseInt(srcParts[1]) : 0;
                var destCageId = parseInt(document.getElementById('destCageSelect').value) || 0;
                var destSlotId = parseInt(document.getElementById('destSlotSelect').value) || 0;
                if (srcCode && srcSlot && destCageId && destSlotId) {
                    window.refreshCagesAfterMove({
                        srcCageCode: srcCode,
                        srcSlotNumber: srcSlot,
                        destCageId: destCageId,
                        destSlotId: destSlotId,
                        count: getToMove()
                    });
                }
            }
        } else {
            var errors = result.json.errors || {};
            Object.keys(errors).forEach(function(field) {
                var input = form.querySelector('[name="' + field + '"]');
                if (!input) {
                    var errEl = document.getElementById('moveError');
                    if (errEl && errors[field] && errors[field][0]) {
                        errEl.textContent = errors[field][0];
                        errEl.classList.remove('hidden');
                    }
                    return;
                }
                var wrapper = input.closest('div');
                if (!wrapper) return;
                wrapper.classList.add('has-move-error');
                input.style.borderColor = '#9b1c24';
                input.classList.add('ring-1');
                input.style.setProperty('--tw-ring-color', '#f3cdd0');
                var msg = errors[field][0];
                var errorEl = document.createElement('p');
                errorEl.className = 'move-error flex items-center gap-1.5 text-sm mt-1';
                errorEl.style.color = '#9b1c24';
                errorEl.innerHTML = '<i data-lucide="alert-circle" class="w-4 h-4" style="color: #9b1c24; min-width: 16px;"></i> ' + msg;
                wrapper.appendChild(errorEl);
            });
            if (window.lucide) lucide.createIcons();
        }
    }).catch(function() {
        form.submit();
    });
}

window.openMoveModal = openMoveModal;
window.closeMoveModal = closeMoveModal;

(function() {
    if (window.__moveModalEscapeBound) return;
    window.__moveModalEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('moveModal');
            if (modal && modal.style.display !== 'none') {
                e.stopPropagation();
                closeMoveModal();
            }
        }
    });
})();
</script>
@endpush