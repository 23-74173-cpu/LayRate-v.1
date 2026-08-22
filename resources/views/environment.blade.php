@extends('layouts.app')
@section('title', 'Environment')

@section('content')
<div class="space-y-5">

    <x-page-header title="Environment" subtitle="Monitor coop temperature, humidity, and alert thresholds" />

    <x-fab>
        <button type="button" onclick="openEnvThresholdsModal()"
                class="flex items-center gap-3 bg-white border border-[#D9D9D9] text-[#333333] px-4 py-2.5 rounded-full shadow-lg hover:bg-[#F5F6F8] transition-colors text-sm">
            <span>Configure Thresholds</span>
            <div class="w-8 h-8 rounded-full bg-[#6B4C8A]/10 flex items-center justify-center">
                <i data-lucide="sliders" class="w-4 h-4 text-[#6B4C8A]"></i>
            </div>
        </button>
    </x-fab>

    {{-- Page-level tabs: Live Data / Log History --}}
    <x-underline-tabs :tabs="[
        'live' => ['label' => 'Live Data',  'icon' => 'activity', 'onclick' => 'switchEnvTab(\'live\')'],
        'logs'  => ['label' => 'Log History', 'icon' => 'clock',   'onclick' => 'switchEnvTab(\'logs\')'],
    ]" active="{{ $envTab ?? 'live' }}" />

    {{-- ── LIVE DATA TAB ── --}}
    <div id="panelLiveData" class="{{ ($envTab ?? 'live') !== 'live' ? 'hidden' : '' }}">

        <style>
            @keyframes layrate-fan-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            .layrate-fan-spinning { animation: layrate-fan-spin 1s linear infinite; }
        </style>

        {{-- ── Fan Control Card (SSE-driven — lives outside the polled turbo-frame) ── --}}
        <div id="relayCard" class="bg-white rounded-xl border border-[#D9D9D9] p-5 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div id="relayFanBubble" class="w-14 h-14 rounded-full flex items-center justify-center shrink-0 transition-colors"
                         style="background:#F0F0F0; color:#615d59;">
                        <i data-lucide="fan" class="w-7 h-7"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="text-sm font-semibold text-[#1f1f1f]">Cooling Fan</div>
                            <span id="relayStatusBadge" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold leading-none whitespace-nowrap"
                                  style="background:#F0F0F0; color:#615d59; border:1px solid #E6E6E6;">—</span>
                            <span id="relayModeBadge" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold leading-none whitespace-nowrap"
                                  style="background:#eef2fb; color:#002D5E; border:1px solid #d6e0f2;">AUTO</span>
                        </div>
                        <div id="relaySubtext" class="text-xs text-[#6B7280] mt-1">Waiting for sensor signal…</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-relay-action="on"
                            class="relay-action-btn inline-flex items-center justify-center rounded-lg border border-[#D9D9D9] px-4 py-2 text-sm font-medium text-[#6B7280] hover:bg-[#F5F6F8] transition-colors">Fan ON</button>
                    <button type="button" data-relay-action="off"
                            class="relay-action-btn inline-flex items-center justify-center rounded-lg border border-[#D9D9D9] px-4 py-2 text-sm font-medium text-[#6B7280] hover:bg-[#F5F6F8] transition-colors">Fan OFF</button>
                    <button type="button" data-relay-action="auto"
                            class="relay-action-btn inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors"
                            style="background:#002D5E;">AUTO</button>
                </div>
            </div>
        </div>

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

{{-- ── Alert Threshold Configuration Modal (outside tab panels so always in DOM) ── --}}
<div id="envThresholdsModal" data-modal  data-close="closeEnvThresholdsModal" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEnvThresholdsModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Alert Thresholds</h2>
            <button onclick="closeEnvThresholdsModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form action="{{ route('environment.thresholds') }}" method="POST" id="threshold-form">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TEMP MIN (°C)</label>
                    <input type="number" name="temp_min" step="0.5"
                           value="{{ $thresholds['temp_min'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TEMP MAX (°C)</label>
                    <input type="number" name="temp_max" step="0.5"
                           value="{{ $thresholds['temp_max'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">HUMIDITY MIN (%)</label>
                    <input type="number" name="hum_min" step="1"
                           value="{{ $thresholds['hum_min'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
                <div>
                    <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">HUMIDITY MAX (%)</label>
                    <input type="number" name="hum_max" step="1"
                           value="{{ $thresholds['hum_max'] }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEnvThresholdsModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Cancel</button>
                <x-button type="submit" id="save-thresholds-btn" class="flex-1 py-2.5">
                    Save Thresholds
                </x-button>
            </div>
        </form>
    </div>
