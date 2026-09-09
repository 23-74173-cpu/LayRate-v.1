import { chromium } from 'playwright';
import fs from 'fs';

const CHROME = 'C:\\Users\\donor\\AppData\\Local\\ms-playwright\\chromium-1234\\chrome-win64\\chrome.exe';
const BASE = 'http://127.0.0.1:8899';

const browser = await chromium.launch({ headless: true, executablePath: CHROME });
const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });

await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
await page.fill('input[name="email"]', 'admin@layrate.local');
await page.fill('input[name="password"]', 'password');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
  page.click('button[type="submit"]'),
]);

await page.goto(BASE + '/cages', { waitUntil: 'networkidle' });
await page.waitForTimeout(1500);

const infoBtns = await page.locator('.cage-info-btn').count();
console.log('info buttons:', infoBtns);
if (infoBtns > 0) {
  await page.locator('.cage-info-btn').first().click();
  await page.waitForTimeout(1200);
  const popupVisible = await page.locator('#cageInfoPopup:not(.hidden)').count();
  console.log('popup visible:', popupVisible);
  if (popupVisible > 0) {
    await page.screenshot({ path: 'shot_popup_front.png' });
    // click Details (front header) to flip
    const det = page.locator('#cageInfoPopup button[onclick*="flipCageInfoPopup"]').first();
    await det.click();
    await page.waitForTimeout(800);
    await page.screenshot({ path: 'shot_popup_back.png' });
    // measure label/value positions for spec rows
    const ls = await page.evaluate(() => {
      const flipper = document.getElementById('cageInfoFlipper');
      const rows = flipper.querySelectorAll('.back-face > div > div > div > div');
      const out = [];
      flipper.querySelectorAll('.back-face [style*="border-top"]').forEach(r => {
        if (r.localName !== 'div') return;
        const spans = r.children;
        if (spans.length < 2) return;
        const label = spans[0], value = spans[1];
        const lr = label.getBoundingClientRect();
        const vr = value.getBoundingClientRect();
        out.push({ label: label.textContent.trim(), labelRight: Math.round(lr.right), valueLeft: Math.round(vr.left), gap: Math.round(vr.left - lr.right) });
      });
      const rows2 = [];
      flipper.querySelectorAll('.back-face .rounded-lg > div').forEach(r => {
        const spans = r.children;
        if (spans.length < 2) return;
        const lr = spans[0].getBoundingClientRect();
        const vr = spans[1].getBoundingClientRect();
        rows2.push({ label: spans[0].textContent.trim(), labelRight: Math.round(lr.right), valueLeft: Math.round(vr.left), gap: Math.round(vr.left - lr.right) });
      });
      return rows2;
    });
    console.log(JSON.stringify(ls, null, 2));
  }
}
await browser.close();
console.log('done');