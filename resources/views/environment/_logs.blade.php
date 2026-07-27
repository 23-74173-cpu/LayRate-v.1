<turbo-frame id="environment-logs">
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-5 py-3 border-b border-[#D9D9D9]">
            <div class="text-xs tracking-wider text-[#6B7280]">ENVIRONMENT LOG HISTORY</div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('environment.logs') }}" data-turbo-frame="environment-logs" class="px-5 py-3 border-b border-[#D9D9D9] flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-[#6B7280] mb-1">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="border border-[#D9D9D9] rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30">
            </div>
            <div>
                <label class="block text-xs text-[#6B7280] mb-1">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="border border-[#D9D9D9] rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30">
            </div>
            <div>
                <label class="block text-xs text-[#6B7280] mb-1">Cage</label>
                <select name="cage_id"
                        class="border border-[#D9D9D9] rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30">
                    <option value="">All Cages</option>
                    @foreach($cages as $id => $code)
                    <option value="{{ $id }}" {{ request('cage_id') == $id ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="text-xs px-4 py-1.5 rounded-lg font-medium text-white transition-colors" style="background-color:#0075de;">Filter</button>
            @if(request()->hasAny(['date_from','date_to','cage_id']))
            <a href="{{ route('environment.logs') }}" data-turbo-frame="environment-logs" class="text-xs px-4 py-1.5 rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] transition-colors">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Time</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Avg Temp</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Avg Humidity</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryLogs as $log)
                @php
                    $s = \App\Services\EnvironmentStatusService::summary($log->avg_temp, $log->avg_hum, $thresholds);
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                    <td class="px-5 py-3.5 text-sm text-[#333333] font-mono">{{ $log->time_slot }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $log->avg_temp }}°C</td>
                    <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $log->avg_hum }}%</td>
                    <td class="px-5 py-3">
                        <x-status-badge :status="$s" type="sensor" />
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-[#6B7280]">No environmental data recorded yet.</td></tr>
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
