import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rft-two');
mkdirSync(shots, { recursive: true });
const report = { startedAt: new Date().toISOString() };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, {
    waitUntil: 'domcontentloaded',
    timeout: 45000,
  });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 70, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// ─── 10351346512 reply ───
await login(page, 'subscriber2');
await page.goto('http://listora.local/my-listings/?tab=reviews', { waitUntil: 'domcontentloaded' });
await page.locator('.listora-dashboard__reply-trigger').first().waitFor({ state: 'visible', timeout: 15000 });
report.replyBefore = await page.evaluate(() => ({
  href: location.href,
  hash: location.hash,
  triggers: [...document.querySelectorAll('.listora-dashboard__reply-trigger')].map((b) => b.textContent.trim()),
}));
await page.locator('.listora-dashboard__reply-trigger').first().click({ force: true });
await page.waitForTimeout(200);
const token = 'QA-RFT2-REPLY-' + Date.now();
report.replyToken = token;
await page.locator('.listora-dashboard__reply-textarea').first().fill(token);
await page.evaluate(() => { window.__qaMarker = 'alive'; });
const nav1 = page.waitForNavigation({ timeout: 10000 }).catch(() => null);
await page.locator('.listora-dashboard__reply-form button[type="submit"]').first().click();
report.replyNav = !!(await nav1);
await page.waitForTimeout(1500);
report.replyAfter = await page.evaluate((tok) => ({
  href: location.href,
  marker: window.__qaMarker || null,
  submitting: [...document.querySelectorAll('.listora-dashboard__reply-form button[type=submit] span')].map((s) => ({
    t: s.textContent.trim(),
    hid: getComputedStyle(s).display === 'none',
  })),
  hasYourReply: /your reply/i.test(document.body.innerText),
  hasToken: document.body.innerText.includes(tok),
  editReply: [...document.querySelectorAll('.listora-dashboard__reply-trigger')].some((b) => /edit reply/i.test(b.textContent)),
}));
await page.screenshot({ path: join(shots, '01-reply-after.png') });

// Edit reply on same row
if (report.replyAfter.editReply || report.replyAfter.hasYourReply) {
  await page.locator('.listora-dashboard__reply-trigger').first().click({ force: true });
  await page.waitForTimeout(200);
  const token2 = token + '-EDIT';
  report.editToken = token2;
  await page.locator('.listora-dashboard__reply-textarea').first().fill(token2);
  await page.evaluate(() => { window.__qaMarker = 'alive'; });
  const nav2 = page.waitForNavigation({ timeout: 10000 }).catch(() => null);
  await page.locator('.listora-dashboard__reply-form button[type="submit"]').first().click();
  report.editNav = !!(await nav2);
  await page.waitForTimeout(1500);
  report.editAfter = await page.evaluate((tok) => ({
    href: location.href,
    marker: window.__qaMarker || null,
    hasToken: document.body.innerText.includes(tok),
    stuck: [...document.querySelectorAll('.listora-dashboard__reply-form button[type=submit] span')].some((s) => /submitting/i.test(s.textContent) && getComputedStyle(s).display !== 'none'),
  }));
  await page.screenshot({ path: join(shots, '02-edit-reply-after.png') });
}

// ─── 10350113286 needs ───
await login(page, 'journey_owner');
await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const created = await page.evaluate(async () => {
  const nonce = window.wpApiSettings?.nonce
    || window.wp?.apiFetch?.nonce
    || (window.listoraDashboard && window.listoraDashboard.nonce)
    || '';
  const body = {
    title: 'QA-RFT2-stale-need',
    description: 'Throwaway need to retest stale count gates. Enough text to pass min length.',
    listing_type: 'business',
    urgency: 'normal',
  };
  const r = await fetch('/wp-json/listora/v1/needs', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: JSON.stringify(body),
  });
  const text = await r.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* raw */ }
  return { status: r.status, nonce: !!nonce, json, text: text.slice(0, 400) };
});
report.needCreate = created;
const needId = created.json && (created.json.id || created.json.need_id || (created.json.data && created.json.data.id));
report.needId = needId || 0;

await browser.close();
writeFileSync(join(dir, 'report-rft-two.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({
  replyBefore: report.replyBefore,
  replyNav: report.replyNav,
  replyAfter: report.replyAfter,
  editNav: report.editNav,
  editAfter: report.editAfter,
  needCreate: report.needCreate,
  needId: report.needId,
}, null, 2));
