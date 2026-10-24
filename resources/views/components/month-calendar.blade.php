@props(['month', 'days' => [], 'today', 'dayUrl', 'maxMonth'])
@php
    $start = \Carbon\Carbon::createFromFormat('!Y-m', $month);
    $daysInMonth = $start->daysInMonth;
    $leading = ($start->dayOfWeekIso - 1) % 7;
    $cells = array_merge(array_fill(0, $leading, null), range(1, $daysInMonth));
    $cells = array_pad($cells, (int) (ceil(count($cells) / 7) * 7), null);
    $previous = $start->copy()->subMonth()->format('Y-m');
    $next = $start->copy()->addMonth()->format('Y-m');
    $monthBaseQuery = request()->only(['cage_id', 'cage_slot_id', 'breed']);
    $selectedSources = (array) request()->query('logged_via[]', request()->query('logged_via', []));
    if ($selectedSources) $monthBaseQuery['logged_via[]'] = (array) $selectedSources;
    $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $recentCalendarDays = [];
    foreach ($cells as $dayNumber) {
        if ($dayNumber === null) continue;
        $date = $start->copy()->day($dayNumber)->toDateString();
        $summary = $days[$date] ?? null;
        $eggs = (int) ($summary->eggs ?? 0);
        $hens = (int) ($summary->hens ?? 0);
        $hdep = $hens > 0 ? ($eggs / $hens) * 100 : null;
        $sourceLabel = $summary ? collect(['unknown' => 'imported', 'manual' => 'manual', 'sensor' => 'sensor'])->filter(fn ($label, $source) => (int) ($summary->{$source.'_count'} ?? 0) > 0)->values()->implode(', ') : '';
        $recentCalendarDays[$date] = ['summary' => $summary, 'eggs' => $eggs, 'hens' => $hens, 'hdep' => $hdep, 'sourceLabel' => $sourceLabel, 'sourceShort' => str_replace(['imported', 'manual', 'sensor'], ['import', 'man', 'sens'], $sourceLabel), 'tint' => !$summary ? 'bg-[#f6f5f4] text-[#8a8580]' : (($hdep !== null && $hdep >= 80) ? 'bg-emerald-50' : (($hdep !== null && $hdep >= 50) ? 'bg-amber-50' : 'bg-rose-50'))];
    }
    $dayCell = function (array $cell) use ($recentCalendarDays, $start, $today, $dayUrl): \Illuminate\Support\HtmlString {
        $dayNumber = $cell['day'];
        $date = $cell['dateString'];
        $displayDate = $cell['currentMonth']
            ? $start->copy()->setDay($dayNumber)
            : \Carbon\Carbon::createFromFormat('!Y-m-d', $date);
        $record = $recentCalendarDays[$date] ?? ['summary' => null, 'eggs' => 0, 'hdep' => null, 'sourceLabel' => '', 'sourceShort' => '', 'tint' => 'bg-[#f6f5f4] text-[#8a8580]'];
        $summary = $record['summary'];
        $label = $summary ? $displayDate->format('j F').', '.number_format($record['eggs']).' eggs, HDEP '.($record['hdep'] === null ? 'not available' : round($record['hdep']).'%').', source '.$record['sourceLabel'] : $displayDate->format('j F').', no records';
        $classes = 'calendar-day recent-calendar-day '.$record['tint'].($date === $today ? ' calendar-today ring-2 ring-navy ring-inset' : ' border border-black/5');
        $marker = $summary && (int) $summary->override_count > 0 ? '<span title="'.e($summary->override_count).' overridden records" class="text-xs text-violet-700"><span aria-hidden="true">✦</span><span class="sr-only">'.e($summary->override_count).' overridden records</span></span>' : '';
        $content = $summary ? '<span class="mt-1 block truncate text-xs font-medium" title="'.e(number_format($record['eggs'])).' eggs">'.e($record['eggs'] >= 1000 ? number_format($record['eggs'] / 1000, 1).'k' : number_format($record['eggs'])).' <span class="font-normal">eggs</span></span><span class="mt-0.5 block truncate text-[10px] text-[#615d59]">'.e($record['sourceShort']).'</span>' : '<span class="mt-1 block text-xs">—</span><span class="sr-only">No records</span>';
        $dateEscaped = e($date);
        return new \Illuminate\Support\HtmlString('<td class="align-top"><button type="button" class="'.e($classes).'" data-calendar-date="'.$dateEscaped.'" data-detail-url="'.e($dayUrl.'/'.$date).'" aria-label="'.e($label).'" aria-current="'.($date === $today ? 'date' : 'false').'" aria-disabled="'.($summary ? 'false' : 'true').'"'.($summary ? ' tabindex="0"' : '').'><span class="flex items-center justify-between gap-1 text-sm font-semibold"><span>'.e($dayNumber).'</span>'.$marker.'</span>'.$content.'</button></td>');
    };
