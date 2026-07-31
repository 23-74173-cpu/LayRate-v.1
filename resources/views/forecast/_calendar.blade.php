@php
    $calendarToday = now();
    $calendarMonth = $calendarDate->copy();
    $monthStart = $calendarMonth->copy()->startOfMonth();
    $monthEnd = $calendarMonth->copy()->endOfMonth();
    $daysInMonth = $calendarMonth->daysInMonth;

    // Monday-first calendar: Monday = 0, Sunday = 6
    $startOffset = ($monthStart->dayOfWeek + 6) % 7;
    $totalCells = $startOffset + $daysInMonth;
    $endOffset = (7 - ($totalCells % 7)) % 7;
    $weeksInMonth = ($totalCells + $endOffset) / 7;

    $forecastMap = collect($forecasts ?? [])->keyBy(fn($f) => is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date);
    $weekdays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    $prevMonthDate = $calendarMonth->copy()->subMonthNoOverflow();
    $nextMonthDate = $calendarMonth->copy()->addMonthNoOverflow();
    $prevUrl = request()->fullUrlWithQuery(['month' => $prevMonthDate->month, 'year' => $prevMonthDate->year]);
    $nextUrl = request()->fullUrlWithQuery(['month' => $nextMonthDate->month, 'year' => $nextMonthDate->year]);
    $todayUrl = request()->fullUrlWithQuery(['month' => now()->month, 'year' => now()->year]);

    $maxSelectableDate = \App\Forecast\ForecastRules::maxStartDate()->format('Y-m-d');
    $tomorrowDate = \App\Forecast\ForecastRules::minStartDate()->format('Y-m-d');

    // Calendar navigation is bounded to the present and future only:
    //   • Year filter: current year always; the next year only when viewing
    //     December (year end) or already viewing next year — no other years.
    //   • Month filter: only the current month and future months. For the next
    //     year, every month is in the future, so all 12 are offered.
    $todayYear  = (int) now()->format('Y');
    $todayMonth = (int) now()->format('n');
    $viewYear   = (int) $calendarMonth->format('Y');
    $viewMonth  = (int) $calendarMonth->format('n');

    $yearOptions = [$todayYear];
    if ($viewMonth === 12 || $viewYear === $todayYear + 1) {
        $yearOptions[] = $todayYear + 1;
    }
    $yearOptions = array_values(array_unique($yearOptions));

    $monthOptions = $viewYear === $todayYear + 1 ? range(1, 12) : range($todayMonth, 12);

    // The earliest month the calendar can show is the current month, so the
    // previous-month arrow is disabled for the current month (and any past month
    // reached via a crafted URL).
    $isAtOrBeforeCurrentMonth = ! $calendarMonth->gt(now()->startOfMonth());
@endphp

