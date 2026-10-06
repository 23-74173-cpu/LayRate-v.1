/**
 * filter-ui.js — one consistent filter system for every record table.
 *
 * Delegated + Turbo-safe (binds once): drives every [data-filter-bar]
 * (x-filter-bar) — auto-apply on change for selects/dates/presets on sm+
 * screens, 300 ms debounced text, Apply-only inside the phone bottom sheet.
 * Results render in the bar's Turbo frame (in-flight frame loads abort when
 * src changes, so no custom cancellation is needed); page resets to 1 on
 * every change; the overflow-redirect middleware still applies server-side.
 *
 * Phone sheet: the same field nodes are reparented into the sheet on
 * <sm screens (single set of controls, state can never diverge). Opening
 * snapshots the form so dismissing without Apply restores it. Focus is
 * trapped, Esc/backdrop close, background scroll locks, safe-area insets
 * come from the sheet markup, and the sheet (z-50) sits above the
 * floating dock (z-40).
 */
(function () {
    'use strict';

    if (window.__filterUiBound) return;
    window.__filterUiBound = true;

    var phoneMQ = window.matchMedia ? window.matchMedia('(max-width: 639.98px)') : null;
    function isPhone() { return !!(phoneMQ && phoneMQ.matches); }

    function bars() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-filter-bar]'));
    }

    function frameFor(bar) {
        var id = bar && bar.getAttribute('data-frame');
        return id ? document.getElementById(id) : null;
    }

    /* ── Phone placement: one set of fields, moved in/out of the sheet ── */

    function placeBar(bar) {
        var fields = bar.querySelector('[data-filter-fields]');
        var body = bar.querySelector('[data-filter-sheet-body]');
        if (!fields || !body) return;
        if (fields.__homeParent === undefined) {
            fields.__homeParent = fields.parentNode;
            fields.__homeNext = fields.nextSibling;
        }
        if (isPhone()) {
            if (fields.parentNode !== body) body.appendChild(fields);
        } else {
            if (fields.parentNode !== fields.__homeParent) {
                fields.__homeParent.insertBefore(fields, fields.__homeNext);
            }
            closeSheet(bar, false);
        }
    }

    function placeAll() { bars().forEach(placeBar); }

    /* ── State helpers ── */

    function controls(bar) {
        return Array.prototype.slice.call(
            bar.querySelectorAll('select[name], input[name][type="date"], input[name][type="search"], input[name][type="text"], input[name][type="checkbox"]')
        );
    }

    function isActiveControl(el) {
        if (el.type === 'checkbox') return el.checked;
        return (el.value || '') !== '';
    }

    function activeCount(bar) {
        var n = 0;
        controls(bar).forEach(function (el) { if (isActiveControl(el)) n++; });
        return n;
    }

    function updateCount(bar) {
        var badge = bar.querySelector('[data-filter-count]');
        if (!badge) return;
        var n = activeCount(bar);
        badge.textContent = String(n);
        badge.style.display = n > 0 ? 'inline-flex' : 'none';
        var openBtn = bar.querySelector('[data-filter-open]');
        if (openBtn) openBtn.setAttribute('aria-label', n > 0 ? 'Filters, ' + n + ' active' : 'Filters');
    }

    function updateAllCounts() { bars().forEach(updateCount); }

    function snapshot(bar) {
        var fd = new FormData(bar);
        var out = [];
        fd.forEach(function (v, k) { out.push([k, v]); });
        return out;
    }

    function restore(bar, snap) {
        controls(bar).forEach(function (el) {
            if (el.type === 'checkbox') { el.checked = false; return; }
            el.value = '';
        });
        (snap || []).forEach(function (pair) {
            var list = bar.querySelectorAll('[name="' + pair[0] + '"]');
            list.forEach(function (el) {
                if (el.type === 'checkbox') { if (el.value === pair[1]) el.checked = true; }
                else el.value = pair[1];
            });
        });
        updateCount(bar);
        updatePresetUI(bar);
    }

    function dateError(bar, show) {
        var err = bar.querySelector('[data-filter-date-error]');
        if (err) err.classList.toggle('hidden', !show);
        var range = bar.querySelector('[data-filter-date-range]');
        if (range) range.setAttribute('aria-invalid', show ? 'true' : 'false');
    }

    function datesValid(bar) {
        var range = bar.querySelector('[data-filter-date-range]');
        if (!range) { dateError(bar, false); return true; }
        var from = range.querySelector('input[name]');
        var to = range.querySelectorAll('input[name]')[1];
        var ok = !(from && to && from.value && to.value && from.value > to.value);
        dateError(bar, !ok);
        return ok;
    }

    /* ── Presets (anchored on the server-rendered reporting today: no tz drift) ── */

    function addDays(ymd, delta) {
        var parts = String(ymd).split('-');
        var d = new Date(Date.UTC(+parts[0], +parts[1] - 1, +parts[2]));
        d.setUTCDate(d.getUTCDate() + delta);
        function p(n) { return (n < 10 ? '0' : '') + n; }
        return d.getUTCFullYear() + '-' + p(d.getUTCMonth() + 1) + '-' + p(d.getUTCDate());
    }

    function firstOfMonth(ymd) { return String(ymd).slice(0, 7) + '-01'; }

    function applyPreset(bar, preset) {
        var range = bar.querySelector('[data-filter-date-range]');
        if (!range) return;
        var today = range.getAttribute('data-today') || '';
        if (!today) return;
        var inputs = range.querySelectorAll('input[name]');
        if (inputs.length < 2 || !inputs[0].name || !inputs[1].name) return;
        var map = {
            today: [today, today],
            '7days': [addDays(today, -6), today],
            '30days': [addDays(today, -29), today],
            month: [firstOfMonth(today), today]
        };
        var pair = map[preset];
        if (!pair) return;
        inputs[0].value = pair[0];
        inputs[1].value = pair[1];
        // date-mdy.js overrides the native value setter, so the visible
        // mm/dd/yyyy box syncs itself from this ISO assignment. No event is
        // fired here on purpose: a synthetic 'change' would double-apply.
        updatePresetUI(bar);
        updateCount(bar);
        if (sheetOpen(bar)) return; // phone sheet waits for Apply
        applyBar(bar);
    }

    function updatePresetUI(bar) {
        var range = bar.querySelector('[data-filter-date-range]');
        if (!range) return;
        var today = range.getAttribute('data-today') || '';
        var inputs = range.querySelectorAll('input[name]');
        var cur = inputs.length >= 2 ? (inputs[0].value || '') + '|' + (inputs[1].value || '') : '|';
        var expect = {
            today: today + '|' + today,
            '7days': addDays(today, -6) + '|' + today,
            '30days': addDays(today, -29) + '|' + today,
            month: firstOfMonth(today) + '|' + today
        };
        range.querySelectorAll('[data-preset]').forEach(function (btn) {
            var on = today !== '' && expect[btn.getAttribute('data-preset')] === cur;
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.style.backgroundColor = on ? 'var(--color-navy)' : '';
            btn.style.color = on ? '#ffffff' : '';
            btn.style.borderColor = on ? 'var(--color-navy)' : '';
        });
    }

    function updateAllPresetUI() { bars().forEach(updatePresetUI); }

    /* ── Apply / reset ── */

    function collectParams(bar) {
        var params = new URLSearchParams(new FormData(bar));
        // Drop empties and any stale page: every filter change resets to 1.
        Array.prototype.slice.call(params.keys()).forEach(function (k) {
            if (k === 'page' || params.get(k) === '') params.delete(k);
        });
        return params;
    }

    function applyBar(bar) {
        if (!datesValid(bar)) return;
        var frame = frameFor(bar);
        if (!frame) return;
        var action = bar.getAttribute('data-action') || window.location.pathname;
        var qs = collectParams(bar).toString();
        frame.setAttribute('aria-busy', 'true');
        frame.classList.add('is-loading');
        frame.setAttribute('src', action + (qs ? '?' + qs : ''));
        syncPageUrl(bar, qs);
        document.dispatchEvent(new CustomEvent('filter:applied', {
            detail: { formId: bar.id, frameId: frame.id, params: qs }
        }));
    }

    // Opt-in per bar (data-sync-url): mirror canonical filter params into the
    // page URL with replaceState — never pushState, so Back never steps
    // through filter states. No listeners involved, so nothing can stack
    // across Turbo navigations.
    function syncPageUrl(bar, qs) {
        if (!bar.hasAttribute('data-sync-url')) return;
        try {
            var url = new URL(window.location.href);
            url.search = qs || '';
            window.history.replaceState({}, '', url);
        } catch (err) { /* non-HTTP context: page URL simply stays put */ }
    }

    function resetBar(bar, andApply) {
        bar.reset();
        updatePresetUI(bar);
        updateCount(bar);
        dateError(bar, false);
        if (andApply !== false) applyBar(bar);
    }

    /* ── Sheet ── */

    function sheet(bar) { return bar.querySelector('[data-filter-sheet]'); }
    function sheetOpen(bar) {
        var s = sheet(bar);
        return !!(s && !s.hasAttribute('hidden'));
    }
    function anySheetOpen() { return bars().some(sheetOpen); }

    function openSheet(bar) {
        if (!isPhone()) return;
        placeBar(bar);
        bar.__snapshot = snapshot(bar);
        var s = sheet(bar);
        if (!s) return;
        s.removeAttribute('hidden');
        document.documentElement.style.overflow = 'hidden';
        // Skip date-mdy's hidden native inputs (tabindex -1, 1px): focus the
        // visible control instead.
        var first = s.querySelector('select:not(.mdy-native), input:not(.mdy-native), button[data-filter-apply]');
        if (first) first.focus();
    }

    function closeSheet(bar, restoreSnap) {
        var s = sheet(bar);
        if (!s || s.hasAttribute('hidden')) return;
        if (restoreSnap !== false && bar.__snapshot) restore(bar, bar.__snapshot);
        s.setAttribute('hidden', '');
        if (!anySheetOpen()) document.documentElement.style.overflow = '';
        var openBtn = bar.querySelector('[data-filter-open]');
        if (openBtn && restoreSnap !== false) openBtn.focus();
    }

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target && e.target.closest) {
            var link = e.target.closest('[data-filter-link]');
            if (link) {
                e.preventDefault();
                link.click();
                return;
            }
        }
        if (e.key === 'Escape') {
            var open = bars().filter(sheetOpen);
            if (open.length) { e.preventDefault(); open.forEach(function (b) { closeSheet(b, true); }); }
            return;
        }
        // Focus trap while a sheet is open.
        if (e.key !== 'Tab') return;
        var openBar = bars().filter(sheetOpen)[0];
        if (!openBar || !isPhone()) return;
        var s = sheet(openBar);
        var focusables = Array.prototype.slice.call(
            s.querySelectorAll('button, select, input:not(.mdy-native), [tabindex]')
        ).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    /* ── Wiring (all delegated, bound once) ── */

    var debounceTimer = null;

    document.addEventListener('change', function (e) {
        var bar = e.target && e.target.closest ? e.target.closest('[data-filter-bar]') : null;
        if (!bar) return;
        if (e.target.matches('select[name], input[name][type="date"], input[name][type="checkbox"]')) {
            updateCount(bar);
            updatePresetUI(bar);
            if (sheetOpen(bar)) return; // phone sheet waits for Apply
            applyBar(bar);
        }
        if (e.target.matches('[data-preset]')) return; // buttons, not inputs
    });

    document.addEventListener('input', function (e) {
        var bar = e.target && e.target.closest ? e.target.closest('[data-filter-bar]') : null;
        if (!bar || !e.target.matches('[data-filter-debounce]')) return;
        updateCount(bar);
        if (sheetOpen(bar)) return;
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { applyBar(bar); }, 300);
    });

    document.addEventListener('click', function (e) {
        var t = e.target && e.target.closest ? e.target : null;

        var presetBtn = t && t.closest ? t.closest('[data-preset]') : null;
        if (presetBtn) {
            var bar = presetBtn.closest('[data-filter-bar]');
            if (bar) { e.preventDefault(); applyPreset(bar, presetBtn.getAttribute('data-preset')); }
            return;
        }

        var openBtn = t && t.closest ? t.closest('[data-filter-open]') : null;
        if (openBtn) {
            var ob = openBtn.closest('[data-filter-bar]');
            if (ob) openSheet(ob);
            return;
        }

        if (t && t.closest && t.closest('[data-filter-sheet-backdrop]')) {
            var bb = t.closest('[data-filter-bar]');
            if (bb) closeSheet(bb, true);
            return;
        }

        var closeBtn = t && t.closest ? t.closest('[data-filter-sheet-close]') : null;
        if (closeBtn) {
            var cb = closeBtn.closest('[data-filter-bar]');
            if (cb) closeSheet(cb, true);
            return;
        }

        var applyBtn = t && t.closest ? t.closest('[data-filter-apply]') : null;
        if (applyBtn) {
            var ab = applyBtn.closest('[data-filter-bar]');
            if (ab && datesValid(ab)) { applyBar(ab); closeSheet(ab, false); }
            return;
        }

        var clearBtn = t && t.closest ? t.closest('[data-filter-clear]') : null;
        if (clearBtn) {
            var clb = clearBtn.closest('[data-filter-bar]');
            if (clb) { resetBar(clb, true); closeSheet(clb, false); }
            return;
        }

        var resetBtn = t && t.closest ? t.closest('[data-filter-reset]') : null;
        if (resetBtn) {
            var rb = resetBtn.closest('[data-filter-bar]');
            if (rb) resetBar(rb, true);
            return;
        }

        var clearAll = t && t.closest ? t.closest('[data-filter-clear-all]') : null;
        if (clearAll) {
            var frame = t.closest('turbo-frame');
            var ab2 = frame ? document.querySelector('[data-filter-bar][data-frame="' + frame.id + '"]') : bars()[0];
            if (ab2) resetBar(ab2, true);
            return;
        }

        var chip = t && t.closest ? t.closest('[data-chip-clear]') : null;
        if (chip) {
            var frame2 = chip.closest('turbo-frame');
            var bar2 = frame2 ? document.querySelector('[data-filter-bar][data-frame="' + frame2.id + '"]') : bars()[0];
            if (!bar2) return;
            clearParam(bar2, chip.getAttribute('data-chip-clear'));
            applyBar(bar2);
            return;
        }

        var link = t && t.closest ? t.closest('[data-filter-link]') : null;
        if (link) {
            e.preventDefault();
            var frame3 = link.closest('turbo-frame');
            var bar3 = frame3 ? document.querySelector('[data-filter-bar][data-frame="' + frame3.id + '"]') : bars()[0];
            if (!bar3) return;
            if (setParam(bar3, link.getAttribute('data-param'), link.getAttribute('data-value'))) {
                updateCount(bar3);
                updatePresetUI(bar3);
                applyBar(bar3);
            }
        }
    });

    function clearParam(bar, param) {
        if (!param) return;
        bar.querySelectorAll('[name="' + param + '"], [name="' + param + '[]"]').forEach(function (el) {
            if (el.type === 'checkbox') el.checked = false;
            else el.value = '';
        });
        updateCount(bar);
        updatePresetUI(bar);
    }

    function setParam(bar, param, value) {
        if (!param) return false;
        var sel = bar.querySelector('select[name="' + param + '"]');
        if (sel) {
            var has = Array.prototype.some.call(sel.options, function (o) { return o.value === value; });
            if (!has) return false;
            sel.value = value;
            return true;
        }
        var input = bar.querySelector('input[name="' + param + '"]');
        if (input && (input.type === 'text' || input.type === 'search' || input.type === 'date')) {
            input.value = value || '';
            return true;
        }
        return false;
    }

    document.addEventListener('submit', function (e) {
        var bar = e.target && e.target.closest ? e.target.closest('[data-filter-bar]') : null;
        if (!bar) return;
        e.preventDefault(); // Enter key applies; never a full navigation
        if (sheetOpen(bar)) return;
        applyBar(bar);
    });

    // Frame swaps: drop the loading state, refresh counts/badges, and let
    // page glue (selection counts, QR "matching" totals) re-sync.
    document.addEventListener('turbo:frame-load', function (e) {
        var frame = e.target;
        if (!frame || !frame.id) return;
        frame.removeAttribute('aria-busy');
        frame.classList.remove('is-loading');
        updateAllCounts();
        updateAllPresetUI();
        document.dispatchEvent(new CustomEvent('filter:frame-loaded', { detail: { frameId: frame.id } }));
    });

    if (phoneMQ && phoneMQ.addEventListener) phoneMQ.addEventListener('change', placeAll);
    else if (phoneMQ && phoneMQ.addListener) phoneMQ.addListener(placeAll);

    document.addEventListener('turbo:load', function () { placeAll(); updateAllCounts(); updateAllPresetUI(); });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { placeAll(); updateAllCounts(); updateAllPresetUI(); });
    } else {
        placeAll();
        updateAllCounts();
        updateAllPresetUI();
    }
})();
