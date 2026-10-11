import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rel-block');
mkdirSync(shots, { recursive: true });
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 50 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, '1');
await page.goto('http://listora.local/wp-admin/post.php?post=7281&action=edit', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2500);
report.adminIds = await page.evaluate(() => ({
  post_ID: document.querySelector('#post_ID')?.value || null,
  listed: document.querySelector('#post_ID') ? true : false,
  bodyClass: document.body.className,
}));
await page.screenshot({ path: join(shots, '09-admin-ids.png') });

await login(page, 'varundubey');
await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1000);
const svcBtn = page.locator('.listora-dashboard__services-toggle[data-wp-context*="7281"]').first();
report.svcBtn = await svcBtn.count();
if (report.svcBtn) {
  await svcBtn.click();
  await page.waitForTimeout(700);
}
const modal = page.locator('.listora-dashboard__services-modal, [class*="services-overlay"], [class*="services-panel"]').first();
report.modal = await page.evaluate(() => {
  const overlay = document.querySelector('[class*="services-"]');
  const titles = [...document.querySelectorAll('[class*="service"]')].map((el) => el.innerText).join(' | ').slice(0, 400);
  return {
    hasQARow: /QA Row One/.test(document.body.innerText),
    snippet: document.body.innerText.replace(/\s+/g, ' ').slice(0, 500),
  };
});
await page.screenshot({ path: join(shots, '10-services-modal.png') });
const edit = page.locator('[data-wp-on--click="actions.editService"]').filter({ visible: true }).first();
report.editVisible = await edit.count();
if (report.editVisible) {
  await edit.click();
  await page.waitForTimeout(500);
}
report.form = await page.evaluate(() => {
  const form = [...document.querySelectorAll('.listora-dashboard__service-form')].find((el) => !el.hidden);
  const sel = form?.querySelector('select[name="service_category"]');
  return {
    formOpen: !!form,
    editingId: form?.dataset.editingServiceId || null,
    catValue: sel?.value || null,
    catOptions: sel ? [...sel.options].map((o) => ({ v: o.value, t: o.textContent.trim() })) : [],
  };
});
await page.screenshot({ path: join(shots, '11-service-form.png') });
const save = page.locator('button').filter({ hasText: /^Save Service$/ }).filter({ visible: true }).first();
if (await save.count()) {
  await save.click();
  await page.waitForTimeout(1500);
}
report.saved = true;
await page.screenshot({ path: join(shots, '12-after-save.png') });

await page.goto('http://listora.local/my-listings/?tab=profile', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
report.profile = await page.evaluate(() => {
  const a = [...document.querySelectorAll('a')].find((el) => /change photo/i.test(el.textContent));
  return { href: a?.href || null, hint: document.body.innerText.match(/gravatar|buddypress|profile page/i)?.[0] || null };
});
await page.screenshot({ path: join(shots, '13-profile.png') });

await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
report.listingsLayout = await page.evaluate(() => ({
  rows: document.querySelectorAll('.listora-dashboard__listing-row').length,
  toggle: !!document.querySelector('[class*="view-toggle"], [data-listora-view]'),
}));
await page.goto('http://listora.local/my-listings/?tab=favorites', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
report.favLayout = await page.evaluate(() => ({
  grid: !!document.querySelector('.listora-dashboard__favorites-grid'),
  toggle: !!document.querySelector('[class*="view-toggle"], [data-listora-view]'),
}));
await page.screenshot({ path: join(shots, '14-favorites.png') });

await browser.close();
writeFileSync(join(dir, 'report-rel-2.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
