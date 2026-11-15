// Appendix E: Admin interface screenshot capture (not a functional test).
//
// Usage (local dev/QA only; credentials come from the environment, never
// from this file):
//   LAYRATE_ADMIN_EMAIL=... LAYRATE_ADMIN_PASSWORD=... \
//     node e2e/appendix-e/capture-admin-screenshots.mjs [--only=id,id] [--only-failed]
//
// Safety: signs in through the real login form, then aborts every
// non-GET request so no click can create, change, acknowledge or delete
// anything. Overlays that would need a server call to dismiss are hidden
// client-side only. Refuses non-local base URLs.
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.LAYRATE_BASE_URL ?? 'http://127.0.0.1:8000';
const OUT = path.resolve(process.env.LAYRATE_SHOT_DIR ?? 'manuscript-evidence/admin-interface-screenshots');
const RESULTS = path.join(OUT, 'capture-results.json');
const EMAIL = process.env.LAYRATE_ADMIN_EMAIL;
const PASSWORD = process.env.LAYRATE_ADMIN_PASSWORD;

if (!['127.0.0.1', 'localhost'].includes(new URL(BASE).hostname)) {
  console.error(`Refusing non-local base URL: ${BASE}`);
  process.exit(2);
}
if (!EMAIL || !PASSWORD) {
  console.error('Set LAYRATE_ADMIN_EMAIL and LAYRATE_ADMIN_PASSWORD.');
  process.exit(2);
}

const args = process.argv.slice(2);
const onlyArg = args.find(a => a.startsWith('--only='));
let only = onlyArg ? new Set(onlyArg.slice(7).split(',')) : null;
const onlyFull = args.includes('--only-full');
if (args.includes('--only-failed') && fs.existsSync(RESULTS)) {
  only = new Set(Object.entries(JSON.parse(fs.readFileSync(RESULTS, 'utf8'))).filter(([, r]) => r.status !== 'ok').map(([id]) => id));
}

// ── helpers ──────────────────────────────────────────────────────────────
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function settle(page, ms = 1500) {
  await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
  await sleep(ms);
}

async function goto(page, url) {
  await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
  await settle(page, 2200);
  // Freeze animations (e.g. the floating dock button) so clicks are stable
  // and captures are deterministic.
  await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' });
  await hideBlockingOverlays(page);
}

/** Open the quick-actions dock menu through its own button. */
async function openDock(page) {
  if (await isShown(page, '#dockFabMenu')) return;
  await page.locator('#quickDock button[aria-label="Quick actions"]').click();
  await sleep(600);
  if (!(await isShown(page, '#dockFabMenu'))) throw new Error('#dockFabMenu did not open');
}

/** Hide (client-side only) overlays that would otherwise cover every page. */
async function hideBlockingOverlays(page) {
  await page.evaluate(() => {
    for (const id of ['alerts-modal', 'yesterdaySummaryModal', 'dayCompleteModal']) {
      const el = document.getElementById(id);
      if (el) el.style.setProperty('display', 'none', 'important');
    }
  });
}

/** Full-length capture: the app scrolls an inner container, not the
 *  document, so grow the viewport to the content height for the shot. */
async function fullShot(page, file) {
  const extra = await page.evaluate(() => {
    const scrollers = [...document.querySelectorAll('*')].filter(el => {
      const oy = getComputedStyle(el).overflowY;
      return (oy === 'auto' || oy === 'scroll') && el.scrollHeight > el.clientHeight + 4 && el.clientHeight > 300;
    });
    const main = scrollers.sort((a, b) => b.clientWidth * b.clientHeight - a.clientWidth * a.clientHeight)[0];
    if (main) main.scrollTop = 0;
    return main ? main.scrollHeight - main.clientHeight : 0;
  });
  const height = Math.min(900 + extra, 8000);
  await page.setViewportSize({ width: 1440, height });
  await sleep(900);
  await page.screenshot({ path: file, fullPage: true });
  await page.setViewportSize({ width: 1440, height: 900 });
}

