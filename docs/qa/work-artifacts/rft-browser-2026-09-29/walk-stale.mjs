import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'walk-stale-config.json'), 'utf8'));
const out = { started: new Date().toISOString() };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

async function openDashNeeds(page) {
  await page.goto(cfg.dash_needs, { waitUntil: 'domcontentloaded', timeout: 45000 });
  const tab = page.getByRole('tab', { name: /needs/i }).first();
  if (await tab.count()) await tab.click().catch(() => {});
  await page.getByRole('tab', { name: /my needs/i }).first().click().catch(() => {});
  await page.waitForTimeout(600);
}

async function confirmDanger(page) {
  const dlg = page.locator('[role="alertdialog"], .listora-confirm, dialog').first();
  await page.waitForTimeout(300);
  const danger = page.getByRole('button', { name: /delete|confirm|yes/i }).last();
  if (await danger.count()) {
    out.confirmFocus = await page.evaluate(() => document.activeElement?.innerText?.trim() || '');
    await danger.click();
  } else {
    page.once('dialog', (d) => d.accept());
  }
}

const browser = await chromium.launch({ headless: false, slowMo: 160, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, cfg.owner);

await page.goto(cfg.edit_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(600);
out.editBanner = await page.locator('.listora-post-need__editing').innerText().catch(() => '');
await page.screenshot({ path: join(shots, 'stale-01-edit-banner.png') });

await openDashNeeds(page);
const staleRow = page.locator(`.listora-dashboard__need-row[data-need-id="${cfg.del_id}"]`);
out.dashStale = await staleRow.evaluate((el) => ({
  count: el.querySelector('.listora-dashboard__need-respcount')?.innerText?.trim() || '',
  hasDelete: !!el.querySelector('.listora-dashboard__need-delete'),
  text: el.innerText.replace(/\s+/g, ' ').slice(0, 280),
})).catch(() => ({ missing: true }));
await staleRow.screenshot({ path: join(shots, 'stale-02-dash-delete-offered.png') }).catch(async () => {
  await page.screenshot({ path: join(shots, 'stale-02-dash-delete-offered.png') });
});

if (out.dashStale.hasDelete) {
  await staleRow.locator('.listora-dashboard__need-delete').click();
  await page.waitForTimeout(500);
  await page.screenshot({ path: join(shots, 'stale-03-delete-confirm.png') });
  out.confirmFocus = await page.evaluate(() => document.activeElement?.innerText?.trim() || '');
  const confirmBtn = page.locator('.listora-confirm__btn--danger, .listora-confirm__btn--primary').first();
  if (await confirmBtn.count()) {
    await confirmBtn.click({ timeout: 5000 });
  }
  await page.waitForURL(/need_closed|need_deleted|closed|tab=needs|#needs/, { timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(800);
  out.afterDelete = {
    url: page.url(),
    notice: await page.locator('.listora-dashboard__need-notice').innerText().catch(() => ''),
    body: (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 500),
  };
  await page.screenshot({ path: join(shots, 'stale-04-after-delete.png') });
}

await login(page, cfg.admin);
await page.goto(cfg.admin_url, { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
const adminRow = page.locator('tr, .listora-table__row, article').filter({ hasText: 'QA-RFT-stale-edit-20260929' }).first();
out.admin = {
  found: (await adminRow.count()) > 0,
  text: (await adminRow.innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 400),
};
if (out.admin.found) {
  await adminRow.scrollIntoViewIfNeeded();
  await adminRow.screenshot({ path: join(shots, 'stale-05-admin-quotes.png') }).catch(async () => {
    await page.screenshot({ path: join(shots, 'stale-05-admin-quotes.png') });
  });
} else {
  await page.screenshot({ path: join(shots, 'stale-05-admin-quotes.png'), fullPage: true });
}

writeFileSync(join(dir, 'report-stale.json'), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await page.waitForTimeout(300);
await browser.close();
