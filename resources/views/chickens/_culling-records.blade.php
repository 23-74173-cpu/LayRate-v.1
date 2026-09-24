<turbo-frame id="chickens-culling-records">
    @if($cullingLogs->isEmpty())
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#9CA3AF]">No culling records found.</div>
    @else
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-[#FAFAFA] text-left text-xs font-semibold uppercase tracking-wider text-[#6B7280]">
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Hen ID</th>
                        <th class="px-3 py-2">Cage</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2">Notes</th>
                        <th class="px-3 py-2">By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cullingLogs as $log)
                    <tr class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]">
                        <td class="px-3 py-2 text-[#333]">{{ $log->cull_date->format('M d, Y') }}</td>
                        <td class="px-3 py-2 font-mono text-[#333]">{{ $log->hen->chicken_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#333]">{{ $log->hen?->cage?->cage_code ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <x-status-badge :status="$log->reason" type="culling" />
                        </td>
                        <td class="px-3 py-2 text-[#9CA3AF] max-w-32 truncate">{{ $log->notes ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#9CA3AF]">{{ $log->recorder?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-paginator :paginator="$cullingLogs" />
    </div>
    @endif
</turbo-frame>
