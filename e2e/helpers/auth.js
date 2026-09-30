// Shared login helper for Table IX specs. Seeded dev credentials
// (DatabaseSeeder/UserSeeder): both accounts use password "password".
export const USERS = {
  admin: { email: 'admin@layrate.local', password: 'password' },
  operator: { email: 'operator@layrate.local', password: 'password' },
};

/** Sign in through the real login form (fetch-intercepted; ends in navigation). */
export async function loginAs(page, role) {
  const user = USERS[role];
  if (!user) throw new Error(`unknown role: ${role}`);
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(user.email);
  await page.locator('input[name="password"]').fill(user.password);
  await Promise.all([
    page.waitForURL(/dashboard|forecast|profile|environment/, { timeout: 15_000 }),
    page.locator('button[type="submit"]').click(),
  ]);
}

/** CSRF token for same-session page.request POSTs (shares cookies with page). */
export async function csrfToken(page) {
  return page.locator('meta[name="csrf-token"]').getAttribute('content');
}

/**
 * Dismiss the unacknowledged-alerts modal when it is up. It covers the full
 * viewport and intercepts pointer events, so any test that clicks page
 * controls must call this first. No-op when no alerts are pending.
 * NOTE: dismissal acknowledges the alerts server-side (the app's own flow).
 */
export async function dismissAlertsModal(page) {
  const modal = page.locator('#alerts-modal');
  let visible = false;
  try {
    visible = await modal.isVisible({ timeout: 2_000 });
  } catch {
    visible = false;
  }
  if (!visible) return;
  await page.keyboard.press('Escape');
  await modal.waitFor({ state: 'hidden', timeout: 10_000 });
}