</div>

{{-- ── Environment Log Override Modal ── --}}
<div id="envLogOverrideModal" data-modal  data-close="closeEnvLogOverride" style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEnvLogOverride()"></div>
    <div class="relative w-full max-w-sm rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Override Environment Log</h2>
            <button onclick="closeEnvLogOverride()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="envLogOverrideForm" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <p class="text-sm text-[#6B7280]">Replace the daily average for <strong id="envLogOverrideCageLabel">—</strong> on <strong id="envLogOverrideDateLabel">—</strong>.</p>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Temperature (°C)</label>
                    <input type="number" name="temperature_c" id="envOverrideTemp" step="0.1" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                    <x-input-error name="temperature_c" />
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Humidity (%)</label>
                    <input type="number" name="humidity_pct" id="envOverrideHum" step="1" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                    <x-input-error name="humidity_pct" />
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEnvLogOverride()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Cancel</button>
                <x-button type="submit" class="flex-1 py-2.5">Save Override</x-button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function switchEnvTab(tab) {
    if (window.__envActiveTab === tab) return;
    window.__envActiveTab = tab;

    document.getElementById('panelLiveData').classList.toggle('hidden', tab !== 'live');
    document.getElementById('panelLogHistory').classList.toggle('hidden', tab !== 'logs');
    document.querySelectorAll('[onclick^="switchEnvTab"]').forEach(function(btn) {
        var key = btn.getAttribute('onclick').match(/'(\w+)'/)?.[1];
        var isActive = key === tab;
        btn.className = 'pb-2 text-sm font-medium border-b-2 transition-colors ' +
            (isActive ? 'border-[#002D5E] text-[#002D5E]' : 'border-transparent text-[#6B7280] hover:text-[#333]');
    });
    if (tab === 'live') {
        startLivePolling();
        startRelaySSE();
        envRefreshNow();
    } else {
        stopLivePolling();
        stopRelaySSE();
    }
}

// ── Threshold Config Modal ──
function openEnvThresholdsModal() {
    var modal = document.getElementById('envThresholdsModal');
    if (!modal) return;
    modal.style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
    stopLivePolling();
}

function closeEnvThresholdsModal() {
    var modal = document.getElementById('envThresholdsModal');
    if (!modal) return;
    modal.style.display = 'none';
    startLivePolling();
}

function getLiveDataSrc() {
    var frame = document.getElementById('environment-live-data');
    if (!frame) return '{{ route("environment.live-data") }}';
    return frame.getAttribute('src') || '{{ route("environment.live-data") }}';
}

function changeTrendRange(range) {
    var base = '{{ route("environment.live-data") }}';
    var url = base + '?range=' + range;
    var frame = document.getElementById('environment-live-data');
    if (frame) {
        frame.setAttribute('src', url);
        frame.reload();
    }
}

function envRefreshNow() {
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
        .catch(function() { /* silent — polling will retry */ });
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
        startRelaySSE();
    }
});
document.addEventListener('turbo:before-cache', function() {
    stopLivePolling();
    stopRelaySSE();
});

// ── Relay / Fan — SSE-driven state + manual control ──
var __relayInitial = @json($relayState ?? ['configured' => false]);

