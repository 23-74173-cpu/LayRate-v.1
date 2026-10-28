<turbo-frame id="environment-logs">
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-5 py-3 border-b border-[#D9D9D9]">
            <div class="text-xs tracking-wider text-[#6B7280]">ENVIRONMENT LOG HISTORY — DAILY AVERAGES</div>
        </div>

        {{-- Filters --}}
        <form id="environmentLogFilters" method="GET" action="{{ route('environment.logs') }}" data-turbo-frame="environment-logs" class="filter-legacy-form px-5 py-3 border-b border-[#D9D9D9]" aria-label="Filter environment log history">
            <button type="button" data-env-filter-toggle class="filter-control filter-icon-toggle inline-flex items-center justify-center rounded-lg border border-[#D9D9D9]" aria-label="Show filters" title="Show filters" aria-expanded="false" aria-controls="environmentLogFilterFields"><i data-lucide="sliders-horizontal" class="h-4 w-4" aria-hidden="true"></i></button>
            <span data-env-filter-summary class="ml-2 text-sm text-[#615d59]" aria-live="polite">{{ (filled(request('date_from')) ? 1 : 0) + (filled(request('date_to')) ? 1 : 0) + (filled(request('cage_id')) ? 1 : 0) ?: '' }} {{ (filled(request('date_from')) ? 1 : 0) + (filled(request('date_to')) ? 1 : 0) + (filled(request('cage_id')) ? 1 : 0) ? 'active filters' : '' }}</span>
            <span data-env-fields-home class="hidden"></span>
            <span data-filter-fields-home="environmentLogFilterFields" class="hidden"></span>
            <span data-filter-actions-home class="hidden"></span>
            <div id="environmentLogFilterFields" data-env-filter-fields hidden class="filter-legacy-fields mt-3 grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
            <div>
                <label for="environmentLogFrom" class="filter-label">From</label>
                <input id="environmentLogFrom" type="date" name="date_from" value="{{ request('date_from') }}"
                       class="filter-control w-full min-w-0 sm:w-auto border border-[#D9D9D9] rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-navy/30">
            </div>
            <div>
                <label for="environmentLogTo" class="filter-label">To</label>
                <input id="environmentLogTo" type="date" name="date_to" value="{{ request('date_to') }}"
                       class="filter-control w-full min-w-0 sm:w-auto border border-[#D9D9D9] rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-navy/30">
            </div>
            <div>
                <label for="environmentLogCage" class="filter-label">Cage</label>
                <select id="environmentLogCage" name="cage_id"
                        class="filter-control w-full min-w-0 sm:w-auto border border-[#D9D9D9] rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-navy/30">
                    <option value="">All Cages</option>
                    @foreach($cages as $id => $code)
                    <option value="{{ $id }}" {{ request('cage_id') == $id ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            </div>
            <div data-env-filter-actions data-filter-action class="filter-legacy-actions mt-3 flex justify-end gap-2">
                <button type="submit" class="filter-control filter-action text-center text-sm rounded-lg font-medium text-white transition-colors" style="background-color:var(--color-navy);">Filter</button>
                <a href="{{ route('environment.logs') }}" class="filter-control filter-action inline-flex items-center justify-center border border-[#D9D9D9] rounded-lg text-sm">Reset</a>
            </div>
        </form>

        <div class="mobile-wide-table overflow-x-auto">
        <table class="w-full max-md:min-w-[900px] mobile-sticky-first">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium" data-sticky-label>Date</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Cage</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Avg Temp</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Min–Max</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Avg Humidity</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Readings</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Status</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryLogs as $log)
                @php
                    $s = \App\Services\EnvironmentStatusService::summary($log->avg_temp, $log->avg_hum, $thresholds);
                    $cageCode = $cages[$log->cage_id] ?? 'Cage #'.$log->cage_id;
                @endphp
                <tr data-env-log-cage="{{ $log->cage_id }}" data-env-log-date="{{ $log->log_date }}" class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                    <td class="px-5 py-3.5 text-sm text-[#333333] font-mono max-md:whitespace-nowrap" data-sticky-label>{{ \App\Services\ReportingDateService::displayDate($log->log_date) }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ $cageCode }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $log->avg_temp }}°C</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $log->min_temp }}–{{ $log->max_temp }}°C</td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $log->avg_hum }}%</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $log->reading_count }}</td>
                    <td class="px-5 py-3">
                        <x-status-badge :status="$s" type="sensor" />
                    </td>
                    <td class="px-5 py-3.5">
                        <button type="button" onclick="openEnvLogOverride({{ $log->cage_id }}, '{{ $log->log_date }}', {{ $log->avg_temp }}, {{ $log->avg_hum }}, '{{ $cageCode }}')"
                                class="max-md:min-h-11 text-xs max-md:text-sm px-2.5 py-1 rounded border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] transition-colors">
                            Override
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-[#6B7280]">No environmental data recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if(method_exists($summaryLogs, 'links'))
        <div class="px-5 py-3 border-t border-[#D9D9D9]">
            {{ $summaryLogs->withQueryString()->links('components.paginator') }}
        </div>
        @endif
    </div>
</turbo-frame>
