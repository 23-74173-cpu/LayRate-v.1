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

    $forecastMap = collect($forecasts ?? [])->keyBy(fn($f) => $f->target_date->format('Y-m-d'));
    $weekdays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    $prevMonthDate = $calendarMonth->copy()->subMonthNoOverflow();
    $nextMonthDate = $calendarMonth->copy()->addMonthNoOverflow();
    $prevUrl = request()->fullUrlWithQuery(['month' => $prevMonthDate->month, 'year' => $prevMonthDate->year]);
    $nextUrl = request()->fullUrlWithQuery(['month' => $nextMonthDate->month, 'year' => $nextMonthDate->year]);
    $todayUrl = request()->fullUrlWithQuery(['month' => now()->month, 'year' => now()->year]);
@endphp

<turbo-frame id="production-calendar">
<div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
    {{-- Month / Year header with navigation --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div>
            <div class="text-xs tracking-wider text-[#6B7280]">PRODUCTION CALENDAR</div>
            <div class="text-xl font-semibold text-[#333333]">{{ $calendarMonth->format('F Y') }}</div>
        </div>

        <div class="flex items-center gap-2">
            {{-- Month selector --}}
            <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2" data-turbo-frame="production-calendar" data-turbo-action="advance">
                @foreach(request()->except(['month','year']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <select name="month" onchange="this.form.submit()"
                        class="border border-[#D9D9D9] rounded-lg px-2 py-1.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                    @foreach($months as $index => $monthName)
                    <option value="{{ $index + 1 }}" {{ $calendarMonth->month == $index + 1 ? 'selected' : '' }}>{{ $monthName }}</option>
                    @endforeach
                </select>
                <select name="year" onchange="this.form.submit()"
                        class="border border-[#D9D9D9] rounded-lg px-2 py-1.5 text-sm bg-white focus:outline-none focus:border-[#002D5E]">
                    @for($y = now()->year - 5; $y <= now()->year + 5; $y++)
                    <option value="{{ $y }}" {{ $calendarMonth->year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>

            {{-- Prev / Next arrows --}}
                <a href="{{ $prevUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
                <a href="{{ $nextUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
                <a href="{{ $todayUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="text-xs px-3 py-1.5 rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    Today
                </a>
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
                    <th class="text-center text-[10px] sm:text-xs font-semibold text-[#6B7280] uppercase tracking-wider py-2 px-1 bg-[#F9F9F7] rounded">
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
                        $calendarCells->push([
                            'day' => $prevMonth->daysInMonth - $i,
                            'currentMonth' => false,
                            'dateString' => null,
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
                    for ($day = 1; $day <= $endOffset; $day++) {
                        $calendarCells->push([
                            'day' => $day,
                            'currentMonth' => false,
                            'dateString' => null,
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
                        <div class="calendar-day rounded-lg border {{ $cell['currentMonth'] ? ($cell['isToday'] ? 'border-[#002D5E] bg-[#002D5E]/5' : 'border-[#F0F0F0] bg-white hover:border-[#D9D9D9]') : 'border-[#F0F0F0] bg-[#F9F9F7] opacity-60' }} relative flex flex-col items-start justify-start transition-colors">
                            <span class="text-xs sm:text-sm {{ $cell['isToday'] ? 'font-bold text-[#002D5E]' : ($cell['currentMonth'] ? 'text-[#333333]' : 'text-[#9CA3AF]') }}">{{ $cell['day'] }}</span>
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

    {{-- Week summary footer --}}
    <div class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="bg-[#F9F9F7] rounded-lg p-3 text-center">
            <div class="text-xs text-[#6B7280]">Weeks in month</div>
            <div class="text-lg font-semibold text-[#333333]">{{ $weeksInMonth }}</div>
        </div>
        <div class="bg-[#F9F9F7] rounded-lg p-3 text-center">
            <div class="text-xs text-[#6B7280]">Days in month</div>
            <div class="text-lg font-semibold text-[#333333]">{{ $daysInMonth }}</div>
        </div>
        <div class="bg-[#F9F9F7] rounded-lg p-3 text-center">
            <div class="text-xs text-[#6B7280]">Forecast days</div>
            <div class="text-lg font-semibold text-[#333333]">{{ count($forecastMap) }}</div>
        </div>
        <div class="bg-[#F9F9F7] rounded-lg p-3 text-center">
            <div class="text-xs text-[#6B7280]">Current week</div>
            <div class="text-lg font-semibold text-[#333333]">{{ $calendarToday->weekOfMonth }}</div>
        </div>
    </div>
</div>
</turbo-frame>

<script>
    if (window.lucide) lucide.createIcons();
</script>
