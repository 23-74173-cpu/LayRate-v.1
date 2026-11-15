@props([
    'modalId' => '',
    'sessionKey' => 'reopen_modal',
    'guard' => 'default',
])

@if(session($sessionKey))
<script>
(function() {
    var k = '__reopen_{{ str_replace('-', '_', $guard) }}';
    if (window[k]) return;
    window[k] = true;
    // Reopen once for this response only. Turbo keeps window and document
    // between visits, so a listener left behind would reopen the modal after
    // every later page load (e.g. after the corrected form saved).
    var f = function() {
        document.removeEventListener('turbo:load', f);
        document.removeEventListener('DOMContentLoaded', f);
        if (!window[k]) return;
        window[k] = false;
        var el = document.getElementById('{{ $modalId }}');
        if (el) {
            el.style.display = 'flex';
            {{ $slot }}
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', f);
    } else {
        f();
    }
    document.addEventListener('turbo:load', f);
})();
</script>
@endif
