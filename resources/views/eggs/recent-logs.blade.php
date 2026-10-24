@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
{{-- Bottom clearance: the fixed quick-dock floats over the viewport's
     bottom-right, so trailing space keeps it off the last table row/paginator. --}}
<div class="space-y-5 recent-logs-page md:pb-16">

    <x-page-header title="Egg Management" subtitle="Review and manage egg production records" subtitle-id="egg-header-subtitle" />

    @include('eggs._tabs', ['activeTab' => 'recent-logs'])

    <turbo-frame id="egg-content">

        <x-card>
        <x-slot:header>
            <div class="flex items-center gap-3">
                @if($viewMode === 'calendar')
                    <h3 class="card-title">Production Calendar</h3>
                @else
                    <h3 class="card-title">Production Logs</h3>
                @endif
            </div>
        </x-slot:header>

        <form id="recentLogsForm" onsubmit="return false" class="mb-4">
            <div class="recent-filters grid grid-cols-2 gap-3 max-md:grid-cols-12 max-md:items-end md:flex md:flex-nowrap md:items-center md:gap-x-4 md:gap-y-3">
                <div class="recent-view-toggle inline-flex min-h-11 items-center rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] p-1" role="group" aria-label="Recent Logs view">
                    <a href="{{ route('eggs.recent-logs', request()->only(['cage_id', 'cage_slot_id', 'breed']) + ($filters['logged_via'] ? ['logged_via[]' => (array) $filters['logged_via']] : []) + ['view' => 'calendar', 'month' => $month]) }}" @if($viewMode === 'calendar') aria-current="page" @endif class="inline-flex min-h-10 flex-1 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy {{ $viewMode === 'calendar' ? 'bg-white text-[#1f1f1f] shadow-sm' : 'text-[#615d59] hover:bg-white/70' }}">Calendar</a>
                    <a href="{{ route('eggs.recent-logs', request()->only(['cage_id', 'cage_slot_id', 'breed']) + ($filters['logged_via'] ? ['logged_via[]' => (array) $filters['logged_via']] : []) + ['view' => 'list', 'month' => $month]) }}" @if($viewMode === 'list') aria-current="page" @endif class="inline-flex min-h-10 flex-1 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy {{ $viewMode === 'list' ? 'bg-white text-[#1f1f1f] shadow-sm' : 'text-[#615d59] hover:bg-white/70' }}">List</a>
                </div>
                @if($viewMode === 'calendar')
                    <input type="hidden" name="view" value="calendar">
                    <input type="hidden" name="month" value="{{ $month }}">
                @endif
                {{-- Cage filter --}}
                <div class="recent-filter-cage max-md:col-span-6 max-md:col-start-1 max-md:row-start-2">
                    <label class="block text-sm mb-1" style="color: #615d59;">Cage</label>
                    <select name="cage_id" onchange="recentLogsFilter()"
                            class="w-full min-w-0 max-md:min-h-11 md:w-auto border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">All Cages</option>
                        @foreach($cages as $c)
                        <option value="{{ $c->id }}" {{ $filters['cage_id'] == $c->id ? 'selected' : '' }}>
                            {{ $c->cage_code }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Slot filter --}}
                <div class="recent-filter-slot max-md:col-span-6 max-md:col-start-7 max-md:row-start-2">
                    <label class="block text-sm mb-1" style="color: #615d59;">Slot</label>
                    <select name="cage_slot_id" onchange="recentLogsFilter()"
                            class="w-full min-w-0 max-md:min-h-11 md:w-auto border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">All Slots</option>
                        @foreach($cageSlots as $slot)
                        <option value="{{ $slot->id }}" {{ $filters['cage_slot_id'] == $slot->id ? 'selected' : '' }}>
                            {{ $slot->cage->cage_code }} · R{{ $slot->row_number }}-C{{ $slot->column_number }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Breed filter --}}
                <div class="recent-filter-breed max-md:col-span-9 max-md:col-start-1 max-md:row-start-3">
                    <label class="block text-sm mb-1" style="color: #615d59;">Breed</label>
                    <select name="breed" onchange="recentLogsFilter()"
                            class="w-full min-w-0 max-md:min-h-11 md:w-auto border rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">All Breeds</option>
                        @foreach($breeds as $b)
                        <option value="{{ $b }}" {{ $filters['breed'] == $b ? 'selected' : '' }}>
                            {{ $b }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Manual vs Sensor filter (checkbox chips) --}}
                <div class="recent-filter-via max-md:col-span-12 max-md:col-start-1 max-md:row-start-4 md:col-span-1">
                    <label class="block text-sm mb-1" style="color: #615d59;">Logged Via</label>
                    <div class="flex flex-wrap items-center gap-2 md:min-h-10">
                        @foreach(['manual' => 'Manual', 'sensor' => 'Sensor', 'unknown' => 'Unknown'] as $value => $label)
                        <label class="recent-via-chip inline-flex max-md:min-h-11 items-center gap-1.5 text-sm cursor-pointer" style="color: #31302e;">
                            <input type="checkbox" name="logged_via[]" value="{{ $value }}" onchange="recentLogsFilter()"
                                   {{ in_array($value, (array) $filters['logged_via']) ? 'checked' : '' }}
                                   class="recent-via-input rounded border-gray-300 text-navy focus:ring-navy">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="recent-filter-reset max-md:col-span-3 max-md:col-start-10 max-md:row-start-3 max-md:justify-self-end md:col-span-1 flex items-center justify-end gap-2">
                    <a href="{{ route('eggs.recent-logs', ['view' => $viewMode, 'month' => $month] + request()->only(['cage_id', 'cage_slot_id', 'breed']) + ($filters['logged_via'] ? ['logged_via[]' => (array) $filters['logged_via']] : [])) }}"
                       class="max-md:inline-flex max-md:items-center max-md:justify-center max-md:min-h-11 px-3 py-1.5 text-xs max-md:text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        @if($viewMode === 'calendar')
            @include('egg-logging._recent-calendar', ['month' => $month, 'calendarDays' => $calendarDays, 'today' => $today, 'dayUrl' => url('/eggs/recent-logs/day'), 'maxMonth' => substr($today, 0, 7)])
        @else
            <turbo-frame id="egg-logs-list" src="{{ route('eggs.logging.logs', request()->only(['cage_id', 'cage_slot_id', 'breed', 'logged_via'])) }}" loading="lazy">
                @include('egg-logging._logs-skeleton')
            </turbo-frame>
        @endif
        </x-card>

        @if($viewMode === 'list')
            @include('egg-logging._edit-modal')
        @endif

        @if($viewMode === 'calendar')
        <div id="calendarDayDialog" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="calendarDayTitle" aria-hidden="true">
            <button type="button" class="absolute inset-0 h-full w-full bg-black/40" data-calendar-close aria-label="Close day details"></button>
            <section class="absolute inset-x-0 bottom-0 max-h-[88dvh] overflow-y-auto rounded-t-2xl bg-white p-5 pb-24 shadow-2xl md:inset-y-0 md:left-auto md:right-0 md:max-h-none md:w-[min(620px,90vw)] md:rounded-none md:pb-5" tabindex="-1">
                <div class="mb-4 flex items-center justify-between gap-3"><h2 id="calendarDayTitle" class="text-lg font-semibold">Production details</h2><button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-black/5" data-calendar-close aria-label="Close day details">×</button></div>
                <div id="calendarDayContent" aria-live="polite"><x-empty-state icon="egg" message="Loading production records…" /></div>
            </section>
        </div>
        @endif

