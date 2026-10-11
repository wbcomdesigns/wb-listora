import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rft5');
const snap = JSON.parse(readFileSync(join(dir, 'rft5-snap.json'), 'utf8'));
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
}

function planState(planId) {
  return {
    cards: [...document.querySelectorAll('.listora-plan-card')].map((c) => {
      const r = c.querySelector('input[name="plan_id"]');
      return {
        id: r && r.value,
        hidden: !!c.hidden,
        cats: c.getAttribute('data-listing-categories') || '',
        display: getComputedStyle(c).display,
      };
    }),
    scoped: (() => {
      const c = [...document.querySelectorAll('.listora-plan-card')].find((el) => {
        const r = el.querySelector('input[name="plan_id"]');
        return r && String(r.value) === String(planId);
      });
      return c
        ? { hidden: !!c.hidden, cats: c.getAttribute('data-listing-categories'), display: getComputedStyle(c).display }
        : null;
    })(),
  };
}

const browser = await chromium.launch({ headless: false, slowMo: 60 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.locator('.listora-submission__type-card').filter({ hasText: /classified/i }).first().click();
await page.getByRole('button', { name: /continue/i }).click();
await page.waitForFunction(() => document.querySelector('select[name="category"]')?.options.length > 1, { timeout: 15000 });

await page.selectOption('select[name="category"]', String(snap.cruiser_id));
await page.waitForTimeout(250);
report.cruiser = await page.evaluate(planState, snap.plan_id);

await page.selectOption('select[name="category"]', '102');
await page.waitForTimeout(250);
report.vehicles = await page.evaluate(planState, snap.plan_id);

await page.selectOption('select[name="category"]', String(snap.parent_id));
await page.waitForTimeout(250);
report.parent = await page.evaluate(planState, snap.plan_id);
await page.screenshot({ path: join(shots, '11-plans-after-parent.png') });

await page.selectOption('select[name="category"]', String(snap.cruiser_id));
await page.locator('input[name="title"], [name="listing_title"]').first().fill('QA-RFT5-plan-visible');
for (let i = 0; i < 8; i++) {
  const step = await page.evaluate(() => document.querySelector('.listora-submission__step:not([hidden])')?.getAttribute('data-step'));
  report['walkstep_' + i] = step;
  if (step === 'plan') break;
  const btn = page.getByRole('button', { name: /continue/i });
  if (!(await btn.count())) break;
  await btn.click();
  await page.waitForTimeout(400);
}
report.planStep = await page.evaluate(planState, snap.plan_id);
report.planStepVisibleText = await page.evaluate(() => document.body.innerText.includes('QA-RFT5-Moto-Plan'));
await page.screenshot({ path: join(shots, '12-plan-step-visible.png'), fullPage: true });

// Needs: post-need page first, then edit, dash, admin as user 1
await login(page, 'journey_owner');
await page.goto('http://listora.local/post-need/', { waitUntil: 'domcontentloaded' });
report.postNeedHome = { status: await page.evaluate(() => document.body.innerText.slice(0, 180)), href: await page.url() };
await page.goto(`http://listora.local/post-need/?edit_need=${snap.need_id}`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.needEdit = await page.evaluate(() => ({
  href: location.href,
  title: document.title,
  banner: /already has quotes|vendors who quoted/i.test(document.body.innerText),
  hasForm: !!document.querySelector('form, textarea, input[name="title"]'),
  snippet: document.body.innerText.replace(/\s+/g, ' ').slice(0, 350),
}));
await page.screenshot({ path: join(shots, '13-need-edit.png') });

await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
const needsTab = page.locator('#dash-tab-needs, [href*="tab=needs"]');
if (await needsTab.count()) await needsTab.first().click().catch(() => {});
await page.waitForTimeout(400);
report.needDash = await page.evaluate(() => ({
  panelHidden: document.querySelector('#dash-panel-needs')?.hasAttribute('hidden') ?? null,
  text: (document.querySelector('#dash-panel-needs')?.innerText || document.body.innerText).replace(/\s+/g, ' ').slice(0, 500),
}));
await page.screenshot({ path: join(shots, '14-need-dash.png') });

await login(page, '1');
await page.goto('http://listora.local/wp-admin/admin.php?page=listora-needs&need_status=open', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.needAdmin = await page.evaluate(() => {
  const row = [...document.querySelectorAll('tr, .listora-row')].find((el) => /QA-RFT5-stale-need/.test(el.innerText));
  return {
    found: !!row,
    text: row ? row.innerText.replace(/\s+/g, ' ').slice(0, 280) : document.body.innerText.replace(/\s+/g, ' ').slice(0, 280),
  };
});
await page.screenshot({ path: join(shots, '15-need-admin.png') });

await browser.close();
writeFileSync(join(dir, 'report-rft5b.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({
  cruiser: report.cruiser && report.cruiser.scoped,
  vehicles: report.vehicles && report.vehicles.scoped,
  parent: report.parent && report.parent.scoped,
  cruiserAttr: report.cruiser && report.cruiser.scoped && report.cruiser.scoped.cats,
  planStep: report.planStep && report.planStep.scoped,
  motoVisible: report.planStepVisibleText,
  steps: Object.keys(report).filter((k) => k.startsWith('walkstep_')).map((k) => report[k]),
  needEdit: report.needEdit,
  needDash: report.needDash,
  needAdmin: report.needAdmin,
}, null, 2));
