import { chromium } from 'playwright';
import { writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-reply');
const report = { startedAt: new Date().toISOString() };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, {
    waitUntil: 'domcontentloaded',
    timeout: 45000,
  });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
  return page.evaluate(async () => {
    try {
      const r = await fetch('/wp-json/wp/v2/users/me?context=edit', { credentials: 'same-origin' });
      const j = await r.json();
      return { id: j.id, slug: j.slug, name: j.name, roles: j.roles };
    } catch (e) {
      return { error: String(e) };
    }
  });
}

async function measureReply(page, label, url) {
  const result = { label, url };
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('#dash-panel-reviews', { timeout: 15000 });
  await page.waitForFunction(() => {
    const p = document.querySelector('#dash-panel-reviews');
    return p && !p.hasAttribute('hidden') && getComputedStyle(p).display !== 'none';
  }, { timeout: 10000 }).catch(() => {});
  await page.locator('.listora-dashboard__reply-trigger').first().waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(400);

  result.me = await page.evaluate(() => ({
    href: location.href,
    pathname: location.pathname,
    search: location.search,
    hash: location.hash,
  }));

  result.triggers = await page.evaluate(() => {
    const btns = [...document.querySelectorAll('.listora-dashboard__reply-trigger')];
    return btns.map((b) => ({
      text: (b.textContent || '').trim(),
      hidden: b.classList.contains('is-hidden') || b.hidden || getComputedStyle(b).display === 'none',
      box: b.getBoundingClientRect().toJSON(),
    }));
  });

  const trigger = page.locator('.listora-dashboard__reply-trigger').first();
  result.triggerCount = await page.locator('.listora-dashboard__reply-trigger').count();
  if (result.triggerCount === 0) {
    result.blocked = 'no reply trigger';
    await page.screenshot({ path: join(shots, `${label}-no-trigger.png`), fullPage: true });
    return result;
  }

  await trigger.click({ timeout: 8000, force: true });
  await page.waitForTimeout(250);
  const token = `QA-REPLY-${label}-${Date.now()}`;
  result.token = token;
  await page.locator('.listora-dashboard__reply-textarea').first().fill(token);

  await page.evaluate(() => {
    window.__qaMarker = 'alive';
    window.__qaUnload = false;
    window.addEventListener('beforeunload', () => { window.__qaUnload = true; });
  });

  const submittingVis = [];
  const unsub = await page.exposeFunction('qaPushSubmitting', (v) => submittingVis.push(v)).catch(() => null);
  await page.evaluate(() => {
    const btn = document.querySelector('.listora-dashboard__reply-form button[type="submit"]');
    if (!btn) return;
    const obs = new MutationObserver(() => {
      const spans = [...btn.querySelectorAll('span')];
      window.__qaSubmitting = spans.map((s) => ({
        text: s.textContent.trim(),
        hidden: s.classList.contains('is-hidden') || getComputedStyle(s).display === 'none',
      }));
    });
    obs.observe(btn, { subtree: true, attributes: true, childList: true });
  });

  await page.screenshot({ path: join(shots, `${label}-before-submit.png`) });
  const navPromise = page.waitForNavigation({ timeout: 8000 }).catch(() => null);
  await page.locator('.listora-dashboard__reply-form button[type="submit"]').first().click();
  const nav = await navPromise;
  await page.waitForTimeout(2500);

  result.after = await page.evaluate(() => ({
    href: location.href,
    marker: window.__qaMarker || null,
    hash: location.hash,
    search: location.search,
    submitting: window.__qaSubmitting || null,
    bodyHasToken: document.body.innerText.includes('QA-REPLY-'),
    triggerTexts: [...document.querySelectorAll('.listora-dashboard__reply-trigger')].map((b) => b.textContent.trim()),
    ownerReply: [...document.querySelectorAll('.listora-dashboard__owner-reply')].map((el) => el.innerText.slice(0, 120)),
  }));
  result.navigated = !!nav;
  result.markerSurvived = result.after.marker === 'alive';
  result.stuckSubmitting = !!(result.after.submitting || []).find((s) => /submitting/i.test(s.text) && !s.hidden);
  result.reloadHappened = !result.markerSurvived;
  await page.screenshot({ path: join(shots, `${label}-after-submit.png`) });
  return result;
}

