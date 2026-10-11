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

await login(page, 'journey_owner');
await page.goto(`${cfg.dashboard}`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.cards['10350524500'] = await page.evaluate(() => {
  const links = [...document.querySelectorAll('a')];
  const active = links.find((a) => /active/i.test(a.textContent) && a.href.includes('listings'));
  const pending = links.find((a) => /pending/i.test(a.textContent) && a.href.includes('listings'));
  return {
    activeHref: active ? active.getAttribute('href') : '',
    pendingHref: pending ? pending.getAttribute('href') : '',
    activeHasStatus: active ? /listings_status=publish/.test(active.href) : false,
    pendingHasStatus: pending ? /listings_status=pending/.test(pending.href) : false,
    sameHref: active && pending ? active.href === pending.href : null,
    activeIntercept: active ? active.getAttribute('data-wp-on--click') : null,
    pendingIntercept: pending ? pending.getAttribute('data-wp-on--click') : null,
  };
});
await shot(page, '09-stat-tiles.png');

await page.goto(`${cfg.dashboard}?tab=listings`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
report.cards['10350942570'] = await page.evaluate(() =>
  [...document.querySelectorAll('a.listora-btn--sm, button.listora-btn--sm, .listora-btn.listora-btn--sm')].slice(0, 8).map((b) => ({
    tag: b.tagName,
    text: b.textContent.replace(/\s+/g, ' ').trim().slice(0, 24),
    h: Math.round(b.getBoundingClientRect().height),
    fw: getComputedStyle(b).fontWeight,
    fs: getComputedStyle(b).fontSize,
  }))
);
await shot(page, '10-listing-action-btns.png');

const addSvc = page.locator('button, a').filter({ hasText: /add service/i }).first();
if (await addSvc.count()) {
  await addSvc.click().catch(() => {});
  await page.waitForTimeout(350);
}
report.cards['10350405749'] = await page.evaluate(() => ({
  photoLabel: /\bPhoto\b/.test(document.body.innerText),
  imageId: !!document.querySelector('input[name="service_image_id"]'),
  choose: !!document.querySelector('[data-wp-on--click="actions.serviceChoosePhoto"], .listora-dashboard__service-photo'),
}));
await shot(page, '11-frontend-service-photo.png');

await page.goto(`${cfg.dashboard}?tab=needs`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
report.cards['10350436293'] = await page.evaluate(() => {
  const h1s = [...document.querySelectorAll('h1')].map((h) => h.textContent.replace(/\s+/g, ' ').trim());
  const h3s = [...document.querySelectorAll('h3')].map((h) => h.textContent.replace(/\s+/g, ' ').trim());
  const btns = [...document.querySelectorAll('.listora-btn')].filter((b) => /edit|delete/i.test(b.textContent));
  return {
    h1s,
    h3s,
    duplicateH3: h3s.filter((t) => /^my needs$/i.test(t)).length,
    buttons: btns.slice(0, 6).map((b) => ({
      tag: b.tagName,
      text: b.textContent.replace(/\s+/g, ' ').trim().slice(0, 20),
      h: Math.round(b.getBoundingClientRect().height),
      fw: getComputedStyle(b).fontWeight,
      fs: getComputedStyle(b).fontSize,
    })),
  };
});
await shot(page, '12-my-needs.png');

await page.goto(`${cfg.dashboard}?tab=responses`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
report.cards['10351487141'] = await page.evaluate(() => {
  const h1s = [...document.querySelectorAll('h1')].map((h) => h.textContent.replace(/\s+/g, ' ').trim());
  const h3s = [...document.querySelectorAll('#dash-panel-responses h3, [id*="responses"] h3')].map((h) => h.textContent.replace(/\s+/g, ' ').trim());
  return { h1s, panelH3s: h3s, duplicate: [...h1s, ...h3s].filter((t) => /my responses/i.test(t)).length };
});
await shot(page, '13-my-responses.png');

await page.goto(`${cfg.dashboard}?tab=listings&action=edit&id=7264`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
const beforeTab = page.url();
await page.locator('[role="tab"], .listora-dashboard__nav-item, button').filter({ hasText: /credit/i }).first().click().catch(() => {});
await page.waitForTimeout(400);
const afterTab = page.url();
report.cards['10351013467'] = {
  before: beforeTab,
  after: afterTab,
  droppedAction: !/[?&]action=/.test(afterTab),
  droppedId: !/[?&]id=/.test(afterTab),
};
await shot(page, '14-tab-switch-url.png');

await page.goto(`${cfg.dashboard}?tab=listings`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
const more = page.locator('button, [aria-haspopup]').filter({ hasText: /more|⋯|…/ }).first();
if (await more.count()) {
  await more.click().catch(() => {});
  await page.waitForTimeout(250);
}
const deact = page.locator('button, a, [role="menuitem"]').filter({ hasText: /deactivat|reactivat/i }).first();
if (await deact.count()) {
  await deact.click().catch(() => {});
  await page.waitForTimeout(350);
}
report.cards['10350931568'] = await page.evaluate(() => {
  const primary = document.querySelector('.listora-confirm__btn--primary');
  const root = document.documentElement;
  return {
    overlay: !!document.querySelector('.listora-confirm-overlay, .listora-confirm'),
    primaryBg: primary ? getComputedStyle(primary).backgroundColor : null,
    tokenPrimary: getComputedStyle(root).getPropertyValue('--listora-primary').trim(),
    tokenAccent: getComputedStyle(root).getPropertyValue('--listora-accent').trim(),
  };
});
await shot(page, '15-confirm-primary.png');
const cancel = page.locator('.listora-confirm__btn--cancel, button').filter({ hasText: /cancel/i }).first();
if (await cancel.count()) await cancel.click().catch(() => {});

await page.goto(`${cfg.add_listing}`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const typeBtn = page.locator('button, [role="button"], .listora-submission__type').filter({ hasText: /business|restaurant/i }).first();
if (await typeBtn.count()) await typeBtn.click().catch(() => {});
for (let i = 0; i < 3; i++) {
  const next = page.getByRole('button', { name: /continue|next/i }).first();
  if (await next.count()) {
    await next.click().catch(() => {});
    await page.waitForTimeout(350);
  }
}
const titleInput = page.locator('input[name="title"], #listora-listing-title, input[name="listing_title"]').first();
if (await titleInput.count()) await titleInput.fill('QA-RFT-wizard-unsaved').catch(() => {});
const wizard = await page.evaluate(() => {
  const form = document.querySelector('.listora-submission__form');
  const steps = form ? form.closest('.listora-submission')?.querySelectorAll('.listora-submission__step') : [];
  const idx = steps && steps.length ? [...steps].findIndex((s) => !s.hidden) : -1;
  return { hasForm: !!form, step: idx };
});
let beforeUnloadFired = false;
page.once('dialog', async (d) => {
  beforeUnloadFired = d.type() === 'beforeunload' || /leave|reload|unsaved/i.test(d.message());
  await d.dismiss().catch(() => {});
});
await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
await page.waitForTimeout(500);
report.cards['10350859556'] = {
  wizard,
  beforeUnloadFired,
  afterReload: await page.evaluate(() => {
    const title = document.querySelector('input[name="title"], #listora-listing-title');
    return { title: title ? title.value : '', step1: /step 1|choose a type|select.*type/i.test(document.body.innerText) };
  }),
};
await shot(page, '16-wizard-after-reload.png');
report.cards['10351182660'] = await page.evaluate(() => {
  const heading = [...document.querySelectorAll('h2,h3,h4,label,.listora-submission__label')].find((el) => /social links/i.test(el.textContent));
  return { heading: heading ? heading.textContent.trim() : '', present: !!heading };
});

await page.context().clearCookies();
await page.goto(cfg.directory, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
report.cards['10351358128'] = await page.evaluate(() => {
  const cards = [...document.querySelectorAll('.listora-card, .listora-listing-card, article')];
  const hits = cards.filter((c) => /listed by/i.test(c.innerText)).slice(0, 5).map((c) => c.innerText.replace(/\s+/g, ' ').trim().slice(0, 120));
  return { cardCount: cards.length, listedByCount: hits.length, samples: hits };
});
await shot(page, '17-directory-listed-by.png');

await page.setViewportSize({ width: 390, height: 844 });
await page.goto(cfg.directory, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1100);
const mapNotice = await page.evaluate(() => {
  const n = document.querySelector('.listora-map__notice');
  if (!n) return { missing: true, body: document.body.innerText.slice(0, 160) };
  const r = n.getBoundingClientRect();
  const cs = getComputedStyle(n);
  return {
    hidden: n.hasAttribute('hidden') || cs.display === 'none',
    w: Math.round(r.width),
    h: Math.round(r.height),
    text: n.innerText.replace(/\s+/g, ' ').trim(),
    clipped: n.scrollWidth > n.clientWidth + 2,
    whiteSpace: cs.whiteSpace,
    widthCss: cs.width,
  };
});
await shot(page, '18-map-notice-390.png');
if (!mapNotice.missing) {
  await page.addStyleTag({ content: '.listora-map__notice{width:auto!important;max-width:calc(100% - 1.5rem)!important;}' });
  await page.waitForTimeout(200);
  mapNotice.reverted = await page.evaluate(() => {
    const n = document.querySelector('.listora-map__notice');
    const r = n.getBoundingClientRect();
    return { w: Math.round(r.width), h: Math.round(r.height) };
  });
}
report.cards['10350135718'] = mapNotice;
await page.setViewportSize({ width: 1440, height: 900 });

if (cfg.hours_url) {
  await page.goto(cfg.hours_url, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(700);
  const hoursTab = page.locator('[role="tab"], button, a').filter({ hasText: /hours|place details|details/i }).first();
  if (await hoursTab.count()) await hoursTab.click().catch(() => {});
  await page.waitForTimeout(350);
  report.cards['10350895542'] = await page.evaluate(() => {
    const text = document.body.innerText;
    return {
      hasHours: /11:00|open|monday|tuesday|business hours/i.test(text),
      emptyPanel: /no hours|not available/i.test(text) && !/\d{1,2}:\d{2}/.test(text),
      snippet: text.replace(/\s+/g, ' ').slice(0, 220),
    };
  });
  await shot(page, '19-place-hours.png');
}

await login(page, 'journey_other');
await page.goto(cfg.golden || cfg.owned, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
const revTab = page.locator('[role="tab"], button, a').filter({ hasText: /review/i }).first();
if (await revTab.count()) await revTab.click().catch(() => {});
await page.waitForTimeout(400);
report.cards['10351338091'] = await page.evaluate(() => {
  const ta = document.querySelector('#listora-detail-review-content, textarea[name="content"]');
  if (!ta) return { missing: true, body: document.body.innerText.slice(0, 200) };
  const ph = ta.placeholder || '';
  return { placeholder: ph, leadingWs: /^\s/.test(ph) || ph.includes('\n') || ph.includes('\t') };
});
await shot(page, '20-review-placeholder.png');

const replyTarget = (cfg.review_posts || []).find((r) => r.author === 146) || (cfg.review_posts || [])[0];
if (replyTarget) {
  await login(page, replyTarget.author === 146 ? 'journey_owner' : 'journey_admin');
  await page.goto(`${cfg.dashboard}?tab=reviews`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(700);
  const replyBtn = page.locator('button, a').filter({ hasText: /^reply$/i }).first();
  const replyInfo = { url: page.url(), hasReply: await replyBtn.count() };
  if (await replyBtn.count()) {
    await replyBtn.click().catch(() => {});
    await page.waitForTimeout(250);
    const boxEl = page.locator('textarea').last();
    if (await boxEl.count()) await boxEl.fill('QA-RFT reply check');
    const send = page.locator('button').filter({ hasText: /submit|send|reply/i }).last();
    if (await send.count()) {
      await send.click().catch(() => {});
      await page.waitForTimeout(2200);
    }
    replyInfo.after = await page.evaluate(() => ({ stuck: /submitting/i.test(document.body.innerText), url: location.href }));
  }
  report.cards['10351346512'] = replyInfo;
  await shot(page, '21-owner-reply.png');
} else {
  report.cards['10351346512'] = { skipped: 'no approved reviews' };
}

await page.goto(cfg.directory, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
report.cards['10351203714'] = await page.evaluate(() => ({
  radiusInput: !!document.querySelector('[name*="radius"], #listora-radius, [data-listora-radius]'),
  nearMe: /near me/i.test(document.body.innerText),
}));

report.finished = new Date().toISOString();
writeFileSync(join(dir, 'report-rft-front.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
