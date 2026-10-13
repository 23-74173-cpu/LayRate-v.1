/* User Manual walkthrough 7: choose forecast inputs and review predictions. */
(function () {
    'use strict';
    var storageKey = 'wt7_state';
    var started = new URLSearchParams(window.location.search).get('walkthrough7') === '1';
    if (!started && !sessionStorage.getItem(storageKey)) return;
    if (!document.getElementById('forecast-workspace')) return;
    if (window.__forecastWalkthroughCleanup) window.__forecastWalkthroughCleanup();
    var state;
    try { state = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (e) {}
    state = state || { step: 'readiness', generated: false };
    if (state.step === 'summary' || state.step === 'models') state.step = 'calendar';
    var pageState = document.getElementById('forecast-walkthrough-state');
    if (!pageState) return;
    var active = true;
    var lastTarget = null;
    var menuOriginalStyle = null;
    var listeners = [];
    var highlightedRows = [];
    var timer;
    var overlay = document.createElement('div');
    overlay.id = 'wt7Overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:90;pointer-events:none;';
    overlay.innerHTML = '<div data-wt7-spot style="position:fixed;border:3px solid var(--color-navy);border-radius:12px;pointer-events:none;"></div>'
        + [0, 1, 2, 3].map(function () { return '<div data-wt7-dim style="position:fixed;background:rgba(15,20,35,.55);pointer-events:auto;"></div>'; }).join('')
        + '<div data-wt7-tip role="status" style="position:fixed;z-index:92;width:340px;max-width:calc(100vw - 24px);max-height:calc(100vh - 90px);overflow:auto;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px;box-shadow:0 20px 50px rgba(0,0,0,.3);pointer-events:auto;">'
        + '<div data-wt7-label style="font-size:11px;font-weight:700;color:var(--color-navy);margin-bottom:6px;"></div>'
        + '<div data-wt7-text style="font-size:14px;line-height:1.5;color:#1f1f1f;"></div>'
        + '<button data-wt7-next type="button" style="margin-top:12px;padding:8px 16px;background:var(--color-navy);color:white;border-radius:8px;pointer-events:auto;">Next</button></div>'
        + '<button data-wt7-end type="button" style="position:fixed;top:16px;right:16px;z-index:93;background:#fff;border:1px solid #e6e6e6;padding:8px 14px;border-radius:999px;pointer-events:auto;">End tutorial</button>';
    document.body.appendChild(overlay);
    var spot = overlay.querySelector('[data-wt7-spot]');
    var dims = Array.from(overlay.querySelectorAll('[data-wt7-dim]'));
    var tip = overlay.querySelector('[data-wt7-tip]');
    var label = overlay.querySelector('[data-wt7-label]');
    var text = overlay.querySelector('[data-wt7-text]');
    var next = overlay.querySelector('[data-wt7-next]');
    function query(selector) { return document.querySelector(selector); }
    function visibleModal(id) {
        var modal = document.getElementById(id);
        return modal && window.getComputedStyle(modal).display !== 'none' ? modal : null;
    }
    function modalCard(id) { var modal = visibleModal(id); return modal && modal.querySelector('.relative'); }
    function persist() { sessionStorage.setItem(storageKey, JSON.stringify(state)); }
    function setStep(name) { state.step = name; lastTarget = null; persist(); render(); }
    function canGenerate() { return pageState.dataset.admin === '1' && Number(window.__forecastDataDays || 0) >= 90; }
    function hasSavedForecast() {
        var saved = document.getElementById('forecastSavedState');
        return !!(saved && Number(saved.dataset.count) > 0);
    }
    function inputsValid() {
        var scope = query('#formScope');
        if (!scope || !['farm', 'cage', 'breed'].includes(scope.value)) return false;
        if (scope.value === 'cage' && (!query('#cageSelect') || !query('#cageSelect').value)) return false;
        if (scope.value === 'breed' && (!query('#breedSelect') || !query('#breedSelect').value)) return false;
        return true;
    }
    function savedRows() { return []; }
    var steps = {
        readiness: {
            text: '<strong>Check forecast readiness.</strong><br>Forecast generation requires at least 90 days of production history for the selected scope and an administrator account. The readiness message shows missing history; record or import production data to build it.<br><br>Tap <em>Next</em> to explore the forecasting controls.',
            target: function() { return modalCard('forecastLockOverlay') || query('#forecastForm'); }, manual: true,
            onNext: function() { var dismiss = document.getElementById('lockDismissBtn'); if (dismiss) dismiss.click(); }, to: 'scope'
        },
        scope: {
            text: '<strong>Choose the forecast scope.</strong><br><em>Whole Farm</em>: combined production.<br><em>Per Cage</em>: choose one cage.<br><em>Per Breed</em>: choose one breed.<br>Choose your target, then tap <em>Next</em>.',
            target: function() { return query('#forecastForm'); }, manual: true, interactive: true, valid: inputsValid, to: 'horizon'
        },
        horizon: {
            text: '<strong>Choose the horizon.</strong><br>Select how many future days to predict. The selected horizon controls the forecast range. Tap <em>Next</em> when your scope, target, and horizon are ready.',
            target: function() { return document.getElementById('forecastHorizonChoices') || query('#forecastForm'); }, manual: true, interactive: true,
            valid: function() { return inputsValid() && !!query('#forecastForm input[name="horizon"]:checked'); }, to: 'generate'
        },
        generate: {
            text: function() { return hasSavedForecast() ? '<strong>Your forecast is already saved.</strong><br>Predictions are available for this selection. Tap <em>Next</em> to review the results; you do not need to generate again.' : canGenerate() ? '<strong>Generate the forecast.</strong><br>Click <em>Generate Forecast</em>. The model trains and saves predictions; the tutorial waits for successful completion. A failed run stays here so you can retry.' : '<strong>Generation is currently unavailable.</strong><br>You need an administrator account and enough production history for this scope. Tap <em>Next</em> to review the calendar and any existing results.'; },
            target: function() { return modalCard('forecastLoadingOverlay') || query('#generateForecastBtn') || query('#forecastForm'); },
            manual: true, interactive: true, valid: function() { return hasSavedForecast() || !canGenerate(); }, to: 'calendar'
        },
        calendar: {
            text: '<strong>Production calendar.</strong><br>Review historical production and the forecast dates for your chosen scope. Forecasts estimate future production; they are not guaranteed egg counts. For a <strong>custom forecast date range</strong>, click and hold an available calendar cell, then drag across the cells through your end date and release. This selects the continuous range from your start date to your end date. On touch screens, enable <em>Drag select</em> first, then hold and drag. Review the selected dates before generating the range.<br><br>Try selecting a range, then tap <em>Next</em> to learn the Quick Actions menu.',
            target: function() { return document.getElementById('production-calendar'); }, manual: true, interactive: true, to: 'downloadSheet'
        },
        downloadSheet: {
            text: '<strong>Download input sheet.</strong><br>Downloads an Excel template for your chosen dates. Its cage and other pre-filled columns are protected; fill in the editable egg production, environment, feed, and mortality fields.<br>This is the sheet to fill in before importing production data. Tap <em>Next</em> for the next action.',
            target: function() { return query('#dockFabMenu #fabDownloadBtn'); }, manual: true, explainAction: true, to: 'downloadData'
        },
        downloadData: {
            text: '<strong>Download Production Data.</strong><br>Downloads the production data already stored in the app for review or keeping a copy. Use <em>Download input sheet</em> when you need a template to fill in for an import.',
            target: function() { return query('#dockFabMenu #fabProductionDataBtn'); }, manual: true, explainAction: true, to: 'inputStatus'
        },
        inputStatus: {
            text: '<strong>View input records status.</strong><br>Shows the available input records so you can review your production history and data coverage before forecasting.',
            target: function() { return query('#dockFabMenu #fabInputRecordsBtn'); }, manual: true, explainAction: true, to: 'importData'
        },
        importData: {
            text: '<strong>Import production data.</strong><br>Uploads a completed forecast input sheet into the app.<br><br><strong>Download the input sheet first</strong>, fill in its editable fields with your production data, and save the file as <em>.xlsx</em>. Then use this action to upload that completed file and review the import preview before confirming.',
            target: function() { return query('#dockFabMenu #fabImportBtn'); }, manual: true, explainAction: true, to: 'exportReport'
        },
        exportReport: {
            text: '<strong>Export Forecast Report.</strong><br>Downloads your forecast report in PDF, Excel, or CSV format for sharing or keeping a copy. Tap <em>Done</em> to finish the walkthrough.',
            target: function() { return query('#dockFabMenu #fabExportBtn'); }, manual: true, explainAction: true, to: 'done'
        }
    };
    function render() {
        if (!active) return;
        steps.generate.manual = hasSavedForecast() || !canGenerate();
        var step = steps[state.step];
        if (step && step.explainAction) {
            var menu = document.getElementById('dockFabMenu');
            if (menu) {
                if (!menuOriginalStyle) menuOriginalStyle = { maxHeight: menu.style.maxHeight, overflowY: menu.style.overflowY };
                menu.classList.remove('hidden');
                if (innerWidth < 700) { menu.style.maxHeight = '45vh'; menu.style.overflowY = 'auto'; }
                else { menu.style.maxHeight = menuOriginalStyle.maxHeight; menu.style.overflowY = menuOriginalStyle.overflowY; }
            }
            var dock = document.getElementById('quickDock');
            if (dock) dock.setAttribute('data-active', 'true');
            var toggle = query('#quickDock .fab-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }
        var target = step && step.target();
        var targets = Array.isArray(target) ? target : target ? [target] : [];
        targets = targets.filter(function (el) { return el && el.getClientRects().length; });
        var hasTarget = targets.length > 0;
        // Keep the instructions and gray overlay visible during AJAX refreshes.
        if (!hasTarget) targets = [document.getElementById('forecast-workspace')];
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
        var blocked = !hasTarget || (step && step.manual && !step.interactive && !step.explainAction);
        var bars = blocked ? [[0, 0, innerWidth, innerHeight], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]]
            : [[0, 0, innerWidth, Math.max(0, top)], [0, bottom, innerWidth, Math.max(0, innerHeight - bottom)], [0, top, Math.max(0, left), Math.max(0, bottom - top)], [right, top, Math.max(0, innerWidth - right), Math.max(0, bottom - top)]];
        bars.forEach(function (bar, i) { Object.assign(dims[i].style, { left: bar[0] + 'px', top: bar[1] + 'px', width: bar[2] + 'px', height: bar[3] + 'px' }); });
        label.textContent = step ? 'Forecast walkthrough · ' + (Object.keys(steps).indexOf(state.step) + 1) + ' of ' + Object.keys(steps).length : 'Forecast walkthrough';
        text.innerHTML = step ? (typeof step.text === 'function' ? step.text() : step.text) : 'Loading forecasting…';
        if (!hasTarget && step) text.innerHTML += '<br><br>Waiting for the page to finish loading. You can end the tutorial at any time.';
        next.style.display = step && step.manual ? 'inline-block' : 'none';
        next.disabled = !hasTarget || !!(step && step.valid && !step.valid());
        next.style.opacity = next.disabled ? '.5' : '1';
        next.style.cursor = next.disabled ? 'not-allowed' : 'pointer';
        next.textContent = state.step === 'exportReport' ? 'Done' : 'Next';
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
        highlightedRows.forEach(function (row) { row.classList.remove('wt7-record-highlight'); });
        highlightedRows = state.step === 'records' ? savedRows() : [];
        highlightedRows.forEach(function (row) { row.classList.add('wt7-record-highlight'); });
    }
    function listen(target, event, handler, options) {
        target.addEventListener(event, handler, options);
        listeners.push(function () { target.removeEventListener(event, handler, options); });
    }
    // Explanation steps highlight actions without running their handlers.
    listen(document, 'click', function(e) {
        if (steps[state.step] && steps[state.step].explainAction && e.target.closest('#dockFabMenu button, #dockFabMenu a')) {
            e.preventDefault(); e.stopImmediatePropagation();
        }
    }, true);
    listen(next, 'click', function () {
        var step = steps[state.step];
        if (!step || !step.manual || next.disabled || (step.valid && !step.valid())) return;
        if (step.to === 'done') { finish(); return; }
        if (step.onNext) step.onNext();
        setStep(step.to);
    });
    function cleanup() {
        var menu = document.getElementById('dockFabMenu');
        if (menu && menuOriginalStyle) { menu.style.maxHeight = menuOriginalStyle.maxHeight; menu.style.overflowY = menuOriginalStyle.overflowY; }
        var dock = document.getElementById('quickDock');
        if (dock && steps[state.step] && steps[state.step].explainAction) dock.removeAttribute('data-active');
        if (menu && steps[state.step] && steps[state.step].explainAction) menu.classList.add('hidden');
        var toggle = query('#quickDock .fab-toggle');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        if (window.LayRateWalkthroughNotes) LayRateWalkthroughNotes.release();
        active = false;
        clearInterval(timer);
        listeners.forEach(function (remove) { remove(); });
        highlightedRows.forEach(function (row) { row.classList.remove('wt7-record-highlight'); });
        overlay.remove();
        if (window.__forecastWalkthroughCleanup === cleanup) window.__forecastWalkthroughCleanup = null;
    }
    function finish() {
        cleanup(); sessionStorage.removeItem(storageKey);
        var url = new URL(location.href); url.searchParams.delete('walkthrough7');
        history.replaceState(history.state, '', url);
    }
    listen(document, 'forecast:completed', function() {
        if (state.step !== 'generate') return;
        state.generated = true;
        setStep('calendar');
    });
    listen(overlay.querySelector('[data-wt7-end]'), 'click', finish);
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
    window.__forecastWalkthroughCleanup = cleanup;
    timer = setInterval(function () {
        if (!active) return;
        var step = steps[state.step];
        // Closing a form returns to its action choice, rather than stranding Next.

        if (step && step.done && step.done()) { setStep(step.to); return; }
        render();
    }, 250);
    persist();
    render();
})();
