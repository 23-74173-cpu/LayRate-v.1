<turbo-frame id="chickens-mortality-records">
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-4 py-2.5 border-b border-[#D9D9D9] bg-[#F5F6F8]">
            <span class="text-sm font-medium text-[#333333]">Recent Records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-[#FAFAFA] text-left text-xs font-semibold uppercase tracking-wider text-[#6B7280]">
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Cage</th>
                        <th class="px-3 py-2">Count</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2">Notes</th>
                        @can('admin')<th class="px-3 py-2"></th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse($mortalityLogs as $log)
                    <tr class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]">
                        <td class="px-3 py-2 text-[#333]">{{ $log->log_date->format('M d, Y') }}</td>
                        <td class="px-3 py-2 text-[#333]">{{ $log->cage?->cage_code ?? '—' }}</td>
                        <td class="px-3 py-2 text-[#333] font-medium">{{ $log->count }}</td>
                        <td class="px-3 py-2">
                            <x-status-badge :status="$log->reason" type="mortality" />
                        </td>
                        <td class="px-3 py-2 text-[#9CA3AF] max-w-32 truncate">{{ $log->notes ?? '—' }}</td>
                        @can('admin')
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('mortality.destroy', $log) }}"
                                  data-confirm="Delete this mortality record?" data-confirm-action="Delete" data-confirm-severity="destructive">
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
                        <td colspan="6" class="px-3 py-10 text-center text-[#9CA3AF] text-sm">No records yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <x-paginator :paginator="$mortalityLogs" />
        </div>
    </div>
</turbo-frame>
