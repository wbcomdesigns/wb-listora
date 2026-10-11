import { chromium } from 'playwright';
import { writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
const out = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 180 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);

const typeBtn = page.locator('.listora-submission__type, [data-type="business"]').filter({ hasText: /business/i }).first();
if (await typeBtn.count()) await typeBtn.click();
else await page.getByText('Business', { exact: true }).first().click().catch(() => {});
await page.getByRole('button', { name: /continue/i }).click().catch(() => {});
await page.waitForTimeout(400);

await page.locator('#listora-title').fill('QA ZIP keep 80439').catch(() => {});
await page.locator('.listora-submission__stepper [data-step="details"], [data-wp-on--click*="details"], button, a').filter({ hasText: /^details$/i }).first().click().catch(() => {});
await page.getByRole('button', { name: /continue/i }).click().catch(() => {});
await page.waitForTimeout(500);

// Details step may still be hidden — force-show map address fields if needed
await page.evaluate(() => {
  document.querySelectorAll('.listora-submission__step').forEach((el) => {
    el.hidden = false;
    el.style.display = 'block';
  });
});
await page.waitForTimeout(400);

out.postalCount = await page.locator('input[name$="[postal_code]"]').count();
out.labels = await page.evaluate(() =>
  [...document.querySelectorAll('.listora-submission__map-address-label')].map((n) => n.textContent.trim())
);

if (out.postalCount) {
  const postal = page.locator('input[name$="[postal_code]"]').first();
  const city = page.locator('input[name$="[city]"]').first();
  const state = page.locator('input[name$="[state]"]').first();
  await postal.fill('80439', { force: true });
  await city.fill('Evergreen', { force: true });
  await state.fill('CO', { force: true });
  out.before = { zip: await postal.inputValue(), city: await city.inputValue(), state: await state.inputValue() };
  await page.screenshot({ path: join(shots, '12-zip-before-pick.png') });

  const map = page.locator('.leaflet-container').first();
  if (await map.count()) {
    await map.click({ position: { x: 240, y: 180 } });
    await page.waitForTimeout(1500);
  }
  out.after = { zip: await postal.inputValue(), city: await city.inputValue(), state: await state.inputValue() };
  out.survived = out.after.zip === '80439' && out.after.city === 'Evergreen' && out.after.state === 'CO';
  await page.screenshot({ path: join(shots, '12b-zip-after-pick.png') });
}

// Transactions row for journey_owner
await login(page, 'journey_admin');
await page.goto('http://listora.local/wp-admin/admin.php?page=listora-transactions', { waitUntil: 'domcontentloaded' });
const search = page.locator('input[type="search"], input[name*="member"], input[placeholder*="member" i]').first();
if (await search.count()) {
  await search.fill('journey_owner');
  await page.getByRole('button', { name: /apply/i }).click().catch(() => {});
  await page.waitForTimeout(600);
}
out.tx = await page.evaluate(() => {
  const rows = [...document.querySelectorAll('table tbody tr')].slice(0, 3).map((r) => r.innerText.replace(/\s+/g, ' ').trim());
  return rows;
});
await page.screenshot({ path: join(shots, '13c-transactions-owner.png') });

writeFileSync(join(dir, 'report-zip.json'), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await page.waitForTimeout(600);
await browser.close();
