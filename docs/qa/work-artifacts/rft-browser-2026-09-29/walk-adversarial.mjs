import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-adv');
mkdirSync(shots, { recursive: true });
const report = { started: new Date().toISOString() };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 110, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// ── 10351400338 customer path: owner-bar Edit listing as admin ──
await login(page, 'journey_admin');
await page.goto('http://listora.local/listing/qa-owned-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
const ownerEdit = page.locator('.listora-detail__owner-bar-actions a').filter({ hasText: /edit listing/i }).first();
report.editHref = await ownerEdit.getAttribute('href').catch(() => null);
if (await ownerEdit.count()) {
  await ownerEdit.click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(1200);
}
report.adminEditCustomer = await page.evaluate(() => {
  const title = document.querySelector('input[name="title"], #listora-listing-title, input[name="listing_title"]');
  const gutenberg = !!document.querySelector('.block-editor, .editor-styles-wrapper, .wp-block-post-title');
  return {
    url: location.href,
    titleValue: title ? title.value : '',
    gutenberg,
    hasQATitle: /QA owned listing/i.test(document.body.innerText),
    blankWizard: !title && !/QA owned listing/i.test(document.body.innerText),
  };
});
await page.screenshot({ path: join(shots, '01-admin-edit-customer.png') });

// ── 10351076301 category select after term exists ──
await page.goto('http://listora.local/wp-admin/post.php?post=15&action=edit', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(900);
await page.locator('.wb-listora-services-metabox').first().scrollIntoViewIfNeeded().catch(() => {});
report.svcCat = await page.evaluate(() => {
  const box = document.querySelector('.wb-listora-services-metabox');
  const selects = box ? box.querySelectorAll('select[name*="[categories]"]') : [];
  const opts = [...selects].flatMap((s) => [...s.options].map((o) => o.textContent.trim()));
  return {
    selects: selects.length,
    options: opts.slice(0, 8),
    emptyMsg: box ? /no categories yet/i.test(box.innerText) : null,
  };
});
await page.screenshot({ path: join(shots, '02-svc-category.png') });

// ── 10351182660 social heading gone when all platforms off ──
await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
await page.locator('.listora-submission__type, button').filter({ hasText: /^business$/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
for (let i = 0; i < 8; i++) {
  const next = page.getByRole('button', { name: /continue|next/i }).first();
  if (!(await next.count())) break;
  await next.click().catch(() => {});
  await page.waitForTimeout(300);
}
report.socialEmpty = await page.evaluate(() => ({
  heading: /social links/i.test(document.body.innerText),
  stepText: document.body.innerText.replace(/\s+/g, ' ').slice(0, 240),
}));
await page.screenshot({ path: join(shots, '03-social-empty.png') });

// ── 10350859556 / wizard step ──
report.wizard = await page.evaluate(() => {
  const form = document.querySelector('.listora-submission__form');
  const steps = form ? form.closest('.listora-submission')?.querySelectorAll('.listora-submission__step') : [];
  return { step: steps && steps.length ? [...steps].findIndex((s) => !s.hidden) : -1 };
});

// ── 10351013467 stay on edit URL then switch ──
await page.goto('http://listora.local/my-listings/?tab=listings&action=edit&id=7264', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(900);
const editUrl = page.url();
await page.locator('#dash-tab-credits, a, button').filter({ hasText: /^credits$/i }).first().click().catch(() => {});
await page.waitForTimeout(500);
report.tabSwitch = { before: editUrl, after: page.url() };
await page.screenshot({ path: join(shots, '04-tab-switch.png') });

// ── 10350524500 click tiles ──
await page.goto('http://listora.local/my-listings/?tab=overview', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const active = page.locator('a').filter({ hasText: /active/i }).first();
if (await active.count()) await active.click();
await page.waitForTimeout(700);
report.activeClick = { url: page.url(), status: new URL(page.url()).searchParams.get('listings_status') };
await page.screenshot({ path: join(shots, '05-active-tile.png') });
await page.goto('http://listora.local/my-listings/?tab=overview', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
const pending = page.locator('a').filter({ hasText: /pending/i }).first();
if (await pending.count()) await pending.click();
await page.waitForTimeout(700);
report.pendingClick = { url: page.url(), status: new URL(page.url()).searchParams.get('listings_status') };
await page.screenshot({ path: join(shots, '06-pending-tile.png') });

// ── 10350436293 My Needs buttons ──
await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.needs = await page.evaluate(() => {
  const h1s = [...document.querySelectorAll('h1')].map((h) => h.textContent.replace(/\s+/g, ' ').trim());
  const h3Dup = [...document.querySelectorAll('h3')].filter((h) => /^my needs$/i.test(h.textContent.trim())).length;
  const btns = [...document.querySelectorAll('.listora-btn')].filter((b) => /edit|delete/i.test(b.textContent));
  return {
    h1s,
    h3Dup,
    buttons: btns.map((b) => ({
      tag: b.tagName,
      text: b.textContent.replace(/\s+/g, ' ').trim().slice(0, 20),
      h: Math.round(b.getBoundingClientRect().height),
      fw: getComputedStyle(b).fontWeight,
      fs: getComputedStyle(b).fontSize,
    })),
  };
});
await page.screenshot({ path: join(shots, '07-my-needs-btns.png') });

// ── 10350931568 Reactivate: deactivate via REST-less UI then measure, cancel, we'll restore in PHP ──
await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
await page.getByRole('button', { name: /more/i }).first().click().catch(() => {});
await page.waitForTimeout(250);
await page.locator('button, [role="menuitem"]').filter({ hasText: /deactivat/i }).first().click().catch(() => {});
await page.waitForTimeout(300);
const confirmDeact = page.locator('.listora-confirm__btn--danger, .listora-confirm__btn--primary').filter({ hasText: /deactivat|confirm/i }).first();
if (await confirmDeact.count()) {
  await confirmDeact.click();
  await page.waitForTimeout(1500);
}
await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const reactivateBtn = page.locator('button').filter({ hasText: /^reactivate$/i }).first();
report.hasReactivate = await reactivateBtn.count();
if (await reactivateBtn.count()) {
  await reactivateBtn.click();
  await page.waitForTimeout(400);
}
report.reactivate = await page.evaluate(() => {
  const btn = document.querySelector('.listora-confirm__btn--primary');
  const root = document.documentElement;
  return {
    overlay: !!document.querySelector('.listora-confirm-overlay'),
    label: btn ? btn.textContent.trim() : '',
    bg: btn ? getComputedStyle(btn).backgroundColor : null,
    tokenPrimary: getComputedStyle(root).getPropertyValue('--listora-primary').trim(),
  };
});
await page.screenshot({ path: join(shots, '08-reactivate.png') });
await page.locator('.listora-confirm__btn--cancel').click().catch(() => {});

// ── 10351012222 criteria on live review form ──
await login(page, 'journey_other');
await page.goto('http://listora.local/listing/the-golden-fork/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
await page.locator('[role="tab"]').filter({ hasText: /review/i }).first().click().catch(() => {});
await page.waitForTimeout(500);
report.criteriaForm = await page.evaluate(() => {
  const text = document.body.innerText;
  return {
    qaCriterion: /QA Bounce Criterion/i.test(text),
    food: /\bFood\b/.test(text),
    quality: /Quality/i.test(text),
    service: /\bService\b/.test(text),
    snippet: text.replace(/\s+/g, ' ').match(/.{0,40}(QA Bounce|Food|Quality|Service).{0,40}/)?.[0] || text.slice(0, 220),
  };
});
await page.screenshot({ path: join(shots, '09-review-criteria-front.png') });

// ── 10350895542 hours visible in sidebar ──
await page.context().clearCookies();
await page.goto('http://listora.local/listing/sakura-house/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
await page.locator('.listora-hours-table, .listora-detail__hours').first().scrollIntoViewIfNeeded().catch(() => {});
report.hoursVisible = await page.evaluate(() => {
  const table = document.querySelector('.listora-hours-table');
  if (!table) return { table: false };
  const r = table.getBoundingClientRect();
  return {
    table: true,
    inViewport: r.top < innerHeight && r.bottom > 0,
    monday: table.innerText,
  };
});
await page.screenshot({ path: join(shots, '10-hours-sidebar.png') });

// ── 10351346512 reply as Golden Fork owner (user 1) ──
await login(page, 'varundubey');
await page.goto('http://listora.local/my-listings/?tab=reviews', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
const replyBtn = page.locator('button').filter({ hasText: /^reply$/i }).first();
report.reply = { hasReply: await replyBtn.count() };
if (await replyBtn.count()) {
  await replyBtn.click();
  await page.waitForTimeout(300);
  await page.locator('textarea').last().fill('QA-ADV reply — checking stuck submitting');
  await page.locator('button[type="submit"], button').filter({ hasText: /submit|send|post reply|reply/i }).last().click().catch(() => {});
  await page.waitForTimeout(2800);
  report.reply.after = await page.evaluate(() => ({
    stuck: /submitting/i.test(document.body.innerText),
    url: location.href,
    hasThanks: /thank|replied|reply posted/i.test(document.body.innerText),
  }));
}
await page.screenshot({ path: join(shots, '11-reply.png') });

report.finished = new Date().toISOString();
writeFileSync(join(dir, 'report-adversarial.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
