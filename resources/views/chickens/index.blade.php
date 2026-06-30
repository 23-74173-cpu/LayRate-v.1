@extends('layouts.app')
@section('title', 'Chicken Inventory')

@section('content')
<main class="p-5 space-y-5">

    {{-- Header + Tabs --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-medium text-[#333333]">Chickens</h1>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-[#D9D9D9]">
        <nav class="flex gap-6">
            <button onclick="switchTab('inventory')" id="tabInventory"
                    class="pb-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $tab === 'inventory' ? 'border-[#002D5E] text-[#002D5E]' : 'border-transparent text-[#6B7280] hover:text-[#333]' }}">
                <i data-lucide="list" class="w-4 h-4 inline mr-1"></i> Inventory
            </button>
            <button onclick="switchTab('mortality')" id="tabMortality"
                    class="pb-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $tab === 'mortality' ? 'border-[#002D5E] text-[#002D5E]' : 'border-transparent text-[#6B7280] hover:text-[#333]' }}">
                <i data-lucide="skull" class="w-4 h-4 inline mr-1"></i> Mortality
            </button>
        </nav>
    </div>

    {{-- ============================================ --}}
    {{-- INVENTORY TAB --}}
    {{-- ============================================ --}}
    <div id="panelInventory" class="{{ $tab !== 'inventory' ? 'hidden' : '' }}">

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('chickens.index') }}" id="inventoryFilterForm" class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <input type="hidden" name="tab" value="inventory" id="filterTabInput">
            <div class="flex flex-wrap items-end gap-3">

                {{-- Status Toggle --}}
                <div class="flex items-center gap-1 border border-[#D9D9D9] rounded overflow-hidden">
                    @foreach(['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $val => $label)
                    <label class="px-3 py-1.5 text-xs cursor-pointer transition-colors {{ $isActive === $val ? 'bg-[#002D5E] text-white' : 'bg-white text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                        <input type="radio" name="status" value="{{ $val }}" class="hidden" onchange="this.form.submit()" {{ $isActive === $val ? 'checked' : '' }}>
                        {{ $label }}
                    </label>
                    @endforeach
                </div>

                {{-- Cage Filter --}}
                <div>
                    <label class="block text-[10px] font-medium text-[#9CA3AF] mb-1">Cage</label>
                    <select name="cage_id" class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="this.form.submit()">
                        <option value="">All Cages</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}" {{ $cageId == $c->id ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Breed Filter --}}
                <div>
                    <label class="block text-[10px] font-medium text-[#9CA3AF] mb-1">Breed</label>
                    <select name="breed" class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E]" onchange="this.form.submit()">
                        <option value="">All Breeds</option>
                        @foreach($breeds as $b)
                        <option value="{{ $b }}" {{ $breed == $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div>
                    <label class="block text-[10px] font-medium text-[#9CA3AF] mb-1">Tag Code</label>
                    <div class="flex gap-1">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search tag..."
                               class="border border-[#D9D9D9] rounded px-2 py-1.5 text-xs w-36 focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <button type="submit" class="px-2 py-1.5 bg-[#002D5E] text-white rounded text-xs hover:bg-[#001F42]">
                            <i data-lucide="search" class="w-3 h-3"></i>
                        </button>
                    </div>
                </div>

                @if($cageId || $breed || $search)
                <a href="{{ route('chickens.index', ['tab' => 'inventory']) }}"
                   class="px-2 py-1.5 text-xs text-red-500 hover:underline">Clear filters</a>
                @endif
            </div>
        </form>

        {{-- Bulk Action Bar --}}
        <div id="bulkActionBar" class="hidden bg-white rounded-lg border border-[#D9D9D9] px-4 py-3 flex items-center justify-between">
            <span class="text-sm text-[#6B7280]">
                <strong id="bulkCount" class="text-[#002D5E]">0</strong> hen(s) selected
            </span>
            <div class="flex items-center gap-2">
                <button type="button" onclick="bulkMove()"
                        class="px-3 py-1.5 text-xs border border-[#002D5E] text-[#002D5E] rounded hover:bg-[#002D5E]/5">
                    <i data-lucide="arrow-right" class="w-3 h-3 inline"></i> Move Selected
                </button>
                <button type="button" onclick="bulkRemove()"
                        class="px-3 py-1.5 text-xs border border-red-400 text-red-500 rounded hover:bg-red-50">
                    <i data-lucide="trash-2" class="w-3 h-3 inline"></i> Remove Selected
                </button>
            </div>
        </div>

        {{-- Hen List --}}
        <div class="space-y-3">
            @forelse($hensByCage as $cageId => $hensGroup)
                @php
                    $cage = $hensGroup->first()->cage;
                    $slotsInCage = $hensGroup->groupBy(fn($h) => $h->cageSlot?->id)->filter()->sortBy(fn($g) => $g->first()->cageSlot?->slot_number);
                @endphp

                <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
                    {{-- Cage header --}}
                    <div class="flex items-center justify-between px-4 py-2.5" style="background: {{ $cage->color }}10; border-bottom: 1px solid {{ $cage->color }}30">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold" style="color: {{ $cage->color }}">{{ $cage->cage_code }}</span>
                            <span class="text-xs text-[#6B7280]">{{ $cage->location ?: 'No location' }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/80 text-[#6B7280]">
                                {{ $hensGroup->where('is_active', 1)->count() }} active
                                @if($hensGroup->where('is_active', 0)->count() > 0)
                                / {{ $hensGroup->where('is_active', 0)->count() }} inactive
                                @endif
                            </span>
                        </div>
                        <button onclick="toggleCage(this)"
                                class="text-[#6B7280] hover:text-[#333] transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4 cage-chevron transition-transform"></i>
                        </button>
                    </div>

                    {{-- Slots (expandable) --}}
                    <div class="cage-slots hidden">
                        @foreach($slotsInCage as $slotId => $slotHens)
                            @php
                                $slot = $slotHens->first()->cageSlot;
                                $isExpanded = true;
                            @endphp
                            <div class="border-t border-[#F0F0F0]">
                                {{-- Slot header --}}
                                <div class="flex items-center justify-between px-4 py-2 bg-[#FAFAFA] cursor-pointer"
                                     onclick="toggleSlot(this)">
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="font-medium text-[#333]">
                                            Slot {{ $slot->row_number }}-{{ $slot->column_number }}
                                            (#{{ $slot->slot_number }})
                                        </span>
                                        @if($slot->has_sensor)
                                        <span class="flex items-center gap-0.5 text-emerald-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> sensor
                                        </span>
                                        @endif
                                        <span class="text-[#9CA3AF]">
                                            {{ $slotHens->count() }}/{{ $cage->max_chickens_per_slot }} hens
                                        </span>
                                        @if($slotHens->count() > 0)
                                        <span class="text-[#9CA3AF]">
                                            · {{ $slotHens->first()->breed }}
                                        </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-[#9CA3AF]">slot actions:</span>
                                        <button type="button"
                                                onclick="event.stopPropagation(); openMoveModal('{{ $slotHens->pluck('id')->join(',') }}', {{ $slotHens->count() }}, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $slotHens->first()->breed ?? '' }}')"
                                                class="px-1.5 py-0.5 text-[10px] border border-[#D9D9D9] rounded hover:bg-[#E5E7EB]">
                                            Move All
                                        </button>
                                        <button type="button"
                                                onclick="event.stopPropagation(); openRemoveModal('{{ $slotHens->pluck('id')->join(',') }}', {{ $slotHens->count() }}, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $slotHens->first()->breed ?? '' }}')"
                                                class="px-1.5 py-0.5 text-[10px] border border-red-200 text-red-400 rounded hover:bg-red-50">
                                            Remove All
                                        </button>
                                        <i data-lucide="chevron-down" class="w-3 h-3 text-[#9CA3AF] slot-chevron transition-transform"></i>
                                    </div>
                                </div>

                                {{-- Individual Hens --}}
                                <div class="slot-hens hidden">
                                    @foreach($slotHens as $hen)
                                    <div class="flex items-center gap-3 px-4 py-2 border-t border-[#F5F5F5] hover:bg-[#FAFAFA] text-xs">
                                        <input type="checkbox" class="hen-checkbox w-3.5 h-3.5 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]"
                                               value="{{ $hen->id }}"
                                               onclick="updateBulkBar()">
                                        <span class="w-20 font-mono text-[#6B7280]">{{ $hen->tag_code ?? '—' }}</span>
                                        <span class="w-32 text-[#333]">{{ $hen->breed }}</span>
                                        <span class="w-12 text-[#6B7280]">{{ $hen->current_age_weeks }}w</span>
                                        <span class="w-16 text-[#6B7280]">flock {{ $hen->flock_age_weeks }}w</span>
                                        <span class="flex-1">
                                            @if($hen->is_active)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Active</span>
                                            @else
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactive</span>
                                            @endif
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button type="button"
                                                    onclick="openMoveModal('{{ $hen->id }}', 1, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $hen->breed }}')"
                                                    class="px-1.5 py-0.5 text-[10px] border border-[#D9D9D9] rounded hover:bg-[#E5E7EB]">
                                                Move
                                            </button>
                                            <button type="button"
                                                    onclick="openRemoveModal('{{ $hen->id }}', 1, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $hen->breed }}')"
                                                    class="px-1.5 py-0.5 text-[10px] border border-red-200 text-red-400 rounded hover:bg-red-50">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#9CA3AF]">
                No hens found matching your filters.
            </div>
            @endforelse
        </div>

        {{-- Total count --}}
        @if($hensByCage->isNotEmpty())
        <p class="text-xs text-[#9CA3AF] text-right">
            Showing {{ $hensByCage->flatten()->count() }} hen(s)
        </p>
        @endif
    </div>

    {{-- ============================================ --}}
    {{-- MORTALITY TAB --}}
    {{-- ============================================ --}}
    <div id="panelMortality" class="{{ $tab !== 'mortality' ? 'hidden' : '' }}">

        {{-- Today's Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 text-center">
                <div class="text-2xl font-bold text-[#333]">{{ $todayTotal }}</div>
                <div class="text-xs text-[#6B7280] mt-1">Deaths Today</div>
            </div>
            @foreach($cages as $c)
            @php $count = $todayByCage->get($c->cage_code, 0); @endphp
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 text-center {{ $count > 0 ? 'bg-red-50 border-red-200' : '' }}">
                <div class="text-2xl font-bold {{ $count > 0 ? 'text-red-600' : 'text-[#333]' }}">{{ $count }}</div>
                <div class="text-xs text-[#6B7280] mt-1">{{ $c->cage_code }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            {{-- Record Form --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h3 class="text-sm font-semibold text-[#333] mb-4">Record Mortality</h3>
                <form method="POST" action="{{ route('mortality.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Cage <span class="text-red-500">*</span></label>
                        <select name="cage_id" required
                                class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                            <option value="">Select cage...</option>
                            @foreach($cages as $c)
                            <option value="{{ $c->id }}">{{ $c->cage_code }} — {{ $c->location ?: 'No location' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="log_date" required value="{{ today()->toDateString() }}"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Count <span class="text-red-500">*</span></label>
                        <input type="number" name="count" min="1" required placeholder="e.g. 2"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason <span class="text-red-500">*</span></label>
                        <select name="reason" required
                                class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                            <option value="">Select reason...</option>
                            @foreach(['Disease', 'Heat Stress', 'Injury', 'Predator', 'Unknown', 'Other'] as $reason)
                            <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                        <textarea name="notes" rows="2" placeholder="Optional details..."
                                  class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none"></textarea>
                    </div>
                    <button type="submit"
                            class="w-full py-2 bg-[#002D5E] text-white text-sm rounded hover:bg-[#001F42]">
                        Save Record
                    </button>
                </form>
            </div>

            {{-- Recent Records --}}
            <div class="lg:col-span-2 bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
                <div class="px-4 py-2.5 border-b border-[#D9D9D9] bg-[#F5F6F8]">
                    <span class="text-sm font-medium text-[#333]">Recent Records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-[#FAFAFA] text-left">
                                <th class="px-3 py-2 text-[#9CA3AF] font-medium">Date</th>
                                <th class="px-3 py-2 text-[#9CA3AF] font-medium">Cage</th>
                                <th class="px-3 py-2 text-[#9CA3AF] font-medium">Count</th>
                                <th class="px-3 py-2 text-[#9CA3AF] font-medium">Reason</th>
                                <th class="px-3 py-2 text-[#9CA3AF] font-medium">Notes</th>
                                @if(auth()->user()->role === 'admin')<th class="px-3 py-2"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mortalityLogs as $log)
                            <tr class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]">
                                <td class="px-3 py-2 text-[#333]">{{ $log->log_date->format('M d, Y') }}</td>
                                <td class="px-3 py-2 text-[#333]">{{ $log->cage?->cage_code ?? '—' }}</td>
                                <td class="px-3 py-2 text-[#333] font-medium">{{ $log->count }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        $reasonColors = [
                                            'Disease' => 'bg-red-100 text-red-700',
                                            'Heat Stress' => 'bg-yellow-100 text-yellow-700',
                                            'Injury' => 'bg-yellow-100 text-yellow-700',
                                            'Predator' => 'bg-red-100 text-red-700',
                                            'Unknown' => 'bg-gray-100 text-gray-600',
                                            'Other' => 'bg-gray-100 text-gray-600',
                                        ];
                                    @endphp
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $reasonColors[$log->reason] ?? '' }}">
                                        {{ $log->reason }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-[#9CA3AF] max-w-32 truncate">{{ $log->notes ?? '—' }}</td>
                                @can('admin')
                                <td class="px-3 py-2">
                                    <form method="POST" action="{{ route('mortality.destroy', $log) }}"
                                          onsubmit="return confirm('Delete this mortality record?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-[#9CA3AF] text-sm">No records yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</main>

{{-- Modals --}}
@include('chickens.partials.move-modal')
@include('chickens.partials.remove-modal')
@endsection

@push('scripts')
<script>
function switchTab(tab) {
    document.getElementById('panelInventory').classList.toggle('hidden', tab !== 'inventory');
    document.getElementById('panelMortality').classList.toggle('hidden', tab !== 'mortality');
    document.getElementById('tabInventory').classList.toggle('border-[#002D5E]', tab === 'inventory');
    document.getElementById('tabInventory').classList.toggle('text-[#002D5E]', tab === 'inventory');
    document.getElementById('tabInventory').classList.toggle('border-transparent', tab !== 'inventory');
    document.getElementById('tabInventory').classList.toggle('text-[#6B7280]', tab !== 'inventory');
    document.getElementById('tabMortality').classList.toggle('border-[#002D5E]', tab === 'mortality');
    document.getElementById('tabMortality').classList.toggle('text-[#002D5E]', tab === 'mortality');
    document.getElementById('tabMortality').classList.toggle('border-transparent', tab !== 'mortality');
    document.getElementById('tabMortality').classList.toggle('text-[#6B7280]', tab !== 'mortality');
}

function toggleCage(btn) {
    const slots = btn.closest('.bg-white').querySelector('.cage-slots');
    const chevron = btn.querySelector('.cage-chevron');
    if (slots.classList.contains('hidden')) {
        slots.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        slots.classList.add('hidden');
        chevron.style.transform = '';
    }
}

function toggleSlot(btn) {
    const hens = btn.closest('.border-t').querySelector('.slot-hens');
    const chevron = btn.querySelector('.slot-chevron');
    if (hens.classList.contains('hidden')) {
        hens.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        hens.classList.add('hidden');
        chevron.style.transform = '';
    }
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.hen-checkbox:checked');
    const bar = document.getElementById('bulkActionBar');
    const count = document.getElementById('bulkCount');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        count.textContent = checked.length;
    } else {
        bar.classList.add('hidden');
    }
}

function getCheckedHenIds() {
    return Array.from(document.querySelectorAll('.hen-checkbox:checked')).map(el => el.value).join(',');
}

function bulkMove() {
    const ids = getCheckedHenIds();
    const count = document.querySelectorAll('.hen-checkbox:checked').length;
    openMoveModal(ids, count, null, null);
}

function bulkRemove() {
    const ids = getCheckedHenIds();
    const count = document.querySelectorAll('.hen-checkbox:checked').length;
    openRemoveModal(ids, count, null, null);
}

// Initialize: expand all slots
document.querySelectorAll('.cage-slots').forEach(el => el.classList.remove('hidden'));
document.querySelectorAll('.slot-hens').forEach(el => el.classList.remove('hidden'));
</script>
@endpush
