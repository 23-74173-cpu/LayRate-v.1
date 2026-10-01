{{--
    <x-confirm-modal />
    Replaces all onsubmit="return confirm('...')".
    Intent tiers (visuals styled once in app.css):
      destructive — red icon + red button (permanent/irreversible actions)
      neutral     — calm navy icon + navy button (standard confirmations,
                    e.g. "Sign out of LayRate?")
      confirm     — primary-blue icon + blue button (generic confirmations)
      warning     — amber icon + amber button (caution, reversible)
      success     — green icon + green button (acknowledge a result)
      info        — blue icon + single "Got it" button (informational)
    Default: neutral.

    Layout: large circular tinted icon centered on top, centered bold title,
    centered muted message, centered equal-width buttons (full-width stacked
    on mobile). Small X in the top-right. Backdrop blur/dim, 20px radius,
    ESC-to-close and click-outside preserved, action button autofocused.

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
        class="modal-card relative w-full max-w-md p-6 sm:p-8 max-h-screen max-h-[100dvh] overflow-y-auto"
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

        {{-- Intent icon (large, centered) --}}
        <div id="confirm-modal-icon" class="modal-icon modal-icon--neutral">
            <i data-lucide="circle-check"></i>
        </div>

        {{-- Title --}}
        <h3 id="confirm-modal-title" class="modal-title mt-4">
            Confirm action
        </h3>

        {{-- Message --}}
        <p id="confirm-modal-message" class="modal-message">
            Are you sure you want to proceed?
        </p>

        {{-- Actions --}}
        <div id="confirm-modal-actions" class="modal-actions">
            <button
                id="confirm-modal-cancel"
                type="button"
                onclick="confirmModalClose()"
                class="modal-btn modal-btn--secondary"
            >
                Cancel
            </button>
            <button
                id="confirm-modal-action"
                type="button"
                class="modal-btn modal-btn--neutral"
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
        var cancelBtn  = document.getElementById('confirm-modal-cancel');
        var actionBtn  = document.getElementById('confirm-modal-action');
        var actionsRow = document.getElementById('confirm-modal-actions');

        // Intent visuals — classes styled once in app.css (.modal-icon-*,
        // .modal-btn-*). Unknown severities fall back to neutral.
        var lucideIcon, iconModifier, btnModifier;
        if (severity === 'destructive') {
            lucideIcon = 'alert-triangle';
            iconModifier = 'modal-icon--danger';
            btnModifier = 'modal-btn--danger';
            cancelBtn.classList.remove('hidden');
            actionsRow.classList.remove('modal-actions--single');
            actionBtn.classList.remove('w-full');
        } else if (severity === 'warning') {
            lucideIcon = 'alert-triangle';
            iconModifier = 'modal-icon--warning';
            btnModifier = 'modal-btn--warning';
            cancelBtn.classList.remove('hidden');
            actionsRow.classList.remove('modal-actions--single');
            actionBtn.classList.remove('w-full');
        } else if (severity === 'success') {
            lucideIcon = 'circle-check';
            iconModifier = 'modal-icon--success';
            btnModifier = 'modal-btn--success';
            cancelBtn.classList.remove('hidden');
            actionsRow.classList.remove('modal-actions--single');
            actionBtn.classList.remove('w-full');
        } else if (severity === 'confirm') {
            lucideIcon = 'circle-check';
            iconModifier = 'modal-icon--confirm';
            btnModifier = 'modal-btn--primary';
            cancelBtn.classList.remove('hidden');
            actionsRow.classList.remove('modal-actions--single');
            actionBtn.classList.remove('w-full');
        } else if (severity === 'info') {
            lucideIcon = 'circle-info';
            iconModifier = 'modal-icon--info';
            btnModifier = 'modal-btn--primary';
            cancelBtn.classList.add('hidden');
            actionsRow.classList.add('modal-actions--single');
            actionBtn.classList.add('w-full');
        } else {
            // neutral (default)
            lucideIcon = 'circle-check';
            iconModifier = 'modal-icon--neutral';
            btnModifier = 'modal-btn--neutral';
            cancelBtn.classList.remove('hidden');
            actionsRow.classList.remove('modal-actions--single');
            actionBtn.classList.remove('w-full');
        }

        iconWrap.className = 'modal-icon ' + iconModifier;
        iconWrap.innerHTML = '<i data-lucide="' + lucideIcon + '"></i>';
        actionBtn.className = 'modal-btn ' + btnModifier;

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
            // Use submit() not requestSubmit() to bypass Turbo's submit-event
            // interception entirely. At this point the user has already
            // confirmed the action, so re-dispatching a submit event — and
            // risking Turbo hijacking the navigation — is unnecessary.
            form.submit();
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
