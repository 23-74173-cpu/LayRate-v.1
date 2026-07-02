<turbo-frame id="environment-logs">
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-5 py-3 border-b border-[#D9D9D9]">
            <div class="text-xs tracking-wider text-[#6B7280]">ENVIRONMENT LOG HISTORY</div>
        </div>
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
                    $s = ($log->avg_temp > 30 || $log->avg_hum > 70) ? 'Alert'
                       : (($log->avg_temp > 28.5 || $log->avg_hum >= 70) ? 'Warning' : 'Normal');
                    $sBg  = $s === 'Normal' ? '#D5E8D4' : ($s === 'Warning' ? '#FFF3CD' : '#F8D7DA');
                    $sTxt = $s === 'Normal' ? '#2D6A4F'  : ($s === 'Warning' ? '#856404' : '#721C24');
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                    <td class="px-5 py-3 text-sm text-[#333333] font-mono">{{ $log->time_slot }}</td>
                    <td class="px-5 py-3 text-sm text-[#333333]">{{ $log->avg_temp }}°C</td>
                    <td class="px-5 py-3 text-sm text-[#333333]">{{ $log->avg_hum }}%</td>
                    <td class="px-5 py-3">
                        <span class="text-xs px-2.5 py-1 rounded" style="background:{{ $sBg }};color:{{ $sTxt }}">{{ $s }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-6 text-center text-sm text-[#6B7280]">No environmental data recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</turbo-frame>