@endphp

<section class="space-y-4" data-month-calendar data-month="{{ $month }}" aria-label="Production calendar">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            @if($month > '1970-01')
                <a href="{{ route('eggs.recent-logs', $monthBaseQuery + ['view' => 'calendar', 'month' => $previous]) }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-hairline bg-white text-lg" aria-label="Previous month"><span aria-hidden="true">‹</span><span class="sr-only">Previous month</span></a>
            @else
                <span class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-hairline bg-[#f6f5f4] text-lg opacity-50" aria-hidden="true">‹</span>
            @endif
            <label class="sr-only" for="calendarMonthSelect">Select month and year</label>
            <select id="calendarMonthSelect" class="min-h-11 max-w-[calc(100vw-8rem)] rounded-lg border border-hairline bg-white px-2 text-sm font-semibold sm:max-w-none sm:px-3 sm:text-base" aria-label="Month and year" onchange="calendarChangeMonth(this.value)">
                @php($optionMonth = \Carbon\Carbon::createFromFormat('!Y-m', $month))
                @for($i = 0; $i < 48 && $optionMonth->format('Y-m') <= $maxMonth; $i++, $optionMonth->subMonth())
                    <option value="{{ $optionMonth->format('Y-m') }}" {{ $optionMonth->format('Y-m') === $month ? 'selected' : '' }}>{{ $optionMonth->format('F Y') }}</option>
                @endfor
            </select>
            <a href="{{ route('eggs.recent-logs', $monthBaseQuery + ['view' => 'calendar', 'month' => $next]) }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-hairline bg-white text-lg {{ $month >= $maxMonth ? 'pointer-events-none opacity-40' : '' }}" aria-label="Next month" @if($month >= $maxMonth) aria-disabled="true" tabindex="-1" @endif><span aria-hidden="true">›</span><span class="sr-only">Next month</span></a>
        </div>
        <a href="{{ route('eggs.recent-logs', $monthBaseQuery + ['view' => 'calendar', 'month' => substr($today, 0, 7)]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-hairline bg-white px-4 text-sm font-medium">Today</a>
    </div>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm" aria-label="Calendar legend">
        <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded bg-emerald-100 ring-1 ring-emerald-300" aria-hidden="true"></i> HDEP ≥ 80%</span>
        <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded bg-amber-100 ring-1 ring-amber-300" aria-hidden="true"></i> HDEP 50–79%</span>
        <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded bg-rose-100 ring-1 ring-rose-300" aria-hidden="true"></i> HDEP &lt; 50%</span>
        <span>✦ Override</span>
    </div>

    @if(count($days) === 0)
        <p class="rounded-lg bg-[#f6f5f4] px-4 py-3 text-sm text-[#615d59]">No production records for {{ $start->format('F Y') }} with the selected filters.</p>
    @endif

    <x-month-calendar-grid :weekdays="array_map(fn ($day) => substr($day, 0, 3), $weekdays)" class="recent-calendar-grid" label="{{ $start->format('F Y') }} egg production">
        @foreach(array_chunk($cells, 7) as $week)
            <tr>
                @foreach($week as $dayNumber)
                    @if($dayNumber === null)
                        <td class="align-top"><span class="calendar-day calendar-day-placeholder" aria-hidden="true"></span></td>
                    @else
                        <?php
                            $date = $start->copy()->day($dayNumber)->toDateString();
                            $dateCell = ['day' => $dayNumber, 'currentMonth' => true, 'dateString' => $date];
                        ?>
                        {!! $dayCell($dateCell) !!}
                    @endif
                @endforeach
            </tr>
        @endforeach
    </x-month-calendar-grid>
</section>

<script>
function calendarChangeMonth(month) {
    var url = new URL(window.location.href);
    url.searchParams.set('month', month);
    url.searchParams.set('view', 'calendar');
    url.searchParams.delete('_calendarNav');
    window.location.assign(url.toString());
}
(function () {
    if (window.__monthCalendarBound) return;
    window.__monthCalendarBound = true;
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-calendar-date]');
        if (!button || button.getAttribute('aria-disabled') === 'true') return;
        if (window.openCalendarDay) window.openCalendarDay(button);
    });
    document.addEventListener('keydown', function (event) {
        var button = event.target.closest('[data-calendar-date]');
        if (!button) return;
        var cells = Array.from(document.querySelectorAll('[data-calendar-date]:not([aria-disabled="true"])'));
        var index = cells.indexOf(button);
        var delta = {ArrowLeft:-1, ArrowRight:1, ArrowUp:-7, ArrowDown:7}[event.key];
        if (delta !== undefined && cells.length) {
            event.preventDefault();
            var target = Math.max(0, Math.min(cells.length - 1, index + delta));
            cells[target].focus();
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            button.click();
        }
    });
})();
</script>
