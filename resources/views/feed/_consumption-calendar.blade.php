@php
    $start = \Carbon\Carbon::createFromFormat('!Y-m', $month);
    $daysInMonth = $start->daysInMonth;
    $leading = ($start->dayOfWeekIso - 1) % 7;
    $previous = $start->copy()->subMonthNoOverflow()->format('Y-m');
    $next = $start->copy()->addMonthNoOverflow()->format('Y-m');
    $baseQuery = request()->except(['view', 'month', 'page', 'return_url']);
    unset($baseQuery['tab']);
    $amounts = $calendarDays->pluck('total_kg')->map(fn ($amount) => (float) $amount);
    $minKg = $amounts->min() ?? 0;
    $maxKg = $amounts->max() ?? 0;
    $rangeKg = $maxKg - $minKg;
    $filterLabel = $preselectedCageId ? ($cages->firstWhere('id', $preselectedCageId)?->cage_code ?? 'Selected cage') : null;
    $calendarCells = collect();
    $previousMonth = $start->copy()->subMonthNoOverflow();
    $nextMonth = $start->copy()->addMonthNoOverflow();
    for ($i = $leading - 1; $i >= 0; $i--) {
        $day = $previousMonth->daysInMonth - $i;
        $calendarCells->push(['day' => $day, 'date' => $previousMonth->copy()->day($day)->toDateString(), 'current' => false]);
    }
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $calendarCells->push(['day' => $day, 'date' => $start->copy()->day($day)->toDateString(), 'current' => true]);
    }
    $trailing = (7 - ($calendarCells->count() % 7)) % 7;
    for ($day = 1; $day <= $trailing; $day++) {
        $calendarCells->push(['day' => $day, 'date' => $nextMonth->copy()->day($day)->toDateString(), 'current' => false]);
    }
@endphp