async function isShown(page, selector) {
  return page.evaluate(sel => {
    const el = document.querySelector(sel);
    if (!el) return false;
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return cs.display !== 'none' && cs.visibility !== 'hidden' && r.width > 0 && r.height > 0;
  }, selector);
}

/** Click the first visible element whose onclick calls fnName(; page
 *  actions that live in the quick-actions dock are reached by opening it. */
async function clickOnclick(page, fnName) {
  const find = () => page.evaluateHandle(fn => [...document.querySelectorAll('[onclick]')]
    .find(el => el.getAttribute('onclick').includes(fn + '(') && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden'), fnName);
  let el = (await find()).asElement();
  if (!el && (await page.locator(`#dockFabMenu [onclick*="${fnName}("]`).count())) {
    await openDock(page);
    el = (await find()).asElement();
  }
  if (!el) return false;
  await el.scrollIntoViewIfNeeded();
  // Same element and handler; dispatch directly if a sibling overlay keeps
  // Playwright's actionability check from settling.
  await el.click({ timeout: 5_000 }).catch(() => el.evaluate(e => e.click()));
  return true;
}

async function clickText(page, selector, text) {
  let loc = page.locator(selector, { hasText: text }).filter({ visible: true }).first();
  if (!(await loc.count()) && (await page.locator('#dockFabMenu button, #dockFabMenu a', { hasText: text }).count())) {
    await openDock(page);
    loc = page.locator('#dockFabMenu button, #dockFabMenu a', { hasText: text }).filter({ visible: true }).first();
  }
  if (!(await loc.count())) return false;
  await loc.click();
  return true;
}

/** Open a modal through its real trigger; returns how it was opened. */
async function openModal(page, modalSel, { fn, text, textSel = 'button, a', click } = {}) {
  if (click && (await page.locator(click).filter({ visible: true }).count())) await page.locator(click).filter({ visible: true }).first().click();
  else if (fn && (await clickOnclick(page, fn))) {} // eslint-disable-line no-empty
  else if (text && (await clickText(page, textSel, text))) {} // eslint-disable-line no-empty
  else throw new Error(`no visible trigger for ${modalSel}`);
  await sleep(900);
  await settle(page, 600);
  if (!(await isShown(page, modalSel))) throw new Error(`${modalSel} did not open`);
}

async function clickTab(page, name) {
  const tab = page.getByRole('tab', { name, exact: false }).filter({ visible: true }).first();
  if (!(await tab.count())) throw new Error(`tab not found: ${name}`);
  await tab.click();
  await settle(page, 1800);
  await hideBlockingOverlays(page);
}

// ── capture plan ─────────────────────────────────────────────────────────
// Each entry: id, file (folder/NN-name.png), url, optional prep, full (page) flag.
const plan = [];
const add = (id, file, url, prep, opts = {}) => plan.push({ id, file, url, prep, ...opts });

// 01 Dashboard
add('dash-overview', '01-dashboard/01-dashboard--production-performance.png', '/dashboard');
add('dash-overview-full', '01-dashboard/02-dashboard--production-performance-full-page.png', '/dashboard', null, { full: true });
add('dash-env', '01-dashboard/03-dashboard--environmental-analytics-tab.png', '/dashboard', p => clickTab(p, 'Environmental Analytics'), { full: true });
add('dash-feed', '01-dashboard/04-dashboard--feed-analytics-tab.png', '/dashboard', p => clickTab(p, 'Feed Analytics'), { full: true });
add('dash-flock', '01-dashboard/05-dashboard--flock-analytics-tab.png', '/dashboard', p => clickTab(p, 'Flock Analytics'), { full: true });
add('dash-kpi-info', '01-dashboard/06-dashboard--kpi-information-popover.png', '/dashboard', async p => {
  await p.locator('.kpi-info-btn').filter({ visible: true }).first().click(); await sleep(700);
});
add('dash-kpi-breakdown', '01-dashboard/07-dashboard--kpi-per-cage-breakdown-modal.png', '/dashboard', async p => {
  await p.locator('.kpi-info-btn[data-kpi-breakdown]').filter({ visible: true }).first().click(); await sleep(600);
  const more = p.locator('body > *:last-child button, [data-kpi-popover-more], .kpi-popover button').filter({ visible: true }).last();
  if (await more.count()) await more.click();
  await sleep(1200);
  if (!(await isShown(p, '#kpiModal'))) throw new Error('#kpiModal did not open');
});
add('dash-checklist', '01-dashboard/08-dashboard--todays-checklist-panel.png', '/dashboard', async p => {
  await p.locator('#quickDock button[aria-label="Open checklist"]').click(); await sleep(900);
  if (!(await isShown(p, '#dataChecklistPanel'))) throw new Error('#dataChecklistPanel did not open');
});
add('dash-account-menu', '01-dashboard/09-dashboard--account-menu.png', '/dashboard', async p => {
  await p.locator('#profileMenuBtn').click(); await sleep(600);
  if (!(await isShown(p, '#profileMenu'))) throw new Error('#profileMenu did not open');
});

// 02 Cages
add('cages-main', '02-cages/01-cages--farm-layout-and-cage-list.png', '/cages');
add('cages-full', '02-cages/02-cages--full-page.png', '/cages', null, { full: true });
add('cages-add', '02-cages/03-cages--add-cage-modal.png', '/cages', p => openModal(p, '#addCageModal', { fn: 'openAddModal' }));
add('cages-edit', '02-cages/04-cages--edit-cage-modal.png', '/cages', async p => {
  await clickOnclick(p, 'toggleAllCages'); await sleep(800);
  await openModal(p, '#editCageModal', { fn: 'openEditModal' });
});
add('cages-quick-actions', '02-cages/07-cages--quick-actions-menu.png', '/cages', p => openDock(p));
add('cages-grid', '02-cages/05-cages--farm-grid-settings-modal.png', '/cages', p => openModal(p, '#gridSettingsModal', { fn: 'openGridSettings' }));
add('cages-info', '02-cages/06-cages--cage-information-popup.png', '/cages', async p => {
  const btn = p.locator('[data-cage-info], [data-cage-info-btn], .cage-info-btn').filter({ visible: true }).first();
  if (await btn.count()) await btn.click();
  else await p.locator('[data-cage-id]').filter({ visible: true }).first().click();
  await sleep(1200);
  if (!(await isShown(p, '#cageInfoPopup'))) throw new Error('#cageInfoPopup did not open');
});

// 03 Hens
add('hens-inventory', '03-hens/01-hens--inventory-tab.png', '/chickens');
add('hens-cage-modal', '03-hens/02-hens--cage-details-modal.png', '/chickens', async p => {
  await p.locator('.cage-overview-card').first().click(); await sleep(900);
  if (!(await isShown(p, '#cageSlotsModal'))) throw new Error('#cageSlotsModal did not open');
});
add('hens-slot-hens', '03-hens/03-hens--cage-details-slot-hens-panel.png', '/chickens', async p => {
  await p.locator('.cage-overview-card').first().click(); await sleep(900);
  await p.locator('#cageSlotsModal .slot-mini').filter({ visible: true }).first().click(); await sleep(700);
});
add('hens-register', '03-hens/04-hens--register-hens-modal.png', '/chickens', p => openModal(p, '#registerModal', { fn: 'openRegisterModal' }));
add('hens-foot-tags', '03-hens/05-hens--print-foot-tags-modal.png', '/chickens', p => openModal(p, '#henFootTagsModal', { text: 'Foot Tags' }));
add('hens-mortality', '03-hens/06-hens--mortality-tab.png', '/chickens', p => clickTab(p, 'Mortality'), { full: true });
add('hens-culled', '03-hens/07-hens--culled-tab.png', '/chickens', p => clickTab(p, 'Culled'));
add('hens-removed', '03-hens/08-hens--removed-tab.png', '/chickens', p => clickTab(p, 'Removed'));
for (const [i, [fn, sel, label]] of [
  ['openHealthEventModal', '#healthEventModal', 'health-event'],
  ['openWeightCheckModal', '#weightCheckModal', 'weight-check'],
  ['openCullModal', '#cullModal', 'cull-hen'],
  ['openRemovalModal', '#removalModal', 'remove-hen'],
].entries()) {
  add(`hens-${label}`, `03-hens/${String(9 + i).padStart(2, '0')}-hens--${label}-modal.png`, '/chickens', async p => {
    await p.locator('.cage-overview-card').first().click(); await sleep(900);
    await p.locator('#cageSlotsModal .slot-mini').filter({ visible: true }).first().click(); await sleep(700);
    await openModal(p, sel, { fn });
  });
}

// 04 Egg Management (sub-tabs are links)
const eggTabs = [
  ['egg-history', '/eggs/production-history', 'production-history'],
  ['egg-logging', '/eggs/logging', 'egg-logging'],
  ['egg-recent', '/eggs/recent-logs', 'recent-logs'],
  ['egg-stocks', '/eggs/stocks', 'egg-stocks'],
  ['egg-preorders', '/eggs/pre-orders', 'pre-orders'],
  ['egg-hdep-history', '/egg-production-history', 'history'],
];
eggTabs.forEach(([id, url, label], i) => add(id, `04-egg-management/${String(i + 1).padStart(2, '0')}-egg-management--${label}-tab.png`, url, null, { full: true }));
// Override Count is documented as not captured in capture-report.md: it
// needs a slot with a sensor reading on the manual-entry form.
add('egg-quick-actions', '04-egg-management/07-egg-management--stocks-quick-actions-menu.png', '/eggs/stocks', p => openDock(p));
add('egg-prices', '04-egg-management/08-egg-management--egg-prices-modal.png', '/eggs/stocks', p => openModal(p, '#eggPricesModal', { text: 'Egg Prices' }));
add('egg-add-stock', '04-egg-management/09-egg-management--add-stock-modal.png', '/eggs/stocks', p => openModal(p, '#addStockModal', { text: 'Add Stock' }));
add('egg-thresholds', '04-egg-management/10-egg-management--stock-thresholds-modal.png', '/eggs/stocks', p => openModal(p, '#thresholdsModal', { text: 'Thresholds' }));
add('egg-weights', '04-egg-management/11-egg-management--egg-weights-modal.png', '/eggs/stocks', p => openModal(p, '#eggWeightsModal', { text: 'Egg Weights' }));
add('egg-labels', '04-egg-management/12-egg-management--print-qr-labels-modal.png', '/eggs/stocks', p => openModal(p, '#eggLabelsModal', { text: 'Print All QR Labels' }));
add('egg-add-order', '04-egg-management/13-egg-management--add-pre-order-modal.png', '/eggs/pre-orders', p => openModal(p, '#addOrderModal', { text: 'Add Pre-Order' }));
add('egg-edit-log', '04-egg-management/14-egg-management--edit-production-log-modal.png', '/eggs/recent-logs', async p => {
  await p.locator('[onclick*="openEditLog("]').filter({ visible: true }).first().waitFor({ timeout: 15_000 });
  await openModal(p, '#editLogModal', { fn: 'openEditLog' });
});

// 05 Feeds Management
add('feed-batches', '05-feeds-management/01-feeds-management--feed-batches-tab.png', '/feed', null, { full: true });
add('feed-consumption', '05-feeds-management/02-feeds-management--daily-consumption-tab.png', '/feed', p => clickTab(p, 'Daily Consumption'), { full: true });
add('feed-fcr', '05-feeds-management/03-feeds-management--fcr-tab.png', '/feed', p => clickTab(p, 'FCR'), { full: true });
add('feed-add-batch', '05-feeds-management/04-feeds-management--add-feed-batch-modal.png', '/feed', p => openModal(p, '#addBatchModal', { text: 'Add Feed Batch' }));
add('feed-edit-batch', '05-feeds-management/05-feeds-management--edit-feed-batch-modal.png', '/feed', p => openModal(p, '#editBatchModal', { fn: 'openEditBatch' }));
add('feed-log-consumption', '05-feeds-management/06-feeds-management--log-consumption-modal.png', '/feed', async p => {
  await clickTab(p, 'Daily Consumption');
  await openModal(p, '#consumptionModal', { text: 'Add Consumption' });
});
add('feed-fcr-guide', '05-feeds-management/08-feeds-management--fcr-guide-modal.png', '/feed', async p => {
  await clickTab(p, 'FCR');
  await openModal(p, '#fcr-guide-modal', { fn: 'openFcrGuideModal' });
});
add('feed-farm-entry', '05-feeds-management/07-feeds-management--whole-farm-entry-modal.png', '/feed', async p => {
  await clickTab(p, 'Daily Consumption');
  await openModal(p, '#farmEntryModal', { text: 'Log Whole-Farm Feeding' });
});

// 06 Environment
add('env-live', '06-environment/01-environment--live-data-tab.png', '/environment', null, { full: true });
add('env-logs', '06-environment/02-environment--log-history-tab.png', '/environment', p => clickTab(p, 'Log History'), { full: true });
add('env-thresholds', '06-environment/03-environment--alert-thresholds-modal.png', '/environment', p => openModal(p, '#envThresholdsModal', { fn: 'openEnvThresholdsModal' }));
add('env-fan', '06-environment/04-environment--fan-control-modal.png', '/environment', p => openModal(p, '#envFanModal', { fn: 'openEnvFanModal' }));
add('env-manual', '06-environment/05-environment--manual-reading-modal.png', '/environment', p => openModal(p, '#envManualModal', { fn: 'openEnvManualModal' }));
add('env-override', '06-environment/06-environment--override-log-modal.png', '/environment', async p => {
  await clickTab(p, 'Log History');
  await p.locator('[onclick*="openEnvLogOverride("]').filter({ visible: true }).first().waitFor({ timeout: 15_000 });
  await openModal(p, '#envLogOverrideModal', { fn: 'openEnvLogOverride' });
});

// 07 Hardware
add('hw-main', '07-hardware/01-hardware--device-overview.png', '/hardware', null, { full: true });
add('hw-add', '07-hardware/02-hardware--add-device-modal.png', '/hardware', p => openModal(p, '#addModal', { text: 'Add Device' }));
add('hw-edit', '07-hardware/03-hardware--edit-device-modal.png', '/hardware', p => openModal(p, '#editModal', { fn: 'openEditModal' }));

// 08 Forecast
const FC_SAVED = '/forecast?scope=cage&cage=CAGE-A&horizon=7&forecast_view=saved';
add('fc-main', '08-forecast/01-forecast--7-day-forecast-results.png', FC_SAVED);
add('fc-main-full', '08-forecast/01b-forecast--7-day-forecast-full-page.png', FC_SAVED, null, { full: true });
add('fc-compare', '08-forecast/01c-forecast--compare-production-full-page.png', FC_SAVED + '&compare_production=1', null, { full: true });
add('fc-day', '08-forecast/02-forecast--day-forecast-modal.png', '/forecast', async p => {
  const cell = p.locator('.calendar-day[data-selectable="true"]').filter({ visible: true }).first();
  if (!(await cell.count())) throw new Error('no calendar day cell');
  await cell.click(); await sleep(1500);
  if (!(await isShown(p, '#forecastDayModal'))) throw new Error('#forecastDayModal did not open');
});
// Forecast quick actions; "Download input sheet" is a file download, not a screen.
const anyDialogShown = p => p.evaluate(() => [...document.querySelectorAll('[role="dialog"], [data-modal], [id$="Modal"], [id$="Overlay"]')]
  .some(m => getComputedStyle(m).display !== 'none' && m.getBoundingClientRect().width > 0 && m.id !== 'dockFabMenu'));
for (const [i, [text, label]] of [
  ['View input records status', 'input-records-status'],
  ['Import production data', 'import-production-data'],
  ['Export Forecast Report', 'export-forecast-report'],
].entries()) {
  add(`fc-${label}`, `08-forecast/${String(3 + i).padStart(2, '0')}-forecast--${label}.png`, '/forecast', async p => {
    await openDock(p);
    await p.locator('#dockFabMenu button, #dockFabMenu a', { hasText: text }).first().click();
    await sleep(1500); await settle(p, 800);
    if (new URL(p.url()).pathname === '/forecast' && !(await anyDialogShown(p))) throw new Error(`${text}: no dialog or page opened`);
  });
}

// 09 Reports
add('rep-production', '09-reports/01-reports--production-report.png', '/reports?type=production', null, { full: true });
add('rep-feed', '09-reports/02-reports--feed-report.png', '/reports?type=feed', null, { full: true });
add('rep-environment', '09-reports/03-reports--environment-report.png', '/reports?type=environment', null, { full: true });
add('rep-mortality', '09-reports/04-reports--mortality-report.png', '/reports?type=mortality', null, { full: true });
add('rep-all', '09-reports/05-reports--all-reports.png', '/reports?type=all', null, { full: true });
add('rep-filters', '09-reports/06-reports--filter-panel.png', '/reports', async p => {
  await p.locator('#navbar-filters-btn').filter({ visible: true }).click(); await sleep(800);
});

// 10 Notes
add('notes-main', '10-notes/01-notes--notes-list.png', '/notes', null, { full: true });
add('notes-edit', '10-notes/02-notes--edit-note-modal.png', '/notes', p => openModal(p, '#noteEditModal', { fn: 'openNoteEdit' }));

// 11 Notifications
add('notif-main', '11-notifications/01-notifications--alerts-list.png', '/notifications', null, { full: true });

// 12 Profile & settings
add('prof-profile', '12-profile-and-settings/01-profile--profile-tab.png', '/profile', null, { full: true });
add('prof-settings', '12-profile-and-settings/02-profile--profile-settings-tab.png', '/profile', p => clickTab(p, 'Profile Settings'), { full: true });
add('prof-system', '12-profile-and-settings/03-profile--system-settings-tab.png', '/profile', p => clickTab(p, 'System Settings'), { full: true });
add('prof-manual', '12-profile-and-settings/04-profile--user-manual-tab.png', '/profile', p => clickTab(p, 'User Manual'));
add('prof-add-user', '12-profile-and-settings/05-profile--add-user-modal.png', '/profile', async p => {
  await clickTab(p, 'Profile Settings');
  await openModal(p, '#addUserModal', { fn: 'openAddUserModal' });
});
add('prof-edit-user', '12-profile-and-settings/06-profile--edit-user-modal.png', '/profile', async p => {
  await clickTab(p, 'Profile Settings');
  await openModal(p, '#editUserModal', { fn: 'openEditUserModal' });
});
add('prof-system-time', '12-profile-and-settings/07-settings--system-time.png', '/settings/system-time');

// 13 Pages reachable only by URL (no navigation link)
add('fin-main', '13-unlinked-admin-pages/01-finance--expenses.png', '/finance', null, { full: true });
add('mort-main', '13-unlinked-admin-pages/02-mortality--records.png', '/mortality', null, { full: true });
add('mort-edit', '13-unlinked-admin-pages/03-mortality--edit-record-modal.png', '/mortality', p => openModal(p, '#editMortalityModal', { fn: 'openEditMortality' }));

// ── run ──────────────────────────────────────────────────────────────────
const results = fs.existsSync(RESULTS) ? JSON.parse(fs.readFileSync(RESULTS, 'utf8')) : {};
const browser = await chromium.launch();
const viewport = { width: 1440, height: 900 };

// Login page (signed out), plus the two first-load overlays as states.
if (!onlyFull && (!only || only.has('login') || only.has('dash-alerts') || only.has('dash-yesterday'))) {
  const ctx = await browser.newContext({ viewport, deviceScaleFactor: 1, colorScheme: 'light' });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login'); await settle(page, 1200);
  if (!only || only.has('login')) {
    fs.mkdirSync(path.join(OUT, '00-login'), { recursive: true });
    await page.screenshot({ path: path.join(OUT, '00-login/01-login--sign-in-page.png') });
    results.login = { file: '00-login/01-login--sign-in-page.png', status: 'ok' };
  }
  await ctx.close();
}

const ctx = await browser.newContext({ viewport, deviceScaleFactor: 1, colorScheme: 'light' });
const page = await ctx.newPage();
const jsErrors = [];
page.on('pageerror', e => jsErrors.push(e.message));
await page.goto(BASE + '/login');
await page.fill('input[name="email"]', EMAIL);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForURL(u => !u.pathname.startsWith('/login'), { timeout: 20_000 }), page.click('button[type="submit"]')]);

