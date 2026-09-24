/*
 * LayRate date fields: always show and type dates as mm/dd/yyyy.
 *
 * A native <input type="date"> shows its value in whatever order the
 * browser's language uses: dd/mm/yyyy on en-GB / en-PH machines (the Pi's
 * default), mm/dd/yyyy on en-US, "Sep 24, 2026" on iPhones. CSS cannot change
 * that, so the same field looked different from device to device.
 *
 * This script leaves every native date input in the page, with the same
 * name, id, value (still ISO yyyy-mm-dd) and events, so every controller,
 * validation rule and page script keeps working unchanged. It only hides it
 * and puts a plain text box in front of it that always reads mm/dd/yyyy:
 *
 *   - typing "09/24/2026" (slashes are added for you) writes 2026-09-24 into
 *     the real input; the calendar icon (or Alt+Down) still opens the
 *     browser's own date picker;
 *   - page scripts that set input.value = '2026-09-24' update the text box;
 *   - the real input fires 'input' while typing and 'change' when the user
 *     leaves the field, the same events page scripts already listen for;
 *   - on phones / tablets the box opens the native picker instead of the
 *     keyboard.
 *
 * datetime-local inputs get the same treatment as "mm/dd/yyyy hh:mm AM".
 * Opt a field out with the data-native-date attribute.
 */
