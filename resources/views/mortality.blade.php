@extends('layouts.app')
@section('title', 'Mortality Log')

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <x-page-header title="Mortality Log" subtitle="Record and track hen mortality per cage" />
        <span class="text-xs px-3 py-1.5 rounded-full bg-[#F8D7DA] text-[#721C24] border border-[#F5C6CB] shrink-0 ml-3">
            {{ $todayTotal }} recorded today
        </span>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- ── Record Form ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-[#6B7280]"></i>
                Record Mortality
            </h2>

            <form action="{{ route('mortality.store') }}" method="POST" class="space-y-4"
                  data-confirm="Record this mortality? The selected hen(s) will be deactivated."
                  data-confirm-action="Record" data-confirm-severity="destructive">
                @csrf

                {{-- Date --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                    <input type="date" name="log_date" required
                           value="{{ old('log_date', \App\Services\ReportingDateService::reportingDateString()) }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    @error('log_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Hens --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">SELECT HENS <span class="text-red-500">*</span></label>
                    <input type="hidden" name="hen_ids" id="mortalityHenIds" value="">
                    <button type="button" onclick="openHenPickerModal()" id="henPickerBtn"
                            class="w-full flex items-center justify-between border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-left focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C] transition-colors hover:border-[#9CA3AF]">
                        <span id="henPickerLabel" class="text-[#9CA3AF]">Click to select hens...</span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-[#9CA3AF]"></i>
                    </button>
                    @error('hen_ids')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Reason dropdown --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAUSE OF DEATH</label>
                    <select name="reason" required id="reasonSelect"
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="">Select reason…</option>
                        @foreach(\App\Models\MortalityLog::REASONS as $reason)
                        <option value="{{ $reason }}" {{ old('reason') === $reason ? 'selected' : '' }}>
                            {{ $reason }}
                        </option>
                        @endforeach
                    </select>
                    @error('reason')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Notes (always visible, below reason) --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">ADDITIONAL NOTES</label>
                    <textarea name="notes" rows="3"
                              placeholder="Describe symptoms, location in cage, or any observations…"
                              class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] resize-none focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <x-button type="submit" id="mortalitySubmitBtn" class="w-full py-2.5">
                    Save Record
                </x-button>
            </form>
        </div>

        {{-- ── Today's Summary + Recent Log ── --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Today's totals per cage --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-sm font-medium text-[#333333] mb-3">Today's Summary</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach($cages as $cage)
                    @php
                        $count = $todayByCage[$cage->cage_code] ?? 0;
                        $color = $cage->color;
                        $bg    = $count > 0 ? '#F8D7DA' : '#F5F6F8';
                        $txt   = $count > 0 ? '#721C24' : '#6B7280';
                    @endphp
                    <div class="rounded-lg border p-4" style="border-color:{{ $count > 0 ? '#F5C6CB' : '#D9D9D9' }};background:{{ $bg }}">
                        <div class="text-xs font-semibold tracking-[0.125px] uppercase mb-1" style="color:{{ $color }}">{{ $cage->cage_code }}</div>
                        <div class="text-2xl font-bold leading-none tracking-[-0.5px]" style="color:{{ $txt }}">{{ $count }}</div>
                        <div class="text-xs mt-1" style="color:{{ $txt }}">{{ $count === 1 ? 'hen' : 'hens' }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Recent log table (lazy) --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-sm font-medium text-[#333333] mb-3">Recent Records</h2>
                <turbo-frame id="mortality-logs-list" src="{{ route('mortality.logs') }}" loading="lazy">
                    @include('mortality._logs-skeleton')
                </turbo-frame>
            </div>
        </div>
    </div>

</div>

{{-- ── Hen Picker Modal ── --}}
<div id="henPickerModal" class="fixed inset-0 z-[60] hidden items-center justify-center" style="background:rgba(0,0,0,0.4);backdrop-filter:blur(2px);">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 flex flex-col" style="max-height: 80vh;">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#E6E6E6]">
            <h3 class="text-sm font-semibold text-[#333333]">Select Hens</h3>
            <button type="button" onclick="closeHenPickerModal()" class="p-1 rounded hover:bg-[#F0F0F0] transition-colors">
                <i data-lucide="x" class="w-4 h-4" style="color: #615d59;"></i>
            </button>
        </div>
        <div class="px-5 pt-4 pb-3 space-y-3 border-b border-[#E6E6E6]">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#6B7280] mb-1">Cage</label>
                    <select id="pickerCageSelect" onchange="onPickerCageChange()" class="w-full border border-[#D9D9D9] rounded-lg px-2.5 py-1.5 text-xs text-[#333333] bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="">All cages</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#6B7280] mb-1">Slot</label>
                    <select id="pickerSlotSelect" onchange="onPickerFilterChange()" class="w-full border border-[#D9D9D9] rounded-lg px-2.5 py-1.5 text-xs text-[#333333] bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]" disabled>
                        <option value="">All slots</option>
                    </select>
                </div>
            </div>
            <div class="relative">
                <i data-lucide="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5" style="color: #9CA3AF;"></i>
                <input type="text" id="pickerSearch" placeholder="Search by ID..." oninput="onPickerFilterChange()"
                       class="w-full border border-[#D9D9D9] rounded-lg pl-8 pr-2.5 py-1.5 text-xs text-[#333333] bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
            </div>
        </div>
        <div id="henPickerList" class="flex-1 overflow-y-auto px-5 py-3" style="scrollbar-width: thin;"></div>
        <div class="flex items-center justify-between px-5 py-3 border-t border-[#E6E6E6] bg-[#FAFAFA] rounded-b-xl">
            <span id="modalHenCount" class="text-xs font-semibold text-[#102A4C]">0 selected</span>
            <div class="flex gap-2">
                <button type="button" onclick="closeHenPickerModal()"
                        class="px-4 py-1.5 text-xs font-medium rounded-lg border border-[#D9D9D9] text-[#333333] hover:bg-[#F0F0F0] transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="confirmHenSelection()"
                        class="px-4 py-1.5 text-xs font-medium rounded-lg bg-[#102A4C] text-white hover:bg-[#1D4E8F] transition-colors">
                    Confirm Selection
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Edit Mortality Modal ── --}}
<div id="editMortalityModal" data-modal  data-close="closeEditMortalityModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditMortalityModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Mortality Record</h2>
            <button onclick="closeEditMortalityModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="editMortalityForm" method="POST" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                    <input type="date" name="log_date" id="editMortDate" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    <x-input-error name="log_date" />
                </div>

                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">NUMBER OF DEATHS</label>
                    <input type="number" name="count" id="editMortCount" min="1" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    <x-input-error name="count" />
                </div>

                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAUSE OF DEATH</label>
                    <select name="reason" id="editMortReason" required
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="">Select reason…</option>
                        @foreach(\App\Models\MortalityLog::REASONS as $reason)
                        <option value="{{ $reason }}">{{ $reason }}</option>
                        @endforeach
                    </select>
                    <x-input-error name="reason" />
                </div>

                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">ADDITIONAL NOTES</label>
                    <textarea name="notes" id="editMortNotes" rows="3"
                              class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] resize-none focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditMortalityModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg text-white transition-colors"
                        style="background-color: #102A4C;"
                        onmouseover="this.style.backgroundColor='#1D4E8F'"
                        onmouseout="this.style.backgroundColor='#102A4C'">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

@if(session('reopen_edit_mortality'))
@php $editMortality = \App\Models\MortalityLog::find(session('reopen_edit_mortality')); @endphp
@if($editMortality)
<x-modal-reopen modal-id="editMortalityModal" session-key="reopen_edit_mortality" guard="editMortality">
    openEditMortality(
        {{ $editMortality->id }},
        '{{ $editMortality->log_date->format('Y-m-d') }}',
        {{ $editMortality->count }},
        '{{ $editMortality->reason }}',
        '{{ addslashes($editMortality->notes ?? '') }}'
    );
</x-modal-reopen>
@endif
@endif

@push('scripts')
<script>
function toggleCageHens() {}
function updateCageAllCheck() {}

function updateModalHenCount() {
    var n = document.querySelectorAll('#henPickerList .hen-cage-check:checked').length;
    var el = document.getElementById('modalHenCount');
    if (el) el.textContent = n + ' selected';
}

var henPickerData = @json($henPickerData ?? []);

function openHenPickerModal() {
    var modal = document.getElementById('henPickerModal');
    var hidden = document.getElementById('mortalityHenIds');
    var savedIds = hidden.value ? hidden.value.split(',').map(Number) : [];

    var cageSelect = document.getElementById('pickerCageSelect');
    cageSelect.innerHTML = '<option value="">All cages</option>';
    Object.keys(henPickerData).sort().forEach(function(cage) {
        var total = 0;
        Object.values(henPickerData[cage]).forEach(function(hens) { total += hens.length; });
        var opt = document.createElement('option');
        opt.value = cage;
        opt.textContent = cage + ' (' + total + ')';
        cageSelect.appendChild(opt);
    });

    onPickerCageChange();

    document.querySelectorAll('#henPickerList .hen-cage-check').forEach(function(cb) {
        cb.checked = savedIds.indexOf(parseInt(cb.dataset.henId)) !== -1;
    });

    updateModalHenCount();
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (window.lucide) lucide.createIcons();
}

function closeHenPickerModal() {
    var modal = document.getElementById('henPickerModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function onPickerCageChange() {
    var cageCode = document.getElementById('pickerCageSelect').value;
    var slotSelect = document.getElementById('pickerSlotSelect');
    slotSelect.innerHTML = '<option value="">All slots</option>';
    slotSelect.disabled = !cageCode;

    if (cageCode && cageCode === 'Unplaced') {
        slotSelect.disabled = true;
    } else if (cageCode && henPickerData[cageCode]) {
        Object.keys(henPickerData[cageCode]).sort().forEach(function(slot) {
            var opt = document.createElement('option');
            opt.value = slot;
            opt.textContent = 'Slot ' + slot + ' (' + henPickerData[cageCode][slot].length + ')';
            slotSelect.appendChild(opt);
        });
    }

    document.getElementById('pickerSearch').value = '';
    onPickerFilterChange();
}

function onPickerFilterChange() {
    var cageCode = document.getElementById('pickerCageSelect').value;
    var slotCode = document.getElementById('pickerSlotSelect').value;
    var search = document.getElementById('pickerSearch').value.toLowerCase().trim();
    var list = document.getElementById('henPickerList');

    var hens = [];
    if (cageCode) {
        var slots = slotCode ? { [slotCode]: henPickerData[cageCode][slotCode] || [] } : (henPickerData[cageCode] || {});
        Object.entries(slots).forEach(function(entry) {
            var slot = entry[0], slotHens = entry[1];
            slotHens.forEach(function(h) { h._slot = slot; h._cage = cageCode; hens.push(h); });
        });
    } else {
        Object.entries(henPickerData).forEach(function(cageEntry) {
            var c = cageEntry[0], slots = cageEntry[1];
            Object.entries(slots).forEach(function(slotEntry) {
                var slot = slotEntry[0], slotHens = slotEntry[1];
                slotHens.forEach(function(h) { h._slot = slot; h._cage = c; hens.push(h); });
            });
        });
    }

    if (search) {
        hens = hens.filter(function(h) {
            return (h.chicken_id && h.chicken_id.toLowerCase().indexOf(search) !== -1) ||
                   (h.tag_code && h.tag_code.toLowerCase().indexOf(search) !== -1) ||
                   (h.breed && h.breed.toLowerCase().indexOf(search) !== -1);
        });
    }

    var savedIds = (document.getElementById('mortalityHenIds').value || '').split(',').map(Number).filter(Boolean);

    if (hens.length === 0) {
        list.innerHTML = '<div class="py-8 text-center text-sm text-[#9CA3AF]">No hens found.</div>';
        return;
    }

    var grouped = {};
    hens.forEach(function(h) {
        var key = h._cage;
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(h);
    });

    var html = '';
    Object.entries(grouped).forEach(function(entry) {
        var cage = entry[0], cageHens = entry[1];
        html += '<div class="mb-3 last:mb-0">';
        html += '<div class="flex items-center gap-2 px-3 py-1.5 bg-[#F5F6F8] rounded-lg mb-1">';
        html += '<input type="checkbox" class="cage-all-check rounded border-[#D9D9D9] text-[#102A4C] focus:ring-[#102A4C]/30" onchange="toggleCageHens(\'' + cage + '\', this.checked)">';
        html += '<span class="text-xs font-semibold text-[#333333]">' + cage + ' <span class="text-[#9CA3AF] font-normal">(' + cageHens.length + ')</span></span>';
        html += '</div>';
        html += '<div class="divide-y divide-[#F0F0F0] border border-[#E6E6E6] rounded-lg overflow-hidden">';
        cageHens.forEach(function(h) {
            var checked = savedIds.indexOf(h.id) !== -1 ? 'checked' : '';
            html += '<label class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-[#FAFAFA] cursor-pointer transition-colors hen-row">';
            html += '<input type="checkbox" class="hen-cage-check rounded border-[#D9D9D9] text-[#102A4C] focus:ring-[#102A4C]/30" data-hen-id="' + h.id + '" ' + checked + ' onchange="updateModalHenCount()">';
            html += '<span class="text-xs text-[#333333]">' + h.chicken_id + '</span>';
            if (h.tag_code) html += ' <span class="text-[10px] text-[#9CA3AF]">(' + h.tag_code + ')</span>';
            if (h._slot && h._slot !== '—') html += '<span class="text-[10px] text-[#9CA3AF]">Slot ' + h._slot + '</span>';
            html += '<span class="text-[10px] text-[#9CA3AF] ml-auto">' + h.breed + '</span>';
            html += '</label>';
        });
        html += '</div></div>';
    });

    list.innerHTML = html;
    updateModalHenCount();
}

function confirmHenSelection() {
    var checked = document.querySelectorAll('#henPickerList .hen-cage-check:checked');
    var henIds = Array.from(checked).map(function(cb) { return cb.dataset.henId; });

    var hidden = document.getElementById('mortalityHenIds');
    hidden.value = henIds.join(',');

    var label = document.getElementById('henPickerLabel');
    if (henIds.length === 0) {
        label.textContent = 'Click to select hens...';
        label.classList.add('text-[#9CA3AF]');
        label.classList.remove('text-[#333333]');
    } else {
        label.textContent = henIds.length + ' hen(s) selected';
        label.classList.remove('text-[#9CA3AF]');
        label.classList.add('text-[#333333]');
    }

    closeHenPickerModal();
}

function openEditMortality(id, date, count, reason, notes) {
    document.getElementById('editMortalityForm').action = '/mortality/' + id;
    document.getElementById('editMortDate').value = date;
    document.getElementById('editMortCount').value = count;
    document.getElementById('editMortReason').value = reason;
    document.getElementById('editMortNotes').value = notes || '';
    document.getElementById('editMortalityModal').style.display = 'flex';
    lucide.createIcons();
}

function closeEditMortalityModal() {
    document.getElementById('editMortalityModal').style.display = 'none';
}

(function() {
    if (window.__mortalityEscapeBound) return;
    window.__mortalityEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditMortalityModal();
            closeHenPickerModal();
        }
    });
})();
</script>
@endpush
@endsection
