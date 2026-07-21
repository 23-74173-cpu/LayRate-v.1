@extends('layouts.app')
@section('title', 'Environment')

@section('content')
<div class="space-y-5">

    <x-page-header title="Environment" subtitle="Monitor coop temperature, humidity, and alert thresholds" />

    {{-- Page-level tabs: Live Data / Log History --}}
    <x-underline-tabs :tabs="[
        'live' => ['label' => 'Live Data', 'onclick' => 'switchEnvTab(\'live\')'],
        'logs'  => ['label' => 'Log History', 'onclick' => 'switchEnvTab(\'logs\')'],
    ]" active="{{ $envTab ?? 'live' }}" />

    {{-- ── LIVE DATA TAB ── --}}
    <div id="panelLiveData" class="{{ ($envTab ?? 'live') !== 'live' ? 'hidden' : '' }}">

        {{-- Live Data (lazy): metrics, thresholds, sensor cards, trends --}}
        <turbo-frame id="environment-live-data" src="{{ route('environment.live-data') }}" loading="lazy">
            @include('environment._live-data-skeleton')
        </turbo-frame>
    </div>

    {{-- ── LOG HISTORY TAB ── --}}
    <div id="panelLogHistory" class="{{ ($envTab ?? 'live') !== 'logs' ? 'hidden' : '' }}">

        <turbo-frame id="environment-logs" src="{{ route('environment.logs') }}" loading="lazy">
            @include('environment._logs-skeleton')
        </turbo-frame>
    </div>

</div>
@endsection

@push('scripts')
<script>
function switchEnvTab(tab) {
    document.getElementById('panelLiveData').classList.toggle('hidden', tab !== 'live');
    document.getElementById('panelLogHistory').classList.toggle('hidden', tab !== 'logs');
    document.querySelectorAll('[onclick^="switchEnvTab"]').forEach(function(btn) {
        var key = btn.getAttribute('onclick').match(/'(\w+)'/)?.[1];
        var isActive = key === tab;
        btn.className = 'pb-2 text-sm font-medium border-b-2 -mb-px transition-colors ' +
            (isActive ? 'border-[#002D5E] text-[#002D5E]' : 'border-transparent text-[#6B7280] hover:text-[#333]');
    });
    if (tab === 'live') startLivePolling();
    else stopLivePolling();
}

var _livePollTimer = null;

function startLivePolling() {
    stopLivePolling();
    _livePollTimer = setInterval(function() {
        var frame = document.getElementById('environment-live-data');
        if (!frame) return;
        var src = frame.getAttribute('src');
        if (!src) return;
        fetch(src)
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newFrame = doc.querySelector('turbo-frame#environment-live-data');
                if (!newFrame) return;
                frame.innerHTML = newFrame.innerHTML;
                if (typeof lucide !== 'undefined') lucide.createIcons();
                if (typeof initEnvCharts === 'function') initEnvCharts();
            })
            .catch(function() { console.warn('Live poll fetch failed'); });
    }, 10000);
}

function stopLivePolling() {
    if (_livePollTimer) {
        clearInterval(_livePollTimer);
        _livePollTimer = null;
    }
}

document.addEventListener('turbo:load', function() {
    var livePanel = document.getElementById('panelLiveData');
    if (livePanel && !livePanel.classList.contains('hidden')) {
        startLivePolling();
    }
});
document.addEventListener('turbo:before-cache', stopLivePolling);
</script>
@endpush
