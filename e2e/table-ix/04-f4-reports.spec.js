import { test, expect } from '@playwright/test';
import { loginAs, dismissAlertsModal } from '../helpers/auth.js';

// Table IX F4 — Historical Data and Report Generation.
// Baseline pins (quarantined real dataset, is_demo = 0): the Mar-23..Jun-25
// import holds 4,275 production rows over 95 distinct dates totalling 45,345
// eggs at 90.5% average HDEP. Demo rows (Jul-Sep) must never appear in any
// report surface or export. CSV columns for the production report:
// date,cage,breed,eggs,hens,hdep,feed_kg,cp_pct,temp,humidity.

const RANGE = { from: '2026-03-23', to: '2026-06-25' };

function summaryCard(page, label) {
  return page.locator('.kpi-card', { hasText: label });
}

async function downloadCsv(page, url) {
  // Same-session GET (shares the logged-in cookies); the APIRequestContext
  // reads the streamed body directly instead of triggering a download.
  const resp = await page.request.get(url);
  expect(resp.ok()).toBe(true);
  return resp.text();
}

test.describe('Table IX F4 — Historical Data and Report Generation', () => {
  test('TC-19 Mar-Jun production report totals match real-only data, no demo leakage', async ({
    page,
  }) => {
    await loginAs(page, 'admin');
    await page.goto(
      `/reports?type=production&from=${RANGE.from}&to=${RANGE.to}&cage=all`,
    );
    await dismissAlertsModal(page);

    // Summary pills read only real rows.
    await expect(summaryCard(page, 'Total Eggs')).toContainText('45345');
    await expect(summaryCard(page, 'Days Covered')).toContainText('95');
    await expect(summaryCard(page, 'Avg HDEP')).toContainText('90.5%');

    // CSV export: every row inside the real window, totals exact.
    const csv = await downloadCsv(
      page,
      `/reports/csv?type=production&from=${RANGE.from}&to=${RANGE.to}&cage=all`,
    );
    const lines = csv.trim().split('\n');
    const header = lines[0].split(',');
    const rows = lines.slice(1);
    const dateIdx = header.indexOf('date');
    const eggsIdx = header.indexOf('eggs');
    expect(dateIdx).toBeGreaterThanOrEqual(0);
    expect(eggsIdx).toBeGreaterThanOrEqual(0);
    expect(rows.length).toBe(4275);

    let total = 0;
    const dates = new Set();
    for (const row of rows) {
      const cells = row.split(',');
      const d = cells[dateIdx];
      expect(d >= RANGE.from && d <= RANGE.to, `demo date leaked: ${d}`).toBe(true);
      dates.add(d);
      total += Number(cells[eggsIdx]);
    }
    expect(dates.size).toBe(95);
    expect(total).toBe(45345);
  });

  test('TC-20 inverted date range degrades to empty state, no 500', async ({ page }) => {
    await loginAs(page, 'admin');
    const resp = await page.goto(
      '/reports?type=production&from=2026-06-25&to=2026-03-23&cage=all',
    );
    expect(resp.ok()).toBe(true);
    await dismissAlertsModal(page);

    // Designed graceful-empty: message instead of pills/table, no exception.
    await expect(
      page.getByText('No data found for the selected filters.').first(),
    ).toBeVisible();
  });

  test('TC-21 dateless range exports cleanly with zero rows, no crash', async ({ page }) => {
    await loginAs(page, 'admin');
    // A week in 2025 predates every record in the database.
    const resp = await page.goto(
      '/reports?type=production&from=2025-01-06&to=2025-01-12&cage=all',
    );
    expect(resp.ok()).toBe(true);
    await dismissAlertsModal(page);

    await expect(
      page.getByText('No data found for the selected filters.').first(),
    ).toBeVisible();

    // Empty export streams a valid 200 with an empty body (no headers, no
    // exception) — graceful by design, asserted exactly as implemented.
    const csv = await downloadCsv(
      page,
      '/reports/csv?type=production&from=2025-01-06&to=2025-01-12&cage=all',
    );
    expect(csv.trim()).toBe('');
  });
});
