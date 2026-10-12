import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
  test.use({ launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } });
}
const source = readFileSync('resources/views/forecast.blade.php', 'utf8');
const lifecycle = source.slice(source.indexOf('    var forecastProgressFrame'), source.indexOf('    // Use document-level delegation for the forecast form submit'))
  .replace('var FORECAST_POLL_INTERVAL_MS = 2000;', 'var FORECAST_POLL_INTERVAL_MS = 10;')
  .replace('var FORECAST_POLL_TIMEOUT_MS = 6 * 60 * 1000;', 'var FORECAST_POLL_TIMEOUT_MS = 1200;')
  .replace('}, 15000)', '}, 100)').replace('}, 60000)', '}, 250)');

async function start(page, scenario) {
  await page.route('http://forecast-progress.test/**', route => route.fulfill({ contentType: 'text/html', body: `
    <meta name="csrf-token" content="test">
    <form id="forecastForm" action="http://forecast-progress.test/generate"><input name="scope" value="farm"></form>
    <button id="generateForecastBtn" disabled><span id="btnText">Generating...</span></button>
    <div id="forecastLoadingOverlay"><div id="forecastProgressBar"></div><span id="forecastProgressText"></span><span id="forecastStatusText"></span></div>` }));
  await page.goto('http://forecast-progress.test/');
  await page.evaluate(scenario => {
    window.scenario = scenario;
    window.messages = [];
    window.pollCount = 0;
    window.showNotification = (message, type) => window.messages.push({ message, type });
    window.Turbo = { visit: url => { window.resultsUrl = url; } };
    window.refreshForecastWorkspace = () => { window.resultsRefreshed = true; };
    const response = (body, status = 200) => ({ ok: status === 200, status, json: async () => body });
    window.fetch = async (url, options) => {
      if (options.method === 'POST') {
        if (scenario === 'submit_hang') return new Promise(() => {});
        if (scenario === 'body_hang') return { ok: true, json: () => new Promise(() => {}) };
        if (scenario === 'saved') return response({ already_saved: true, redirect_url: '/forecast', message: 'Saved dates kept unchanged.' });
        return response({ poll_url: '/status' });
      }
      window.pollCount++;
      if (scenario === 'poll_hang') return new Promise(() => {});
      if (scenario === 'running_forever') return response({ status: 'running' });
      if (scenario === 'failed') return response({ status: 'failed', error_message: 'Python model failed.' });
      if (scenario === 'stalled') return response({ status: 'stalled', error_message: 'No worker is running.' });
      if (scenario === 'missing') return response({ message: 'Run not found.' }, 404);
      if (scenario === 'expired') return { redirected: true, ok: true };
      if (scenario === 'bad_status') return response({ status: 'unknown' });
      if (scenario === 'retry' && window.pollCount === 1) return response({ message: 'Temporary server error.' }, 500);
      if (window.pollCount < 3) return response({ status: window.pollCount === 1 ? 'queued' : 'running' });
      return response({ status: 'completed', redirect_url: scenario === 'no_redirect' ? null : '/forecast', message: 'Forecast saved.' });
    };
  }, scenario);
  await page.addScriptTag({ content: `var _forecastIsSubmitting = true;
    var FORECAST_STORAGE_KEYS = { startTime: 'start', expectedDuration: 'duration' };
    var FORECAST_RECORD_CEILING = 60000;
    ${lifecycle}` });
  await page.evaluate(() => {
    sessionStorage.setItem('start', Date.now());
    window.startForecastProgress(1000);
    window.submitForecastFormAsync(document.getElementById('forecastForm'));
  });
}

for (const scenario of ['success', 'saved', 'retry', 'no_redirect']) {
  test(`forecast ${scenario} reaches 100% and releases the UI`, async ({ page }) => {
    await start(page, scenario);
    await expect(page.locator('#forecastProgressText')).toHaveText('100%');
    await expect(page.locator('#forecastLoadingOverlay')).toBeHidden();
    await expect(page.locator('#generateForecastBtn')).toBeEnabled();
    expect(await page.evaluate(() => window.messages.at(-1).type)).toBe('success');
    if (scenario === 'no_redirect') expect(await page.evaluate(() => window.resultsRefreshed)).toBe(true);
    else expect(await page.evaluate(() => window.resultsUrl)).toBe('/forecast');
  });
}
for (const scenario of ['failed', 'stalled', 'missing', 'expired', 'bad_status', 'submit_hang', 'body_hang', 'poll_hang', 'running_forever']) {
  test(`forecast ${scenario} clears the overlay instead of hanging at 95%`, async ({ page }) => {
    await start(page, scenario);
    await expect(page.locator('#forecastLoadingOverlay')).toBeHidden();
    await expect(page.locator('#generateForecastBtn')).toBeEnabled();
    expect(await page.evaluate(() => window.messages.at(-1).type)).not.toBe('success');
    await expect(page.locator('#forecastProgressText')).not.toHaveText('100%');
  });
}

// Leaving the page cancels its poller; the queued job may continue in the background.
test('navigation releases a pending forecast UI without a late error', async ({ page }) => {
  await start(page, 'poll_hang');
  await page.evaluate(() => document.dispatchEvent(new Event('turbo:before-visit')));
  await expect(page.locator('#forecastLoadingOverlay')).toBeHidden();
  await expect(page.locator('#generateForecastBtn')).toBeEnabled();
  await page.waitForTimeout(150);
  expect(await page.evaluate(() => window.messages.length)).toBe(0);
});
