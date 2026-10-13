import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
  test.use({ launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } });
}
const source = readFileSync('resources/views/dashboard/_production-history.blade.php', 'utf8');
const chartLibrary = readFileSync('public/js/chart.min.js', 'utf8');
const chartColors = readFileSync('public/js/chart-colors.js', 'utf8');
const dates = Array.from({ length: 30 }, (_, i) => new Date(Date.UTC(2026, 8, 14 + i)).toISOString().slice(0, 10));

for (const compare of [false, true]) {
  test(`forecast overlay aligns dates and paints saved points with compare=${compare}`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const chartData = {
      labels: dates.map(date => date.slice(5)), dateKeys: dates,
      datasets: [{ label: compare ? 'Cage A' : 'Total Production', data: dates.map(() => 10), borderColor: '#002d5e' }]
    };
    const keys = [...dates, '2026-10-14', '2026-10-15'];
    const payload = {
      hasForecast: true, labels: keys.map(date => date.slice(5)), dateKeys: keys,
      actual: [...dates.map(() => 10), null, null], forecast: [...dates.map(() => null), 12, 0],
      summary: 'Awaiting completed production records for comparison.', predictionDetails: {}
    };
    await page.route('http://dashboard-forecast.test/**', route => {
      if (route.request().url().includes('/forecast-overlay')) {
        expect(new URL(route.request().url()).searchParams.get('days')).toBe('30');
        return route.fulfill({ json: payload });
      }
      return route.fulfill({ contentType: 'text/html', body: '<button id="forecastToggleBtn" onclick="toggleForecastOverlay()">Show Forecast</button><div id="forecastVariancePanel" class="hidden"><span id="forecastVarianceText"></span></div><div style="height:300px"><canvas id="dashProductionHistoryChart"></canvas></div>' });
    });
    await page.goto('http://dashboard-forecast.test/');
    await page.addScriptTag({ content: chartLibrary });
    await page.addScriptTag({ content: chartColors });
    await page.addScriptTag({ content: `window.__dashboardHistoryCompare = ${compare}; window.DashboardChartRenderer = {render(id, config) { window.Chart.getChart(id)?.destroy(); window.testChart = new Chart(document.getElementById(id), config); }};` });
    const script = source.slice(source.indexOf('<script data-lucide-init>') + '<script data-lucide-init>'.length, source.lastIndexOf('</script>'))
      .replace('@json($chartData)', JSON.stringify(chartData))
      .replaceAll("{{ $compare ? 'true' : 'false' }}", String(compare))
      .replaceAll("{{ $cageCode ?? '' }}", '')
      .replace("{{ route('dashboard.forecast-overlay') }}", '/forecast-overlay')
      .replace('{{ $days }}', '30');
    await page.addScriptTag({ content: script });
    await page.getByRole('button', { name: 'Show Forecast' }).click();
    await expect.poll(() => page.evaluate(() => window.testChart.data.datasets.length)).toBe(2);
    const rendered = await page.evaluate(() => ({
      labels: testChart.data.labels, actual: testChart.data.datasets[0].data,
      forecast: testChart.data.datasets[1].data,
      callback: typeof testChart.config.options.plugins.tooltip.callbacks.label,
      plugin: typeof testChart.config.plugins[0].beforeDatasetsDraw
    }));
    expect(rendered.labels).toEqual(payload.labels);
    expect(rendered.actual).toEqual(payload.actual);
    expect(rendered.forecast).toEqual(payload.forecast);
    expect(rendered.callback).toBe('function');
    expect(rendered.plugin).toBe('function');
    await expect.poll(() => page.evaluate(() => testChart.getDatasetMeta(1).data.slice(-2).every(point => Number.isFinite(point.x) && Number.isFinite(point.y) && !point.skip))).toBe(true);
    await page.getByRole('button', { name: 'Show Forecast' }).click();
    await expect.poll(() => page.evaluate(() => testChart.data.labels.length)).toBe(30);
    expect(errors).toEqual([]);
  });
}
