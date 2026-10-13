/* User Manual walkthrough 5: alert thresholds and environmental readings. */
(function () {
    'use strict';
    var storageKey = 'wt5_state';
    var started = new URLSearchParams(window.location.search).get('walkthrough5') === '1';
    if (!started && !sessionStorage.getItem(storageKey)) return;
    if (!document.getElementById('environment-live-data')) return;
    if (window.__environmentWalkthroughCleanup) window.__environmentWalkthroughCleanup();
    var state;
    try { state = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (e) {}
    state = state || { step: 'setupMenu', cageIds: [], logDate: null };
    var active = true;
    var lastTarget = null;
    var listeners = [];
    var highlightedRows = [];
    var timer;
    var overlay = document.createElement('div');
    overlay.id = 'wt5Overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:90;pointer-events:none;';
    overlay.innerHTML = '<div data-wt5-spot style="position:fixed;border:3px solid var(--color-navy);border-radius:12px;pointer-events:none;"></div>'
        + [0, 1, 2, 3].map(function () { return '<div data-wt5-dim style="position:fixed;background:rgba(15,20,35,.55);pointer-events:auto;"></div>'; }).join('')
        + '<div data-wt5-tip role="status" style="position:fixed;z-index:92;width:340px;max-width:calc(100vw - 24px);max-height:calc(100vh - 90px);overflow:auto;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px;box-shadow:0 20px 50px rgba(0,0,0,.3);pointer-events:auto;">'
        + '<div data-wt5-label style="font-size:11px;font-weight:700;color:var(--color-navy);margin-bottom:6px;"></div>'
        + '<div data-wt5-text style="font-size:14px;line-height:1.5;color:#1f1f1f;"></div>'
        + '<button data-wt5-next type="button" style="margin-top:12px;padding:8px 16px;background:var(--color-navy);color:white;border-radius:8px;pointer-events:auto;">Next</button></div>'
        + '<button data-wt5-end type="button" style="position:fixed;top:16px;right:16px;z-index:93;background:#fff;border:1px solid #e6e6e6;padding:8px 14px;border-radius:999px;pointer-events:auto;">End tutorial</button>';
    document.body.appendChild(overlay);
    var spot = overlay.querySelector('[data-wt5-spot]');
    var dims = Array.from(overlay.querySelectorAll('[data-wt5-dim]'));
    var tip = overlay.querySelector('[data-wt5-tip]');
    var label = overlay.querySelector('[data-wt5-label]');
    var text = overlay.querySelector('[data-wt5-text]');
    var next = overlay.querySelector('[data-wt5-next]');
    function query(selector) { return document.querySelector(selector); }
    function visibleModal(id) {
        var modal = document.getElementById(id);
        return modal && window.getComputedStyle(modal).display !== 'none' ? modal : null;
    }
    function modalCard(id) { var modal = visibleModal(id); return modal && modal.querySelector('.relative'); }
    function action(name) { return query('#dockFabMenu [data-env-action="' + name + '"]'); }
    function menuOpen() { var menu = document.getElementById('dockFabMenu'); return menu && !menu.classList.contains('hidden'); }
    function persist() { sessionStorage.setItem(storageKey, JSON.stringify(state)); }
    function setStep(name) { state.step = name; lastTarget = null; persist(); render(); }
    function formValid(id) {
        var form = document.getElementById(id);
        var submit = form && form.querySelector('[type="submit"]');
        return !!(form && form.checkValidity() && submit && !submit.disabled);
    }
    function thresholdsValid() {
        if (!formValid('threshold-form')) return false;
        var form = document.getElementById('threshold-form');
        function value(name) { return Number(form.elements[name].value); }
        return value('temp_min') <= value('temp_max') && value('hum_min') <= value('hum_max');
    }
    function readingValid() {
        var cage = document.getElementById('envManualCage');
        // All Cages must represent at least one actual cage.
        return formValid('envManualForm') && cage && (cage.value !== 'all' || cage.options.length > 2);
    }
    function savedRows() {
        return Array.from(document.querySelectorAll('[data-env-log-date]')).filter(function(row) {
            return row.dataset.envLogDate === state.logDate && state.cageIds.map(String).includes(row.dataset.envLogCage);
        });
    }
    var steps = {
        setupMenu: {
            text: '<strong>Set up environment alerts.</strong><br>Click the <em>+</em> Quick Actions button to configure temperature and humidity thresholds.',
            target: function() { return query('#quickDock .fab-toggle'); }, done: menuOpen, to: 'setupAction'
        },
        setupAction: {
            text: '<strong>Configure Thresholds.</strong><br>Click <em>Configure Thresholds</em> to set the ranges used by environment alerts.',
            target: function() { return menuOpen() ? action('thresholds') : query('#quickDock .fab-toggle'); },
            done: function() { return !!visibleModal('envThresholdsModal'); }, to: 'thresholdFields'
        },
        thresholdFields: {
            text: '<strong>Set the alert ranges.</strong><br><em>Temp Min / Max (?C)</em>: lower and upper temperature limits.<br><em>Humidity Min / Max (%)</em>: lower and upper humidity limits.<br>Minimum must not exceed maximum. Temperature limits must be 0?50?C; humidity limits 0?100%. The reference hints explain how your values compare with the recommended ranges.<br><br>Review all four values, then tap <em>Next</em>.',
            target: function() { return modalCard('envThresholdsModal'); }, manual: true, interactive: true,
            valid: thresholdsValid, to: 'thresholdSave'
        },
        thresholdSave: {
            text: '<strong>Save the configuration.</strong><br>Click <em>Save Thresholds</em>. Continue after the settings are saved successfully.',
            target: function() { return query('#save-thresholds-btn'); }
        },
        readingMenu: {
            text: '<strong>Create an environment reading.</strong><br>Click the <em>+</em> Quick Actions button to record temperature and humidity manually.',
            target: function() { return query('#quickDock .fab-toggle'); }, done: menuOpen, to: 'readingAction'
        },
        readingAction: {
            text: '<strong>Manual Entry.</strong><br>Click <em>Manual Entry</em> to log a reading for one cage or all cages.',
            target: function() { return menuOpen() ? action('manual') : query('#quickDock .fab-toggle'); },
            done: function() { return !!visibleModal('envManualModal'); }, to: 'readingFields'
        },
        readingFields: {
            text: '<strong>Fill in the reading.</strong><br><em>Cage</em>: choose one cage, or <em>All Cages</em> to use the same reading for every cage.<br><em>Temperature (?C)</em>: measured temperature, from -10 to 60?C.<br><em>Humidity (%)</em>: measured relative humidity, from 0 to 100%.<br>The reading applies to the current reporting day. It replaces any previous manual reading for that cage and day and becomes the daily average shown in Log History.<br><br>Complete the fields, then tap <em>Next</em>.',
            target: function() { return modalCard('envManualModal'); }, manual: true, interactive: true,
            valid: readingValid, to: 'readingSave'
        },
        readingSave: {
            text: '<strong>Save the reading.</strong><br>Click <em>Save Reading</em>. The walkthrough waits for a successful save; invalid values or a failed request keep you here.',
            target: function() { return query('#envManualForm [type="submit"]'); }
        },
        historyTab: {
            text: '<strong>Review Log History.</strong><br>Click <em>Log History</em> to view the saved daily readings.',
            target: function() { return query('button[onclick="switchEnvTab(\'logs\')"]'); },
            done: function() { var panel = document.getElementById('panelLogHistory'); return panel && !panel.classList.contains('hidden') && savedRows().length > 0; }, to: 'records'
        },
        records: {
            text: '<strong>Your saved environment readings.</strong><br>The highlighted rows show the date, cage, temperature, humidity, reading count, and status based on your thresholds. For <em>All Cages</em>, the same reading appears for each cage.<br>Manual readings take priority over sensor averages for that reporting day. Tap <em>Done</em> to finish.',
            target: savedRows, manual: true, to: 'done'
        }
    };
    function render() {
        if (!active) return;
        var step = steps[state.step];
        var target = step && step.target();
        var targets = Array.isArray(target) ? target : target ? [target] : [];
        targets = targets.filter(function (el) { return el && el.getClientRects().length; });
        var hasTarget = targets.length > 0;
        // Keep the instructions and gray overlay visible during AJAX refreshes.
        if (!hasTarget) targets = [document.getElementById('environment-live-data')];
        if (targets[0] && lastTarget !== targets[0] && hasTarget) {
            targets[0].scrollIntoView({ block: 'nearest', inline: 'nearest' });
            lastTarget = targets[0];
        }
        if (window.LayRateWalkthroughNotes) LayRateWalkthroughNotes.prepare(tip, targets[0]);
        var rects = targets.filter(Boolean).map(function (el) { return el.getBoundingClientRect(); });
        var left = rects.length ? Math.min.apply(null, rects.map(function (r) { return r.left; })) - 6 : 12;
        var top = rects.length ? Math.min.apply(null, rects.map(function (r) { return r.top; })) - 6 : 60;
        var right = rects.length ? Math.max.apply(null, rects.map(function (r) { return r.right; })) + 6 : innerWidth - 12;
        var bottom = rects.length ? Math.max.apply(null, rects.map(function (r) { return r.bottom; })) + 6 : top;
        spot.style.display = hasTarget ? 'block' : 'none';
        Object.assign(spot.style, { left: left + 'px', top: top + 'px', width: (right - left) + 'px', height: (bottom - top) + 'px' });
        var blocked = !hasTarget || (step && step.manual && !step.interactive);
        var bars = blocked ? [[0, 0, innerWidth, innerHeight], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]]
            : [[0, 0, innerWidth, Math.max(0, top)], [0, bottom, innerWidth, Math.max(0, innerHeight - bottom)], [0, top, Math.max(0, left), Math.max(0, bottom - top)], [right, top, Math.max(0, innerWidth - right), Math.max(0, bottom - top)]];
        bars.forEach(function (bar, i) { Object.assign(dims[i].style, { left: bar[0] + 'px', top: bar[1] + 'px', width: bar[2] + 'px', height: bar[3] + 'px' }); });
        label.textContent = step ? 'Environment walkthrough · ' + (Object.keys(steps).indexOf(state.step) + 1) + ' of ' + Object.keys(steps).length : 'Environment walkthrough';
        text.innerHTML = step ? (typeof step.text === 'function' ? step.text() : step.text) : 'Loading environment…';
        if (!hasTarget && step) text.innerHTML += '<br><br>Waiting for the page to finish loading. You can end the tutorial at any time.';
        next.style.display = step && step.manual ? 'inline-block' : 'none';
        next.disabled = !hasTarget || !!(step && step.valid && !step.valid());
        next.style.opacity = next.disabled ? '.5' : '1';
        next.style.cursor = next.disabled ? 'not-allowed' : 'pointer';
        next.textContent = state.step === 'records' ? 'Done' : 'Next';
        if (window.LayRateWalkthroughNotes) {
            LayRateWalkthroughNotes.place(tip, targets[0], { left: left, top: top, bottom: bottom });
        } else {
        tip.style.left = Math.max(12, Math.min(left, innerWidth - tip.offsetWidth - 12)) + 'px';
        var tipTop = bottom + 14;
        if (tipTop + tip.offsetHeight > innerHeight - 12) tipTop = top - tip.offsetHeight - 14;
        tipTop = Math.max(12, Math.min(tipTop, innerHeight - tip.offsetHeight - 12));
        tip.style.top = tipTop + 'px';
        // On compact screens the instructions may overlap a large form. Let
        // clicks reach its inputs; Next remains clickable in the tooltip.
        var overlaps = tipTop < bottom && tipTop + tip.offsetHeight > top;
        tip.style.pointerEvents = overlaps && step && (!step.manual || step.interactive) ? 'none' : 'auto';
        }
        highlightedRows.forEach(function (row) { row.classList.remove('wt5-record-highlight'); });
        highlightedRows = state.step === 'records' ? savedRows() : [];
        highlightedRows.forEach(function (row) { row.classList.add('wt5-record-highlight'); });
    }
    function listen(target, event, handler, options) {
        target.addEventListener(event, handler, options);
        listeners.push(function () { target.removeEventListener(event, handler, options); });
    }
    listen(document, 'environment:thresholds-saved', function() {
        if (!active || !['thresholdFields', 'thresholdSave'].includes(state.step)) return;
        if (window.closeEnvThresholdsModal) window.closeEnvThresholdsModal();
        setStep('readingMenu');
    });
    listen(document, 'environment:reading-saved', function(e) {
        if (!active || !['readingFields', 'readingSave'].includes(state.step)) return;
        state.cageIds = e.detail.cage_ids || [];
        state.logDate = e.detail.log_date;
        if (window.closeEnvManualModal) window.closeEnvManualModal();
        var frame = document.getElementById('environment-logs');
        if (frame && frame.src) {
            var source = new URL(frame.src, location.origin);
            source.searchParams.delete('page');
            source.searchParams.set('date_from', state.logDate);
            source.searchParams.set('date_to', state.logDate);
            if (state.cageIds.length === 1) source.searchParams.set('cage_id', state.cageIds[0]);
            else source.searchParams.delete('cage_id');
            frame.src = source.toString();
        }
        setStep('historyTab');
    });
    listen(next, 'click', function () {
        var step = steps[state.step];
        if (!step || !step.manual || next.disabled || (step.valid && !step.valid())) return;
        if (step.to === 'done') { finish(); return; }
        setStep(step.to);
    });
    function cleanup() {
        if (window.LayRateWalkthroughNotes) LayRateWalkthroughNotes.release();
        active = false;
        clearInterval(timer);
        listeners.forEach(function (remove) { remove(); });
        highlightedRows.forEach(function (row) { row.classList.remove('wt5-record-highlight'); });
        overlay.remove();
        if (window.__environmentWalkthroughCleanup === cleanup) window.__environmentWalkthroughCleanup = null;
    }
    function finish() {
        cleanup(); sessionStorage.removeItem(storageKey);
        var url = new URL(window.location.href);
        url.searchParams.delete('walkthrough5');
        history.replaceState(history.state, '', url);
        var frame = document.getElementById('environment-logs');
        if (frame && frame.src) {
            var source = new URL(frame.src, location.origin);
            ['date_from', 'date_to', 'cage_id', 'page'].forEach(function(key) { source.searchParams.delete(key); });
            frame.src = source.toString();
        }
    }
    listen(overlay.querySelector('[data-wt5-end]'), 'click', finish);
    ['input', 'change', 'click'].forEach(function(event) {
        listen(document, event, function() { setTimeout(render, 0); });
    });
    listen(document, 'turbo:frame-load', render);
    listen(window, 'resize', render);
    listen(window, 'scroll', render, true);
    listen(document, 'wheel', function (e) {
        if (tip.contains(e.target)) return;
        var step = steps[state.step];
        var target = step && step.target();
        if (Array.isArray(target)) target = target[0];
        var modal = target && target.closest('[role="dialog"]');
        var scroll = (modal && modal.querySelector('.overflow-y-auto')) || query('.page-wrapper') || document.scrollingElement;
        if (scroll) { scroll.scrollTop += e.deltaY; e.preventDefault(); }
    }, { passive: false });
    listen(document, 'turbo:before-cache', cleanup);
    listen(document, 'turbo:before-visit', cleanup);
    window.__environmentWalkthroughCleanup = cleanup;
    timer = setInterval(function () {
        if (!active) return;
        var step = steps[state.step];
        // Closing a form returns to its action choice, rather than stranding Next.
        if (['thresholdFields', 'thresholdSave'].includes(state.step) && !visibleModal('envThresholdsModal')) { setStep('setupAction'); return; }
        if (['readingFields', 'readingSave'].includes(state.step) && !visibleModal('envManualModal')) { setStep('readingAction'); return; }
        if (step && step.done && step.done()) { setStep(step.to); return; }
        render();
    }, 250);
    persist();
    render();
})();
