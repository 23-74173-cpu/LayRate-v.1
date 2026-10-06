{{--
/**
 * <x-filter-date-range>
 *
 * From/To pair with quick presets. Preset ranges are derived from the
 * server-rendered anchor date (the reporting "today"), never from the
 * browser clock, so there is no timezone drift. Invalid ranges (From after
 * To) are blocked inline with an error and no request is sent.
 *
 * Props:
 *   @param string $fromName  Default "from"   (aliases handled server-side)
 *   @param string $toName    Default "to"
 *   @param string $fromValue Current from (Y-m-d)
 *   @param string $toValue   Current to (Y-m-d)
 *   @param string $today     Anchor date (Y-m-d) for presets
 *   @param bool   $presets   Show preset pills (default true)
 * }
--}}
@props(['fromName' => 'from', 'toName' => 'to', 'fromValue' => null, 'toValue' => null, 'today' => null, 'presets' => true])

@php $todayAnchor = $today ?: now()->toDateString(); @endphp
<div class="col-span-2 sm:col-span-1 min-w-0" data-filter-date-range data-today="{{ $todayAnchor }}">
    @if($presets)
    <div class="flex flex-wrap gap-1.5 mb-2" role="group" aria-label="Date presets">
        @foreach([['today', 'Today'], ['7days', '7 days'], ['30days', '30 days'], ['month', 'This month']] as [$preset, $presetLabel])
        <button type="button" data-preset="{{ $preset }}" aria-pressed="false"
                class="px-3 min-h-[40px] rounded-full text-xs font-medium border border-[#D9D9D9] bg-white text-[#6B7280] hover:bg-[#F5F6F8] transition-colors">{{ $presetLabel }}</button>
        @endforeach
    </div>
    @endif
    <div class="grid grid-cols-2 gap-3">
        <div class="min-w-0">
            <label for="{{ $fromName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">From</label>
            <input type="date" name="{{ $fromName }}" id="{{ $fromName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" value="{{ $fromValue }}"
                   class="w-full min-w-0 min-h-[44px] border border-[#D9D9D9] rounded-lg px-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy/30 focus:border-navy">
        </div>
        <div class="min-w-0">
            <label for="{{ $toName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">To</label>
            <input type="date" name="{{ $toName }}" id="{{ $toName }}-{{ substr(md5($fromName . $toName), 0, 6) }}" value="{{ $toValue }}"
                   class="w-full min-w-0 min-h-[44px] border border-[#D9D9D9] rounded-lg px-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy/30 focus:border-navy">
        </div>
    </div>
    <p data-filter-date-error class="hidden text-xs mt-1.5" style="color: #9b1c24;" role="alert">From date must be on or before To date.</p>
</div>
