/**
 * chart-colors.js — one shared metric→color map for every Chart.js chart.
 *
 * Mirrors the --color-chart-* tokens in resources/css/app.css (with identical
 * hardcoded fallbacks so charts render correctly even if CSS is not parsed
 * yet). A metric keeps its color on every chart and legend:
 *   eggs #002d5e · HDEP #009e73 · temperature #e69f00 · humidity #56b4e9
 *   feed #8a6bbf · mortality #d55e00
 * Per-category bars (per cage/breed/cause) intentionally do NOT use this —
 * they encode categories, distinguished by labels. Never rely on color
 * alone: charts pair color with line styles, dashes, markers and labels.
 */
window.LayRateChartColors = (function () {
    'use strict';

    function cssVar(name, fallback) {
        try {
            var v = getComputedStyle(document.documentElement).getPropertyValue(name);
            v = (v || '').trim();
            return v !== '' ? v : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function hexToRgb(hex) {
        var h = hex.replace('#', '');
        if (h.length === 3) h = h.split('').map(function (c) { return c + c; }).join('');
        var n = parseInt(h, 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    var api = {
        get eggs() { return cssVar('--color-chart-1', '#002d5e'); },
        get hdep() { return cssVar('--color-chart-2', '#009e73'); },
        get temp() { return cssVar('--color-chart-3', '#e69f00'); },
        get humidity() { return cssVar('--color-chart-4', '#56b4e9'); },
        get feed() { return cssVar('--color-chart-5', '#8a6bbf'); },
        get mortality() { return cssVar('--color-chart-6', '#d55e00'); },
        get grid() { return 'rgba(0,0,0,0.04)'; },
        get axis() { return '#9CA3AF'; },
        get tooltip() { return cssVar('--color-navy', '#002d5e'); },
        alpha: function (hex, a) {
            var c = hexToRgb(hex);
            return 'rgba(' + c[0] + ', ' + c[1] + ', ' + c[2] + ', ' + a + ')';
        }
    };
    return api;
})();
