@extends('layouts.app')
@section('title', 'Bulk Add Chickens')

@section('content')
<main class="p-5 space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('cages.index') }}" class="text-[#6B7280] hover:text-[#333333]">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <h1 class="text-xl font-medium text-[#333333]">Bulk Add Chickens — {{ $cage->cage_code }}</h1>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('bulk-add.store', $cage) }}" id="bulkAddForm">
        @csrf

        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4">1. Select Slots <span id="selectedCount" class="text-[#6B7280] font-normal">(0 selected)</span></h2>
            <div class="space-y-1.5 overflow-x-auto">
                @for($row = 1; $row <= $cage->rows; $row++)
                <div class="flex items-center gap-1">
                    <div class="w-6 text-[11px] text-[#6B7280] shrink-0">{{ chr(64 + $row) }}</div>
                    @foreach($slots->where('row_number', $row) as $slot)
                    @php $isFull = $slot->current_occupancy >= $cage->max_chickens_per_slot; @endphp
                    <label class="flex-1 min-w-[44px] {{ $isFull ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}"
                           title="{{ $isFull ? "Full — {$slot->current_occupancy}/{$cage->max_chickens_per_slot}" : '' }}">
                        <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}"
                               data-occupancy="{{ $slot->current_occupancy }}"
                               class="slot-checkbox sr-only" {{ $isFull ? 'disabled' : '' }}
                               onchange="onSlotToggle(this)">
                        <div class="slot-visual border rounded text-center text-[10px] py-2 px-1 leading-tight bg-[#F5F6F8] border-[#D9D9D9]">
                            @if($slot->has_sensor)
                            <span class="block w-1.5 h-1.5 rounded-full bg-emerald-500 ml-auto"></span>
                            @endif
                            <div class="font-medium text-[#333333]">{{ $slot->label }}</div>
                            <div class="text-[#6B7280]">{{ $slot->current_occupancy }}/{{ $cage->max_chickens_per_slot }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
                @endfor
            </div>
        </div>

        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 mb-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4">2. Flock Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Breed</label>
                    <select name="breed" required class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                        <option @selected(old('breed', 'ISA Brown') === 'ISA Brown')>ISA Brown</option>
                        <option @selected(old('breed') === 'Lohmann Brown-Classic')>Lohmann Brown-Classic</option>
                        <option @selected(old('breed') === 'Dekalb White')>Dekalb White</option>
                        <option @selected(old('breed') === 'Hy-Line Brown')>Hy-Line Brown</option>
                        <option @selected(old('breed') === 'Novogen Brown')>Novogen Brown</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Age at Placement (weeks)</label>
                    <input type="number" name="age_at_placement_weeks" min="0" value="0" required
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                </div>
                <div>
                    <label class="block text-sm text-[#333333] mb-1.5">Placement Date</label>
                    <input type="text" value="{{ now()->format('Y-m-d') }} (auto)" readonly
                           class="w-full border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-[#F5F6F8] text-[#6B7280]">
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm text-[#333333] mb-1.5">Chickens per Slot</label>
                <input type="number" name="chickens_per_slot" id="chickensPerSlot" min="1" value="{{ $cage->max_chickens_per_slot }}" required
                       class="w-full max-w-xs border border-[#D9D9D9] rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                <p id="capacityNote" class="text-xs text-[#6B7280] mt-1.5"></p>
            </div>
        </div>

        <button type="submit" class="bg-[#002D5E] text-white px-6 py-2.5 rounded-lg text-sm hover:bg-[#001F42] transition-colors">
            Add Chickens
        </button>
    </form>

</main>
@endsection

@push('scripts')
<script>
lucide.createIcons();

const maxPerSlot = {{ $cage->max_chickens_per_slot }};

function onSlotToggle(checkbox) {
    const visual = checkbox.nextElementSibling;
    visual.classList.toggle('bg-blue-100', checkbox.checked);
    visual.classList.toggle('border-blue-400', checkbox.checked);

    const checked = document.querySelectorAll('.slot-checkbox:checked');
    document.getElementById('selectedCount').textContent = `(${checked.length} selected)`;

    let minRemaining = maxPerSlot;
    checked.forEach(cb => {
        const remaining = maxPerSlot - parseInt(cb.dataset.occupancy, 10);
        if (remaining < minRemaining) minRemaining = remaining;
    });

    const perSlotInput = document.getElementById('chickensPerSlot');
    perSlotInput.max = minRemaining;
    if (parseInt(perSlotInput.value, 10) > minRemaining) perSlotInput.value = minRemaining;

    document.getElementById('capacityNote').textContent = checked.length > 0
        ? `Most-constrained selected slot has ${minRemaining} spot(s) left — capped to that.`
        : '';
}
</script>
@endpush
