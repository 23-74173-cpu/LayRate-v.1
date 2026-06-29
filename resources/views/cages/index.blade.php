@extends('layouts.app')
@section('title', 'Cage Management')

@section('content')
<main class="p-5 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-medium text-[#333333]">Cage Management</h1>
        <button onclick="document.getElementById('addCageModal').classList.remove('hidden')"
                class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Cage
        </button>
    </div>

    @forelse($cages as $cage)
    @php
        $cageEditData = [
            'id' => $cage->id,
            'cage_code' => $cage->cage_code,
            'location' => $cage->location,
            'rows' => $cage->rows,
            'slots_per_row' => $cage->slots_per_row,
            'max_chickens_per_slot' => $cage->max_chickens_per_slot,
            'is_active' => $cage->is_active,
        ];
    @endphp
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <span class="text-base font-medium" style="color:{{ $cage->color }}">{{ $cage->cage_code }}</span>
                <span class="text-sm text-[#6B7280] ml-2">{{ $cage->location ?: '—' }}</span>
                <span class="text-xs px-2.5 py-1 rounded-full ml-2 {{ $cage->is_active ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                    {{ $cage->is_active ? 'active' : 'inactive' }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ Route::has('bulk-add.show') ? route('bulk-add.show', $cage) : '#' }}" class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                    <i data-lucide="users" class="w-3 h-3"></i> Bulk Add Chickens
                </a>
                <button onclick='openEditModal(@json($cageEditData))'
                        class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                    <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                </button>
                <form method="POST" action="{{ route('cages.destroy', $cage) }}" onsubmit="return confirm('Delete {{ $cage->cage_code }}? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="flex items-center justify-center w-8 h-8 border border-red-200 text-red-400 rounded hover:bg-red-50">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="text-xs text-[#6B7280] mb-3">
            {{ $cage->rows }} rows × {{ $cage->slots_per_row }} slots, max {{ $cage->max_chickens_per_slot }}/slot — total capacity {{ $cage->total_capacity }}
        </div>

        <div class="space-y-1.5 overflow-x-auto">
            @for($row = 1; $row <= $cage->rows; $row++)
            <div class="flex items-center gap-1">
                <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ chr(64 + $row) }}</div>
                @foreach($cage->slots->where('row_number', $row) as $slot)
                <div class="flex-1 min-w-[44px]">
                    @include('partials.slot-box', ['slot' => $slot])
                </div>
                @endforeach
            </div>
            @endfor
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-8 text-center text-sm text-[#6B7280]">No cages yet. Click "+ Add Cage" to get started.</div>
    @endforelse

</main>

{{-- ── Add Cage Modal (Battery Cage Configuration) ── --}}
<div id="addCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-medium">Battery Cage Configuration</h2>
            <button onclick="document.getElementById('addCageModal').classList.add('hidden')" class="text-[#6B7280] hover:text-[#333333]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('cages.store') }}">
            @csrf
            <label class="block text-sm text-[#333333] mb-1.5">Cage Name</label>
            <input name="cage_code" id="cfgCageCode" placeholder="e.g. CAGE-E" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Location</label>
            <input name="location" placeholder="e.g. North Wing"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Rows</label>
                    <input name="rows" id="cfgRows" type="number" min="1" max="26" value="3" required oninput="updateConfigPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Slots/Row</label>
                    <input name="slots_per_row" id="cfgSlotsPerRow" type="number" min="1" max="50" value="10" required oninput="updateConfigPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Max/Slot</label>
                    <input name="max_chickens_per_slot" id="cfgMaxPerSlot" type="number" min="1" max="20" value="4" required oninput="updateConfigPreview()"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
            </div>

            <div class="bg-[#F5F6F8] border border-[#D9D9D9] rounded-lg p-3 mb-4 text-xs text-[#333333]">
                <div class="font-medium mb-1">Configuration Summary</div>
                <div id="cfgSummary">30 slots · 120 total capacity</div>
            </div>

            <div class="mb-5">
                <div class="text-sm text-[#333333] mb-1.5">Layout Preview</div>
                <div id="cfgPreview" class="space-y-1 overflow-x-auto"></div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('addCageModal').classList.add('hidden')"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8]">Cancel</button>
                <button type="submit" class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Create Battery Cage System</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
lucide.createIcons();

function updateConfigPreview() {
    const rows = parseInt(document.getElementById('cfgRows').value) || 0;
    const slotsPerRow = parseInt(document.getElementById('cfgSlotsPerRow').value) || 0;
    const maxPerSlot = parseInt(document.getElementById('cfgMaxPerSlot').value) || 0;
    const totalSlots = rows * slotsPerRow;
    const totalCapacity = totalSlots * maxPerSlot;

    document.getElementById('cfgSummary').textContent = `${totalSlots} slots · ${totalCapacity} total capacity`;

    const preview = document.getElementById('cfgPreview');
    preview.innerHTML = '';
    for (let r = 1; r <= rows; r++) {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'flex items-center gap-1';
        const label = document.createElement('div');
        label.className = 'w-6 text-[11px] text-[#6B7280] shrink-0';
        label.textContent = String.fromCharCode(64 + r);
        rowDiv.appendChild(label);
        for (let c = 1; c <= slotsPerRow; c++) {
            const cell = document.createElement('div');
            cell.className = 'flex-1 min-w-[28px] h-6 rounded bg-[#F5F6F8] border border-[#D9D9D9]';
            rowDiv.appendChild(cell);
        }
        preview.appendChild(rowDiv);
    }
}

window.addEventListener('DOMContentLoaded', updateConfigPreview);
</script>
@endpush
