@php
$hasSensor = $cage->cageSlots && $cage->cageSlots->where('has_sensor', true)->count() > 0;
@endphp
<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] {{ $hasSensor ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $hasSensor ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
    {{ $hasSensor ? 'Sensor' : 'No sensor' }}
</span>
