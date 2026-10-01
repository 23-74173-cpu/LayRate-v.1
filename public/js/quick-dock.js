/**
 * quick-dock.js — always-visible floating dock (checklist + "+" actions).
 *
 * Delegated + init-guarded (layout scripts re-execute on Turbo visits).
 * Idle look is pure CSS (:hover, :focus-within); JS only tracks the
 * transient states CSS cannot see: touch recency, open panels, scrolling.
 * The dock shell is data-turbo-permanent, so open/closed state survives
 * navigation; badge counts + panel items + fab menu are patched from the
 * incoming page on turbo:before-render (see patchFreshData below).
 */
(function () {
    'use strict';

    if (window.__quickDockWired) return;
    window.__quickDockWired = true;

    var TOUCH_MS = 3000;
    var SCROLL_MS = 150;

    function dock() { return document.getElementById('quickDock'); }
    function panel() { return document.getElementById('dataChecklistPanel'); }
    function fabMenu() { return document.getElementById('dockFabMenu'); }

    var touchTimer = null;
    var scrollTimer = null;

    function isPanelOpen() {
        var p = panel();
        return !!(p && !p.classList.contains('hidden'));
    }

    function isMenuOpen() {
        var m = fabMenu();
        return !!(m && !m.classList.contains('hidden'));
    }

    // data-active forces full opacity (panels/menus open, recent touch).
    function refreshActive() {
        var d = dock();
        if (!d) return;
        var active = isPanelOpen() || isMenuOpen() || d.hasAttribute('data-touch');
        if (active) d.setAttribute('data-active', 'true');
        else d.removeAttribute('data-active');
    }

    function togglePanel(show) {
        var p = panel();
        if (!p) return;
        var btn = document.querySelector('[data-dock-action="checklist"]');
        var willShow = typeof show === 'boolean' ? show : p.classList.contains('hidden');
        if (willShow) closeFabMenu();
        p.classList.toggle('hidden', !willShow);
        if (btn) btn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        if (willShow) {
            if (window.lucide) lucide.createIcons();
            // Focus into the panel; return focus to the button on close.
            var first = p.querySelector('a, button');
            if (first) first.focus({ preventScroll: true });
        } else if (btn && p.contains(document.activeElement)) {
            btn.focus({ preventScroll: true });
        }
        refreshActive();
    }

    function closePanel() {
        var p = panel();
        if (p && !p.classList.contains('hidden')) togglePanel(false);
    }

    // Close the "+" menu (mirrors the shared delegated handler's closed
    // state so both paths agree; the handler itself owns the toggle).
    function closeFabMenu() {
        var m = fabMenu();
        if (!m || m.classList.contains('hidden')) return;
        m.classList.add('hidden');
        var toggle = document.querySelector('#quickDock .fab-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Quick actions');
        }
        var icon = document.querySelector('#quickDock .fab-icon');
        if (icon) icon.style.transform = 'rotate(0deg)';
        refreshActive();
    }

    // Hide the "+" unit when its menu is empty (pages without dock actions).
    function syncFabVisibility() {
        var m = fabMenu();
        if (!m) return;
        var fab = m.closest('.fab');
        var hasItems = m.children.length > 0;
        if (fab) fab.style.display = hasItems ? '' : 'none';
    }

    // Permanent dock + fresh data: patch badge counts, panel items and the
    // page-actions menu from the incoming page before Turbo swaps.
    function patchFreshData(fresh, live) {
        if (!fresh || !live) return;
        var badge = fresh.querySelector('[data-dock-badge]');
        var liveBadge = live.querySelector('[data-dock-badge]');
        if (badge && liveBadge) {
            liveBadge.textContent = badge.textContent;
            var lbl = badge.getAttribute('aria-label');
            if (lbl) liveBadge.setAttribute('aria-label', lbl);
        } else if (!badge && liveBadge) {
            liveBadge.remove();
        } else if (badge && !liveBadge) {
            var host = live.querySelector('[data-dock-action="checklist"]');
            if (host) host.appendChild(badge.cloneNode(true));
        }
        var fItems = fresh.querySelector('[data-dock-panel-items]');
        var lItems = live.querySelector('[data-dock-panel-items]');
        if (fItems && lItems) lItems.innerHTML = fItems.innerHTML;
        var fMenu = fresh.querySelector('#dockFabMenu');
        var lMenu = live.querySelector('#dockFabMenu');
        if (fMenu && lMenu) {
            lMenu.innerHTML = fMenu.innerHTML;
            // Fresh pages start with the menu closed.
            lMenu.classList.add('hidden');
            var lt = live.querySelector('#quickDock .fab-toggle');
            if (lt) {
                lt.setAttribute('aria-expanded', 'false');
                lt.setAttribute('aria-label', 'Quick actions');
            }
            var li = live.querySelector('#quickDock .fab-icon');
            if (li) li.style.transform = 'rotate(0deg)';
            syncFabVisibility();
        }
        if (window.lucide) lucide.createIcons();
    }

    document.addEventListener('turbo:before-render', function (e) {
        var fresh = e.detail && e.detail.newBody && e.detail.newBody.querySelector
            ? e.detail.newBody.querySelector('[data-quick-dock]') : null;
        patchFreshData(fresh, dock());
    });

    // Checklist button + panel close (delegated). Opening one surface
    // closes the other so panel and "+" menu never overlap.
    document.addEventListener('click', function (e) {
        var act = e.target && e.target.closest ? e.target.closest('[data-dock-action="checklist"]') : null;
        if (act) { togglePanel(); return; }
        var cls = e.target && e.target.closest ? e.target.closest('[data-dock-close-panel]') : null;
        if (cls) { togglePanel(false); }
        // "+" menu is toggled by the shared delegated handler in layouts/app
        // (registered earlier, so it runs first); sync opacity right after.
        if (e.target && e.target.closest && e.target.closest('#quickDock .fab-toggle, #dockFabMenu')) {
            closePanel();
            refreshActive();
        }
    });

    // Esc closes the open panel/menu (never the dock itself).
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (isPanelOpen()) { togglePanel(false); return; }
        var m = fabMenu();
        if (m && !m.classList.contains('hidden')) closeFabMenu();
    });

    // Arrow keys move through open menu/panel items.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        var d = dock();
        if (!d || !d.contains(e.target)) return;
        var scope = e.target.closest('#dockFabMenu, #dataChecklistPanel');
        if (!scope) return;
        var items = Array.prototype.filter.call(
            scope.querySelectorAll('a, button'),
            function (el) { return !el.disabled && el.offsetParent !== null; }
        );
        if (!items.length) return;
        e.preventDefault();
        var i = items.indexOf(document.activeElement);
        var next = e.key === 'ArrowDown'
            ? items[(i + 1) % items.length]
            : items[(i - 1 + items.length) % items.length];
        next.focus();
    });

    // Outside pointer closes the open panel/menu (never inside dialogs).
    document.addEventListener('pointerdown', function (e) {
        var d = dock();
        if (!d || (d.contains(e.target))) return;
        if (e.target.closest && e.target.closest('[role="dialog"], .modal-card')) return;
        closePanel();
    }, true);

    // Touch: no hover exists, so a tap forces full opacity briefly, kept
    // while a panel/menu is open, fading ~3s after the last interaction.
    function markTouch() {
        var d = dock();
        if (!d) return;
        d.setAttribute('data-touch', 'true');
        refreshActive();
        if (touchTimer) clearTimeout(touchTimer);
        touchTimer = setTimeout(function () {
            var dd = dock();
            if (dd) dd.removeAttribute('data-touch');
            refreshActive();
        }, TOUCH_MS);
    }
    document.addEventListener('touchstart', function (e) {
        var d = dock();
        if (d && d.contains(e.target)) markTouch();
    }, { passive: true });
    // Any real interaction also counts as touch recency.
    document.addEventListener('click', function (e) {
        var d = dock();
        if (d && d.contains(e.target) && ('ontouchstart' in window)) markTouch();
    });

    // While actively scrolling, recede further; restore shortly after stop.
    // Passive + attribute flip only: no layout work, stays smooth.
    document.addEventListener('scroll', function () {
        var d = dock();
        if (!d) return;
        d.setAttribute('data-scrolling', 'true');
        if (scrollTimer) clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function () {
            var dd = dock();
            if (dd) dd.removeAttribute('data-scrolling');
        }, SCROLL_MS);
    }, { passive: true, capture: true });

    function init() {
        var d = dock();
        if (!d) return;
        refreshActive();
        syncFabVisibility();
        var p = panel();
        if (p) p.classList.add('hidden');
    }
    init();
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:frame-load', init);
})();
