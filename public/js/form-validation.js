/**
 * form-validation.js — branded client-side validation for every form.
 *
 * Replaces the browser's native "Please fill out this field." bubbles with
 * inline errors that match x-input-error (danger icon + message under the
 * field, danger border, aria wiring). Server-side validation stays the
 * authority — this only gives instant feedback with the same friendly tone
 * as lang/en/validation.php.
 *
 * Conventions:
 * - Auto-attaches to all <form> (page, modals, Turbo frames/streams).
 * - Opt out per form: data-native-validation.
 * - Per-field message override: data-error-message="...".
 * - Never double-binds: one delegated document-level submit listener
 *   (capture phase) + novalidate stamped per form via WeakSet.
 * - Invalid submit: preventDefault + stopPropagation (so AJAX/data-confirm
 *   handlers never fire), focus first invalid field.
 */
(function () {
    'use strict';

    var DANGER = '#9b1c24';

    var seenForms = (typeof WeakSet !== 'undefined') ? new WeakSet() : null;
    var stamped = [];

    function labelFor(field) {
        if (field.getAttribute('data-error-label')) return field.getAttribute('data-error-label');
        var id = field.id;
        if (id) {
            var lab = document.querySelector('label[for="' + id + '"]');
            if (lab && lab.textContent.trim() !== '') return lab.textContent.trim().toLowerCase().replace(/\*$/, '').trim();
        }
        var wrap = field.closest('label');
        if (wrap) {
            var clone = wrap.cloneNode(true);
            var inner = clone.querySelector('input,select,textarea');
            if (inner) inner.remove();
            if (clone.textContent.trim() !== '') return clone.textContent.trim().toLowerCase().replace(/\*$/, '').trim();
        }
        if (field.getAttribute('aria-label')) return field.getAttribute('aria-label').trim().toLowerCase();
        var name = (field.name || '').replace(/[_-]+/g, ' ').replace(/\[\]$/, '').trim().toLowerCase();
        return name !== '' ? name : 'this field';
    }

    function messageFor(field, kind, extra) {
        var override = field.getAttribute('data-error-message');
        if (override) return override;
        var label = labelFor(field);
        switch (kind) {
            case 'required': return 'Please fill in the ' + label + ' before continuing.';
            case 'email': return 'Enter a valid email address, like name@farm.com.';
            case 'number': return 'Enter a number for the ' + label + '.';
            case 'integer': return 'Enter a whole number for the ' + label + ', like 12.';
            case 'minlength': return 'The ' + label + ' needs at least ' + extra + ' characters.';
            case 'min': return 'The ' + label + ' must be at least ' + extra + '.';
            case 'max': return 'That value looks too high. Check the ' + label + ' and try again.';
            case 'pattern': return 'The ' + label + ' format does not look right. Check it and try again.';
            case 'date': return 'Choose a valid date for the ' + label + '.';
            default: return 'Please check the ' + label + ' and try again.';
        }
    }

    function errorNode(field) {
        var id = field.id ? 'err-' + field.id : null;
        var existing = null;
        if (id) existing = document.getElementById(id);
        if (existing && existing.hasAttribute('data-client-error')) return existing;
        var p = document.createElement('p');
        p.setAttribute('data-client-error', 'true');
        p.setAttribute('role', 'alert');
        p.className = 'flex items-center gap-1 mt-1 text-xs text-danger';
        if (id) p.id = id;
        // Insert directly after the field (or after its wrapper for radios/checkbox groups).
        var anchor = field;
        if ((field.type === 'radio' || field.type === 'checkbox') && field.name) {
            var group = document.querySelectorAll('input[name="' + field.name + '"]');
            if (group.length > 1) anchor = group[group.length - 1];
        }
        anchor.parentNode.insertBefore(p, anchor.nextSibling);
        return p;
    }

    function showError(field, msg) {
        var p = errorNode(field);
        p.innerHTML = '';
        var icon = document.createElement('i');
        icon.setAttribute('data-lucide', 'alert-circle');
        icon.className = 'w-3 h-3 shrink-0';
        icon.style.color = DANGER;
        var span = document.createElement('span');
        span.textContent = msg;
        p.appendChild(icon);
        p.appendChild(span);
        p.style.display = 'flex';
        if (!field.hasAttribute('data-keep-border')) {
            field.style.borderColor = DANGER;
            field.setAttribute('data-client-border', '1');
        }
        field.setAttribute('aria-invalid', 'true');
        if (p.id) {
            var described = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (described.indexOf(p.id) === -1) {
                described.push(p.id);
                field.setAttribute('aria-describedby', described.join(' '));
            }
        }
        if (window.lucide) lucide.createIcons();
    }

    function clearError(field) {
        var id = field.id ? 'err-' + field.id : null;
        var p = id ? document.getElementById(id) : null;
        if (p && p.hasAttribute('data-client-error')) p.style.display = 'none';
        // Only clear an inline border this module set (server-side error
        // borders from x-input-error flows are never touched).
        if (field.getAttribute('data-client-border') === '1') {
            field.style.borderColor = '';
            field.removeAttribute('data-client-border');
        }
        field.removeAttribute('aria-invalid');
    }

    function isVisible(field) {
        return !!(field.offsetWidth || field.offsetHeight || field.getClientRects().length);
    }

    function checkField(field) {
        if (field.disabled || field.readOnly) return null;
        if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return null;
        var val = (field.value || '');
        var isCheckbox = field.type === 'checkbox';
        var isRadio = field.type === 'radio';

        if (field.required) {
            if (isCheckbox && !field.checked) return messageFor(field, 'required');
            if (isRadio) {
                var group = document.querySelectorAll('input[name="' + field.name + '"]');
                var anyChecked = Array.prototype.some.call(group, function (r) { return r.checked; });
                // Report once per group (on its first visible member).
                if (!anyChecked && group[0] === field && isVisible(field)) return messageFor(field, 'required');
                if (anyChecked || group[0] !== field) return checkRest(field, val);
            } else if (val.trim() === '') {
                return messageFor(field, 'required');
            }
        }
        return checkRest(field, val);
    }

    function checkRest(field, val) {
        if (val === '' || val == null) return null;
        var type = (field.type || 'text').toLowerCase();
        if (type === 'email') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val.trim())) return messageFor(field, 'email');
        }
        if (type === 'number' || type === 'range') {
            if (val.trim() !== '' && isNaN(Number(val))) return messageFor(field, 'number');
            // Native default step is 1 (whole numbers); mirror it with a
            // friendly message. Explicit decimal steps allow fractions.
            var stepAttr = (field.getAttribute('step') || '1').toLowerCase();
            if ((stepAttr === '1' || stepAttr === '') && val.trim() !== '' && !isNaN(Number(val))) {
                if (!Number.isInteger(Number(val))) return messageFor(field, 'integer');
            }
        }
        if (field.minLength > 0 && val.length < field.minLength) return messageFor(field, 'minlength', field.minLength);
        if (field.maxLength > 0 && val.length > field.maxLength) return null; // maxlength enforced natively
        if (field.min !== '' && val !== '' && !isNaN(Number(val)) && !isNaN(Number(field.min)) && Number(val) < Number(field.min)) {
            return messageFor(field, 'min', field.min);
        }
        if (field.max !== '' && val !== '' && !isNaN(Number(val)) && !isNaN(Number(field.max)) && Number(val) > Number(field.max)) {
            return messageFor(field, 'max', field.max);
        }
        if (field.pattern && val !== '') {
            try {
                var re = new RegExp('^(?:' + field.pattern + ')$');
                if (!re.test(val)) return messageFor(field, 'pattern');
            } catch (e) { /* invalid pattern — let the server decide */ }
        }
        if ((type === 'date' || type === 'month' || type === 'time' || type === 'datetime-local') && val !== '') {
            if (isNaN(Date.parse(val)) && isNaN(Number(val))) {
                // Fall back to native validity for exotic date inputs.
                if (field.validity && field.validity.badInput) return messageFor(field, 'date');
            }
        }
        return null;
    }

    function stamp(form) {
        if (form.hasAttribute('data-native-validation')) return;
        form.setAttribute('novalidate', '');
        if (seenForms) {
            if (seenForms.has(form)) return;
            seenForms.add(form);
        } else if (stamped.indexOf(form) !== -1) {
            return;
        } else {
            stamped.push(form);
        }
    }

    function stampAll(root) {
        (root || document).querySelectorAll('form:not([data-native-validation])').forEach(stamp);
    }

    // Live-clear: delegated, works for Turbo-rendered content.
    document.addEventListener('input', function (e) {
        var t = e.target;
        if (t && /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) clearError(t);
    }, true);
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (t && /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) {
            if (checkField(t) === null) clearError(t);
        }
    }, true);

    // Validate on submit (capture: runs before form-level AJAX/confirm handlers).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || form.hasAttribute('data-native-validation')) return;
        stamp(form);
        var fields = form.querySelectorAll('input,select,textarea');
        var firstBad = null;
        Array.prototype.forEach.call(fields, function (field) {
            if (!isVisible(field) && field.type !== 'hidden') {
                // Hidden-by-CSS fields (e.g. in closed modals of multi-form pages)
                // are still validated only if their form is being submitted and
                // they carry a value expectation — skip purely hidden ones.
                var style = window.getComputedStyle ? window.getComputedStyle(field) : null;
                if (style && style.display === 'none') return;
            }
            var msg = checkField(field);
            if (msg) {
                showError(field, msg);
                if (!firstBad) firstBad = field;
            } else {
                clearError(field);
            }
        });
        if (firstBad) {
            e.preventDefault();
            e.stopPropagation();
            try { firstBad.focus({ preventScroll: false }); } catch (err) { firstBad.focus(); }
        }
    }, true);

    stampAll(document);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { stampAll(document); });
    }
    document.addEventListener('turbo:load', function () { stampAll(document); });
    document.addEventListener('turbo:frame-load', function () { stampAll(document); });
    if (window.Turbo) {
        document.addEventListener('turbo:render', function () { stampAll(document); });
    }
    // Turbo caches the DOM on navigation — strip client-side error artifacts
    // so back/forward restores never show stale errors (server-rendered
    // x-input-error nodes are untouched).
    document.addEventListener('turbo:before-cache', function () {
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-client-error]'),
            function (node) { node.parentNode.removeChild(node); }
        );
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-client-border="1"]'),
            function (field) {
                field.style.borderColor = '';
                field.removeAttribute('data-client-border');
                field.removeAttribute('aria-invalid');
            }
        );
    });
})();
