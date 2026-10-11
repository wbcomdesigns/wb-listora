import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rft-two');
const needId = readFileSync(join(dir, 'rft2-need-id.txt'), 'utf8').trim();
const restNeedId = readFileSync(join(dir, 'rft2-need-rest-id.txt'), 'utf8').trim();
const report = { needId, restNeedId };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 70 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, 'journey_owner');
await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1000);
const tab = page.locator('#dash-tab-needs');
report.hasNav = await tab.count();
if (report.hasNav) {
  await tab.click();
  await page.waitForTimeout(500);
}
const row = page.locator(`[data-need-id="${needId}"]`);
report.hasRow = await row.count();
report.dash = await page.evaluate((id) => {
  const panel = document.querySelector('#dash-panel-needs');
  const r = document.querySelector(`[data-need-id="${id}"]`);
  const text = r ? r.innerText : (panel ? panel.innerText : document.body.innerText);
  return {
    hasPanel: !!panel,
    panelHidden: panel ? panel.hasAttribute('hidden') : null,
    hasRow: !!r,
    responsesLine: (text.match(/\d+\s+responses?/i) || [])[0] || '',
    hasBanner: /already has quotes|vendors who quoted/i.test(text),
    hasDelete: /delete/i.test(text),
    snippet: text.replace(/\s+/g, ' ').slice(0, 400),
  };
}, needId);
await page.screenshot({ path: join(shots, '03-needs-dash.png') });

// Edit first (T-43 #1)
const edit = row.locator('a').filter({ hasText: /^Edit$/i }).first();
if (await edit.count()) {
  report.editHref = await edit.getAttribute('href');
  await edit.click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(800);
  report.editPage = await page.evaluate(() => ({
    href: location.href,
    status: document.querySelector('.listora-post-need__editing')?.innerText || '',
    hasQuotesBanner: /already has quotes|vendors who quoted/i.test(document.body.innerText),
    editingYour: /You are editing your request/i.test(document.body.innerText),
    title: document.title,
  }));
  await page.screenshot({ path: join(shots, '05-need-edit.png') });
} else {
  report.editHref = null;
}

// Admin Quotes while the need still exists (T-43 #3)
await login(page, '1');
await page.goto('http://listora.local/wp-admin/admin.php?page=listora-needs&need_status=open', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1200);
report.admin = await page.evaluate((id) => {
  const text = document.body.innerText;
  const rows = [...document.querySelectorAll('tr, .listora-table__row, [class*="row"]')].filter((el) => el.innerText.includes('QA-RFT2-stale-need') || el.innerText.includes(String(id)));
  return {
    href: location.href,
    title: document.title,
    forbidden: /sorry, you are not allowed|403/i.test(text),
    found: rows.length,
    rows: rows.map((el) => el.innerText.replace(/\s+/g, ' ').slice(0, 280)),
    snippet: text.replace(/\s+/g, ' ').slice(0, 400),
  };
}, needId);
await page.screenshot({ path: join(shots, '06-needs-admin.png') });

// REST PATCH as author (T-43 #4) from dashboard so wp.apiFetch has nonce
await login(page, 'journey_owner');
await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
report.restPatch = await page.evaluate(async (id) => {
  if (!window.wp || !window.wp.apiFetch) return { noApiFetch: true };
  try {
    return await window.wp.apiFetch({
      path: '/listora/v1/needs/' + id,
      method: 'PUT',
      data: { description: 'QA patched stale count ' + Date.now() },
    });
  } catch (e) {
    return { error: e.message || String(e), status: e.data && e.data.status, code: e.code };
  }
}, needId);

// Delete the first need (T-43 #2)
await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
if (await page.locator('#dash-tab-needs').count()) {
  await page.locator('#dash-tab-needs').click();
  await page.waitForTimeout(400);
}
const del = page.locator(`[data-need-id="${needId}"] .listora-dashboard__need-delete`).first();
report.hasDeleteBtn = await del.count();
if (report.hasDeleteBtn) {
  await del.click();
  await page.waitForTimeout(500);
  const confirm = page.locator('.listora-confirm__btn--danger').first();
  report.confirmLabel = (await confirm.count()) ? (await confirm.textContent() || '').trim() : null;
  if (await confirm.count()) {
    await confirm.click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(800);
  }
  report.afterDelete = {
    href: page.url(),
    deleted: /need_deleted=1/.test(page.url()),
    closed: /need_closed=1/.test(page.url()),
    notice: await page.evaluate(() => document.querySelector('.listora-dashboard__need-notice')?.innerText || document.body.innerText.match(/deleted|closed|vendors who quoted/i)?.[0] || ''),
  };
  await page.screenshot({ path: join(shots, '04-needs-after-delete.png') });
}

// REST DELETE second need (T-43 #5)
report.restDelete = await page.evaluate(async (id) => {
  if (!window.wp || !window.wp.apiFetch) return { noApiFetch: true };
  try {
    return await window.wp.apiFetch({
      path: '/listora/v1/needs/' + id,
      method: 'DELETE',
    });
  } catch (e) {
    return { error: e.message || String(e), status: e.data && e.data.status, code: e.code };
  }
}, restNeedId);

await browser.close();
writeFileSync(join(dir, 'report-needs2.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