const browser = await chromium.launch({ headless: false, slowMo: 80, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

report.who = await login(page, 'subscriber2');
report.pathNoHash = await measureReply(page, '01-nohash', 'http://listora.local/my-listings/?tab=reviews');

await login(page, 'subscriber2');
report.pathHash = await measureReply(page, '02-hash', 'http://listora.local/my-listings/?tab=reviews#reviews');

await login(page, 'subscriber2');
await page.goto('http://listora.local/my-listings/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
const reviewsTab = page.getByRole('tab', { name: /reviews/i }).or(page.locator('[id="dash-tab-reviews"], a[href*="reviews"]')).first();
if (await reviewsTab.count()) {
  await reviewsTab.click();
  await page.waitForTimeout(600);
}
report.pathTabClick = {
  href: await page.evaluate(() => location.href),
  triggers: await page.locator('.listora-dashboard__reply-trigger').count(),
};
await page.screenshot({ path: join(shots, '03-tab-click.png') });

await login(page, 'subscriber2');
await page.goto('http://listora.local/listing/amalfi-coast-italian/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
const listingReply = page.locator('.listora-reviews__reply-btn').first();
report.listing = {
  href: await page.evaluate(() => location.href),
  replyBtns: await page.locator('.listora-reviews__reply-btn').count(),
  title: await page.title(),
};
if (report.listing.replyBtns > 0) {
  await listingReply.click();
  await page.waitForTimeout(200);
  const token = `QA-LISTING-REPLY-${Date.now()}`;
  report.listing.token = token;
  await page.locator('.listora-reviews__reply-form textarea').first().fill(token);
  await page.evaluate(() => { window.__qaMarker = 'alive'; });
  const navPromise = page.waitForNavigation({ timeout: 8000 }).catch(() => null);
  await page.locator('.listora-reviews__reply-form button.listora-btn--primary').first().click();
  await navPromise;
  await page.waitForTimeout(1500);
  report.listing.after = await page.evaluate(() => ({
    marker: window.__qaMarker || null,
    href: location.href,
    hasToken: document.body.innerText.includes('QA-LISTING-REPLY-'),
  }));
  await page.screenshot({ path: join(shots, '04-listing-after.png') });
} else {
  await page.screenshot({ path: join(shots, '04-listing-no-reply.png') });
}

await login(page, 'journey_owner');
report.editReply = await measureReply(page, '05-edit-reply', 'http://listora.local/my-listings/?tab=reviews');

await page.setViewportSize({ width: 390, height: 844 });
await login(page, 'subscriber2');
await page.goto('http://listora.local/my-listings/?tab=reviews', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(500);
report.mobile390 = {
  triggers: await page.locator('.listora-dashboard__reply-trigger').count(),
  href: await page.evaluate(() => location.href),
};
await page.screenshot({ path: join(shots, '06-390.png') });

await browser.close();
report.finishedAt = new Date().toISOString();
writeFileSync(join(dir, 'report-reply.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({
  who: report.who,
  noHash: {
    href: report.pathNoHash.me,
    triggers: report.pathNoHash.triggerCount,
    reload: report.pathNoHash.reloadHappened,
    stuck: report.pathNoHash.stuckSubmitting,
    navigated: report.pathNoHash.navigated,
    afterHref: report.pathNoHash.after && report.pathNoHash.after.href,
  },
  hash: {
    href: report.pathHash.me,
    reload: report.pathHash.reloadHappened,
    stuck: report.pathHash.stuckSubmitting,
    navigated: report.pathHash.navigated,
  },
  tabClick: report.pathTabClick,
  listing: report.listing,
  editReply: {
    triggers: report.editReply.triggerCount,
    reload: report.editReply.reloadHappened,
    stuck: report.editReply.stuckSubmitting,
  },
  mobile390: report.mobile390,
}, null, 2));