<turbo-frame id="production-calendar">
<div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
    {{-- Month / Year header with navigation --}}
    <div class="mb-5">
        <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Production Calendar</div>

        {{-- Month/Year filter pills + Today + Clear Forecast, with Prev/Next
             right-aligned — all on one row. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('forecast') }}" class="flex items-center gap-2" data-turbo-frame="production-calendar" data-turbo-action="advance">
                @foreach(request()->except(['month','year']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="relative">
                    <select name="month" onchange="this.form.submit()" aria-label="Select month"
                            class="appearance-none cursor-pointer rounded-lg border border-[#D9D9D9] bg-white py-1.5 px-4 text-center text-sm font-semibold text-[#333333] shadow-sm transition-colors hover:border-[#002D5E]/40 hover:bg-[#F5F6F8] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30">
                        @foreach($monthOptions as $m)
                        <option value="{{ $m }}" {{ $calendarMonth->month == $m ? 'selected' : '' }}>{{ $months[$m - 1] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative">
                    <select name="year" onchange="this.form.submit()" aria-label="Select year"
                            class="appearance-none cursor-pointer rounded-lg border border-[#D9D9D9] bg-white py-1.5 px-4 min-w-24 text-center text-sm font-semibold text-[#333333] shadow-sm transition-colors hover:border-[#002D5E]/40 hover:bg-[#F5F6F8] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30">
                        @foreach($yearOptions as $y)
                        <option value="{{ $y }}" {{ $calendarMonth->year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                </form>

                <a href="{{ $todayUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="text-xs min-w-16 text-center px-3 py-1.5 rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    Today
                </a>

                @can('admin')
                <form method="POST" action="{{ route('forecast.clear') }}" class="inline" data-confirm="Clear all forecast badges from the calendar for the current selection?" data-confirm-action="Clear" data-confirm-severity="neutral">
                    @csrf
                    <input type="hidden" name="scope" value="{{ $scope }}">
                    @if($scope === 'cage')
                    <input type="hidden" name="cage" value="{{ $cageCode }}">
                    @elseif($scope === 'breed')
                    <input type="hidden" name="breed" value="{{ $breed }}">
                    @endif
                    <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-[#D9D9D9] text-red-600 hover:bg-red-50 hover:border-red-200 transition-colors flex items-center gap-1.5">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        Clear Forecast
                    </button>
                </form>
                @endcan
            </div>

            <div class="flex items-center gap-2">
                @if($isAtOrBeforeCurrentMonth)
                <span class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#F0F0F0] bg-[#F9F9F7] text-[#D9D9D9] cursor-not-allowed transition-colors" title="Past months are not available">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </span>
                @else
                <a href="{{ $prevUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
                @endif
                <a href="{{ $nextUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- Mobile-friendly calendar styles --}}
    <style>
        .production-calendar { table-layout: fixed; border-collapse: separate; border-spacing: 4px; width: 100%; min-width: 320px; }
        .production-calendar th,
        .production-calendar td { width: 14.285%; }
        .production-calendar .calendar-day { height: 64px; padding: 6px; }
        .production-calendar .calendar-day .forecast-badge { font-size: 9px; padding: 2px 4px; }
        @media (min-width: 640px) {
            .production-calendar { border-spacing: 8px; min-width: 100%; }
            .production-calendar .calendar-day { height: 96px; padding: 8px; }
            .production-calendar .calendar-day .forecast-badge { font-size: 11px; padding: 2px 6px; }
        }
    </style>

    {{-- Calendar table: thead = weekdays (Mon–Sun), tbody = week rows, td = day columns --}}
    <div class="overflow-x-auto -mx-1 px-1">
        <table class="production-calendar">
            <thead>
                <tr>
                    @foreach($weekdays as $dayLabel)
                    <th class="text-center text-xs sm:text-xs font-semibold text-[#6B7280] uppercase tracking-wider py-2 px-1 bg-[#F9F9F7] rounded">
                        <span class="hidden sm:inline">{{ $dayLabel }}</span>
                        <span class="sm:hidden">{{ substr($dayLabel, 0, 1) }}</span>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $calendarCells = collect();

                    // Leading days from previous month
                    $prevMonth = $calendarMonth->copy()->subMonthNoOverflow();
                    for ($i = $startOffset - 1; $i >= 0; $i--) {
                        $day = $prevMonth->daysInMonth - $i;
                        $dateString = $prevMonth->copy()->setDay($day)->format('Y-m-d');
                        $calendarCells->push([
                            'day' => $day,
                            'currentMonth' => false,
                            'dateString' => $dateString,
                            'isToday' => false,
                            'forecast' => null,
                        ]);
                    }

                    // Current month days
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $dateString = $calendarMonth->copy()->setDay($day)->format('Y-m-d');
                        $calendarCells->push([
                            'day' => $day,
                            'currentMonth' => true,
                            'dateString' => $dateString,
                            'isToday' => $dateString === $calendarToday->format('Y-m-d'),
                            'forecast' => $forecastMap[$dateString] ?? null,
                        ]);
                    }

                    // Trailing days from next month
                    $nextMonth = $calendarMonth->copy()->addMonthNoOverflow();
                    for ($day = 1; $day <= $endOffset; $day++) {
                        $dateString = $nextMonth->copy()->setDay($day)->format('Y-m-d');
                        $calendarCells->push([
                            'day' => $day,
                            'currentMonth' => false,
                            'dateString' => $dateString,
                            'isToday' => false,
                            'forecast' => null,
                        ]);
                    }

                    $calendarWeeks = $calendarCells->chunk(7);
                @endphp

                @foreach($calendarWeeks as $week)
                <tr>
                    @foreach($week as $cell)
                    <td class="align-top">
                        @php
                            $isSelectable = $cell['currentMonth'] && $cell['dateString'] && $cell['dateString'] >= $tomorrowDate && $cell['dateString'] <= $maxSelectableDate;
                            $hasForecast = !empty($cell['forecast']);
                            $baseClasses = 'calendar-day rounded-lg border relative flex flex-col items-start justify-start transition-colors';
                            if (!$cell['currentMonth']) {
                                $dayClasses = $baseClasses . ' border-[#F0F0F0] bg-[#F9F9F7] opacity-60';
                            } elseif ($hasForecast) {
                                $dayClasses = $baseClasses . ' border-[#A8D5A2] bg-[#EBF5E9] ' . ($isSelectable ? 'hover:bg-[#D5E8D4]/60 cursor-pointer' : 'cursor-not-allowed');
                            } elseif ($cell['isToday']) {
                                $dayClasses = $baseClasses . ' border-[#002D5E] bg-[#002D5E]/5 cursor-not-allowed';
                            } else {
                                $dayClasses = $baseClasses . ' border-[#F0F0F0] bg-white ' . ($isSelectable ? 'hover:border-[#002D5E] cursor-pointer' : 'hover:border-[#D9D9D9] cursor-not-allowed');
                            }
                            $dayNumberClasses = 'text-xs sm:text-sm ';
                            if ($cell['isToday']) {
                                $dayNumberClasses .= 'font-bold text-[#002D5E]';
                            } elseif ($hasForecast) {
                                $dayNumberClasses .= 'font-semibold text-[#1F5F35]';
                            } elseif ($cell['currentMonth']) {
                                $dayNumberClasses .= 'text-[#333333]';
                            } else {
                                $dayNumberClasses .= 'text-[#9CA3AF]';
                            }
                        @endphp
                        <div class="{{ $dayClasses }}"
                             data-date="{{ $cell['dateString'] }}"
                             data-selectable="{{ $isSelectable ? 'true' : 'false' }}"
                             onclick="window.handleForecastDayClick('{{ $cell['dateString'] }}', {{ $isSelectable ? 'true' : 'false' }})">
                            {{-- No separate "Today" badge here — the cell's own border,
                                 background tint, and bold day-number already mark today
                                 unambiguously; a fourth label repeated the same signal. --}}
                            <div class="flex items-center gap-1">
                                <span class="{{ $dayNumberClasses }}">{{ $cell['day'] }}</span>
                            </div>
                            @if($cell['forecast'])
                            <i data-lucide="egg" class="absolute top-1 right-1 w-3.5 h-3.5 sm:w-4 sm:h-4 text-[#2D7D46]" title="Forecast: {{ number_format($cell['forecast']->predicted_egg_count, 0) }} eggs"></i>
                            <span class="forecast-badge mt-auto rounded bg-[#D5E8D4] text-[#1F5F35] font-medium whitespace-nowrap">
                                {{ number_format($cell['forecast']->predicted_egg_count, 0) }}
                            </span>
                            @endif
                        </div>
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

{{-- Single-day forecast modal --}}
<div id="forecastDayModal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-auto overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#F0F0F0]">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-[#002D5E]/10 flex items-center justify-center">
                    <i data-lucide="calendar-plus" class="w-4 h-4 text-[#002D5E]"></i>
                </div>
                <h3 id="forecastDayModalTitle" class="text-base font-semibold text-[#333333]">Forecast this day</h3>
            </div>
            <button type="button" id="closeForecastDayModal" class="text-[#6B7280] hover:text-[#333333] transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-5">
            <p id="forecastDayModalHint" class="text-sm text-[#333333] mb-1">Generate a single-day egg production forecast for</p>
            <p class="text-lg font-semibold text-[#002D5E] mb-4" id="forecastDayModalDate">—</p>

            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">Scope</label>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" data-day-scope="farm" class="day-scope-btn flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium border transition-colors">
                        <i data-lucide="globe" class="w-3.5 h-3.5"></i> Whole Farm
                    </button>
                    <button type="button" data-day-scope="cage" class="day-scope-btn flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium border transition-colors">
                        <i data-lucide="box" class="w-3.5 h-3.5"></i> Per Cage
                    </button>
                    <button type="button" data-day-scope="breed" class="day-scope-btn flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium border transition-colors">
                        <i data-lucide="bird" class="w-3.5 h-3.5"></i> Per Breed
                    </button>
                </div>

                <div id="dayScopeCage" class="hidden mt-3">
                    <label class="block text-sm text-[#333333] mb-1.5">Select Cage</label>
                    <select id="dayCageSelect"
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                        @foreach($allCages as $c)
                        <option value="{{ $c }}" {{ $c === $cageCode ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="dayScopeBreed" class="hidden mt-3">
                    <label class="block text-sm text-[#333333] mb-1.5">Select Breed</label>
                    <select id="dayBreedSelect"
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                        @foreach($allBreeds as $b)
                        <option value="{{ $b }}" {{ $b === $breed ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @can('admin')
            <form method="POST" action="{{ route('forecast.generate') }}" id="forecastDayForm" data-turbo="false">
                @csrf
                <input type="hidden" name="scope" id="dayScopeInput" value="{{ $scope }}">
                <input type="hidden" name="horizon" id="forecastDayHorizon" value="1">
                <input type="hidden" name="start_date" id="forecastDayModalStartDate" value="">
                <input type="hidden" name="cage" id="dayCageInput" value="{{ $scope === 'cage' ? $cageCode : 'ALL' }}">
                <input type="hidden" name="breed" id="dayBreedInput" value="{{ $scope === 'breed' ? ($breed ?? '') : '' }}">
                <button type="submit" class="w-full bg-[#002D5E] text-white py-3 rounded-lg text-sm font-medium hover:bg-[#001F42] transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>Generate Forecast</span>
                </button>
            </form>
            @endcan
            <button type="button" id="cancelForecastDayModal" class="w-full mt-3 border border-[#D9D9D9] text-[#6B7280] py-2.5 rounded-lg text-sm font-medium hover:bg-[#F5F6F8] transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>
</turbo-frame>

<script>
    if (window.lucide) lucide.createIcons();

    function parseLocalDate(dateString) {
        const [y, m, d] = dateString.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    function formatAlertDate(dateString) {
        const date = parseLocalDate(dateString);
        return date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }

    function closeForecastModal() {
        const modal = document.getElementById('forecastDayModal');
        if (modal) modal.style.display = 'none';
    }

    window.handleForecastDayClick = function(dateString, isSelectable) {
        // A drag-to-select ends with a native click on the hovered cell; swallow
        // that click so it doesn't also open the single-day modal.
        if (Date.now() - suppressNextDayClick < 350) {
            suppressNextDayClick = 0;
            return;
        }

        if (isSelectable) {
            window.openForecastDayModal(dateString);
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const maxDate = new Date(today);
        maxDate.setDate(today.getDate() + 30);
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);

        const clicked = parseLocalDate(dateString);
        let message = '';

        if (clicked < tomorrow) {
            message = 'Forecasting is only available for future dates. Please select a date starting from tomorrow.';
        } else if (clicked > maxDate) {
            message = 'Custom forecasts can only be generated up to 30 days from today (' +
                      maxDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) +
                      '). Please select a date within this range.';
        } else {
            message = 'This date cannot be forecast. Please select a date between tomorrow and 30 days from today.';
        }

        showNotification(message, 'warning');
    };

    // ── Drag-to-select a contiguous day range (Egg Logging style, range-fill) ──
    // The selection is always the full date window between the anchor and the
    // hovered cell, so the chosen days are guaranteed to be a continuous
    // ascending sequence (5 → 6 → 7) with no skipped dates.
    let isDayDragging = false;
    let dayDragMoved = false;
    let dayDragAnchor = null;   // 'Y-m-d'
    let dayDragHover = null;    // 'Y-m-d'
    let dayDragOriginalClasses = {}; // date -> original className for restore
    let suppressNextDayClick = 0;    // timestamp; self-heals if no click follows

    function snapshotSelectableDayClasses() {
        dayDragOriginalClasses = {};
        document.querySelectorAll('.calendar-day[data-selectable="true"]').forEach(function(el) {
            dayDragOriginalClasses[el.dataset.date] = el.className;
        });
    }

    function clearDaySelection() {
        isDayDragging = false;
        dayDragAnchor = null;
        dayDragHover = null;
        Object.keys(dayDragOriginalClasses).forEach(function(date) {
            const el = document.querySelector('.calendar-day[data-date="' + date + '"]');
            if (el) el.className = dayDragOriginalClasses[date];
        });
        dayDragOriginalClasses = {};
    }

    function dayDateToMs(dateString) {
        const [y, m, d] = dateString.split('-').map(Number);
        return Date.UTC(y, m - 1, d);
    }

    function selectedDayRange() {
        if (!dayDragAnchor || !dayDragHover) return [];
        const from = Math.min(dayDateToMs(dayDragAnchor), dayDateToMs(dayDragHover));
        const to = Math.max(dayDateToMs(dayDragAnchor), dayDateToMs(dayDragHover));
        const dates = [];
        for (let ms = from; ms <= to; ms += 86400000) {
            const d = new Date(ms);
            dates.push(d.getUTCFullYear() + '-' +
                       String(d.getUTCMonth() + 1).padStart(2, '0') + '-' +
                       String(d.getUTCDate()).padStart(2, '0'));
        }
        return dates;
    }

    function setDayCellSelected(date, selected) {
        const el = document.querySelector('.calendar-day[data-date="' + date + '"]');
        if (!el) return;
        if (selected) {
            el.classList.add('ring-2', 'ring-[#002D5E]', 'ring-offset-1', 'bg-[#002D5E]/10');
        } else {
            el.classList.remove('ring-2', 'ring-[#002D5E]', 'ring-offset-1', 'bg-[#002D5E]/10');
        }
    }

    function renderDaySelection() {
        Object.keys(dayDragOriginalClasses).forEach(function(date) {
            const el = document.querySelector('.calendar-day[data-date="' + date + '"]');
            if (el) el.className = dayDragOriginalClasses[date];
        });
        selectedDayRange().forEach(function(date) { setDayCellSelected(date, true); });
    }

    function onCalendarDayMouseDown(e) {
        if (e.button !== 0) return;
        const el = e.currentTarget;
        if (el.dataset.selectable !== 'true') return;
        e.preventDefault(); // prevent text-selection while dragging
        isDayDragging = true;
        dayDragMoved = false;
        dayDragAnchor = el.dataset.date;
        dayDragHover = el.dataset.date;
        snapshotSelectableDayClasses();
        renderDaySelection();
    }

    function onCalendarDayMouseEnter(e) {
        if (!isDayDragging) return;
        const el = e.currentTarget;
        if (el.dataset.selectable !== 'true') return;
        if (el.dataset.date !== dayDragHover) {
            dayDragHover = el.dataset.date;
            dayDragMoved = true;
            renderDaySelection();
        }
    }

    function isContiguousRange(dates) {
        for (let i = 1; i < dates.length; i++) {
            if (dayDateToMs(dates[i]) - dayDateToMs(dates[i - 1]) !== 86400000) return false;
        }
        return true;
    }

    function onCalendarDayGlobalMouseUp() {
        if (!isDayDragging) return;
        isDayDragging = false;
        const dates = selectedDayRange();
        if (dates.length === 0) {
            clearDaySelection();
            return;
        }

        // Validation: the chosen dates must form a continuous sequence.
        if (!isContiguousRange(dates)) {
            showNotification('Please select dates in a continuous sequence — you cannot skip a day.', 'warning');
            clearDaySelection();
            return;
        }

        suppressNextDayClick = Date.now();
        if (dayDragMoved) {
            window.openForecastRangeModal(dates);
        } else {
            window.openForecastDayModal(dates[0]);
        }
    }

    // Wire drag handlers on the current calendar cells (flag-guarded so
    // re-wires don't double-bind) and bind the global mouseup exactly once.
    // Re-run after every Turbo frame render of #production-calendar, because
    // this script lives outside the <turbo-frame> (its scripts do not
    // re-execute when the frame contents are replaced).
    window.__calendarDayDragInit = function() {
        document.querySelectorAll('.calendar-day[data-selectable="true"]').forEach(function(el) {
            if (!el.__dragWired) {
                el.__dragWired = true;
                el.addEventListener('mousedown', onCalendarDayMouseDown);
                el.addEventListener('mouseenter', onCalendarDayMouseEnter);
            }
        });
        if (!window.__calendarDayDragBound) {
            window.__calendarDayDragBound = true;
            document.addEventListener('mouseup', onCalendarDayGlobalMouseUp);
        }
    };

    if (!window.__calendarDayFrameLoadBound) {
        window.__calendarDayFrameLoadBound = true;
        document.addEventListener('turbo:frame-load', function(e) {
            if (e.target && e.target.id === 'production-calendar' && window.__calendarDayDragInit) {
                window.__calendarDayDragInit();
            }
        });
    }

    window.__calendarDayDragInit();

    function formatAlertRange(startDate, endDate) {
        if (startDate === endDate) return formatAlertDate(startDate);
        const start = parseLocalDate(startDate);
        const end = parseLocalDate(endDate);
        const sameMonth = start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear();
        const s = sameMonth
            ? start.toLocaleDateString('en-US', { month: 'long' }) + ' ' + start.getDate()
            : start.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
        return s + ' \u2013 ' + end.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function openForecastModal(dates) {
        const modal = document.getElementById('forecastDayModal');
        const dateDisplay = document.getElementById('forecastDayModalDate');
        const startInput = document.getElementById('forecastDayModalStartDate');
        const horizonInput = document.getElementById('forecastDayHorizon');
        const title = document.getElementById('forecastDayModalTitle');
        const hint = document.getElementById('forecastDayModalHint');
        if (!modal || !dateDisplay || !startInput) return;

        dates.sort();
        const first = dates[0];
        const last = dates[dates.length - 1];
        const isRange = dates.length > 1;

        startInput.value = first;
        if (horizonInput) horizonInput.value = dates.length;
        dateDisplay.textContent = isRange ? formatAlertRange(first, last) : formatAlertDate(first);
        if (title) title.textContent = isRange ? 'Forecast this range' : 'Forecast this day';
        if (hint) {
            hint.textContent = isRange
                ? 'Generate a ' + dates.length + '-day egg production forecast for'
                : 'Generate a single-day egg production forecast for';
        }

        syncDaySelectsFromWorkspace();
        setDayScope(getLiveForecastScope());
        modal.style.display = 'flex';
        if (window.lucide) lucide.createIcons();
    }

    window.openForecastDayModal = function(dateString) { openForecastModal([dateString]); };
    window.openForecastRangeModal = function(dates) { openForecastModal(dates); };

    function setDayScope(scope) {
        const scopeInput = document.getElementById('dayScopeInput');
        const cageInput = document.getElementById('dayCageInput');
        const breedInput = document.getElementById('dayBreedInput');
        const cageWrap = document.getElementById('dayScopeCage');
        const breedWrap = document.getElementById('dayScopeBreed');

        document.querySelectorAll('.day-scope-btn').forEach(function(btn) {
            const isActive = btn.dataset.dayScope === scope;
            btn.classList.toggle('bg-[#002D5E]', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('border-[#002D5E]', isActive);
            btn.classList.toggle('border-[#D9D9D9]', !isActive);
            btn.classList.toggle('text-[#6B7280]', !isActive);
            btn.classList.toggle('hover:bg-[#F5F6F8]', !isActive);
        });

        if (scopeInput) scopeInput.value = scope;
        if (cageWrap) cageWrap.classList.toggle('hidden', scope !== 'cage');
        if (breedWrap) breedWrap.classList.toggle('hidden', scope !== 'breed');

        const cageSelect = document.getElementById('dayCageSelect');
        const breedSelect = document.getElementById('dayBreedSelect');

        if (cageInput) {
            if (scope === 'cage') {
                cageInput.value = cageSelect ? cageSelect.value : 'ALL';
            } else {
                cageInput.value = 'ALL';
            }
        }
        if (breedInput) {
            breedInput.value = (scope === 'breed' && breedSelect) ? breedSelect.value : '';
        }
        if (window.lucide) lucide.createIcons();
    }

    // Delegate scope button clicks inside the modal
    if (!window.__dayScopeClickBound) {
        window.__dayScopeClickBound = true;
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.day-scope-btn');
            if (btn) {
                setDayScope(btn.dataset.dayScope);
            }
        });
    }

    // Delegate dropdown changes inside the modal
    if (!window.__dayScopeChangeBound) {
        window.__dayScopeChangeBound = true;
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'dayCageSelect') {
                const cageInput = document.getElementById('dayCageInput');
                if (cageInput) cageInput.value = e.target.value;
            }
            if (e.target && e.target.id === 'dayBreedSelect') {
                const breedInput = document.getElementById('dayBreedInput');
                if (breedInput) breedInput.value = e.target.value;
            }
        });
    }

    // The modal's scope should mirror the live selection in the Forecast Inputs panel.
    // #formScope is kept in sync by the workspace's in-place refresh, so the modal stays
    // locked to the current scope even after switching scope without a page reload.
    function getLiveForecastScope() {
        const el = document.getElementById('formScope');
        return el && el.value ? el.value : '{{ $scope }}';
    }

    function syncDaySelectsFromWorkspace() {
        const cageSelect = document.getElementById('dayCageSelect');
        const breedSelect = document.getElementById('dayBreedSelect');
        const wsCage = document.getElementById('cageSelect');
        const wsBreed = document.getElementById('breedSelect');

        if (cageSelect && wsCage) {
            const values = Array.from(cageSelect.options).map(function(o) { return o.value; });
            if (values.indexOf(wsCage.value) !== -1) cageSelect.value = wsCage.value;
        }
        if (breedSelect && wsBreed) {
            const values = Array.from(breedSelect.options).map(function(o) { return o.value; });
            if (values.indexOf(wsBreed.value) !== -1) breedSelect.value = wsBreed.value;
        }
    }

    // Close modal on backdrop click — use document delegation so it works
    // even after the frame content is replaced (scripts don't re-execute).
    if (!window.__forecastDayModalCloseBound) {
        window.__forecastDayModalCloseBound = true;
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('forecastDayModal');
            if (!modal) return;
            if (e.target.closest('#closeForecastDayModal, #cancelForecastDayModal')) {
                modal.style.display = 'none';
                clearDaySelection();
                return;
            }
            if (e.target === modal) {
                modal.style.display = 'none';
                clearDaySelection();
            }
        });
    }

    if (!window.__forecastDayFormSubmitBound) {
        window.__forecastDayFormSubmitBound = true;
        document.addEventListener('submit', function(e) {
            if (e.target.id !== 'forecastDayForm') return;
            e.preventDefault();
            const form = e.target;
            const overlay = document.getElementById('forecastLoadingOverlay');
            if (!overlay) return;
            overlay.style.display = 'flex';

            const scopeInput = form.querySelector('input[name="scope"]');
            const scope = scopeInput ? scopeInput.value : 'cage';
            const horizonInput = form.querySelector('input[name="horizon"]');
            const horizon = horizonInput ? (parseInt(horizonInput.value, 10) || 1) : 1;
            const expectedDuration = window.resolveForecastDuration(scope, horizon);

            sessionStorage.setItem('layrate_forecast_start_time', Date.now());
            window.startForecastProgress(expectedDuration);

            clearDaySelection();
            setTimeout(function() { form.submit(); }, 80);
        });
    }
</script>
