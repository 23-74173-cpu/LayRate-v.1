<turbo-frame id="chickens-inventory-list">
    {{-- Hen List --}}
    <div class="space-y-3">
        {{-- Unplaced Hens --}}
        @if($unplacedHens->isNotEmpty())
            @include('chickens._unplaced-list')
        @endif

        @forelse($hensByCage as $cageId => $hensGroup)
            @php
                $cage = $hensGroup->first()->cage;
                $slotsInCage = $hensGroup->groupBy(fn($h) => $h->cageSlot?->id)->filter()->sortBy(fn($g) => $g->first()->cageSlot?->slot_number);
            @endphp

            <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
                {{-- Cage header (clickable to expand/collapse slots) --}}
                <div class="flex items-center justify-between px-4 py-2.5 cursor-pointer" style="background: {{ $cage->color }}10; border-bottom: 1px solid {{ $cage->color }}30"
                     onclick="toggleCage(this)">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold" style="color: {{ $cage->color }}">{{ $cage->cage_code }}</span>
                        <span class="text-xs text-[#6B7280]">{{ $cage->formatted_location }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-white/80 text-[#6B7280]">
                            {{ $hensGroup->where('is_active', 1)->count() }} active
                            @if($hensGroup->where('is_active', 0)->count() > 0)
                            / {{ $hensGroup->where('is_active', 0)->count() }} inactive
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="event.stopPropagation(); toggleColumns(this)"
                                class="text-[#9CA3AF] hover:text-[#6B7280] transition-colors p-1 rounded" title="Toggle columns">
                            <i data-lucide="columns" class="w-3.5 h-3.5"></i>
                        </button>
                        <button type="button" onclick="event.stopPropagation(); toggleCage(this)"
                                class="text-[#6B7280] hover:text-[#333] transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4 cage-chevron transition-transform"></i>
                        </button>
                    </div>
                </div>

                {{-- Slots (expandable) --}}
                <div class="cage-slots hidden">
                    @foreach($slotsInCage as $slotId => $slotHens)
                        @php
                            $slot = $slotHens->first()->cageSlot;
                        @endphp
                        <div class="border-t border-[#F0F0F0]">
                            {{-- Slot header --}}
                            <div class="flex items-center justify-between px-4 py-2 bg-[#FAFAFA] cursor-pointer"
                                 onclick="toggleSlot(this)">
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="font-medium text-[#333]">
                                        Slot {{ $slot->row_number }}-{{ $slot->column_number }}
                                        (#{{ $slot->slot_number }})
                                    </span>
                                    @if($slot->hasBreakbeam())
                                    <span class="flex items-center gap-0.5 text-emerald-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> sensor
                                    </span>
                                    @endif
                                    <span class="text-[#9CA3AF]">
                                        {{ $slotHens->count() }}/{{ $cage->max_chickens_per_slot }} hens
                                    </span>
                                    @if($slotHens->count() > 0)
                                    <span class="text-[#9CA3AF]">
                                        · {{ $slotHens->first()->breed }}
                                    </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-[#9CA3AF]">slot actions:</span>
                                    <button type="button"
                                            onclick="event.stopPropagation(); openMoveModal('{{ $slotHens->pluck('id')->join(',') }}', {{ $slotHens->count() }}, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $slotHens->first()->breed ?? '' }}')"
                                            class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Move all">
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <button type="button"
                                            onclick="event.stopPropagation(); openRemoveModal('{{ $slotHens->pluck('id')->join(',') }}', {{ $slotHens->count() }}, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $slotHens->first()->breed ?? '' }}')"
                                            class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Remove all">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <i data-lucide="chevron-down" class="w-3 h-3 text-[#9CA3AF] slot-chevron transition-transform"></i>
                                </div>
                            </div>

                            {{-- Individual Hens --}}
                            <div class="slot-hens hidden">
                                {{-- Column headers --}}
                                <div class="flex items-center gap-3 px-4 py-1.5 border-t border-[#F0F0F0] bg-[#F5F6F8] text-[10px] font-semibold uppercase tracking-wider text-[#6B7280]">
                                    <label class="flex items-center gap-1 cursor-pointer" title="Select all in this slot">
                                        <input type="checkbox" onchange="toggleAllInSlot(this)"
                                               class="w-3 h-3 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]">
                                    </label>
                                    <span data-col="id" class="w-28 shrink-0 whitespace-nowrap col-toggle">Chicken ID</span>
                                    <span data-col="breed" class="w-32 shrink-0 whitespace-nowrap col-toggle">Breed</span>
                                    <span data-col="age" class="w-12 shrink-0 col-toggle">Age</span>
                                    <span data-col="flock" class="w-16 shrink-0 col-toggle">Flock</span>
                                    <span data-col="status" class="flex-1 col-toggle">Status</span>
                                    <span data-col="actions" class="col-toggle">Actions</span>
                                </div>
                                @foreach($slotHens as $hen)
                                <div class="flex items-center gap-3 px-4 py-2 border-t border-[#F5F5F5] hover:bg-[#FAFAFA] text-xs">
                                    <input type="checkbox" class="hen-checkbox w-3.5 h-3.5 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]"
                                           value="{{ $hen->id }}"
                                           onclick="updateBulkBar()">
                                    <span data-col="id" class="w-28 shrink-0 whitespace-nowrap font-mono text-[#6B7280] col-toggle">{{ $hen->tag_code ?? $hen->chicken_id ?? '—' }}</span>
                                    <span data-col="breed" class="w-32 shrink-0 whitespace-nowrap text-[#333] col-toggle">{{ $hen->breed }}</span>
                                    <span data-col="age" class="w-12 shrink-0 text-[#6B7280] col-toggle">{{ $hen->current_age_weeks }}w</span>
                                    <span data-col="flock" class="w-16 shrink-0 text-[#6B7280] col-toggle">flock {{ $hen->flock_age_weeks }}w</span>
                                    <span data-col="status" class="flex-1 col-toggle">
                                        @if($hen->is_active)
                                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Active</span>
                                        @else
                                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactive</span>
                                        @endif
                                    </span>
                                    <div data-col="actions" class="flex items-center gap-1 col-toggle">
                                        <button type="button"
                                                onclick="openWeightCheckModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')"
                                                class="p-1.5 rounded-full hover:bg-blue-50 transition-colors" style="color: #a39e98;" aria-label="Record weight">
                                            <i data-lucide="scale" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button type="button"
                                                onclick="openHealthEventModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')"
                                                class="p-1.5 rounded-full hover:bg-green-50 transition-colors" style="color: #a39e98;" aria-label="Log health event">
                                            <i data-lucide="heart" class="w-3.5 h-3.5"></i>
                                        </button>
                                        @if($hen->is_active)
                                        <button type="button"
                                                 onclick="openCullModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')"
                                                 class="p-1.5 rounded-full hover:bg-orange-50 transition-colors" style="color: #a39e98;" aria-label="Cull hen">
                                            <i data-lucide="crosshair" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button type="button"
                                                 onclick="openRemovalModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')"
                                                 class="p-1.5 rounded-full hover:bg-purple-50 transition-colors" style="color: #a39e98;" aria-label="Remove/sell hen">
                                            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                                        </button>
                                        @endif
                                        <button type="button"
                                                 onclick="openMoveModal('{{ $hen->id }}', 1, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $hen->breed }}')"
                                                 class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Move hen">
                                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button type="button"
                                                onclick="openRemoveModal('{{ $hen->id }}', 1, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $hen->breed }}')"
                                                class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Remove hen">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            @if($unplacedHens->isEmpty())
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-10 text-center text-sm text-[#9CA3AF]">
                No hens found matching your filters.
            </div>
            @endif
        @endforelse
    </div>

    {{-- Total count --}}
    @php
        $placedCount = $hensByCage->flatten()->count();
        $totalVisible = $placedCount + $unplacedCount;
    @endphp
    @if($totalVisible > 0)
    <p class="text-xs text-[#9CA3AF] text-right mt-3">
        Showing {{ $totalVisible }} hen(s)
        @if($unplacedCount > 0)
        ({{ $unplacedCount }} unplaced)
        @endif
    </p>
    @endif
</turbo-frame>