(function () {
    if (window.__layrateDateMdy) return;
    window.__layrateDateMdy = true;

    var SELECTOR = 'input[type="date"]:not([data-native-date]),input[type="datetime-local"]:not([data-native-date])';
    var MIRRORED_ATTRS = ['class', 'style', 'disabled', 'readonly', 'required', 'min', 'max', 'value', 'title'];
    var proto = HTMLInputElement.prototype;
    var valueDesc = Object.getOwnPropertyDescriptor(proto, 'value');
    var states = new WeakMap(); // native input -> state
    var coarsePointer = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

    // ── Styles (unlayered, so they win over Tailwind's @layer utilities) ──
    var ICON = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2'/%3E%3Cpath d='M16 2v4M8 2v4M3 10h18'/%3E%3C/svg%3E";
    var css =
        'input.mdy-native{position:absolute!important;opacity:0!important;pointer-events:none!important;' +
        'width:1px!important;height:1px!important;min-width:0!important;min-height:0!important;' +
        'margin:0!important;padding:0!important;border:0!important;' +
        'left:var(--mdy-left,auto)!important;top:var(--mdy-top,auto)!important;}' +
        'input.mdy-display{padding-right:2.25rem!important;background-image:url("' + ICON + '")!important;' +
        'background-repeat:no-repeat!important;background-position:right .65rem center!important;background-size:1rem 1rem!important;}' +
        'input.mdy-display.mdy-over-icon{cursor:pointer!important;}' +
        'input.mdy-display:disabled{cursor:not-allowed!important;}';

    function injectStyles() {
        if (document.getElementById('mdy-date-styles')) return;
        var style = document.createElement('style');
        style.id = 'mdy-date-styles';
        style.textContent = css;
        (document.head || document.documentElement).appendChild(style);
    }

    // ── Format helpers ──
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function daysInMonth(y, m) { return new Date(y, m, 0).getDate(); }

    // 'yyyy-mm-dd' or 'yyyy-mm-ddThh:mm[:ss]' -> 'mm/dd/yyyy' or 'mm/dd/yyyy hh:mm AM'
    function isoToDisplay(iso, withTime) {
        var m = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(iso || '');
        if (!m) return '';
        var out = m[2] + '/' + m[3] + '/' + m[1];
        if (withTime && m[4] !== undefined) {
            var h = parseInt(m[4], 10);
            var ap = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            if (h === 0) h = 12;
            out += ' ' + pad(h) + ':' + m[5] + ' ' + ap;
        }
        return out;
    }

    // Typed text -> ISO string, '' for an empty box, or null when the text is
    // not a real date. Also accepts a pasted ISO date (2026-09-24).
    function displayToIso(text, withTime) {
        text = (text || '').trim();
        if (text === '') return '';

        var iso = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?$/.exec(text);
        var mo, d, y, h = 0, mi = 0;
        if (iso) {
            y = +iso[1]; mo = +iso[2]; d = +iso[3];
            if (withTime) {
                if (iso[4] === undefined) return null;
                h = +iso[4]; mi = +iso[5];
                if (h > 23 || mi > 59) return null;
            }
        } else {
            var m = withTime
                ? /^(\d{1,2})\/(\d{1,2})\/(\d{4}),?\s+(\d{1,2}):(\d{2})\s*([AaPp][Mm])?$/.exec(text)
                : /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(text);
            if (!m) return null;
            mo = +m[1]; d = +m[2]; y = +m[3];
            if (withTime) {
                h = +m[4]; mi = +m[5];
                if (mi > 59) return null;
                if (m[6]) {
                    if (h < 1 || h > 12) return null;
                    h = (h % 12) + (m[6].toUpperCase() === 'PM' ? 12 : 0);
                } else if (h > 23) {
                    return null;
                }
            }
        }
        if (y < 1000 || mo < 1 || mo > 12 || d < 1 || d > daysInMonth(y, mo)) return null;

        var out = y + '-' + pad(mo) + '-' + pad(d);
        if (withTime) out += 'T' + pad(h) + ':' + pad(mi);
        return out;
    }

    function nativeValue(native) { return valueDesc.get.call(native); }
    function setNativeValue(native, v) { valueDesc.set.call(native, v); }

    function fire(el, type) {
        el.dispatchEvent(new Event(type, { bubbles: true }));
    }

    // ── Validation: the text box is what the user sees, so it carries the
    // messages (in mm/dd/yyyy) instead of the hidden native input. ──
    function refreshValidity(state) {
        var native = state.native, display = state.display, msg = '';
        if (state.badText) {
            msg = state.withTime
                ? 'Enter a date and time like 09/24/2026 03:45 PM.'
                : 'Enter a date like 09/24/2026 (mm/dd/yyyy).';
        } else {
            var v = nativeValue(native);
            if (v) {
                if (native.min && v < native.min) msg = 'Date must be on or after ' + isoToDisplay(native.min, state.withTime) + '.';
                else if (native.max && v > native.max) msg = 'Date must be on or before ' + isoToDisplay(native.max, state.withTime) + '.';
            }
        }
        display.setCustomValidity(msg);
    }

    // Copy what the page controls on the native input (look, state) onto the
    // text box. Runs at setup and again whenever those attributes change, so
    // a script that disables the field or marks it red still works.
    function mirror(state) {
        var native = state.native, display = state.display;
        var cls = (native.getAttribute('class') || '').split(/\s+/).filter(function (c) {
            return c && c !== 'mdy-native';
        });
        var overIcon = display.classList.contains('mdy-over-icon');
        display.className = cls.join(' ');
        display.classList.add('mdy-display');
        if (overIcon) display.classList.add('mdy-over-icon');

        var style = native.getAttribute('style');
        if (style) display.setAttribute('style', style); else display.removeAttribute('style');

        display.disabled = native.disabled;
        display.readOnly = native.readOnly;
        display.required = native.required;
        if (native.title) display.title = native.title; else display.removeAttribute('title');
    }

    // Native value -> text box. keepText leaves what the user is typing alone.
    function sync(state, keepText) {
        if (!keepText) {
            state.badText = false;
            state.display.value = isoToDisplay(nativeValue(state.native), state.withTime);
        }
        refreshValidity(state);
    }

    // Parks the invisible native input right under the text box so the
    // browser's picker (and any validation bubble) opens in the right place.
    function positionNative(state) {
        var native = state.native, display = state.display;
        var parent = native.offsetParent;
        if (!parent) return;
        var d = display.getBoundingClientRect();
        var p = parent.getBoundingClientRect();
        var left = d.left - p.left + parent.scrollLeft - parent.clientLeft;
        var top = d.bottom - p.top + parent.scrollTop - parent.clientTop - 1;
        native.style.setProperty('--mdy-left', Math.round(left) + 'px');
        native.style.setProperty('--mdy-top', Math.round(top) + 'px');
    }

    function openPicker(state) {
        var native = state.native;
        if (native.disabled || native.readOnly) return;
        positionNative(state);
        try {
            if (typeof native.showPicker === 'function') {
                native.showPicker();
                return;
            }
        } catch (e) { /* fall through to the focus/click fallback */ }
        try { native.focus(); native.click(); } catch (e) { /* nothing else to try */ }
    }

    // Text box -> native value, when the user leaves the field (or presses
    // Enter). Fires 'change' on the native input only if the value changed.
    function commit(state) {
        var native = state.native, display = state.display;
        var iso = displayToIso(display.value, state.withTime);
        if (iso === null) {
            state.badText = true;
            if (nativeValue(native) !== '') {
                setNativeValue(native, '');
                state.internal = true;
                fire(native, 'input');
                state.internal = false;
            }
        } else {
            state.badText = false;
            if (nativeValue(native) !== iso) setNativeValue(native, iso);
            display.value = isoToDisplay(iso, state.withTime);
        }
        refreshValidity(state);
        var now = nativeValue(native);
        if (now !== state.committed) {
            state.committed = now;
            state.internal = true;
            fire(native, 'change');
            state.internal = false;
        }
    }

    // Adds the slashes as the user types digits: 0924 -> 09/24/
    function mask(display, e) {
        var v = display.value;
        if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return; // pasted ISO date, parsed as-is
        var cleaned = v.replace(/[^\d\/]/g, '').replace(/\/{2,}/g, '/');
        var deleting = e && e.inputType && e.inputType.indexOf('delete') === 0;
        if (!deleting && (/^\d{2}$/.test(cleaned) || /^\d{1,2}\/\d{2}$/.test(cleaned))) cleaned += '/';
        if (cleaned.length > 10) cleaned = cleaned.slice(0, 10);
        if (cleaned !== v) display.value = cleaned;
    }

    var attrObserver = new MutationObserver(function (records) {
        records.forEach(function (r) {
            var state = states.get(r.target);
            if (!state) return;
            mirror(state);
            if (r.attributeName === 'value') sync(state, document.activeElement === state.display);
            else refreshValidity(state);
        });
    });

    function enhance(native) {
        if (states.has(native) || !native.parentNode) return;
        var withTime = native.type === 'datetime-local';

        // A Turbo cache snapshot restores a clone of the page: the text box
        // is cloned too but no longer wired up, so rebuild it.
        var prev = native.previousElementSibling;
        if (prev && prev.hasAttribute('data-mdy-display')) prev.parentNode.removeChild(prev);

        var display = document.createElement('input');
        display.type = 'text';
        display.setAttribute('data-mdy-display', '');
        display.setAttribute('autocomplete', 'off');
        display.setAttribute('spellcheck', 'false');
        display.placeholder = withTime ? 'mm/dd/yyyy hh:mm AM' : 'mm/dd/yyyy';
        display.maxLength = withTime ? 20 : 10;
        if (coarsePointer) display.setAttribute('inputmode', 'none');
        else if (!withTime) display.setAttribute('inputmode', 'numeric');
        var aria = native.getAttribute('aria-label');
        if (aria) display.setAttribute('aria-label', aria);

        var state = {
            native: native, display: display, withTime: withTime,
            badText: false, internal: false, committed: nativeValue(native),
        };
        states.set(native, state);

        native.parentNode.insertBefore(display, native);
        native.classList.add('mdy-native');
        native.setAttribute('tabindex', '-1');
        native.setAttribute('aria-hidden', 'true');
        mirror(state);
        sync(state, false);

        // Page scripts set native.value directly (edit modals, resets after an
        // AJAX save): keep the text box in step with those writes.
        ['value', 'valueAsDate', 'valueAsNumber'].forEach(function (prop) {
            var desc = Object.getOwnPropertyDescriptor(proto, prop);
            if (!desc || !desc.set) return;
            Object.defineProperty(native, prop, {
                configurable: true,
                enumerable: desc.enumerable,
                get: function () { return desc.get.call(this); },
                set: function (v) {
                    desc.set.call(this, v);
                    state.committed = nativeValue(this);
                    sync(state, false);
                },
            });
        });

        // The browser's picker changed the native value.
        native.addEventListener('input', function () {
            if (state.internal) return;
            sync(state, false);
        });
        native.addEventListener('change', function () {
            if (state.internal) return;
            state.committed = nativeValue(native);
            sync(state, false);
        });
        native.addEventListener('invalid', function () { positionNative(state); });

        // The text box's own input/change events stay private: pages listen on
        // the real (native) input, which gets its own events below.
        display.addEventListener('input', function (e) {
            e.stopPropagation();
            if (!withTime) mask(display, e);
            var iso = displayToIso(display.value, withTime);
            state.badText = false;
            if (iso !== null && iso !== nativeValue(native)) {
                setNativeValue(native, iso);
                state.internal = true;
                fire(native, 'input');
                state.internal = false;
            }
            refreshValidity(state);
        });
        display.addEventListener('change', function (e) {
            e.stopPropagation();
            commit(state);
        });
        display.addEventListener('focus', function () {
            state.committed = nativeValue(native);
        });
        display.addEventListener('keydown', function (e) {
            if ((e.altKey && (e.key === 'ArrowDown' || e.key === 'Down')) || e.key === 'F4') {
                e.preventDefault();
                openPicker(state);
            } else if (e.key === 'Enter') {
                commit(state);
            }
        });

        function onIcon(e) {
            return e.offsetX >= display.clientWidth - 36;
        }
        display.addEventListener('mousemove', function (e) {
            display.classList.toggle('mdy-over-icon', onIcon(e));
        });
        display.addEventListener('mouseleave', function () {
            display.classList.remove('mdy-over-icon');
        });
        display.addEventListener('click', function (e) {
            if (coarsePointer || onIcon(e)) openPicker(state);
        });

        attrObserver.observe(native, { attributes: true, attributeFilter: MIRRORED_ATTRS });
    }

    function scan(root) {
        if (!root || root.nodeType !== 1) return;
        if (root.matches && root.matches(SELECTOR)) enhance(root);
        if (root.querySelectorAll) {
            var list = root.querySelectorAll(SELECTOR);
            for (var i = 0; i < list.length; i++) enhance(list[i]);
        }
    }

    function scanAll() { scan(document.body); }

    // Clicking a <label for="..."> of a hidden native input focuses the text box.
    document.addEventListener('click', function (e) {
        var label = e.target && e.target.closest ? e.target.closest('label') : null;
        if (!label || !label.control) return;
        var state = states.get(label.control);
        if (!state || e.target === state.display) return;
        e.preventDefault();
        if (coarsePointer) openPicker(state);
        else state.display.focus();
    });

    // form.reset() puts native inputs back to their default value without
    // going through the value setter.
    document.addEventListener('reset', function (e) {
        var form = e.target;
        setTimeout(function () {
            if (!form || !form.querySelectorAll) return;
            var list = form.querySelectorAll('input.mdy-native');
            for (var i = 0; i < list.length; i++) {
                var state = states.get(list[i]);
                if (state) {
                    state.committed = nativeValue(list[i]);
                    sync(state, false);
                }
            }
        }, 0);
    }, true);

    function start() {
        injectStyles();
        scanAll();
        new MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) {
                var added = records[i].addedNodes;
                for (var j = 0; j < added.length; j++) scan(added[j]);
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
        document.addEventListener('turbo:load', scanAll);
        document.addEventListener('turbo:frame-load', scanAll);
    }

    // Exposed for other scripts and for tests.
    window.LayRateDates = {
        toDisplay: isoToDisplay,
        toIso: displayToIso,
        refresh: scanAll,
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
