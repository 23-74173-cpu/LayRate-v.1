@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
{{-- Bottom clearance: the fixed quick-dock floats over the viewport's
     bottom-right, so trailing space keeps it off the last table row/paginator. --}}
<div class="space-y-5 recent-logs-page md:pb-16">

    <x-page-header title="Egg Management" subtitle="Review and manage egg production records" subtitle-id="egg-header-subtitle" />

    @include('eggs._tabs', ['activeTab' => 'recent-logs'])

    <turbo-frame id="egg-content">

        <x-card header="Production Logs">
        <form id="recentLogsForm" onsubmit="return false" class="mb-4">
            <div class="recent-filters grid grid-cols-2 gap-3 max-md:grid-cols-12 max-md:items-end md:flex md:flex-wrap md:items-end md:gap-x-4 md:gap-y-3">
                {{-- Cage filter --}}
                <div class="recent-filter-cage max-md:col-span-6 max-md:col-start-1 max-md:row-start-1">
                    <label class="block text-xs max-md:text-sm mb-1" style="color: #615d59;">Cage</label>
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
                <div class="recent-filter-slot max-md:col-span-6 max-md:col-start-7 max-md:row-start-1">
                    <label class="block text-xs max-md:text-sm mb-1" style="color: #615d59;">Slot</label>
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
                <div class="recent-filter-breed max-md:col-span-9 max-md:col-start-1 max-md:row-start-2">
                    <label class="block text-xs max-md:text-sm mb-1" style="color: #615d59;">Breed</label>
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
                <div class="recent-filter-via max-md:col-span-12 max-md:col-start-1 max-md:row-start-3 md:col-span-1">
                    <label class="block text-xs max-md:text-sm mb-1" style="color: #615d59;">Logged Via</label>
                    <div class="flex flex-wrap items-center gap-2">
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

                <div class="recent-filter-reset max-md:col-span-3 max-md:col-start-10 max-md:row-start-2 max-md:justify-self-end md:col-span-1 flex items-center justify-end gap-2">
                    <a href="{{ route('eggs.recent-logs') }}"
                       class="max-md:inline-flex max-md:items-center max-md:justify-center max-md:min-h-11 px-3 py-1.5 text-xs max-md:text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <turbo-frame id="egg-logs-list" src="{{ route('eggs.logging.logs', request()->only(['cage_id', 'cage_slot_id', 'breed', 'logged_via'])) }}" loading="lazy">
            @include('egg-logging._logs-skeleton')
        </turbo-frame>
        </x-card>

        @include('egg-logging._edit-modal')

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
    var frame = document.querySelector('turbo-frame#egg-logs-list');
    if (frame) frame.setAttribute('src', '/eggs/logging/logs?' + params.toString());
}

function openEditLog({id, date, eggCount, henCount, notes, cageSlotId, sizes}) {
    document.getElementById('editLogForm').action = '/eggs/logging/' + id;
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
    document.getElementById('editLogModal').style.display = 'none';
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
