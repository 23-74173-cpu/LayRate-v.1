@extends('layouts.app')
@section('title', 'Cage Management')

@section('content')
<main class="p-5 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-medium text-[#333333]">Cage Management</h1>
        <button onclick="openAddModal()"
                class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Cage
        </button>
    </div>

    {{-- Flash error for resize --}}
    @if(session('errors') && session('errors')->has('resize'))
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 flex items-start gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i>
        {{ session('errors')->first('resize') }}
    </div>
    @endif

    {{-- Cage Cards --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        @forelse($cages as $cage)
        @php
            $color = $cage->color;
            $slotsByRow = $cage->cageSlots->groupBy('row_number');
            $sensorCount = $cage->cageSlots->where('has_sensor', true)->count();
            $occupiedCount = $cage->cageSlots->where('current_occupancy', '>', 0)->count();
            $primaryHen = $cage->hens->first();
            $shouldOpenEdit = session('edit_cage_id') == $cage->id;
        @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            {{-- Cage Header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-[#D9D9D9]" style="background:{{ $color }}10">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                    <span class="text-xs text-[#6B7280]">{{ $cage->location ?: 'No location' }}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $cage->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $cage->is_active ? 'active' : 'inactive' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] text-[#6B7280]">{{ $sensorCount }} sensor{{ $sensorCount !== 1 ? 's' : '' }}</span>
                    <button onclick="openEditModal({{ $cage->id }}, '{{ $cage->cage_code }}', '{{ $cage->location }}', {{ $cage->rows }}, {{ $cage->slots_per_row }}, {{ $cage->max_chickens_per_slot }}, {{ $cage->is_active ? 1 : 0 }})"
                            class="flex items-center gap-1 text-xs border border-[#D9D9D9] bg-white px-2 py-1 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                        <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                    </button>
                    <a href="{{ route('cages.confirm-delete', $cage) }}"
                       class="flex items-center justify-center w-7 h-7 border border-red-200 text-red-400 rounded hover:bg-red-50">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            {{-- Cage Meta --}}
            <div class="px-5 py-2 flex flex-wrap gap-4 text-[11px] text-[#6B7280] border-b border-[#D9D9D9]">
                <span>{{ $cage->rows }} rows × {{ $cage->slots_per_row }} slots</span>
                <span>{{ $cage->total_capacity }} total capacity</span>
                <span>{{ $occupiedCount }} slot{{ $occupiedCount !== 1 ? 's' : '' }} occupied</span>
                @if($primaryHen)
                <span>{{ $primaryHen->breed }} · {{ $primaryHen->current_age_weeks }} wks</span>
                @endif
            </div>

            {{-- Slot Grid --}}
            <div class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] text-[#6B7280] uppercase tracking-wider">Slot Layout</span>
                    <div class="flex items-center gap-3 text-[9px] text-[#9CA3AF]">
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-sm bg-emerald-500"></span> sensor
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-sm bg-[#F5F6F8] border border-[#D9D9D9]"></span> empty
                        </span>
                    </div>
                </div>

                @php $colHeaders = range(1, $cage->slots_per_row); @endphp
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full">
                        {{-- Column headers --}}
                        <div class="flex gap-1 mb-1 pl-8">
                            @foreach($colHeaders as $col)
                            <div class="w-9 text-center text-[9px] text-[#9CA3AF]">{{ $col }}</div>
                            @endforeach
                        </div>

                        {{-- Rows --}}
                        @foreach($slotsByRow as $rowNum => $slots)
                        <div class="flex gap-1 mb-1">
                            {{-- Row header --}}
                            <div class="w-7 flex items-center justify-center text-[9px] text-[#9CA3AF]">{{ $rowNum }}</div>
                            {{-- Slot boxes --}}
                            @for($col = 1; $col <= $cage->slots_per_row; $col++)
                                @php
                                    $slot = $slots->firstWhere('column_number', $col);
                                @endphp
                                @if($slot)
                                    @php
                                        $isSensor = $slot->has_sensor;
                                        $occupancy = $slot->current_occupancy;
                                        $slotPrimaryHen = $slot->primaryHen();
                                        $bgClass = $isSensor ? 'bg-emerald-50 border-emerald-200' : ($occupancy > 0 ? 'bg-[#F5F6F8] border-[#E5E7EB]' : 'bg-white border-[#E5E7EB]');
                                    @endphp
                                    <div class="relative w-9 h-9 rounded border {{ $bgClass }} flex flex-col items-center justify-center cursor-pointer hover:ring-1 hover:ring-[#002D5E] transition-all"
                                         onclick="toggleSlotSensor({{ $cage->id }}, {{ $slot->id }}, {{ $isSensor ? 1 : 0 }})"
                                         title="Slot {{ $rowNum }}-{{ $col }} ({{ $isSensor ? 'sensor' : 'manual' }})">
                                        @if($isSensor)
                                            <div class="absolute top-0 right-0 w-2 h-2 rounded-bl-sm bg-emerald-500"></div>
                                        @endif
                                        <span class="text-[9px] font-mono text-[#6B7280]">{{ $slot->slot_number }}</span>
                                        @if($slotPrimaryHen)
                                            <span class="text-[8px] text-[#333]">{{ $slotPrimaryHen->current_age_weeks }}w</span>
                                        @endif
                                        <span class="text-[8px] text-[#9CA3AF]">{{ $occupancy }}/{{ $cage->max_chickens_per_slot }}</span>
                                    </div>
                                @else
                                    <div class="w-9 h-9"></div>
                                @endif
                            @endfor
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
        <div class="col-span-2 bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#6B7280]">
            No cages yet. Click "+ Add Cage" to get started.
        </div>
        @endforelse
        </div>
    </div>

    @if(session('edit_cage_id'))
    @php
        $editCage = $cages->firstWhere('id', session('edit_cage_id'));
    @endphp
    @endif

    @if(session('edit_cage_id') && isset($editCage))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            openEditModal(
                {{ $editCage->id }},
                '{{ $editCage->cage_code }}',
                '{{ $editCage->location }}',
                {{ $editCage->rows }},
                {{ $editCage->slots_per_row }},
                {{ $editCage->max_chickens_per_slot }},
                {{ $editCage->is_active ? 1 : 0 }}
            );
        });
    </script>
    @endif
</main>

{{-- ── Add Cage Modal ── --}}
<div id="addCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-medium">Battery Cage Configuration</h2>
            <button onclick="closeAddModal()" class="text-[#6B7280] hover:text-[#333333]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('cages.store') }}" id="addCageForm">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm text-[#333333] mb-1.5">Cage Name</label>
                    <input name="cage_code" id="addCageCode" placeholder="e.g. CAGE-E" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm text-[#333333] mb-1.5">Location</label>
                    <input name="location" id="addLocation" placeholder="e.g. North Wing"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Rows</label>
                    <input type="number" name="rows" id="addRows" value="3" min="1" max="10"
                           oninput="updateAddPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Slots per Row</label>
                    <input type="number" name="slots_per_row" id="addSlotsPerRow" value="5" min="1" max="10"
                           oninput="updateAddPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm text-[#333333] mb-1.5">Max Chickens per Slot</label>
                    <input type="number" name="max_chickens_per_slot" id="addMaxPerSlot" value="4" min="1" max="10"
                           oninput="updateAddPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            {{-- Configuration Summary --}}
            <div class="mt-4 p-3 bg-[#F5F6F8] rounded-lg border border-[#D9D9D9]">
                <div class="text-[10px] text-[#6B7280] uppercase tracking-wider mb-2">Configuration Summary</div>
                <div class="flex justify-between text-sm">
                    <span class="text-[#6B7280]">Total slots</span>
                    <span class="font-medium text-[#333333]" id="addSummarySlots">15</span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-[#6B7280]">Total capacity</span>
                    <span class="font-medium text-[#002D5E]" id="addSummaryCapacity">60 hens</span>
                </div>
            </div>

            {{-- Layout Preview --}}
            <div class="mt-4">
                <div class="text-[10px] text-[#6B7280] uppercase tracking-wider mb-2">Layout Preview</div>
                <div class="border border-[#E5E7EB] rounded-lg p-3 bg-white overflow-x-auto">
                    <div class="flex gap-1 mb-1 pl-6" id="addPreviewColHeaders">
                        @for($c = 1; $c <= 5; $c++)
                            <div class="w-8 text-center text-[9px] text-[#9CA3AF]">{{ $c }}</div>
                        @endfor
                    </div>
                    <div id="addPreviewGrid" class="space-y-1">
                        @for($r = 1; $r <= 3; $r++)
                            <div class="flex gap-1">
                                <div class="w-5 flex items-center justify-center text-[9px] text-[#9CA3AF]">{{ $r }}</div>
                                @for($c = 1; $c <= 5; $c++)
                                    <div class="w-8 h-8 rounded border border-[#E5E7EB] bg-[#F9F9F7] flex items-center justify-center">
                                        <span class="text-[9px] font-mono text-[#9CA3AF]">{{ ($r - 1) * 5 + $c }}</span>
                                    </div>
                                @endfor
                            </div>
                        @endfor
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeAddModal()"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8]">Cancel</button>
                <button type="submit" class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Add Cage</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Edit Cage Modal ── --}}
<div id="editCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-medium">Edit Cage — <span id="editCageCode"></span></h2>
            <button onclick="closeEditModal()" class="text-[#6B7280] hover:text-[#333333]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="" id="editCageForm">
            @csrf @method('PUT')

            @if(session('errors') && session('errors')->has('resize'))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                <div class="flex items-start gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i>
                    {{ session('errors')->first('resize') }}
                </div>
            </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm text-[#333333] mb-1.5">Location</label>
                    <input name="location" id="editLocation"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Rows</label>
                    <input type="number" name="rows" id="editRows" value="3" min="1" max="10"
                           oninput="updateEditPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Slots per Row</label>
                    <input type="number" name="slots_per_row" id="editSlotsPerRow" value="5" min="1" max="10"
                           oninput="updateEditPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm text-[#333333] mb-1.5">Max Chickens per Slot</label>
                    <input type="number" name="max_chickens_per_slot" id="editMaxPerSlot" value="4" min="1" max="10"
                           oninput="updateEditPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <div class="mt-4 p-3 bg-[#F5F6F8] rounded-lg border border-[#D9D9D9]">
                <div class="text-[10px] text-[#6B7280] uppercase tracking-wider mb-2">New Configuration</div>
                <div class="flex justify-between text-sm">
                    <span class="text-[#6B7280]">Total slots</span>
                    <span class="font-medium text-[#333333]" id="editSummarySlots">15</span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-[#6B7280]">Total capacity</span>
                    <span class="font-medium text-[#002D5E]" id="editSummaryCapacity">60 hens</span>
                </div>
            </div>

            <div class="mt-4">
                <label class="flex items-center gap-2 mb-4 cursor-pointer">
                    <input id="editActive" name="is_active" type="checkbox" value="1" class="w-4 h-4">
                    <span class="text-sm text-[#333333]">Active</span>
                </label>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8]">Cancel</button>
                <button type="submit" class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
lucide.createIcons();

function openAddModal() {
    document.getElementById('addCageModal').classList.remove('hidden');
    updateAddPreview();
}

function closeAddModal() {
    document.getElementById('addCageModal').classList.add('hidden');
}

function openEditModal(id, cageCode, location, rows, slotsPerRow, maxPerSlot, isActive) {
    document.getElementById('editCageForm').action = '/cages/' + id;
    document.getElementById('editCageCode').textContent = cageCode;
    document.getElementById('editLocation').value = location || '';
    document.getElementById('editRows').value = rows;
    document.getElementById('editSlotsPerRow').value = slotsPerRow;
    document.getElementById('editMaxPerSlot').value = maxPerSlot;
    document.getElementById('editActive').checked = isActive === 1;
    updateEditPreview();
    document.getElementById('editCageModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editCageModal').classList.add('hidden');
}

function updateAddPreview() {
    const rows = parseInt(document.getElementById('addRows').value) || 1;
    const slotsPerRow = parseInt(document.getElementById('addSlotsPerRow').value) || 1;
    const maxPerSlot = parseInt(document.getElementById('addMaxPerSlot').value) || 1;
    const totalSlots = rows * slotsPerRow;
    const totalCapacity = totalSlots * maxPerSlot;

    document.getElementById('addSummarySlots').textContent = totalSlots;
    document.getElementById('addSummaryCapacity').textContent = totalCapacity + ' hens';

    const grid = document.getElementById('addPreviewGrid');
    const colHeaders = document.getElementById('addPreviewColHeaders');

    colHeaders.innerHTML = '';
    for (let c = 1; c <= slotsPerRow; c++) {
        const d = document.createElement('div');
        d.className = 'w-8 text-center text-[9px] text-[#9CA3AF]';
        d.textContent = c;
        colHeaders.appendChild(d);
    }

    let html = '';
    for (let r = 1; r <= rows; r++) {
        html += '<div class="flex gap-1 mb-1">';
        html += '<div class="w-5 flex items-center justify-center text-[9px] text-[#9CA3AF]">' + r + '</div>';
        for (let c = 1; c <= slotsPerRow; c++) {
            const num = (r - 1) * slotsPerRow + c;
            html += '<div class="w-8 h-8 rounded border border-[#E5E7EB] bg-[#F9F9F7] flex items-center justify-center">';
            html += '<span class="text-[9px] font-mono text-[#9CA3AF]">' + num + '</span>';
            html += '</div>';
        }
        html += '</div>';
    }
    grid.innerHTML = html;
}

function updateEditPreview() {
    const rows = parseInt(document.getElementById('editRows').value) || 1;
    const slotsPerRow = parseInt(document.getElementById('editSlotsPerRow').value) || 1;
    const maxPerSlot = parseInt(document.getElementById('editMaxPerSlot').value) || 1;
    const totalSlots = rows * slotsPerRow;
    const totalCapacity = totalSlots * maxPerSlot;

    document.getElementById('editSummarySlots').textContent = totalSlots;
    document.getElementById('editSummaryCapacity').textContent = totalCapacity + ' hens';
}

function toggleSlotSensor(cageId, slotId, currentSensor) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/cages/${cageId}/slots/${slotId}/toggle-sensor`;
    form.innerHTML = `@csrf`;
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
