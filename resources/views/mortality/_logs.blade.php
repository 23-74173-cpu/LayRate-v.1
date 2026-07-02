<turbo-frame id="mortality-logs-list">
    @if($logs->isEmpty())
    <div class="py-8 text-center text-sm text-[#6B7280]">No mortality records yet.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-[#D9D9D9]">
                    <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">DATE</th>
                    <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">CAGE</th>
                    <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">COUNT</th>
                    <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">REASON</th>
                    <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">NOTES</th>
                    <th class="py-2 text-[10px] tracking-wider text-[#6B7280] font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#F5F6F8]">
                @foreach($logs as $log)
                @php
                    $reasonColors = [
                        'Disease'    => ['#F8D7DA','#721C24'],
                        'Heat Stress'=> ['#FFF3CD','#856404'],
                        'Injury'     => ['#FFF3CD','#856404'],
                        'Predator'   => ['#F8D7DA','#721C24'],
                        'Unknown'    => ['#F5F6F8','#6B7280'],
                        'Other'      => ['#F5F6F8','#6B7280'],
                    ];
                    [$rBg,$rTxt] = $reasonColors[$log->reason] ?? ['#F5F6F8','#6B7280'];
                @endphp
                <tr class="hover:bg-[#F5F6F8]/50">
                    <td class="py-2.5 pr-3 text-[#6B7280]">{{ $log->log_date->format('M d, Y') }}</td>
                    <td class="py-2.5 pr-3">
                        <span class="font-medium" style="color:{{ $log->cage->color }}">{{ $log->cage->cage_code }}</span>
                    </td>
                    <td class="py-2.5 pr-3 font-semibold text-[#333333]">{{ $log->count }}</td>
                    <td class="py-2.5 pr-3">
                        <span class="px-2 py-0.5 rounded text-[10px]" style="background:{{ $rBg }};color:{{ $rTxt }}">
                            {{ $log->reason }}
                        </span>
                    </td>
                    <td class="py-2.5 pr-3 text-[#6B7280] max-w-[200px] truncate">
                        {{ $log->notes ?: '—' }}
                    </td>
                    <td class="py-2.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openEditMortality({{ $log->id }}, '{{ $log->log_date->format('Y-m-d') }}', {{ $log->count }}, '{{ addslashes($log->reason) }}', '{{ addslashes($log->notes ?? '') }}')"
                                    class="p-1.5 hover:bg-black/5 rounded-full transition-colors" style="color: #a39e98;" aria-label="Edit record">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <form action="{{ route('mortality.destroy', $log) }}" method="POST"
                                  data-confirm="Delete this mortality record?" data-confirm-action="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 hover:bg-red-50 rounded-full transition-colors" style="color: #a39e98;" aria-label="Delete record">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-[#F0F0F0] flex items-center justify-between text-xs text-[#6B7280]">
            <span>Showing {{ $logs->firstItem() }}-{{ $logs->lastItem() }} of {{ $logs->total() }}</span>
            <div class="flex items-center gap-1">
                @if($logs->onFirstPage())
                <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                @else
                <a href="{{ $logs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                @endif
                @foreach($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                    @if($page == $logs->currentPage())
                    <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                    @elseif($page >= $logs->currentPage() - 1 && $page <= $logs->currentPage() + 1)
                    <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                    @endif
                @endforeach
                @if($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                @else
                <span class="px-2 py-1 text-[#9CA3AF]">Next ›</span>
                @endif
            </div>
        </div>
        @endif
    </div>
    @endif
</turbo-frame>
