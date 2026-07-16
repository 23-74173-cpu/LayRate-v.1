@php use App\Models\Alert; @endphp
<turbo-frame id="feed-live-data">
    {{-- ── Metric Cards (now 4) ── --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">AVG CP% THIS WEEK</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ number_format($avgCp, 1) }}%</div>
            <div class="text-xs text-[#6B7280] mt-1">within target</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">AVG FEED/CAGE/DAY</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ $avgFeedPerCage }} <span class="text-xl">kg</span></div>
            <div class="text-xs text-[#6B7280] mt-1">rolling 7 days</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">TOTAL FEED USED</div>
            <div class="text-3xl tracking-tight text-[#333333]">{{ number_format($totalFeedWeek, 1) }} <span class="text-xl">kg</span></div>
            <div class="text-xs text-[#6B7280] mt-1">last 7 days</div>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-4">
            <div class="text-xs tracking-wider text-[#6B7280] mb-2">FEED COST THIS MONTH</div>
            <div class="text-3xl tracking-tight text-[#333333]">
                @if($totalFeedCostMonth !== null && $totalFeedCostMonth > 0)
                    ₱{{ number_format($totalFeedCostMonth, 2) }}
                @else
                    <span class="text-lg text-[#9CA3AF]">—</span>
                @endif
            </div>
            <div class="text-xs text-[#6B7280] mt-1">from batches with unit cost</div>
        </div>
    </div>

    {{-- ── Tabs ── --}}
    <div id="feed-tabs-nav" class="mb-5">
        <x-underline-tabs :tabs="[
            'batches'     => ['label' => 'Feed Batches',     'icon' => 'folder',    'onclick' => 'feedSwitchTab(\'batches\')'],
            'consumption' => ['label' => 'Daily Consumption','icon' => 'utensils', 'onclick' => 'feedSwitchTab(\'consumption\')'],
            'fcr'         => ['label' => 'FCR',              'icon' => 'scale',     'onclick' => 'feedSwitchTab(\'fcr\')'],
        ]" active="{{ request()->get('tab', 'batches') }}" />
    </div>

    <script>
        function feedSwitchTab(tab) {
            document.querySelectorAll('.tab-panel').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-'+tab).classList.remove('hidden');

            const nav = document.querySelector('#feed-tabs-nav');
            if (nav) {
                nav.querySelectorAll('button').forEach(btn => {
                    btn.classList.remove('border-[#002D5E]', 'text-[#002D5E]');
                    btn.classList.add('border-transparent', 'text-[#6B7280]');
                });
                const active = nav.querySelector('button[onclick*="'+tab+'"]');
                if (active) {
                    active.classList.remove('border-transparent', 'text-[#6B7280]');
                    active.classList.add('border-[#002D5E]', 'text-[#002D5E]');
                }
            }
        }

        /* ── FCR AJAX with AbortController ── */
        let fcrAbortController = null;

        function fcrLoad(groupBy) {
            var panel = document.getElementById('tab-fcr');
            if (!panel) return;

            /* Cancel any in-flight request before starting a new one */
            if (fcrAbortController) {
                fcrAbortController.abort();
            }
            fcrAbortController = new AbortController();

            /* Read current selections */
            var cageSelect = document.getElementById('fcr-cage-select');
            var cageId = cageSelect ? cageSelect.value : 'all';

            /* Update group-by if provided, otherwise keep current */
            if (!groupBy) {
                groupBy = panel.getAttribute('data-fcr-group-by') || 'week';
            }
            panel.setAttribute('data-fcr-group-by', groupBy);

            /* Update active button style */
            panel.querySelectorAll('[data-group-by]').forEach(function(btn) {
                var isActive = btn.getAttribute('data-group-by') === groupBy;
                btn.className = 'text-xs px-3 py-1.5 rounded-full border transition-colors ' +
                    (isActive
                        ? 'bg-[#002D5E] text-white border-[#002D5E]'
                        : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]');
            });

            /* Show loading, hide error + content */
            var loadingEl = document.getElementById('fcr-loading');
            var errorEl   = document.getElementById('fcr-error');
            var contentEl = document.getElementById('fcr-content');
            if (loadingEl) loadingEl.classList.remove('hidden');
            if (errorEl)   errorEl.classList.add('hidden');
            if (contentEl) contentEl.classList.add('hidden');

            var url = '/feed/fcr-data?cage_id=' + encodeURIComponent(cageId) + '&group_by=' + encodeURIComponent(groupBy);

            fetch(url, { signal: fcrAbortController.signal })
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.text();
                })
                .then(function(html) {
                    if (!contentEl) return;
                    contentEl.innerHTML = html;
                    panel.setAttribute('data-fcr-cage-id', cageId);
                    if (loadingEl) loadingEl.classList.add('hidden');
                    contentEl.classList.remove('hidden');
                    /* Re-run lucide icon replacement on new content */
                    if (typeof lucide !== 'undefined' && lucide.createIcons) {
                        lucide.createIcons({ els: contentEl.querySelectorAll('[data-lucide]') });
                    }
                })
                .catch(function(err) {
                    if (err.name === 'AbortError') return;
                    if (loadingEl) loadingEl.classList.add('hidden');
                    if (errorEl)   errorEl.classList.remove('hidden');
                    if (contentEl) contentEl.classList.add('hidden');
                });
        }

        /* ── FCR Interpretation Guide Modal ── */
        function openFcrGuideModal() {
            var modal = document.getElementById('fcr-guide-modal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeFcrGuideModal() {
            var modal = document.getElementById('fcr-guide-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
            }
        }

        (function() {
            if (window.__fcrGuideEscapeBound) return;
            window.__fcrGuideEscapeBound = true;
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeFcrGuideModal();
            });
        })();

        (function() {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || 'batches';
            if (document.getElementById('tab-' + tab)) {
                feedSwitchTab(tab);
            }
        })();
    </script>

    {{-- Feed Batches Panel --}}
    <div id="tab-batches" class="tab-panel">
        <div class="flex items-center gap-4 text-xs mb-3" style="color: #615d59;">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background:#1f6b3a"></span> Optimal (16–18%)
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background:#8a5a00"></span> Watch (&lt;16% or &gt;18%)
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" style="background:#9b1c24"></span> Critical
            </span>
        </div>
        <div class="rounded-xl border overflow-hidden" style="background-color: #ffffff; border-color: #e6e6e6;">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Code</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Brand</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Received</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">CP%</th>
                        <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Qty (kg)</th>
                        <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Total Cost</th>
                        <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Remaining</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Notes</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                    @php
                        $isLow = $batch->is_low_stock;
                    @endphp
                    <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8] {{ $isLow ? 'bg-red-50' : '' }}">
                        <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ $batch->batch_code }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $batch->brand ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $batch->date_received->format('Y-m-d') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs px-2.5 py-1 rounded-full" style="background:{{ $batch->cpColor }};color:{{ $batch->cpText }}">
                                {{ number_format($batch->crude_protein, 1) }}%
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-right text-[#333333]">{{ $batch->total_quantity_kg !== null ? number_format($batch->total_quantity_kg, 1) : '—' }}</td>
                        <td class="px-5 py-3.5 text-sm text-right text-[#333333]">
                            @if($batch->total_cost !== null)
                                ₱{{ number_format($batch->total_cost, 2) }}
                            @else
                                <span class="text-[#9CA3AF]">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-sm text-right">
                            @if($batch->remaining_kg !== null)
                                <span class="{{ $isLow ? 'text-[#9B1C24] font-semibold' : 'text-[#333333]' }}">
                                    {{ number_format($batch->remaining_kg, 1) }}
                                </span>
                                @if($isLow)
                                    <span class="inline-block w-2 h-2 rounded-full bg-[#9B1C24] ml-1" title="Low stock!"></span>
                                @endif
                            @else
                                <span class="text-[#9CA3AF]">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-sm text-[#6B7280] max-w-[160px] truncate">{{ $batch->notes ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-1.5">
                                <button onclick="openEditBatch({{ $batch->id }}, '{{ addslashes($batch->brand ?? '') }}', {{ $batch->crude_protein }}, {{ $batch->total_quantity_kg ?? 'null' }}, {{ $batch->unit_cost ?? 'null' }}, {{ $batch->low_stock_threshold ?? 'null' }}, '{{ addslashes($batch->notes ?? '') }}')"
                                        class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit batch">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </button>
                                <button onclick="deleteBatch({{ $batch->id }})"
                                        class="flex items-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-red-50 text-[#6B7280]"
                                        aria-label="Delete batch">
                                    <i data-lucide="trash-2" class="w-3 h-3" style="color: #9b1c24;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-5 py-8 text-center text-sm text-[#6B7280]">No feed batches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Daily Consumption Panel --}}
    <div id="tab-consumption" class="tab-panel hidden">
        <div class="flex items-center justify-end gap-2 mb-3">
            <button onclick="openFarmEntryModal(null, null, '{{ now()->toDateString() }}', null, null, null)"
                    class="flex items-center gap-1.5 text-xs border border-[#D9D9D9] px-3 py-1.5 rounded-lg hover:bg-[#F5F6F8] transition-colors text-[#6B7280]">
                <i data-lucide="scale" class="w-3.5 h-3.5"></i> Log Whole-Farm Feeding
            </button>
            <button onclick="openConsumptionModal(null, null, '{{ now()->toDateString() }}', null, null, null)"
                    class="flex items-center gap-1.5 text-xs border border-[#D9D9D9] px-3 py-1.5 rounded-lg hover:bg-[#F5F6F8] transition-colors text-[#6B7280]">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Consumption
            </button>
        </div>
        <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Date</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Time</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Cage</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Batch</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Consumed (kg)</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Source</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consumptionLogs as $log)
                    @php
                        $cColor = $log->cage?->color ?? '#6B7280';
                        $isDistributed = $log->source === 'distributed';
                    @endphp
                    <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8] {{ $isDistributed ? 'bg-amber-50/50' : '' }}">
                        <td class="px-5 py-3 text-sm font-mono text-[#333333]">{{ $log->log_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3 text-sm text-[#6B7280]">{{ $log->log_time?->format('H:i') ?? '—' }}</td>
                        <td class="px-5 py-3 text-sm font-medium" style="color:{{ $cColor }}">{{ $log->cage?->cage_code ?? '—' }}</td>
                        <td class="px-5 py-3 text-sm text-[#333333]">{{ $log->feedBatch->batch_code }}</td>
                        <td class="px-5 py-3 text-sm text-[#333333]">{{ number_format($log->feed_consumed_kg, 2) }} kg</td>
                        <td class="px-5 py-3">
                            @if($isDistributed)
                                <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200" title="Estimated from whole-farm entry">
                                    <i data-lucide="git-branch" class="w-3 h-3"></i> Estimated
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-100">
                                    <i data-lucide="check-circle-2" class="w-3 h-3"></i> Direct
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1">
                                @if($isDistributed)
                                    <button onclick="openFarmEntryModal({{ $log->farm_feed_entry_id }}, {{ $log->feed_batch_id }}, '{{ $log->log_date->format('Y-m-d') }}', '{{ $log->log_time?->format('H:i') ?? '' }}', {{ $log->farmFeedEntry?->total_kg ?? 'null' }}, {{ $log->farmFeedEntry?->unit_cost ?? 'null' }})"
                                            class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit whole-farm entry">
                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    </button>
                                @else
                                    <button onclick="openConsumptionModal({{ $log->cage_id }}, {{ $log->feed_batch_id }}, '{{ $log->log_date->format('Y-m-d') }}', '{{ $log->log_time?->format('H:i') ?? '' }}', {{ $log->feed_consumed_kg }}, {{ $log->id }})"
                                            class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit consumption">
                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <form method="POST" action="{{ route('feed.consumption.destroy', $log) }}"
                                          data-confirm="Delete this consumption record?" data-confirm-action="Delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete consumption log">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-[#6B7280]">No consumption data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($consumptionLogs->hasPages())
            <div class="px-5 py-3 border-t border-[#D9D9D9] flex items-center justify-between text-xs text-[#6B7280]">
                <span>Showing {{ $consumptionLogs->firstItem() }}-{{ $consumptionLogs->lastItem() }} of {{ $consumptionLogs->total() }}</span>
                <div class="flex items-center gap-1">
                    @if($consumptionLogs->onFirstPage())
                    <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                    @else
                    <a href="{{ $consumptionLogs->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                    @endif
                    @foreach($consumptionLogs->getUrlRange(1, $consumptionLogs->lastPage()) as $page => $url)
                        @if($page == $consumptionLogs->currentPage())
                        <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                        @elseif($page >= $consumptionLogs->currentPage() - 1 && $page <= $consumptionLogs->currentPage() + 1)
                        <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($consumptionLogs->hasMorePages())
                    <a href="{{ $consumptionLogs->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                    @else
                    <span class="px-2 py-1 text-[#9CA3AF]">Next ›</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- FCR Panel --}}
    <div id="tab-fcr" class="tab-panel hidden"
         data-fcr-cage-id="{{ $fcrSelectedId ?? $cages->value('id') ?? '' }}"
         data-fcr-group-by="{{ $fcrGroupBy }}">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 relative">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <div>
                    <h3 class="text-sm font-semibold text-[#333333]">Feed Conversion Ratio</h3>
                    <p class="text-xs text-[#6B7280]">kg feed ÷ kg egg mass (lower is better)</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <select id="fcr-cage-select" onchange="fcrLoad()"
                            class="border border-[#D9D9D9] rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-[#002D5E]">
                        <option value="all">All Cages</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}" {{ (int) $fcrSelectedId === $c->id ? 'selected' : '' }}>
                            {{ $c->cage_code }}
                        </option>
                        @endforeach
                    </select>

                    <div class="flex items-center gap-1">
                        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)
                        <button type="button"
                                data-group-by="{{ $value }}"
                                onclick="fcrLoad('{{ $value }}')"
                                class="text-xs px-3 py-1.5 rounded-full border transition-colors {{ $fcrGroupBy === $value ? 'bg-[#002D5E] text-white border-[#002D5E]' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Loading skeleton --}}
            <div id="fcr-loading" class="hidden">
                <div class="animate-pulse space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-100 rounded-lg h-20"></div>
                        <div class="bg-gray-100 rounded-lg h-20"></div>
                        <div class="bg-gray-100 rounded-lg h-20"></div>
                    </div>
                    <div class="bg-gray-100 rounded-lg h-48"></div>
                </div>
            </div>

            {{-- Error state --}}
            <div id="fcr-error" class="hidden text-center py-8">
                <p class="text-sm text-[#9B1C24] mb-3">
                    <i data-lucide="alert-triangle" class="inline-block w-4 h-4 align-text-bottom"></i>
                    Failed to load FCR data.
                </p>
                <button type="button" onclick="fcrLoad()"
                        class="text-xs px-4 py-2 rounded-lg border border-[#D9D9D9] text-[#333333] hover:bg-[#F5F6F8] transition-colors">
                    Retry
                </button>
            </div>

            {{-- Content (initially server-rendered) --}}
            <div id="fcr-content">
                @include('feed._fcr-content', [
                    'fcrCurrent'    => $fcrCurrent,
                    'fcrTimeline'   => $fcrTimeline,
                    'fcrGroupBy'    => $fcrGroupBy,
                    'fcrCageLabel'  => $fcrCageLabel,
                    'fcrSelectedId' => $fcrSelectedId,
                ])
            </div>

            {{-- FCR Guide Modal --}}
            <div id="fcr-guide-modal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeFcrGuideModal()"></div>
                <div class="relative w-full max-w-md rounded-2xl p-6 overflow-y-auto max-h-[90vh]" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Understanding FCR</h2>
                        <button type="button" onclick="closeFcrGuideModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                            <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                        </button>
                    </div>
                    <div class="text-sm leading-relaxed" style="color: #4B5563;">
                        <p class="mb-4">
                            <strong>Feed Conversion Ratio</strong> = kg of feed consumed ÷ kg of egg mass produced.
                            It measures how efficiently your flock converts feed into eggs.
                        </p>
                        <div class="space-y-2.5 mb-4">
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500 flex-shrink-0"></span>
                                <span><strong>&le; {{ config('fcr.good_threshold', 2.5) }}</strong> — Efficient conversion</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-yellow-500 flex-shrink-0"></span>
                                <span><strong>{{ config('fcr.good_threshold', 2.5) }} – {{ config('fcr.warning_threshold', 4.0) }}</strong> — Monitor closely</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0"></span>
                                <span><strong>&gt; {{ config('fcr.warning_threshold', 4.0) }}</strong> — Investigate feed or flock health</span>
                            </div>
                        </div>
                        <p class="text-xs text-[#9CA3AF] border-t border-[#D9D9D9] pt-3">
                            Pre-lay pullets, molting flocks, or very young hens may show high FCR
                            due to low egg mass rather than poor feed efficiency. Adjust thresholds
                             in the egg weight configuration in the egg stocks section to match your flock profile.
                        </p>
                    </div>
                    <button type="button" onclick="closeFcrGuideModal()"
                            class="w-full py-2.5 text-sm font-medium rounded-full text-white transition-opacity mt-5"
                            style="background-color: #0075de;"
                            onmouseover="this.style.opacity='0.85'"
                            onmouseout="this.style.opacity='1'">Got it</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Populate consumption and farm-entry modal selects --}}
    <script>
        (function() {
            var cageSelect = document.querySelector('#consumptionModal select[name="cage_id"]');
            var batchSelect = document.querySelector('#consumptionModal select[name="feed_batch_id"]');
            var farmBatchSelect = document.querySelector('#farmEntryModal select[name="feed_batch_id"]');

            var cages = @json($cages->map(fn($c) => ['id' => $c->id, 'code' => $c->cage_code]));
            var batches = @json($batches->map(fn($b) => ['id' => $b->id, 'code' => $b->batch_code]));

            if (cageSelect && cageSelect.options.length <= 1) {
                cages.forEach(function(c) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.code;
                    cageSelect.appendChild(opt);
                });
            }

            if (batchSelect && batchSelect.options.length <= 1) {
                batches.forEach(function(b) {
                    var opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.code;
                    batchSelect.appendChild(opt);
                });
            }

            if (farmBatchSelect && farmBatchSelect.options.length <= 1) {
                batches.forEach(function(b) {
                    var opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.code;
                    farmBatchSelect.appendChild(opt);
                });
            }
        })();
    </script>

    @if($preselectedCageId ?? null)
    <script>
        feedSwitchTab('consumption');
    </script>
    @endif
</turbo-frame>
