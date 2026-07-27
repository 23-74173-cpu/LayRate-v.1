{{--
    <x-confirm-modal />
    Replaces all onsubmit="return confirm('...')".
    Severity tiers:
      destructive — red icon + red button (permanent/irreversible actions)
      neutral     — calm icon + navy button (standard confirmations)
      info        — blue icon + single "Got it" button (informational)
    Default: neutral.

    Usage:
      Include once in the layout or page.
      Trigger via JS: confirmModal('Are you sure?', formElement, 'Delete', 'destructive')
      Or via data attributes on a form:
        <form data-confirm="Delete this record?" data-confirm-action="Delete" data-confirm-severity="destructive">
        <form data-confirm="Note added." data-confirm-action="Got it" data-confirm-severity="info" data-confirm-cancel="false">
--}}

{{-- Backdrop + Card --}}
<div
    id="confirm-modal"
    class="fixed inset-0 z-50 hidden min-h-screen min-h-[100dvh] items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-modal-title"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0"
        style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);"
        onclick="confirmModalClose()"
    ></div>

    {{-- Card --}}
    <div
        class="relative w-full max-w-md rounded-2xl p-6"
        style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;"
    >
        {{-- Close X --}}
        <button
            type="button"
            onclick="confirmModalClose()"
            class="absolute top-4 right-4 p-1.5 rounded-full hover:bg-black/5 transition-colors"
            aria-label="Close"
        >
            <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
        </button>

        {{-- Icon --}}
        <div id="confirm-modal-icon" class="mb-4 flex items-center justify-center w-10 h-10 rounded-full" style="background-color: #e8ecf4;">
            <i data-lucide="info" class="w-5 h-5" style="color: #213183;"></i>
        </div>

        {{-- Title --}}
        <h3 id="confirm-modal-title" class="text-lg font-semibold" style="color: #1f1f1f; letter-spacing: -0.125px;">
            Confirm action
        </h3>

        {{-- Message --}}
        <p id="confirm-modal-message" class="mt-2 text-sm" style="color: #615d59;">
            Are you sure you want to proceed?
        </p>

        {{-- Actions --}}
        <div id="confirm-modal-actions" class="mt-6 flex items-center justify-end gap-3">
            <button
                id="confirm-modal-cancel"
                type="button"
                onclick="confirmModalClose()"
                class="px-4 py-2 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors"
            >
                Cancel
            </button>
            <button
                id="confirm-modal-action"
                type="button"
                class="px-4 py-2 text-sm font-medium rounded-full text-white transition-colors"
                style="background-color: #213183;"
            >
                Confirm
            </button>
        </div>
    </div>
</div>

{{-- JS logic --}}
<script>
(function() {
    let pendingForm = null;

    window.confirmModal = function(message, form, actionLabel, severity) {
        pendingForm = form;
        severity = severity || 'neutral';
        document.getElementById('confirm-modal-message').innerHTML = message;
        document.getElementById('confirm-modal-action').textContent = actionLabel || 'Confirm';

        var iconWrap = document.getElementById('confirm-modal-icon');
        var icon     = iconWrap.querySelector('i');
        var cancelBtn  = document.getElementById('confirm-modal-cancel');
        var actionBtn  = document.getElementById('confirm-modal-action');
        var actionsRow = document.getElementById('confirm-modal-actions');

        if (severity === 'destructive') {
            iconWrap.style.backgroundColor = '#fbe4e6';
            icon.setAttribute('data-lucide', 'alert-triangle');
            icon.style.color = '#9b1c24';
            actionBtn.style.backgroundColor = '#9b1c24';
            actionBtn.className = 'px-4 py-2 text-sm font-medium rounded-full bg-[#9b1c24] text-white hover:bg-[#7a161d] transition-colors';
            cancelBtn.classList.remove('hidden');
            actionsRow.classList.remove('flex-col');
            actionsRow.classList.add('flex');
        } else if (severity === 'info') {
            iconWrap.style.backgroundColor = '#dbeafe';
            icon.setAttribute('data-lucide', 'circle-info');
            icon.style.color = '#1D4E8F';
            actionBtn.style.backgroundColor = '#1D4E8F';
            actionBtn.className = 'px-4 py-2 text-sm font-medium rounded-lg bg-[#1D4E8F] text-white hover:bg-[#163d73] transition-colors';
            cancelBtn.classList.add('hidden');
            actionsRow.classList.remove('flex');
            actionsRow.classList.add('flex-col');
            actionBtn.classList.add('w-full');
        } else {
            // neutral (default)
            iconWrap.style.backgroundColor = '#e8ecf4';
            icon.setAttribute('data-lucide', 'circle-check');
            icon.style.color = '#213183';
            actionBtn.className = 'px-4 py-2 text-sm font-medium rounded-full text-white transition-colors';
            actionBtn.style.backgroundColor = '#213183';
            cancelBtn.classList.remove('hidden');
            actionsRow.classList.remove('flex-col');
            actionsRow.classList.add('flex');
        }

        // Remove hidden from the modal itself
        document.getElementById('confirm-modal').classList.remove('hidden');
        document.getElementById('confirm-modal').classList.add('flex');

        // Re-render Lucide icons for the dynamic icon
        if (typeof lucide !== 'undefined') lucide.createIcons();

        actionBtn.focus();
    };

    window.confirmModalClose = function() {
        pendingForm = null;
        document.getElementById('confirm-modal').classList.add('hidden');
        document.getElementById('confirm-modal').classList.remove('flex');
    };

    document.getElementById('confirm-modal-action').addEventListener('click', function() {
        var form = pendingForm;
        confirmModalClose();
        if (!form) return;
        if (form instanceof HTMLFormElement) {
            // Flag lets the data-confirm submit interceptor pass this
            // submission through — without it, requestSubmit() re-fires the
            // intercepted submit event and the form never actually submits.
            form.dataset.confirmed = 'true';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        } else if (typeof form.submit === 'function') {
            // JS-path pseudo-form ({ submit: callback }) — e.g. Clear All Cages.
            form.submit();
        }
    });

    // Escape key closes modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            confirmModalClose();
        }
    });

    // Auto-wire forms with data-confirm attribute
    function wireConfirmForms() {
        document.querySelectorAll('form[data-confirm]:not([data-confirm-wired])').forEach(function(form) {
            form.setAttribute('data-confirm-wired', 'true');
            form.addEventListener('submit', function(e) {
                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;
                    return; // user already confirmed — let the submit proceed
                }
                e.preventDefault();
                const message  = form.getAttribute('data-confirm');
                const action   = form.getAttribute('data-confirm-action') || 'Confirm';
                const severity = form.getAttribute('data-confirm-severity') || 'neutral';
                confirmModal(message, form, action, severity);
            });
        });
    }

    wireConfirmForms();

    // Re-wire after Turbo frame/page loads
    document.addEventListener('turbo:frame-load', wireConfirmForms);
    document.addEventListener('turbo:load', wireConfirmForms);
})();
</script>
