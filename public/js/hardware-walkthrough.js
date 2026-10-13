/* User Manual walkthrough 6: register hardware and review its assignment. */
(function () {
    'use strict';
    var storageKey = 'wt6_state';
    var started = new URLSearchParams(window.location.search).get('walkthrough6') === '1';
    if (!started && !sessionStorage.getItem(storageKey)) return;
    if (!document.getElementById('hardware-live-data')) return;
    if (window.__hardwareWalkthroughCleanup) window.__hardwareWalkthroughCleanup();
    var state;
    try { state = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (e) {}
    state = state || { step: 'menu', itemId: null };
    var pageState = document.getElementById('hardware-walkthrough-state');
    if (!pageState || pageState.dataset.admin !== '1') return;
    if (pageState.dataset.createdId) { state.itemId = pageState.dataset.createdId; state.step = 'records'; }
    else if (pageState.dataset.reopen === '1') { state.step = 'fields'; if (window.openAddModal) window.openAddModal(); }
    var form = document.getElementById('hardwareAddForm');
    if (form && !form.querySelector('[name="walkthrough6"]')) {
        var flag = document.createElement('input'); flag.type = 'hidden'; flag.name = 'walkthrough6'; flag.value = '1'; form.appendChild(flag);
    }
    var active = true;
    var lastTarget = null;
    var listeners = [];
    var highlightedRows = [];
    var timer;
    var overlay = document.createElement('div');
    overlay.id = 'wt6Overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:90;pointer-events:none;';
    overlay.innerHTML = '<div data-wt6-spot style="position:fixed;border:3px solid var(--color-navy);border-radius:12px;pointer-events:none;"></div>'
        + [0, 1, 2, 3].map(function () { return '<div data-wt6-dim style="position:fixed;background:rgba(15,20,35,.55);pointer-events:auto;"></div>'; }).join('')
        + '<div data-wt6-tip role="status" style="position:fixed;z-index:92;width:340px;max-width:calc(100vw - 24px);max-height:calc(100vh - 90px);overflow:auto;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px;box-shadow:0 20px 50px rgba(0,0,0,.3);pointer-events:auto;">'
        + '<div data-wt6-label style="font-size:11px;font-weight:700;color:var(--color-navy);margin-bottom:6px;"></div>'
        + '<div data-wt6-text style="font-size:14px;line-height:1.5;color:#1f1f1f;"></div>'
        + '<button data-wt6-next type="button" style="margin-top:12px;padding:8px 16px;background:var(--color-navy);color:white;border-radius:8px;pointer-events:auto;">Next</button></div>'
        + '<button data-wt6-end type="button" style="position:fixed;top:16px;right:16px;z-index:93;background:#fff;border:1px solid #e6e6e6;padding:8px 14px;border-radius:999px;pointer-events:auto;">End tutorial</button>';
    document.body.appendChild(overlay);
    var spot = overlay.querySelector('[data-wt6-spot]');
    var dims = Array.from(overlay.querySelectorAll('[data-wt6-dim]'));
    var tip = overlay.querySelector('[data-wt6-tip]');
    var label = overlay.querySelector('[data-wt6-label]');
    var text = overlay.querySelector('[data-wt6-text]');
    var next = overlay.querySelector('[data-wt6-next]');
    function query(selector) { return document.querySelector(selector); }
    function visibleModal(id) {
        var modal = document.getElementById(id);
        return modal && window.getComputedStyle(modal).display !== 'none' ? modal : null;
    }
    function modalCard(id) { var modal = visibleModal(id); return modal && modal.querySelector('.relative'); }
    function action() { return query('#dockFabMenu [data-hardware-action="add"]'); }
    function menuOpen() { var menu = document.getElementById('dockFabMenu'); return menu && !menu.classList.contains('hidden'); }
    function persist() { sessionStorage.setItem(storageKey, JSON.stringify(state)); }
    function setStep(name) { state.step = name; lastTarget = null; persist(); render(); }
    function formValid() {
        var form = document.getElementById('hardwareAddForm');
        if (!form || !form.checkValidity()) return false;
        var type = form.elements.device_type.value;
        var status = form.elements.status.value;
        var serial = form.elements.serial_number.value.trim();
        if (!serial) return false;
        if (status === 'spare') return true;
        if (type === 'IR_breakbeam') {
            var primary = form.elements.cage_slot_id.value;
            var extra = document.getElementById('addExtraSlots');
            return !!primary && (!extra || !Array.from(extra.selectedOptions).some(function(option) { return option.value === primary; }));
        }
        if (type === 'DHT22' || type === 'relay') return !!form.elements.cage_id.value;
        return true;
    }
    function savedRows() { var row = state.itemId && query('[data-hardware-id="' + state.itemId + '"]'); return row ? [row] : []; }
    function cell(index) { var row = savedRows()[0]; return row && row.children[index]; }
    var steps = {
        menu: {
            text: '<strong>Register hardware.</strong><br>Click the <em>+</em> Quick Actions button to add a device.',
            target: function() { return query('#quickDock .fab-toggle'); }, done: menuOpen, to: 'action'
        },
        action: {
            text: '<strong>Add Device.</strong><br>Click <em>Add Device</em> to register your hardware.',
            target: function() { return menuOpen() ? action() : query('#quickDock .fab-toggle'); },
            done: function() { return !!visibleModal('addModal'); }, to: 'fields'
        },
        fields: {
            text: '<strong>Fill in the device details.</strong><br><em>Device Type</em>: IR Breakbeam counts eggs; DHT22 measures temperature/humidity; Relay controls equipment; Other covers other hardware.<br><em>Serial Number</em>: a unique identifier, up to 100 characters.<br><em>Assignment</em>: IR uses a primary cage slot with optional additional slots; DHT22 and Relay use a cage. Additional slots must not repeat the primary slot.<br><em>Status</em>: Active, Spare, Faulty, or Removed. Spare devices are unassigned.<br><em>Installation Date</em> and <em>Last Calibration</em>: optional maintenance dates.<br><br>Complete the required details and assignment, then tap <em>Next</em>.',
            target: function() { return modalCard('addModal'); }, manual: true, interactive: true, valid: formValid, to: 'save'
        },
        save: {
            text: '<strong>Add the device.</strong><br>Click <em>Add Device</em> to review the confirmation. Registration is complete only after the server saves it.',
            target: function() { return query('#hardwareAddForm [type="submit"]'); },
            done: function() { return !!visibleModal('confirm-modal'); }, to: 'confirm'
        },
        confirm: {
            text: '<strong>Confirm registration.</strong><br>Click <em>Add Item</em> to save the device, or <em>Cancel</em> to return to the form. The walkthrough resumes on your saved device after the page reloads.',
            target: function() { return modalCard('confirm-modal') || visibleModal('confirm-modal'); }
        },
        records: {
            text: '<strong>Your registered device.</strong><br>The highlighted row is the hardware you just added. Registration records its type, serial number, assignment, and status. Tap <em>Next</em> to review them.',
            target: savedRows, manual: true, to: 'assignment'
        },
        assignment: {
            text: '<strong>Hardware assignment.</strong><br>This column shows the cage or primary cage slot using the device. IR sensors can cover additional slots; Spare devices stay unassigned. The Edit action lets an administrator update these details later.',
            target: function() { return cell(2); }, manual: true, to: 'status'
        },
        status: {
            text: '<strong>Device status.</strong><br>Review whether this device is Active, Spare, Faulty, or Removed. Registration does not by itself establish a connection to the physical device.',
            target: function() { return cell(4); }, manual: true, to: 'readings'
        },
        readings: {
            text: '<strong>Latest reading and maintenance.</strong><br><em>Last Reading</em> shows the latest available sensor data; a newly registered device may have none yet. Installation and calibration dates help track maintenance. Tap <em>Done</em> to finish.',
            target: function() { return cell(3); }, manual: true, to: 'done'
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
        if (!hasTarget) targets = [document.getElementById('hardware-live-data')];
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
        label.textContent = step ? 'Hardware walkthrough · ' + (Object.keys(steps).indexOf(state.step) + 1) + ' of ' + Object.keys(steps).length : 'Hardware walkthrough';
        text.innerHTML = step ? (typeof step.text === 'function' ? step.text() : step.text) : 'Loading hardware…';
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
        highlightedRows.forEach(function (row) { row.classList.remove('wt6-record-highlight'); });
        highlightedRows = state.step === 'records' ? savedRows() : [];
        highlightedRows.forEach(function (row) { row.classList.add('wt6-record-highlight'); });
    }
    function listen(target, event, handler, options) {
        target.addEventListener(event, handler, options);
        listeners.push(function () { target.removeEventListener(event, handler, options); });
    }
    listen(next, 'click', function () {
        var step = steps[state.step];
        if (!step || !step.manual || next.disabled || (step.valid && !step.valid())) return;
        if (step.to === 'done') { finish(); return; }
        if (state.step === 'fields') {
            // Hidden assignments must not be submitted for another device type.
            var form = document.getElementById('hardwareAddForm');
            var type = form.elements.device_type.value;
            var spare = form.elements.status.value === 'spare';
            if (spare || type !== 'IR_breakbeam') {
                form.elements.cage_slot_id.value = '';
                var extras = document.getElementById('addExtraSlots');
                if (extras) Array.from(extras.options).forEach(function(option) { option.selected = false; });
            }
            if (spare || (type !== 'DHT22' && type !== 'relay')) form.elements.cage_id.value = '';
        }
        setStep(step.to);
    });
    function cleanup() {
        if (window.LayRateWalkthroughNotes) LayRateWalkthroughNotes.release();
        active = false;
        clearInterval(timer);
        listeners.forEach(function (remove) { remove(); });
        highlightedRows.forEach(function (row) { row.classList.remove('wt6-record-highlight'); });
        overlay.remove();
        if (window.__hardwareWalkthroughCleanup === cleanup) window.__hardwareWalkthroughCleanup = null;
    }
    function finish() {
        cleanup(); sessionStorage.removeItem(storageKey);
        var form = document.getElementById('hardwareAddForm');
        var flag = form && form.querySelector('[name="walkthrough6"]');
        if (flag) flag.remove();
        var url = new URL(window.location.href); url.searchParams.delete('walkthrough6');
        history.replaceState(history.state, '', url);
        var frame = document.getElementById('hardware-live-data');
        if (frame && frame.src) {
            var source = new URL(frame.src, location.origin);
            ['walkthrough6', 'wt6_item'].forEach(function(key) { source.searchParams.delete(key); });
            frame.src = source.toString();
        }
    }
    listen(overlay.querySelector('[data-wt6-end]'), 'click', finish);
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
    window.__hardwareWalkthroughCleanup = cleanup;
    timer = setInterval(function () {
        if (!active) return;
        var step = steps[state.step];
        // Closing a form returns to its action choice, rather than stranding Next.
        if (['fields', 'save'].includes(state.step) && !visibleModal('addModal') && !visibleModal('confirm-modal')) { setStep('action'); return; }
        if (state.step === 'confirm' && !visibleModal('confirm-modal')) { setStep('fields'); return; }
        if (step && step.done && step.done()) { setStep(step.to); return; }
        render();
    }, 250);
    if (state.itemId) {
        var frame = document.getElementById('hardware-live-data');
        if (frame && frame.src) {
            var source = new URL(frame.src, location.origin);
            source.searchParams.set('walkthrough6', '1');
            source.searchParams.set('wt6_item', state.itemId);
            source.searchParams.delete('page');
            if (frame.src !== source.toString()) frame.src = source.toString();
        }
    }
    persist();
    render();
})();
