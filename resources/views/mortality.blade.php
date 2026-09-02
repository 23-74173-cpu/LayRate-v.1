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
                  data-confirm="Record this mortality? The affected hen(s) will be deactivated and slot occupancy updated."
                  data-confirm-action="Record" data-confirm-severity="destructive">
                @csrf

                {{-- Cage --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                    <select name="cage_id" id="mortalityCageSelect" required
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="">Select cage…</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}" data-active-hens="{{ $cage->active_hens_count }}" {{ (old('cage_id') ?: ($preselectedCageId ?? 0)) == $cage->id ? 'selected' : '' }}>
                            {{ $cage->cage_code }} — {{ $cage->formatted_location }}
                        </option>
                        @endforeach
                    </select>
                    @error('cage_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

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
                    <div id="henPicker" class="border border-[#D9D9D9] rounded-lg max-h-56 overflow-y-auto bg-white" style="scrollbar-width: thin;">
                        @forelse($activeHensByCage as $cageCode => $hens)
                        <div class="hen-cage-group">
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-[#F5F6F8] border-b border-[#E6E6E6] sticky top-0 z-10">
                                <input type="checkbox" id="cageAll_{{ $cageCode }}" class="cage-all-check rounded border-[#D9D9D9] text-[#102A4C] focus:ring-[#102A4C]/30"
                                       onchange="toggleCageHens('{{ $cageCode }}', this.checked)">
                                <label for="cageAll_{{ $cageCode }}" class="text-xs font-semibold text-[#333333] cursor-pointer">{{ $cageCode }} <span class="text-[#9CA3AF] font-normal">({{ $hens->count() }})</span></label>
                            </div>
                            <div class="divide-y divide-[#F0F0F0]">
                                @foreach($hens as $hen)
                                <label class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-[#FAFAFA] cursor-pointer transition-colors hen-row" data-cage="{{ $cageCode }}">
                                    <input type="checkbox" name="hen_ids[]" value="{{ $hen->id }}"
                                           class="hen-cage-check rounded border-[#D9D9D9] text-[#102A4C] focus:ring-[#102A4C]/30"
                                           onchange="updateCageAllCheck('{{ $cageCode }}'); updateHenCount();">
                                    <span class="text-xs text-[#333333]">{{ $hen->chicken_id }}</span>
                                    @if($hen->tag_code)
                                    <span class="text-[10px] text-[#9CA3AF]">({{ $hen->tag_code }})</span>
                                    @endif
                                    <span class="text-[10px] text-[#9CA3AF] ml-auto">{{ $hen->breed }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @empty
                        <div class="px-3 py-6 text-center text-sm text-[#9CA3AF]">No active hens available.</div>
                        @endforelse
                    </div>
                    <div class="flex items-center justify-between mt-1.5">
                        <p class="text-xs text-[#9CA3AF]">Select individual hens across cages</p>
                        <span id="henCount" class="text-xs font-semibold text-[#102A4C]">0 selected</span>
                    </div>
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
function toggleCageHens(cageCode, checked) {
    document.querySelectorAll('#henPicker .hen-row[data-cage="' + cageCode + '"] .hen-cage-check').forEach(function(cb) {
        cb.checked = checked;
    });
    updateHenCount();
}

function updateCageAllCheck(cageCode) {
    var all = document.querySelectorAll('#henPicker .hen-row[data-cage="' + cageCode + '"] .hen-cage-check');
    var checked = document.querySelectorAll('#henPicker .hen-row[data-cage="' + cageCode + '"] .hen-cage-check:checked');
    var toggle = document.getElementById('cageAll_' + cageCode);
    if (toggle) toggle.checked = all.length > 0 && all.length === checked.length;
    updateHenCount();
}

function updateHenCount() {
    var n = document.querySelectorAll('#henPicker .hen-cage-check:checked').length;
    var el = document.getElementById('henCount');
    if (el) el.textContent = n + ' selected';
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
        }
    });
})();
</script>
@endpush
@endsection
