/* User Manual walkthrough 4: feed stock, feeding, and consumption records. */
(function () {
    'use strict';
    var storageKey = 'wt4_state';
    var started = new URLSearchParams(window.location.search).get('walkthrough4') === '1';
    if (!started && !sessionStorage.getItem(storageKey)) return;
    if (!document.getElementById('feed-live-data')) return;
    if (window.__feedWalkthroughCleanup) window.__feedWalkthroughCleanup();
    var state;
    try { state = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (e) {}
    state = state || { step: null, batchId: null, logId: null, farmId: null, mode: null };
    var active = true;
    var lastTarget = null;
    var listeners = [];
    var highlightedRows = [];
    var timer;
    var overlay = document.createElement('div');
    overlay.id = 'wt4Overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:90;pointer-events:none;';
    overlay.innerHTML = '<div data-wt4-spot style="position:fixed;border:3px solid var(--color-navy);border-radius:12px;pointer-events:none;"></div>'
        + [0, 1, 2, 3].map(function () { return '<div data-wt4-dim style="position:fixed;background:rgba(15,20,35,.55);pointer-events:auto;"></div>'; }).join('')
        + '<div data-wt4-tip role="status" style="position:fixed;z-index:92;width:340px;max-width:calc(100vw - 24px);max-height:calc(100vh - 90px);overflow:auto;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px;box-shadow:0 20px 50px rgba(0,0,0,.3);pointer-events:auto;">'
        + '<div data-wt4-label style="font-size:11px;font-weight:700;color:var(--color-navy);margin-bottom:6px;"></div>'
        + '<div data-wt4-text style="font-size:14px;line-height:1.5;color:#1f1f1f;"></div>'
        + '<button data-wt4-next type="button" style="margin-top:12px;padding:8px 16px;background:var(--color-navy);color:white;border-radius:8px;pointer-events:auto;">Next</button></div>'
        + '<button data-wt4-end type="button" style="position:fixed;top:16px;right:16px;z-index:93;background:#fff;border:1px solid #e6e6e6;padding:8px 14px;border-radius:999px;pointer-events:auto;">End tutorial</button>';
    document.body.appendChild(overlay);
    var spot = overlay.querySelector('[data-wt4-spot]');
    var dims = Array.from(overlay.querySelectorAll('[data-wt4-dim]'));
    var tip = overlay.querySelector('[data-wt4-tip]');
    var label = overlay.querySelector('[data-wt4-label]');
    var text = overlay.querySelector('[data-wt4-text]');
    var next = overlay.querySelector('[data-wt4-next]');
    function query(selector) { return document.querySelector(selector); }
    function visibleModal(id) {
        var modal = document.getElementById(id);
        return modal && !modal.classList.contains('hidden') ? modal : null;
    }
    function modalCard(id) { var modal = visibleModal(id); return modal && modal.querySelector('.relative'); }
    function action(name) { return query('#dockFabMenu [data-feed-action="' + name + '"]'); }
    function menuOpen() { var menu = document.getElementById('dockFabMenu'); return menu && !menu.classList.contains('hidden'); }
    function persist() { sessionStorage.setItem(storageKey, JSON.stringify(state)); }
    function tab(name) { if (window.feedSwitchTab) window.feedSwitchTab(name); }
    function setStep(name) { state.step = name; lastTarget = null; persist(); render(); }
    function formValid(id) {
        var form = document.getElementById(id);
        var submit = form && form.querySelector('[type="submit"]');
        return !!(form && form.checkValidity() && submit && !submit.disabled && !form.querySelector('.feed-error'));
    }
    function feedingModal() { return state.mode === 'farm' ? 'farmEntryModal' : 'consumptionModal'; }
    function feedingForm() { return state.mode === 'farm' ? 'farmEntryForm' : 'consumptionForm'; }
    function savedRows() {
        if (state.logId) return Array.from(document.querySelectorAll('[data-feed-log-id="' + state.logId + '"]'));
        if (state.farmId) return Array.from(document.querySelectorAll('[data-feed-farm-id="' + state.farmId + '"]'));
        return [];
    }
    var steps = {
        createMenu: {
            text: '<strong>Create your feed stock.</strong><br>You have no feed batch yet. Click the <em>+</em> Quick Actions button to add one.',
            target: function () { return query('#quickDock .fab-toggle'); },
            done: menuOpen, to: 'createAction'
        },
        createAction: {
            text: '<strong>Add Feed Batch.</strong><br>Click <em>Add Feed Batch</em> to record your feed stock.',
            target: function () { return menuOpen() ? action('batch') : query('#quickDock .fab-toggle'); },
            done: function () { return !!visibleModal('addBatchModal'); }, to: 'batchFields'
        },
        batchFields: {
            text: '<strong>Fill in the feed batch.</strong><br><em>Brand</em>: supplier or feed brand.<br><em>Crude Protein %</em>: protein percentage on the feed label (0–100).<br><em>Total Quantity (kg)</em>: feed received; used to track remaining stock.<br><em>Unit Cost</em>: price per kilogram.<br><em>Date Received</em>: when the feed arrived.<br><em>Low Stock Threshold</em>: remaining quantity that triggers a stock warning.<br><em>Notes</em>: optional supplier or batch details.<br>The batch code is generated automatically.<br><br>Complete the required fields, then tap <em>Next</em>.',
            target: function () { return modalCard('addBatchModal'); }, manual: true, interactive: true,
            valid: function () { return formValid('addFeedBatchForm'); }, to: 'batchSave'
        },
        batchSave: {
            text: '<strong>Save the batch.</strong><br>Click <em>Add Batch</em>. The tutorial continues only after the batch is saved successfully.',
            target: function () { return query('#addFeedBatchForm [type="submit"]'); }
        },
        stock: {
            text: '<strong>Your current feed stock.</strong><br>This feed batch is the stock available to feed your chickens. <em>Remaining</em> shows the kilograms left after recorded consumption. Tap <em>Next</em> to log feeding.',
            target: function () { tab('batches'); return state.batchId ? query('[data-feed-batch-id="' + state.batchId + '"]') : query('[data-feed-batch-id]'); },
            manual: true, to: 'feedingMenu'
        },
        feedingMenu: {
            text: '<strong>Feed your chickens.</strong><br>Click the <em>+</em> Quick Actions button to see the two feeding choices.',
            target: function () { return query('#quickDock .fab-toggle'); }, done: menuOpen, to: 'feedingChoice'
        },
        feedingChoice: {
            text: '<strong>Choose how to log feeding.</strong><br><em>Add Consumption</em>: record feed for one specific cage.<br><em>Log Whole-Farm Feeding</em>: enter the total fed; the system distributes it across active cages in proportion to their hen counts. Those rows are marked <em>Estimated</em>.<br><br>Click either choice to continue.',
            target: function () { return menuOpen() ? [action('consumption'), action('farm')].filter(Boolean) : query('#quickDock .fab-toggle'); },
            done: function () {
                if (visibleModal('consumptionModal')) { state.mode = 'consumption'; return true; }
                if (visibleModal('farmEntryModal')) { state.mode = 'farm'; return true; }
                return false;
            }, to: 'feedingFields'
        },
        feedingFields: {
            text: function () {
                return '<strong>Fill in the feeding record.</strong><br>'
                    + (state.mode === 'farm' ? '<em>Total Fed (kg)</em>: total feed for the whole farm; it will be divided by hen count.' : '<em>Cage</em>: the specific cage being fed.<br><em>Consumed (kg)</em>: feed used by that cage.')
                    + '<br><em>Feed Batch</em>: the stock used for feeding.<br><em>Date and Time</em>: when feeding happened.<br>The quantity must fit the remaining stock. Complete the fields, then tap <em>Next</em>.';
            },
            target: function () { return modalCard(feedingModal()); }, manual: true, interactive: true,
            valid: function () { return formValid(feedingForm()); }, to: 'feedingSave'
        },
        feedingSave: {
            text: function () { return '<strong>Save the feeding.</strong><br>Click <em>' + (state.mode === 'farm' ? 'Distribute' : 'Save') + '</em>. Wait for a successful save before reviewing the records.'; },
            target: function () { return query('#' + feedingForm() + ' [type="submit"]'); }
        },
        consumptionTab: {
            text: '<strong>Review Daily Consumption.</strong><br>Click the <em>Daily Consumption</em> tab to see the feed records you just added.',
            target: function () { return query('#feed-tabs-nav button[onclick*="consumption"]'); },
            done: function () { var panel = document.getElementById('tab-consumption'); return panel && !panel.classList.contains('hidden') && savedRows().length > 0; }, to: 'records'
        },
        records: {
            text: function () { return '<strong>Your feeding records.</strong><br>The highlighted rows show the date, time, cage, feed batch, and kilograms fed.' + (state.mode === 'farm' ? ' Whole-farm feeding produces an <em>Estimated</em> row for each cage with hens.' : ' Your selected cage has a <em>Direct</em> consumption record.') + '<br>These quantities are deducted from feed stock. Tap <em>Done</em> to finish.'; },
            target: savedRows, manual: true, to: 'done'
        }
    };
    function render() {
        if (!active) return;
        // The live frame is lazy-loaded; do not infer "no batches" from its skeleton.
        if (!state.step) {
            var data = query('[data-feed-batch-count]');
            if (data && window.feedSwitchTab) {
                state.step = Number(data.dataset.feedBatchCount) > 0 ? 'stock' : 'createMenu';
                tab('batches'); persist();
            }
        }
        var step = steps[state.step];
        var target = step && step.target();
        var targets = Array.isArray(target) ? target : target ? [target] : [];
        targets = targets.filter(function (el) { return el && el.getClientRects().length; });
        var hasTarget = targets.length > 0;
        // Keep the instructions and gray overlay visible during AJAX refreshes.
        if (!hasTarget) targets = [document.getElementById('feed-live-data')];
        if (targets[0] && lastTarget !== targets[0] && hasTarget) {
            targets[0].scrollIntoView({ block: 'nearest', inline: 'nearest' });
            lastTarget = targets[0];
        }
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
        label.textContent = step ? 'Feed walkthrough · ' + (Object.keys(steps).indexOf(state.step) + 1) + ' of ' + Object.keys(steps).length : 'Feed walkthrough';
        text.innerHTML = step ? (typeof step.text === 'function' ? step.text() : step.text) : 'Loading feed stock…';
        if (!hasTarget && step) text.innerHTML += '<br><br>Waiting for the page to finish loading. You can end the tutorial at any time.';
        next.style.display = step && step.manual ? 'inline-block' : 'none';
        next.disabled = !hasTarget || !!(step && step.valid && !step.valid());
        next.style.opacity = next.disabled ? '.5' : '1';
        next.style.cursor = next.disabled ? 'not-allowed' : 'pointer';
        next.textContent = state.step === 'records' ? 'Done' : 'Next';
        tip.style.left = Math.max(12, Math.min(left, innerWidth - tip.offsetWidth - 12)) + 'px';
        var tipTop = bottom + 14;
        if (tipTop + tip.offsetHeight > innerHeight - 12) tipTop = top - tip.offsetHeight - 14;
        tipTop = Math.max(12, Math.min(tipTop, innerHeight - tip.offsetHeight - 12));
        tip.style.top = tipTop + 'px';
        // On compact screens the instructions may overlap a large form. Let
        // clicks reach its inputs; Next remains clickable in the tooltip.
        var overlaps = tipTop < bottom && tipTop + tip.offsetHeight > top;
        tip.style.pointerEvents = overlaps && step && (!step.manual || step.interactive) ? 'none' : 'auto';
        highlightedRows.forEach(function (row) { row.classList.remove('wt4-record-highlight'); });
        highlightedRows = state.step === 'records' ? savedRows() : [];
        highlightedRows.forEach(function (row) { row.classList.add('wt4-record-highlight'); });
    }
    function listen(target, event, handler, options) {
        target.addEventListener(event, handler, options);
        listeners.push(function () { target.removeEventListener(event, handler, options); });
    }
    function setFilter(key, value) {
        var url = new URL(window.location.href);
        ['page', 'batches_page', 'consumption_page', 'cage_id'].forEach(function (name) { url.searchParams.delete(name); });
        url.searchParams.set('walkthrough4', '1');
        url.searchParams.set(key, String(value));
        url.searchParams.set('tab', 'batches');
        history.replaceState(history.state, '', url);
    }
    listen(document, 'feed:saved', function (e) {
        if (!active) return;
        var data = e.detail.data;
        if (e.detail.formId === 'addFeedBatchForm' && ['batchFields', 'batchSave'].includes(state.step)) {
            state.batchId = data.batch_id;
            setFilter('wt4_batch', state.batchId);
            setStep('stock');
        } else if (e.detail.formId === feedingForm() && ['feedingFields', 'feedingSave'].includes(state.step)) {
            state.logId = data.log_id || null;
            state.farmId = data.farm_entry_id || null;
            setFilter(state.logId ? 'wt4_log' : 'wt4_farm', state.logId || state.farmId);
            setStep('consumptionTab');
        }
    });
    listen(next, 'click', function () {
        var step = steps[state.step];
        if (!step || !step.manual || next.disabled || (step.valid && !step.valid())) return;
        if (step.to === 'done') { finish(); return; }
        setStep(step.to);
    });
    function cleanup() {
        active = false;
        clearInterval(timer);
        listeners.forEach(function (remove) { remove(); });
        highlightedRows.forEach(function (row) { row.classList.remove('wt4-record-highlight'); });
        overlay.remove();
        if (window.__feedWalkthroughCleanup === cleanup) window.__feedWalkthroughCleanup = null;
    }
    function finish() {
        cleanup(); sessionStorage.removeItem(storageKey);
        var url = new URL(window.location.href);
        ['walkthrough4', 'wt4_batch', 'wt4_log', 'wt4_farm'].forEach(function (key) { url.searchParams.delete(key); });
        history.replaceState(history.state, '', url);
        // Restore the full tables after reviewing the newly saved records.
        var frame = document.getElementById('feed-live-data');
        if (frame && frame.src) {
            var source = new URL(frame.src, window.location.origin);
            ['walkthrough4', 'wt4_batch', 'wt4_log', 'wt4_farm'].forEach(function (key) { source.searchParams.delete(key); });
            frame.src = source.toString();
        }
    }
    listen(overlay.querySelector('[data-wt4-end]'), 'click', finish);
    ['input', 'change', 'click'].forEach(function (event) {
        listen(document, event, function (e) {
            if (event === 'input' || event === 'change') {
                var form = e.target.closest('form');
                if (form) form.querySelectorAll('.feed-error').forEach(function (error) { error.remove(); });
            }
            setTimeout(render, 0);
        });
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
    window.__feedWalkthroughCleanup = cleanup;
    timer = setInterval(function () {
        if (!active) return;
        var step = steps[state.step];
        // Closing a form returns to its action choice, rather than stranding Next.
        if (['batchFields', 'batchSave'].includes(state.step) && !visibleModal('addBatchModal')) { setStep('createAction'); return; }
        if (['feedingFields', 'feedingSave'].includes(state.step) && !visibleModal(feedingModal())) { setStep('feedingChoice'); return; }
        if (step && step.done && step.done()) { setStep(step.to); return; }
        render();
    }, 250);
    render();
})();
