<turbo-frame id="egg-logs-list">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b" style="background-color: #f6f5f4; border-color: #e6e6e6;">
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Date</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Cage</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Slot</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Eggs</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Hens</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">HDEP</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Logged By</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Notes</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Override</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-b hover:bg-black/[0.02] transition-colors" style="border-color: #e6e6e6;">
                    <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ $log->log_date->format('Y-m-d') }}</td>
                    <td class="px-6 py-3 text-sm font-semibold font-mono" style="color: {{ $log->cageSlot?->cage?->color ?? '#6B7280' }}">{{ $log->cageSlot?->cage?->cage_code ?? '—' }}</td>
                    <td class="px-6 py-3 text-xs font-mono" style="color: #615d59;">
                        @if($log->cageSlot){{ $log->cageSlot->row_number }}-{{ $log->cageSlot->column_number }}@else — @endif
                    </td>
                    <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ $log->egg_count }}</td>
                    <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ $log->hen_count }}</td>
                    <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ number_format($log->hdep,1) }}%</td>
                    <td class="px-6 py-3 text-sm" style="color: #31302e;">{{ $log->recorder?->name ?? 'Farm Operator' }}</td>
                    <td class="px-6 py-3 text-sm max-w-[200px] truncate" style="color: #615d59;">{{ $log->notes ?? '—' }}</td>
                    <td class="px-6 py-3">
                        @if($log->overriddenBy)
                        <x-status-badge status="Watch" type="general" />
                        @else
                        <span class="text-xs" style="color: #a39e98;">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-1">
                            <button onclick="openEditLog({{ $log->id }}, '{{ $log->log_date->format('Y-m-d') }}', {{ $log->egg_count }}, {{ $log->hen_count }}, '{{ addslashes($log->notes ?? '') }}', {{ $log->cage_slot_id }})"
                                    class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit log">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            @if(auth()->user()->role === 'admin')
                            <form method="POST" action="{{ route('eggs.logging.destroy', $log) }}"
                                  data-confirm="Delete this log?" data-confirm-action="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete log">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-6 py-10 text-center text-sm" style="color: #a39e98;">No logs yet. Select a slot and save the first record.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-paginator :paginator="$logs" />
</turbo-frame>
