import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-batch');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'rft-batch-config.json'), 'utf8'));
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 110, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, 'journey_owner');

await page.goto(`${cfg.dashboard}?tab=my-responses`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.responses = await page.evaluate(() => ({
  url: location.href,
  h1s: [...document.querySelectorAll('h1')].map((h) => h.textContent.replace(/\s+/g, ' ').trim()),
  h3s: [...document.querySelectorAll('h3')].map((h) => h.textContent.replace(/\s+/g, ' ').trim()),
  notice: document.body.innerText.includes('no "responses"') || document.body.innerText.includes('no responses section'),
  body: document.body.innerText.replace(/\s+/g, ' ').slice(0, 280),
}));
await page.screenshot({ path: join(shots, '13b-my-responses.png') });

await page.goto(`${cfg.dashboard}?tab=listings`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
await page.getByRole('button', { name: /services/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
await page.getByRole('button', { name: /add service/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
report.servicePhoto = await page.evaluate(() => ({
  photoLabel: /\bPhoto\b/.test(document.body.innerText),
  imageId: !!document.querySelector('input[name="service_image_id"]'),
  choose: !!document.querySelector('.listora-dashboard__service-photo, [data-listora-service-photo-empty]'),
  emptyText: (document.querySelector('[data-listora-service-photo-empty]') || {}).textContent || '',
}));
await page.screenshot({ path: join(shots, '11b-service-photo.png') });

await page.goto(`${cfg.dashboard}?tab=listings`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
await page.getByRole('button', { name: /more/i }).first().click().catch(() => {});
await page.waitForTimeout(300);
await page.locator('button, a, [role="menuitem"]').filter({ hasText: /deactivat/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
report.confirm = await page.evaluate(() => {
  const primary = document.querySelector('.listora-confirm__btn--primary, .listora-confirm__btn--danger');
  const root = document.documentElement;
  return {
    overlay: !!document.querySelector('.listora-confirm-overlay, .listora-confirm'),
    btnText: primary ? primary.textContent.trim() : '',
    bg: primary ? getComputedStyle(primary).backgroundColor : null,
    tokenPrimary: getComputedStyle(root).getPropertyValue('--listora-primary').trim(),
  };
});
await page.screenshot({ path: join(shots, '15b-confirm.png') });
await page.locator('.listora-confirm__btn--cancel, button').filter({ hasText: /cancel/i }).first().click().catch(() => {});

await page.goto(`${cfg.dashboard}?tab=listings&action=edit&id=7264`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
const before = page.url();
await page.locator('#dash-tab-credits, [data-wp-context*="credits"]').first().click().catch(async () => {
  await page.locator('a, button').filter({ hasText: /^credits$/i }).first().click().catch(() => {});
});
await page.waitForTimeout(400);
report.tabSwitch = { before, after: page.url() };
await page.screenshot({ path: join(shots, '14b-tab-switch.png') });

await page.goto(`${cfg.add_listing}`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
await page.locator('.listora-submission__type, button, [role="button"]').filter({ hasText: /^business$/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
for (let i = 0; i < 5; i++) {
  const next = page.getByRole('button', { name: /continue|next/i }).first();
  if (!(await next.count())) break;
  await next.click().catch(() => {});
  await page.waitForTimeout(350);
}
await page.locator('input[name="title"], #listora-listing-title').first().fill('QA-RFT-wizard-unsaved').catch(() => {});
report.wizard = await page.evaluate(() => {
  const form = document.querySelector('.listora-submission__form');
  const steps = form ? form.closest('.listora-submission')?.querySelectorAll('.listora-submission__step') : [];
  const idx = steps && steps.length ? [...steps].findIndex((s) => !s.hidden) : -1;
  return { step: idx, title: (document.querySelector('input[name="title"]') || {}).value || '' };
});
let warned = false;
page.once('dialog', async (d) => {
  warned = true;
  await d.dismiss().catch(() => {});
});
await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
await page.waitForTimeout(400);
report.wizard.after = { warned, url: page.url(), title: await page.locator('input[name="title"]').first().inputValue().catch(() => '') };
await page.screenshot({ path: join(shots, '16b-wizard.png') });

await page.context().clearCookies();
await page.goto(cfg.hours_url, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
report.hours = await page.evaluate(() => {
  const tabs = [...document.querySelectorAll('[role="tab"]')].map((t) => t.textContent.replace(/\s+/g, ' ').trim());
  const times = [...document.body.innerText.matchAll(/\d{1,2}:\d{2}/g)].map((m) => m[0]);
  return { tabs, times: times.slice(0, 12), hasPlace: tabs.some((t) => /place|hours|details/i.test(t)) };
});
for (const name of report.hours.tabs) {
  if (/overview|services|reviews/i.test(name)) continue;
  await page.locator('[role="tab"]').filter({ hasText: name }).first().click().catch(() => {});
  await page.waitForTimeout(250);
}
report.hours.afterClick = await page.evaluate(() => ({
  times: [...document.body.innerText.matchAll(/\d{1,2}:\d{2}/g)].map((m) => m[0]).slice(0, 12),
  text: document.body.innerText.replace(/\s+/g, ' ').match(/.{0,30}(Mon|Tue|Wed|11:00|Open).{0,40}/i)?.[0] || '',
}));
await page.screenshot({ path: join(shots, '19b-hours.png') });

await login(page, 'journey_admin');
await page.goto(`${cfg.dashboard}?tab=reviews`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
const replyBtn = page.locator('button').filter({ hasText: /^reply$/i }).first();
report.reply = { hasReply: await replyBtn.count(), url: page.url() };
if (await replyBtn.count()) {
  await replyBtn.click();
  await page.waitForTimeout(250);
  await page.locator('textarea').last().fill('QA-RFT reply');
  await page.locator('button').filter({ hasText: /submit|send|post reply/i }).last().click().catch(() => {});
  await page.waitForTimeout(2200);
  report.reply.after = await page.evaluate(() => ({ stuck: /submitting/i.test(document.body.innerText), url: location.href }));
}
await page.screenshot({ path: join(shots, '21b-reply.png') });

writeFileSync(join(dir, 'report-rft-gaps.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
