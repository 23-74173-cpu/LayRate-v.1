{{--
    <x-notification-toast />
    Floating notification toast for replacing native alert() calls.
    Auto-dismisses after 5 seconds. Styled in Notion's soft + accent pattern.

    Usage:
      showNotification('Message text', 'info');
      Types: 'info' (blue), 'error' (red), 'success' (green), 'warning' (yellow)
--}}

<div
    id="notification-toast"
    class="fixed bottom-6 right-6 z-[100] hidden max-w-sm rounded-lg px-4 py-3 shadow-lg transition-all duration-300 translate-y-4 opacity-0"
    role="alert"
    aria-live="polite"
>
    <div class="flex items-start gap-3">
        <div id="notification-toast-icon" class="w-5 h-5 mt-0.5 shrink-0"></div>
        <p id="notification-toast-message" class="text-sm flex-1"></p>
        <button
            type="button"
            onclick="hideNotification()"
            class="p-1 rounded hover:bg-black/5 transition-colors shrink-0"
            aria-label="Dismiss"
        >
            <i data-lucide="x" class="w-3.5 h-3.5" style="color: inherit;"></i>
        </button>
    </div>
</div>

<script>
(function() {
    let hideTimer = null;

    function getToast() {
        return document.getElementById('notification-toast');
    }

    function getIconEl() {
        return document.getElementById('notification-toast-icon');
    }

    function getMsgEl() {
        return document.getElementById('notification-toast-message');
    }

    // Semantic token triples — same ok/watch/alert/info colors as badges,
    // trend pills and modals (one meaning = one color everywhere).
    var STYLES = {
        info: {
            bg: '#dcebfa',
            border: '#b8d4fe',
            text: 'var(--color-info)',
            icon: '<i data-lucide="info" class="w-5 h-5" style="color: var(--color-navy);"></i>'
        },
        error: {
            bg: '#fbe4e6',
            border: '#f3cdd0',
            text: '#9b1c24',
            icon: '<i data-lucide="alert-circle" class="w-5 h-5" style="color: #9b1c24;"></i>'
        },
        success: {
            bg: '#e8f5ec',
            border: '#cfe8d6',
            text: '#1f6b3a',
            icon: '<i data-lucide="check-circle" class="w-5 h-5" style="color: #1f6b3a;"></i>'
        },
        warning: {
            bg: '#fdf3e0',
            border: '#f3e3bf',
            text: '#8a5a00',
            icon: '<i data-lucide="alert-triangle" class="w-5 h-5" style="color: #8a5a00;"></i>'
        }
    };

    window.showNotification = function(message, type) {
        type = type || 'info';
        var style = STYLES[type] || STYLES.info;
        var toast = getToast();

        getMsgEl().textContent = message;
        getMsgEl().style.color = style.text;
        getIconEl().innerHTML = style.icon;

        toast.style.backgroundColor = style.bg;
        toast.style.border = '1px solid ' + style.border;
        toast.style.color = style.text;

        toast.classList.remove('hidden', 'translate-y-4', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');

        if (window.lucide) lucide.createIcons();

        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(hideNotification, 5000);
    };

    window.hideNotification = function() {
        var toast = getToast();
        toast.classList.add('translate-y-4', 'opacity-0');
        toast.classList.remove('translate-y-0', 'opacity-100');
        setTimeout(function() {
            toast.classList.add('hidden');
        }, 300);
    };
})();
</script>
