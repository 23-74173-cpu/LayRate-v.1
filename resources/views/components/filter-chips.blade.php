{{--
/**
 * <x-filter-chips>
 *
 * Active-filter chips + result line. Rendered SERVER-side inside the table's
 * Turbo frame (it knows the counts and the validated state), so chips always
 * match the rows on screen. Each X clears its control in the page form and
 * re-applies (filter-ui.js, delegated).
 *
 * Props:
 *   @param array $chips    [['param' =>, 'label' =>, 'display' =>], ...]
 *   @param int   $first    First visible row (paginator firstItem, may be null)
 *   @param int   $last     Last visible row (paginator lastItem, may be null)
 *   @param int   $filtered Filtered total (paginator total)
 *   @param int   $total    Unfiltered total for the "(N total)" suffix
 * }
--}}
@props(['chips' => [], 'first' => null, 'last' => null, 'filtered' => 0, 'total' => null, 'note' => null])

<div class="flex flex-wrap items-center gap-2" data-filter-chips>
    @if(count($chips) > 0)
    <span class="text-xs font-medium" style="color: #615d59;">Active:</span>
    @foreach($chips as $chip)
    <button type="button" data-chip-clear="{{ $chip['param'] }}"
            title="Remove {{ $chip['label'] }} filter"
            class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 min-h-[32px] rounded-full text-xs font-medium border border-[#D9D9D9] bg-white text-[#333333] hover:bg-[#F5F6F8] transition-colors">
        <span><strong>{{ $chip['label'] }}:</strong> {{ $chip['display'] }}</span>
        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full hover:bg-black/10" aria-hidden="true">×</span>
        <span class="sr-only">Remove {{ $chip['label'] }} filter</span>
    </button>
    @endforeach
    <button type="button" data-filter-clear-all class="text-xs font-medium underline underline-offset-2 hover:brightness-90" style="color: var(--color-navy);">Clear all</button>
    @endif
    <span class="text-xs ml-auto" style="color: #615d59;" aria-live="polite" data-filter-result data-filtered-count="{{ (int) $filtered }}">
        @if($first !== null)Showing {{ number_format($first) }}–{{ number_format($last) }} of {{ number_format($filtered) }}@else Showing 0 of {{ number_format($filtered) }}@endif@if($total !== null && $filtered !== (int) $total) ({{ number_format($total) }} total)@endif@if($note !== null)<span class="block sm:inline sm:ml-1">{{ $note }}</span>@endif
    </span>
</div>
