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
@endphp

<turbo-frame id="production-calendar">
<div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
    {{-- Month / Year header with navigation --}}
    <div class="mb-5">
        <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">Production Calendar</div>

        {{-- Row 1: month/year (the select IS the display — tapping it opens the
             picker directly, no separate static label duplicating the same text)
             + Prev/Next, right-aligned, same row. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('forecast') }}" class="flex items-center gap-1" data-turbo-frame="production-calendar" data-turbo-action="advance">
                @foreach(request()->except(['month','year']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <select name="month" onchange="this.form.submit()" aria-label="Select month"
                        class="appearance-none bg-transparent border-none cursor-pointer text-xl font-semibold text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 rounded px-1 -ml-1">
                    @foreach($months as $index => $monthName)
                    <option value="{{ $index + 1 }}" {{ $calendarMonth->month == $index + 1 ? 'selected' : '' }}>{{ $monthName }}</option>
                    @endforeach
                </select>
                <select name="year" onchange="this.form.submit()" aria-label="Select year"
                        class="appearance-none bg-transparent border-none cursor-pointer text-xl font-semibold text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#002D5E]/30 rounded px-1">
                    @for($y = now()->year - 5; $y <= now()->year + 5; $y++)
                    <option value="{{ $y }}" {{ $calendarMonth->year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>

            <div class="flex items-center gap-2">
                <a href="{{ $prevUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
                <a href="{{ $nextUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        {{-- Row 2: Today + Clear Forecast, directly beneath the month/year/prev/next row. --}}
        <div class="flex items-center gap-2 mt-3">
            <a href="{{ $todayUrl }}" data-turbo-frame="production-calendar" data-turbo-action="advance" class="text-xs px-3 py-1.5 rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] hover:text-[#333333] transition-colors">
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
                <h3 class="text-base font-semibold text-[#333333]">Forecast this day</h3>
            </div>
            <button type="button" id="closeForecastDayModal" class="text-[#6B7280] hover:text-[#333333] transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-5">
            <p class="text-sm text-[#333333] mb-1">Generate a single-day egg production forecast for</p>
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
                <input type="hidden" name="horizon" value="1">
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

    window.openForecastDayModal = function(dateString) {
        const modal = document.getElementById('forecastDayModal');
        const dateDisplay = document.getElementById('forecastDayModalDate');
        const startInput = document.getElementById('forecastDayModalStartDate');
        if (!modal || !dateDisplay || !startInput) return;
        startInput.value = dateString;
        dateDisplay.textContent = formatAlertDate(dateString);
        modal.style.display = 'flex';
        if (window.lucide) lucide.createIcons();
    };

    window.handleForecastDayClick = function(dateString, isSelectable) {
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

    window.openForecastDayModal = function(dateString) {
        const modal = document.getElementById('forecastDayModal');
        const dateDisplay = document.getElementById('forecastDayModalDate');
        const startInput = document.getElementById('forecastDayModalStartDate');
        if (!modal || !dateDisplay || !startInput) return;
        startInput.value = dateString;
        dateDisplay.textContent = formatAlertDate(dateString);
        setDayScope('{{ $scope }}');
        modal.style.display = 'flex';
        if (window.lucide) lucide.createIcons();
    };

    // Close modal on backdrop click — use document delegation so it works
    // even after the frame content is replaced (scripts don't re-execute).
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('forecastDayModal');
        if (!modal) return;
        if (e.target.closest('#closeForecastDayModal, #cancelForecastDayModal')) {
            modal.style.display = 'none';
            return;
        }
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    document.addEventListener('submit', function(e) {
        if (e.target.id !== 'forecastDayForm') return;
        e.preventDefault();
        const form = e.target;
        const overlay = document.getElementById('forecastLoadingOverlay');
        if (!overlay) return;
        overlay.style.display = 'flex';

        const scopeInput = form.querySelector('input[name="scope"]');
        const scope = scopeInput ? scopeInput.value : 'cage';
        const expectedDuration = window.resolveForecastDuration(scope, 1);

        sessionStorage.setItem('layrate_forecast_start_time', Date.now());
        window.startForecastProgress(expectedDuration);

        setTimeout(function() { form.submit(); }, 80);
    });
</script>
