@php
    $start = \Carbon\Carbon::createFromFormat('!Y-m', $month);
    $daysInMonth = $start->daysInMonth;
    $leading = ($start->dayOfWeekIso - 1) % 7;
    $previous = $start->copy()->subMonthNoOverflow()->format('Y-m');
    $next = $start->copy()->addMonthNoOverflow()->format('Y-m');
    $monthBaseQuery = request()->only(['cage_id', 'cage_slot_id', 'breed']);
    $selectedSources = (array) request()->query('logged_via[]', request()->query('logged_via', []));
    if ($selectedSources) $monthBaseQuery['logged_via[]'] = $selectedSources;
    $recentCalendarDays = [];
    foreach (range(1, $daysInMonth) as $dayNumber) {
        $date = $start->copy()->day($dayNumber)->toDateString();
        $summary = $calendarDays[$date] ?? null;
        $eggs = (int) ($summary->eggs ?? 0);
        $hens = (int) ($summary->hens ?? 0);
        $hdep = $hens > 0 ? ($eggs / $hens) * 100 : null;
        $sourceLabel = $summary ? collect(['unknown' => 'imported', 'manual' => 'manual', 'sensor' => 'sensor'])
            ->filter(fn ($label, $source) => (int) ($summary->{$source.'_count'} ?? 0) > 0)
            ->values()->implode(', ') : '';
        $recentCalendarDays[$date] = [
            'summary' => $summary,
            'eggs' => $eggs,
            'hdep' => $hdep,
            'sourceLabel' => $sourceLabel,
            'sourceShort' => str_replace(['imported', 'manual', 'sensor'], ['import', 'man', 'sens'], $sourceLabel),
            'tint' => !$summary ? 'bg-[#f6f5f4] text-[#8a8580]' : (($hdep !== null && $hdep >= 80) ? 'bg-emerald-50' : (($hdep !== null && $hdep >= 50) ? 'bg-amber-50' : 'bg-rose-50')),
        ];
    }
@endphp

