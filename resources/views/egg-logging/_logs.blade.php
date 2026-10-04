<turbo-frame id="egg-logs-list">
    {{-- KPIs for every record matching the filters; inside this frame so
         they follow filter changes and live reloads with the list. --}}
    @isset($logStats)
    @php
        $sensorPct = $logStats['records'] > 0 ? round($logStats['sensor'] / $logStats['records'] * 100) : 0;
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi-card label="Records" infoKey="recent-logs.records" icon="clipboard-list" delay="0ms"
                    :value="number_format($logStats['records'])">
            <div class="text-xs mt-1.5 font-medium" style="color: #615d59;">matching the filters</div>
        </x-kpi-card>
        <x-kpi-card label="Eggs Logged" infoKey="recent-logs.eggs" icon="egg" delay="60ms"
                    :value="number_format($logStats['eggs'])">
            <div class="text-xs mt-1.5 font-medium" style="color: #615d59;">across these records</div>
        </x-kpi-card>
        <x-kpi-card label="Logged by IR" infoKey="recent-logs.sensor-share" icon="radio" delay="120ms"
                    :value="$sensorPct . '%'">
            <div class="text-xs mt-1.5 font-medium" style="color: #615d59;">{{ number_format($logStats['sensor']) }} sensor · {{ number_format($logStats['records'] - $logStats['sensor']) }} other</div>
        </x-kpi-card>
        <x-kpi-card label="Overridden" infoKey="recent-logs.overridden" icon="shield-check" delay="180ms"
                    :value="number_format($logStats['overridden'])">
            <div class="text-xs mt-1.5 font-medium" style="color: #615d59;">sensor counts corrected</div>
        </x-kpi-card>
    </div>
    @endisset
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b" style="background-color: #f6f5f4; border-color: #e6e6e6;">
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Date</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Cage</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Slot</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Eggs</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Hens</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">HDEP</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Logged By</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Source</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Notes</th>
                    <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-5 py-3" style="color: #615d59;">Override</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-b hover:bg-black/[0.02] transition-colors" style="border-color: #e6e6e6;">
                    <td class="px-5 py-3.5 text-sm font-mono" style="color: #1f1f1f;">{{ $log->log_date->format('m/d/Y') }}</td>
                    <td class="px-5 py-3.5 text-sm font-semibold font-mono" style="color: {{ $log->cageSlot?->cage?->color ?? '#6B7280' }}">{{ $log->cageSlot?->cage?->cage_code ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-xs font-mono" style="color: #615d59;">
                        @if($log->cageSlot){{ $log->cageSlot->row_number }}-{{ $log->cageSlot->column_number }}@else — @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm font-mono" style="color: #1f1f1f;">{{ $log->egg_count }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono" style="color: #1f1f1f;">{{ $log->hen_count }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono" style="color: #1f1f1f;">{{ number_format($log->hdep,1) }}%</td>
                    <td class="px-5 py-3.5 text-sm" style="color: #31302e;">{{ $log->recorder?->name ?? 'Farm Operator' }}</td>
                    <td class="px-5 py-3.5">
                        @if($log->logged_via === 'sensor')
                        <x-status-badge status="sensor" type="slot" />
                        @elseif($log->logged_via === 'manual')
                        <x-status-badge status="manual" type="slot" />
                        @else
                        <span class="text-xs" style="color: #a39e98;">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm max-w-[200px] truncate" style="color: #615d59;">{{ $log->notes ?? '—' }}</td>
                    <td class="px-5 py-3.5">
                        @if($log->overriddenBy)
                        <x-status-badge status="Watch" type="general" />
                        @else
                        <span class="text-xs" style="color: #a39e98;">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1">
                            @php
                                $sizes = $log->eggSizeLogs->keyBy('egg_size');
                            @endphp
                            <button onclick="openEditLog({id: {{ $log->id }}, date: '{{ $log->log_date->format('Y-m-d') }}', eggCount: {{ $log->egg_count }}, henCount: {{ $log->hen_count }}, notes: '{{ addslashes($log->notes ?? '') }}', cageSlotId: {{ $log->cage_slot_id }}, sizes: {small: {{ $sizes->get('small')?->count ?? 0 }}, medium: {{ $sizes->get('medium')?->count ?? 0 }}, large: {{ $sizes->get('large')?->count ?? 0 }}, xl: {{ $sizes->get('xl')?->count ?? 0 }}, jumbo: {{ $sizes->get('jumbo')?->count ?? 0 }}})"
                                    class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit log">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <form method="POST" action="{{ route('eggs.logging.reset', $log) }}"
                                  data-confirm="Reset egg count for this log to 0? This action preserves the log entry but clears the count and size breakdown."
                                  data-confirm-action="Reset" data-confirm-severity="destructive">
                                @csrf @method('PUT')
                                <button type="submit" class="p-1.5 rounded-full hover:bg-amber-50 transition-colors" style="color: #a39e98;" aria-label="Reset count">
                                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                            @if(auth()->user()->role === 'admin')
                            <form method="POST" action="{{ route('eggs.logging.destroy', $log) }}"
                                  data-confirm="Delete this production log permanently? Data loss includes egg count, size breakdown, and stock batch references will be unlinked."
                                  data-confirm-action="Delete" data-confirm-severity="destructive">
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
                <tr><td colspan="11" class="px-5 py-10 text-center text-sm" style="color: #a39e98;">No logs yet. Select a slot and save the first record.</td></tr>
                @endforelse
                @if($logs->count() > 0)
                {{-- Blank filler rows to keep the table at a consistent height (perPage rows). --}}
                @for($i = 0; $i < $logs->perPage() - $logs->count(); $i++)
                <tr class="empty-filler-row" aria-hidden="true">
                    <td colspan="11" class="px-5 py-3.5">&nbsp;</td>
                </tr>
                @endfor
                @endif
            </tbody>
        </table>
    </div>
    <x-paginator :paginator="$logs" />
</turbo-frame>
