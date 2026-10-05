/**
 * chart-fullscreen.js — full-screen chart viewer (Dashboard, Analytics,
 * Environment).
 *
 * A <x-chart-fullscreen-button chart="canvasId"> opens a full-viewport
 * overlay with a fresh Chart.js copy of that chart, rebuilt from the config
 * LayRateChart already stores for it (LayRateChart._configs[id]). The copy has
 * pinch / scroll-wheel zoom and drag-to-pan (chartjs-plugin-zoom + Hammer.js,
 * loaded the first time the viewer opens and attached to the copy only), and
 * keeps tooltips, crosshair and legend toggles like the original.
 *
 * Phones held upright always see the "turn your phone sideways" animation
 * first. The chart appears as soon as the phone is turned. Where the browser
 * allows it (Android Chrome), "Rotate screen" switches to real full screen and
 * locks landscape; "View upright anyway" is always there for phones with
 * rotation lock on (iPhone Safari cannot lock orientation from a web page).
 *
 * The copy follows live updates: LayRateChart.create()/setData() call
 * LayRateChartFullscreen.sync(id), so Environment's 10-second refresh or a
 * Compare/Forecast toggle redraws it too. Closed (and destroyed) on Esc, the
 * close button, leaving browser full screen (Android back), and before any
 * Turbo navigation.
 */