// From here on, nothing may write: abort every non-GET request.
const blocked = [];
await page.route('**/*', route => {
  const req = route.request();
  if (req.method() !== 'GET' && req.method() !== 'HEAD') { blocked.push(`${req.method()} ${new URL(req.url()).pathname}`); return route.abort(); }
  return route.continue();
});

// First-load overlays on the dashboard, captured once as UI states.
await page.goto(BASE + '/dashboard'); await settle(page, 2500);
for (const [id, sel, file] of onlyFull ? [] : [
  ['dash-alerts', '#alerts-modal', '01-dashboard/00a-dashboard--unacknowledged-alerts-modal.png'],
  ['dash-yesterday', '#yesterdaySummaryModal', '01-dashboard/00b-dashboard--yesterdays-production-record-modal.png'],
]) {
  if (only && !only.has(id)) continue;
  if (await isShown(page, sel)) {
    fs.mkdirSync(path.dirname(path.join(OUT, file)), { recursive: true });
    await page.screenshot({ path: path.join(OUT, file) });
    results[id] = { file, status: 'ok' };
    await page.evaluate(s => document.querySelector(s).style.setProperty('display', 'none', 'important'), sel);
  } else {
    results[id] = { file, status: 'skipped', reason: `${sel} not shown in this session` };
  }
}

