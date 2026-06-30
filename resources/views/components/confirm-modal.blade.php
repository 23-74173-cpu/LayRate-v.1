{{--
    <x-confirm-modal />
    Replaces all onsubmit="return confirm('...')".
    Notion-style modal: centered, white card, subtle backdrop blur,
    clear primary (destructive) / secondary (cancel) button hierarchy.

    Usage:
      Include once in the layout or page.
      Trigger via JS: confirmModal('Are you sure?', formElement)
      Or via data attributes on a form:
        <form data-confirm="Delete this record?" data-confirm-action="Delete">
--}}

{{-- Backdrop + Card --}}
<div
    id="confirm-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center p-4"
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
        {{-- Icon --}}
        <div class="mb-4 flex items-center justify-center w-10 h-10 rounded-full" style="background-color: #fbe4e6;">
            <i data-lucide="alert-triangle" class="w-5 h-5" style="color: #9b1c24;"></i>
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
        <div class="mt-6 flex items-center justify-end gap-3">
            <button
                type="button"
                onclick="confirmModalClose()"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                onmouseover="this.style.backgroundColor='#f6f5f4'"
                onmouseout="this.style.backgroundColor='transparent'"
            >
                Cancel
            </button>
            <button
                id="confirm-modal-action"
                type="button"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                style="background-color: #9b1c24; color: #ffffff;"
                onmouseover="this.style.backgroundColor='#7a161d'"
                onmouseout="this.style.backgroundColor='#9b1c24'"
            >
                Delete
            </button>
        </div>
    </div>
</div>

{{-- JS logic --}}
<script>
(function() {
    let pendingForm = null;

    window.confirmModal = function(message, form, actionLabel) {
        pendingForm = form;
        document.getElementById('confirm-modal-message').textContent = message;
        document.getElementById('confirm-modal-action').textContent = actionLabel || 'Confirm';
        document.getElementById('confirm-modal').classList.remove('hidden');
        document.getElementById('confirm-modal').classList.add('flex');
        document.getElementById('confirm-modal-action').focus();
    };

    window.confirmModalClose = function() {
        pendingForm = null;
        document.getElementById('confirm-modal').classList.add('hidden');
        document.getElementById('confirm-modal').classList.remove('flex');
    };

    document.getElementById('confirm-modal-action').addEventListener('click', function() {
        if (pendingForm) {
            pendingForm.submit();
        }
        confirmModalClose();
    });

    // Escape key closes modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            confirmModalClose();
        }
    });

    // Auto-wire forms with data-confirm attribute
    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = form.getAttribute('data-confirm');
            const action = form.getAttribute('data-confirm-action') || 'Confirm';
            confirmModal(message, form, action);
        });
    });
})();
</script>
