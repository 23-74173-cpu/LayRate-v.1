/** Shared, Turbo-safe read-only filter behavior. */
(function () {
    'use strict';

    if (window.__filterUiBound) return;
    window.__filterUiBound = true;

    var mobile = window.matchMedia ? window.matchMedia('(max-width: 639.98px)') : { matches: false };
    var debounceTimer;
    var controlsSelector = 'input[name]:not([type="hidden"]), select[name], textarea[name]';

    function bars() { return Array.from(document.querySelectorAll('[data-filter-bar]')); }
    var legacySelector = '[data-hen-filter-toggle], [data-recent-filter-toggle], [data-preorder-filter-toggle], [data-report-filter-toggle], [data-dashboard-filter-toggle], [data-env-filter-toggle], [data-feed-filter-toggle]';
    function panel(bar) { return bar.querySelector('[data-filter-panel]'); }
    function sheet(bar) { return bar.querySelector('[data-filter-sheet]'); }
    function isOpen(bar) { return !!(sheet(bar) && !sheet(bar).hidden); }
    function fields(bar) { return Array.from(bar.querySelectorAll(controlsSelector)); }

    function activeControls(bar) {
        return fields(bar).filter(function (control) {
            if (control.type === 'checkbox' || control.type === 'radio') return control.checked;
            return String(control.value || '').trim() !== '';
        });
    }

    function updateSummary(bar) {
        var active = activeControls(bar);
        var badge = bar.querySelector('[data-filter-count]');
        var summary = bar.querySelector('[data-filter-summary-display]');
        var button = bar.querySelector('[data-filter-toggle]');
        var legacySummary = bar.querySelector('[data-hen-filter-summary], [data-recent-filter-summary], [data-preorder-filter-summary], [data-report-filter-summary], [data-dashboard-filter-summary], [data-env-filter-summary], [data-feed-filter-summary]');
        var count = active.filter(function (control) { return control.type !== 'date'; }).length;
        var dates = bar.querySelectorAll('input[type="date"][name]');
        if (dates.length && Array.from(dates).some(function (date) { return date.value !== ''; })) count += 1;
        if (badge) {
            badge.textContent = String(count);
            badge.classList.toggle('hidden', count === 0);
        }
        if (summary) {
            var names = active.map(function (control) {
                var label = control.id && bar.querySelector('label[for="' + CSS.escape(control.id) + '"]');
                var wrappingLabel = control.closest('label');
                return label ? label.textContent.trim() : (wrappingLabel ? wrappingLabel.textContent.trim() : control.name);
            });
            var explicit = active.length ? bar.getAttribute('data-active-summary') : '';
            summary.textContent = explicit || (names.length ? 'Active: ' + names.join(', ') : '');
        }
        if (legacySummary) legacySummary.textContent = count ? 'Active filters: ' + count : '';
        if (button) {
            var label = button.getAttribute('aria-expanded') === 'true' ? 'Hide filters' : 'Show filters';
            button.setAttribute('aria-label', count ? label + ', ' + count + ' active' : label);
            button.setAttribute('title', label);
            button.setAttribute('aria-expanded', button.getAttribute('aria-expanded') || 'false');
            var icon = button.querySelector('[data-lucide]');
            if (icon) {
                icon.setAttribute('data-lucide', button.getAttribute('aria-expanded') === 'true' ? 'chevron-up' : 'sliders-horizontal');
                if (window.lucide) window.lucide.createIcons({ els: [icon] });
            }
        }
    }

    function legacyBar(trigger) {
        return trigger && trigger.closest('form');
    }

    function legacyActions(bar) {
        return bar && bar.querySelector('[data-hen-filter-actions], [data-recent-filter-actions], [data-preorder-filter-actions], [data-report-filter-actions], [data-dashboard-filter-actions], [data-env-filter-actions], [data-feed-filter-actions]');
    }

    function moveLegacyActionsToPanel(bar, fieldsContainer) {
        var panelElement = bar.querySelector('[data-filter-panel]') || bar;
        var actions = legacyActions(bar);
        var items = [];
        if (actions && actions !== fieldsContainer && actions.parentNode !== fieldsContainer) items.push(actions);
        fieldsContainer.querySelectorAll('[data-filter-action]:not([data-filter-action="export"]):not([data-filter-action="charts"]):not([data-filter-action="forecast"])').forEach(function (action) { items.push(action); });
        items.forEach(function (item) {
            if (!item.__filterHomeParent) {
                item.__filterHomeParent = fieldsContainer;
                item.__filterHomeNext = null;
            }
            if (item.parentNode !== panelElement) {
                item.classList.add('filter-legacy-actions');
                panelElement.appendChild(item);
            }
        });
    }

    function restoreLegacyHome(bar, fieldsContainer) {
        var homeMarker = fieldsContainer && fieldsContainer.id
            ? bar.querySelector('[data-filter-fields-home="' + CSS.escape(fieldsContainer.id) + '"]')
            : null;
        if (fieldsContainer && !homeMarker) homeMarker = bar.querySelector('[data-env-fields-home]');
        var fieldsParent = homeMarker ? homeMarker.parentNode : bar;
        if (fieldsContainer && fieldsContainer.parentNode !== fieldsParent) fieldsParent.insertBefore(fieldsContainer, homeMarker || null);
        if (fieldsContainer) {
            var actionMarker = bar.querySelector('[data-filter-action-home]');
            if (actionMarker && actionMarker.parentNode === fieldsContainer.parentNode && fieldsContainer.nextSibling !== actionMarker) fieldsContainer.parentNode.insertBefore(fieldsContainer, actionMarker);
        }
        var actions = Array.from(bar.querySelectorAll('.filter-legacy-actions'));
        actions.forEach(function (action) {
            var homeParent = action.__filterHomeParent;
            var homeNext = action.__filterHomeNext;
            if (homeParent && homeParent.isConnected && action.parentNode !== homeParent) {
                homeParent.insertBefore(action, homeNext && homeNext.parentNode === homeParent ? homeNext : null);
            }
            action.classList.remove('filter-legacy-actions');
        });
    }

    function makeLegacySheet(bar, title) {
        if (!bar || bar.querySelector('[data-filter-sheet]')) return;
        var template = bar.querySelector('[data-filter-sheet-template]');
        if (template && template.content) {
            var fragment = template.content.cloneNode(true);
            bar.appendChild(fragment);
            template.remove();
            return;
        }
        var sheetElement = document.createElement('div');
        sheetElement.className = 'fixed inset-0 z-50 sm:hidden';
        sheetElement.setAttribute('data-filter-sheet', '');
        sheetElement.setAttribute('role', 'dialog');
        sheetElement.setAttribute('aria-modal', 'true');
        var sheetId = 'filter-dialog-' + (bar.id || Math.random().toString(36).slice(2));
        sheetElement.setAttribute('aria-labelledby', sheetId);
        sheetElement.innerHTML = '<div data-filter-sheet-backdrop class="absolute inset-0" style="background:rgba(0,0,0,.4)"></div><section data-filter-sheet-content class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-2xl bg-white p-5 pb-[calc(env(safe-area-inset-bottom)+1.25rem)]"><header class="mb-1 flex items-center justify-between"><h2 id="' + sheetId + '" class="text-base font-semibold">' + title + '</h2><button type="button" data-filter-sheet-close aria-label="Close filters" class="filter-control inline-flex w-11 shrink-0 items-center justify-center rounded-full">×</button></header><p class="mb-4 text-xs text-[#6B7280]">Changes are pending until you apply them. Cancel discards changes.</p><div data-filter-sheet-fields class="mt-4"></div><div data-filter-actions class="filter-actions-row mt-4 flex flex-wrap justify-end gap-2"><button type="button" data-filter-clear class="filter-action filter-control inline-flex items-center justify-center rounded-lg border border-[#D9D9D9] text-sm">Clear</button><button type="button" data-filter-cancel class="filter-action filter-control inline-flex items-center justify-center rounded-lg border border-[#D9D9D9] text-sm">Cancel</button><button type="button" data-filter-apply class="filter-action filter-control inline-flex items-center justify-center rounded-lg bg-navy text-sm text-white">Apply</button></div><p data-filter-error class="hidden mt-3 text-xs text-red-700" role="alert"></p></section>';
        bar.appendChild(sheetElement);
    }

    function legacyFields(bar) {
        return bar && bar.querySelector('[data-hen-filter-fields], [data-recent-filter-fields], [data-preorder-filter-fields], [data-report-filter-fields], [data-dashboard-filter-fields], [data-env-filter-fields], [data-feed-filter-fields]');
    }

    function legacyIsOpen(bar) { return !!(bar && bar.querySelector('[data-filter-sheet]:not([hidden])')); }

    function legacyUpdateSummary(bar) {
        if (!bar) return;
        var active = Array.from(bar.querySelectorAll('input[name]:not([type="hidden"]), select[name], textarea[name]')).filter(function (control) {
            if (control.type === 'checkbox' || control.type === 'radio') return control.checked;
            if (control.name === 'feed_preset') return ['today', '7d', '30d', 'latest'].indexOf(control.value) >= 0;
            return String(control.value || '').trim() !== '' && control.value !== 'all';
        });
        var count = active.length;
        var dates = active.filter(function (control) { return control.type === 'date'; });
        if (dates.length > 1) count -= dates.length - 1;
        var summary = bar.querySelector('[data-hen-filter-summary], [data-recent-filter-summary], [data-preorder-filter-summary], [data-report-filter-summary], [data-dashboard-filter-summary], [data-env-filter-summary], [data-feed-filter-summary]');
        if (summary) summary.textContent = count ? 'Active filters: ' + count : '';
        var toggle = bar.querySelector(legacySelector);
        if (toggle) {
            var label = toggle.getAttribute('aria-expanded') === 'true' ? 'Hide filters' : 'Show filters';
            toggle.setAttribute('aria-label', count ? label + ', ' + count + ' active' : label);
            toggle.setAttribute('title', label);
        }
    }

    function bindLegacyFilterForm(bar) {
        if (!bar) return;
        var title = bar.getAttribute('aria-label') || 'Filter records';
        makeLegacySheet(bar, title);
        if (bar.dataset.sharedFilterFormBound) return;
        bar.dataset.sharedFilterFormBound = 'true';
        bar.querySelectorAll('[data-filter-action], [data-hen-filter-actions], [data-recent-filter-actions], [data-preorder-filter-actions], [data-report-filter-actions], [data-dashboard-filter-actions], [data-env-filter-actions], [data-feed-filter-actions]').forEach(function (action) {
            if (!action.__filterHomeParent) {
                action.__filterHomeParent = action.parentNode;
                action.__filterHomeNext = null;
            }
        });
        bar.querySelectorAll(legacySelector).forEach(function (trigger) {
            compactLegacyToggle(trigger);
            if (trigger.dataset.sharedDisclosureClick) return;
            trigger.dataset.sharedDisclosureClick = 'true';
            trigger.addEventListener('click', function () {
                var fieldsContainer = legacyFields(bar) || (bar.matches('form[data-turbo-frame="environment-logs"]') && bar.querySelector('[data-env-filter-fields]'));
                if (!fieldsContainer) return;
                if (mobile.matches) {
                    openLegacyDrawer(bar, trigger, fieldsContainer);
                    setLegacyToggle(trigger, true);
                }
                else {
                    var expanded = trigger.getAttribute('aria-expanded') !== 'true';
                    setLegacyToggle(trigger, expanded);
                    fieldsContainer.hidden = !expanded;
                    if (expanded) {
                        moveLegacyActionsToPanel(bar, fieldsContainer);
                    }
                    if (!expanded) {
                        restoreLegacyHome(bar, fieldsContainer);
                        trigger.focus();
                    }
                }
            });
        });
        if (bar.id === 'dashboardGlobalFilters') {
            var dashboardToggle = bar.querySelector('[data-dashboard-filter-toggle]');
            if (dashboardToggle && !dashboardToggle.dataset.sharedDisclosureClick) {
                compactLegacyToggle(dashboardToggle);
                dashboardToggle.dataset.sharedDisclosureClick = 'true';
                dashboardToggle.addEventListener('click', function () {
                    var dashboardFields = legacyFields(bar);
                    if (!dashboardFields) return;
                    if (mobile.matches) {
                        openLegacyDrawer(bar, dashboardToggle, dashboardFields);
                        setLegacyToggle(dashboardToggle, true);
                    }
                    else {
                        var expanded = dashboardToggle.getAttribute('aria-expanded') !== 'true';
                        dashboardFields.hidden = !expanded;
                        setLegacyToggle(dashboardToggle, expanded);
                        if (expanded) {
                            moveLegacyActionsToPanel(bar, dashboardFields);
                        }
                        else restoreLegacyHome(bar, dashboardFields);
                    }
                });
            }
        }
        if (bar.matches('form[data-turbo-frame="environment-logs"]')) {
            var environmentToggle = bar.querySelector('[data-env-filter-toggle]');
            if (environmentToggle && !environmentToggle.dataset.sharedDisclosureClick) {
                compactLegacyToggle(environmentToggle);
                environmentToggle.dataset.sharedDisclosureClick = 'true';
                environmentToggle.addEventListener('click', function () {
                    var environmentFields = bar.querySelector('[data-env-filter-fields]');
                    if (!environmentFields) return;
                    if (mobile.matches) {
                        openLegacyDrawer(bar, environmentToggle, environmentFields);
                        setLegacyToggle(environmentToggle, true);
                    }
                    else {
                        var expanded = environmentToggle.getAttribute('aria-expanded') !== 'true';
                        environmentFields.hidden = !expanded;
                        setLegacyToggle(environmentToggle, expanded);
                        if (expanded) {
                            moveLegacyActionsToPanel(bar, environmentFields);
                        }
                        else restoreLegacyHome(bar, environmentFields);
                    }
                });
            }
        }
        bar.addEventListener('click', function (event) {
            var clearButton = event.target.closest('[data-recent-filter-clear], [data-preorder-filter-clear], [data-feed-filter-reset]');
            if (!clearButton) return;
            event.preventDefault();
            clearLegacy(bar);
            if (legacyIsOpen(bar)) closeLegacyDrawer(bar, false, true);
            else applyLegacy(bar);
        });
        bar.addEventListener('input', function () { legacyUpdateSummary(bar); });
        bar.addEventListener('change', function () { legacyUpdateSummary(bar); });
        legacyUpdateSummary(bar);
    }

    function setLegacyToggle(trigger, expanded) {
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        var label = expanded ? 'Hide filters' : 'Show filters';
        trigger.setAttribute('aria-label', label);
        trigger.setAttribute('title', label);
        trigger.innerHTML = '<i data-lucide="' + (expanded ? 'chevron-up' : 'sliders-horizontal') + '" class="h-4 w-4" aria-hidden="true"></i>';
        if (window.lucide) window.lucide.createIcons({ els: [trigger.querySelector('[data-lucide]')] });
    }

    function compactLegacyToggle(trigger) {
        if (!trigger || trigger.dataset.compactToggle === 'true') return;
        trigger.dataset.compactToggle = 'true';
        trigger.classList.add('filter-icon-toggle');
        trigger.setAttribute('aria-label', 'Show filters');
        trigger.setAttribute('title', 'Show filters');
        trigger.innerHTML = '<i data-lucide="sliders-horizontal" class="h-4 w-4" aria-hidden="true"></i>';
        if (window.lucide) window.lucide.createIcons({ els: [trigger.querySelector('[data-lucide]')] });
    }

    function openLegacyDrawer(bar, trigger, fieldsContainer) {
        var drawer = bar.querySelector('[data-filter-sheet]');
        if (!drawer) return;
        bar.__filterTrigger = trigger;
        bar.__filterSnapshot = snapshot(bar);
        bar.querySelectorAll('[data-filter-action], [data-hen-filter-actions], [data-recent-filter-actions], [data-preorder-filter-actions], [data-report-filter-actions], [data-dashboard-filter-actions], [data-env-filter-actions], [data-feed-filter-actions]').forEach(function (action) {
            if (!action.__filterHomeParent) {
                action.__filterHomeParent = fieldsContainer;
                action.__filterHomeNext = null;
            }
        });
        var sheetFields = drawer.querySelector('[data-filter-sheet-fields]');
        fieldsContainer.hidden = false;
        if (sheetFields && fieldsContainer.parentNode !== sheetFields) sheetFields.appendChild(fieldsContainer);
        var sheetContent = drawer.querySelector('[data-filter-sheet-content]');
        fieldsContainer.querySelectorAll('[data-filter-action]:not([data-filter-action="export"]):not([data-filter-action="charts"]):not([data-filter-action="forecast"])').forEach(function (action) {
            if (!action.__filterHomeParent) {
                action.__filterHomeParent = fieldsContainer;
                action.__filterHomeNext = null;
            }
            if (sheetContent && action.parentNode !== sheetContent) {
                action.classList.add('filter-legacy-actions');
                sheetContent.appendChild(action);
            }
        });
        var desktopActions = bar.querySelector('[data-filter-desktop-actions]');
        if (desktopActions && sheetContent && desktopActions.parentNode !== sheetContent) sheetContent.appendChild(desktopActions);
        drawer.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        var closeButton = drawer.querySelector('[data-filter-sheet-close]');
        if (closeButton) closeButton.focus();
    }

    function dismissLegacyDrawer(bar, restore, applyChanges) {
        var result = closeLegacyDrawer(bar, restore, applyChanges);
        if (!result) return false;
        var trigger = bar.__filterTrigger;
        if (trigger) setLegacyToggle(trigger, false);
        return true;
    }

    function closeLegacyDrawer(bar, restore, shouldApply) {
        var drawer = bar && bar.querySelector('[data-filter-sheet]');
        if (!drawer || drawer.hidden) return true;
        if (shouldApply && !applyLegacy(bar)) return false;
        if (!shouldApply && restore) restoreFields(bar, bar.__filterSnapshot || []);
        var fieldsContainer = legacyFields(bar) || (bar.matches('form[data-turbo-frame="environment-logs"]') && bar.querySelector('[data-env-filter-fields]'));
        restoreLegacyHome(bar, fieldsContainer);
        if (fieldsContainer) fieldsContainer.hidden = true;
        var desktopActions = bar.querySelector('[data-filter-desktop-actions]');
        var desktopHome = bar.querySelector('[data-filter-desktop-home]');
        if (desktopActions && desktopHome && desktopActions.parentNode !== desktopHome.parentNode) desktopHome.parentNode.insertBefore(desktopActions, desktopHome);
        var error = drawer.querySelector('[data-filter-error]');
        if (error) error.classList.add('hidden');
        drawer.hidden = true;
        if (bar.__filterTrigger) setLegacyToggle(bar.__filterTrigger, false);
        if (!Array.from(document.querySelectorAll('[data-filter-sheet]')).some(function (item) { return !item.hidden; })) document.documentElement.style.overflow = '';
        if (bar.__filterTrigger && bar.__filterTrigger.isConnected) bar.__filterTrigger.focus();
        legacyUpdateSummary(bar);
        return true;
    }

    function restoreFields(bar, values) {
        Array.from(bar.querySelectorAll(controlsSelector)).forEach(function (control) {
            var saved = values.find(function (entry) { return entry.name === control.name && entry.value === control.value; });
            if (control.type === 'checkbox' || control.type === 'radio') control.checked = !!(saved && saved.checked);
            else if (control.tagName === 'SELECT' && saved && saved.selected) Array.from(control.options).forEach(function (option, index) { option.selected = !!saved.selected[index]; });
            else {
                var first = values.find(function (entry) { return entry.name === control.name; });
                control.value = first ? first.value : '';
            }
        });
    }

    function clearLegacy(bar) {
        if (!bar) return;
        bar.querySelectorAll('input[name]:not([type="hidden"]), select[name], textarea[name]').forEach(function (control) {
            if (control.type === 'checkbox' || control.type === 'radio') control.checked = false;
            else if (control.tagName === 'SELECT') {
                var allOption = Array.from(control.options).find(function (option) { return option.value === '' || option.value === 'all'; });
                control.value = allOption ? allOption.value : '';
            } else control.value = '';
        });
        if (bar.id === 'inventoryFiltersForm') {
            var allStatus = bar.querySelector('input[name="status"][value="all"]');
            if (allStatus) allStatus.checked = true;
        }
        if (bar.id === 'feed-global-filters') {
            var preset = bar.elements.feed_preset;
            if (preset) preset.value = '';
        }
        legacyUpdateSummary(bar);
    }

    function trapFocus(event, drawer) {
        if (!drawer) return;
        var focusables = Array.from(drawer.querySelectorAll('button, input:not([type="hidden"]), select, textarea, a[href], [tabindex]:not([tabindex="-1"])')).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
        if (!focusables.length) return;
        if (event.shiftKey && document.activeElement === focusables[0]) { event.preventDefault(); focusables[focusables.length - 1].focus(); }
        else if (!event.shiftKey && document.activeElement === focusables[focusables.length - 1]) { event.preventDefault(); focusables[0].focus(); }
    }

    function applyLegacy(bar) {
        var feed = bar.matches('[data-feed-filter-form]') ? bar : bar.querySelector('[data-feed-filter-form]');
        if (feed) {
            var preset = feed.elements.feed_preset;
            if (preset && preset.value) { feed.elements.feed_from.value = ''; feed.elements.feed_to.value = ''; }
            legacyUpdateSummary(feed);
            if (window.feedReloadLiveData) window.feedReloadLiveData();
            return true;
        }
        var dashboard = bar.querySelector('[data-dashboard-filter-fields]');
        if (dashboard) {
            var dashboardCage = dashboard.querySelector('#dashboardCageSelect');
            var dashboardFrom = dashboard.querySelector('#dashboardFromDate');
            var dashboardTo = dashboard.querySelector('#dashboardToDate');
            if (dashboardCage && typeof window.filterDashboard === 'function') window.filterDashboard(dashboardCage.value);
            if (dashboardFrom && typeof window.setDashboardFromDate === 'function') window.setDashboardFromDate(dashboardFrom.value);
            if (dashboardTo && typeof window.setDashboardToDate === 'function') window.setDashboardToDate(dashboardTo.value);
            legacyUpdateSummary(bar);
            return true;
        }
        var drawer = bar.querySelector('[data-filter-sheet]');
        if (drawer) {
        var fields = legacyFields(bar);
        var from = fields && fields.querySelector('[name="date_from"], [name="from"], [name="feed_from"]');
        var to = fields && fields.querySelector('[name="date_to"], [name="to"], [name="feed_to"]');
            if (from && to && from.value && to.value && from.value > to.value) {
                var error = bar.querySelector('[data-filter-error]');
                if (error) { error.textContent = 'Start date must be on or before end date.'; error.classList.remove('hidden'); }
                from.focus();
                return false;
            }
        }
        var event = new CustomEvent('filter:apply-legacy', { bubbles: true, detail: { form: bar } });
        bar.dispatchEvent(event);
        if (bar.matches('form[data-turbo-frame="environment-logs"]')) {
            var envUrl = new URL(bar.action, window.location.origin);
            envUrl.search = new URLSearchParams(new FormData(bar)).toString();
            if (window.Turbo) window.Turbo.visit(envUrl.toString(), { frame: 'environment-logs' });
            else if (bar.requestSubmit) bar.requestSubmit();
        } else if (typeof window.recentLogsFilter === 'function' && bar.id === 'recentLogsForm') window.recentLogsFilter();
        else if (typeof window.preOrdersFilter === 'function' && bar.id === 'preOrdersForm') window.preOrdersFilter();
        else if (bar.id === 'inventoryFiltersForm' && typeof window.filterInventory === 'function') window.filterInventory();
        else if (bar.id === 'reportForm') {
            if (typeof window.reportFetch === 'function') window.reportFetch();
            else if (bar.requestSubmit) bar.requestSubmit();
        }
        legacyUpdateSummary(bar);
        return true;
    }

    function setExpanded(bar, expanded) {
        var trigger = bar.querySelector('[data-filter-toggle]');
        var target = panel(bar);
        if (!trigger || !target) return;
        setSharedToggle(trigger, expanded);
        target.hidden = !expanded;
        if (!expanded) trigger.focus();
        updateSummary(bar);
    }

    function setSharedToggle(trigger, expanded) {
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        var label = expanded ? 'Hide filters' : 'Show filters';
        trigger.setAttribute('aria-label', label);
        trigger.setAttribute('title', label);
        var icon = trigger.querySelector('[data-lucide]');
        if (icon) {
            icon.setAttribute('data-lucide', expanded ? 'chevron-up' : 'sliders-horizontal');
            if (window.lucide) window.lucide.createIcons({ els: [icon] });
        }
    }

    function snapshot(bar) {
        return Array.from(bar.querySelectorAll(controlsSelector)).map(function (control) {
            return { name: control.name, type: control.type, value: control.value, checked: control.checked, selected: control.tagName === 'SELECT' ? Array.from(control.options).map(function (option) { return option.selected; }) : null };
        });
    }

    function restore(bar, values) {
        Array.from(bar.querySelectorAll(controlsSelector)).forEach(function (control) {
            var saved = values.find(function (entry) { return entry.name === control.name && entry.value === control.value; });
            if (control.type === 'checkbox' || control.type === 'radio') control.checked = !!(saved && saved.checked);
            else if (control.tagName === 'SELECT' && saved && saved.selected) Array.from(control.options).forEach(function (option, index) { option.selected = !!saved.selected[index]; });
            else {
                var first = values.find(function (entry) { return entry.name === control.name; });
                control.value = first ? first.value : '';
            }
        });
        updateSummary(bar);
    }

    function queryFor(bar) {
        var params = new URLSearchParams(new FormData(bar));
        params.delete('page');
        Array.from(params.keys()).forEach(function (key) {
            if (params.get(key) === '') params.delete(key);
        });
        return params.toString();
    }

    function syncUrl(bar, query) {
        if (!bar.hasAttribute('data-sync-url')) return;
        var url = new URL(window.location.href);
        url.search = query;
        window.history.replaceState({}, '', url);
    }

    function apply(bar) {
        var form = bar;
        var dateError = bar.querySelector('[data-filter-date-error]');
        if (dateError) dateError.classList.add('hidden');
        var dateRange = bar.querySelector('[data-filter-date-range]');
        if (dateRange) {
            var dates = dateRange.querySelectorAll('input[type="date"]');
            if (dates.length > 1 && dates[0].value && dates[1].value && dates[0].value > dates[1].value) {
                var error = bar.querySelector('[data-filter-error]') || dateRange.querySelector('[data-filter-date-error]');
                if (error) { error.textContent = 'Start date must be on or before end date.'; error.classList.remove('hidden'); }
                dates[0].focus();
                return false;
            }
        }
        var query = queryFor(form);
        var frame = document.getElementById(form.dataset.frame);
        if (frame && form.dataset.action) {
            frame.setAttribute('aria-busy', 'true');
            frame.classList.add('is-loading');
            frame.setAttribute('src', form.dataset.action + (query ? '?' + query : ''));
        }
        syncUrl(form, query);
        document.dispatchEvent(new CustomEvent('filter:applied', { detail: { formId: form.id, frameId: form.dataset.frame, params: query } }));
        updateSummary(form);
        return true;
    }

    function clear(bar, shouldApply) {
        bar.querySelectorAll(controlsSelector).forEach(function (control) {
            if (control.type === 'checkbox' || control.type === 'radio') control.checked = false;
            else control.value = '';
        });
        var error = bar.querySelector('[data-filter-error], [data-filter-date-error]');
        if (error) error.classList.add('hidden');
        updateSummary(bar);
        if (shouldApply) return apply(bar);
        return true;
    }

    function openDrawer(bar, trigger) {
        var drawer = sheet(bar);
        if (!drawer) return;
        setSharedToggle(trigger, true);
        bar.__filterTrigger = trigger;
        bar.__filterSnapshot = snapshot(bar);
        var fieldsContainer = bar.querySelector('[data-filter-fields]');
        var drawerFields = drawer.querySelector('[data-filter-sheet-fields]');
        if (fieldsContainer) fieldsContainer.hidden = false;
        if (fieldsContainer && drawerFields && fieldsContainer.parentNode !== drawerFields) drawerFields.appendChild(fieldsContainer);
        var desktopActions = bar.querySelector('[data-filter-desktop-actions]');
        var sheetContent = drawer.querySelector('[data-filter-sheet-content]');
        if (desktopActions && !desktopActions.__filterHomeParent) {
            desktopActions.__filterHomeParent = desktopActions.parentNode;
            desktopActions.__filterHomeNext = desktopActions.nextSibling;
        }
        if (desktopActions && sheetContent && desktopActions.parentNode !== sheetContent) sheetContent.appendChild(desktopActions);
        drawer.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        var closeButton = drawer.querySelector('[data-filter-sheet-close]');
        if (closeButton) closeButton.focus();
    }

    function closeDrawer(bar, shouldRestore, shouldApply) {
        var drawer = sheet(bar);
        if (!drawer || drawer.hidden) return true;
        if (shouldApply && !apply(bar)) return false;
        else if (shouldRestore !== false) restore(bar, bar.__filterSnapshot || []);
        var fieldsContainer = bar.querySelector('[data-filter-fields]');
        var target = panel(bar);
        if (fieldsContainer && target && fieldsContainer.parentNode !== target) target.insertBefore(fieldsContainer, target.firstChild);
        var desktopActions = bar.querySelector('[data-filter-desktop-actions]');
        if (desktopActions && desktopActions.__filterHomeParent && desktopActions.parentNode !== desktopActions.__filterHomeParent) {
            var actionHome = desktopActions.__filterHomeParent;
            actionHome.insertBefore(desktopActions, desktopActions.__filterHomeNext && desktopActions.__filterHomeNext.parentNode === actionHome ? desktopActions.__filterHomeNext : null);
        }
        if (fieldsContainer) fieldsContainer.hidden = true;
        var content = drawer.querySelector('[data-filter-sheet-content]');
        var error = drawer.querySelector('[data-filter-error]');
        if (error && content && error.parentNode !== content) content.appendChild(error);
        drawer.hidden = true;
        if (bar.__filterTrigger) setSharedToggle(bar.__filterTrigger, false);
        if (!bars().some(isOpen) && !Array.from(document.querySelectorAll('[data-filter-sheet]')).some(function (item) { return !item.hidden; })) document.documentElement.style.overflow = '';
        if (bar.__filterTrigger && bar.__filterTrigger.isConnected) bar.__filterTrigger.focus();
        return true;
    }

    function bindDisclosureButtons() {
        document.querySelectorAll(legacySelector).forEach(function (trigger) { compactLegacyToggle(trigger); bindLegacyFilterForm(legacyBar(trigger)); });
        var dashboardForm = document.getElementById('dashboardGlobalFilters');
        if (dashboardForm) bindLegacyFilterForm(dashboardForm);
        document.querySelectorAll('form[data-turbo-frame="environment-logs"]').forEach(function (form) { bindEnvironmentForm(form); });
    }

    function bindEnvironmentForm(form) {
        if (!form || form.dataset.sharedFilterBound) return;
        form.dataset.sharedFilterBound = 'true';
        form.id = form.id || 'environmentLogFilters';
        bindLegacyFilterForm(form);
        var from = form.querySelector('[name="date_from"]');
        var to = form.querySelector('[name="date_to"]');
        form.addEventListener('submit', function (event) {
            if (from && to && from.value && to.value && from.value > to.value) {
                event.preventDefault();
                to.setCustomValidity('End date must be on or after start date.');
                to.reportValidity();
                to.addEventListener('change', function clearDateValidity() { to.setCustomValidity(''); }, { once: true });
            }
        });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-filter-toggle]');
        if (trigger) {
            var bar = trigger.closest('[data-filter-bar]');
            if (mobile.matches) openDrawer(bar, trigger);
            else setExpanded(bar, trigger.getAttribute('aria-expanded') !== 'true');
            return;
        }
        var close = event.target.closest('[data-filter-sheet-close], [data-filter-cancel]');
        if (close) {
            var closeBar = close.closest('[data-filter-bar]') || close.closest('form');
            if (closeBar && closeBar.matches('[data-filter-bar]')) closeDrawer(closeBar, true, false);
            else dismissLegacyDrawer(closeBar, true, false);
            return;
        }
        var backdrop = event.target.closest('[data-filter-sheet-backdrop]');
        if (backdrop) {
            var backdropBar = backdrop.closest('[data-filter-bar]') || backdrop.closest('form');
            if (backdropBar && backdropBar.matches('[data-filter-bar]')) closeDrawer(backdropBar, true, false);
            else dismissLegacyDrawer(backdropBar, true, false);
            return;
        }
        var applyButton = event.target.closest('[data-filter-apply]');
        if (applyButton) {
            var applyBar = applyButton.closest('[data-filter-bar]') || applyButton.closest('form');
            if (applyBar && applyBar.matches('[data-filter-bar]')) closeDrawer(applyBar, true, true);
            else dismissLegacyDrawer(applyBar, true, true);
            return;
        }
        var clearButton = event.target.closest('[data-filter-clear]');
        if (clearButton) {
            var clearBar = clearButton.closest('[data-filter-bar]') || clearButton.closest('form');
            if (clearBar && clearBar.matches('[data-filter-bar]')) {
                clear(clearBar, false);
                closeDrawer(clearBar, true, true);
            } else {
                clearLegacy(clearBar);
                dismissLegacyDrawer(clearBar, true, true);
            }
            return;
        }
        var resetButton = event.target.closest('[data-filter-reset]');
        if (resetButton) { clear(resetButton.closest('[data-filter-bar]'), !isOpen(resetButton.closest('[data-filter-bar]'))); return; }
        var legacyReset = event.target.closest('[data-feed-filter-reset], [data-recent-filter-clear], [data-preorder-filter-clear]');
        if (legacyReset) {
            var resetBar = legacyReset.closest('form');
            clearLegacy(resetBar);
            if (legacyIsOpen(resetBar)) closeLegacyDrawer(resetBar, false, true);
            else applyLegacy(resetBar);
            return;
        }
    });

    document.addEventListener('keydown', function (event) {
        var openBar = bars().find(isOpen);
        var legacyOpenSheet = Array.from(document.querySelectorAll('[data-filter-sheet]:not([hidden])')).find(function (item) { return !item.closest('[data-filter-bar]'); });
        if (!openBar && legacyOpenSheet) {
            var legacyOpenBar = legacyOpenSheet.closest('form');
            if (event.key === 'Escape') { event.preventDefault(); dismissLegacyDrawer(legacyOpenBar, true, false); return; }
            if (event.key === 'Tab') trapFocus(event, legacyOpenSheet);
            return;
        }
        if (!openBar) return;
        if (event.key === 'Escape') { event.preventDefault(); closeDrawer(openBar, true, false); return; }
        if (event.key !== 'Tab') return;
        trapFocus(event, sheet(openBar));
    });

    document.addEventListener('change', function (event) {
        var bar = event.target.closest('[data-filter-bar]');
        if (bar) {
            updateSummary(bar);
            if (!isOpen(bar)) apply(bar);
            return;
        }
        var legacy = event.target.closest('form');
        if (!legacy || !legacyFields(legacy)) return;
        legacyUpdateSummary(legacy);
        if (legacyIsOpen(legacy)) return;
        if (legacy.id === 'dashboardGlobalFilters') applyLegacy(legacy);
        else if (legacy.id === 'feed-global-filters') {
            if (event.target.name === 'feed_preset' && event.target.value) { legacy.elements.feed_from.value = ''; legacy.elements.feed_to.value = ''; }
            else if (event.target.name === 'feed_from' || event.target.name === 'feed_to') legacy.elements.feed_preset.value = '';
            if (window.feedReloadLiveData) window.feedReloadLiveData();
        } else if (legacy.id === 'recentLogsForm' && typeof window.recentLogsFilter === 'function') window.recentLogsFilter();
        else if (legacy.id === 'preOrdersForm' && typeof window.preOrdersFilter === 'function') window.preOrdersFilter();
        else if (legacy.id === 'inventoryFiltersForm' && typeof window.filterInventory === 'function') window.filterInventory();
        else if (legacy.id === 'reportForm' && typeof window.reportFetch === 'function') window.reportFetch();
        else if (legacy.matches('form[data-turbo-frame="environment-logs"]')) {
            if (event.target.name !== 'cage_id' && legacy.requestSubmit) legacy.requestSubmit();
        }
    });

    document.addEventListener('input', function (event) {
        var bar = event.target.closest('[data-filter-bar]');
        if (bar) {
            updateSummary(bar);
            if (isOpen(bar)) return;
        } else {
            var legacy = event.target.closest('form');
            if (!legacy || !legacyFields(legacy)) return;
            legacyUpdateSummary(legacy);
            if (legacyIsOpen(legacy)) return;
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { apply(bar); }, 300);
    });

    document.addEventListener('submit', function (event) {
        var bar = event.target.closest('[data-filter-bar]');
        if (bar) {
            event.preventDefault();
            if (!isOpen(bar)) apply(bar);
            return;
        }
        if (event.target.matches('form[data-turbo-frame="environment-logs"]') && legacyIsOpen(event.target)) {
            event.preventDefault();
            closeLegacyDrawer(event.target, false, true);
        }
    });

    document.addEventListener('turbo:frame-load', function (event) {
        event.target.removeAttribute('aria-busy');
        event.target.classList.remove('is-loading');
        bars().forEach(updateSummary);
        var environmentForm = event.target.querySelector('form[data-turbo-frame="environment-logs"]');
        if (environmentForm) bindEnvironmentForm(environmentForm);
        bindDisclosureButtons();
    });

    if (mobile.addEventListener) mobile.addEventListener('change', function () {
        if (!mobile.matches) bars().forEach(function (bar) { if (isOpen(bar)) closeDrawer(bar, true, false); });
    });

    document.addEventListener('turbo:load', function () { bars().forEach(updateSummary); bindDisclosureButtons(); bindEnvironmentForm(document.querySelector('form[data-turbo-frame="environment-logs"]')); });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { bars().forEach(updateSummary); bindDisclosureButtons(); bindEnvironmentForm(document.querySelector('form[data-turbo-frame="environment-logs"]')); });
    else { bars().forEach(updateSummary); bindDisclosureButtons(); bindEnvironmentForm(document.querySelector('form[data-turbo-frame="environment-logs"]')); }
    document.addEventListener('filter:refresh', function () { bindDisclosureButtons(); bindEnvironmentForm(document.querySelector('form[data-turbo-frame="environment-logs"]')); });
})();
