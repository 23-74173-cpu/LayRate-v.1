import { test, expect } from '@playwright/test';
import { loginAs, dismissAlertsModal } from '../helpers/auth.js';

// Table IX F5 — Alert and Threshold Notification.
// TC-13/14 mutate global thresholds through the real modal flow and RESTORE
// the snapshotted values in-test, so no cleanup SQL is needed for them.
// TC-15 fires a real breach through the ingestion endpoint (the only path
// that evaluates EnvironmentAlertService::check): backdated Jan-15 payload
// per benchmark hygiene (see 03-f2-sensor header + table-ix README terminal
// cleanup). The unread temperature_high alert it creates is removed by that
// same documented cleanup step.

const DEVICE_KEY = process.env.LAYRATE_DEVICE_KEY ?? 'layrate-pi-dev-key-2026';

async function openThresholds(page) {
  await page.getByRole('button', { name: 'Open menu' }).click();
  await page.getByRole('button', { name: 'Configure Thresholds' }).click();
  await expect(page.locator('#envThresholdsModal')).toBeVisible();
}

async function readThresholds(page) {
  const modal = page.locator('#envThresholdsModal');
  const values = {};
  for (const name of ['temp_min', 'temp_max', 'hum_min', 'hum_max']) {
    values[name] = await modal.locator(`input[name="${name}"]`).inputValue();
  }
  return values;
}

async function saveThresholds(page, values) {
  const modal = page.locator('#envThresholdsModal');
  for (const [name, value] of Object.entries(values)) {
    await modal.locator(`input[name="${name}"]`).fill(String(value));
  }
  await modal.locator('#save-thresholds-btn').click();
}

function toastMessage(page) {
  return page.locator('#notification-toast-message');
}

test.describe('Table IX F5 — Alert and Threshold Notification', () => {
  test('TC-13 admin saves thresholds and they persist', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/environment');
    await dismissAlertsModal(page);

    await openThresholds(page);
    const original = await readThresholds(page);

    await saveThresholds(page, { ...original, temp_max: '32' });
    await expect(toastMessage(page)).toContainText('Thresholds saved successfully', {
      timeout: 10_000,
    });

    // Reload proves server-side persistence, not just modal state.
    await page.reload();
    await dismissAlertsModal(page);
    await openThresholds(page);
    expect(await readThresholds(page)).toMatchObject({ ...original, temp_max: '32' });

    // Restore.
    await saveThresholds(page, original);
    await expect(toastMessage(page)).toContainText('Thresholds saved successfully', {
      timeout: 10_000,
    });
    await page.reload();
    await dismissAlertsModal(page);
    await openThresholds(page);
    expect(await readThresholds(page)).toMatchObject(original);
  });

  test('TC-14 equal bounds accepted, inverted bounds rejected with values kept', async ({
    page,
  }) => {
    await loginAs(page, 'admin');
    await page.goto('/environment');
    await dismissAlertsModal(page);

    await openThresholds(page);
    const original = await readThresholds(page);

    // Boundary: min == max is a legal degenerate window.
    await saveThresholds(page, { ...original, temp_min: '25', temp_max: '25' });
    await expect(toastMessage(page)).toContainText('Thresholds saved successfully', {
      timeout: 10_000,
    });

    // Error: max < min fails validation; the rejection must surface and the
    // stored values must not move. Hide any prior toast first so whatever
    // appears comes from this submit.
    await page.evaluate(() => window.hideNotification && window.hideNotification());
    await saveThresholds(page, { temp_min: '30', temp_max: '20' });
    const errMsg = page.locator('#notification-toast-message');
    await expect(errMsg).toBeVisible({ timeout: 10_000 });
    await expect(errMsg).not.toContainText('Thresholds saved successfully');
    await page.reload();
    await dismissAlertsModal(page);
    await openThresholds(page);
    expect(await readThresholds(page)).toMatchObject({ temp_min: '25', temp_max: '25' });

    // Restore.
    await saveThresholds(page, original);
    await expect(toastMessage(page)).toContainText('Thresholds saved successfully', {
      timeout: 10_000,
    });
  });

  test('TC-15 breaching reading creates an unread temperature alert', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/environment');
    await dismissAlertsModal(page);

    // 38 C breaches temp_max (30); 55 % humidity stays inside 40-70 so only
    // the temperature leg fires. Backdated per benchmark hygiene.
    const resp = await page.request.post('/api/sensor-readings', {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Device-Key': DEVICE_KEY,
      },
      data: {
        recorded_at: '2026-01-15T12:10:00+08:00',
        readings: [{ serial_number: 'DHT22-001', temperature_c: 38.0, humidity_pct: 55.0 }],
      },
    });
    expect(resp.status()).toBe(200);

    const alertsResp = await page.request.get('/api/alerts?limit=100', {
      headers: { Accept: 'application/json', 'X-Device-Key': DEVICE_KEY },
    });
    expect(alertsResp.ok()).toBe(true);
    const body = await alertsResp.json();
    const alerts = body.alerts ?? body.data?.alerts ?? [];
    const breach = alerts.find(
      (a) => a.alert_type === 'temperature_high' && a.cage_code === 'CAGE-T' && !a.is_read,
    );
    expect(breach, 'unread temperature_high alert for CAGE-T').toBeTruthy();
    expect(breach.message).toMatch(/38.*30|above maximum/);
  });
});
