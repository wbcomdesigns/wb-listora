import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'walk-stale-config.json'), 'utf8'));
const out = { started: new Date().toISOString() };

const browser = await chromium.launch({ headless: false, slowMo: 120, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await page.goto(`${cfg.base}?autologin=${cfg.admin}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});

const adminUrl = `${cfg.admin_url}&need_status=published&s=QA-RFT-stale-edit`;
await page.goto(adminUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);

const row = page.locator('tr, .listora-table__row').filter({ hasText: 'QA-RFT-stale-edit-20260929' }).first();
out.admin = {
  url: page.url(),
  found: (await row.count()) > 0,
  text: (await row.innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 500),
};
if (out.admin.found) {
  await row.scrollIntoViewIfNeeded();
  await row.screenshot({ path: join(shots, 'stale-05-admin-quotes.png') });
} else {
  out.body = (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 600);
  await page.screenshot({ path: join(shots, 'stale-05-admin-quotes.png'), fullPage: true });
}

writeFileSync(join(dir, 'report-admin-stale.json'), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await page.waitForTimeout(300);
await browser.close();
