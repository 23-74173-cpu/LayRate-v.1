<turbo-frame id="chickens-mortality-records">
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-4 py-2.5 border-b border-[#D9D9D9] bg-[#F5F6F8]">
            <span class="text-sm font-medium text-[#333]">Recent Records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-[#FAFAFA] text-left">
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Date</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Cage</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Count</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Reason</th>
                        <th class="px-3 py-2 text-[#9CA3AF] font-medium">Notes</th>
                        @can('admin')<th class="px-3 py-2"></th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse($mortalityLogs as $log)
                    @php
                        $reasonColors = [
                            'Disease' => 'bg-red-100 text-red-700',
                            'Heat Stress' => 'bg-yellow-100 text-yellow-700',
                            'Injury' => 'bg-yellow-100 text-yellow-700',
                            'Predator' => 'bg-red-100 text-red-700',
                            'Unknown' => 'bg-gray-100 text-gray-600',
                            'Other' => 'bg-gray-100 text-gray-600',
                        ];
                    @endphp
                    <tr class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]">
                        <td class="px-3 py-2 text-[#333]">{{ $log->log_date->format('M d, Y') }}</td>
                        <td class="px-3 py-2 text-[#333]">{{ $log->cage?->cage_code ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#333] font-medium">{{ $log->count }}</td>
                        <td class="px-3 py-2">
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $reasonColors[$log->reason] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $log->reason }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-[#9CA3AF] max-w-32 truncate">{{ $log->notes ?? '—' }}</td>
                        @can('admin')
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('mortality.destroy', $log) }}"
                                  onsubmit="return confirm('Delete this mortality record?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600" aria-label="Delete record">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-[#9CA3AF] text-sm">No records yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            @if($mortalityLogs->hasPages())
            <div class="px-4 py-3 border-t border-[#F0F0F0] flex items-center justify-between text-xs text-[#6B7280]">
                <span>Showing {{ $mortalityLogs->firstItem() }}-{{ $mortalityLogs->lastItem() }} of {{ $mortalityLogs->total() }}</span>
                <div class="flex items-center gap-1">
                    @if($mortalityLogs->onFirstPage())
                    <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                    @else
                    <a href="{{ $mortalityLogs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                    @endif
                    @foreach($mortalityLogs->getUrlRange(1, $mortalityLogs->lastPage()) as $page => $url)
                        @if($page == $mortalityLogs->currentPage())
                        <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                        @elseif($page >= $mortalityLogs->currentPage() - 1 && $page <= $mortalityLogs->currentPage() + 1)
                        <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($mortalityLogs->hasMorePages())
                    <a href="{{ $mortalityLogs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                    @else
                    <span class="px-2 py-1 text-[#9CA3Af]">Next ›</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</turbo-frame>
