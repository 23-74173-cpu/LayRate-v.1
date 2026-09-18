@php
    use App\Services\FcrStatusService;

    $periodLabel = match($fcrGroupBy) { 'month' => 'MONTH', 'week' => 'WEEK', default => 'DAY' };
    $cageDisplay = $fcrCageLabel ?? ($fcrSelectedId === 'all' ? 'All Cages' : 'the selected cage');

    $summaryStatus = FcrStatusService::status($fcrCurrent);
    $summaryBadge  = FcrStatusService::badgeClasses($fcrCurrent);
    $summaryLabel  = FcrStatusService::label($fcrCurrent);

    $goodThreshold    = (float) config('fcr.good_threshold', 2.5);
    $warningThreshold = (float) config('fcr.warning_threshold', 4.0);
@endphp
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5">
    <x-kpi-card
        label="FCR (THIS {{ $periodLabel }})"
        icon="scale"
        cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
        delay="0ms"
        :value="$fcrCurrent !== null ? number_format($fcrCurrent, 2) : 'N/A'"
    >
        <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">lower is better · {{ $summaryLabel }}</div>
    </x-kpi-card>
    <x-kpi-card
        label="FEED CONSUMED"
        icon="package"
        cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
        delay="60ms"
        :value="number_format($fcrTimeline->sum('feed_kg'), 1) . ' kg'"
    >
        <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">shown periods</div>
    </x-kpi-card>
    <x-kpi-card
        class="col-span-2 md:col-span-1"
        label="EST. EGG MASS"
        icon="egg"
        cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
        delay="120ms"
        :value="number_format($fcrTimeline->sum('egg_mass_kg'), 2) . ' kg'"
    >
        <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">egg counts + weights</div>
    </x-kpi-card>
</div>
@if($fcrTimeline->isEmpty())
<div class="text-center py-10 text-sm text-[#6B7280]">
    No feed or production data for {{ $cageDisplay }}.
</div>
@else
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
            @foreach($fcrTimeline->take(5) as $row)
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
