<turbo-frame id="chickens-inventory-list">
    {{-- Hen List --}}
    <div class="space-y-3">
        {{-- Unplaced Hens --}}
        @if($unplacedHens->isNotEmpty())
            @include('chickens._unplaced-list')
        @endif

        @forelse($cageGroups as $hensGroup)
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
                                    <x-icon-button icon="arrow-right" label="Move all" color="neutral"
                                        onclick="event.stopPropagation(); openMoveModal('{{ $slotHens->pluck('id')->join(',') }}', {{ $slotHens->count() }}, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $slotHens->first()->breed ?? '' }}')" />
                                    <x-icon-button icon="trash-2" label="Remove all" color="red"
                                        onclick="event.stopPropagation(); openRemoveModal('{{ $slotHens->pluck('id')->join(',') }}', {{ $slotHens->count() }}, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $slotHens->first()->breed ?? '' }}')" />
                                    <i data-lucide="chevron-down" class="w-3 h-3 text-[#9CA3AF] slot-chevron transition-transform"></i>
                                </div>
                            </div>

                            {{-- Individual Hens --}}
                            <div class="slot-hens hidden">
                                {{-- Column headers --}}
                                <div class="flex flex-wrap items-center gap-3 px-4 py-1.5 border-t border-[#F0F0F0] bg-[#F5F6F8] text-xs font-semibold uppercase tracking-wider text-[#6B7280]">
                                    <label class="flex items-center gap-1 cursor-pointer" title="Select all in this slot">
                                        <input type="checkbox" onchange="toggleAllInSlot(this)"
                                               class="w-3 h-3 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]">
                                    </label>
                                    <span data-col="id" class="w-28 shrink-0 whitespace-nowrap col-toggle">Chicken ID</span>
                                    <span data-col="breed" class="w-32 shrink-0 whitespace-nowrap col-toggle hidden sm:inline">Breed</span>
                                    <span data-col="age" class="w-12 shrink-0 col-toggle hidden sm:inline">Age</span>
                                    <span data-col="flock" class="w-16 shrink-0 col-toggle hidden sm:inline">Flock</span>
                                    <span data-col="status" class="flex-1 col-toggle">Status</span>
                                    <span data-col="actions" class="col-toggle w-full sm:w-auto">Actions</span>
                                </div>
                                @foreach($slotHens as $hen)
                                <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-t border-[#F5F5F5] hover:bg-[#FAFAFA] text-xs">
                                    <input type="checkbox" class="hen-checkbox w-3.5 h-3.5 rounded border-[#D9D9D9] text-[#002D5E] focus:ring-[#002D5E]"
                                           value="{{ $hen->id }}"
                                           onclick="updateBulkBar()">
                                    <span data-col="id" class="w-28 shrink-0 whitespace-nowrap font-mono text-[#6B7280] col-toggle">{{ $hen->tag_code ?? $hen->chicken_id ?? '—' }}</span>
                                    <span data-col="breed" class="w-32 shrink-0 whitespace-nowrap text-[#333] col-toggle hidden sm:inline">{{ $hen->breed }}</span>
                                    <span data-col="age" class="w-12 shrink-0 text-[#6B7280] col-toggle hidden sm:inline">{{ $hen->current_age_weeks }}w</span>
                                    <span data-col="flock" class="w-16 shrink-0 text-[#6B7280] col-toggle hidden sm:inline">flock {{ $hen->flock_age_weeks }}w</span>
                                    <span data-col="status" class="flex-1 col-toggle">
                                        <x-status-badge :status="$hen->is_active ? 'Active' : 'Inactive'" type="general" />
                                    </span>
                                    <div data-col="actions" class="flex items-center gap-1 col-toggle w-full sm:w-auto justify-end sm:justify-start pt-1 sm:pt-0 border-t border-[#F5F5F5] sm:border-t-0">
                                        <x-icon-button icon="scale" label="Record weight" color="blue"
                                            onclick="openWeightCheckModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')" />
                                        <x-icon-button icon="heart" label="Log health event" color="green"
                                            onclick="openHealthEventModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')" />
                                        @if($hen->is_active)
                                        <x-icon-button icon="crosshair" label="Cull hen" color="orange"
                                            onclick="openCullModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')" />
                                        <x-icon-button icon="log-out" label="Remove hen" color="purple"
                                            onclick="openRemovalModal('{{ $hen->id }}', '{{ $hen->tag_code ?? $hen->chicken_id }} ({{ $cage->cage_code }} slot {{ $slot->slot_number }})')" />
                                        @endif
                                        <x-icon-button icon="arrow-right" label="Move hen" color="neutral"
                                            onclick="openMoveModal('{{ $hen->id }}', 1, '{{ $cage->cage_code }} slot {{ $slot->slot_number }}', '{{ $hen->breed }}')" />
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

    <x-paginator :paginator="$cageGroups" />

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
