/**
 * kpi-info.js — explanation popovers for every KPI card's info button.
 *
 * Delegated (Turbo-safe, binds once): the info button is caught in the
 * capture phase so its click never triggers card navigation, then toggles a
 * single shared popover. Esc or an outside click closes it, and it also
 * auto-hides shortly after the cursor leaves both the info button and the
 * popover. The popover is
 * clamped into the viewport and flips above the button when there is no
 * room below. Cards with a per-cage breakdown (data-kpi-breakdown) offer it
 * as an explicit action that reuses the existing openKpiModal() when the
 * page provides one.
 */
(function () {
    'use strict';

    if (window.__kpiInfoBound) return;
    window.__kpiInfoBound = true;

    var pop = null;
    var hideTimer = null;

    function cancelHide() {
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
    }

    function scheduleHide() {
        if (!pop || hideTimer) return;
        // Grace period covers the gap between button and popover and lets
        // the cursor pass through without flicker.
        hideTimer = setTimeout(function () {
            hideTimer = null;
            if (!pop) return;
            var btn = pop._btn;
            var overBtn = !!(btn && btn.isConnected && btn.matches && btn.matches(':hover'));
            var overPop = !!(pop.isConnected && pop.matches && pop.matches(':hover'));
            if (!overBtn && !overPop) close();
        }, 250);
    }

    function close() {
        cancelHide();
        if (pop) {
            if (pop.parentNode) pop.parentNode.removeChild(pop);
            pop = null;
        }
        document.removeEventListener('keydown', onKey, true);
    }

    function onKey(e) {
        if (e.key === 'Escape') {
            close();
        }
    }

    function toggle(btn) {
        if (pop && pop._btn === btn) {
            close();
            return;
        }
        close();

        var title = btn.getAttribute('data-info-title') || 'About this metric';
        var text = btn.getAttribute('data-info-text') || '';
        var breakdownKey = btn.getAttribute('data-kpi-breakdown');

        pop = document.createElement('div');
        pop.className = 'kpi-popover';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', title);
        pop._btn = btn;

        var h = document.createElement('div');
        h.className = 'kpi-popover-title';
        h.textContent = title;
        pop.appendChild(h);

        var p = document.createElement('div');
        p.className = 'kpi-popover-text';
        p.textContent = text !== '' ? text : 'Explanation coming soon.';
        pop.appendChild(p);

        if (breakdownKey && typeof openKpiModal === 'function') {
            var more = document.createElement('button');
            more.type = 'button';
            more.className = 'kpi-popover-breakdown';
            more.setAttribute('data-kpi-breakdown-open', breakdownKey);
            more.textContent = 'See per-cage breakdown';
            pop.appendChild(more);
        }

        pop.style.visibility = 'hidden';
        document.body.appendChild(pop);

        var rect = btn.getBoundingClientRect();
        var w = Math.min(280, window.innerWidth - 16);
        pop.style.width = w + 'px';
        var pw = pop.offsetWidth;
        var ph = pop.offsetHeight;

        var left = Math.max(8, Math.min(rect.right - pw, window.innerWidth - pw - 8));
        var top = rect.bottom + 8;
        var flipped = false;
        if (top + ph > window.innerHeight - 8) {
            top = Math.max(8, rect.top - ph - 8);
            flipped = top !== 8 || rect.top - ph - 8 >= 8;
        }
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
        if (flipped) pop.setAttribute('data-flip', 'true');
        pop.style.visibility = '';

        document.addEventListener('keydown', onKey, true);
    }

    // Capture: runs before bubble-phase card navigation, so the info click
    // never navigates away. stopPropagation keeps it contained.
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-kpi-popover]') : null;
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            toggle(btn);
        }
    }, true);

    // Hover tracking: cursor over the popover or any info button keeps it
    // alive; anywhere else starts the auto-hide grace timer.
    document.addEventListener('mouseover', function (e) {
        if (!pop) return;
        var t = e.target;
        var ui = t && t.closest ? t.closest('.kpi-popover, [data-kpi-popover]') : null;
        if (ui) cancelHide();
        else scheduleHide();
    });

    // Bubble: breakdown action + outside-to-close.
    document.addEventListener('click', function (e) {
        if (!pop) return;
        var opener = e.target && e.target.closest ? e.target.closest('[data-kpi-breakdown-open]') : null;
        if (opener) {
            var key = opener.getAttribute('data-kpi-breakdown-open');
            close();
            if (typeof openKpiModal === 'function') openKpiModal(key);
            return;
        }
        if (!e.target.closest || !e.target.closest('.kpi-popover')) {
            close();
        }
    });
})();