(function () {
    'use strict';

    if (window.LayRateChartFullscreen) return;

    var ZOOM_TYPES_XY = { scatter: 1, bubble: 1 };
    var NO_ZOOM_TYPES = { pie: 1, doughnut: 1, polarArea: 1, radar: 1 };

    var state = null;          // the open viewer
    var zoomPlugin = null;     // ChartZoom, unregistered globally
    var zoomForChart = null;   // the Chart.js module ChartZoom was built against
    var zoomLoading = null;

    // ── helpers ──────────────────────────────────────────────────────────
    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.async = false;
            s.onload = resolve;
            s.onerror = function () { reject(new Error('Failed to load ' + src)); };
            document.head.appendChild(s);
        });
    }

    // Hammer.js + chartjs-plugin-zoom, loaded once, on first use. The plugin
    // registers itself on every chart when loaded; it is unregistered again
    // straight away and only passed to the full-screen copy, so the regular
    // page charts are untouched. Reloaded if LayRateChart's stuck-paint
    // recovery swapped in a fresh Chart.js module (the plugin binds to it).
    function loadZoom() {
        if (zoomPlugin && zoomForChart === window.Chart) return Promise.resolve(zoomPlugin);
        if (zoomLoading) return zoomLoading;
        var needsReload = !!zoomPlugin;
        zoomLoading = (window.Hammer ? Promise.resolve() : loadScript('/js/hammer.min.js'))
            .then(function () {
                if (window.ChartZoom && !needsReload) return;
                return loadScript('/js/chartjs-plugin-zoom.min.js' + (needsReload ? '?r=' + Date.now() : ''));
            })
            .then(function () {
                zoomPlugin = window.ChartZoom || null;
                zoomForChart = window.Chart;
                if (zoomPlugin && window.Chart && typeof window.Chart.unregister === 'function') {
                    try { window.Chart.unregister(zoomPlugin); } catch (e) { /* not registered */ }
                }
                return zoomPlugin;
            })
            .catch(function () { return null; })   // zoom is a bonus: the chart still opens
            .then(function (plugin) { zoomLoading = null; return plugin; });
        return zoomLoading;
    }

    // Deep copy of plain objects/arrays; functions, gradients and other
    // non-plain values are kept by reference. The copy needs its own data:
    // some chart plugins write colours into their own chart's datasets, and
    // Chart.js watches data arrays, so two charts must not share them.
    function clone(value, seen) {
        if (value === null || typeof value !== 'object') return value;
        var proto = Object.getPrototypeOf(value);
        if (!Array.isArray(value) && proto !== Object.prototype && proto !== null) return value;
        seen = seen || new Map();
        if (seen.has(value)) return seen.get(value);
        var out = Array.isArray(value) ? [] : {};
        seen.set(value, out);
        Object.keys(value).forEach(function (k) { out[k] = clone(value[k], seen); });
        return out;
    }

    function isPhone() {
        var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        return !!coarse && Math.min(window.screen.width, window.screen.height) <= 600;
    }

    function isPortrait() {
        return !!(window.matchMedia && window.matchMedia('(orientation: portrait)').matches);
    }

    function canRotateForUser() {
        var el = document.documentElement;
        return !!((document.fullscreenEnabled || document.webkitFullscreenEnabled)
            && (el.requestFullscreen || el.webkitRequestFullscreen)
            && window.screen.orientation && typeof window.screen.orientation.lock === 'function');
    }

    function fullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || null;
    }

    function requestFs(el) {
        if (el.requestFullscreen) return el.requestFullscreen({ navigationUI: 'hide' });
        if (el.webkitRequestFullscreen) { el.webkitRequestFullscreen(); return Promise.resolve(); }
        return Promise.reject(new Error('no fullscreen'));
    }

    function exitFs() {
        if (!fullscreenElement()) return;
        try {
            if (document.exitFullscreen) document.exitFullscreen().catch(function () {});
            else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
        } catch (e) { /* already exited */ }
    }

    function icons(root) {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            try { window.lucide.createIcons({ root: root }); } catch (e) { /* icons are decorative */ }
        }
    }

    function toast(msg) {
        if (typeof window.showToast === 'function') window.showToast(msg, false);
    }

    // ── overlay markup ───────────────────────────────────────────────────
    function buildOverlay(title) {
        var root = document.createElement('div');
        root.className = 'chart-fs';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'chartFsTitle');
        root.innerHTML =
            '<div class="chart-fs-rotate" hidden>' +
                '<div class="chart-fs-phone" aria-hidden="true">' +
                    '<svg viewBox="0 0 120 120" width="120" height="120">' +
                        '<path class="chart-fs-arrow" d="M28 30 A44 44 0 0 1 90 22" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>' +
                        '<path class="chart-fs-arrow" d="M84 12 L92 22 L80 28" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '<g class="chart-fs-device">' +
                            '<rect x="40" y="26" width="40" height="70" rx="7" fill="none" stroke="currentColor" stroke-width="4"/>' +
                            '<line x1="54" y1="88" x2="66" y2="88" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>' +
                            '<polyline points="47,72 55,60 62,66 73,48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '</g>' +
                    '</svg>' +
                '</div>' +
                '<p class="chart-fs-rotate-title">Turn your phone sideways</p>' +
                '<p class="chart-fs-rotate-text">The chart opens in landscape so you can see every detail.</p>' +
                '<div class="chart-fs-rotate-actions">' +
                    '<button type="button" class="chart-fs-primary" data-fs-rotate hidden><i data-lucide="rotate-cw"></i> Rotate screen</button>' +
                    '<button type="button" class="chart-fs-secondary" data-fs-upright>View upright anyway</button>' +
                    '<button type="button" class="chart-fs-link" data-fs-close>Cancel</button>' +
                '</div>' +
            '</div>' +
            '<div class="chart-fs-view" hidden>' +
                '<div class="chart-fs-bar">' +
                    '<div class="chart-fs-title" id="chartFsTitle"></div>' +
                    '<button type="button" class="chart-fs-tool" data-fs-reset hidden><i data-lucide="zoom-out"></i><span>Reset zoom</span></button>' +
                    '<button type="button" class="chart-fs-tool chart-fs-close" data-fs-close aria-label="Close full screen"><i data-lucide="x"></i></button>' +
                '</div>' +
                '<div class="chart-fs-canvas"><canvas></canvas></div>' +
                '<div class="chart-fs-hint" hidden>Pinch or scroll to zoom · drag to move</div>' +
            '</div>';
        root.querySelector('.chart-fs-title').textContent = title;
        return root;
    }

    // ── open / show / close ──────────────────────────────────────────────
    function open(id, title, trigger) {
        var lc = window.LayRateChart;
        var config = lc && lc._configs ? lc._configs[id] : null;
        var live = lc && lc._instances ? lc._instances[id] : null;
        if (!config || !live) { toast('No chart data to show yet.'); return; }
        if (state) close();

        var root = buildOverlay(title || '');
        document.body.appendChild(root);
        document.documentElement.classList.add('chart-fs-open');
        state = { id: id, root: root, trigger: trigger, chart: null, mql: null, onOrient: null, enteredFs: false };
        icons(root);
        loadZoom();   // start fetching while the user turns the phone

        if (isPhone() && isPortrait()) {
            showRotate();
        } else {
            showChart();
        }
    }

    function showRotate() {
        var root = state.root;
        var step = root.querySelector('.chart-fs-rotate');
        step.hidden = false;
        var rotateBtn = root.querySelector('[data-fs-rotate]');
        rotateBtn.hidden = !canRotateForUser();
        (rotateBtn.hidden ? root.querySelector('[data-fs-upright]') : rotateBtn).focus({ preventScroll: true });

        // Continue as soon as the phone is actually turned.
        if (window.matchMedia) {
            state.mql = window.matchMedia('(orientation: landscape)');
            state.onOrient = function (e) { if (e.matches && state) showChart(); };
            if (state.mql.addEventListener) state.mql.addEventListener('change', state.onOrient);
            else if (state.mql.addListener) state.mql.addListener(state.onOrient);
        }
    }

    function stopOrientationWatch() {
        if (!state || !state.mql || !state.onOrient) return;
        if (state.mql.removeEventListener) state.mql.removeEventListener('change', state.onOrient);
        else if (state.mql.removeListener) state.mql.removeListener(state.onOrient);
        state.mql = null;
        state.onOrient = null;
    }

    function rotateForUser() {
        var root = state.root;
        requestFs(root).then(function () {
            if (!state) return;
            state.enteredFs = true;
            return window.screen.orientation.lock('landscape');
        }).then(function () {
            if (state) showChart();
        }).catch(function () {
            if (!state) return;
            // Full screen may have worked even though the lock didn't.
            var btn = root.querySelector('[data-fs-rotate]');
            if (btn) btn.hidden = true;
            var text = root.querySelector('.chart-fs-rotate-text');
            if (text) text.textContent = "This browser can't turn the screen for you. Turn your phone sideways, or view it upright.";
        });
    }

    function showChart() {
        if (!state || state.chart) return;
        stopOrientationWatch();
        var root = state.root;
        root.querySelector('.chart-fs-rotate').hidden = true;
        root.querySelector('.chart-fs-view').hidden = false;
        root.querySelector('[data-fs-close].chart-fs-close').focus({ preventScroll: true });

        var id = state.id;
        loadZoom().then(function (zoom) {
            if (!state || state.id !== id) return;
            // Next frame: the canvas box has its final size before Chart.js measures it.
            requestAnimationFrame(function () {
                if (!state || state.id !== id || state.chart) return;
                createCopy(zoom);
            });
        });
    }

    // ── full-screen scaling ──────────────────────────────────────────────
    // Card charts use 10px text; on a full screen that reads as tiny. Text
    // scales with the viewer height (phone landscape is only ~300px tall).
    function viewerFontSize() {
        var h = window.innerHeight || 800;
        return h < 480 ? 11 : (h < 820 ? 13 : 14);
    }

    // Raise a font to at least `size`; scriptable (function) fonts are left alone.
    function atLeast(font, size, weight) {
        if (typeof font === 'function') return font;
        var f = Object.assign({}, font || {});
        if (!f.size || f.size < size) f.size = size;
        if (weight && !f.weight) f.weight = weight;
        return f;
    }

    function scaleForViewer(type, options, data) {
        var fs = viewerFontSize();
        var radial = !!NO_ZOOM_TYPES[type];

        options.layout = { padding: { top: 10, right: 18, bottom: 6, left: 10 } };

        if (!radial) {
            options.scales = options.scales || {};
            ['x', 'y'].forEach(function (k) { if (!options.scales[k]) options.scales[k] = {}; });
            Object.keys(options.scales).forEach(function (k) {
                var axis = options.scales[k];
                if (!axis || typeof axis !== 'object') return;
                axis.ticks = Object.assign({}, axis.ticks || {});
                axis.ticks.font = atLeast(axis.ticks.font, fs);
                axis.ticks.padding = Math.max(axis.ticks.padding || 0, 6);
                if (axis.title && axis.title.display) {
                    axis.title = Object.assign({}, axis.title, { font: atLeast(axis.title.font, fs, '600') });
                }
            });
        }

        var p = options.plugins;
        if (p.legend !== false) {
            p.legend = Object.assign({}, p.legend || {});
            p.legend.labels = Object.assign({}, p.legend.labels || {});
            p.legend.labels.font = atLeast(p.legend.labels.font, fs);
            p.legend.labels.padding = Math.max(p.legend.labels.padding || 0, 16);
            p.legend.labels.boxWidth = Math.max(p.legend.labels.boxWidth || 0, 12);
        }
        if (p.tooltip !== false) {
            p.tooltip = Object.assign({}, p.tooltip || {});
            p.tooltip.padding = 12;
            p.tooltip.boxPadding = 6;
            p.tooltip.caretSize = 7;
            p.tooltip.cornerRadius = 8;
            p.tooltip.titleFont = atLeast(p.tooltip.titleFont, fs + 1, '600');
            p.tooltip.bodyFont = atLeast(p.tooltip.bodyFont, fs);
        }

        tuneDatasets(type, data);
    }

    // Few categories on a wide screen made each bar enormous: cap the
    // thickness. Lines get a larger touch target and hover dot.
    function tuneDatasets(type, data) {
        (data.datasets || []).forEach(function (ds) {
            var t = ds.type || type;
            if (t === 'bar') {
                if (ds.maxBarThickness == null) ds.maxBarThickness = 64;
            } else if (t === 'line' || t === 'scatter') {
                ds.pointHitRadius = Math.max(ds.pointHitRadius || 0, 12);
                if (typeof ds.pointHoverRadius !== 'function') ds.pointHoverRadius = Math.max(ds.pointHoverRadius || 0, 6);
                if (t === 'scatter' && typeof ds.pointRadius !== 'function') ds.pointRadius = Math.max(ds.pointRadius || 0, 4);
            }
        });
    }

    function buildConfig(source, zoom) {
        var type = source.type;
        var options = clone(source.options || {});
        options.responsive = true;
        options.maintainAspectRatio = false;
        options.devicePixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        // Resize immediately (the app default delays it 120 ms). With a delay,
        // closing the viewer inside that window left Chart.js running a
        // scheduled update on the destroyed copy, which threw. One chart
        // resizing at once on rotation is cheap.
        options.resizeDelay = 0;
        if (options.animation !== false) {
            options.animation = Object.assign({}, options.animation || {}, { duration: 300 });
        }
        options.plugins = options.plugins || {};

        var data = clone(source.data);
        scaleForViewer(type, options, data);

        var plugins = (source.plugins || []).slice();
        var zoomable = !!zoom && !NO_ZOOM_TYPES[type];
        if (zoomable) {
            // Zoom along the category axis: x normally, y for horizontal bars.
            var mode = ZOOM_TYPES_XY[type] ? 'xy' : (options.indexAxis === 'y' ? 'y' : 'x');
            options.plugins.zoom = {
                limits: { x: { min: 'original', max: 'original' }, y: { min: 'original', max: 'original' } },
                pan: { enabled: true, mode: mode, threshold: 4 },
                zoom: { wheel: { enabled: true, speed: 0.12 }, pinch: { enabled: true }, mode: mode },
            };
            plugins.push(zoom);
        }

        return { config: { type: type, data: data, options: options, plugins: plugins }, zoomable: zoomable };
    }

    function createCopy(zoom) {
        var source = window.LayRateChart && window.LayRateChart._configs[state.id];
        if (!source || typeof window.Chart === 'undefined') { close(); return; }
        var built = buildConfig(source, zoom);
        var canvas = state.root.querySelector('.chart-fs-canvas canvas');
        try {
            state.chart = new window.Chart(canvas, built.config);
        } catch (e) {
            console.error('[ChartFullscreen] Could not draw the chart:', e);
            close();
            toast('This chart could not be opened in full screen.');
            return;
        }
        state.zoomable = built.zoomable;
        state.root.querySelector('[data-fs-reset]').hidden = !built.zoomable;
        state.root.querySelector('.chart-fs-hint').hidden = !built.zoomable;
    }

    // Live update from LayRateChart (same canvas id re-rendered or morphed).
    function sync(id) {
        if (!state || state.id !== id || !state.chart) return;
        var source = window.LayRateChart && window.LayRateChart._configs[id];
        if (!source) return;
        if (source.type !== state.chart.config.type) {
            try { state.chart.destroy(); } catch (e) {}
            state.chart = null;
            loadZoom().then(function (zoom) { if (state && state.id === id && !state.chart) createCopy(zoom); });
            return;
        }
        try {
            var data = clone(source.data);
            tuneDatasets(source.type, data);
            state.chart.data = data;
            state.chart.update('none');
        } catch (e) { /* keep the last good copy */ }
    }

    function close() {
        if (!state) return;
        var s = state;
        state = null;
        if (s.mql && s.onOrient) {
            if (s.mql.removeEventListener) s.mql.removeEventListener('change', s.onOrient);
            else if (s.mql.removeListener) s.mql.removeListener(s.onOrient);
        }
        if (s.chart) { try { s.chart.destroy(); } catch (e) {} }
        if (window.screen.orientation && typeof window.screen.orientation.unlock === 'function') {
            try { window.screen.orientation.unlock(); } catch (e) { /* nothing locked */ }
        }
        exitFs();
        if (s.root && s.root.parentNode) s.root.parentNode.removeChild(s.root);
        document.documentElement.classList.remove('chart-fs-open');
        if (s.trigger && document.contains(s.trigger)) {
            try { s.trigger.focus({ preventScroll: true }); } catch (e) {}
        }
    }

    // Buttons only show while their chart's canvas is on the page (empty
    // states render no canvas).
    function refreshButtons() {
        document.querySelectorAll('[data-chart-fullscreen]').forEach(function (btn) {
            btn.hidden = !document.getElementById(btn.getAttribute('data-chart-fullscreen'));
        });
    }

    // ── events (bound once) ──────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;

        var trigger = t.closest('[data-chart-fullscreen]');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            open(trigger.getAttribute('data-chart-fullscreen'), trigger.getAttribute('data-chart-title'), trigger);
            return;
        }
        if (!state || !state.root.contains(t)) return;
        if (t.closest('[data-fs-close]')) { e.preventDefault(); close(); return; }
        if (t.closest('[data-fs-upright]')) { e.preventDefault(); showChart(); return; }
        if (t.closest('[data-fs-rotate]')) { e.preventDefault(); rotateForUser(); return; }
        if (t.closest('[data-fs-reset]')) {
            e.preventDefault();
            if (state.chart && typeof state.chart.resetZoom === 'function') state.chart.resetZoom();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (state && e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
    }, true);

    // Leaving browser full screen (Android back gesture / button) closes the viewer.
    ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (ev) {
        document.addEventListener(ev, function () {
            if (state && state.enteredFs && !fullscreenElement()) close();
        });
    });

    document.addEventListener('turbo:before-render', close);
    document.addEventListener('turbo:before-cache', close);
    document.addEventListener('turbo:load', refreshButtons);
    document.addEventListener('turbo:frame-load', refreshButtons);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refreshButtons);
    else refreshButtons();

    window.LayRateChartFullscreen = { open: open, close: close, sync: sync, refreshButtons: refreshButtons };
})();
