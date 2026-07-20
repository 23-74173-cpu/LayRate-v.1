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
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
        <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">FCR (THIS {{ $periodLabel }})</div>
        <div class="flex items-center gap-3">
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">
                {{ $fcrCurrent !== null ? number_format($fcrCurrent, 2) : 'N/A' }}
            </div>
            @if($summaryStatus !== 'na')
            <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full {{ $summaryBadge['bg'] }} {{ $summaryBadge['text'] }}">
                <i data-lucide="{{ $summaryBadge['icon'] }}" class="w-3 h-3"></i>
                {{ $summaryLabel }}
            </span>
            @else
            <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full {{ $summaryBadge['bg'] }} {{ $summaryBadge['text'] }}">
                <i data-lucide="{{ $summaryBadge['icon'] }}" class="w-3 h-3"></i>
                {{ $summaryLabel }}
            </span>
            @endif
        </div>
        @if($fcrCurrent === null)
        <div class="text-xs text-[#9CA3AF] mt-1">No egg mass logged for this period</div>
        @else
        <div class="flex items-center gap-1.5 mt-1">
            <span class="text-xs text-[#6B7280]">lower is better</span>
            <button type="button" onclick="openFcrGuideModal()"
                    class="inline-flex items-center justify-center w-4 h-4 rounded-full border border-[#D9D9D9] text-[#9CA3AF] hover:text-[#6B7280] hover:border-[#6B7280] transition-colors flex-shrink-0"
                    aria-label="What does FCR mean?">
                <i data-lucide="info" class="w-3 h-3"></i>
            </button>
        </div>
        @endif
    </div>
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
        <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">FEED CONSUMED</div>
        <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ number_format($fcrTimeline->sum('feed_kg'), 1) }} <span class="text-xs">kg</span></div>
        <div class="text-xs text-[#6B7280] mt-1">shown periods</div>
    </div>
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
        <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280] mb-1">EST. EGG MASS</div>
        <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ number_format($fcrTimeline->sum('egg_mass_kg'), 2) }} <span class="text-xs">kg</span></div>
        <div class="text-xs text-[#6B7280] mt-1">egg counts + weights</div>
    </div>
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
            @foreach($fcrTimeline as $row)
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
