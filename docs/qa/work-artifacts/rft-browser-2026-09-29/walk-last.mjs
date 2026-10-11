import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
const cfg = JSON.parse(readFileSync(join(dir, 'walk-config.json'), 'utf8'));

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

const out = {};
const browser = await chromium.launch({ headless: false, slowMo: 180 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, cfg.admin);
await page.goto(cfg.audit, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
out.tabs = await page.evaluate(() => [...document.querySelectorAll('.listora-hub-tabs__tab')].map((a) => a.textContent.trim()));
const wh = page.locator('.listora-hub-tabs__tab', { hasText: /webhook/i });
if (await wh.count()) await wh.click();
else await page.goto(cfg.webhooks, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
out.webhooks = await page.evaluate(() => ({
  url: location.href,
  denied: /not allowed/i.test(document.body.innerText),
  title: document.querySelector('h1')?.textContent?.trim() || '',
  text: document.body.innerText.replace(/\s+/g, ' ').slice(0, 500),
}));
await page.screenshot({ path: join(shots, '08d-webhooks-enabled.png') });

await login(page, cfg.other);
await page.goto(cfg.review_listing, { waitUntil: 'domcontentloaded' });
await page.getByRole('tab', { name: /review/i }).click().catch(() => {});
await page.getByRole('button', { name: /write a review/i }).click().catch(() => {});
await page.waitForTimeout(500);
out.captcha = await page.evaluate(() => ({
  tokens: [...document.querySelectorAll('[name="listora_captcha_token"]')].map((n) => n.id),
  providers: [...document.querySelectorAll('[name="listora_captcha_provider"]')].map((n) => n.value),
  recaptcha: !!document.querySelector('script[src*="recaptcha/api.js"]'),
}));
await page.screenshot({ path: join(shots, '05d-review-captcha-on.png'), fullPage: true });

writeFileSync(join(dir, 'report-last.json'), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await page.waitForTimeout(600);
await browser.close();
