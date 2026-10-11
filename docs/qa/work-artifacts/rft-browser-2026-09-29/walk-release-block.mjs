import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rel-block');
mkdirSync(shots, { recursive: true });
const ids = JSON.parse(readFileSync(join(dir, 'rel-ids.json'), 'utf8'));
const report = { ids, title: `QA-REL-SVC-${Date.now()}` };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 60 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// 1) Admin add service via metabox + Update
await login(page, '1');
await page.goto(`http://listora.local/wp-admin/post.php?post=${ids.listing}&action=edit`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2000);
// dismiss welcome/guide
await page.keyboard.press('Escape').catch(() => {});
const titleInput = page.locator('#wb-listora-services-new-rows input[name*="[title]"]').first();
report.adminHasNewRow = await titleInput.count();
if (report.adminHasNewRow) {
  await titleInput.scrollIntoViewIfNeeded();
  await titleInput.fill(report.title);
}
const catSelect = page.locator('#wb-listora-services-new-rows select[name*="[categories]"], #wb-listora-services-new-rows select[multiple]').first();
report.adminCat = await page.evaluate(() => {
  const sel = document.querySelector('#wb-listora-services-new-rows select');
  if (!sel) return { missing: true };
  return {
    multiple: sel.multiple,
    size: sel.size,
    tag: sel.tagName,
    options: sel.options.length,
    html: sel.outerHTML.slice(0, 180),
  };
});
await page.screenshot({ path: join(shots, '01-admin-services.png'), fullPage: true });
const update = page.getByRole('button', { name: /^Update$/i }).first();
if (await update.count()) {
  await update.click();
  await page.waitForTimeout(2500);
}
report.adminAfter = {
  href: page.url(),
  notice: await page.locator('#message, .components-snackbar, .notice-success').first().innerText().catch(() => ''),
  titleStillInNew: await titleInput.inputValue().catch(() => null),
};
await page.screenshot({ path: join(shots, '02-admin-after-update.png') });

// 2) Frontend edit service on 7281 as owner — save without changing cats
await login(page, 'varundubey');
await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1000);
const servicesBtn = page.locator('a, button').filter({ hasText: /^Services$/i }).first();
report.hasServicesBtn = await servicesBtn.count();
if (report.hasServicesBtn) {
  await servicesBtn.click();
  await page.waitForTimeout(800);
}
const editSvc = page.locator('[data-wp-on--click="actions.editService"]').first();
report.hasEditSvc = await editSvc.count();
if (report.hasEditSvc) {
  await editSvc.click();
  await page.waitForTimeout(600);
}
report.editForm = await page.evaluate(() => {
  const sel = document.querySelector('select[name="service_category"]');
  const form = document.querySelector('[data-editing-service-id], form.listora-dashboard__service-form, [name="service_title"]');
  return {
    catValue: sel ? sel.value : null,
    catMultiple: sel ? sel.multiple : null,
    options: sel ? [...sel.options].map((o) => o.textContent.trim()) : [],
    editingId: document.querySelector('[data-editing-service-id]')?.dataset.editingServiceId || null,
    title: document.querySelector('[name="service_title"]')?.value || '',
  };
});
await page.screenshot({ path: join(shots, '03-frontend-edit-service.png') });
const saveSvc = page.locator('button[type="submit"], button').filter({ hasText: /save service|update service|save/i }).first();
if (await saveSvc.count()) {
  await saveSvc.click();
  await page.waitForTimeout(1500);
}
report.afterFrontendSave = { href: page.url() };
await page.screenshot({ path: join(shots, '04-frontend-after-save.png') });

// 3) Profile Change photo
await page.goto('http://listora.local/my-listings/?tab=profile', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
report.profile = await page.evaluate(() => {
  const a = [...document.querySelectorAll('a')].find((el) => /change photo/i.test(el.textContent));
  return { href: a ? a.href : null, text: a ? a.textContent.trim() : null };
});
await page.screenshot({ path: join(shots, '05-profile.png') });

// 4) Listings vs Favorites layout
await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
report.listings = await page.evaluate(() => ({
  list: !!document.querySelector('.listora-dashboard__listing-list, .listora-dashboard__listing-row'),
  grid: !!document.querySelector('.listora-dashboard__favorites-grid'),
  toggle: !!document.querySelector('[data-listora-view], .listora-view-toggle'),
}));
await page.screenshot({ path: join(shots, '06-listings.png') });
await page.goto('http://listora.local/my-listings/?tab=favorites', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
report.favorites = await page.evaluate(() => ({
  list: !!document.querySelector('.listora-dashboard__listing-row'),
  grid: !!document.querySelector('.listora-dashboard__favorites-grid, .listora-grid'),
  toggle: !!document.querySelector('[data-listora-view], .listora-view-toggle'),
}));
await page.screenshot({ path: join(shots, '07-favorites.png') });

// 5) Admin locations metabox
await login(page, '1');
await page.goto('http://listora.local/wp-admin/post.php?post=37&action=edit', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1500);
report.locations = await page.evaluate(() => {
  const box = document.querySelector('#listora_listing_locationdiv, #listora_listing_location, [id*="listing_location"]');
  const text = box ? box.innerText : '';
  return {
    hasBox: !!box,
    hasSearch: box ? !!box.querySelector('input[type="search"], input[type="text"]') : false,
    snippet: text.replace(/\s+/g, ' ').slice(0, 280),
  };
});
await page.screenshot({ path: join(shots, '08-locations-metabox.png') });

await browser.close();
writeFileSync(join(dir, 'report-rel-block.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
