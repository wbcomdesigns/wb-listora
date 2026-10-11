import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'walk-count-config.json'), 'utf8'));
const out = { started: new Date().toISOString() };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

function contrast(fg, bg) {
  const parse = (c) => {
    const m = String(c).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (!m) return null;
    return [m[1], m[2], m[3]].map((v) => {
      v = Number(v) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
  };
  const a = parse(fg);
  const b = parse(bg);
  if (!a || !b) return null;
  const L = (rgb) => 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
  const L1 = L(a);
  const L2 = L(b);
  return Number(((Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05)).toFixed(2));
}

const browser = await chromium.launch({ headless: false, slowMo: 160, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// ── 10347569000 count ──
await login(page, cfg.other);
await page.goto(cfg.grid_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
out.vendorGrid = await page.evaluate(() => {
  const card = document.querySelector('.listora-needs__card');
  return {
    title: card?.querySelector('.listora-needs__title')?.innerText?.trim() || '',
    count: card?.querySelector('.listora-needs__responses')?.innerText?.trim() || '',
    body: document.body.innerText.replace(/\s+/g, ' ').slice(0, 400),
  };
});
await page.screenshot({ path: join(shots, 'rft3-count-vendor-grid.png') });

await page.goto(cfg.need_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(600);
out.vendorDetail = await page.evaluate(() => ({
  header: [...document.querySelectorAll('.listora-need-detail__meta-row')].map((row) => ({
    dt: row.querySelector('dt')?.innerText?.trim() || '',
    dd: row.querySelector('dd')?.innerText?.trim() || '',
  })),
}));
await page.screenshot({ path: join(shots, 'rft3-count-vendor-detail.png') });

await login(page, cfg.owner);
await page.goto(cfg.need_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(600);
out.ownerDetail = await page.evaluate(() => ({
  header: [...document.querySelectorAll('.listora-need-detail__meta-row')].map((row) => ({
    dt: row.querySelector('dt')?.innerText?.trim() || '',
    dd: row.querySelector('dd')?.innerText?.trim() || '',
  })),
  empty: document.querySelector('.listora-need-respond__empty')?.innerText?.trim() || '',
  quotes: document.querySelectorAll('.listora-need-respond__item').length,
}));
await page.screenshot({ path: join(shots, 'rft3-count-owner-detail.png') });

if (cfg.edit_url) {
  await page.goto(cfg.edit_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(600);
  out.editBanner = await page.evaluate(() => document.querySelector('.listora-post-need__editing')?.innerText?.trim() || '');
  await page.screenshot({ path: join(shots, 'rft3-count-edit-banner.png') });
}

// ── 10347037187 hover ──
await page.context().clearCookies();
await page.goto(cfg.listing_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
await page.waitForTimeout(600);

const actionBtns = page.locator('.listora-detail__actions .listora-btn');
const n = await actionBtns.count();
out.hover = { buttons: [] };
for (let i = 0; i < n; i++) {
  const btn = actionBtns.nth(i);
  const label = ((await btn.innerText()) || '').replace(/\s+/g, ' ').trim();
  const before = await btn.evaluate((el) => ({
    font: getComputedStyle(el).fontSize,
    label: el.innerText.trim(),
  }));
  await btn.hover();
  await page.waitForTimeout(200);
  const after = await btn.evaluate((el) => ({
    font: getComputedStyle(el).fontSize,
    hovered: el.matches(':hover'),
  }));
  out.hover.buttons.push({ label, before: before.font, after: after.font, hovered: after.hovered, grew: before.font !== after.font });
}
await page.screenshot({ path: join(shots, 'rft3-hover-actions.png') });

// Q1: strip the pin and prove the old jump still exists
await page.addStyleTag({ content: '.listora-detail__actions .listora-btn:hover,.listora-detail__actions .listora-btn:focus{font-size:unset !important;}' });
const directions = page.locator('.listora-detail__actions .listora-btn', { hasText: /direction/i }).first();
if (await directions.count()) {
  const base = await directions.evaluate((el) => getComputedStyle(el).fontSize);
  await directions.hover();
  await page.waitForTimeout(200);
  const jumped = await directions.evaluate((el) => ({ font: getComputedStyle(el).fontSize, hovered: el.matches(':hover') }));
  out.hover.preFix = { base, jumped: jumped.font, hovered: jumped.hovered, grew: base !== jumped.font };
}
await page.screenshot({ path: join(shots, 'rft3-hover-unpinned.png') });

writeFileSync(join(dir, 'report-rft3.json'), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await page.waitForTimeout(300);
await browser.close();
