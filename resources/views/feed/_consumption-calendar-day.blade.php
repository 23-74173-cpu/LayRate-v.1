@php
    $totalKg = (float) $logs->sum('feed_consumed_kg');
    $isEstimated = $logs->isNotEmpty() && $logs->every(fn ($log) => $log->source === 'distributed');
    $byTime = $logs->groupBy(fn ($log) => $log->log_time?->format('H:i') ?? 'Unknown time');
    $dateLabel = \Carbon\Carbon::createFromFormat('!Y-m-d', $date)->format('l, j F Y');
@endphp
<div class="space-y-5" data-feed-calendar-day-detail>
    <header class="rounded-xl bg-[#f6f5f4] p-3 md:p-4">
        <h3 class="text-base font-semibold">{{ $dateLabel }}</h3>
        <p class="mt-2 text-lg font-bold">{{ number_format($totalKg, 1) }} kg <span class="text-sm font-normal text-[#615d59]">· {{ $logs->count() }} feedings</span></p>
        @if($isEstimated)
            <span class="mt-2 inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-warning-bg text-warning border border-warning-border" title="Distributed estimates from a whole-farm entry"><i data-lucide="git-branch" class="hidden md:inline-block w-3 h-3" aria-hidden="true"></i> Estimated</span>
        @endif
    </header>

    @foreach(['07:00', '15:00'] as $time)
        @php $timeLogs = $byTime->get($time, collect())->keyBy('cage_id'); @endphp
        <section class="rounded-xl border border-[#e6e6e6] p-3 sm:p-4">
            <h3 class="mb-2 text-sm font-semibold text-[#333333]">{{ $time }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">Feed consumption at {{ $time }} for {{ $dateLabel }}</caption>
                    <thead><tr class="border-b text-left text-[#6B7280]"><th class="py-2 pr-2">Cage</th><th class="py-2 pr-2 text-right">Consumed</th><th class="py-2 text-right">Action</th></tr></thead>
                    <tbody>
                        @foreach($cages as $cage)
                            @php $log = $timeLogs->get($cage->id); @endphp
                            <tr class="border-b border-black/5">
                                <th scope="row" class="py-2 pr-2 text-left font-medium">{{ $cage->cage_code }}</th>
                                <td class="py-2 pr-2 text-right">{{ $log ? number_format($log->feed_consumed_kg, 2).' kg' : 'No record' }}</td>
                                <td class="py-2 text-right">
                                    @if($log && $log->source === 'direct')
                                        <button type="button" class="min-h-11 min-w-11 text-navy underline" aria-label="Edit consumption for {{ $cage->cage_code }} at {{ $time }}" onclick="closeFeedCalendarForEdit(); openConsumptionModal({{ $log->cage_id }}, {{ $log->feed_batch_id }}, '{{ $log->log_date->format('Y-m-d') }}', '{{ $log->log_time?->format('H:i') ?? '' }}', {{ $log->feed_consumed_kg }}, {{ $log->id }})">Edit</button>
                                    @elseif($log && $log->source === 'distributed')
                                        <button type="button" class="min-h-11 min-w-11 text-navy underline" aria-label="Edit whole-farm entry for {{ $cage->cage_code }} at {{ $time }}" onclick="closeFeedCalendarForEdit(); openFarmEntryModal({{ $log->farm_feed_entry_id }}, {{ $log->feed_batch_id }}, '{{ $log->log_date->format('Y-m-d') }}', '{{ $log->log_time?->format('H:i') ?? '' }}', {{ $log->farmFeedEntry?->total_kg ?? 'null' }})">Edit</button>
                                    @else
                                        <span class="text-[#9CA3AF]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr class="font-semibold"><th scope="row" class="py-2 text-left">Feeding subtotal</th><td class="py-2 text-right">{{ number_format((float) $timeLogs->sum('feed_consumed_kg'), 2) }} kg</td><td></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
