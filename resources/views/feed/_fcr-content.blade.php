@php
    use App\Services\FcrStatusService;

    $periodLabel = match($fcrGroupBy) { 'month' => 'month', 'week' => 'week', default => 'day' };
    $cageDisplay = $fcrCageLabel ?? ($fcrSelectedId === 'all' ? 'All Cages' : 'the selected cage');

    $summaryStatus = FcrStatusService::status($fcrCurrent);
    $summaryBadge  = FcrStatusService::badgeClasses($fcrCurrent);
    $summaryLabel  = FcrStatusService::label($fcrCurrent);

    $goodThreshold    = (float) config('fcr.good_threshold', 2.5);
    $warningThreshold = (float) config('fcr.warning_threshold', 4.0);
    $hasValidFcr = $fcrTimeline->contains(fn ($row) => $row['fcr'] !== null);
@endphp
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5">
    <x-kpi-card
        label="FCR (This {{ $periodLabel }})" infoKey="feed.fcr-current"
        icon="scale"
        cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
        delay="0ms"
        :value="$fcrCurrent !== null ? number_format($fcrCurrent, 2) : 'N/A'"
    >
        <div class="kpi-secondary-detail text-xs mt-1.5 font-medium max-md:hidden" style="color: #615d59;">lower is better · {{ $summaryLabel }}</div>
    </x-kpi-card>
    <x-kpi-card
        label="Feed Consumed" infoKey="feed.fcr-consumed"
        icon="package"
        cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
        delay="60ms"
        :value="number_format($fcrTotalFeedKg, 1) . ' kg'"
    >
        <div class="kpi-secondary-detail text-xs mt-1.5 font-medium max-md:hidden" style="color: #615d59;">shown periods</div>
    </x-kpi-card>
    <x-kpi-card
        class="col-span-2 md:col-span-1"
        label="Est. Egg Mass" infoKey="feed.fcr-egg-mass"
        icon="egg"
        cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
        delay="120ms"
        :value="number_format($fcrTotalEggMassKg, 2) . ' kg'"
    >
        <div class="kpi-secondary-detail text-xs mt-1.5 font-medium max-md:hidden" style="color: #615d59;">egg counts + weights</div>
    </x-kpi-card>
</div>
@if(! $hasValidFcr)
<div class="mx-auto max-w-xl rounded-xl border border-[#D9D9D9] bg-[#F8FAFC] px-5 py-8 text-center" role="status" aria-live="polite">
    <i data-lucide="chart-no-axes-column-increasing" class="mx-auto mb-3 h-8 w-8 text-[#615d59]" aria-hidden="true"></i>
    @if($fcrTimeline->isEmpty())
        <h3 class="text-base font-semibold text-[#333333]">No FCR data for this period</h3>
        <p class="mt-2 text-sm text-[#615d59]">There are no matching feed and egg production records for the selected dates. Try another date range.</p>
        <span class="sr-only">No feed or production history is available for {{ $cageDisplay }}.</span>
    @else
        <h3 class="text-base font-semibold text-[#333333]">Incomplete feed or production data</h3>
        <p class="mt-2 text-sm text-[#615d59]">Feed or egg production records exist for {{ $cageDisplay }}, but both are needed in the same period to calculate FCR.</p>
    @endif
    @if($fcrAsOf ?? null)
        <span class="block mt-2">{{ $fcrAsOf }}.</span>
    @endif
</div>
@else
    @if($fcrAsOf ?? null)
    <p class="text-xs text-[#615d59] mb-3" data-fcr-as-of>{{ $fcrAsOf }}.</p>
    @endif
<div class="overflow-x-auto">
    <table class="w-full">
        <thead>
            <tr class="border-b border-[#D9D9D9] bg-white">
                <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Period</th>
                <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Feed (kg)</th>
                <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Egg Mass (kg)</th>
                <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">FCR</th>
            </tr>
        </thead>
        <tbody>
            @foreach($fcrTimeline->filter(fn ($row) => $row['fcr'] !== null)->take(5) as $row)
            @php
                $rowStatus = FcrStatusService::status($row['fcr']);
                $rowBadge  = FcrStatusService::badgeClasses($row['fcr']);
            @endphp
            <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $row['label'] }}</td>
                <td class="px-5 py-3.5 text-sm text-right text-[#333333]">{{ number_format($row['feed_kg'], 1) }}</td>
                <td class="px-5 py-3.5 text-sm text-right text-[#333333]">{{ number_format($row['egg_mass_kg'], 2) }}</td>
                <td class="px-5 py-3.5 text-sm text-right font-medium">
                    @if($row['fcr'] === null)
                    <span class="inline-flex items-center gap-1.5 text-[#9CA3AF]">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span>
                        N/A
                    </span>
                    @else
                    <span class="inline-flex items-center justify-end gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full {{ $rowBadge['dot'] }} inline-block"></span>
                        <span class="{{ $rowStatus === 'critical' ? 'text-[#9B1C24] font-semibold' : 'text-[#333333]' }}">
                            {{ number_format($row['fcr'], 2) }}
                        </span>
                    </span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
