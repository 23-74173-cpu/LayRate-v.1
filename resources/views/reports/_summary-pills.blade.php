{{-- Summary pills — shared by the preview table and the printable document --}}
@if($summary !== null)
<div class="flex gap-4 mb-6">
    @if($type === 'production')
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Total Eggs</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_eggs }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Avg HDEP</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_hdep }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Total Hens</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_hens }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Days Covered</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->days }}</div>
    </div>
    @elseif($type === 'feed')
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Total Consumed</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_kg }} kg</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Avg per Day</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_per_day }} kg</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Batches Used</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->batches }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Days Covered</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->days }}</div>
    </div>
    @elseif($type === 'environment')
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Avg Temperature</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_temp }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Avg Humidity</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->avg_hum }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Total Readings</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->readings }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Alert Readings</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->alerts }}</div>
    </div>
    @elseif($type === 'egg_stock')
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Total Stocked</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_stocked }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Batches</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->batches }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Top Size</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->top_size }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Days Covered</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->days }}</div>
    </div>
    @elseif($type === 'mortality')
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Total Deaths</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->total_deaths }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Top Cause</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->top_cause }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Most Affected</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->most_affected }}</div>
    </div>
    <div class="flex-1 border border-[#D9D9D9] rounded px-4 py-2 text-center">
        <div class="text-xs tracking-wider text-[#6B7280] uppercase">Days Covered</div>
        <div class="text-lg font-semibold text-[#102A4C]">{{ $summary->days }}</div>
    </div>
    @endif
</div>
@endif