function applyRelayState(state) {
    if (!state) return;
    var bubble = document.getElementById('relayFanBubble');
    var badge = document.getElementById('relayStatusBadge');
    var modeBadge = document.getElementById('relayModeBadge');
    var subtext = document.getElementById('relaySubtext');
    if (!badge || !modeBadge || !subtext) return;

    if (!state.configured) {
        if (bubble) { bubble.style.background = '#F0F0F0'; bubble.style.color = '#615d59'; bubble.classList.remove('layrate-fan-spinning'); }
        badge.textContent = 'No Relay';
        badge.style.background = '#F0F0F0'; badge.style.color = '#615d59'; badge.style.borderColor = '#E6E6E6';
        modeBadge.textContent = '—';
        modeBadge.style.background = '#F0F0F0'; modeBadge.style.color = '#615d59'; modeBadge.style.borderColor = '#E6E6E6';
        subtext.textContent = 'No active relay device is registered.';
        paintRelayButtons(null);
        return;
    }

    var on = state.relay_status === 'on';
    var online = !!state.online;
    var blocked = !!state.relay_safety;

    // Three states (not two): ON / OFF / safety-blocked. "Blocked" means the
    // user commanded MANUAL ON but an invalid DHT22 read forced the fan off —
    // shown with a warning, never as a plain OFF.
    if (!online) {
        if (bubble) { bubble.style.background = '#F0F0F0'; bubble.style.color = '#8a5a00'; bubble.classList.remove('layrate-fan-spinning'); }
        badge.textContent = 'No Signal';
        badge.style.background = '#fdf3e0'; badge.style.color = '#8a5a00'; badge.style.borderColor = '#f3e3bf';
    } else if (blocked) {
        if (bubble) { bubble.style.background = '#fbe4e6'; bubble.style.color = '#9b1c24'; bubble.classList.remove('layrate-fan-spinning'); }
        badge.textContent = 'Safety Block';
        badge.style.background = '#fbe4e6'; badge.style.color = '#9b1c24'; badge.style.borderColor = '#f3cdd0';
    } else if (on) {
        if (bubble) { bubble.style.background = '#e8f0fb'; bubble.style.color = '#002D5E'; bubble.classList.add('layrate-fan-spinning'); }
        badge.textContent = 'Fan ON';
        badge.style.background = '#e8f5ec'; badge.style.color = '#1f6b3a'; badge.style.borderColor = '#cfe8d6';
    } else {
        if (bubble) { bubble.style.background = '#F0F0F0'; bubble.style.color = '#615d59'; bubble.classList.remove('layrate-fan-spinning'); }
        badge.textContent = 'Fan OFF';
        badge.style.background = '#F0F0F0'; badge.style.color = '#615d59'; badge.style.borderColor = '#E6E6E6';
    }

    if (state.control_mode === 'manual') {
        modeBadge.textContent = 'MANUAL';
        modeBadge.style.background = '#fbe4e6'; modeBadge.style.color = '#9b1c24'; modeBadge.style.borderColor = '#f3cdd0';
    } else {
        modeBadge.textContent = 'AUTO';
        modeBadge.style.background = '#eef2fb'; modeBadge.style.color = '#002D5E'; modeBadge.style.borderColor = '#d6e0f2';
    }

    if (!online) {
        subtext.textContent = state.stale
            ? 'Bridge unreachable — last signal a while ago. Auto-hysteresis still runs on the Arduino.'
            : 'Waiting for the sensor bridge to report…';
    } else if (blocked) {
        subtext.textContent = 'MANUAL ON requested, but the DHT22 reading is invalid — the safety default forced the fan OFF. Check the sensor wiring.';
    } else if (state.control_mode === 'manual') {
        var by = state.last_changed_by ? ' by ' + state.last_changed_by : '';
        subtext.textContent = 'Manual override' + by + '. Hysteresis is paused until you return to AUTO.';
    } else if (state.temperature_c !== null && state.temperature_c !== undefined) {
        subtext.textContent = state.temperature_c + '°C · auto-hysteresis · fan ' + (on ? 'on' : 'off');
    } else {
        subtext.textContent = 'Auto-hysteresis active.';
    }

    paintRelayButtons(state.control_mode === 'manual' ? (on ? 'on' : 'off') : 'auto');
}

function paintRelayButtons(activeAction) {
    document.querySelectorAll('[data-relay-action]').forEach(function(btn) {
        var action = btn.getAttribute('data-relay-action');
        var active = action === activeAction;
        if (active) {
            btn.style.background = '#002D5E';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#002D5E';
        } else {
            btn.style.background = '#ffffff';
            btn.style.color = '#6B7280';
            btn.style.borderColor = '#D9D9D9';
        }
    });
}

function startRelaySSE() {
    var card = document.getElementById('relayCard');
    if (!card) return;
    if (window.__relaySource) window.__relaySource.close();
    var url = '{{ route("environment.relay-stream") }}';
    window.__relaySource = new EventSource(url);
    window.__relaySource.addEventListener('relay_state', function(e) {
        try { applyRelayState(JSON.parse(e.data)); } catch (err) {}
    });
    window.__relaySource.onerror = function() {
        if (window.__relaySource && window.__relaySource.readyState === EventSource.CLOSED) {
            setTimeout(function() {
                if (document.getElementById('relayCard')) startRelaySSE();
            }, 3000);
        }
    };
}

