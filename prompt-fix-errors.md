## Fix browser console errors (CORS, 500, JS null crashes)

### Current state
Browser console shows these errors when navigating the app at http://layratepi.local:

**1. CORS errors (separate device)** — cross-origin resources from http://192.168.4.1 blocked. Not fixable in this repo (that device's web server code is elsewhere).

**2. 500 errors** — `/profile` and one other endpoint return HTTP 500.

**3. JS null-crash in hideLoadingModal** (`loading-modal.blade.php:40-44`):
```js
window.hideLoadingModal = function() {
    var el = document.getElementById('loading-modal');
    el.classList.add('hidden');       // CRASHES if #loading-modal not in DOM
    el.classList.remove('flex');
};
```
Fire on every `turbo:load` / `turbo:render` / `pageshow` event. If Turbo replaces the body during navigation and the element is absent (edge case on certain Turbo transitions), this crashes all subsequent JS on the page.

**4. JS null-crash in feedSwitchTab** (`feed/_live-data.blade.php:44-45`):
```js
function feedSwitchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(el => el.classList.add('hidden'));
    document.getElementById('tab-'+tab).classList.remove('hidden');  // CRASHES if tab panel missing
```
The IIFE at `_live-data.blade.php:154-160` calls this on page load. If the query param `tab` doesn't match any panel ID, or the panels aren't rendered yet, it crashes.

**5. Possible null in consumption/farm-entry modal scripts** — `_live-data.blade.php:471-503` uses `document.querySelector(...)` without null guards. If modals aren't present in the DOM (rendered in the parent frame, not the turbo-frame content), these crash.

### Target state
- No JS TypeError crashes from null element access
- `/profile` endpoint returns 200
- All `document.getElementById` / `querySelector` / `querySelectorAll` calls in blade views are null-guarded
- `hideLoadingModal` never throws regardless of Turbo lifecycle state

### File scope
Only modify these files:
- `resources/views/components/loading-modal.blade.php`
- `resources/views/feed/_live-data.blade.php`
- `resources/views/feed.blade.php`
- `app/Http/Controllers/AccountController.php` (if needed for 500)
- `resources/views/profile.blade.php` (if needed for 500)

Do NOT touch:
- Database schema, migrations, seeders
- CSS/Tailwind files
- Any file outside the list above
- The Arduino firmware

### Constraints
- Add null guards only where needed. Do not refactor working code.
- Only make changes directly requested. Do not add extra features, abstractions, or refactoring.
- For the 500 error on `/profile`: investigate first by checking Laravel logs (`storage/logs/laravel.log`), then fix the root cause.

### Stop conditions
- Stop and ask before deleting any file or adding any dependency.
- Show me what you found in the Laravel logs for the 500 error before fixing it.

### Check
After fixing, verify:
1. `hideLoadingModal` handles missing element: `if (!el) return;`
2. `feedSwitchTab` guards `document.getElementById('tab-'+tab)` against null
3. All `querySelector` calls in `_live-data.blade.php` lines 44, 85, 288-295, 321-325, 471-503 have null guards
4. Run `php artisan route:list` to confirm `/profile` route exists
