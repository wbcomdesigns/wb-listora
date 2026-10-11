import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const out = { started: new Date().toISOString(), pages: {} };

const BASE = 'http://listora.local';
const URLS = {
  home: `${BASE}/`,
  listings: `${BASE}/listings/`,
  listing: `${BASE}/listing/sakura-house/`,
  add: `${BASE}/add-listing/`,
  dash: `${BASE}/my-listings/`,
  needs: `${BASE}/needs/`,
  postNeed: `${BASE}/post-need/`,
  buy: `${BASE}/buy-credits-2/`,
  adminSettings: `${BASE}/wp-admin/admin.php?page=listora-settings`,
  adminListings: `${BASE}/wp-admin/edit.php?post_type=listora_listing`,
  adminNeeds: `${BASE}/wp-admin/admin.php?page=listora-needs&need_status=published`,
};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${BASE}/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

async function probe(page) {
  return page.evaluate(() => {
    const root = document.querySelector('.wb-listora, .listora-app, .listora-dashboard, .listora-directory, .listora-detail, .listora-needs, .listora-post-need, .listora-credits, .wb-listora-admin, [class*="listora-"]');
    const broken = [...document.images].filter((img) => img.complete && img.naturalWidth === 0 && img.src && !img.src.startsWith('data:')).map((img) => img.src).slice(0, 8);
    const overflow = document.documentElement.scrollWidth > document.documentElement.clientWidth + 4;
    const small = [...document.querySelectorAll('.listora-btn, .listora-detail__tab, .listora-needs__pill, .listora-dashboard__nav-item, .listora-filter-chip')].map((el) => {
      const r = el.getBoundingClientRect();
      return { text: (el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 32), w: Math.round(r.width), h: Math.round(r.height) };
    }).filter((b) => b.h > 0 && b.h < 40).slice(0, 12);
    const html = document.documentElement;
    return {
      title: document.title,
      h1: document.querySelector('h1')?.innerText?.trim().slice(0, 80) || '',
      themeClass: document.body.className.split(' ').filter((c) => /theme|reign|buddyx|dark|light|bx-|rg-/.test(c)).join(' '),
      dark: html.getAttribute('data-bx-mode') || html.getAttribute('data-theme') || (html.className.match(/dark|light/) || [''])[0],
      hasPlugin: !!root,
      overflow,
      broken,
      smallBtns: small,
      bodyStart: document.body.innerText.replace(/\s+/g, ' ').trim().slice(0, 180),
    };
  });
}

async function shot(page, name) {
  const info = await probe(page);
  out.pages[name] = { url: page.url(), ...info };
  await page.screenshot({ path: join(shots, `${name}.png`), fullPage: false });
}

async function toggleDark(page) {
  const btn = page.locator('.bx-color-mode-toggle__btn, .rg-color-mode-toggle, [class*="color-mode"] button, button[aria-label*="dark" i], button[aria-label*="light" i]').first();
  if (await btn.count()) {
    await btn.click({ timeout: 4000 }).catch(() => {});
    await page.waitForTimeout(500);
    return true;
  }
  await page.evaluate(() => {
    document.documentElement.setAttribute('data-bx-mode', 'dark');
    document.documentElement.classList.add('rg-dark');
    document.body.classList.add('rg-dark');
  });
  await page.waitForTimeout(400);
  return 'forced-attr';
}

const browser = await chromium.launch({ headless: false, slowMo: 80, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await page.goto(URLS.home, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
await shot(page, '01-home-1440-light');

await page.goto(URLS.listings, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(900);
await shot(page, '02-listings-1440-light');
const card = page.locator('.listora-card, .listora-listing-card, article.listora-listings__card').first();
if (await card.count()) {
  await card.hover().catch(() => {});
  await page.waitForTimeout(300);
  await card.screenshot({ path: join(shots, '02b-listings-card-hover.png') }).catch(() => {});
}

await page.goto(URLS.listing, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(900);
await shot(page, '03-listing-1440-light');
const reviews = page.getByRole('tab', { name: /reviews/i }).first();
if (await reviews.count()) {
  await reviews.click().catch(() => {});
  await page.waitForTimeout(500);
  await shot(page, '03b-listing-reviews-1440');
}

await login(page, 'journey_owner');
await page.goto(URLS.add, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(900);
await shot(page, '04-add-listing-1440');

await page.goto(URLS.dash, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '05-dash-listings-1440');

for (const [name, label] of [['06-dash-needs-1440', /my needs/i], ['07-dash-credits-1440', /credits/i], ['07b-dash-reviews-1440', /^reviews/i]]) {
  const tab = page.getByRole('link', { name: label }).first();
  if (await tab.count()) {
    await tab.click().catch(() => {});
    await page.waitForTimeout(700);
  }
  await shot(page, name);
}

await page.goto(URLS.needs, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '08-needs-1440');

await page.goto(URLS.postNeed, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '09-post-need-1440');

await page.goto(URLS.buy, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '10-buy-credits-1440');

await login(page, 'journey_admin');
await page.goto(URLS.adminSettings, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '11-admin-settings-1440');
await page.goto(URLS.adminListings, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
await shot(page, '12-admin-listings-1440');
await page.goto(URLS.adminNeeds, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
await shot(page, '13-admin-needs-1440');

await login(page, 'journey_owner');
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(URLS.listings, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '14-listings-390');
await page.goto(URLS.listing, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '15-listing-390');
await page.goto(URLS.dash, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '16-dash-390');
await page.goto(URLS.needs, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
await shot(page, '17-needs-390');

await page.setViewportSize({ width: 1440, height: 900 });
await page.goto(URLS.listings, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
out.darkToggle = await toggleDark(page);
await shot(page, '18-listings-1440-dark');
await page.goto(URLS.listing, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '19-listing-1440-dark');
await page.goto(URLS.dash, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
await shot(page, '20-dash-1440-dark');
await page.goto(URLS.needs, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
await shot(page, '21-needs-1440-dark');

writeFileSync(join(dir, 'report-model-look.json'), JSON.stringify(out, null, 2));
console.log(JSON.stringify({ started: out.started, darkToggle: out.darkToggle, pages: Object.fromEntries(Object.entries(out.pages).map(([k, v]) => [k, { overflow: v.overflow, broken: v.broken.length, small: v.smallBtns.length, dark: v.dark, h1: v.h1 }])) }, null, 2));
await page.waitForTimeout(200);
await browser.close();
