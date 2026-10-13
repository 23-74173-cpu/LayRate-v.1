(function () {
    if (window.__forecastResetBound) return;
    window.__forecastResetBound = true;
    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-reset-forecast]');
        if (!button || button.disabled) return;
        var form = document.getElementById('forecastForm');
        if (!form) return;
        var scope = document.getElementById('formScope').value;
        var cage = document.getElementById('cageSelect');
        var breed = document.getElementById('breedSelect');
        var horizon = form.querySelector('[name="horizon"]:checked');
        var target = scope === 'farm' ? 'Whole Farm' : scope === 'cage' ? cage.value : breed.value;
        confirmModal('Reset the next ' + (horizon ? horizon.value : 7) + ' forecast days for ' + target + ' and regenerate using the corrected production records? Previous predictions will be archived.', { submit: function () {
            button.disabled = true; button.textContent = 'Resetting…';
            var data = new FormData();
            data.append('scope', scope);
            if (scope === 'cage') data.append('cage', cage.value);
            if (scope === 'breed') data.append('breed', breed.value);
            data.append('horizon', horizon ? horizon.value : '7');
            fetch(button.dataset.resetUrl, { method: 'POST', body: data, headers: {
                Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            } }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not reset the forecast.');
                });
            }).then(function () {
                var saved = document.getElementById('forecastSavedState');
                if (saved) saved.dataset.count = '0';
                form.requestSubmit();
            }).catch(function (error) {
                if (window.showNotification) showNotification(error.message, 'error');
            }).finally(function () { button.disabled = false; button.textContent = 'Reset and regenerate'; });
        } }, 'Reset and regenerate', 'warning');
    });
})();
