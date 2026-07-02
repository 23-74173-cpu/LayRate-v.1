{{--
    <x-alerts-modal :alerts="$alerts" />
    One-time informational modal shown at the start of a session when unread alerts exist.
    Acknowledging sets a server-side session flag (does NOT mark alerts read).
    "View all" links to the dedicated notifications page.
--}}
@props(['alerts'])

@if($alerts->isNotEmpty())
<div id="alerts-modal" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="alerts-modal-title">
    {{-- Backdrop --}}
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="acknowledgeAlertsModal()"></div>

    {{-- Card --}}
    <div class="relative w-full max-w-md rounded-2xl p-6 mx-4" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        {{-- Close X --}}
        <button type="button" onclick="acknowledgeAlertsModal()" class="absolute top-4 right-4 p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
            <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
        </button>

        {{-- Icon --}}
        <div class="mb-4 flex items-center justify-center w-10 h-10 rounded-full" style="background-color: #e0f2fe;">
            <i data-lucide="bell" class="w-5 h-5" style="color: #0075de;"></i>
        </div>

        {{-- Title --}}
        <h2 id="alerts-modal-title" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">
            {{ $alerts->count() }} {{ Str::plural('alert', $alerts->count()) }} need attention
        </h2>

        {{-- Subtitle --}}
        <p class="mt-1 text-sm" style="color: #615d59;">
            Review them on the notifications page. They will stay active until you mark them read.
        </p>

        {{-- Alert list --}}
        <div id="alerts-modal-list" class="mt-5 space-y-2 max-h-[16rem] overflow-y-auto scrollbar-thin pr-1">
            @foreach($alerts as $alert)
            <div data-alert-id="{{ $alert->id }}" class="flex items-start gap-3 rounded-lg border p-3" style="background-color: #fafafa; border-color: #e6e6e6;">
                <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background-color: {{ $alert->cage?->color ?? '#6B7280' }};"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium" style="color: #31302e;">
                        {{ $alert->cage?->cage_code ?? 'Farm-wide' }}
                        <span class="font-normal" style="color: #a39e98;">· {{ $alert->triggered_at->diffForHumans() }}</span>
                    </p>
                    <p class="text-sm mt-0.5" style="color: #1f1f1f;">{{ $alert->message }}</p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex flex-col-reverse sm:flex-row items-center justify-end gap-3">
            <button type="button" onclick="acknowledgeAlertsModal()" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg transition-colors" style="color: #1f1f1f; border: 1px solid #e6e6e6;" onmouseover="this.style.backgroundColor='#f6f5f4'" onmouseout="this.style.backgroundColor='transparent'">
                Acknowledge
            </button>
            <a href="{{ route('notifications.index') }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg text-center transition-colors" style="background-color: #0075de; color: #ffffff;" onmouseover="this.style.backgroundColor='#005bb5'" onmouseout="this.style.backgroundColor='#0075de'">
                View all notifications
            </a>
        </div>
    </div>
</div>

<script>
(function() {
    window.acknowledgeAlertsModal = function() {
        const ids = Array.from(document.querySelectorAll('#alerts-modal-list [data-alert-id]'))
            .map(function(el) { return parseInt(el.dataset.alertId, 10); })
            .filter(function(id) { return !isNaN(id); });

        fetch('{{ route('alerts.acknowledge-modal') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(function() {
            const modal = document.getElementById('alerts-modal');
            if (modal) modal.remove();
        })
        .catch(function() {
            const modal = document.getElementById('alerts-modal');
            if (modal) modal.remove();
        });
    };

    // Escape key closes modal
    function onKeydown(e) {
        if (e.key === 'Escape') acknowledgeAlertsModal();
    }

    document.removeEventListener('keydown', onKeydown);
    document.addEventListener('keydown', onKeydown);
})();
</script>
@endif