function stopRelaySSE() {
    if (window.__relaySource) {
        window.__relaySource.close();
        window.__relaySource = null;
    }
}

function controlRelay(action) {
    if (window.__relaySending) return;
    window.__relaySending = true;

    var form = new FormData();
    form.append('_token', '{{ csrf_token() }}');
    form.append('action', action);

    fetch('{{ route("environment.relay.control") }}', {
        method: 'POST',
        body: form,
        headers: { 'Accept': 'application/json' },
    })
    .then(function(r) {
        return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; });
    })
    .then(function(res) {
        if (res.ok && res.data && res.data.success) {
            applyRelayState(res.data.relay);
            if (typeof showNotification === 'function') {
                showNotification('Relay set to ' + action.toUpperCase() + '.', 'success');
            }
        } else if (res.status === 404) {
            if (typeof showNotification === 'function') {
                showNotification((res.data && res.data.message) || 'No active relay registered.', 'error');
            }
        } else if (res.status === 422) {
            var msg = Object.values(res.data.errors || {})[0]?.[0] || 'Validation failed.';
            if (typeof showNotification === 'function') showNotification(msg, 'error');
        } else {
            throw new Error('Server returned ' + res.status);
        }
    })
    .catch(function(err) {
        if (typeof showNotification === 'function') {
            showNotification('Failed to send relay command: ' + err.message, 'error');
        }
    })
    .finally(function() {
        window.__relaySending = false;
    });
}

(function initRelayWidget() {
    document.querySelectorAll('[data-relay-action]').forEach(function(btn) {
        if (btn.__relayWired) return;
        btn.__relayWired = true;
        btn.addEventListener('click', function() {
            controlRelay(btn.getAttribute('data-relay-action'));
        });
    });
    applyRelayState(window.__relayInitial);
    var livePanel = document.getElementById('panelLiveData');
    if (livePanel && !livePanel.classList.contains('hidden')) {
        startRelaySSE();
    }
})();

// ── Environment Log Override ──
function openEnvLogOverride(cageId, date, currentTemp, currentHum, cageCode) {
    var form = document.getElementById('envLogOverrideForm');
    if (!form) return;
    form.action = '/environment/logs/' + cageId + '/' + date;

    document.getElementById('envOverrideTemp').value = currentTemp;
    document.getElementById('envOverrideHum').value = currentHum;
    document.getElementById('envLogOverrideCageLabel').textContent = cageCode || 'Cage #' + cageId;
    document.getElementById('envLogOverrideDateLabel').textContent = date;
    document.getElementById('envLogOverrideModal').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeEnvLogOverride() {
    var modal = document.getElementById('envLogOverrideModal');
    if (modal) modal.style.display = 'none';
}

// Escape key closes modals
if (!window.__envThresholdsEscapeBound) {
    window.__envThresholdsEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEnvThresholdsModal();
            closeEnvLogOverride();
        }
    });
}

// ── Threshold save handler (AJAX, on form directly since modal is outside Turbo Frame) ──
(function() {
    var form = document.getElementById('threshold-form');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('save-thresholds-btn');
        if (!btn) return;
        var originalText = btn.textContent;
        btn.textContent = 'Saving\u2026';
        btn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json' },
        })
        .then(function(r) {
            if (r.ok || r.redirected) {
                envRefreshNow();
                if (typeof showNotification === 'function') {
                    showNotification('Thresholds saved successfully.', 'success');
                }
            } else if (r.status === 422) {
                return r.json().then(function(data) {
                    var msg = Object.values(data.errors)[0]?.[0] || 'Validation failed.';
                    if (typeof showNotification === 'function') {
                        showNotification(msg, 'error');
                    }
                });
            } else {
                throw new Error('Server returned ' + r.status);
            }
        })
        .catch(function(err) {
            if (typeof showNotification === 'function') {
                showNotification('Failed to save thresholds: ' + err.message, 'error');
            }
        })
        .finally(function() {
            btn.textContent = originalText;
            btn.disabled = false;
        });
    });
})();
</script>
@endpush
