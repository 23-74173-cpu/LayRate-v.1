import { test, expect } from '@playwright/test';
import { loginAs, csrfToken, dismissAlertsModal } from '../helpers/auth.js';

// Table IX F1 — Egg Production Forecasting.
// Baseline pins: farm-scope forecast generated 2026-09-17 (targets Sep 18-24)
// from the quarantined Mar-Jun 2026 import (is_demo = 0). Stored forecasts
// render as badges on the production calendar (forecast/_calendar); the old
// #forecastChart results partial was replaced by the calendar (commit
// 574559c) and is dead code — assertions target the calendar cells.
// MAINTENANCE RULE: the page only shows rows for the CURRENT reporting date
// (Manila midnight boundary). When the reporting date rolls past the pinned
// run, regenerate (farm scope, horizon 7: dispatch GenerateForecastJob +
// queue:work) and update EXPECTED_BADGES to the new run. Do NOT unpin to
// fuzzy assertions — the exact values are the real-data provenance proof.

// target_date -> badge text (number_format(predicted_egg_count, 0))
const EXPECTED_BADGES = {
  '2026-09-18': '482',
  '2026-09-19': '481',
  '2026-09-20': '480',
  '2026-09-21': '481',
  '2026-09-22': '479',
  '2026-09-23': '482',
  '2026-09-24': '479',
};

async function expectFarmBadges(page) {
  await expect(page.locator('#production-calendar')).toBeVisible();
  for (const [date, badge] of Object.entries(EXPECTED_BADGES)) {
    const cell = page.locator(`.calendar-day[data-date="${date}"]`);
    await expect(cell, `calendar cell ${date}`).toBeVisible();
    await expect(cell.locator('.forecast-badge'), `badge ${date}`).toHaveText(badge);
  }
}

test.describe('Table IX F1 — Egg Production Forecasting', () => {
  test('TC-01 farm forecast renders Sep-16 run as calendar badges', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/forecast?scope=farm&horizon=7');
    await dismissAlertsModal(page);
    await expectFarmBadges(page);
  });

  test('TC-02 insufficient-data scope shows lock overlay and blocks generation', async ({ page }) => {
    await loginAs(page, 'admin');
    // CAGE-D has 0 quarantined-real days (< 90 required).
    await page.goto('/forecast?scope=cage&cage=CAGE-D');
    await dismissAlertsModal(page);

    const overlay = page.locator('#forecastLockOverlay');
    await expect(overlay).toBeVisible();
    await expect(overlay).toContainText('Insufficient Forecast Data');

    // Dismiss the lock dialog, then attempt generation anyway: the client
    // guard must refuse to submit (no navigation, no progress overlay) and
    // re-show the lock dialog.
    await page.locator('#lockDismissBtn').click();
    await expect(overlay).toBeHidden();
    await page.locator('#generateForecastBtn').click();
    await expect(overlay).toBeVisible();
    await expect(page.locator('#forecastLoadingOverlay')).toBeHidden();
    await expect(page).toHaveURL(/\/forecast/);
  });

  test('TC-03 server rejects generation for insufficient-data scope, keeps existing forecast', async ({
    page,
  }) => {
    await loginAs(page, 'admin');
    const token = await csrfToken(page);

    // Bypass the JS lock overlay with a direct POST: the server must still refuse.
    const rejected = await page.request.post('/forecast/generate', {
      maxRedirects: 0,
      form: { _token: token, scope: 'cage', cage: 'CAGE-D', horizon: '7' },
      headers: { Referer: '/forecast?scope=cage&cage=CAGE-D' },
    });
    expect(rejected.status()).toBe(302);

    const follow = await page.request.get(rejected.headers()['location']);
    expect(await follow.text()).toContain('Need at least 90 days of production records');

    // The 2026-09-16 farm forecast is untouched by the rejected run.
    await page.goto('/forecast?scope=farm&horizon=7');
    await dismissAlertsModal(page);
    await expectFarmBadges(page);
  });
});
