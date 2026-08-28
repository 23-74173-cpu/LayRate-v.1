<div id="unplacedCard" class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2.5"
         style="background: #F0F4FF; border-bottom: 1px solid #CCDDFF;">
        <button type="button" onclick="toggleUnplaced()"
                class="flex items-center gap-3 text-left cursor-pointer" style="flex: 1; min-width: 0;">
            <span class="flex items-center gap-3">
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0" style="color: #1D4E8F; transition: transform 0.2s ease;" id="unplacedChevron"></i>
                <span class="text-sm font-semibold text-[#1D4E8F]">Unplaced</span>
                <span class="text-xs px-1.5 py-0.5 rounded-full bg-white/80 text-[#6B7280]">
                    {{ $unplacedCount }} hen(s)
                </span>
            </span>
        </button>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('cages.bulk-add', ['hen_ids' => $unplacedHens->pluck('id')->join(',')]) }}"
               class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg text-white bg-[#002D5E] hover:bg-[#001F42] transition-colors">
                <i data-lucide="arrow-right" class="w-3 h-3"></i> Place into cage
            </a>
        </div>
    </div>

    @php $unplacedPerPage = 4; @endphp
    <div class="slot-hens divide-y divide-[#F0F0F0] hidden" data-unplaced-list id="unplacedList">
        {{-- Column headers --}}
        <div class="flex flex-wrap items-center gap-3 px-4 py-1.5 bg-[#F5F6F8] text-xs font-semibold uppercase tracking-wider text-[#6B7280]">
            <label class="flex items-center gap-1 cursor-pointer" title="Select all unplaced">
                <input type="checkbox" onchange="toggleAllInSlot(this)"
                       class="w-3 h-3 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]">
            </label>
            <span class="w-28 shrink-0 whitespace-nowrap">Hen ID</span>
            <span class="w-32 shrink-0 whitespace-nowrap hidden sm:inline">Breed</span>
            <span class="w-12 shrink-0 whitespace-nowrap hidden sm:inline">Age</span>
            <span class="flex-1"></span>
            <span class="shrink-0 w-full sm:w-auto">Actions</span>
        </div>
        @foreach($unplacedHens as $hen)
        <div class="flex flex-wrap items-center gap-3 px-4 py-2 hover:bg-[#FAFAFA] text-xs unplaced-row"
             data-unplaced-index="{{ $loop->index }}">
            <input type="checkbox" class="hen-checkbox w-3.5 h-3.5 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E] shrink-0"
                   value="{{ $hen->id }}"
                   onclick="updateBulkBar()">
            <span class="w-28 shrink-0 whitespace-nowrap font-mono text-[#6B7280]">{{ $hen->chicken_id ?? '—' }}</span>
            <span class="w-32 shrink-0 whitespace-nowrap text-[#333] hidden sm:inline">{{ $hen->breed }}</span>
            <span class="w-12 shrink-0 whitespace-nowrap text-[#6B7280] hidden sm:inline">{{ $hen->current_age_weeks }}w</span>
            <span class="flex-1"></span>
            <div class="flex items-center gap-1 shrink-0 w-full sm:w-auto justify-end sm:justify-start pt-1 sm:pt-0 border-t border-[#F5F5F5] sm:border-t-0">
                <a href="{{ route('cages.bulk-add', ['hen_ids' => $hen->id]) }}"
                   class="p-1.5 rounded-full hover:bg-blue-50 transition-colors" style="color: #a39e98;" aria-label="Place into cage">
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
                <x-icon-button icon="crosshair" label="Cull hen" color="orange"
                    onclick="openCullModal('{{ $hen->id }}', '{{ $hen->chicken_id }} (unplaced)')" />
                <x-icon-button icon="log-out" label="Remove hen" color="purple"
                    onclick="openRemovalModal('{{ $hen->id }}', '{{ $hen->chicken_id }} (unplaced)')" />
            </div>
        </div>
        @endforeach
    </div>

    @if($unplacedHens->count() > $unplacedPerPage)
    <div class="flex items-center justify-between px-4 py-2 border-t border-[#F0F0F0] bg-[#FAFAFA] text-xs text-[#6B7280] hidden" id="unplacedPagination">
        <span data-unplaced-range></span>
        <div class="flex items-center gap-2">
            <button type="button" data-unplaced-prev onclick="unplacedPageMove(-1)"
                    class="px-2 py-1 rounded border border-[#D9D9D9] hover:bg-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
            <button type="button" data-unplaced-next onclick="unplacedPageMove(1)"
                    class="px-2 py-1 rounded border border-[#D9D9D9] hover:bg-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
        </div>
    </div>
    @endif
</div>
