import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'walk-config.json'), 'utf8'));
const report = { started: new Date().toISOString(), cards: {}, notes: [] };

function contrast(fg, bg) {
  const parse = (c) => {
    const m = String(c).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (!m) return null;
    return [m[1], m[2], m[3]].map((v) => {
      v = Number(v) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
  };
  const a = parse(fg);
  const b = parse(bg);
  if (!a || !b) return null;
  const L = (rgb) => 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
  const L1 = L(a);
  const L2 = L(b);
  return (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
}

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
}

async function shot(page, name) {
  const path = join(shots, `${name}.png`);
  await page.screenshot({ path, fullPage: false });
  return path;
}

async function openDashTab(page, label) {
  const tab = page.locator('[role="tab"], .listora-dashboard__nav-item, a, button').filter({ hasText: new RegExp(`^\\s*${label}`, 'i') }).first();
  if (await tab.count()) {
    await tab.click().catch(() => {});
    await page.waitForTimeout(400);
  }
}

async function main() {
  const browser = await chromium.launch({
    headless: false,
    slowMo: 220,
    args: ['--start-maximized'],
  });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.on('pageerror', (err) => report.notes.push(`js:${page.url()} ${err.message}`));

  // --- 10347575691 confirm focus ---
  await login(page, cfg.owner);
  await page.goto(cfg.dash, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const more = page.locator('.listora-dashboard__menu-wrap button').filter({ hasText: /more/i }).first();
  if (await more.count()) {
    await more.click();
    await page.waitForTimeout(250);
  }
  const deact = page.locator('[data-wp-on--click="actions.deactivateListing"]').first();
  const confirmCard = {};
  if (await deact.count()) {
    await deact.click({ force: true });
    await page.waitForSelector('.listora-confirm__btn--cancel', { timeout: 5000 });
    await page.waitForTimeout(80);
    confirmCard.focus = await page.evaluate(() => {
      const el = document.activeElement;
      return {
        text: el?.textContent?.trim() || '',
        className: el?.className || '',
        isCancel: !!el?.classList?.contains('listora-confirm__btn--cancel'),
      };
    });
    await shot(page, '01-confirm-deactivate-focus');
    await page.locator('.listora-confirm__btn--cancel').click();
  } else {
    confirmCard.missing = 'no deactivate button on owner dashboard';
  }
  report.cards['10347575691'] = confirmCard;

  // --- 10340840934 dark contrast ---
  await page.goto(cfg.dash_credits, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);
  await openDashTab(page, 'Credits');
  const dark = await page.evaluate(() => {
    const bal = document.querySelector('.listora-dashboard__credits-balance, .listora-credits__balance, [class*="credit"] strong, .listora-dashboard__credit-amount');
    const btn = [...document.querySelectorAll('a,button')].find((n) => /post a need/i.test(n.textContent || ''));
    const pick = (el) => {
      if (!el) return null;
      const s = getComputedStyle(el);
      const p = getComputedStyle(el.closest('[class*="card"], .listora-dashboard, body') || document.body);
      return { color: s.color, bg: s.backgroundColor === 'rgba(0, 0, 0, 0)' ? p.backgroundColor : s.backgroundColor, text: el.textContent.trim().slice(0, 80) };
    };
    return {
      scheme: document.documentElement.getAttribute('data-theme') || document.body.className,
      token: getComputedStyle(document.documentElement).getPropertyValue('--listora-primary-text-toward').trim(),
      mix: getComputedStyle(document.documentElement).getPropertyValue('--listora-primary-text-mix').trim(),
      primaryText: getComputedStyle(document.documentElement).getPropertyValue('--listora-primary-text').trim(),
      balance: pick(bal),
      postNeed: pick(btn),
    };
  });
  if (dark.balance) dark.balance.ratio = contrast(dark.balance.color, dark.balance.bg);
  if (dark.postNeed) dark.postNeed.ratio = contrast(dark.postNeed.color, dark.postNeed.bg);
  await shot(page, '02-dark-credits-1440');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(300);
  await shot(page, '02b-dark-credits-390');
  await page.setViewportSize({ width: 1440, height: 900 });
  report.cards['10340840934'] = dark;

  // --- 10346233520 fractional preview ---
  await page.goto(cfg.add, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const frac = await page.evaluate(() => {
    const banner = document.querySelector('[data-listora-credit-banner]');
    const preview = document.querySelector('[data-step="preview"]');
    if (preview) preview.hidden = false;
    if (banner) banner.hidden = false;
    return banner
      ? {
          balance: banner.getAttribute('data-balance'),
          cost: banner.getAttribute('data-default-cost'),
          text: banner.innerText.replace(/\s+/g, ' ').trim().slice(0, 240),
        }
      : { missing: true };
  });
  await shot(page, '03-fractional-preview');
  report.cards['10346233520'] = frac;

  // --- 10347570210 needs accept ---
  await page.goto(cfg.dash_needs, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);
  await openDashTab(page, 'Needs');
  await openDashTab(page, 'My Needs');
  const row = page.locator(`.listora-dashboard__need-row[data-need-id="${cfg.need_id}"]`);
  const needs = { need: cfg.need_id, response: cfg.response_id };
  if (await row.count()) {
    const toggle = row.locator('.listora-dashboard__need-toggle');
    if (await toggle.count()) await toggle.click();
    await page.waitForTimeout(400);
    needs.before = await row.evaluate((el) => ({
      actionsHidden: el.querySelector('.listora-dashboard__need-actions')?.hidden ?? null,
      actionsText: el.querySelector('.listora-dashboard__need-actions')?.innerText || '',
    }));
    await shot(page, '04-needs-before-accept');
    const accept = row.locator('button[data-action="accept"]');
    if (await accept.count()) {
      await accept.click();
      await page.waitForTimeout(1200);
      needs.after = await row.evaluate((el) => ({
        actionsHidden: el.querySelector('.listora-dashboard__need-actions')?.hidden ?? null,
        actionsText: el.querySelector('.listora-dashboard__need-actions')?.innerText || '',
        pill: el.querySelector('.listora-dashboard__need-meta .listora-dashboard__status')?.textContent?.trim() || '',
      }));
      await shot(page, '04b-needs-after-accept');
    } else {
      needs.missing = 'no Accept button';
    }
  } else {
    needs.missing = 'need row not found';
    await shot(page, '04-needs-missing');
  }
  report.cards['10347570210'] = needs;

  // --- 10336539750 recaptcha on review form ---
  await login(page, cfg.other);
  await page.goto(cfg.review_listing, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const reviewsTab = page.locator('a,button,[role="tab"]').filter({ hasText: /review/i }).first();
  if (await reviewsTab.count()) {
    await reviewsTab.click().catch(() => {});
    await page.waitForTimeout(400);
  }
  const captcha = await page.evaluate(() => {
    const tokens = [...document.querySelectorAll('[name="listora_captcha_token"]')].map((n) => n.id);
    const providers = [...document.querySelectorAll('[name="listora_captcha_provider"]')].map((n) => n.value);
    const recaptcha = !!document.querySelector('script[src*="recaptcha/api.js"]');
    return { tokens, providers, recaptcha, forms: document.querySelectorAll('form').length };
  });
  await shot(page, '05-review-captcha');
  report.cards['10336539750'] = captcha;

  // --- admin surfaces ---
  await login(page, cfg.admin);

  await page.goto(cfg.audit, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(700);
  const audit = await page.evaluate((name) => {
    const body = document.body.innerText;
    return {
      hasMemberHash: /Member\s*#/.test(body),
      hasName: name ? body.includes(name) : false,
      name,
      filters: !!document.querySelector('select, .listora-filters, form.listora-admin-filters'),
      sample: body.slice(0, 400),
    };
  }, cfg.audit_user_name);
  await shot(page, '06-audit-log');
  report.cards['10347569613'] = audit;

  await page.goto(cfg.email, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  const email = await page.evaluate(() => ({
    title: document.querySelector('h1,h2')?.textContent?.trim() || '',
    hasFilters: !!document.querySelector('select, input[type="search"]'),
    rawKeys: /credits_refunded|listing_submitted/.test(document.body.innerText) && /_/.test(document.body.innerText),
  }));
  await shot(page, '07-email-log');

  await page.goto(cfg.webhooks, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  const hooks = await page.evaluate(() => ({
    title: document.querySelector('h1,h2')?.textContent?.trim() || '',
    activeWord: (document.body.innerText.match(/Active|Failing|Disabled/g) || []).slice(0, 8),
  }));
  await shot(page, '08-webhooks');
  report.cards['10337184050'] = { email, hooks, auditFilters: audit.filters };

  await page.goto(cfg.tx, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const refund = {};
  const removeBtn = page.locator('[data-listora-refund-open]').first();
  if (await removeBtn.count()) {
    refund.amountAttr = await removeBtn.getAttribute('data-amount');
    refund.label = await removeBtn.innerText();
    await shot(page, '09-transactions-row');
    await removeBtn.scrollIntoViewIfNeeded();
    await removeBtn.click({ force: true });
    await page.waitForSelector('#listora-refund-modal, [data-listora-refund-amount]', { timeout: 5000 });
    await page.waitForTimeout(300);
    refund.modal = await page.evaluate(() => {
      const amount = document.querySelector('[data-listora-refund-amount]');
      const title = document.querySelector('#listora-refund-modal-title, .listora-refund-modal h2');
      return {
        title: title?.textContent?.trim() || '',
        value: amount?.value || '',
        visible: !!document.querySelector('#listora-refund-modal:not([hidden])') || !!amount,
      };
    });
    await shot(page, '09b-refund-modal');
    const submit = page.locator('[data-listora-refund-submit]');
    if (await submit.count()) {
      await submit.click();
      await page.waitForTimeout(1500);
      refund.afterClick = await page.evaluate(() => document.body.innerText.includes('Credits removed') || document.body.innerText.includes('removed'));
      await shot(page, '09c-refund-after');
    }
  } else {
    refund.missing = 'no Remove credits button';
    await shot(page, '09-transactions-empty');
  }
  report.cards['10337028634'] = refund;

  // --- wizard ---
  await page.goto(cfg.wizard_type, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const wizard = await page.evaluate(() => {
    const icons = [...document.querySelectorAll('.listora-wizard__type-icon [data-lucide], .listora-wizard__type-icon i')].map(
      (el) => el.getAttribute('data-lucide') || el.className
    );
    const unique = [...new Set(icons)];
    const checked = [...document.querySelectorAll('input[name="listing_types[]"]:checked, input[name="listing_types"]:checked')].map((n) => n.value);
    return { icons, unique, uniqueCount: unique.length, iconCount: icons.length, checked };
  });
  await shot(page, '10-wizard-types');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(250);
  await shot(page, '10b-wizard-types-390');
  await page.setViewportSize({ width: 1440, height: 900 });

  await page.goto(cfg.wizard_loc, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  const loc = await page.evaluate(() => {
    const lat = document.querySelector('input[name="latitude"]');
    const lng = document.querySelector('input[name="longitude"]');
    if (lat) lat.value = '999';
    if (lng) lng.value = '999';
    return { hasLat: !!lat, hasLng: !!lng, latMax: lat?.max || '', lngMax: lng?.max || '' };
  });
  await shot(page, '11-wizard-location');
  report.cards['10346233857'] = { checked: wizard.checked };
  report.cards['10346233945'] = { ...wizard, location: loc };

  writeFileSync(join(dir, 'report.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await page.waitForTimeout(1500);
  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
