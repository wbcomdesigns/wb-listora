import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const theme = process.env.LOOK_THEME || 'theme';
const out = { started: new Date().toISOString(), theme };

function contrast(fg, bg) {
  const parse = (c) => {
    const m = String(c || '').match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?/);
    if (!m) return null;
    const a = m[4] === undefined ? 1 : Number(m[4]);
    const rgb = [m[1], m[2], m[3]].map((v) => {
      v = Number(v) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    if (a < 1) return { ratio: null, note: `alpha ${a}`, fg, bg };
    const L = (r) => 0.2126 * r[0] + 0.7152 * r[1] + 0.0722 * r[2];
    const L1 = L(rgb);
    const parseBg = String(bg || '').match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (!parseBg) return null;
    const brgb = [parseBg[1], parseBg[2], parseBg[3]].map((v) => {
      v = Number(v) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    const L2 = L(brgb);
    return { ratio: Number(((Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05)).toFixed(2)), fg, bg };
  };
  return parse(fg, bg);
}

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

async function styles(page, selector) {
  return page.evaluate((sel) => {
    return [...document.querySelectorAll(sel)].slice(0, 12).map((el) => {
      const cs = getComputedStyle(el);
      return {
        text: (el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 40),
        tag: el.tagName,
        className: el.className.toString().slice(0, 80),
        bg: cs.backgroundColor,
        color: cs.color,
        deco: cs.textDecorationLine,
        font: cs.fontSize,
        h: Math.round(el.getBoundingClientRect().height),
      };
    });
  }, selector);
}

const browser = await chromium.launch({ headless: false, slowMo: 120, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await page.goto('http://listora.local/listings/', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
out.listings = {
  theme: await page.evaluate(() => document.body.className),
  chips: await styles(page, '.listora-filter-chip, .listora-directory__type, .listora-chip, [class*="type-chip"], .listora-listings__type-chip'),
  filters: await styles(page, 'button, a').then(() => page.evaluate(() => {
    const el = [...document.querySelectorAll('button, a, .listora-btn')].find((n) => /filter/i.test(n.innerText));
    if (!el) return null;
    const cs = getComputedStyle(el);
    return { text: el.innerText.trim(), bg: cs.backgroundColor, color: cs.color, deco: cs.textDecorationLine, h: Math.round(el.getBoundingClientRect().height) };
  })),
  titles: await page.evaluate(() => [...document.querySelectorAll('.listora-card__title a, .listora-listing-card__title a, h2 a, h3 a')].slice(0, 5).map((el) => ({
    text: el.innerText.trim().slice(0, 40),
    deco: getComputedStyle(el).textDecorationLine,
    color: getComputedStyle(el).color,
  }))),
};
await page.screenshot({ path: join(shots, `look-${theme}-listings.png`) });

await page.goto('http://listora.local/listing/sakura-house/', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
out.detail = {
  tabs: await styles(page, '.listora-detail__tab, [role="tab"]'),
  actions: await styles(page, '.listora-detail__actions .listora-btn'),
};
await page.screenshot({ path: join(shots, `look-${theme}-sakura.png`) });

await page.goto('http://listora.local/buy-credits-2/', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
out.buy = await page.evaluate(() => {
  const el = [...document.querySelectorAll('button, a, .listora-btn')].find((n) => /buy now/i.test(n.innerText));
  if (!el) return { missing: true, body: document.body.innerText.replace(/\s+/g, ' ').slice(0, 300) };
  const cs = getComputedStyle(el);
  return { text: el.innerText.trim(), bg: cs.backgroundColor, color: cs.color, deco: cs.textDecorationLine };
});
await page.screenshot({ path: join(shots, `look-${theme}-buy.png`) });

await page.goto('http://listora.local/needs/', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(700);
out.needs = {
  urgency: await styles(page, '.listora-needs__filter button, .listora-needs [class*="urgenc"] button, .listora-chip, button'),
  titles: await page.evaluate(() => [...document.querySelectorAll('.listora-needs__title a')].slice(0, 3).map((el) => ({
    text: el.innerText.trim().slice(0, 40),
    deco: getComputedStyle(el).textDecorationLine,
  }))),
};
await page.screenshot({ path: join(shots, `look-${theme}-needs.png`) });

await page.goto('http://listora.local/browse-categories/', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(500);
out.cats = { status: page.url(), title: await page.title(), h1: await page.locator('h1').first().innerText().catch(() => '') };
await page.screenshot({ path: join(shots, `look-${theme}-cats.png`) });

await login(page, 'journey_owner');
await page.goto('http://listora.local/my-listings/', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
out.dash = {
  nav: await styles(page, '.listora-dashboard__nav-item, [role="tab"]'),
  badges: await styles(page, '.listora-dashboard__nav-count, .listora-dashboard__badge, .listora-count'),
  add: await page.evaluate(() => {
    const el = [...document.querySelectorAll('a, button')].find((n) => /add listing/i.test(n.innerText));
    if (!el) return null;
    const cs = getComputedStyle(el);
    return { text: el.innerText.trim(), bg: cs.backgroundColor, color: cs.color, deco: cs.textDecorationLine };
  }),
  header: await page.evaluate(() => [...document.querySelectorAll('.listora-dashboard a, .listora-dashboard button')].filter((n) => /post a need|browse needs|buy/i.test(n.innerText)).slice(0, 6).map((el) => {
    const cs = getComputedStyle(el);
    return { text: el.innerText.trim().slice(0, 40), bg: cs.backgroundColor, color: cs.color, deco: cs.textDecorationLine };
  })),
};
await page.screenshot({ path: join(shots, `look-${theme}-dash.png`) });

await page.setViewportSize({ width: 390, height: 844 });
await page.waitForTimeout(400);
out.mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
await page.screenshot({ path: join(shots, `look-${theme}-dash-390.png`) });

writeFileSync(join(dir, `report-look-${theme}.json`), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await page.waitForTimeout(250);
await browser.close();
