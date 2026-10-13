/* Keep walkthrough instructions outside the active modal, including on phones. */
(function () {
    if (window.LayRateWalkthroughNotes) return;
    var saved = null;
    function release() {
        if (!saved) return;
        saved.card.style.maxHeight = saved.maxHeight;
        saved.card.style.overflowY = saved.overflowY;
        saved.modal.style.alignItems = saved.alignItems;
        saved.modal.style.paddingTop = saved.paddingTop;
        saved.modal.style.paddingBottom = saved.paddingBottom;
        saved = null;
    }
    function cardFor(target) {
        var modal = target && target.closest('[role="dialog"], #cageInfoPopup');
        var card = modal && (modal.querySelector('#cageInfoCard') || modal.querySelector('.relative'));
        return card ? { modal: modal, card: card } : null;
    }
    function prepare(tip, target) {
        var pair = cardFor(target);
        if (saved && (!pair || saved.card !== pair.card)) release();
        tip.style.boxSizing = 'border-box';
        tip.style.width = '340px';
        tip.style.maxWidth = 'calc(100vw - 24px)';
        tip.style.overflowY = 'auto';
        tip.style.pointerEvents = 'auto';
        tip.style.maxHeight = 'calc(100dvh - 90px)';
        if (!pair) return;
        if (saved) release();
        var rect = pair.card.getBoundingClientRect();
        if (Math.max(rect.left - 24, innerWidth - rect.right - 24) >= 220) return;
        // Reserve a separate bottom area rather than putting notes over inputs.
        var noteHeight = Math.min(220, Math.floor(innerHeight * .32));
        tip.style.maxHeight = noteHeight + 'px';
        saved = {
            modal: pair.modal, card: pair.card,
            maxHeight: pair.card.style.maxHeight, overflowY: pair.card.style.overflowY,
            alignItems: pair.modal.style.alignItems, paddingTop: pair.modal.style.paddingTop,
            paddingBottom: pair.modal.style.paddingBottom
        };
        pair.modal.style.alignItems = 'flex-start';
        pair.modal.style.paddingTop = '12px';
        pair.modal.style.paddingBottom = (noteHeight + 36) + 'px';
        pair.card.style.maxHeight = Math.max(100, innerHeight - noteHeight - 48) + 'px';
        pair.card.style.overflowY = 'auto';
    }
    function place(tip, target, bounds) {
        var menu = target && target.closest('#dockFabMenu');
        if (menu) {
            var menuRect = menu.getBoundingClientRect();
            var room = menuRect.left - 24;
            if (room >= 220) {
                tip.style.width = Math.min(340, room) + 'px';
                tip.style.left = (menuRect.left - tip.offsetWidth - 12) + 'px';
                tip.style.top = Math.max(12, Math.min(menuRect.top, innerHeight - tip.offsetHeight - 12)) + 'px';
            } else {
                // On phones, keep all notes above the entire actions menu.
                tip.style.maxHeight = Math.max(40, menuRect.top - 24) + 'px';
                tip.style.left = Math.max(12, (innerWidth - tip.offsetWidth) / 2) + 'px';
                tip.style.top = Math.max(12, menuRect.top - tip.offsetHeight - 12) + 'px';
            }
            return;
        }
        var pair = cardFor(target);
        if (pair) {
            var rect = pair.card.getBoundingClientRect();
            var rightRoom = innerWidth - rect.right - 24;
            var leftRoom = rect.left - 24;
            if (!saved && Math.max(rightRoom, leftRoom) >= 220) {
                var room = Math.max(rightRoom, leftRoom);
                tip.style.width = Math.min(340, room) + 'px';
                tip.style.left = (rightRoom >= leftRoom ? rect.right + 12 : rect.left - tip.offsetWidth - 12) + 'px';
                tip.style.top = Math.max(12, Math.min(rect.top, innerHeight - tip.offsetHeight - 12)) + 'px';
            } else {
                tip.style.left = Math.max(12, (innerWidth - tip.offsetWidth) / 2) + 'px';
                tip.style.top = (innerHeight - tip.offsetHeight - 12) + 'px';
            }
            return;
        }
        var top = bounds.bottom + 14;
        if (top + tip.offsetHeight > innerHeight - 12) top = bounds.top - tip.offsetHeight - 14;
        tip.style.left = Math.max(12, Math.min(bounds.left, innerWidth - tip.offsetWidth - 12)) + 'px';
        tip.style.top = Math.max(12, Math.min(top, innerHeight - tip.offsetHeight - 12)) + 'px';
    }
    window.LayRateWalkthroughNotes = { prepare: prepare, place: place, release: release };
    document.addEventListener('turbo:before-cache', release);
    document.addEventListener('turbo:before-visit', release);
})();
