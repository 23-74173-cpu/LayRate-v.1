<turbo-frame id="chickens-inventory-list">
    @php
        $placedCount = $hensByCage->flatten()->count();
        $totalVisible = $placedCount + $unplacedCount;
    @endphp

    {{-- Unplaced Hens --}}
    @if($unplacedHens->isNotEmpty())
        @include('chickens._unplaced-list')
    @endif

    <div class="space-y-6">

        {{-- ── Cage Overview (full-width row) ── --}}
        <x-card>
            <x-slot:headerSlot>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: #1f1f1f;">Cage Overview</h2>
                        <p class="text-sm" style="color: #6B7280;">Overview of all cages</p>
                    </div>
                    <span class="text-sm font-medium whitespace-nowrap" style="color: #31302e;">
                        Total: <strong style="color: #1f1f1f;">{{ $totalVisible }}</strong> hen(s)
                        @if($unplacedCount > 0)
                        ({{ $unplacedCount }} unplaced)
                        @endif
                    </span>
                </div>
            </x-slot:headerSlot>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @forelse($cageGroups as $hensGroup)
                    @php
                        $cage = $hensGroup->first()->cage;
                        $slotCount = $cage->cageSlots->count();
                        $activeCount = $hensGroup->where('is_active', 1)->count();
                        $inactiveCount = $hensGroup->where('is_active', 0)->count();
                        $totalCapacity = $cage->total_capacity ?? 0;
                        $occupancyPct = $totalCapacity > 0 ? round(($activeCount / $totalCapacity) * 100) : 0;
                    @endphp
                    <div class="rounded-xl border p-4 flex flex-col gap-2 min-h-[7rem] cage-overview-card cursor-pointer transition-all hover:shadow-md"
                         data-cage-id="{{ $cage->id }}"
                         data-cage-code="{{ $cage->cage_code }}"
                         onclick="switchChickenCage('{{ $cage->id }}')"
                         role="button" tabindex="0"
                         onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); switchChickenCage('{{ $cage->id }}'); }"
                         style="background-color: #ffffff; border-color: #e6e6e6;">
                        <div class="flex items-center justify-between gap-2">
                            <x-cage-color :cage="$cage" />
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold whitespace-nowrap shrink-0"
                                  style="background-color: {{ $cage->colorSoft }}; color: {{ $cage->color }};">
                                {{ $slotCount }} slot{{ $slotCount !== 1 ? 's' : '' }}
                            </span>
                        </div>
                        <span class="text-xs truncate" style="color: #615d59;">{{ $cage->formatted_location }}</span>
                        <div class="flex items-center gap-2 text-xs mt-auto" style="color: #a39e98;">
                            <span>Hens:</span>
                            <span class="font-semibold whitespace-nowrap" style="color: {{ $occupancyPct > 100 ? '#9b1c24' : '#1f1f1f' }};">
                                {{ $activeCount }}/{{ $totalCapacity ?: '?' }}
                            </span>
                            <span class="ml-auto font-semibold whitespace-nowrap" style="color: #1f1f1f;">
                                {{ $occupancyPct }}%
                            </span>
                        </div>
                    </div>
                @empty
                    @if($unplacedHens->isEmpty())
                    <div class="w-full rounded-xl border p-10 text-center text-sm col-span-full"
                         style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
                        No hens found matching your filters.
                    </div>
                    @endif
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- ── Cage Slot Modal (rows + slots + per-slot hens) ── --}}
    <div id="cageSlotsModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeChickenCageModal()"></div>

        {{-- Card --}}
        <div class="relative w-full max-w-6xl rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">
                    Cage <span id="cageSlotsModalTitle" style="color: #0075de;">—</span>
                </h2>
                <button type="button" onclick="closeChickenCageModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

        @foreach($cageGroups as $hensGroup)
            @php
                $cage = $hensGroup->first()->cage;
                $hensBySlot = $hensGroup->groupBy(fn($h) => $h->cageSlot?->id);
                $activeCount = $hensGroup->where('is_active', 1)->count();
                $inactiveCount = $hensGroup->where('is_active', 0)->count();
                $cageSlotsForThis = $cage->cageSlots->sortBy(fn($s) => [$s->row_number, $s->column_number]);
                $totalSlots = $cageSlotsForThis->count();
                $maxPerSlot = $cage->max_chickens_per_slot ?? 4;
                $rows = $cage->rows ?? 1;
                $slotsPerRow = $cage->slots_per_row ?? 1;
                $slotsByRowCol = $cageSlotsForThis->keyBy(fn($s) => $s->row_number . '-' . $s->column_number);
                $cellW = max(34, min(104, (int) floor(640 / max(1, $slotsPerRow))));
                $gridWidth = 40 + ($slotsPerRow * $cellW) + (($slotsPerRow - 1) * 6);
            @endphp
            <div class="cage-grid hidden" data-cage-id="{{ $cage->id }}" data-cage-card="{{ $cage->id }}">
                <div class="mb-3 flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-sm font-bold" style="color: {{ $cage->color }};">{{ $cage->cage_code }}</span>
                        <span class="text-xs truncate" style="color: #9CA3AF;">{{ $cage->formatted_location }}</span>
                    </div>
                    <span class="text-xs whitespace-nowrap" style="color: #a39e98;">
                        {{ $activeCount }} active @if($inactiveCount > 0)/ {{ $inactiveCount }} inactive @endif · {{ $totalSlots }} slots
                    </span>
                </div>

                <style>
                .cage-grid .two-col { display: flex; flex-direction: column; gap: 24px; align-items: flex-start; }
                .cage-grid .two-col > div { width: 100%; min-width: 0; }
                @media (min-width: 1024px) {
                    .cage-grid .two-col { flex-direction: row; }
                    .cage-grid .two-col > div:nth-child(1) { width: 44%; }
                    .cage-grid .two-col > div:nth-child(2) { width: 56%; }
                }
                </style>
                <div class="two-col">

                    {{-- ══ LEFT: rows + slots grid ══ --}}
                    <div class="min-w-0">
                        <div class="overflow-x-auto pb-1">
                            <div class="grid gap-1.5" style="grid-template-columns: 40px repeat({{ $slotsPerRow }}, minmax(0, 1fr)); width: 100%; max-width: {{ min($gridWidth, 520) }}px;">
                                {{-- Column headers --}}
                                <div></div>
                                @for($c = 1; $c <= $slotsPerRow; $c++)
                                <div class="flex items-center justify-center text-xs font-semibold" style="color: #a39e98;">C{{ $c }}</div>
                                @endfor
                                {{-- Rows with row labels --}}
                                @for($r = 1; $r <= $rows; $r++)
                                <div class="flex items-center text-xs font-semibold" style="color: #615d59;">R{{ $r }}</div>
                                @for($c = 1; $c <= $slotsPerRow; $c++)
                                    @php
                                        $slot = $slotsByRowCol->get($r . '-' . $c)
                                            ?? $cageSlotsForThis->firstWhere('slot_number', (($r - 1) * $slotsPerRow) + $c);
                                    @endphp
                                    @if(!$slot)
                                    <div class="flex items-center justify-center aspect-square rounded-lg border border-dashed text-xs" style="border-color: #e6e6e6; color: #d1d5db;">—</div>
                                    @else
                                        @php
                                            $isSensor = $slot->hasBreakbeam();
                                            $slotHens = $hensBySlot->get($slot->id, collect());
                                            $activeHenCount = $slotHens->where('is_active', 1)->count();
                                            $primaryHen = $slotHens->where('is_active', 1)->first() ?? $slotHens->first();
                                        @endphp
                                        <button type="button"
                                                class="slot-card slot-mini flex flex-col items-center justify-center aspect-square rounded-lg border transition-all relative select-none {{ $activeHenCount === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}"
                                                style="background-color: {{ $activeHenCount > 0 ? '#f0f7ff' : '#ffffff' }}; border-color: #e6e6e6;"
                                                data-slot-id="{{ $slot->id }}"
                                                data-cage-id="{{ $cage->id }}"
                                                data-cage-code="{{ $cage->cage_code }}"
                                                data-slot-number="{{ $slot->slot_number }}"
                                                data-row="{{ $slot->row_number }}"
                                                data-col="{{ $slot->column_number }}"
                                                data-hens="{{ $activeHenCount }}"
                                                data-breed="{{ $primaryHen?->breed ?? '—' }}"
                                                data-age="{{ $primaryHen?->current_age_weeks ?? 0 }}"
                                                data-has-sensor="{{ $isSensor ? 1 : 0 }}"
                                                data-empty="{{ $activeHenCount === 0 ? 1 : 0 }}"
                                                data-slot-tile="{{ $slot->id }}"
                                                aria-label="{{ $cage->cage_code }} slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $activeHenCount }} hens{{ $activeHenCount === 0 ? ', no hens assigned' : '' }}"
                                                tabindex="{{ $activeHenCount === 0 ? '-1' : '0' }}"
                                                title="{{ $activeHenCount === 0 ? 'No hens assigned to this slot' : 'Slot ' . $slot->row_number . '-' . $slot->column_number . ': ' . $activeHenCount . ' hens' }}"
                                                onclick="showSlotHens({{ $cage->id }}, {{ $slot->id }})">
                                            @if($isSensor)
                                            <span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full" style="background-color: #0075de;" title="Sensor equipped"></span>
                                            @endif
                                            @if($activeHenCount === 0)
                                            <span class="text-xs text-center leading-tight" style="color: #a39e98;">No<br>hens</span>
                                            @else
                                            <span class="text-xs font-semibold leading-none" style="color: {{ $activeHenCount >= $maxPerSlot ? '#9b1c24' : '#1f1f1f' }}">
                                                {{ $activeHenCount }}
                                            </span>
                                            @endif
                                        </button>
                                    @endif
                                @endfor
                                @endfor
                            </div>
                        </div>

                        <div class="mt-2 text-xs" style="color: #a39e98;">
                            Hens:
                            <span class="font-semibold" style="color: #1f1f1f;">{{ $activeCount }}/{{ $cage->total_capacity ?? '?' }}</span>
                            @if($inactiveCount > 0) · {{ $inactiveCount }} inactive @endif
                        </div>
                    </div>

                    {{-- ══ RIGHT: chicken list ══ --}}
                    <div class="min-w-0">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wider" style="color: #6B7280;">Chickens in slot</div>

                        <div class="slot-panel-placeholder rounded-xl border p-8 text-center text-sm" style="border-color: #e6e6e6; background-color: #FAFAFA; color: #a39e98;">
                            <i data-lucide="mouse-pointer-2" class="w-6 h-6 mx-auto mb-2" style="color: #d1d5db;"></i>
                            Click a slot to view its hens
                        </div>

                        {{-- Per-slot hen panels (only the clicked slot's hens shown) --}}
                        @foreach($hensBySlot->sortBy(fn($g) => $g->first()->cageSlot?->slot_number) as $slotId => $slotHens)
                            @php
                                $slot = $slotHens->first()->cageSlot;
                            @endphp
                            <div class="slot-hens-panel hidden rounded-xl border overflow-hidden" style="border-color: #e6e6e6;" data-slot-hens="{{ $slot->id }}">
                                {{-- Slot header --}}
                                <div class="flex items-center justify-between px-4 py-2" style="background-color: #FAFAFA;">
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
                                    </div>
                                </div>

                                {{-- Individual Hens --}}
                                <div class="slot-hens">
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
            </div>
        @endforeach

        </div>
    </div>
</div>

    <x-paginator :paginator="$cageGroups" />

    {{-- Total count --}}
    @if($totalVisible > 0)
    <p class="text-xs text-[#9CA3AF] text-right mt-3">
        Showing {{ $totalVisible }} hen(s)
        @if($unplacedCount > 0)
        ({{ $unplacedCount }} unplaced)
        @endif
    </p>
    @endif
</turbo-frame>