<script>
(function () {
    if (window.__mobileLogRowsBound) return;
    window.__mobileLogRowsBound = true;
    function decorateRows() {
        var phone = window.matchMedia('(max-width: 767.98px)').matches;
        document.querySelectorAll('.mobile-log-row').forEach(function (row) {
            if (phone) {
                row.setAttribute('tabindex', '0');
                row.setAttribute('role', 'button');
                row.setAttribute('aria-expanded', row.classList.contains('is-expanded') ? 'true' : 'false');
            } else {
                row.removeAttribute('tabindex');
                row.removeAttribute('role');
                row.removeAttribute('aria-expanded');
                row.classList.remove('is-expanded');
            }
        });
    }
    function toggleRow(row) {
        if (!row || !row.classList.contains('mobile-log-row')) return;
        if (window.matchMedia('(min-width: 768px)').matches) return;
        var expanded = row.classList.toggle('is-expanded');
        row.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
    decorateRows();
    document.addEventListener('turbo:frame-load', decorateRows);
    document.addEventListener('turbo:load', decorateRows);
    window.addEventListener('resize', decorateRows);
    document.addEventListener('click', function (event) {
        var row = event.target.closest && event.target.closest('.mobile-log-row');
        if (!row || event.target.closest('button, a, form, input, select, textarea')) return;
        if (window.matchMedia('(min-width: 768px)').matches) return;
        toggleRow(row);
    });
    document.addEventListener('keydown', function (event) {
        var row = event.target.closest && event.target.closest('.mobile-log-row');
        if (!row || (event.key !== 'Enter' && event.key !== ' ')) return;
        if (event.target.closest('button, a, form, input, select, textarea')) return;
        event.preventDefault();
        toggleRow(row);
    });
})();

function recentLogsFilter() {
    var form = document.getElementById('recentLogsForm');
    if (!form) return;
    var params = new URLSearchParams(new FormData(form));
    var currentUrl = new URL(window.location.href);
    var viewMode = currentUrl.searchParams.get('view') || 'list';
    var month = currentUrl.searchParams.get('month') || '{{ substr($today, 0, 7) }}';
    currentUrl.searchParams.delete('cage_id');
    currentUrl.searchParams.delete('cage_slot_id');
    currentUrl.searchParams.delete('breed');
    currentUrl.searchParams.delete('logged_via');
    ['cage_id', 'cage_slot_id', 'breed'].forEach(function (key) {
        if (params.has(key)) currentUrl.searchParams.set(key, params.get(key));
    });
    params.getAll('logged_via[]').forEach(function (value) { currentUrl.searchParams.append('logged_via[]', value); });
    if (viewMode === 'calendar') {
        currentUrl.searchParams.set('view', viewMode);
        currentUrl.searchParams.set('month', month);
        window.location.assign(currentUrl.toString());
        return;
    }
    currentUrl.searchParams.set('view', viewMode);
    if (currentUrl.searchParams.has('month')) currentUrl.searchParams.set('month', month);
    window.history.replaceState(window.history.state, '', currentUrl.toString());
    var frame = document.querySelector('turbo-frame#egg-logs-list');
    if (frame) {
        var frameUrl = new URL('/eggs/logging/logs', window.location.origin);
        ['cage_id', 'cage_slot_id', 'breed'].forEach(function (key) {
            if (currentUrl.searchParams.has(key)) frameUrl.searchParams.set(key, currentUrl.searchParams.get(key));
        });
        currentUrl.searchParams.getAll('logged_via[]').forEach(function (value) { frameUrl.searchParams.append('logged_via[]', value); });
        frame.setAttribute('src', frameUrl.toString());
    }
}

window.openCalendarDay = function (button) {
    var dialog = document.getElementById('calendarDayDialog');
    var content = document.getElementById('calendarDayContent');
    if (!dialog || !content) return;
    window.__calendarDayOpener = button;
    window.history.pushState({calendarDayOpen: true}, '', window.location.href);
    content.innerHTML = '<p class="py-8 text-center text-sm text-[#615d59]">Loading day details…</p>';
    dialog.classList.remove('hidden');
    dialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
    dialog.querySelector('section').focus();
    var url = new URL(button.dataset.detailUrl, window.location.origin);
    var pageUrl = new URL(window.location.href);
    var selectedVia = pageUrl.searchParams.getAll('logged_via[]');
    ['cage_id', 'cage_slot_id', 'breed'].forEach(function (key) {
        if (pageUrl.searchParams.has(key)) url.searchParams.set(key, pageUrl.searchParams.get(key));
    });
    selectedVia.forEach(function (value) { url.searchParams.append('logged_via[]', value); });
    fetch(url.toString(), {headers: {'X-Requested-With':'XMLHttpRequest'}})
        .then(function (response) { if (!response.ok) throw new Error('Unable to load this day.'); return response.text(); })
        .then(function (html) {
            content.innerHTML = html;
        if (window.lucide) lucide.createIcons();
            if (window.__wireConfirmForms) window.__wireConfirmForms();
        })
        .catch(function () { content.innerHTML = '<p class="py-8 text-center text-sm text-red-700" role="alert">Could not load these production records. Close this panel and try again.</p>'; });
};

(function () {
    var dialog = document.getElementById('calendarDayDialog');
    if (!dialog || window.__calendarDayDialogBound) return;
    window.__calendarDayDialogBound = true;
    function close() {
        dialog.classList.add('hidden');
        dialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        if (window.__calendarDayOpener && window.__calendarDayOpener.isConnected) window.__calendarDayOpener.focus();
    }
    dialog.addEventListener('click', function (event) { if (event.target.closest('[data-calendar-close]')) close(); });
    document.addEventListener('keydown', function (event) {
        if (dialog.classList.contains('hidden')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        var focusables = Array.from(dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(function (el) { return el.offsetParent !== null; });
        if (!focusables.length) { event.preventDefault(); dialog.querySelector('section').focus(); return; }
        var first = focusables[0], last = focusables[focusables.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    window.addEventListener('popstate', function () {
        if (dialog.classList.contains('hidden')) return;
        dialog.classList.add('hidden');
        dialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        if (window.__calendarDayOpener && window.__calendarDayOpener.isConnected) window.__calendarDayOpener.focus();
    });
})();

function openEditLog({id, date, eggCount, henCount, notes, cageSlotId, sizes}) {
    var editForm = document.getElementById('editLogForm');
    if (!editForm) return;
    editForm.action = '/eggs/logging/' + id;
    document.getElementById('editLogDate').value = date;
    document.getElementById('editEggCount').value = eggCount;
    document.getElementById('editHenCountDisplay').value = henCount;
    document.getElementById('editNotes').value = notes || '';
    document.getElementById('editSizeSmall').value = sizes?.small ?? 0;
    document.getElementById('editSizeMedium').value = sizes?.medium ?? 0;
    document.getElementById('editSizeLarge').value = sizes?.large ?? 0;
    document.getElementById('editSizeXl').value = sizes?.xl ?? 0;
    document.getElementById('editSizeJumbo').value = sizes?.jumbo ?? 0;
    document.getElementById('editLogModal').style.display = 'flex';
    editComputeHdep();
    editCheckSizeSum();
    lucide.createIcons();
}

function closeEditLogModal() {
    var modal = document.getElementById('editLogModal');
    if (modal) modal.style.display = 'none';
}

function editComputeHdep() {
    const eggs = parseInt(document.getElementById('editEggCount').value) || 0;
    const hens = parseInt(document.getElementById('editHenCountDisplay').value) || 1;
    const hdep = ((eggs / hens) * 100).toFixed(1);
    const el = document.getElementById('editHdepDisplay');
    el.textContent = 'HDEP:  ' + hdep + '%';
    el.style.backgroundColor = eggs > hens ? '#fbe4e6' : '#f6f5f4';
    el.style.borderColor = eggs > hens ? '#f3cdd0' : '#e6e6e6';
    el.style.color = eggs > hens ? '#9b1c24' : '#1f1f1f';
}

// ── Close SSE before frame content is replaced (tab switch) ──
if (!window.__recentLogsSseGuard) {
    window.__recentLogsSseGuard = true;
    document.addEventListener('turbo:before-frame-render', function(e) {
        if (!e.target || e.target.id !== 'egg-content') return;
        if (window.__logsSource) {
            window.__logsSource.close();
            window.__logsSource = null;
        }
    });
}

// ── Close SSE before full page navigation (sidebar, etc.) ──
if (!window.__recentLogsNavGuard) {
    window.__recentLogsNavGuard = true;
    document.addEventListener('turbo:before-render', function() {
        if (window.__logsSource) {
            window.__logsSource.close();
            window.__logsSource = null;
        }
    });
}

(function() {
    if (window.__recentLogsBound) return;
    window.__recentLogsBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('editLogModal');
            if (modal && !modal.classList.contains('hidden')) closeEditLogModal();
        }
    });

    window.__logsSource = null;
    var lastKnownId = 0;
    var lastKnownVersion = -1;

    function connectLogsSSE() {
        if (!document.querySelector('turbo-frame#egg-logs-list')) return;

        if (window.__logsSource) window.__logsSource.close();
        window.__logsSource = new EventSource('/eggs/logging/live-logs?since=' + lastKnownId + '&version=' + lastKnownVersion);

        window.__logsSource.addEventListener('log_update', function(e) {
            try {
                var data = JSON.parse(e.data);
                if (typeof data.version === 'number') lastKnownVersion = data.version;
                if (data.latest_id > lastKnownId) {
                    lastKnownId = data.latest_id;
                    var frame = document.querySelector('turbo-frame#egg-logs-list');
                    if (frame) {
                        var src = frame.getAttribute('src');
                        var url = new URL(src, window.location.origin);
                        url.searchParams.set('_', Date.now());
                        frame.setAttribute('src', url.toString());
                    }
                }
            } catch(err) {}
        });

        window.__logsSource.onerror = function() {
            if (window.__logsSource && window.__logsSource.readyState === EventSource.CLOSED) {
                setTimeout(function() {
                    if (document.querySelector('turbo-frame#egg-logs-list')) {
                        connectLogsSSE();
                    }
                }, 3000);
            }
        };
    }

    document.addEventListener('turbo:load', function() {
        if (window.__logsSource) window.__logsSource.close();
        setTimeout(connectLogsSSE, 500);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', connectLogsSSE);
    } else {
        connectLogsSSE();
    }
})();
</script>
</turbo-frame>
</div>
@endsection
