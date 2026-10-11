import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'walk-count-config.json'), 'utf8'));
const out = { started: new Date().toISOString(), cfg };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

async function measureNeed(page) {
  return page.evaluate(() => {
    const card = document.querySelector('.listora-needs__card');
    const title = card?.querySelector('.listora-needs__title')?.innerText?.trim() || '';
    const count = card?.querySelector('.listora-needs__responses')?.innerText?.trim() || '';
    const header = [...document.querySelectorAll('.listora-need-detail__meta-row')].map((row) => ({
      dt: row.querySelector('dt')?.innerText?.trim() || '',
      dd: row.querySelector('dd')?.innerText?.trim() || '',
    }));
    const empty = document.querySelector('.listora-need-respond__empty')?.innerText?.trim() || '';
    const quotes = [...document.querySelectorAll('.listora-need-respond__item')].length;
    const body = document.body.innerText.replace(/\s+/g, ' ').slice(0, 900);
    return { url: location.href, title, count, header, empty, quotes, body };
  });
}

async function openDashTab(page, label) {
  const tab = page.getByRole('tab', { name: new RegExp(label, 'i') }).first();
  if (await tab.count()) await tab.click().catch(() => {});
  else await page.locator('.listora-dashboard__nav-item, [role="tab"]').filter({ hasText: new RegExp(label, 'i') }).first().click().catch(() => {});
  await page.waitForTimeout(500);
}

const browser = await chromium.launch({ headless: false, slowMo: 200, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, cfg.other);
await page.goto(cfg.grid_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
out.vendorGrid = await measureNeed(page);
await page.screenshot({ path: join(shots, 'count-01-vendor-grid-lie.png'), fullPage: false });
await page.setViewportSize({ width: 390, height: 844 });
await page.waitForTimeout(300);
await page.screenshot({ path: join(shots, 'count-01b-vendor-grid-390.png'), fullPage: false });
await page.setViewportSize({ width: 1440, height: 900 });

if (await page.locator('.listora-needs__title a').first().count()) {
  await page.locator('.listora-needs__title a').first().click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(700);
}
out.vendorDetail = await measureNeed(page);
await page.screenshot({ path: join(shots, 'count-02-vendor-detail-lie.png'), fullPage: true });

await login(page, cfg.owner);
await page.goto(cfg.need_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
out.ownerDetail = await measureNeed(page);
await page.screenshot({ path: join(shots, 'count-03-owner-detail-empty.png'), fullPage: true });

await page.goto(cfg.dash_needs, { waitUntil: 'domcontentloaded', timeout: 45000 });
await openDashTab(page, 'Needs');
await openDashTab(page, 'My Needs');
await page.waitForTimeout(600);
const row = page.locator(`.listora-dashboard__need-row[data-need-id="${cfg.need_id}"]`);
if (await row.count()) {
  await row.scrollIntoViewIfNeeded();
  out.ownerDash = await row.evaluate((el) => ({
    title: el.querySelector('.listora-dashboard__need-title')?.innerText?.trim() || '',
    count: el.querySelector('.listora-dashboard__need-respcount')?.innerText?.trim() || '',
    quotes: el.querySelectorAll('.listora-need-respond__item, .listora-dashboard__response').length,
    text: el.innerText.replace(/\s+/g, ' ').slice(0, 400),
  }));
  await row.screenshot({ path: join(shots, 'count-04-owner-dash.png') }).catch(async () => {
    await page.screenshot({ path: join(shots, 'count-04-owner-dash.png') });
  });
} else {
  out.ownerDash = { missing: true, body: (await page.locator('body').innerText()).slice(0, 500) };
  await page.screenshot({ path: join(shots, 'count-04-owner-dash.png') });
}

await login(page, cfg.other);
await page.goto(cfg.honest_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
out.honestDetail = await measureNeed(page);
await page.screenshot({ path: join(shots, 'count-05-honest-7148.png'), fullPage: false });

writeFileSync(join(dir, 'report-count.json'), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await page.waitForTimeout(400);
await browser.close();
