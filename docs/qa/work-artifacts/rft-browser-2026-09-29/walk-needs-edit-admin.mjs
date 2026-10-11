import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rft-two');
const needId = readFileSync(join(dir, 'rft2-need-edit-id.txt'), 'utf8').trim();
const report = { needId };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 70 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, 'journey_owner');
await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
if (await page.locator('#dash-tab-needs').count()) {
  await page.locator('#dash-tab-needs').click();
  await page.waitForTimeout(400);
}
const row = page.locator(`[data-need-id="${needId}"]`);
report.hasRow = await row.count();
const edit = page.locator(`[data-need-id="${needId}"] a`, { hasText: 'Edit' }).first();
report.editCount = await edit.count();
if (report.editCount) {
  report.editHref = await edit.getAttribute('href');
  await Promise.all([
    page.waitForURL(/edit_need|post-need/, { timeout: 15000 }).catch(() => {}),
    edit.click(),
  ]);
  await page.waitForTimeout(800);
}
report.editPage = await page.evaluate(() => ({
  href: location.href,
  status: document.querySelector('.listora-post-need__editing')?.innerText || '',
  hasQuotesBanner: /already has quotes|vendors who quoted/i.test(document.body.innerText),
  editingYour: /You are editing your request/i.test(document.body.innerText),
}));
await page.screenshot({ path: join(shots, '05-need-edit.png') });

await login(page, '1');
await page.goto('http://listora.local/wp-admin/admin.php?page=listora-needs&need_status=open', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1000);
const openTab = page.getByRole('link', { name: /Open/i }).first();
if (await openTab.count()) {
  await openTab.click();
  await page.waitForTimeout(800);
}
const search = page.locator('input[type="search"], input[name="s"], input[placeholder*="earch"]').first();
if (await search.count()) {
  await search.fill('QA-RFT2-stale-need-edit');
  const go = page.locator('button, input[type=submit]').filter({ hasText: /search/i }).first();
  if (await go.count()) await go.click();
  else await search.press('Enter');
  await page.waitForTimeout(1000);
}
report.admin = await page.evaluate((id) => {
  const rows = [...document.querySelectorAll('tr, .listora-table__row, [class*="__row"]')].filter((el) =>
    /QA-RFT2-stale-need-edit/.test(el.innerText) || el.innerText.includes(String(id))
  );
  const headers = [...document.querySelectorAll('th, [role="columnheader"]')].map((el) => el.innerText.trim());
  return {
    href: location.href,
    headers,
    found: rows.length,
    rows: rows.map((el) => el.innerText.replace(/\s+/g, ' ').slice(0, 320)),
    quotesHeader: headers.findIndex((h) => /quotes/i.test(h)),
  };
}, needId);
await page.screenshot({ path: join(shots, '06-needs-admin-open.png'), fullPage: true });

await browser.close();
writeFileSync(join(dir, 'report-needs-edit-admin.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
