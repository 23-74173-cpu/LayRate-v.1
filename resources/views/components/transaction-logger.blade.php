{{--
    Logs every state-changing form submission's data to the browser console,
    so the actual transaction payload is visible during a demo/inspection
    without touching every individual view.

    Fires only for the real submission (skips a data-confirm interception —
    e.defaultPrevented — since that's a cancelled/pending attempt, not an
    actual transaction), and only for POST-family forms (POST/PUT/PATCH/
    DELETE via Laravel's _method spoofing) — GET forms are filters/searches,
    not transactions.

    Sensitive fields (password/pin/token) are logged as present but masked.
--}}
<script>
(function() {
    const SENSITIVE = /password|pin|token/i;

    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form.matches || !form.matches('form') || e.defaultPrevented) return;

        const spoofed = form.querySelector('input[name="_method"]');
        const method = (spoofed ? spoofed.value : form.method || 'GET').toUpperCase();
        if (method === 'GET') return;

        const data = {};
        new FormData(form).forEach(function(value, key) {
            if (key === '_token' || key === '_method') return;
            if (value instanceof File) {
                data[key] = value.name ? `[File: ${value.name}]` : '[File]';
            } else {
                data[key] = SENSITIVE.test(key) ? '••••••••' : value;
            }
        });

        console.log(
            `[Transaction] ${method} ${form.getAttribute('action') || location.pathname}`,
            data
        );
    });
})();
</script>
