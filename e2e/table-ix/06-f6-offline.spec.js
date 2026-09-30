import { test, expect } from '@playwright/test';
import { loginAs, dismissAlertsModal } from '../helpers/auth.js';

// Table IX F6 — Offline Web Interface (LAN-without-WAN).
// The thesis "offline" claim is architectural: everything is served from the
// local host with zero outbound dependencies (no Service Worker exists by
// design). These cases simulate severed upstream internet by aborting every
// non-local request at the browser edge, then prove (a) the app never even
// attempts an outbound call, and (b) core flows complete with no local
// failures and no JS errors. Install BEFORE login so sign-in itself is covered.

const LOCAL_HOSTS = new Set(['127.0.0.1', 'localhost']);

async function enforceLocalOnly(page) {
  const external = [];
  const failedLocal = [];
  await page.route('**/*', (route) => {
    let url;
    try {
      url = new URL(route.request().url());
    } catch {
      return route.continue();
    }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return route.continue();
    if (LOCAL_HOSTS.has(url.hostname)) return route.continue();
    external.push(route.request().url());
    return route.abort();
  });
  page.on('requestfailed', (req) => {
    // Navigation-cancelled in-flight requests (e.g. a lazy dashboard frame
    // still loading when the test moves on) surface as ERR_ABORTED — a
    // harness artifact, not an app failure.
    if (req.failure()?.errorText === 'net::ERR_ABORTED') return;
    try {
      if (LOCAL_HOSTS.has(new URL(req.url()).hostname)) failedLocal.push(req.url());
    } catch {
      /* ignore malformed URLs */
    }
  });
  const pageErrors = [];
  page.on('pageerror', (err) => pageErrors.push(String(err)));
  return { external, failedLocal, pageErrors };
}

test.describe('Table IX F6 — Offline Web Interface (LAN-without-WAN)', () => {
  test('TC-16 login and dashboard render with zero outbound attempts', async ({ page }) => {
    const net = await enforceLocalOnly(page);
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    await dismissAlertsModal(page);

    // Core KPIs paint from local data only.
    await expect(page.getByText('Total Hens').first()).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText('Lifetime Eggs').first()).toBeVisible();

    // Strongest form of the claim: not just "degraded gracefully" — the app
    // never attempted a single non-local request, and nothing local failed.
    expect(net.external).toEqual([]);
    expect(net.failedLocal).toEqual([]);
    expect(net.pageErrors).toEqual([]);
  });

  test('TC-17 offline user journey completes across five modules', async ({ page }) => {
    const net = await enforceLocalOnly(page);
    await loginAs(page, 'admin');

    await page.goto('/dashboard');
    await dismissAlertsModal(page);
    await expect(page.getByText('Total Hens').first()).toBeVisible({ timeout: 15_000 });

    await page.goto('/eggs/logging');
    // Picking a cage card reveals its total-cage log form — the real
    // interaction (per-slot grids stay hidden until their own tab flow).
    await page.locator('.cage-overview-card[data-cage-code="CAGE-A"]').click();
    await expect(page.locator('#totalCageLogForm')).toBeVisible();
    await expect(page.locator('#tcTotalEggs')).toBeVisible();
    await expect(page.locator('#tcCageCode')).toContainText('CAGE-A');

    await page.goto('/environment');
    await expect(page.locator('#envTempChartEmpty')).toBeVisible({ timeout: 15_000 });

    await page.goto('/forecast?scope=farm&horizon=7');
    await expect(page.locator('#production-calendar')).toBeVisible();

    await page.goto(
      '/reports?type=production&from=2026-03-23&to=2026-06-25&cage=all&full=1',
    );
    await expect(page.locator('#report-doc')).toBeVisible();

    expect(net.external).toEqual([]);
    expect(net.failedLocal).toEqual([]);
    expect(net.pageErrors).toEqual([]);
  });

  test('TC-18 Excel export downloads a valid file with upstream severed', async ({ page }) => {
    // NOTE: drafted as PDF, switched to Excel — /reports/pdf 500s on this
    // host for two filed reasons (Prompt 16: missing PHP GD extension here,
    // plus a real full-range OOM at 256M). The Excel path is pure-PHP and
    // exercises the same offline export pipeline end to end.
    const net = await enforceLocalOnly(page);
    await loginAs(page, 'admin');
    await page.goto(
      '/reports?type=production&from=2026-03-23&to=2026-06-25&cage=all',
    );
    await dismissAlertsModal(page);

    await page.locator('#exportDropdownBtn').click();
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 60_000 }),
      page.locator('#exportExcelLink').click(),
    ]);
    const filePath = await download.path();
    const fs = await import('node:fs');
    const buf = fs.readFileSync(filePath);
    expect(buf.subarray(0, 4).toString('latin1')).toBe('PK\x03\x04');
    expect(buf.length).toBeGreaterThan(100_000);

    expect(net.external).toEqual([]);
    expect(net.failedLocal).toEqual([]);
    expect(net.pageErrors).toEqual([]);
  });
});
