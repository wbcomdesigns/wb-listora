import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-adv');
mkdirSync(shots, { recursive: true });
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 120, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// Social: click type CARD then continue to Details
await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
await page.locator('.listora-submission__type-card').filter({ hasText: /business/i }).first().click();
await page.waitForTimeout(200);
await page.getByRole('button', { name: /continue/i }).first().click();
await page.waitForTimeout(500);
for (let i = 0; i < 4; i++) {
  const social = await page.evaluate(() => /social links/i.test(document.body.innerText));
  const step = await page.evaluate(() => document.body.innerText.match(/Details|Basic Info|Media/i)?.[0]);
  report[`socialStep${i}`] = { social, url: page.url() };
  if (social) break;
  const next = page.getByRole('button', { name: /continue/i }).first();
  if (await next.count()) await next.click().catch(() => {});
  await page.waitForTimeout(400);
}
report.socialFinal = await page.evaluate(() => ({
  socialLinks: /social links/i.test(document.body.innerText),
  facebook: /facebook/i.test(document.body.innerText),
  body: document.body.innerText.replace(/\s+/g, ' ').slice(0, 400),
}));
await page.screenshot({ path: join(shots, '12-social-details.png') });

// Tab switch from visible edit form
await page.goto('http://listora.local/my-listings/?tab=listings&action=edit&id=7264', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
report.editLand = { url: page.url(), title: await page.locator('input[name="title"]').first().inputValue().catch(() => '') };
await page.locator('#dash-tab-credits').click().catch(async () => {
  await page.locator('[id^="dash-tab"]').filter({ hasText: /credit/i }).first().click();
});
await page.waitForTimeout(400);
report.tabAfter = page.url();
await page.screenshot({ path: join(shots, '13-tab-from-edit.png') });

// Reactivate after PHP-deactivated listing
await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const reac = page.locator('button').filter({ hasText: /^reactivate$/i });
report.reactivateCount = await reac.count();
if (await reac.count()) {
  await reac.first().click();
  await page.waitForTimeout(400);
  report.reactivate = await page.evaluate(() => {
    const btn = document.querySelector('.listora-confirm__btn--primary');
    const root = document.documentElement;
    const token = getComputedStyle(root).getPropertyValue('--listora-primary').trim();
    const bg = btn ? getComputedStyle(btn).backgroundColor : null;
    return { label: btn ? btn.textContent.trim() : '', bg, token, overlay: !!document.querySelector('.listora-confirm-overlay') };
  });
  await page.screenshot({ path: join(shots, '14-reactivate-real.png') });
  await page.locator('.listora-confirm__btn--cancel').click().catch(() => {});
}

// Criteria form — scroll to write review
await login(page, 'journey_other');
await page.goto('http://listora.local/listing/the-golden-fork/#reviews', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
await page.locator('[role="tab"]').filter({ hasText: /review/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
await page.locator('#listora-detail-review-content, textarea[name="content"], .listora-review-form').first().scrollIntoViewIfNeeded().catch(() => {});
report.criteria = await page.evaluate(() => {
  const labels = [...document.querySelectorAll('.listora-review-form label, [class*="criteria"] label, .listora-criteria label')].map((l) => l.textContent.replace(/\s+/g, ' ').trim());
  const all = [...document.querySelectorAll('label')].map((l) => l.textContent.replace(/\s+/g, ' ').trim()).filter((t) => /qa bounce|food|service|ambiance|value|quality/i.test(t));
  return {
    qa: /QA Bounce Criterion/i.test(document.body.innerText),
    labels,
    matching: all,
  };
});
await page.screenshot({ path: join(shots, '15-criteria-form.png'), fullPage: true });

// Reply on listing as owner
await login(page, 'varundubey');
await page.goto('http://listora.local/listing/the-golden-fork/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
await page.locator('[role="tab"]').filter({ hasText: /review/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
const reply = page.locator('button').filter({ hasText: /^reply$/i }).first();
report.replyOnListing = await reply.count();
if (await reply.count()) {
  await reply.click();
  await page.waitForTimeout(250);
  await page.locator('textarea').last().fill('QA-ADV listing reply');
  await page.locator('button').filter({ hasText: /submit|send|post/i }).last().click().catch(() => {});
  await page.waitForTimeout(2500);
  report.replyAfter = await page.evaluate(() => ({ stuck: /submitting/i.test(document.body.innerText), url: location.href }));
}
await page.goto('http://listora.local/my-listings/?tab=reviews', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.replyDash = await page.locator('button').filter({ hasText: /^reply$/i }).count();
await page.screenshot({ path: join(shots, '16-reply-varun.png') });

writeFileSync(join(dir, 'report-adv2.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