<section class="recent-production-calendar w-full min-w-0 space-y-4" data-month-calendar data-month="{{ $month }}" aria-label="Production calendar">
    <div class="calendar-toolbar flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e6e6e6] bg-[#fafafa] p-3">
        <div class="flex flex-wrap items-center gap-2 min-w-0">
            <label class="sr-only" for="recentCalendarMonth">Select month</label>
            <select id="recentCalendarMonth" class="calendar-control cursor-pointer rounded-lg border border-[#D9D9D9] bg-white px-3 text-sm font-semibold text-[#333333]" aria-label="Select month" onchange="calendarChangeMonth(this.value)">
                @foreach(range(1, 12) as $monthNumber)<option value="{{ $monthNumber }}" {{ $start->month === $monthNumber ? 'selected' : '' }}>{{ $start->copy()->month($monthNumber)->format('F') }}</option>@endforeach
            </select>
            <label class="sr-only" for="recentCalendarYear">Select year</label>
            <select id="recentCalendarYear" class="calendar-control cursor-pointer rounded-lg border border-[#D9D9D9] bg-white px-3 min-w-24 text-sm font-semibold text-[#333333]" aria-label="Select year" onchange="calendarChangeYear(this.value)">
                @for($year = (int) substr($today, 0, 4) - 5; $year <= (int) substr($today, 0, 4); $year++)<option value="{{ $year }}" {{ $start->year === $year ? 'selected' : '' }}>{{ $year }}</option>@endfor
            </select>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('eggs.recent-logs', $monthBaseQuery + ['view' => 'calendar', 'month' => $previous]) }}" class="calendar-nav w-10 h-10 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280]" aria-label="Previous month"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
            @if($month < $maxMonth)
                <a href="{{ route('eggs.recent-logs', $monthBaseQuery + ['view' => 'calendar', 'month' => $next]) }}" class="calendar-nav w-10 h-10 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280]" aria-label="Next month"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
            @else
                <span class="calendar-nav w-10 h-10 flex items-center justify-center rounded-lg border border-[#F0F0F0] bg-[#F9F9F7] text-[#D9D9D9] cursor-not-allowed" aria-label="Next month disabled"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
            @endif
            <a href="{{ route('eggs.recent-logs', $monthBaseQuery + ['view' => 'calendar', 'month' => substr($today, 0, 7)]) }}" class="calendar-control inline-flex min-w-16 items-center justify-center rounded-lg border border-[#D9D9D9] px-3 text-xs font-semibold text-[#6B7280]">Today</a>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2 mb-3 text-xs text-[#6B7280]" aria-label="Calendar legend">
        <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm border border-emerald-200 bg-emerald-50" aria-hidden="true"></i> HDEP ≥ 80%</span>
        <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm border border-amber-200 bg-amber-50" aria-hidden="true"></i> HDEP 50–79%</span>
        <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm border border-rose-200 bg-rose-50" aria-hidden="true"></i> HDEP &lt; 50%</span>
        <span><span class="max-md:hidden" aria-hidden="true">✦ </span><span class="sr-only">Override</span></span>
        @if(count($calendarDays) === 0)<span class="sm:ml-auto">No records for {{ $start->format('F Y') }} with the selected filters.</span>@endif
    </div>
    <x-production-calendar-grid :weekdays="['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :label="'Daily egg production for '.$start->format('F Y')" class="recent-calendar-grid w-full">
            @php
                $previousMonth = $start->copy()->subMonthNoOverflow();
                $nextMonth = $start->copy()->addMonthNoOverflow();
                $calendarCells = collect();
                for ($i = $leading - 1; $i >= 0; $i--) {
                    $day = $previousMonth->daysInMonth - $i;
                    $calendarCells->push(['day' => $day, 'dateString' => $previousMonth->copy()->day($day)->toDateString(), 'currentMonth' => false]);
                }
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $calendarCells->push(['day' => $day, 'dateString' => $start->copy()->day($day)->toDateString(), 'currentMonth' => true]);
                }
                $trailing = (7 - ($calendarCells->count() % 7)) % 7;
                for ($day = 1; $day <= $trailing; $day++) {
                    $calendarCells->push(['day' => $day, 'dateString' => $nextMonth->copy()->day($day)->toDateString(), 'currentMonth' => false]);
                }
            @endphp
            @foreach($calendarCells->chunk(7) as $week)
                <tr>
                    @foreach($week as $cell)
                        @php
                            $dayNumber = $cell['day'];
                            $date = $cell['dateString'];
                            $record = $recentCalendarDays[$date] ?? ['summary' => null, 'eggs' => 0, 'hdep' => null, 'sourceLabel' => '', 'sourceShort' => '', 'tint' => 'bg-[#f6f5f4] text-[#8a8580]'];
                            $summary = $record['summary'];
                            $displayDate = \Carbon\Carbon::createFromFormat('!Y-m-d', $date);
                            $label = $summary
                                ? $displayDate->format('j F').', '.number_format($record['eggs']).' eggs, HDEP '.($record['hdep'] === null ? 'not available' : round($record['hdep']).'%').', source '.$record['sourceLabel']
                                : $displayDate->format('j F').', no records';
                            $classes = 'calendar-day recent-calendar-day rounded-lg border relative flex flex-col items-start justify-start transition-colors '.$record['tint'];
                            if (!$cell['currentMonth'] && $date !== $today) $classes .= ' opacity-60';
                            if ($date === $today) $classes .= ' calendar-today';
                            if (!$summary) $classes .= ' border-[#F0F0F0]';
                            $marker = $summary && (int) $summary->override_count > 0
                                ? '<span title="'.e($summary->override_count).' overridden records" class="text-xs text-violet-700"><span class="max-md:hidden" aria-hidden="true">✦</span><span class="sr-only">'.e($summary->override_count).' overridden records</span></span>'
                                : '';
                            $content = $summary
                                ? '<span class="production-badge mt-auto inline-flex items-center gap-1 rounded font-bold whitespace-nowrap"><i data-lucide="egg" class="calendar-count-icon max-md:hidden" aria-hidden="true"></i><span>'.e($record['eggs'] >= 1000 ? number_format($record['eggs'] / 1000, 1).'k' : number_format($record['eggs'])).'</span></span><span class="sr-only">'.e($record['sourceLabel']).'</span>'
                                : '<span class="mt-auto block text-xs">—</span><span class="sr-only">No records</span>';
                        @endphp
                        <td class="align-top">
                            <button type="button" class="{{ $classes }}" data-calendar-date="{{ $date }}" data-detail-url="{{ $dayUrl.'/'.$date }}" aria-label="{{ $label }}" @if($summary) tabindex="0" @else aria-disabled="true" tabindex="-1" @endif @if($date === $today) aria-current="date" @endif>
                                <span class="flex w-full items-start justify-between gap-1"><span class="calendar-day-number text-xs sm:text-sm {{ $cell['currentMonth'] ? 'text-[#333333]' : 'text-[#9CA3AF]' }}">{{ $dayNumber }}</span><span class="flex items-center gap-1">{!! $marker !!}@if($summary)<span class="source-marker" aria-hidden="true">{{ $record['sourceShort'] }}</span>@endif</span></span>
                                {!! $content !!}
                            </button>
                        </td>
                    @endforeach
                </tr>
            @endforeach
    </x-production-calendar-grid>
</section>

<script>
function calendarChangeMonth(month) {
    var url = new URL(window.location.href);
    var year = document.getElementById('recentCalendarYear').value;
    var selectedMonth = String(month).padStart(2, '0');
    var maxMonth = '{{ $maxMonth }}';
    var targetMonth = year + '-' + selectedMonth;
    if (targetMonth > maxMonth) targetMonth = maxMonth;
    url.searchParams.set('month', targetMonth);
    url.searchParams.set('view', 'calendar');
    url.searchParams.delete('page');
    window.location.assign(url.toString());
}
function calendarChangeYear(year) {
    var month = document.getElementById('recentCalendarMonth').value.padStart(2, '0');
    var url = new URL(window.location.href);
    var maxMonth = '{{ $maxMonth }}';
    var targetMonth = year + '-' + month;
    if (targetMonth > maxMonth) targetMonth = maxMonth;
    url.searchParams.set('month', targetMonth);
    url.searchParams.set('view', 'calendar');
    url.searchParams.delete('page');
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
            cells[Math.max(0, Math.min(cells.length - 1, index + delta))].focus();
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            button.click();
        }
    });
})();
</script>