for (const item of plan) {
  if (only && !only.has(item.id)) continue;
  if (onlyFull && !item.full) continue;
  const out = path.join(OUT, item.file);
  fs.mkdirSync(path.dirname(out), { recursive: true });
  const errorsBefore = jsErrors.length;
  try {
    await goto(page, item.url);
    if (new URL(page.url()).pathname.startsWith('/login')) throw new Error('session lost (redirected to login)');
    if (item.prep) await item.prep(page);
    await page.mouse.move(820, 18); // neutral spot in the top bar: no hover tooltips
    await sleep(400);
    if (item.full) await fullShot(page, out);
    else await page.screenshot({ path: out });
    results[item.id] = { file: item.file, status: 'ok', url: item.url, jsErrors: jsErrors.slice(errorsBefore) };
    console.log('ok      ', item.file);
  } catch (e) {
    results[item.id] = { file: item.file, status: 'failed', url: item.url, reason: e.message.split('\n')[0] };
    if (fs.existsSync(out)) fs.unlinkSync(out);
    console.log('FAILED  ', item.file, '-', e.message.split('\n')[0]);
  }
  await page.keyboard.press('Escape').catch(() => {});
}

fs.writeFileSync(RESULTS, JSON.stringify(results, null, 2));
console.log(`\nBlocked write requests: ${blocked.length ? [...new Set(blocked)].join(', ') : 'none'}`);
console.log(`ok=${Object.values(results).filter(r => r.status === 'ok').length} failed=${Object.values(results).filter(r => r.status === 'failed').length} skipped=${Object.values(results).filter(r => r.status === 'skipped').length}`);
await browser.close();
