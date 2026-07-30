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
    var f = function() {
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
