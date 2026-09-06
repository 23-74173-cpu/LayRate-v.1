@php
$days = $days ?? 30;
$frameId = $frameId ?? '';
$routeName = $routeName ?? '';
$routeUrl = $frameId ? route($routeName) : '';
$options = [7 => 'Week', 30 => 'Month', 90 => '3 Months', 0 => 'Full'];
@endphp
<div class="inline-flex items-center gap-1 rounded-lg p-1 shrink-0 ml-auto" style="background-color: #f3f4f6;">
    @foreach($options as $d => $label)
    <button type="button"
       data-days-filter="{{ $d }}"
       onclick="setCardDays('{{ $frameId }}', '{{ $routeUrl }}', {{ $d }})"
       class="card-days-btn px-3 py-1.5 text-xs font-semibold rounded-md transition-all {{ $days === $d ? 'card-days-active' : 'text-[#6B7280] hover:bg-[#e5e7eb]' }}"
       {{ $days === $d ? 'style="background-color: #0075de; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"' : '' }}>
        {{ $label }}
    </button>
    @endforeach
</div>