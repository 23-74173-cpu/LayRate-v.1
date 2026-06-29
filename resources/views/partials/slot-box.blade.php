@php
    $slotStatusBg = match($slot->status) {
        'full'    => 'bg-[#F8D7DA] border-red-200',
        'partial' => 'bg-[#FFF3CD] border-amber-200',
        default   => 'bg-[#F5F6F8] border-[#D9D9D9]',
    };
@endphp
<div class="relative {{ $slotStatusBg }} border rounded text-center text-[10px] py-2 px-1 leading-tight">
    @if($slot->has_sensor)
    <span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-emerald-500" title="Sensor-equipped"></span>
    @endif
    <div class="font-medium text-[#333333]">{{ $slot->label }}</div>
    <div class="text-[#6B7280]">{{ $slot->current_occupancy }}/{{ $slot->cage->max_chickens_per_slot }}</div>
</div>
