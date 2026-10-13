import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
  test.use({ launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } });
}
const source = readFileSync('resources/views/forecast.blade.php', 'utf8');
const exporting = source.slice(source.indexOf('        function exportForecast(format)'), source.indexOf("        document.querySelectorAll('.export-forecast-btn')"));

async function setup(page, scenario, format = 'csv') {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.route('http://forecast-export.test/**', route => {
    if (route.request().method() === 'POST') {
      const body = route.request().postDataJSON();
      expect(body).toMatchObject({ scope: 'farm', horizon: '30', month: '9', year: '2025', forecast_view: 'backtest' });
      expect(route.request().headers()['x-csrf-token']).toBe('form-token');
      if (scenario === 'empty') return route.fulfill({ status: 422, json: { message: 'No saved forecasts for this month.' } });
      if (scenario === 'html') return route.fulfill({ contentType: 'text/html', body: '<h1>Sign in</h1>' });
      if (scenario === 'redirect') return route.fulfill({ status: 302, headers: { Location: '/login' } });
      const types = { csv: 'text/csv', excel: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', pdf: 'application/pdf' };
      const ext = { csv: 'csv', excel: 'xlsx', pdf: 'pdf' }[format];
      return route.fulfill({ contentType: types[format], headers: { 'Content-Disposition': `attachment; filename="forecast-backtest-farm-2025-09.${ext}"` }, body: 'test export bytes' });
    }
    if (route.request().url().endsWith('/login')) return route.fulfill({ contentType: 'text/html', body: 'Sign in' });
    return route.fulfill({ contentType: 'text/html', body: `<input name="forecast_view" value="saved"><form id="forecastForm"><input name="scope" value="farm">${scenario === 'missing_token' ? '' : '<input name="_token" value="form-token">'}<input type="radio" name="horizon" value="7"><input type="radio" name="horizon" value="30" checked></form><div id="production-calendar"><input name="month" value="9"><input name="year" value="2025"><input name="forecast_view" value="backtest"></div><div id="forecastExportLoadingOverlay" style="display:none"></div><button id="export">Export</button>` });
  });
  await page.goto('http://forecast-export.test/');
  await page.addScriptTag({ content: `var exportOverlay = document.getElementById('forecastExportLoadingOverlay'); function closeExportModalFn() {} function showExportLoading() { exportOverlay.style.display='flex'; } function hideExportLoading() { exportOverlay.style.display='none'; } window.messages=[]; window.showNotification=(message)=>messages.push(message); ${exporting} document.getElementById('export').onclick=()=>exportForecast('${format}');` });
  return errors;
}

for (const format of ['csv', 'excel', 'pdf']) {
  test(`${format} exports the visible calendar using the form token and server filename`, async ({ page }) => {
    const errors = await setup(page, 'success', format);
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export' }).click();
    const file = await download;
    expect(file.suggestedFilename()).toBe(`forecast-backtest-farm-2025-09.${{ csv: 'csv', excel: 'xlsx', pdf: 'pdf' }[format]}`);
    await expect(page.locator('#forecastExportLoadingOverlay')).toBeHidden();
    expect(errors).toEqual([]);
  });
}
for (const scenario of ['empty', 'html', 'redirect', 'missing_token']) {
  test(`export ${scenario} reports an error without downloading an invalid file`, async ({ page }) => {
    const errors = await setup(page, scenario);
    const downloads = [];
    page.on('download', download => downloads.push(download));
    await page.getByRole('button', { name: 'Export' }).click();
    await expect.poll(() => page.evaluate(() => messages.length)).toBe(1);
    await expect(page.locator('#forecastExportLoadingOverlay')).toBeHidden();
    expect(downloads).toEqual([]);
    expect(errors).toEqual([]);
  });
}
