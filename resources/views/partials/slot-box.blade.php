@php
$slotNumber = $slot->slot_number;
$rowNum = $slot->row_number;
$colNum = $slot->column_number;
$isSensor = $slot->hasBreakbeam();
$occupancy = $slot->current_occupancy;
$status = $slot->status;
$primaryHen = $slot->primaryHen();
@endphp
<div class="relative group slot-box"
     data-cage-id="{{ $cage->id }}"
     data-slot-id="{{ $slot->id }}"
     data-slot-number="{{ $slotNumber }}"
     data-row="{{ $rowNum }}"
     data-col="{{ $colNum }}"
     data-status="{{ $status }}"
     data-has-sensor="{{ $isSensor ? 1 : 0 }}"
>
    {{-- Slot number label (shown on hover) --}}
    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
        <span class="text-xs font-medium text-white/90">{{ $rowNum }}-{{ $colNum }}</span>
    </div>

    {{-- Normal view --}}
    <div class="absolute inset-0 flex flex-col items-center justify-center transition-opacity group-hover:opacity-0">
        @if($isSensor)
            <div class="absolute top-0 right-0 w-2 h-2 rounded-bl bg-emerald-500"></div>
        @endif
        <span class="text-xs font-mono text-[#6B7280]">{{ $slotNumber }}</span>
        @if($primaryHen)
            <span class="text-xs text-[#333333] mt-0.5">{{ $primaryHen->breed ?? '' }}</span>
        @endif
        <span class="text-xs text-[#9CA3AF] mt-0.5">{{ $occupancy }}/{{ $cage->max_chickens_per_slot }}</span>
    </div>
</div>
