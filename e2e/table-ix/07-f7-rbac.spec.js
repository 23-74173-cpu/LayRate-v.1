import { test, expect } from '@playwright/test';
import { loginAs, csrfToken } from '../helpers/auth.js';

// Table IX F7 — Role-Based Access Control (roles: admin | operator).
// Gating reality (no /admin/* prefix exists here): destructive/admin routes
// carry the `admin` middleware (EnsureAdmin -> 403); GET /forecast is gated
// in-controller (ensureAdminOrRedirect -> bounce, not 403); the sidebar hides
// adminOnly entries (Forecast link, layouts/app.blade.php:304-313); the Team
// panel renders only when $team is non-null (admins).
// TC-12 uses the operator account itself as the role-change subject
// (promote-then-demote), so the suite leaves zero account residue: end state
// always equals start state (operator is operator).

const OPERATOR_ID = 2;
const OPERATOR_ATTRS = { name: 'Farm Operator', email: 'operator@layrate.local' };

async function setRole(request, token, role) {
  return request.put(`/settings/users/${OPERATOR_ID}`, {
    maxRedirects: 0,
    form: { _token: token, _method: 'PUT', ...OPERATOR_ATTRS, role },
  });
}

test.describe('Table IX F7 — Role-Based Access Control', () => {
  test('TC-10 admin sees admin surface and reaches admin routes', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');

    await expect(page.locator('nav a[data-route="forecast"]')).toBeVisible();

    await page.goto('/forecast?scope=farm&horizon=7');
    await expect(page.locator('#forecast-workspace')).toBeVisible();

    await page.goto('/profile?tab=settings');
    await expect(page.getByText('Manage who has access to LayRate')).toBeVisible();
  });

  test('TC-11 operator is denied the admin surface on every layer', async ({ page }) => {
    await loginAs(page, 'operator');
    await page.goto('/dashboard');

    // 1. Navigation layer: no Forecast link.
    await expect(page.locator('nav a[data-route="forecast"]')).toHaveCount(0);

    // 2. Page layer: direct navigation bounces away (in-controller gate).
    await page.goto('/forecast?scope=farm&horizon=7');
    await expect(page).not.toHaveURL(/\/forecast/);
    await expect(page.locator('#forecast-workspace')).toHaveCount(0);

    // 3. Mutation layer: direct POST is refused with 403.
    const token = await csrfToken(page);
    const genResp = await page.request.post('/forecast/generate', {
      maxRedirects: 0,
      form: { _token: token, scope: 'farm', horizon: '7' },
    });
    expect(genResp.status()).toBe(403);

    // 4. Team surface: no panel, and self-promotion is refused.
    await page.goto('/profile?tab=settings');
    await expect(page.getByText('Manage who has access to LayRate')).toHaveCount(0);
    const escResp = await page.request.put(`/settings/users/${OPERATOR_ID}`, {
      maxRedirects: 0,
      form: { _token: await csrfToken(page), _method: 'PUT', ...OPERATOR_ATTRS, role: 'admin' },
    });
    expect(escResp.status()).toBe(403);
    await page.reload();
    await expect(page.locator('nav a[data-route="forecast"]')).toHaveCount(0);
  });

  test('TC-12 mid-session role change takes effect without re-login, no leak', async ({
    page,
    browser,
  }) => {
    await loginAs(page, 'admin');
    const adminToken = await csrfToken(page);

    const opCtx = await browser.newContext({ baseURL: page.url().split('/').slice(0, 3).join('/') });
    const opPage = await opCtx.newPage();
    await loginAs(opPage, 'operator');

    // Promote: privilege gain must be immediate in the live session.
    const promote = await setRole(page.request, adminToken, 'admin');
    expect(promote.status()).toBe(302);
    await opPage.reload();
    await expect(opPage.locator('nav a[data-route="forecast"]')).toBeVisible();
    await opPage.goto('/forecast?scope=farm&horizon=7');
    await expect(opPage.locator('#forecast-workspace')).toBeVisible();

    // Demote: privilege loss must be equally immediate — no leak through the
    // surviving session.
    const demote = await setRole(page.request, adminToken, 'operator');
    expect(demote.status()).toBe(302);
    await opPage.reload();
    await expect(opPage.locator('nav a[data-route="forecast"]')).toHaveCount(0);
    const opToken = await csrfToken(opPage);
    const genResp = await opPage.request.post('/forecast/generate', {
      maxRedirects: 0,
      form: { _token: opToken, scope: 'farm', horizon: '7' },
    });
    expect(genResp.status()).toBe(403);

    await opCtx.close();
  });
});
