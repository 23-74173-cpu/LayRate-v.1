/**
 * Back/Forward sign-in guard.
 *
 *   <script src="/js/auth-guard.js" data-mode="app|guest"
 *           data-status-url="/auth/status" data-login-url="/login"
 *           data-home-url="/dashboard" data-logout-url="/logout"></script>
 *
 * The server already sends no-store on every page (PreventBackHistory), so a
 * normal Back/Forward asks the server again. This covers the cases where the
 * browser still shows a page without asking: the back-forward cache (Safari
 * keeps even no-store pages), Turbo's own snapshots, and a tab left open
 * while the session ended somewhere else.
 *
 * app mode (farm pages):
 *   - sign-out wipes this tab's sessionStorage and hides the page as it
 *     leaves, so a back-forward cache copy can never be seen;
 *   - a page restored from that cache after sign-out goes straight to login;
 *   - Back/Forward, a restored page, or coming back to the tab asks
 *     /auth/status, and a dead session goes to login.
 * guest mode (login and info pages):
 *   - a page restored while signed in goes to the dashboard;
 *   - coming back to the tab after signing in elsewhere goes to the dashboard.
 *
 * Navigation uses location.replace, so the page that was left behind does
 * not stay in history. localStorage keeps only the "signed in" flag used to
 * decide before the first paint; UI preferences there are left alone.
 */
(function () {
    if (window.__layrateAuthGuard) return;
    window.__layrateAuthGuard = true;

    var script = document.currentScript;
    if (!script) return;
    var mode = script.getAttribute('data-mode') === 'guest' ? 'guest' : 'app';
    var statusUrl = script.getAttribute('data-status-url') || '/auth/status';
    var loginUrl = script.getAttribute('data-login-url') || '/login';
    var homeUrl = script.getAttribute('data-home-url') || '/dashboard';
    var logoutUrl = new URL(script.getAttribute('data-logout-url') || '/logout', location.href).href;
    var FLAG = 'layrate_signed_in';
    var root = document.documentElement;
    var leaving = false;
    var lastCheck = 0;

    function setFlag(on) { try { localStorage.setItem(FLAG, on ? '1' : '0'); } catch (e) {} }
    function getFlag() { try { return localStorage.getItem(FLAG); } catch (e) { return null; } }
    function hide() { root.style.visibility = 'hidden'; }
    function show() { root.style.visibility = ''; }

    function go(url) {
        if (leaving) return;
        leaving = true;
        hide();
        window.location.replace(url);
    }

    // true / false, or null when the server can't be reached (offline Pi,
    // network blip): never send anyone away on a failed request.
    function check() {
        lastCheck = Date.now();
        return fetch(statusUrl, {
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'manual',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            // A redirect here means a middleware ended the session
            // (deactivated user, password changed on another device).
            if (res.type === 'opaqueredirect' || res.status === 401 || res.status === 419) return false;
            if (!res.ok) return null;
            return res.json().then(function (d) { return !!(d && d.authenticated); });
        }).catch(function () { return null; });
    }

    function verify() {
        return check().then(function (signedIn) {
            if (signedIn === null) { show(); return; }
            setFlag(signedIn);
            if (mode === 'app' && !signedIn) go(loginUrl);
            else if (mode === 'guest' && signedIn) go(homeUrl);
            else show();
        });
    }

    if (mode === 'app') {
        setFlag(true);

        // Sign-out forms post through form.submit() (after the confirm
        // modal), which skips the submit event; the formdata event still
        // fires. Captured on document since it doesn't bubble.
        document.addEventListener('formdata', function (e) {
            var form = e.target;
            if (!form || !form.action) return;
            if (form.action.split('?')[0] !== logoutUrl) return;
            leaving = true;
            setFlag(false);
            try { sessionStorage.clear(); } catch (err) {}
        }, true);

        // Hide only as the page is put away (not while the sign-out request
        // is still running), so a cached copy is stored blank.
        window.addEventListener('pagehide', function () { if (leaving) hide(); });
    } else {
        // Stored blank too; pageshow below shows it again, or leaves for the
        // dashboard without the login form ever appearing.
        window.addEventListener('pagehide', function (e) { if (e.persisted) hide(); });
        setFlag(false);
        // A guest page only renders when no one is signed in, so whatever
        // this tab remembered from the farm pages is stale.
        try { sessionStorage.clear(); } catch (err) {}
    }

    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        leaving = false;
        var flag = getFlag();
        if (mode === 'app' && flag === '0') { go(loginUrl); return; }
        if (mode === 'guest' && flag === '1') { go(homeUrl); return; }
        show();
        verify();
    });

    // Back/Forward inside the app is drawn by Turbo from its snapshot cache
    // without asking the server.
    window.addEventListener('popstate', function () { if (mode === 'app') verify(); });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible' || leaving) return;
        if (Date.now() - lastCheck < 10000) return;
        verify();
    });
})();
