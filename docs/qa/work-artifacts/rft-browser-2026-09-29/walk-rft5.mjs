import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rft5');
mkdirSync(shots, { recursive: true });
const snap = JSON.parse(readFileSync(join(dir, 'rft5-snap.json'), 'utf8'));
const report = { snap, startedAt: new Date().toISOString() };

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

// --- 10033 + 10125 + 10191: classified wizard ---
await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
const typeCard = page.locator('.listora-submission__type-card').filter({ hasText: /classified/i }).first();
await typeCard.click();
await page.getByRole('button', { name: /continue/i }).click();
await page.waitForTimeout(800);
await page.waitForFunction(() => {
  const sel = document.querySelector('select[name="category"]');
  return sel && sel.options.length > 1;
}, { timeout: 15000 }).catch(() => {});

report.categories = await page.evaluate(() => {
  const sel = document.querySelector('select[name="category"]');
  if (!sel) return { missing: true };
  return {
    count: sel.options.length,
    options: [...sel.options].map((o) => ({
      value: o.value,
      text: o.textContent,
      raw: JSON.stringify(o.textContent),
      leadingNbsp: (o.textContent.match(/\u00a0/g) || []).length,
    })),
  };
});
await page.screenshot({ path: join(shots, '01-category-dropdown.png') });

async function planVisibility(label) {
  return page.evaluate((planId) => {
    const cards = [...document.querySelectorAll('.listora-plan-card')];
    const scoped = cards.find((c) => {
      const r = c.querySelector('input[name="plan_id"]');
      return r && String(r.value) === String(planId);
    });
    const shown = cards.filter((c) => !c.hidden).map((c) => {
      const r = c.querySelector('input[name="plan_id"]');
      return {
        id: r && r.value,
        title: (c.innerText || '').split('\n')[0],
        cats: c.getAttribute('data-listing-categories'),
        hidden: c.hidden,
      };
    });
    return {
      step: document.querySelector('.listora-submission__step.is-active, .listora-submission__step:not([hidden])')?.dataset?.step || null,
      cardCount: cards.length,
      scopedAttr: scoped ? scoped.getAttribute('data-listing-categories') : null,
      scopedHidden: scoped ? !!scoped.hidden : null,
      shown,
    };
  }, snap.plan_id);
}

// Select Cruiser (child)
if (snap.cruiser_id) {
  await page.selectOption('select[name="category"]', String(snap.cruiser_id));
  await page.waitForTimeout(300);
}
report.plansAfterCruiser = await planVisibility('cruiser');
await page.screenshot({ path: join(shots, '02-after-cruiser.png') });

// Select Vehicles (unrelated)
await page.selectOption('select[name="category"]', '102');
await page.waitForTimeout(300);
report.plansAfterVehicles = await planVisibility('vehicles');
await page.screenshot({ path: join(shots, '03-after-vehicles.png') });

// Select parent Motorcycle
if (snap.parent_id) {
  await page.selectOption('select[name="category"]', String(snap.parent_id));
  await page.waitForTimeout(300);
}
report.plansAfterParent = await planVisibility('parent');

// Walk to Plan step so the owner sees the cards
await page.locator('input[name="title"], #listora-title, [name="listing_title"]').first().fill('QA-RFT5-plan-walk').catch(() => {});
await page.locator('textarea[name="description"], [name="listing_content"]').first().fill('QA RFT5 plan walk description text for required field.').catch(() => {});
for (let i = 0; i < 6; i++) {
  const step = await page.evaluate(() => {
    const el = document.querySelector('.listora-submission__step:not([hidden])[data-step]');
    return el ? el.getAttribute('data-step') : document.body.innerText.slice(0, 80);
  });
  report['step_' + i] = step;
  if (String(step).includes('plan') || /choose a plan|pricing/i.test(String(step))) break;
  const cont = page.getByRole('button', { name: /continue/i });
  if (await cont.count()) {
    await cont.click();
    await page.waitForTimeout(500);
  } else {
    break;
  }
}
report.plansVisibleStep = await planVisibility('visible-step');
await page.screenshot({ path: join(shots, '04-plan-step.png'), fullPage: true });

