<turbo-frame id="chickens-culling-records">
    @if($cullingLogs->isEmpty())
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#9CA3AF]">No culling records found.</div>
    @else
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-[#FAFAFA] text-left">
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Date</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Chicken ID</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Cage</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Reason</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Notes</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cullingLogs as $log)
                    <tr class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]">
                        <td class="px-3 py-2 text-[#333]">{{ $log->cull_date->format('M d, Y') }}</td>
                        <td class="px-3 py-2 font-mono text-[#333]">{{ $log->hen->chicken_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#333]">{{ $log->hen?->cage?->cage_code ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium
                                @switch($log->reason)
                                    @case('illness') bg-red-100 text-red-700 @break
                                    @case('aggression') bg-yellow-100 text-yellow-700 @break
                                    @case('low_production') bg-orange-100 text-orange-700 @break
                                    @case('age') bg-gray-100 text-gray-600 @break
                                    @default bg-gray-100 text-gray-600
                                @endswitch">
                                {{ str_replace('_', ' ', ucfirst($log->reason)) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-[#9CA3AF] max-w-32 truncate">{{ $log->notes ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#9CA3AF]">{{ $log->recorder?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($cullingLogs->hasPages())
        <div class="px-4 py-3 border-t border-[#F0F0F0] flex items-center justify-between text-xs text-[#6B7280]">
            <span>Showing {{ $cullingLogs->firstItem() }}-{{ $cullingLogs->lastItem() }} of {{ $cullingLogs->total() }}</span>
            <div class="flex items-center gap-1">
                @if($cullingLogs->onFirstPage())
                <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                @else
                <a href="{{ $cullingLogs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                @endif
                @foreach($cullingLogs->getUrlRange(1, $cullingLogs->lastPage()) as $page => $url)
                    @if($page == $cullingLogs->currentPage())
                    <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                    @elseif($page >= $cullingLogs->currentPage() - 1 && $page <= $cullingLogs->currentPage() + 1)
                    <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                    @endif
                @endforeach
                @if($cullingLogs->hasMorePages())
                <a href="{{ $cullingLogs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                @else
                <span class="px-2 py-1 text-[#9CA3Af]">Next ›</span>
                @endif
            </div>
        </div>
        @endif
    </div>
    @endif
</turbo-frame>
