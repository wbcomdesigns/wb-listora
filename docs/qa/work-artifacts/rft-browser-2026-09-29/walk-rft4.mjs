import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const report = { cards: {} };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 200, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// ZIP / address fields (OSM — this site's map provider)
await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const biz = page.locator('button, [role="button"], .listora-submission__type, [data-type]').filter({ hasText: /business/i }).first();
if (await biz.count()) await biz.click().catch(() => {});
for (let i = 0; i < 6; i++) {
  const postal = page.locator('input[name$="[postal_code]"]');
  if (await postal.count()) break;
  const next = page.getByRole('button', { name: /continue/i }).first();
  if (await next.count()) await next.click().catch(() => {});
  await page.waitForTimeout(400);
}
const zip = {};
const postal = page.locator('input[name$="[postal_code]"]');
zip.visible = await postal.count();
zip.cityVisible = await page.locator('input[name$="[city]"]').count();
zip.stateVisible = await page.locator('input[name$="[state]"]').count();
if (zip.visible) {
  await postal.fill('80439');
  await page.locator('input[name$="[city]"]').fill('Evergreen');
  await page.locator('input[name$="[state]"]').fill('CO');
  zip.before = { zip: '80439', city: 'Evergreen', state: 'CO' };
  await page.screenshot({ path: join(shots, '12-zip-before-pick.png') });
  const search = page.locator('input[placeholder*="address" i], input[placeholder*="search" i], .listora-map-search input, input.listora-submission__map-search').first();
  if (await search.count()) {
    await search.fill('Evergreen, Colorado');
    await page.waitForTimeout(400);
    await search.press('Enter').catch(() => {});
    await page.waitForTimeout(1500);
    const suggestion = page.locator('.listora-map-search__result, [class*="suggest"], li[role="option"]').first();
    if (await suggestion.count()) await suggestion.click().catch(() => {});
    await page.waitForTimeout(1200);
  } else {
    const map = page.locator('.leaflet-container, .listora-submission__map').first();
    if (await map.count()) await map.click({ position: { x: 220, y: 160 } }).catch(() => {});
    await page.waitForTimeout(1200);
  }
  zip.after = {
    zip: await postal.inputValue(),
    city: await page.locator('input[name$="[city]"]').inputValue(),
    state: await page.locator('input[name$="[state]"]').inputValue(),
  };
  await page.screenshot({ path: join(shots, '12b-zip-after-pick.png') });
} else {
  zip.missing = await page.evaluate(() => document.body.innerText.slice(0, 200));
  await page.screenshot({ path: join(shots, '12-zip-no-fields.png') });
}
report.cards['10345218924'] = zip;

// Credits history time + fractional leftover from signed top-up
await page.goto('http://listora.local/my-listings/#credits', { waitUntil: 'domcontentloaded' });
await page.locator('[role="tab"], .listora-dashboard__nav-item').filter({ hasText: /credit/i }).first().click().catch(() => {});
await page.waitForTimeout(500);
report.cards['10347001646'] = {
  member: await page.evaluate(() => document.body.innerText.replace(/\s+/g, ' ').slice(0, 500)),
};
await page.screenshot({ path: join(shots, '13-credits-history.png') });

// Admin transactions + plugins
await login(page, 'journey_admin');
await page.goto('http://listora.local/wp-admin/admin.php?page=listora-transactions', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.cards['10347001646'].admin = await page.evaluate(() => {
  const first = document.querySelector('table tbody tr');
  return first ? first.innerText.replace(/\s+/g, ' ').trim() : document.body.innerText.slice(0, 300);
});
await page.screenshot({ path: join(shots, '13b-transactions-time.png') });

await page.goto('http://listora.local/wp-admin/plugins.php', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
report.cards['10344694274'] = await page.evaluate(() => ({
  adManager: /ad manager/i.test(document.body.innerText),
  careerBoard: /career board/i.test(document.body.innerText),
  listora: /listora/i.test(document.body.innerText),
}));
await page.screenshot({ path: join(shots, '14-plugins-no-sibling-sdk.png') });

writeFileSync(join(dir, 'report-rft4.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await page.waitForTimeout(800);
await browser.close();