<section class="feed-month-calendar w-full min-w-0 p-3 sm:p-5" data-month-calendar data-month="{{ $month }}" aria-label="Feed consumption calendar">
    <div class="calendar-toolbar flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e6e6e6] bg-[#fafafa] p-3">
        <div class="flex flex-wrap items-center gap-2 min-w-0">
            <label class="sr-only" for="feedCalendarMonth">Select month</label>
            <select id="feedCalendarMonth" class="feed-calendar-control cursor-pointer rounded-lg border border-[#D9D9D9] bg-white px-3 text-sm font-semibold text-[#333333]" aria-label="Select month" onchange="feedCalendarChangeMonth(this.value)">
                @foreach(range(1, 12) as $monthNumber)<option value="{{ $monthNumber }}" {{ $start->month === $monthNumber ? 'selected' : '' }}>{{ $start->copy()->month($monthNumber)->format('F') }}</option>@endforeach
            </select>
            <label class="sr-only" for="feedCalendarYear">Select year</label>
            <select id="feedCalendarYear" class="feed-calendar-control cursor-pointer rounded-lg border border-[#D9D9D9] bg-white px-3 min-w-24 text-sm font-semibold text-[#333333]" aria-label="Select year" onchange="feedCalendarChangeYear(this.value)">
                @for($year = (int) substr($today, 0, 4) - 5; $year <= (int) substr($today, 0, 4); $year++)<option value="{{ $year }}" {{ $start->year === $year ? 'selected' : '' }}>{{ $year }}</option>@endfor
            </select>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('feed', $baseQuery + ['tab' => 'consumption', 'view' => 'calendar', 'month' => $previous]) }}" onclick="event.preventDefault(); feedCalendarNavigate('{{ $previous }}')" class="calendar-nav w-10 h-10 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280]" aria-label="Previous month"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
            @if($month < $maxMonth)
                <a href="{{ route('feed', $baseQuery + ['tab' => 'consumption', 'view' => 'calendar', 'month' => $next]) }}" onclick="event.preventDefault(); feedCalendarNavigate('{{ $next }}')" class="calendar-nav w-10 h-10 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280]" aria-label="Next month"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
            @else
                <span class="calendar-nav w-10 h-10 flex items-center justify-center rounded-lg border border-[#F0F0F0] bg-[#F9F9F7] text-[#D9D9D9] cursor-not-allowed" aria-label="Next month disabled"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
            @endif
            <a href="{{ route('feed', $baseQuery + ['tab' => 'consumption', 'view' => 'calendar', 'month' => $maxMonth]) }}" onclick="event.preventDefault(); feedCalendarNavigate('{{ $maxMonth }}')" class="feed-calendar-control inline-flex min-w-16 items-center justify-center rounded-lg border border-[#D9D9D9] px-3 text-xs font-semibold text-[#6B7280]" @if(substr($today, 0, 7) === $month) aria-current="date" @endif>Today</a>
        </div>
    </div>

    @if($filterLabel)
        <p class="mt-3 text-sm text-[#615d59]" aria-live="polite">Filtered to <span class="font-semibold text-[#333333]">{{ $filterLabel }}</span></p>
    @endif

    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-[#6B7280]" aria-label="Calendar legend">
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-3 w-10 overflow-hidden rounded-sm border border-emerald-300" aria-hidden="true"><i class="flex-1 bg-emerald-50"></i><i class="flex-1 bg-emerald-100"></i><i class="flex-1 bg-emerald-200"></i></span> Low → high</span>
        <span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-amber-600" aria-hidden="true"></i> Missing feeding</span>
        <span class="inline-flex items-center gap-1.5" title="Amounts are distributed estimates from whole-farm entries."><i data-lucide="git-branch" class="h-3.5 w-3.5" aria-hidden="true"></i> Estimated</span>
        @if($calendarDays->isEmpty())<span class="sm:ml-auto">No feed records for {{ $start->format('F Y') }} with the selected filters{{ $filterLabel ? ' ('.$filterLabel.')' : '' }}.</span>@endif
    </div>

                    <div class="mt-2 w-full min-w-0">
        <x-production-calendar-grid :weekdays="['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :label="'Daily feed consumption for '.$start->format('F Y')" class="feed-calendar-grid w-full min-w-0">
            @foreach($calendarCells->chunk(7) as $week)
                <tr>
                    @foreach($week as $cell)
                        @php
                            $summary = $calendarDays[$cell['date']] ?? null;
                            $kg = $summary ? (float) $summary->total_kg : null;
                            $records = (int) ($summary->records ?? 0);
                            $ratio = $kg !== null && $rangeKg > 0 ? ($kg - $minKg) / $rangeKg : 0;
                            $tint = $kg === null ? 'bg-[#fbfbfa]' : ($rangeKg === 0 ? 'bg-emerald-100' : ($ratio < .34 ? 'bg-emerald-50' : ($ratio < .67 ? 'bg-emerald-100' : 'bg-emerald-200')));
                            $dateLabel = \Carbon\Carbon::createFromFormat('!Y-m-d', $cell['date']);
                            $label = $dateLabel->format('j F').($summary ? ', '.number_format($kg, 1).' kg, '.$records.' feedings' : ', no feed records');
                            if ($summary && $records < 6) $label .= ', missing feeding';
                        @endphp
                        <td class="align-top">
                            <button type="button" class="feed-calendar-day rounded-lg border border-[#e6e6e6] {{ $tint }} {{ $cell['current'] ? '' : 'opacity-55' }}" data-calendar-date="{{ $cell['date'] }}" @if($summary) data-detail-url="{{ route('feed.consumption.day', ['date' => $cell['date']]) }}" @endif aria-label="{{ $label }}" @if(!$summary) aria-disabled="true" tabindex="-1" @endif @if($cell['date'] === $today) aria-current="date" @endif>
                                <span class="flex w-full items-start justify-between gap-1"><span class="feed-calendar-day-number {{ $cell['current'] ? 'text-[#333333]' : 'text-[#9CA3AF]' }}">{{ $cell['day'] }}</span>@if($summary && $records < 6)<span class="h-2 w-2 shrink-0 rounded-full bg-amber-600" title="Missing feeding" aria-label="Missing feeding"></span>@endif</span>
                                @if($summary)<span class="feed-calendar-amount mt-auto font-bold text-[#14532d]">{{ number_format($kg, 1) }}</span>@else<span class="calendar-day-placeholder mt-auto block text-xs text-[#9CA3AF]">—</span>@endif
                            </button>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </x-production-calendar-grid>
    </div>
</section>

<script>
function feedCalendarChangeMonth(month) {
    var year = document.getElementById('feedCalendarYear').value;
    feedCalendarNavigate(year + '-' + String(month).padStart(2, '0'));
}
function feedCalendarChangeYear(year) {
    var month = document.getElementById('feedCalendarMonth').value.padStart(2, '0');
    feedCalendarNavigate(year + '-' + month);
}
function feedCalendarNavigate(month) {
    var url = new URL(window.location.href);
    url.searchParams.set('view', 'calendar');
    url.searchParams.set('tab', 'consumption');
    url.searchParams.delete('page');
    var maxMonth = '{{ $maxMonth }}';
    if (month > maxMonth) month = maxMonth;
    url.searchParams.set('month', month);
    window.history.pushState({}, '', url.toString());
    window.__feedActiveTab = null;
    if (window.feedReloadLiveData) window.feedReloadLiveData();
    else window.location.assign(url.toString());
}
(function () {
    if (window.__feedCalendarGridBound) return;
    window.__feedCalendarGridBound = true;
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-calendar-date][data-detail-url]');
        if (button && window.openFeedCalendarDay) window.openFeedCalendarDay(button);
    });
    document.addEventListener('keydown', function (event) {
        var button = event.target.closest('[data-calendar-date]');
        if (!button) return;
        var cells = Array.from(document.querySelectorAll('[data-calendar-date]'));
        var index = cells.indexOf(button);
        var delta = {ArrowLeft:-1, ArrowRight:1, ArrowUp:-7, ArrowDown:7}[event.key];
        if (delta !== undefined && cells.length) {
            event.preventDefault();
            var targetIndex = Math.max(0, Math.min(cells.length - 1, index + delta));
            while (targetIndex >= 0 && targetIndex < cells.length && cells[targetIndex].getAttribute('aria-disabled') === 'true') targetIndex += Math.sign(delta);
            if (cells[targetIndex]) cells[targetIndex].focus();
        } else if (event.key === 'Enter' || event.key === ' ') {
            if (button.hasAttribute('data-detail-url')) { event.preventDefault(); button.click(); }
        }
    });
})();
</script>
