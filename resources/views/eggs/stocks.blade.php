@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
<div class="space-y-5">

    <x-page-header title="Egg Management" subtitle="Track harvested egg inventory by size and freshness" subtitle-id="egg-header-subtitle" actions-id="egg-header-actions">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <x-button variant="secondary" onclick="openEggWeightsModal()">
                    <i data-lucide="weight" class="w-4 h-4"></i> Egg Weights
                </x-button>
                <x-button variant="secondary" onclick="openThresholdsModal()">
                    <i data-lucide="sliders" class="w-4 h-4"></i> Thresholds
                </x-button>
                <x-button onclick="document.getElementById('addStockModal').style.display = 'flex'">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add Stock
                </x-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    @include('eggs._tabs', ['activeTab' => 'stocks'])

    <turbo-frame id="egg-content">
    <div class="space-y-5">

    {{-- ── Summary Cards ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4" id="summaryCards">
        @foreach($sizes as $size)
        @php
            $total = $totals[$size] ?? 0;
            $trays = $trayTotals[$size] ?? 0;
            $label = $size === 'unsorted' ? 'Unsorted' : ucfirst($size);
            $pool = $availablePools[$size] ?? 0;
            $threshold = $eggStockThresholds[$size] ?? 0;
            $isLowStock = $threshold > 0 && $pool <= $threshold;
            $colors = [
                'small'    => ['#2D7D46', '#d6f0e3'],
                'medium'   => ['#1D4E8F', '#dcebfa'],
                'large'    => ['#C2703E', '#fae3d0'],
                'jumbo'    => ['#6B4C8A', '#e9e0f5'],
                'unsorted' => ['#6B7280', '#f0f0f0'],
            ];
            [$color, $soft] = $colors[$size];
        @endphp
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4 {{ $isLowStock ? 'ring-2 ring-amber-400' : '' }}" data-size="{{ $size }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: {{ $color }}">{{ $label }}</span>
                @if($isLowStock)
                <x-status-badge status="Low" type="general" />
                @endif
            </div>
            <div class="text-2xl font-bold leading-none tracking-[-0.5px] text-[#333333]">{{ number_format($total) }}</div>
            <div class="text-xs mt-1 text-[#6B7280]">{{ $trays }} {{ $trays === 1 ? 'tray' : 'trays' }}</div>
            <div class="text-xs mt-1.5 text-[#a39e98]">
                <span class="font-medium" style="color: {{ $color }}">{{ number_format($pool) }}</span> available to stock
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Stock Table ── --}}
    <turbo-frame id="eggs-stocks-live-data" src="{{ route('eggs.stocks.live-data') }}" loading="lazy" target="_top" class="block">
        @include('eggs.stocks._live-data-skeleton')
    </turbo-frame>

    </div>

{{-- Add Stock Modal --}}
<div id="addStockModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddStockModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add Stock Batch</h2>
            <button onclick="closeAddStockModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <div id="addStockErrors" class="hidden p-3 rounded-lg mb-4 text-xs leading-relaxed" style="background-color: #fbe4e6; border: 1px solid #f3cdd0; color: #9b1c24;"></div>

        <form id="addStockForm">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG SIZE</label>
                    <select name="egg_size" id="addStockEggSize" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select size…</option>
                        @foreach(['small','medium','large','jumbo','unsorted'] as $sz)
                        @php $pool = $availablePools[$sz] ?? 0; $label = $sz === 'unsorted' ? 'Unsorted' : ucfirst($sz); @endphp
                        <option value="{{ $sz }}" data-available="{{ $pool }}">{{ $label }} ({{ number_format($pool) }} avail.)</option>
                        @endforeach
                    </select>
                    <p id="addStockPoolNote" class="text-xs mt-1" style="color: #9CA3AF;">Pool reflects logged minus stocked eggs. Any size can be selected — server validates against pool on save.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG COUNT</label>
                    <input type="number" name="count" id="addStockCount" min="1" placeholder="Enter quantity" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                    <p id="addStockPoolHint" class="text-xs mt-1" style="color: #a39e98;">Select a size and source cage to see available pool.</p>
                    <p id="addStockExceedsWarning" class="hidden text-xs mt-1" style="color: #9B1C24;"></p>
                </div>

                <div id="classifySection" class="hidden p-4 rounded-lg border border-dashed" style="border-color: #D9D9D9; background-color: #fafafa;">
                    <div class="flex items-center gap-2 mb-3">
                        <i data-lucide="shuffle" class="w-4 h-4" style="color: #6B7280;"></i>
                        <span class="text-xs font-semibold tracking-[0.05em] uppercase" style="color: #615d59;">Classify Unsorted to Sizes</span>
                    </div>
                    <p class="text-xs text-[#6B7280] mb-3">Optionally distribute these eggs into standard sizes. Leave blank to stock as Unsorted.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-[#2D7D46] font-medium mb-1">Small</label>
                            <input type="number" name="classify_small" min="0" placeholder="0"
                                   class="classify-input w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs text-[#1D4E8F] font-medium mb-1">Medium</label>
                            <input type="number" name="classify_medium" min="0" placeholder="0"
                                   class="classify-input w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs text-[#C2703E] font-medium mb-1">Large</label>
                            <input type="number" name="classify_large" min="0" placeholder="0"
                                   class="classify-input w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs text-[#6B4C8A] font-medium mb-1">Jumbo</label>
                            <input type="number" name="classify_jumbo" min="0" placeholder="0"
                                   class="classify-input w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                    </div>
                    <div id="classifyRemaining" class="flex items-center gap-2 text-xs mt-2 font-medium" style="color: #6B7280;">
                        <span>Remaining to classify:</span>
                        <span id="classifyRemainingCount">0</span>
                    </div>
                    <p id="classifySumHint" class="text-xs mt-1 hidden" style="color: #a39e98;"></p>
                </div>

                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">HARVESTED DATE</label>
                    <input type="date" name="harvested_date" value="{{ now()->toDateString() }}" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">SOURCE CAGE <span class="font-normal normal-case tracking-normal" style="color: #9CA3AF;">— pool scope</span></label>
                    <select name="cage_id" id="stockCageSelect"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">All Cages (farm-wide pool)</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }} — {{ $cage->formatted_location }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs mt-1" style="color: #9CA3AF;">Select a cage to scope the available pool, or leave as "All Cages" for farm-wide pool.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">PRODUCTION LOG <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <select name="source_production_log_id" id="stockLogSelect"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select log…</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeAddStockModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Cancel</button>
                <button type="submit" id="addStockBtn"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg bg-[#002D5E] text-white hover:bg-[#001F42] transition-colors">Add Stock</button>
            </div>
        </form>
    </div>
</div>
{{-- Edit Stock Modal --}}
<div id="editStockModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditStockModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Stock Batch</h2>
            <button onclick="closeEditStockModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="editStockForm" method="POST" action="{{ route('eggs.stocks.update', 0) }}" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG SIZE</label>
                    <select name="egg_size" id="editEggSize" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="small">Small</option>
                        <option value="medium">Medium</option>
                        <option value="large">Large</option>
                        <option value="jumbo">Jumbo</option>
                        <option value="unsorted">Unsorted</option>
                    </select>
                    <x-input-error name="egg_size" />
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">EGG COUNT</label>
                    <input type="number" name="count" id="editEggCount" min="1" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                    <x-input-error name="count" />
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">HARVESTED DATE</label>
                    <input type="date" name="harvested_date" id="editHarvestedDate" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                    <x-input-error name="harvested_date" />
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditStockModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">
                    Cancel
                </button>
                <x-button type="submit" class="flex-1 py-2.5">
                    Save Changes
                </x-button>
            </div>
        </form>
    </div>
</div>

{{-- Egg Weights Modal --}}
<div id="eggWeightsModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEggWeightsModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Egg Weights</h2>
            <button onclick="closeEggWeightsModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <p class="text-xs text-[#6B7280] mb-4">Average weights used to estimate egg mass for Feed Conversion Ratio calculations.</p>

        <form action="{{ route('eggs.stocks.egg-weights') }}" method="POST">
            @csrf
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">SMALL (g)</label>
                    <input type="number" name="egg_weight_small" step="0.1" min="1" max="500"
                           value="{{ $eggWeights['small'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">MEDIUM (g)</label>
                    <input type="number" name="egg_weight_medium" step="0.1" min="1" max="500"
                           value="{{ $eggWeights['medium'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">LARGE (g)</label>
                    <input type="number" name="egg_weight_large" step="0.1" min="1" max="500"
                           value="{{ $eggWeights['large'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">JUMBO (g)</label>
                    <input type="number" name="egg_weight_jumbo" step="0.1" min="1" max="500"
                           value="{{ $eggWeights['jumbo'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">FALLBACK (g)</label>
                    <input type="number" name="egg_weight_fallback" step="0.1" min="1" max="500"
                           value="{{ $eggWeights['fallback'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
            </div>

            @if($errors->any())
            <div class="mt-4 text-xs text-red-500 leading-relaxed">{{ implode('<br>', $errors->all()) }}</div>
            @endif

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEggWeightsModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5">
                    Save Weights
                </x-button>
            </div>
        </form>
    </div>
</div>

{{-- Thresholds Modal --}}
<div id="thresholdsModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeThresholdsModal()"></div>
    <div class="relative w-full max-w-lg rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Thresholds</h2>
            <button onclick="closeThresholdsModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form action="{{ route('eggs.stocks.thresholds') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xs font-medium tracking-[0.05em] uppercase mb-3" style="color: #615d59;">Low-Stock Thresholds</h3>
                    <p class="text-xs text-[#6B7280] mb-4">Minimum available pool per size before alert triggers. Set to 0 to disable.</p>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach(['small' => '#2D7D46', 'medium' => '#1D4E8F', 'large' => '#C2703E', 'jumbo' => '#6B4C8A', 'unsorted' => '#6B7280'] as $sz => $szColor)
                        @php $key = "egg_low_stock_threshold_{$sz}"; $label = $sz === 'unsorted' ? 'Unsorted' : ucfirst($sz); @endphp
                        <div>
                            <label class="block text-xs tracking-wider mb-1.5" style="color: {{ $szColor }}">{{ $label }}</label>
                            <input type="number" name="{{ $key }}" min="0" placeholder="0"
                                   value="{{ $eggStockThresholds[$sz] ?? 0 }}"
                                   class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-medium tracking-[0.05em] uppercase mb-3" style="color: #615d59;">Freshness Thresholds</h3>
                    <p class="text-xs text-[#6B7280] mb-4">Day boundaries for freshness status (Fresh → Aging → Old).</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs tracking-wider text-[#1f6b3a] mb-1.5">Fresh (≤ days)</label>
                            <input type="number" name="egg_freshness_fresh_days" min="1" placeholder="7"
                                   value="{{ $freshnessThresholds['fresh_days'] }}"
                                   class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        </div>
                        <div>
                            <label class="block text-xs tracking-wider text-[#8a5a00] mb-1.5">Aging (≤ days)</label>
                            <input type="number" name="egg_freshness_aging_days" min="1" placeholder="14"
                                   value="{{ $freshnessThresholds['aging_days'] }}"
                                   class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeThresholdsModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5">
                    Save Configuration
                </x-button>
            </div>
        </form>
    </div>
</div>

<script>
@php
    $cageLogsJson = $productionLogs->map(function($logs) {
        return $logs->map(function($log) {
            return [
                'id' => $log->id,
                'date' => $log->log_date->toDateString(),
                'cage_code' => $log->cageSlot?->cage?->cage_code,
            ];
        })->values();
    });
@endphp
var cageLogs = @json($cageLogsJson);

function closeAddStockModal() {
    document.getElementById('addStockModal').style.display = 'none';
}

function openEditStock(id, eggSize, count, harvestedDate) {
    document.getElementById('editStockForm').action = '/eggs/stocks/' + id;
    document.getElementById('editEggSize').value = eggSize;
    document.getElementById('editEggCount').value = count;
    document.getElementById('editHarvestedDate').value = harvestedDate;
    document.getElementById('editStockModal').style.display = 'flex';
    lucide.createIcons();
}

function closeEditStockModal() {
    document.getElementById('editStockModal').style.display = 'none';
}

function openEggWeightsModal() {
    document.getElementById('eggWeightsModal').style.display = 'flex';
    lucide.createIcons();
}

function closeEggWeightsModal() {
    document.getElementById('eggWeightsModal').style.display = 'none';
}

function openThresholdsModal() {
    document.getElementById('thresholdsModal').style.display = 'flex';
    lucide.createIcons();
}

function closeThresholdsModal() {
    document.getElementById('thresholdsModal').style.display = 'none';
}

function updateLogSelect(cageId) {
    var select = document.getElementById('stockLogSelect');
    select.innerHTML = '<option value="">Select log\u2026</option>';
    if (!cageId || !cageLogs[cageId]) return;
    cageLogs[cageId].forEach(function(log) {
        var opt = document.createElement('option');
        opt.value = log.id;
        opt.textContent = log.date;
        select.appendChild(opt);
    });
}

{
    // This whole <script> block lives inside turbo-frame#egg-content, so Turbo
    // re-evaluates it (registers everything again) on every tab round-trip —
    // it is NOT just innerHTML-replaced. That means the *binding logic* must
    // re-run on every render (old fix), but the *document-level listeners
    // that call it* must be registered exactly once — otherwise each round
    // trip stacks another 'turbo:frame-load' listener on `document` (which
    // persists across renders), and N stacked listeners then call the
    // binding logic N times for a single real event. Reassigning
    // window.__bindEggStocks is harmless (it only does fresh DOM queries
    // internally, no captured element state), so exactly one persistent
    // listener always invoking "whichever version is current" is correct.
    window.__bindEggStocks = function() {
        var cageSelect = document.getElementById('stockCageSelect');
        if (cageSelect) {
            cageSelect.addEventListener('change', function() {
                updateLogSelect(this.value);
            });
        }

        var sizeSelect = document.getElementById('addStockEggSize');
        var poolHint = document.getElementById('addStockPoolHint');
        var addBtn = document.getElementById('addStockBtn');
        var countInput = document.getElementById('addStockCount');
        var cageSelect = document.getElementById('stockCageSelect');
        var classifySection = document.getElementById('classifySection');
        var classifyInputs = document.querySelectorAll('.classify-input');
        var classifySumHint = document.getElementById('classifySumHint');
        var classifyRemainingCount = document.getElementById('classifyRemainingCount');

        var eggsStocksBase = '{{ url('eggs/stocks') }}';

        function fetchPoolForCage(cageId, callback) {
            var url = eggsStocksBase + '/pool-data?cage_id=' + (cageId || 0);
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.pools) callback(data.pools);
                })
                .catch(function() { console.warn('fetchPoolForCage failed'); });
        }

        function updateOptionsFromPools(pools) {
            if (!sizeSelect) return;
            for (var i = 0; i < sizeSelect.options.length; i++) {
                var opt = sizeSelect.options[i];
                if (!opt.value) continue;
                var avail = pools[opt.value] || 0;
                opt.setAttribute('data-available', avail);
                opt.textContent = (opt.value === 'unsorted' ? 'Unsorted' : ucfirst(opt.value)) + ' (' + numberFormat(avail) + ' avail.)';
            }
            updateAddStockHint();
        }

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function numberFormat(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function updateAddStockHint() {
            if (!sizeSelect || !poolHint) return;
            var opt = sizeSelect.options[sizeSelect.selectedIndex];
            if (!opt || !opt.value) {
                poolHint.textContent = 'Select a size to see available pool.';
                if (addBtn) addBtn.disabled = true;
                if (countInput) countInput.disabled = true;
                if (classifySection) classifySection.classList.add('hidden');
                return;
            }
            if (addBtn) addBtn.disabled = false;
            var avail = parseInt(opt.getAttribute('data-available') || '0');
            if (avail < 1) {
                poolHint.textContent = 'No eggs available for this size yet \u2014 stock will be validated against production records.';
            } else {
                poolHint.textContent = avail + ' egg(s) available to stock for this size.';
            }
            if (countInput) countInput.disabled = false;

            if (classifySection) {
                if (opt.value === 'unsorted') {
                    classifySection.classList.remove('hidden');
                } else {
                    classifySection.classList.add('hidden');
                }
            }

            checkCountExceedsPool();
        }

        function checkCountExceedsPool() {
            var warningEl = document.getElementById('addStockExceedsWarning');
            if (!warningEl || !sizeSelect || !countInput || !addBtn) return;
            var opt = sizeSelect.options[sizeSelect.selectedIndex];
            var avail = opt && opt.value ? parseInt(opt.getAttribute('data-available') || '0') : 0;
            var entered = parseInt(countInput.value) || 0;

            if (opt && opt.value && entered > avail && avail >= 0) {
                warningEl.textContent = 'Only ' + avail + ' egg(s) available \u2014 stocking ' + entered + ' may exceed pool.';
                warningEl.classList.remove('hidden');
            } else {
                warningEl.classList.add('hidden');
            }
        }

        function updateClassifyStatus() {
            var count = parseInt(countInput ? countInput.value : 0) || 0;
            var sum = 0;
            classifyInputs.forEach(function(inp) { sum += parseInt(inp.value || 0); });
            var remaining = count - sum;

            if (classifyRemainingCount) {
                classifyRemainingCount.textContent = Math.max(0, remaining);
            }

            var remainingEl = document.getElementById('classifyRemaining');
            if (!remainingEl) return;

            if (sum > 0 || count > 0) {
                remainingEl.style.display = 'flex';
                if (remaining === 0 && sum > 0) {
                    remainingEl.style.color = '#2D7D46';
                    remainingEl.innerHTML = '<span>\u2713 All ' + sum + ' eggs classified.</span>';
                    if (classifySumHint) { classifySumHint.classList.add('hidden'); }
                } else if (remaining < 0) {
                    remainingEl.style.color = '#DC2626';
                    remainingEl.innerHTML = '<span>Over-allocated by ' + Math.abs(remaining) + ' egg(s).</span>';
                    if (classifySumHint) {
                        classifySumHint.classList.remove('hidden');
                        classifySumHint.textContent = 'Reduce classification amounts to match egg count (' + count + '), or leave blank to stock as Unsorted.';
                        classifySumHint.style.color = '#DC2626';
                    }
                } else {
                    remainingEl.style.color = '#6B7280';
                    remainingEl.innerHTML = '<span>Remaining to classify: <strong>' + remaining + '</strong></span>';
                    if (classifySumHint) {
                        if (sum > 0) {
                            classifySumHint.classList.remove('hidden');
                            classifySumHint.textContent = 'Classification sum (' + sum + ') is less than egg count (' + count + ').';
                            classifySumHint.style.color = '#C2703E';
                        } else {
                            classifySumHint.classList.add('hidden');
                        }
                    }
                }
            } else {
                remainingEl.style.display = 'none';
                if (classifySumHint) classifySumHint.classList.add('hidden');
            }
        }

        if (cageSelect) {
            cageSelect.addEventListener('change', function() {
                var cageId = this.value || null;
                fetchPoolForCage(cageId, function(pools) {
                    updateOptionsFromPools(pools);
                });
            });
        }

        if (sizeSelect) {
            sizeSelect.addEventListener('change', updateAddStockHint);
            updateAddStockHint();
        }

        if (countInput) {
            countInput.addEventListener('input', updateClassifyStatus);
            countInput.addEventListener('input', checkCountExceedsPool);
        }
        classifyInputs.forEach(function(inp) {
            inp.addEventListener('input', updateClassifyStatus);
        });

        var form = document.getElementById('addStockForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                confirmModal('Add this stock batch?', { submit: performAddStock }, 'Add Batch');
            });
        }

        function performAddStock() {
                var btn = document.getElementById('addStockBtn');
                var originalLabel = btn.textContent;
                loadingButton(btn, 'Adding\u2026');

                var csrf = document.querySelector('meta[name="csrf-token"]').content;
                var formData = new FormData(form);
                var body = {};
                formData.forEach(function(val, key) { body[key] = val; });

                if (body['cage_id'] === '') {
                    delete body['cage_id'];
                }

                var eggSize = document.getElementById('addStockEggSize').value;
                var classifySum = 0;
                document.querySelectorAll('.classify-input').forEach(function(inp) {
                    classifySum += parseInt(inp.value || 0);
                });
                if (eggSize === 'unsorted' && classifySum > 0) {
                    body['classify'] = '1';
                }

                console.log('[Transaction] POST /eggs/stocks', body);

                fetch(eggsStocksBase, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(body),
                })
                .then(function(r) { return r.json().then(function(d) { return { status: r.status, body: d }; }); })
                .then(function(res) {
                    if (res.body.success) {
                        closeAddStockModal();
                        form.reset();

                        var totals = res.body.totals;
                        var trayTotals = res.body.trayTotals;
                        var pools = res.body.availablePools;
                        if (totals) {
                            document.querySelectorAll('#summaryCards [data-size]').forEach(function(card) {
                                var size = card.dataset.size;
                                var total = totals[size] || 0;
                                var trays = trayTotals[size] || 0;
                                var pool = pools[size] || 0;

                                var totalEl = card.querySelector('.text-2xl');
                                if (totalEl) totalEl.textContent = numberFormat(total);

                                var trayEl = card.querySelector('.text-xs.mt-1');
                                if (trayEl) trayEl.textContent = trays + ' ' + (trays === 1 ? 'tray' : 'trays');

                                var poolEl = card.querySelector('.font-medium');
                                if (poolEl) poolEl.textContent = numberFormat(pool);
                            });
                        }

                        var liveFrame = document.getElementById('eggs-stocks-live-data');
                        if (liveFrame) {
                            var src = liveFrame.getAttribute('src');
                            if (src) liveFrame.setAttribute('src', src);
                        }

                        return;
                    }
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                    var errEl = document.getElementById('addStockErrors');
                    if (errEl && res.body.errors) {
                        var msgs = [];
                        for (var key in res.body.errors) {
                            if (res.body.errors.hasOwnProperty(key)) {
                                res.body.errors[key].forEach(function(m) { msgs.push(m); });
                            }
                        }
                        errEl.innerHTML = '<strong class="block mb-1">Could not save:</strong>' + msgs.join('<br>');
                        errEl.classList.remove('hidden');
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                });
        }
    };

    if (!window.__eggStocksListenersRegistered) {
        window.__eggStocksListenersRegistered = true;
        document.addEventListener('turbo:load', function() { window.__bindEggStocks(); });
        document.addEventListener('turbo:frame-load', function(e) {
            if (e.target && e.target.id === 'egg-content') window.__bindEggStocks();
        });
    }
}

if (!window.__eggStocksEscapeBound) {
    window.__eggStocksEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditStockModal();
            closeEggWeightsModal();
            closeThresholdsModal();
        }
    });
}
</script>
</turbo-frame>
</div>
@if(isset($editBatch) && $editBatch)
<x-modal-reopen modal-id="editStockModal" session-key="reopen_edit_stock" guard="editStock">
    openEditStock(
        {{ $editBatch->id }},
        '{{ $editBatch->egg_size }}',
        {{ $editBatch->count }},
        '{{ $editBatch->harvested_date->format('Y-m-d') }}'
    );
</x-modal-reopen>
@endif
@endsection

