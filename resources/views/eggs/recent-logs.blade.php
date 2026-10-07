@extends('layouts.app')
@section('title', 'Egg Management')

@section('content')
{{-- Bottom clearance: the fixed quick-dock floats over the viewport's
     bottom-right, so trailing space keeps it off the last table row/paginator. --}}
<div class="space-y-5 pb-24 sm:pb-16">

    <x-page-header title="Egg Management" subtitle="Review and manage egg production records" subtitle-id="egg-header-subtitle" />

    @include('eggs._tabs', ['activeTab' => 'recent-logs'])

    <turbo-frame id="egg-content">

    <x-card header="Production Logs">
        <x-filter-bar formId="recentLogsFilterForm" frameId="egg-logs-list"
                      action="{{ route('eggs.logging.logs') }}" title="Filter production logs" data-sync-url>
            <x-filter-select name="cage_id" label="Cage" allLabel="All Cages"
                             :value="request('cage_id', request('cage'))"
                             :options="$cages->pluck('cage_code', 'id')->all()" />
            <x-filter-select name="cage_slot_id" label="Slot" allLabel="All Slots"
                             :value="request('cage_slot_id')"
                             :options="$cageSlots->mapWithKeys(fn ($slot) => [$slot->id => $slot->cage->cage_code . ' · R' . $slot->row_number . '-C' . $slot->column_number])->all()" />
            <x-filter-select name="breed" label="Breed" allLabel="All Breeds"
                             :value="request('breed')"
                             :options="$breeds->mapWithKeys(fn ($b) => [$b => $b])->all()" />
            <x-filter-checks name="logged_via" label="Logged Via"
                             :options="['manual' => 'Manual', 'sensor' => 'Sensor', 'unknown' => 'Unknown']"
                             :values="(array) request('logged_via', [])" />
            <x-filter-date-range fromName="from" toName="to"
                                 :fromValue="request('from', request('date_from', $defaultFrom ?? null))"
                                 :toValue="request('to', request('date_to', $defaultTo ?? null))"
                                 :today="\App\Services\ReportingDateService::reportingDateString()" />
            <input type="hidden" name="range" value="{{ request('range') }}">
        </x-filter-bar>
    </x-card>

        <turbo-frame id="egg-logs-list" src="{{ route('eggs.logging.logs', request()->only(['cage_id', 'cage', 'cage_slot_id', 'breed', 'logged_via', 'from', 'to', 'date_from', 'date_to', 'range', 'page'])) }}" loading="lazy">
            @include('egg-logging._logs-skeleton')
        </turbo-frame>
    </x-card>

    @include('egg-logging._edit-modal')

<script>
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

    function connectLogsSSE() {
        if (!document.querySelector('turbo-frame#egg-logs-list')) return;

        if (window.__logsSource) window.__logsSource.close();
        window.__logsSource = new EventSource('/eggs/logging/live-logs?since=' + lastKnownId);

        window.__logsSource.addEventListener('log_update', function(e) {
            try {
                var data = JSON.parse(e.data);
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