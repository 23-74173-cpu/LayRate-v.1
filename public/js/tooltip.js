/**
 * tooltip.js — branded tooltips replacing native title="" bubbles.
 *
 * Auto-upgrades any [title] or [data-tooltip] element: on hover or keyboard
 * focus the native attribute is consumed (so the default bubble never shows)
 * and a navy tooltip bubble renders instead. Delegated + Turbo-safe.
 *
 * Accessibility: if the element has no accessible name (no aria-label and
 * no text content), the tooltip text becomes its aria-label. The bubble is
 * linked via aria-describedby while visible and dismisses with Esc.
 */
(function () {
    'use strict';

    var bubble = null;
    var describedBy = null;
    var currentEl = null;

    function ensureBubble() {
        if (bubble) return bubble;
        bubble = document.createElement('div');
        bubble.className = 'tooltip-bubble';
        bubble.setAttribute('role', 'tooltip');
        bubble.id = 'app-tooltip';
        bubble.style.display = 'none';
        document.body.appendChild(bubble);
        return bubble;
    }

    function textFor(el) {
        if (el.hasAttribute('data-tooltip-text')) return el.getAttribute('data-tooltip-text');
        var t = el.getAttribute('data-tooltip') || el.getAttribute('title') || '';
        return t;
    }

    function consume(el) {
        var t = textFor(el);
        if (!t) return '';
        // Remember and remove the native title so the default bubble never fires.
        if (el.hasAttribute('title')) {
            el.setAttribute('data-tooltip-text', el.getAttribute('title'));
            el.removeAttribute('title');
            t = el.getAttribute('data-tooltip-text');
        }
        // If the element has no other accessible name, the tooltip becomes it.
        var hasName = el.hasAttribute('aria-label') || (el.textContent || '').trim() !== '';
        if (!hasName && !el.hasAttribute('aria-label')) {
            el.setAttribute('aria-label', t);
        }
        return t;
    }

    function show(el) {
        var text = consume(el);
        if (!text) return;
        hide(false);
        currentEl = el;
        var b = ensureBubble();
        b.textContent = text;
        b.style.display = 'block';
        describedBy = el.getAttribute('aria-describedby');
        var ids = (describedBy || '').split(/\s+/).filter(Boolean);
        if (ids.indexOf('app-tooltip') === -1) {
            ids.push('app-tooltip');
            el.setAttribute('aria-describedby', ids.join(' '));
        }
        // Position above, centered; flip below when there is no room; clamp sideways.
        var r = el.getBoundingClientRect();
        b.style.visibility = 'hidden';
        b.style.left = '0px';
        b.style.top = '0px';
        var bw = b.offsetWidth;
        var bh = b.offsetHeight;
        var left = Math.min(Math.max(8, r.left + r.width / 2 - bw / 2), window.innerWidth - bw - 8);
        var top = r.top - bh - 8 + window.scrollY;
        var below = false;
        if (top < window.scrollY + 4) {
            top = r.bottom + 8 + window.scrollY;
            below = true;
        }
        b.style.left = left + 'px';
        b.style.top = top + 'px';
        b.setAttribute('data-below', below ? 'true' : 'false');
        b.style.visibility = '';
    }

    function hide(restore) {
        if (restore !== false && currentEl) {
            if (describedBy) currentEl.setAttribute('aria-describedby', describedBy);
            else currentEl.removeAttribute('aria-describedby');
        }
        describedBy = null;
        currentEl = null;
        if (bubble) bubble.style.display = 'none';
    }

    document.addEventListener('mouseover', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-tooltip],[title]') : null;
        if (el) show(el);
        else if (!e.relatedTarget || !e.relatedTarget.closest || !e.relatedTarget.closest('.tooltip-bubble')) hide();
    }, true);
    document.addEventListener('mouseout', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-tooltip],[title]') : null;
        if (el && (!e.relatedTarget || !e.relatedTarget.closest || !e.relatedTarget.closest('[data-tooltip],[title]'))) hide();
    }, true);
    document.addEventListener('focusin', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-tooltip],[title]') : null;
        if (el) show(el);
    });
    document.addEventListener('focusout', function () { hide(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hide();
    });
    document.addEventListener('scroll', function () { hide(); }, true);
    // Turbo navigation replaces the page while the bubble (a body-level node)
    // would otherwise survive with a stale position — always dismiss.
    document.addEventListener('turbo:before-visit', function () { hide(); });
    document.addEventListener('turbo:render', function () { hide(); });
    document.addEventListener('turbo:frame-load', function () { hide(); });
})();
