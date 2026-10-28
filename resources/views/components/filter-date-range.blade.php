{{--
/**
 * <x-filter-date-range>
 *
 * From/To pair. Invalid ranges (From after
 * To) are blocked inline with an error and no request is sent.
 *
 * Props:
 *   @param string $fromName  Default "from"   (aliases handled server-side)
 *   @param string $toName    Default "to"
 *   @param string $fromValue Current from (Y-m-d)
 *   @param string $toValue   Current to (Y-m-d)
 *   @param string $today     Anchor date (Y-m-d) for validation
 * }
--}}
@props(['fromName' => 'from', 'toName' => 'to', 'fromValue' => null, 'toValue' => null, 'today' => null])

@php $todayAnchor = $today ?: now()->toDateString(); @endphp
<div class="filter-field min-w-0 sm:col-span-2" data-filter-date-range data-today="{{ $todayAnchor }}">
    <div class="grid min-w-0 grid-cols-2 gap-3">
        <div class="filter-field min-w-0">
            <label for="{{ $fromName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" class="filter-label">From</label>
            <input type="date" name="{{ $fromName }}" id="{{ $fromName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" value="{{ $fromValue }}" aria-label="From date"
                   class="filter-control w-full min-w-0 border border-[#D9D9D9] rounded-lg px-2 text-sm bg-white focus:border-navy">
        </div>
        <div class="filter-field min-w-0">
            <label for="{{ $toName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" class="filter-label">To</label>
            <input type="date" name="{{ $toName }}" id="{{ $toName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" value="{{ $toValue }}" aria-label="To date"
                   class="filter-control w-full min-w-0 border border-[#D9D9D9] rounded-lg px-2 text-sm bg-white focus:border-navy">
        </div>
    </div>
    <p data-filter-date-error class="hidden text-xs mt-1.5" style="color: #9b1c24;" role="alert">From date must be on or before To date.</p>
</div>
