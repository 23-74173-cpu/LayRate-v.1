import { test, expect } from '@playwright/test';
import { loginAs, dismissAlertsModal } from './helpers/auth.js';

test('scratch: trace threshold save', async ({ page }) => {
  page.on('response', (r) => {
    if (r.url().includes('thresholds')) console.log('RESP ' + r.status() + ' ' + r.url());
  });
  await loginAs(page, 'admin');
  await page.goto('/environment');
  await dismissAlertsModal(page);
  const action = await page.locator('#threshold-form').getAttribute('action');
  console.log('ACTION=' + action);
  await page.getByRole('button', { name: 'Open menu' }).click();
  await page.getByRole('button', { name: 'Configure Thresholds' }).click();
  const before = await page.locator('#envThresholdsModal input[name="temp_min"]').inputValue();
  console.log('BEFORE-MIN=' + before);
  await page.locator('#envThresholdsModal input[name="temp_max"]').fill('32');
  await page.locator('#save-thresholds-btn').click();
  await expect(page.locator('#notification-toast-message')).toContainText('Thresholds saved successfully', { timeout: 10000 });
  console.log('SAVED-OK');
});
