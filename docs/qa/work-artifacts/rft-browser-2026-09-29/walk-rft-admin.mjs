import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-batch');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'rft-batch-config.json'), 'utf8'));
const report = { started: new Date().toISOString(), cards: {} };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}
async function shot(page, name) {
  await page.screenshot({ path: join(shots, name), fullPage: false });
}

const browser = await chromium.launch({ headless: false, slowMo: 120, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await login(page, 'journey_admin');

await page.goto(`${cfg.base}/wp-admin/admin.php?page=listora-listing-types&edit=restaurant`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
const criteria = await page.evaluate(() => {
  const add = document.querySelector('[data-listora-criteria-add]');
  return {
    list: !!document.getElementById('listora-type-criteria'),
    addLabel: add ? add.textContent.trim() : '',
    rows: document.querySelectorAll('[data-listora-criteria-row]').length,
    heading: /REVIEW CRITERIA/i.test(document.body.innerText),
  };
});
if (criteria.addLabel) {
  await page.click('[data-listora-criteria-add]').catch(() => {});
  await page.waitForTimeout(250);
  criteria.rowsAfterAdd = await page.locator('[data-listora-criteria-row]').count();
}
await shot(page, '01-review-criteria.png');
report.cards['10351012222'] = criteria;

await page.goto(`${cfg.base}/wp-admin/post.php?post=15&action=edit`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(900);
await page.locator('.wb-listora-services-metabox').first().scrollIntoViewIfNeeded().catch(() => {});
const services = await page.evaluate(() => {
  const box = document.querySelector('.wb-listora-services-metabox');
  if (!box) return { missing: true };
  const add = [...box.querySelectorAll('button, a')].find((b) => /add another service/i.test(b.textContent));
  return {
    addBtn: add ? add.textContent.trim() : '',
    categorySelects: box.querySelectorAll('select[name*="[categories]"]').length,
    photoCells: box.querySelectorAll('[class*="photo"], button[data-listora-svc-photo]').length,
    headers: [...box.querySelectorAll('th')].map((th) => th.textContent.trim()),
  };
});
if (services.addBtn) {
  await page.getByRole('button', { name: /add another service/i }).click().catch(() => {});
  await page.waitForTimeout(250);
  services.rowsAfterAdd = await page.evaluate(() =>
    document.querySelectorAll('.wb-listora-services-metabox input[name*="[new]["][name*="[title]"]').length
  );
}
await shot(page, '02-services-metabox.png');
report.cards['10350359093'] = services;
report.cards['10351076301'] = { categorySelects: services.categorySelects, headers: services.headers };
report.cards['10350405749'] = { adminPhotoCells: services.photoCells };

await page.goto(`${cfg.base}/wp-admin/admin.php?page=listora-settings&tab=credits&subtab=payments`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
report.cards['10351375357'] = await page.evaluate(() => {
  const wrap = document.querySelector('[data-listora-subtab="payments"]') || document.querySelector('[data-listora-subtab]');
  const first = wrap ? wrap.querySelector('.listora-settings-pane, .listora-settings-block') : null;
  const prev = wrap && wrap.previousElementSibling;
  return {
    wrap: !!wrap,
    firstMarginTop: first ? getComputedStyle(first).marginTop : null,
    gap: first && prev ? Math.round(first.getBoundingClientRect().top - prev.getBoundingClientRect().bottom) : null,
  };
});
await shot(page, '03-credits-spacing.png');

await page.goto(`${cfg.base}/wp-admin/admin.php?page=listora-coupons&coupon_action=add`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
report.cards['10350743383'] = await page.evaluate(() => {
  const card = document.querySelector('.listora-card--padded') || document.querySelector('.listora-card');
  if (!card) return { missing: true };
  const label = card.querySelector('label, th');
  return {
    paddedClass: card.classList.contains('listora-card--padded'),
    padding: getComputedStyle(card).padding,
    labelInset: label ? Math.round(label.getBoundingClientRect().left - card.getBoundingClientRect().left) : null,
  };
});
await shot(page, '04-coupon-padded.png');

await page.goto(`${cfg.base}/wp-admin/admin.php?page=listora-webhooks&view=add`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.cards['10350920183'] = await page.evaluate(() => {
  const card = document.querySelector('.listora-card--padded') || document.querySelector('.listora-card');
  if (!card) return { missing: true, body: document.body.innerText.slice(0, 240) };
  const label = card.querySelector('label, th');
  return {
    paddedClass: card.classList.contains('listora-card--padded'),
    padding: getComputedStyle(card).padding,
    labelInset: label ? Math.round(label.getBoundingClientRect().left - card.getBoundingClientRect().left) : null,
  };
});
await shot(page, '05-webhook-padded.png');

await page.goto(`${cfg.base}/wp-admin/admin.php?page=listora-transactions`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.cards['10350758450'] = await page.evaluate(() => {
  const list = document.querySelector('.listora-tabs__list, .listora-hub-tabs');
  if (!list) return { missing: true };
  return {
    overflowY: getComputedStyle(list).overflowY,
    offsetH: list.offsetHeight,
    clientH: list.clientHeight,
    scrollH: list.scrollHeight,
    hasVScroll: list.scrollHeight > list.clientHeight + 1,
  };
});
await shot(page, '06-transactions-tabs.png');

await page.goto(`${cfg.base}/wp-admin/admin.php?page=listora-settings`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
report.cards['10351203714'] = await page.evaluate(() => ({
  distanceUnit: /Distance unit/i.test(document.body.innerText),
  honestCopy: /Used for the distance shown on listing cards and search results/i.test(document.body.innerText),
  hasRadiusControl: !!document.querySelector('[name*="search_radius"], [name*="searchRadius"], #search_radius'),
}));
await shot(page, '07-distance-unit.png');

await page.goto(`${cfg.owned}`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const editLink = page.locator('a').filter({ hasText: /edit listing/i }).first();
if (await editLink.count()) {
  await editLink.click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(900);
} else {
  await page.goto(`${cfg.dashboard}?action=edit&id=7264&tab=listings`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(900);
}
report.cards['10351400338'] = await page.evaluate(() => {
  const title = document.querySelector('input[name="title"], input[name="listing_title"], #listora-listing-title, input[name="post_title"]');
  const form = document.querySelector('.listora-submission__form, .listora-submission');
  const filled = title ? title.value : '';
  const visibleInputs = [...(form ? form.querySelectorAll('input, textarea') : [])].filter((i) => i.offsetParent && i.value).length;
  return { url: location.href, titleValue: filled, filledFields: visibleInputs, blank: !filled && visibleInputs === 0 };
});
await shot(page, '08-admin-edit-other.png');

report.finished = new Date().toISOString();
writeFileSync(join(dir, 'report-rft-admin.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
