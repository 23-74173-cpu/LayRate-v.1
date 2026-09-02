<turbo-frame id="chickens-culling-records">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-[#6B7280] uppercase tracking-wider">Culling Records</span>
            <span class="text-xs px-1.5 py-0.5 rounded-full bg-white/80 text-[#6B7280] border border-[#D9D9D9]">
                {{ $cullingLogs->total() }} total
            </span>
        </div>
        <div class="flex items-center gap-2">
            <select id="cullHenSelect"
                    class="border border-[#D9D9D9] rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#002D5E] max-w-[200px]">
                <option value="">Select hen to cull...</option>
                @foreach($activeHens as $hen)
                    <option value="{{ $hen->id }}"
                            data-label="{{ $hen->chicken_id ?? '—' }}{{ $hen->cageSlot?->cage ? ' (' . $hen->cageSlot->cage->cage_code . ')' : ' (unplaced)' }}">
                        {{ $hen->chicken_id ?? '—' }} — {{ $hen->breed }}{{ $hen->cageSlot?->cage ? ' [' . $hen->cageSlot->cage->cage_code . ']' : ' [unplaced]' }}
                    </option>
                @endforeach
            </select>
            <button type="button" onclick="cullFromDropdown()"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors">
                <i data-lucide="crosshair" class="w-3 h-3"></i> Cull
            </button>
        </div>
    </div>

    <script>
    function cullFromDropdown() {
        var sel = document.getElementById('cullHenSelect');
        if (!sel || !sel.value) {
            if (typeof showNotification === 'function') showNotification('Please select a hen first.', 'warning');
            return;
        }
        var opt = sel.options[sel.selectedIndex];
        var label = opt.dataset.label || opt.text;
        openCullModal(sel.value, label);
        sel.value = '';
    }
    window.cullFromDropdown = cullFromDropdown;
    </script>

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
