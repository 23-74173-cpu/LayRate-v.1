@php
    $allLogs = $logs->flatten(1);
    $eggsTotal = (int) $allLogs->sum('egg_count');
    $hensTotal = (int) $allLogs->sum('hen_count');
    $hdepTotal = $hensTotal > 0 ? round($eggsTotal / $hensTotal * 100, 1) : null;
    $sources = $allLogs->pluck('logged_via')->unique()->sort()->values();
    $isImported = $allLogs->isNotEmpty() && $allLogs->every(fn ($log) => $log->logged_via === 'unknown' && str_starts_with((string) $log->notes, 'Import: production_data_clean'));
    $dateLabel = \Carbon\Carbon::parse($date)->format('l, j F Y');
@endphp

<div class="space-y-5" data-calendar-day-detail>
    <header class="rounded-xl bg-[#f6f5f4] p-4">
        <h3 class="text-base font-semibold">{{ $dateLabel }}</h3>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div><div class="text-xs text-[#615d59]">Eggs</div><div class="text-lg font-bold">{{ number_format($eggsTotal) }}</div></div>
            <div><div class="text-xs text-[#615d59]">Stored hens</div><div class="text-lg font-bold">{{ number_format($hensTotal) }}</div></div>
            <div><div class="text-xs text-[#615d59]">Day HDEP</div><div class="text-lg font-bold">{{ $hdepTotal === null ? '—' : number_format($hdepTotal, 1).'%' }}</div></div>
        </div>
        <p class="mt-2 text-sm text-[#615d59]">Source: {{ $sources->isEmpty() ? 'none' : $sources->map(fn ($source) => $source === 'unknown' && $isImported ? 'imported' : $source)->implode(', ') }}</p>
    </header>

    @if($logs->isEmpty())
        <x-empty-state icon="egg" message="No production records match the selected filters for this day." />
    @else
        @foreach($logs as $cageCode => $cageLogs)
            @php
                $cageEggs = (int) $cageLogs->sum('egg_count');
                $cageHens = (int) $cageLogs->sum('hen_count');
                $cageHdep = $cageHens > 0 ? round($cageEggs / $cageHens * 100, 1) : null;
                $cageImported = $cageLogs->every(fn ($log) => $log->logged_via === 'unknown' && str_starts_with((string) $log->notes, 'Import: production_data_clean'));
            @endphp
            <section class="rounded-xl border border-hairline p-4">
                <h3 class="text-base font-semibold">{{ $cageCode }}</h3>
                <p class="mt-2 text-sm">{{ number_format($cageEggs) }} eggs · {{ number_format($cageHens) }} stored hens · HDEP {{ $cageHdep === null ? '—' : number_format($cageHdep, 1).'%' }}</p>
                <details class="mt-3">
                    <summary class="min-h-11 cursor-pointer py-2 text-sm font-medium">{{ $cageImported ? 'Imported allocation (not measured per slot)' : 'Per-slot records' }} ({{ $cageLogs->count() }})</summary>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <caption class="sr-only">{{ $cageCode }} slot production for {{ $dateLabel }}</caption>
                            <thead><tr class="border-b text-left"><th class="py-2 pr-3">Slot</th><th class="py-2 pr-3">Eggs</th><th class="py-2 pr-3">Hens</th><th class="py-2 pr-3">Source</th><th class="py-2">Actions</th></tr></thead>
                            <tbody>
                                @foreach($cageLogs->sortBy(fn ($log) => $log->cageSlot?->slot_number ?? 0) as $log)
                                    @php
                                        $sizeMap = $log->eggSizeLogs->keyBy('egg_size');
                                        $sizes = ['small' => (int) ($sizeMap->get('small')?->count ?? 0), 'medium' => (int) ($sizeMap->get('medium')?->count ?? 0), 'large' => (int) ($sizeMap->get('large')?->count ?? 0), 'xl' => (int) ($sizeMap->get('xl')?->count ?? 0), 'jumbo' => (int) ($sizeMap->get('jumbo')?->count ?? 0)];
                                        $source = $log->logged_via === 'unknown' && str_starts_with((string) $log->notes, 'Import: production_data_clean') ? 'imported' : $log->logged_via;
                                        $slotName = $log->cageSlot ? 'Slot '.$log->cageSlot->slot_number : 'Unknown slot';
                                    @endphp
                                    <tr class="border-b border-black/5">
                                        <td class="py-2 pr-3">{{ $slotName }}</td><td class="py-2 pr-3">{{ number_format($log->egg_count) }}</td><td class="py-2 pr-3">{{ number_format($log->hen_count) }}</td><td class="py-2 pr-3">{{ $source }}</td>
                                        <td class="py-2">
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" class="min-h-11 px-2 text-navy underline" onclick="openEditLog({id:{{ $log->id }},date:'{{ $log->log_date->format('Y-m-d') }}',eggCount:{{ $log->egg_count }},henCount:{{ $log->hen_count }},notes:@js($log->notes),cageSlotId:{{ $log->cage_slot_id }},sizes:@js($sizes)})">Edit</button>
                                                <form method="POST" action="{{ route('eggs.logging.reset', $log) }}" data-confirm="Reset egg count for this log to 0? This action preserves the log entry but clears the count and size breakdown." data-confirm-action="Reset" data-confirm-severity="destructive">@csrf @method('PUT')<button type="submit" class="min-h-11 px-2 text-amber-800 underline">Reset</button></form>
                                                @if(auth()->user()->role === 'admin')
                                                <form method="POST" action="{{ route('eggs.logging.destroy', $log) }}" data-confirm="Delete this production log permanently? Data loss includes egg count, size breakdown, and stock batch references will be unlinked." data-confirm-action="Delete" data-confirm-severity="destructive">@csrf @method('DELETE')<button type="submit" class="min-h-11 px-2 text-red-700 underline">Delete</button></form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>
        @endforeach
    @endif
</div>
