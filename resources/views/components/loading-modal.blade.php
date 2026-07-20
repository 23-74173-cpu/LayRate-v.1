{{--
    <x-loading-modal />
    Shared loading overlay — same visual pattern as the Forecast generation
    overlay (spinner card, title, message), for long-running form submissions.
    Include once in the layout.

    Usage:
      Via data attributes on a form (auto-wired):
        <form data-loading="Saving your changes..." data-loading-title="Saving">
      Shows only when the submit actually proceeds (plays nicely with
      data-confirm: the overlay appears after the user confirms, not before).

      Or via JS: showLoadingModal('Title', 'Message...') / hideLoadingModal()

    Not for lazy-loaded turbo-frames — those keep their skeleton partials.
--}}
<div id="loading-modal" class="fixed inset-0 min-h-screen min-h-[100dvh] bg-black/50 backdrop-blur-sm z-[60] hidden items-center justify-center p-4" role="alert" aria-live="assertive" aria-busy="true">
    <div class="bg-white rounded-xl shadow-xl p-8 max-w-sm w-full mx-4 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#002D5E]/10 mb-4">
            <svg class="animate-spin h-6 w-6 text-[#002D5E]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 id="loading-modal-title" class="text-lg font-semibold text-[#333333] mb-1">Processing</h3>
        <p id="loading-modal-message" class="text-sm text-[#6B7280]">Please wait...</p>
    </div>
</div>

<script>
(function() {
        window.showLoadingModal = function(title, message) {
            var el = document.getElementById('loading-modal');
            if (!el) return;
            var titleEl = document.getElementById('loading-modal-title');
            var msgEl = document.getElementById('loading-modal-message');
            if (titleEl) titleEl.textContent = title || 'Processing';
            if (msgEl) msgEl.textContent = message || 'Please wait...';
            el.classList.remove('hidden');
            el.classList.add('flex');
        };

    window.hideLoadingModal = function() {
        var el = document.getElementById('loading-modal');
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
    };

    // Auto-wire: document-level bubble listener runs after per-form handlers,
    // so a data-confirm interception (defaultPrevented) never shows the overlay.
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[data-loading]')) return;
        if (e.defaultPrevented) return;
        showLoadingModal(
            form.getAttribute('data-loading-title') || 'Processing',
            form.getAttribute('data-loading') || 'Please wait...'
        );
    });

    // Hide whenever a new page/frame finishes rendering, or on back/forward
    // restore — covers success, validation-error re-render, and history nav.
    document.addEventListener('turbo:load', hideLoadingModal);
    document.addEventListener('turbo:render', hideLoadingModal);
    window.addEventListener('pageshow', hideLoadingModal);
})();
</script>
