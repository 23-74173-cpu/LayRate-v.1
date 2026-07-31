@php
    $calendarToday = now();
    $calendarMonth = $calendarMonth->copy();
    $monthStart = $calendarMonth->copy()->startOfMonth();
    $monthEnd = $calendarMonth->copy()->endOfMonth();
    $daysInMonth = $calendarMonth->daysInMonth;

    // Monday-first calendar: Monday = 0, Sunday = 6
    $startOffset = ($monthStart->dayOfWeek + 6) % 7;
    $totalCells = $startOffset + $daysInMonth;
    $endOffset = (7 - ($totalCells % 7)) % 7;
    $weeksInMonth = ($totalCells + $endOffset) / 7;

    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    $prevMonthDate = $calendarMonth->copy()->subMonthNoOverflow();
    $nextMonthDate = $calendarMonth->copy()->addMonthNoOverflow();
    $prevUrl = request()->fullUrlWithQuery(['month' => $prevMonthDate->month, 'year' => $prevMonthDate->year]);
    $nextUrl = request()->fullUrlWithQuery(['month' => $nextMonthDate->month, 'year' => $nextMonthDate->year]);
    $todayUrl = request()->fullUrlWithQuery(['month' => now()->month, 'year' => now()->year]);
@endphp

<turbo-frame id="dashboard-calendar">
<div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
    {{-- Month / Year header with navigation --}}
    <div class="mb-5">
        <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Daily Egg Logs</div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('dashboard.calendar') }}" class="flex items-center gap-2" data-turbo-frame="dashboard-calendar">
                @foreach(request()->except(['month', 'year', 'cage']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="relative">
                    <select name="cage" onchange="this.form.requestSubmit()" aria-label="Filter by cage"
                            class="appearance-none cursor-pointer rounded-lg border border-[#D9D9D9] bg-white py-1.5 pl-3 pr-8 text-sm font-semibold text-[#333333] shadow-sm transition-colors hover:border-[#002D5E]/40 hover:bg-[#F5F6F8] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30">
                        <option value="">All Cages</option>
                        @foreach($cageOptions as $c)
                        <option value="{{ $c->cage_code }}" {{ $cageCode === $c->cage_code ? 'selected' : '' }}>{{ $c->cage_code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative">
                    <select name="month" onchange="this.form.requestSubmit()" aria-label="Select month"
                            class="appearance-none cursor-pointer rounded-lg border border-[#D9D9D9] bg-white py-1.5 px-4 text-center text-sm font-semibold text-[#333333] shadow-sm transition-colors hover:border-[#002D5E]/40 hover:bg-[#F5F6F8] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30">
                        @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $calendarMonth->month == $m ? 'selected' : '' }}>{{ $months[$m - 1] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative">
                    <select name="year" onchange="this.form.requestSubmit()" aria-label="Select year"
                            class="appearance-none cursor-pointer rounded-lg border border-[#D9D9D9] bg-white py-1.5 px-4 min-w-24 text-center text-sm font-semibold text-[#333333] shadow-sm transition-colors hover:border-[#002D5E]/40 hover:bg-[#F5F6F8] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30">
                        @foreach($yearOptions as $y)
                        <option value="{{ $y }}" {{ $calendarMonth->year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                </form>

                <a href="{{ $todayUrl }}" data-turbo-frame="dashboard-calendar" class="text-xs min-w-16 text-center px-3 py-1.5 rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    Today
                </a>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ $prevUrl }}" data-turbo-frame="dashboard-calendar" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
                <a href="{{ $nextUrl }}" data-turbo-frame="dashboard-calendar" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        {{-- Month summary --}}
        <p class="text-xs mt-3" style="color: #6B7280;">
            @if($monthTotalEggs > 0)
            <strong style="color: #333333;">{{ $monthLoggedDays }}</strong> {{ Str::plural('day', $monthLoggedDays) }} logged ·
            <strong style="color: #333333;">{{ number_format($monthTotalEggs) }}</strong> eggs collected this month
            @else
            No logs recorded this month.
            @endif
        </p>
    </div>

    {{-- Mobile-friendly calendar styles --}}
    <style>
        .dashboard-calendar { table-layout: fixed; border-collapse: separate; border-spacing: 4px; width: 100%; min-width: 320px; }
        .dashboard-calendar th,
        .dashboard-calendar td { width: 14.285%; }
        .dashboard-calendar .calendar-day { height: 64px; padding: 6px; }
        .dashboard-calendar .calendar-day .calendar-badge { font-size: 9px; padding: 2px 4px; }
        @media (min-width: 640px) {
            .dashboard-calendar { border-spacing: 8px; min-width: 100%; }
            .dashboard-calendar .calendar-day { height: 96px; padding: 8px; }
            .dashboard-calendar .calendar-day .calendar-badge { font-size: 11px; padding: 2px 6px; }
        }
    </style>

    {{-- Calendar table: thead = weekdays (Mon–Sun), tbody = week rows, td = day columns --}}
    <div class="overflow-x-auto -mx-1 px-1">
        <table class="dashboard-calendar">
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
                        ]);
                    }

                    $calendarWeeks = $calendarCells->chunk(7);
                @endphp

                @foreach($calendarWeeks as $week)
                <tr>
                    @foreach($week as $cell)
                    <td class="align-top">
                        @php
                            $dayLog = $logs->get($cell['dateString']);
                            $hasLogs = ! empty($dayLog);
                            $baseClasses = 'calendar-day rounded-lg border relative flex flex-col items-start justify-start';
                            if (! $cell['currentMonth']) {
                                $dayClasses = $baseClasses . ' border-[#F0F0F0] bg-[#F9F9F7] opacity-60';
                            } elseif ($cell['isToday']) {
                                $dayClasses = $baseClasses . ' border-[#002D5E] bg-[#002D5E]/5';
                            } elseif ($hasLogs) {
                                $dayClasses = $baseClasses . ' border-[#BFDBFE] bg-[#EFF6FF]';
                            } else {
                                $dayClasses = $baseClasses . ' border-[#F0F0F0] bg-white';
                            }
                            $dayNumberClasses = 'text-xs sm:text-sm ';
                            if ($cell['isToday']) {
                                $dayNumberClasses .= 'font-bold text-[#002D5E]';
                            } elseif ($hasLogs) {
                                $dayNumberClasses .= 'font-semibold text-[#1E40AF]';
                            } elseif ($cell['currentMonth']) {
                                $dayNumberClasses .= 'text-[#333333]';
                            } else {
                                $dayNumberClasses .= 'text-[#9CA3AF]';
                            }
                        @endphp
                        <div class="{{ $dayClasses }}" data-date="{{ $cell['dateString'] }}">
                            <span class="{{ $dayNumberClasses }}">{{ $cell['day'] }}</span>
                            @if($hasLogs)
                            <i data-lucide="egg" class="absolute top-1 right-1 w-3.5 h-3.5 sm:w-4 sm:h-4 text-[#1E40AF]"
                               title="{{ $dayLog->logs }} {{ Str::plural('log', $dayLog->logs) }} · {{ number_format($dayLog->eggs) }} eggs"></i>
                            <span class="calendar-badge mt-auto rounded bg-[#DBEAFE] text-[#1E40AF] font-medium whitespace-nowrap">
                                {{ number_format($dayLog->eggs) }}
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
</turbo-frame>

<script>
if (window.lucide) lucide.createIcons();
</script>
