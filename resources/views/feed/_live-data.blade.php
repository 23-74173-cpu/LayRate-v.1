@php use App\Models\Alert; @endphp
<turbo-frame id="feed-live-data">
    <span hidden data-feed-batch-count="{{ $allBatches->count() }}"></span>
    {{-- ── Metric Cards — dashboard gradient KPI design (cardGradient) ── --}}
    @if($feedAsOf ?? null)
    <p class="text-xs font-medium mb-3" style="color:#9ca3af;">Showing last logged week · as of {{ $feedAsOf }}</p>
    @endif
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <x-kpi-card
            label="Avg CP% This Week" infoKey="feed.avg-cp"
            icon="flask-conical"
            cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
            delay="0ms"
            :value="number_format($avgCp, 1) . '%'"
        />
        <x-kpi-card
            label="Avg Feed/Cage/Day" infoKey="feed.avg-per-cage-day"
            icon="scale"
            cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
            delay="60ms"
            :value="$avgFeedPerCage . ' kg'"
        />
        <x-kpi-card
            label="Total Feed Used" infoKey="feed.total-week"
            icon="package"
            cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
            delay="120ms"
            :value="number_format($totalFeedWeek, 1) . ' kg'"
        />
        <x-kpi-card
            label="Feed Cost This Month" infoKey="feed.cost-month"
            icon="banknote"
            cardGradient="linear-gradient(135deg,#16a34a,#15803d)"
            delay="180ms"
            :value="$totalFeedCostMonth !== null && $totalFeedCostMonth > 0 ? '₱' . number_format($totalFeedCostMonth, 2) : null"
        />
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
    (function() {
        function feedSwitchTab(tab) {
            if (window.__feedActiveTab === tab) return;
            window.__feedActiveTab = tab;

            document.querySelectorAll('.tab-panel').forEach(el => el.classList.add('hidden'));
            var panel = document.getElementById('tab-'+tab);
            if (panel) panel.classList.remove('hidden');

            // Keep the URL's ?tab= in sync so a form submit's redirect back
            // to this page (e.g. Add Consumption) returns to the same tab
            // instead of always bouncing to Feed Batches.
            if (!window.__feedPageBooting) {
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                if (tab !== 'consumption') { url.searchParams.delete('view'); url.searchParams.delete('month'); }
                else if (!url.searchParams.has('view')) url.searchParams.set('view', 'records');
                window.history.replaceState({}, '', url);
            }
            const nav = document.querySelector('#feed-tabs-nav');
            if (nav) {
                nav.querySelectorAll('button').forEach(btn => {
                    btn.classList.remove('border-navy', 'text-navy');
                    btn.classList.add('border-transparent', 'text-[#6B7280]');
                });
                const active = nav.querySelector('button[onclick*="'+tab+'"]');
                if (active) {
                    active.classList.remove('border-transparent', 'text-[#6B7280]');
                    active.classList.add('border-navy', 'text-navy');
                }
            }
            // Auto-load FCR data when FCR tab is selected
            if (tab === 'fcr' && window.fcrLoad) {
                window.fcrLoad();
            }
        }

        function setFeedView(view) {
            var url = new URL(window.location);
            url.searchParams.set('tab', 'consumption');
            url.searchParams.set('view', view);
            if (view === 'calendar') {
                var month = url.searchParams.get('month');
                if (!month) {
                    var today = new Date();
                    month = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
                }
                url.searchParams.set('month', month);
            } else {
                url.searchParams.delete('month');
            }
            window.history.pushState({}, '', url);
            if (window.feedReloadLiveData) window.feedReloadLiveData();
        }
        window.setFeedView = setFeedView;

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
                        ? 'bg-navy text-white border-navy'
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

        window.feedSwitchTab = feedSwitchTab;
        window.fcrLoad = fcrLoad;
        window.openFcrGuideModal = openFcrGuideModal;
        window.closeFcrGuideModal = closeFcrGuideModal;

        if (!window.__fcrGuideEscapeBound) {
            window.__fcrGuideEscapeBound = true;
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeFcrGuideModal();
            });
        }

        const tab = '{{ request()->query("tab", "batches") }}';
        if (document.getElementById('tab-' + tab)) feedSwitchTab(tab);
    })();
    </script>

    {{-- Feed Batches Panel --}}
    <div id="tab-batches" class="tab-panel">
        <turbo-frame id="tab-batches-frame">
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
        <div class="rounded-xl border overflow-x-auto" style="background-color: #ffffff; border-color: #e6e6e6;">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Code</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Brand</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Received</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">CP%</th>
                        <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Received Qty (kg)</th>
                        <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Total Cost</th>
                        <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium">Remaining Stock (kg)</th>
                        <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">Notes</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                    @php
                        $isLow = $batch->is_low_stock;
                    @endphp
                    <tr data-feed-batch-id="{{ $batch->id }}" class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                        <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ $batch->batch_code }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $batch->brand ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $batch->date_received->format('m/d/Y') }}</td>
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
                                <x-icon-button icon="pencil" label="Edit batch" color="neutral"
                                    onclick="openEditBatch({{ $batch->id }}, '{{ addslashes($batch->brand ?? '') }}', {{ $batch->crude_protein }}, {{ $batch->total_quantity_kg ?? 'null' }}, {{ $batch->unit_cost ?? 'null' }}, {{ $batch->low_stock_threshold ?? 'null' }}, '{{ addslashes($batch->notes ?? '') }}')" />
                                @can('admin')
                                <button onclick="deleteBatch({{ $batch->id }})"
                                        class="flex items-center justify-center gap-1 text-xs border border-[#D9D9D9] px-2.5 py-1.5 rounded hover:bg-red-50 text-[#6B7280] max-md:min-w-11 max-md:min-h-11 max-md:px-0"
                                        aria-label="Delete batch">
                                    <i data-lucide="trash-2" class="w-3 h-3" style="color: #9b1c24;"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-5 py-10 text-center text-sm text-[#6B7280]">No feed batches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-paginator :paginator="$batches" />
        </div>
    </turbo-frame>
    </div>

    {{-- Daily Consumption Panel --}}
        <div id="tab-consumption" class="tab-panel hidden">
        <div class="feed-consumption-card w-full min-w-0 rounded-lg border border-[#D9D9D9] bg-white">
            <header class="feed-consumption-header flex min-w-0 items-center justify-between gap-3 border-b border-[#D9D9D9] px-4 py-3 sm:px-5">
                <h3 class="card-title min-w-0 truncate">Daily Consumption</h3>
                <div class="recent-view-toggle feed-view-toggle inline-flex h-8 shrink-0 items-center rounded-full border border-[#e6e6e6] bg-[#f6f5f4] p-0.5" role="group" aria-label="Consumption log view">
                    <button type="button" @if(($viewMode ?? 'records') === 'records') aria-current="page" @endif class="inline-flex h-full min-w-0 items-center justify-center rounded-full px-3 text-[13px] font-medium leading-none transition-colors focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy {{ ($viewMode ?? 'records') === 'records' ? 'bg-white text-[#1f1f1f] shadow-sm' : 'text-[#615d59] hover:bg-white/70' }}" onclick="setFeedView('records')">Records</button>
                    <button type="button" @if(($viewMode ?? 'records') === 'calendar') aria-current="page" @endif class="inline-flex h-full min-w-0 items-center justify-center rounded-full px-3 text-[13px] font-medium leading-none transition-colors focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy {{ ($viewMode ?? 'records') === 'calendar' ? 'bg-white text-[#1f1f1f] shadow-sm' : 'text-[#615d59] hover:bg-white/70' }}" onclick="setFeedView('calendar')">Calendar</button>
                </div>
            </header>
            <div class="feed-consumption-content">
            @if(($viewMode ?? 'records') === 'calendar')
                @include('feed._consumption-calendar', compact('month', 'today', 'calendarDays', 'calendarTotals', 'cages', 'maxMonth', 'preselectedCageId'))
            @else
                <turbo-frame id="tab-consumption-frame">
                    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-x-auto">
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
                                    <tr data-feed-log-id="{{ $log->id }}" data-feed-farm-id="{{ $log->farm_feed_entry_id }}" class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                                        <td class="px-5 py-3.5 text-sm font-mono text-[#333333]">{{ $log->log_date->format('m/d/Y') }}</td>
                                        <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $log->log_time?->format('H:i') ?? '—' }}</td>
                                        <td class="px-5 py-3.5 text-sm font-medium" style="color:{{ $cColor }}">{{ $log->cage?->cage_code ?? '—' }}</td>
                                        <td class="px-5 py-3.5 text-sm text-[#333333]">{{ $log->feedBatch->batch_code }}</td>
                                        <td class="px-5 py-3.5 text-sm text-[#333333]">{{ number_format($log->feed_consumed_kg, 2) }} kg</td>
                                        <td class="px-5 py-3.5">
                                            @if($isDistributed)
                                                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-warning-bg text-warning border border-warning-border" title="Estimated from whole-farm entry"><i data-lucide="git-branch" class="w-3 h-3"></i> Estimated</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-success-bg text-success border border-success-border"><i data-lucide="check-circle-2" class="w-3 h-3"></i> Direct</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-1">
                                                @if($isDistributed)
                                                    <x-icon-button icon="pencil" label="Edit whole-farm entry" color="neutral" onclick="openFarmEntryModal({{ $log->farm_feed_entry_id }}, {{ $log->feed_batch_id }}, '{{ $log->log_date->format('Y-m-d') }}', '{{ $log->log_time?->format('H:i') ?? '' }}', {{ $log->farmFeedEntry?->total_kg ?? 'null' }})" />
                                                @else
                                                    <x-icon-button icon="pencil" label="Edit consumption" color="neutral" onclick="openConsumptionModal({{ $log->cage_id }}, {{ $log->feed_batch_id }}, '{{ $log->log_date->format('Y-m-d') }}', '{{ $log->log_time?->format('H:i') ?? '' }}', {{ $log->feed_consumed_kg }}, {{ $log->id }})" />
                                                    @can('admin')
                                                        <x-icon-button icon="trash-2" label="Delete consumption log" color="red" onclick="confirmModal('Delete this consumption record?', { submit: function() { deleteConsumption({{ $log->id }}); } }, 'Delete', 'destructive')" />
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-[#6B7280]">No consumption data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <x-paginator :paginator="$consumptionLogs" />
                    </div>
                </turbo-frame>
            @endif
            </div>
        </div>
        @if(($viewMode ?? 'records') === 'calendar')
            <div id="feedCalendarDayDialog" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="feedCalendarDayTitle" aria-hidden="true" inert>
                <button type="button" class="absolute inset-0 h-full w-full bg-black/40" data-feed-calendar-close aria-label="Close day details"></button>
                <section class="absolute inset-x-0 bottom-0 max-h-[88dvh] overflow-y-auto rounded-t-2xl bg-white p-5 pb-24 shadow-2xl md:inset-y-0 md:left-auto md:right-0 md:max-h-none md:w-[min(620px,90vw)] md:rounded-none md:pb-5" tabindex="-1">
                    <div class="mb-4 flex items-center justify-between gap-3"><h2 id="feedCalendarDayTitle" class="text-lg font-semibold">Feed details</h2><button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-black/5" data-feed-calendar-close aria-label="Close day details">×</button></div>
                    <div id="feedCalendarDayContent" aria-live="polite"><p class="py-8 text-center text-sm text-[#615d59]">Select a day to view feedings.</p></div>
                </section>
            </div>
            <script>
            window.openFeedCalendarDay = function (button) {
                var dialog = document.getElementById('feedCalendarDayDialog');
                var content = document.getElementById('feedCalendarDayContent');
                if (!dialog || !content) return;
                window.__feedCalendarDayOpener = button;
                content.innerHTML = '<p class="py-8 text-center text-sm text-[#615d59]">Loading feed details…</p>';
                dialog.classList.remove('hidden');
                dialog.inert = false;
                dialog.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
                dialog.querySelector('section').focus();
                var url = new URL(button.dataset.detailUrl, window.location.origin);
                var pageUrl = new URL(window.location.href);
                ['cage_id', 'walkthrough4', 'wt4_log', 'wt4_farm'].forEach(function (key) {
                    if (pageUrl.searchParams.has(key)) url.searchParams.set(key, pageUrl.searchParams.get(key));
                });
                fetch(url.toString(), {headers: {'X-Requested-With':'XMLHttpRequest'}})
                    .then(function (response) { if (!response.ok) throw new Error('Unable to load this day.'); return response.text(); })
                    .then(function (html) { content.innerHTML = html; if (window.lucide) lucide.createIcons(); })
                    .catch(function () { content.innerHTML = '<p class="py-8 text-center text-sm text-red-700" role="alert">Could not load these feed records. Close this panel and try again.</p>'; });
            };
            window.closeFeedCalendarForEdit = function () {
                var dialog = document.getElementById('feedCalendarDayDialog');
                if (!dialog) return;
                dialog.classList.add('hidden');
                dialog.inert = true;
                dialog.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                if (window.__feedCalendarDayOpener?.isConnected) window.__feedCalendarDayOpener.focus();
            };
            (function () {
                var dialog = document.getElementById('feedCalendarDayDialog');
                if (!dialog || window.__feedCalendarDayDialogBound) return;
                window.__feedCalendarDayDialogBound = true;
                function close(restoreFocus) {
                    dialog.classList.add('hidden');
                    dialog.setAttribute('aria-hidden', 'true');
                    dialog.inert = true;
                    document.body.classList.remove('overflow-hidden');
                    if (restoreFocus && window.__feedCalendarDayOpener?.isConnected) window.__feedCalendarDayOpener.focus();
                }
                dialog.addEventListener('click', function (event) { if (event.target.closest('[data-feed-calendar-close]')) close(true); });
                document.addEventListener('keydown', function (event) {
                    if (dialog.classList.contains('hidden')) return;
                    if (event.key === 'Escape') { event.preventDefault(); close(true); return; }
                    if (event.key !== 'Tab') return;
                    var focusables = Array.from(dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(function (el) { return el.offsetParent !== null; });
                    if (!focusables.length) { event.preventDefault(); dialog.querySelector('section').focus(); return; }
                    var first = focusables[0], last = focusables[focusables.length - 1];
                    if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                });
            })();
            </script>
        @endif

    {{-- FCR Panel --}}
    <div id="tab-fcr" class="tab-panel hidden"
         data-fcr-cage-id="{{ $fcrSelectedId ?? $cages->value('id') ?? '' }}"
         data-fcr-group-by="{{ $fcrGroupBy }}">
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5 relative">
            <x-card-header title="Feed Conversion Ratio" subtitle="kg feed ÷ kg egg mass (lower is better)" icon="scale">
                <x-slot:actions>
                    <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3">
                    <select id="fcr-cage-select" onchange="fcrLoad()"
                            class="w-full sm:w-auto min-w-0 border border-[#D9D9D9] rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-navy">
                        <option value="all">All Cages</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}" {{ (int) $fcrSelectedId === $c->id ? 'selected' : '' }}>
                            {{ $c->cage_code }}
                        </option>
                        @endforeach
                    </select>

                    <div class="flex flex-wrap items-center gap-1">
                        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)
                        <button type="button"
                                data-group-by="{{ $value }}"
                                onclick="fcrLoad('{{ $value }}')"
                                class="text-xs px-3 py-1.5 rounded-full border transition-colors {{ $fcrGroupBy === $value ? 'bg-navy text-white border-navy' : 'border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8]' }}">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </div>
                </x-slot:actions>
            </x-card-header>

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
                <x-button variant="secondary" size="sm" type="button" onclick="fcrLoad()">
                    Retry
                </x-button>
            </div>

            {{-- Content (initially server-rendered) --}}
            <div id="fcr-content">
                @include('feed._fcr-content', [
                    'fcrCurrent'    => $fcrCurrent,
                    'fcrTimeline'   => $fcrTimeline,
                    'fcrGroupBy'    => $fcrGroupBy,
                    'fcrCageLabel'  => $fcrCageLabel,
                    'fcrSelectedId' => $fcrSelectedId,
                    'fcrTotalFeedKg'    => $fcrTotalFeedKg,
                    'fcrTotalEggMassKg' => $fcrTotalEggMassKg,
                ])
            </div>

            {{-- FCR Guide Modal --}}
            <div id="fcr-guide-modal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeFcrGuideModal()"></div>
                <div class="relative w-full max-w-md rounded-2xl p-6 overflow-y-auto max-h-[90vh]" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Understanding FCR</h2>
                        <x-icon-button icon="x" label="Close" color="neutral" iconSize="w-5 h-5" onclick="closeFcrGuideModal()" />
                    </div>
                    <div class="text-sm leading-relaxed" style="color: #4B5563;">
                        <p class="mb-4">
                            <strong>Feed Conversion Ratio</strong> = kg of feed consumed ÷ kg of egg mass produced.
                            It measures how efficiently your flock converts feed into eggs.
                        </p>
                        <div class="space-y-2.5 mb-4">
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-success flex-shrink-0"></span>
                                <span><strong>&le; {{ config('fcr.good_threshold', 2.5) }}</strong> — Efficient conversion</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-warning flex-shrink-0"></span>
                                <span><strong>{{ config('fcr.good_threshold', 2.5) }} – {{ config('fcr.warning_threshold', 4.0) }}</strong> — Monitor closely</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-danger flex-shrink-0"></span>
                                <span><strong>&gt; {{ config('fcr.warning_threshold', 4.0) }}</strong> — Investigate feed or flock health</span>
                            </div>
                        </div>
                        <p class="text-xs text-[#9CA3AF] border-t border-[#D9D9D9] pt-3">
                            Pre-lay pullets, molting flocks, or very young hens may show high FCR
                            due to low egg mass rather than poor feed efficiency. Adjust thresholds
                             in the egg weight configuration in the egg stocks section to match your flock profile.
                        </p>
                    </div>
                    <x-button type="button" onclick="closeFcrGuideModal()" class="w-full py-2.5 mt-5">
                        Got it
                    </x-button>
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

            var cages = {!! json_encode($cages->map(fn($c) => ['id' => $c->id, 'code' => $c->cage_code])) !!};
            // remaining_kg is null for batches with no total_quantity_kg set (unlimited/untracked) —
            // the exceeds-check below treats null as "no limit to check against".
            var batches = {!! json_encode($allBatches->map(fn($b) => ['id' => $b->id, 'code' => $b->batch_code, 'remaining' => $b->remaining_kg, 'unit_cost' => $b->unit_cost])) !!};

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
                    opt.dataset.remaining = b.remaining === null ? '' : b.remaining;
                    opt.dataset.unitCost = b.unit_cost === null ? '' : b.unit_cost;
                    batchSelect.appendChild(opt);
                });
            }

            if (farmBatchSelect && farmBatchSelect.options.length <= 1) {
                batches.forEach(function(b) {
                    var opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.code;
                    opt.dataset.remaining = b.remaining === null ? '' : b.remaining;
                    farmBatchSelect.appendChild(opt);
                });
            }

            // Live "exceeds remaining stock" check, mirroring FeedController's
            // server-side validation, but visible before the user submits.
            function wireExceedsCheck(selectEl, kgInputEl, warningEl, saveBtnEl) {
                if (!selectEl || !kgInputEl || !warningEl || !saveBtnEl) return;

                function check() {
                    const opt = selectEl.options[selectEl.selectedIndex];
                    const remaining = opt && opt.dataset.remaining !== '' ? parseFloat(opt.dataset.remaining) : null;
                    const entered = parseFloat(kgInputEl.value);

                    if (remaining !== null && !isNaN(entered) && entered > remaining) {
                        warningEl.textContent = `Only ${remaining.toFixed(1)} kg remaining in this batch — cannot log ${entered} kg.`;
                        warningEl.classList.remove('hidden');
                        saveBtnEl.disabled = true;
                        saveBtnEl.classList.add('opacity-50', 'cursor-not-allowed');
                    } else {
                        warningEl.classList.add('hidden');
                        saveBtnEl.disabled = false;
                        saveBtnEl.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                }

                selectEl.addEventListener('change', check);
                kgInputEl.addEventListener('input', check);
            }

            wireExceedsCheck(
                batchSelect,
                document.getElementById('consumptionKgInput'),
                document.getElementById('consumptionExceedsWarning'),
                document.getElementById('consumptionSaveBtn')
            );
            wireExceedsCheck(
                farmBatchSelect,
                document.getElementById('farmKgInput'),
                document.getElementById('farmExceedsWarning'),
                document.getElementById('farmSaveBtn')
            );

        // Boot: activate tab from URL, and load FCR if FCR tab is active
        const activeTab = '{{ request()->query("tab", "batches") }}';
        if (document.getElementById('tab-' + activeTab)) {
            window.__feedPageBooting = true;
            feedSwitchTab(activeTab);
            window.__feedPageBooting = false;
        }
        })();
    </script>

</turbo-frame>
