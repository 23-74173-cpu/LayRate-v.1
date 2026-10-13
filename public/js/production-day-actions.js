(function () {
    if (window.__productionDayActionsBound) return;
    window.__productionDayActionsBound = true;
    var selectedDate = null;
    var requestToken = 0;
    var deleting = false;
    var opener = null;
    function root() { return document.getElementById('eggProductionHistoryCalendar'); }
    function modal() { return document.getElementById('productionDayModal'); }
    function escape(value) {
        var el = document.createElement('span'); el.textContent = String(value); return el.innerHTML;
    }
    function message(text) { var m = modal(); if (m) m.querySelector('[data-day-message]').textContent = text; }
    function busy(value) { var m = modal(); if (m) m.querySelectorAll('[data-day-details], [data-day-delete]').forEach(function (button) { button.disabled = value; }); }
    function close() {
        if (deleting) return;
        requestToken++;
        var m = modal(); if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
        if (opener && opener.isConnected) opener.focus();
    }
    window.closeProductionDayModal = close;
    function open(cell) {
        var m = modal(); if (!m || deleting) return;
        selectedDate = cell.dataset.date; opener = cell; requestToken++;
        m.querySelector('#productionDayTitle').textContent = 'Daily production · ' + selectedDate;
        m.querySelector('[data-day-content]').replaceChildren();
        message('Choose an action. Both actions apply to all cages on this date.'); busy(false);
        m.classList.remove('hidden'); m.classList.add('flex');
        m.querySelector('[data-day-details]').focus();
    }
    function getDetails(date) {
        return fetch(root().dataset.dayUrl + '/' + encodeURIComponent(date), { headers: { Accept: 'application/json' } })
            .then(function (response) { if (!response.ok) throw new Error('Could not load this day. Please try again.'); return response.json(); });
    }
    function details() {
        var token = ++requestToken; busy(true); message('Loading daily production…');
        getDetails(selectedDate).then(function (data) {
            if (token !== requestToken || !modal()) return;
            var rows = data.by_cage.map(function (row) {
                return '<tr><td class="px-3 py-2">' + escape(row.cage) + '</td><td class="px-3 py-2">' + row.records + '</td><td class="px-3 py-2">' + row.eggs + '</td><td class="px-3 py-2">' + row.hens + '</td></tr>';
            }).join('');
            modal().querySelector('[data-day-content]').innerHTML = '<p class="font-semibold mb-3">' + data.eggs + ' eggs · ' + data.records + ' production records</p>'
                + (data.records ? '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th class="text-left px-3 py-2">Cage</th><th class="text-left px-3 py-2">Records</th><th class="text-left px-3 py-2">Eggs</th><th class="text-left px-3 py-2">Logged hens</th></tr></thead><tbody>' + rows + '</tbody></table></div>' : '<p class="text-sm">No production records for this day.</p>')
                + (data.by_size.length ? '<p class="font-medium mt-4 mb-2">Egg sizes</p><ul class="text-sm">' + data.by_size.map(function (row) { return '<li>' + escape(row.size) + ': ' + row.eggs + '</li>'; }).join('') + '</ul>' : '');
            message('Overall totals across all cages. Logged hens are summed from the day’s production records.');
        }).catch(function (error) { if (token === requestToken) message(error.message); })
            .finally(function () { if (token === requestToken) busy(false); });
    }
    function deleteDay() {
        if (deleting || !root() || root().dataset.canDelete !== '1') return;
        var date = selectedDate, token = ++requestToken;
        busy(true); message('Checking the records for this day…');
        getDetails(date).then(function (data) {
            if (token !== requestToken) return;
            busy(false);
            if (!data.records) { message('There are no production records to delete on this day.'); return; }
            message('Review the deletion confirmation before continuing.');
            confirmModal('Delete all ' + data.records + ' production records (' + data.eggs + ' eggs) on ' + date + ' across ALL cages? Associated egg-size records will also be deleted. This cannot be undone.', { submit: function () {
                deleting = true; busy(true); message('Deleting daily production records…');
                fetch(root().dataset.dayUrl + '/' + encodeURIComponent(date), {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                }).then(function (response) {
                    return response.json().then(function (data) { if (!response.ok || !data.success) throw new Error(data.message || 'Deletion failed. Please try again.'); return data; });
                }).then(function (data) {
                    message('Deleted ' + data.deleted + ' production records on ' + date + '.');
                    if (modal()) modal().querySelector('[data-day-content]').replaceChildren();
                    var frame = root() && root().querySelector('#dashboard-calendar');
                    if (frame && frame.reload) frame.reload();
                }).catch(function (error) { message(error.message); })
                    .finally(function () { deleting = false; busy(false); });
            } }, 'Delete all records', 'destructive');
        }).catch(function (error) { if (token === requestToken) { message(error.message); busy(false); } });
    }
    function prepareCells() {
        if (!root()) return;
        root().querySelectorAll('.calendar-day[data-date]').forEach(function (cell) {
            cell.setAttribute('role', 'button'); cell.tabIndex = 0;
            cell.setAttribute('aria-label', 'Production actions for ' + cell.dataset.date);
            cell.style.cursor = 'pointer';
        });
    }
    document.addEventListener('click', function (e) {
        var cell = e.target.closest('#eggProductionHistoryCalendar .calendar-day[data-date]');
        if (cell) { open(cell); return; }
        var m = modal(); if (!m || !m.contains(e.target)) return;
        if (e.target.closest('[data-day-close]')) close();
        else if (e.target.closest('[data-day-details]')) details();
        else if (e.target.closest('[data-day-delete]')) deleteDay();
    });
    document.addEventListener('keydown', function (e) {
        var cell = e.target.closest('#eggProductionHistoryCalendar .calendar-day[data-date]');
        if (cell && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); open(cell); }
        if (e.key === 'Escape') {
            var confirmation = document.getElementById('confirm-modal');
            if (!confirmation || confirmation.classList.contains('hidden')) close();
        }
    });
    document.addEventListener('turbo:frame-load', prepareCells);
    document.addEventListener('turbo:load', prepareCells);
    document.addEventListener('turbo:before-cache', close);
    prepareCells();
})();
