@extends('layouts.app')
@section('title', 'Cage Management')

@section('content')
<main class="p-5">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-medium text-[#333333]">Cage Management</h1>
        <button onclick="document.getElementById('addCageModal').classList.remove('hidden')"
                class="flex items-center gap-2 bg-[#002D5E] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Cage
        </button>
    </div>

    {{-- Cages Table --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden mb-4">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Cage Code</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Location</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Breed</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Hens</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Flock Age</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">HDEP</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Status</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cages as $cage)
                @php
                    $prod  = $cage->latestProduction;
                    $hdep  = $prod?->hdep ?? 0;
                    $hen   = $cage->hens->first();
                    $color = $cage->color;
                    $hdepBg  = $hdep > 70 ? '#D5E8D4' : ($hdep > 40 ? '#FFF3CD' : '#F8D7DA');
                    $hdepTxt = $hdep > 70 ? '#004F9F' : ($hdep > 40 ? '#856404' : '#721C24');
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8] cursor-pointer"
                    onclick="toggleCageDetail('cage-detail-{{ $cage->id }}')">
                    <td class="px-5 py-3.5">
                        <span class="text-sm font-medium" style="color:{{ $color }}">{{ $cage->cage_code }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $cage->location ?: '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $hen?->breed ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $cage->capacity }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">
                        {{ $hen ? $hen->current_age_weeks . ' weeks' : '—' }}
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="text-xs px-2.5 py-1 rounded-full" style="background:{{ $hdepBg }};color:{{ $hdepTxt }}">
                            {{ number_format($hdep,1) }}%
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="text-xs px-2.5 py-1 rounded-full {{ $cage->is_active ? 'bg-[#D5E8D4] text-[#2D6A4F]' : 'bg-gray-200 text-gray-500' }}">
                            {{ $cage->is_active ? 'active' : 'inactive' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2" onclick="event.stopPropagation()">
                            <button onclick="openEditModal({{ $cage->id }}, '{{ $cage->location }}', {{ $cage->capacity }}, {{ $cage->is_active ? 1 : 0 }})"
                                    class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-[#F5F6F8] text-[#6B7280]">
                                <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                            </button>
                            <form method="POST" action="{{ route('cages.destroy', $cage) }}"
                                  onsubmit="return confirm('Delete {{ $cage->cage_code }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex items-center justify-center w-8 h-8 border border-red-200 text-red-400 rounded hover:bg-red-50">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                {{-- Expandable cage detail / slot grid --}}
                <tr id="cage-detail-{{ $cage->id }}" class="hidden bg-[#F9F9F7]">
                    <td colspan="8" class="px-5 py-4">
                        <div class="text-[11px] text-[#6B7280] mb-2">Click a row to view its cage layout below.</div>
                        <div class="space-y-1.5">
                            @foreach(['A','B','C'] as $row)
                            <div class="flex items-center gap-1">
                                <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ $row }}</div>
                                @for($col = 1; $col <= 10; $col++)
                                <div class="flex-1 text-center text-[10px] py-2 rounded bg-[#F5F6F8] text-[#6B7280] hover:bg-[#EAF0F8] cursor-pointer border border-[#E8E8E4]">
                                    {{ $row }}{{ $col }}
                                </div>
                                @endfor
                            </div>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-8 text-center text-sm text-[#6B7280]">No cages yet. Click "+ Add Cage" to get started.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="text-[11px] text-[#6B7280] px-5 py-3 border-t border-[#D9D9D9]">Click a row to view its cage layout below.</p>
    </div>

</main>

{{-- ── Add Cage Modal ── --}}
<div id="addCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-medium">Add New Cage</h2>
            <button onclick="document.getElementById('addCageModal').classList.add('hidden')" class="text-[#6B7280] hover:text-[#333333]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('cages.store') }}">
            @csrf
            <label class="block text-sm text-[#333333] mb-1.5">Cage Code</label>
            <input name="cage_code" placeholder="e.g. CAGE-E" required
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Location</label>
            <input name="location" placeholder="e.g. North Wing"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Breed</label>
            <select name="breed" class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">
                <option>ISA Brown</option>
                <option>Lohmann Brown-Classic</option>
                <option>Dekalb White</option>
                <option>Hy-Line Brown</option>
                <option>Novogen Brown</option>
            </select>

            <label class="block text-sm text-[#333333] mb-1.5">Capacity (hens)</label>
            <input name="capacity" type="number" value="120"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-5 focus:outline-none focus:border-[#002D5E]">

            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('addCageModal').classList.add('hidden')"
                        class="flex-1 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm hover:bg-[#F5F6F8]">Cancel</button>
                <button type="submit" class="flex-1 bg-[#002D5E] text-white py-2.5 rounded-lg text-sm hover:bg-[#001F42]">Add Cage</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Edit Cage Modal ── --}}
<div id="editCageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="bg-white rounded-xl border border-[#D9D9D9] shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-medium">Edit Cage</h2>
            <button onclick="document.getElementById('editCageModal').classList.add('hidden')" class="text-[#6B7280] hover:text-[#333333]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form id="editCageForm" method="POST">
            @csrf @method('PUT')
            <label class="block text-sm text-[#333333] mb-1.5">Location</label>
            <input id="editLocation" name="location"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="block text-sm text-[#333333] mb-1.5">Capacity (hens)</label>
            <input id="editCapacity" name="capacity" type="number"
                   class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white mb-4 focus:outline-none focus:border-[#002D5E]">

            <label class="flex items-center gap-2 mb-5 cursor-pointer">
                <input id="editActive" name="is_active" type="checkbox" value="1" class="w-4 h-4">
                <span class="text-sm text-[#333333]">Active</span>
            </label>

            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('editCageModal').classList.add('hidden')"
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

function toggleCageDetail(id) {
    const el = document.getElementById(id);
    el.classList.toggle('hidden');
}

function openEditModal(id, location, capacity, isActive) {
    document.getElementById('editCageForm').action = '/cages/' + id;
    document.getElementById('editLocation').value  = location;
    document.getElementById('editCapacity').value  = capacity;
    document.getElementById('editActive').checked  = isActive === 1;
    document.getElementById('editCageModal').classList.remove('hidden');
}
</script>
@endpush
