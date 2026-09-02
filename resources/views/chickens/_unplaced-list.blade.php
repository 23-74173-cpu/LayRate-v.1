<div id="unplacedCard" class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2.5"
         style="background: #F0F4FF; border-bottom: 1px solid #CCDDFF;">
        <span class="flex items-center gap-3">
            <span class="text-sm font-semibold text-[#1D4E8F]">Unplaced</span>
            <span class="text-xs px-1.5 py-0.5 rounded-full bg-white/80 text-[#6B7280]">
                {{ $unplacedCount }} hen(s)
            </span>
        </span>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('cages.bulk-add', ['hen_ids' => $unplacedHens->pluck('id')->join(',')]) }}"
               class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg text-white bg-[#002D5E] hover:bg-[#001F42] transition-colors">
                <i data-lucide="arrow-right" class="w-3 h-3"></i> Place into cage
            </a>
        </div>
    </div>

    <div class="divide-y divide-[#F0F0F0]">
        @forelse($unplacedHens as $hen)
        <div class="flex items-center gap-3 px-4 py-2 hover:bg-[#FAFAFA] text-xs">
            <span class="font-mono text-[#6B7280] shrink-0">{{ $hen->chicken_id ?? '—' }}</span>
            <span class="text-[#333] shrink-0 hidden sm:inline">{{ $hen->breed }}</span>
            <span class="text-[#6B7280] shrink-0 hidden sm:inline">{{ $hen->current_age_weeks }}w</span>
            <span class="flex-1"></span>
            <div class="flex items-center gap-1 shrink-0">
                <a href="{{ route('cages.bulk-add', ['hen_ids' => $hen->id]) }}"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-medium text-[#002D5E] hover:bg-blue-50 transition-colors">
                    <i data-lucide="arrow-right" class="w-3 h-3"></i> Place
                </a>
                <x-icon-button icon="crosshair" label="Cull hen" color="orange"
                    onclick="openCullModal('{{ $hen->id }}', '{{ $hen->chicken_id }} (unplaced)')" />
            </div>
        </div>
        @empty
        <div class="px-4 py-3 text-xs text-[#9CA3AF] text-center">No unplaced hens.</div>
        @endforelse
    </div>
</div>