// --- 10350859556 autosave ---
await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
await page.locator('.listora-submission__type-card').filter({ hasText: /business/i }).first().click();
await page.getByRole('button', { name: /continue/i }).click();
await page.waitForTimeout(600);
const title = 'QA-RFT5-AUTOSAVE-' + Date.now();
report.autosaveTitle = title;
await page.locator('input[name="title"], [name="listing_title"]').first().fill(title);
report.autosaveBeforeWait = await page.evaluate(() => ({
  indicator: document.querySelector('.listora-submission__autosave')?.textContent || '',
  listingId: document.querySelector('[name="listing_id"]')?.value || '',
  local: localStorage.getItem('listora_draft_listing_id') || localStorage.getItem('wb_listora_draft_id') || Object.keys(localStorage).filter((k) => /draft|listora/i.test(k)),
}));
await page.waitForTimeout(32000);
report.autosaveAfterWait = await page.evaluate(() => ({
  indicator: document.querySelector('.listora-submission__autosave')?.textContent || '',
  listingId: document.querySelector('[name="listing_id"]')?.value || '',
  local: { ...localStorage },
}));
await page.screenshot({ path: join(shots, '05-autosave-indicator.png') });
await page.reload({ waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
report.autosaveAfterReload = await page.evaluate(() => ({
  href: location.href,
  resumeText: document.body.innerText.match(/unfinished listing draft|Resume draft|Start new/i)?.[0] || '',
  titleValue: document.querySelector('input[name="title"], [name="listing_title"]')?.value || '',
  notice: !!document.querySelector('.listora-submission__draft-resume'),
}));
await page.screenshot({ path: join(shots, '06-resume-notice.png') });
if (report.autosaveAfterReload.notice) {
  await page.getByRole('button', { name: /resume draft/i }).click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(800);
  report.autosaveResumed = await page.evaluate(() => ({
    href: location.href,
    titleValue: document.querySelector('input[name="title"], [name="listing_title"]')?.value || '',
  }));
  await page.screenshot({ path: join(shots, '07-resumed.png') });
}

// --- 10350113286 stale gates ---
if (snap.need_id) {
  await login(page, 'journey_owner');
  await page.goto(`http://listora.local/post-need/?edit_need=${snap.need_id}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  report.needEdit = await page.evaluate(() => ({
    href: location.href,
    banner: /already has quotes|vendors who quoted/i.test(document.body.innerText),
    snippet: document.body.innerText.replace(/\s+/g, ' ').slice(0, 400),
  }));
  await page.screenshot({ path: join(shots, '08-need-edit.png') });

  await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  report.needDash = await page.evaluate((id) => {
    const row = [...document.querySelectorAll('.listora-dashboard__need-row, .listora-card, tr, li')].find((el) => el.innerText.includes('QA-RFT5-stale-need'));
    return {
      found: !!row,
      text: row ? row.innerText.slice(0, 300) : document.body.innerText.replace(/\s+/g, ' ').slice(0, 300),
      hasDelete: !!document.querySelector('button, a, [data-wp-on--click]') && /delete/i.test(document.body.innerText),
    };
  }, snap.need_id);
  await page.screenshot({ path: join(shots, '09-need-dash.png') });

  await login(page, 'journey_admin');
  await page.goto('http://listora.local/wp-admin/admin.php?page=listora-needs', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  const published = page.locator('a').filter({ hasText: /published|open|all/i }).first();
  if (await published.count()) {
    await page.goto('http://listora.local/wp-admin/admin.php?page=listora-needs&need_status=open', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
  }
  report.needAdmin = await page.evaluate(() => {
    const row = [...document.querySelectorAll('tr')].find((el) => el.innerText.includes('QA-RFT5-stale-need'));
    return {
      found: !!row,
      text: row ? row.innerText.replace(/\s+/g, ' ').slice(0, 240) : 'ROW_MISSING ' + document.body.innerText.replace(/\s+/g, ' ').slice(0, 200),
    };
  });
  await page.screenshot({ path: join(shots, '10-need-admin.png') });
}

await browser.close();
report.finishedAt = new Date().toISOString();
writeFileSync(join(dir, 'report-rft5.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({
  cats: report.categories && report.categories.options && report.categories.options.map((o) => ({ t: o.text.trim(), nbsp: o.leadingNbsp, v: o.value })),
  cruiserHidden: report.plansAfterCruiser && report.plansAfterCruiser.scopedHidden,
  vehiclesHidden: report.plansAfterVehicles && report.plansAfterVehicles.scopedHidden,
  parentHidden: report.plansAfterParent && report.plansAfterParent.scopedHidden,
  scopedAttr: report.plansAfterCruiser && report.plansAfterCruiser.scopedAttr,
  planStep: report.plansVisibleStep,
  autosave: { before: report.autosaveBeforeWait, after: report.autosaveAfterWait, reload: report.autosaveAfterReload, resumed: report.autosaveResumed },
  need: { edit: report.needEdit, dash: report.needDash, admin: report.needAdmin },
}, null, 2));
