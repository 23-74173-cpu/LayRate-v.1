import { test, expect } from '@playwright/test';
import { loginAs, dismissAlertsModal } from '../helpers/auth.js';

// Table IX F3 — Real-Time Monitoring Dashboard, DEGRADED-STATE handling.
// Scope warning (read before extending): the DHT22-001 sensor has produced no
// readings since the quarantined demo window and the bridge is offline, so NO
// case here verifies live sensor monitoring. These cases verify the app
// degrades honestly instead: empty trend states at every range, offline
// sensor cards, last-known aggregates, and a clean 404 path for fan control
// with no relay registered. Live-monitoring verification requires the farm Pi
// back online and is explicitly out of scope.
//
// Baseline pins: latest real env rows are the Jun-25 noon overrides for
// CAGE-A/B/C (32.1 C / 81.0 %); CAGE-T and CAGE-D have no real rows at all;
// only CAGE-T maps to a DHT22 (DHT22-001). Post-quarantine the 24h/week/month
// trend windows are all empty (Jun 25 is older than every window).

async function openEnvironment(page) {
  const errors = [];
  page.on('pageerror', (err) => errors.push(String(err)));
  await loginAs(page, 'admin');
  const resp = await page.goto('/environment');
  expect(resp.ok()).toBe(true);
  await dismissAlertsModal(page);
  // Lazy turbo-frame: bring it into view, then wait for the trend panel to
  // settle (empty state). Scroll first: Turbo only fetches lazy frames near
  // the viewport, and headless timing otherwise races frame insertion.
  await page.locator('#environment-live-data').scrollIntoViewIfNeeded();
  await expect(page.locator('#envTempChartEmpty')).toBeVisible({ timeout: 20_000 });
  return errors;
}

test.describe('Table IX F3 — Dashboard degraded-state handling (NOT live monitoring)', () => {
  test('TC-07 empty 24h trend state renders honestly with zero sensors, no crash', async ({
    page,
  }) => {
    const errors = await openEnvironment(page);

    await expect(page.locator('#envTempChartEmpty')).toContainText(
      'No temperature readings in this window',
    );
    await expect(page.locator('#envHumChartEmpty')).toBeVisible();
    // Sensor cards section falls through to its empty message (no cage has a
    // sensor-backed real reading), and the metric reports zero live sensors.
    await expect(page.getByText('No environmental readings recorded yet.')).toBeVisible();
    await expect(page.getByText('0 sensors')).toBeVisible();
    expect(errors).toEqual([]);
  });

  test('TC-08 month range stays empty while cards show offline plus last-known aggregates', async ({
    page,
  }) => {
    const errors = await openEnvironment(page);

    await Promise.all([
      page.waitForResponse((r) => r.url().includes('live-data') && r.ok()),
      page.locator('#trendRange').selectOption('month'),
    ]);
    await expect(page.locator('#trendRange')).toHaveValue('month');

    // Jun-25 data is older than every trend window: charts stay empty rather
    // than resurfacing stale points as if live.
    await expect(page.locator('#envTempChartEmpty')).toBeVisible();
    await expect(page.locator('#envHumChartEmpty')).toBeVisible();

    // Per-cage honesty: CAGE-T maps to DHT22-001 but has no real reading;
    // A/B/C have readings but no assigned sensor.
    await expect(page.getByText('No sensor data').first()).toBeVisible();
    await expect(page.getByText('No sensor assigned').first()).toBeVisible();
    // Coop cards still show the last-known aggregates (Jun-25 overrides).
    await expect(page.getByText('32.1°C').first()).toBeVisible();
    expect(errors).toEqual([]);
  });

  test('TC-09 unconfigured relay reports honestly and refuses control cleanly', async ({
    page,
  }) => {
    const errors = await openEnvironment(page);

    // FAB actions start collapsed behind the toggle.
    await page.getByRole('button', { name: 'Open menu' }).click();
    await page.getByRole('button', { name: 'Cooling Fan' }).click();
    const modal = page.locator('#envFanModal');
    await expect(modal).toBeVisible();
    // No relay hardware is registered: badge/subtext say so outright.
    await expect(page.locator('#relayStatusBadge')).toHaveText('No Relay');
    await expect(page.locator('#relaySubtext')).toContainText(
      'No active relay device is registered.',
    );

    // Control attempt surfaces the 404 as a toast, not a crash or a fake state.
    await page.locator('[data-relay-action="on"]').click();
    await expect(page.locator('#notification-toast-message')).toContainText(
      'No active relay device is registered.',
      { timeout: 10_000 },
    );
    expect(errors).toEqual([]);
  });
});
