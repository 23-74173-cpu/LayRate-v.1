<turbo-frame id="chickens-removal-records">
    @if($removalLogs->isEmpty())
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#9CA3AF]">No removal records found.</div>
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
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Destination</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Notes</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($removalLogs as $log)
                    <tr class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]">
                        <td class="px-3 py-2 text-[#333]">{{ $log->removal_date->format('M d, Y') }}</td>
                        <td class="px-3 py-2 font-mono text-[#333]">{{ $log->hen->chicken_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#333]">{{ $log->hen?->cage?->cage_code ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#333]">{{ $log->reason }}</td>
                        <td class="px-3 py-2 text-[#6B7280]">{{ $log->destination ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#9CA3AF] max-w-24 truncate">{{ $log->notes ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#9CA3AF]">{{ $log->recorder?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($removalLogs->hasPages())
        <div class="px-4 py-3 border-t border-[#F0F0F0] flex items-center justify-between text-xs text-[#6B7280]">
            <span>Showing {{ $removalLogs->firstItem() }}-{{ $removalLogs->lastItem() }} of {{ $removalLogs->total() }}</span>
            <div class="flex items-center gap-1">
                @if($removalLogs->onFirstPage())
                <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                @else
                <a href="{{ $removalLogs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                @endif
                @foreach($removalLogs->getUrlRange(1, $removalLogs->lastPage()) as $page => $url)
                    @if($page == $removalLogs->currentPage())
                    <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                    @elseif($page >= $removalLogs->currentPage() - 1 && $page <= $removalLogs->currentPage() + 1)
                    <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                    @endif
                @endforeach
                @if($removalLogs->hasMorePages())
                <a href="{{ $removalLogs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                @else
                <span class="px-2 py-1 text-[#9CA3Af]">Next ›</span>
                @endif
            </div>
        </div>
        @endif
    </div>
    @endif
</turbo-frame>
