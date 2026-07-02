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

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Record Form ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-[#6B7280]"></i>
                Record Mortality
            </h2>

            <form action="{{ route('mortality.store') }}" method="POST" class="space-y-4">
                @csrf

                {{-- Cage --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                    <select name="cage_id" required
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="">Select cage…</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}" {{ (old('cage_id') ?: ($preselectedCageId ?? 0)) == $cage->id ? 'selected' : '' }}>
                            {{ $cage->cage_code }} — {{ $cage->location }}
                        </option>
                        @endforeach
                    </select>
                    @error('cage_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Date --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                    <input type="date" name="log_date" required
                           value="{{ old('log_date', date('Y-m-d')) }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    @error('log_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Count --}}
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">NUMBER OF DEATHS</label>
                    <input type="number" name="count" min="1" required
                           value="{{ old('count', 1) }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    @error('count')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
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

                <button type="submit"
                        class="w-full bg-[#102A4C] text-white py-2.5 rounded-lg text-sm font-medium hover:bg-[#1D4E8F] transition-colors">
                    Save Record
                </button>
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
                    <div class="rounded-lg border p-3" style="border-color:{{ $count > 0 ? '#F5C6CB' : '#D9D9D9' }};background:{{ $bg }}">
                        <div class="text-xs tracking-wider mb-1" style="color:{{ $color }}">{{ $cage->cage_code }}</div>
                        <div class="text-2xl font-semibold" style="color:{{ $txt }}">{{ $count }}</div>
                        <div class="text-xs mt-1" style="color:{{ $txt }}">{{ $count === 1 ? 'hen' : 'hens' }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Recent log table --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-sm font-medium text-[#333333] mb-3">Recent Records</h2>
                @if($logs->isEmpty())
                <div class="py-8 text-center text-sm text-[#6B7280]">No mortality records yet.</div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-[#D9D9D9]">
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">DATE</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">CAGE</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">COUNT</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">REASON</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">NOTES</th>
                                <th class="py-2 text-[10px] tracking-wider text-[#6B7280] font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F5F6F8]">
                            @foreach($logs as $log)
                            @php
                                $reasonColors = [
                                    'Disease'    => ['#F8D7DA','#721C24'],
                                    'Heat Stress'=> ['#FFF3CD','#856404'],
                                    'Injury'     => ['#FFF3CD','#856404'],
                                    'Predator'   => ['#F8D7DA','#721C24'],
                                    'Unknown'    => ['#F5F6F8','#6B7280'],
                                    'Other'      => ['#F5F6F8','#6B7280'],
                                ];
                                [$rBg,$rTxt] = $reasonColors[$log->reason] ?? ['#F5F6F8','#6B7280'];
                            @endphp
                            <tr class="hover:bg-[#F5F6F8]/50">
                                <td class="py-2.5 pr-3 text-[#6B7280]">{{ $log->log_date->format('M d, Y') }}</td>
                                <td class="py-2.5 pr-3">
                                    <span class="font-medium" style="color:{{ $log->cage->color }}">{{ $log->cage->cage_code }}</span>
                                </td>
                                <td class="py-2.5 pr-3 font-semibold text-[#333333]">{{ $log->count }}</td>
                                <td class="py-2.5 pr-3">
                                    <span class="px-2 py-0.5 rounded text-[10px]" style="background:{{ $rBg }};color:{{ $rTxt }}">
                                        {{ $log->reason }}
                                    </span>
                                </td>
                                <td class="py-2.5 pr-3 text-[#6B7280] max-w-[200px] truncate">
                                    {{ $log->notes ?: '—' }}
                                </td>
                                <td class="py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button onclick="openEditMortality({{ $log->id }}, '{{ $log->log_date->format('Y-m-d') }}', {{ $log->count }}, '{{ addslashes($log->reason) }}', '{{ addslashes($log->notes ?? '') }}')"
                                                class="p-1.5 hover:bg-black/5 rounded-full transition-colors" style="color: #a39e98;" aria-label="Edit record">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <form action="{{ route('mortality.destroy', $log) }}" method="POST"
                                              onsubmit="return confirm('Delete this record?')">
                                            @csrf @method('DELETE')
                                            <button class="p-1.5 hover:bg-red-50 rounded-full transition-colors" style="color: #a39e98;" aria-label="Delete record">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($logs->hasPages())
                    <div class="px-4 py-3 border-t border-[#F0F0F0] flex items-center justify-between text-xs text-[#6B7280]">
                        <span>Showing {{ $logs->firstItem() }}-{{ $logs->lastItem() }} of {{ $logs->total() }}</span>
                        <div class="flex items-center gap-1">
                            @if($logs->onFirstPage())
                            <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                            @else
                            <a href="{{ $logs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                            @endif
                            @foreach($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                                @if($page == $logs->currentPage())
                                <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                                @elseif($page >= $logs->currentPage() - 1 && $page <= $logs->currentPage() + 1)
                                <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                                @endif
                            @endforeach
                            @if($logs->hasMorePages())
                            <a href="{{ $logs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                            @else
                            <span class="px-2 py-1 text-[#9CA3AF]">Next ›</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ── Edit Mortality Modal ── --}}
<div id="editMortalityModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditMortalityModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
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
                </div>

                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">NUMBER OF DEATHS</label>
                    <input type="number" name="count" id="editMortCount" min="1" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
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
                </div>

                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">ADDITIONAL NOTES</label>
                    <textarea name="notes" id="editMortNotes" rows="3"
                              class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] resize-none focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]"></textarea>
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
@push('scripts')
<script>
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
