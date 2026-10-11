import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'walk-config.json'), 'utf8'));
const report = { started: new Date().toISOString(), cards: {}, notes: [] };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

async function shot(page, name) {
  const path = join(shots, `${name}.png`);
  await page.screenshot({ path, fullPage: false });
  return path;
}

async function openDashTab(page, label) {
  const tab = page.getByRole('tab', { name: new RegExp(label, 'i') }).first();
  if (await tab.count()) {
    await tab.click().catch(() => {});
  } else {
    await page.locator('.listora-dashboard__nav-item, [role="tab"]').filter({ hasText: new RegExp(label, 'i') }).first().click().catch(() => {});
  }
  await page.waitForTimeout(400);
}

async function main() {
  const browser = await chromium.launch({ headless: false, slowMo: 220, args: ['--start-maximized'] });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.on('pageerror', (err) => report.notes.push(`js:${err.message}`));

  // Needs: accept + confirm OK, then measure Edit/Close
  if (process.env.SKIP_NEEDS === '1') {
    report.cards['10347570210'] = { skipped: 'already captured 04c/04d' };
  } else {
  await login(page, cfg.owner);
  await page.goto(cfg.dash_needs, { waitUntil: 'domcontentloaded' });
  await openDashTab(page, 'My Needs');
  const row = page.locator(`.listora-dashboard__need-row[data-need-id="${cfg.need_id}"]`);
  const needs = { need: cfg.need_id };
  if (await row.count()) {
    await row.locator('.listora-dashboard__need-toggle').click().catch(() => {});
    await page.waitForTimeout(300);
    needs.before = await row.evaluate((el) => ({
      actionsHidden: el.querySelector('.listora-dashboard__need-actions')?.hidden ?? null,
      actionsText: (el.querySelector('.listora-dashboard__need-actions')?.innerText || '').replace(/\s+/g, ' ').trim(),
    }));
    const accept = row.locator('button[data-action="accept"]');
    if (await accept.count()) {
      await accept.click({ force: true });
      const ok = page.locator('.listora-confirm__btn--primary, .listora-confirm__btn--danger').filter({ hasText: /ok|accept/i }).first();
      await page.waitForSelector('.listora-confirm__actions', { timeout: 4000 });
      await shot(page, '04c-needs-accept-confirm');
      if (await ok.count()) await ok.click();
      else await page.locator('.listora-confirm__actions button').last().click();
      await page.waitForTimeout(1500);
      needs.after = await row.evaluate((el) => ({
        actionsHidden: el.querySelector('.listora-dashboard__need-actions')?.hidden ?? null,
        actionsText: (el.querySelector('.listora-dashboard__need-actions')?.innerText || '').replace(/\s+/g, ' ').trim(),
        pill: el.querySelector('.listora-dashboard__need-meta .listora-dashboard__status')?.textContent?.trim() || '',
      }));
      await shot(page, '04d-needs-after-confirm');
    } else {
      needs.missing = 'no Accept';
    }
  }
  report.cards['10347570210'] = needs;
  }

  // Fractional: reveal credit banner on add-listing
  if (process.env.SKIP_NEEDS === '1') await login(page, cfg.owner);
  await page.goto(cfg.add, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  const frac = await page.evaluate(() => {
    const banner = document.querySelector('[data-listora-credit-banner]');
    const preview = document.querySelector('[data-step="preview"]');
    if (preview) preview.hidden = false;
    if (banner) {
      banner.hidden = false;
      banner.scrollIntoView({ block: 'center' });
    }
    return banner
      ? { balance: banner.getAttribute('data-balance'), cost: banner.getAttribute('data-default-cost'), text: banner.innerText.replace(/\s+/g, ' ').trim() }
      : { missing: true, htmlHasBanner: document.body.innerHTML.includes('data-listora-credit-banner') };
  });
  await page.screenshot({ path: join(shots, '03b-fractional-banner.png'), fullPage: true });
  await shot(page, '03c-fractional-page');
  report.cards['10346233520'] = frac;

  // Review form + captcha
  await login(page, cfg.other);
  await page.goto(cfg.review_listing, { waitUntil: 'domcontentloaded' });
  await page.getByRole('tab', { name: /review/i }).click().catch(() => {});
  await page.getByRole('button', { name: /write a review/i }).click().catch(() => {});
  await page.waitForTimeout(500);
  const captcha = await page.evaluate(() => ({
    tokens: [...document.querySelectorAll('[name="listora_captcha_token"]')].map((n) => n.id),
    providers: [...document.querySelectorAll('[name="listora_captcha_provider"]')].map((n) => n.value),
    recaptcha: !!document.querySelector('script[src*="recaptcha/api.js"]'),
    form: !!document.querySelector('.listora-review-form, form.listora-reviews__form, [class*="review"] form'),
  }));
  await shot(page, '05b-review-form-open');
  report.cards['10336539750'] = captcha;

  // Superadmin for webhooks + refund + wizard
  await login(page, '1');

  await page.goto(cfg.webhooks, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  const hooks = await page.evaluate(() => ({
    title: document.querySelector('h1')?.textContent?.trim() || '',
    denied: /not allowed/i.test(document.body.innerText),
    body: document.body.innerText.slice(0, 300),
  }));
  await shot(page, '08b-webhooks-admin1');
  report.cards['10337184050-webhooks'] = hooks;

  await page.goto(cfg.tx, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(700);
  const refund = {};
  const more = page.locator('table .listora-row-actions, table [aria-haspopup], table button').filter({ hasText: /…|more/i }).first();
  if (await more.count()) await more.click({ force: true }).catch(() => {});
  const removeBtn = page.locator('[data-listora-refund-open]').first();
  if (await removeBtn.count()) {
    refund.amountAttr = await removeBtn.getAttribute('data-amount');
    await removeBtn.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await removeBtn.click({ force: true });
    await page.waitForTimeout(400);
    refund.modal = await page.evaluate(() => {
      const amount = document.querySelector('[data-listora-refund-amount]');
      return {
        title: document.querySelector('#listora-refund-modal-title')?.textContent?.trim() || '',
        value: amount?.value || '',
        hint: document.querySelector('[data-listora-refund-remaining-hint], .listora-modal__desc')?.textContent?.trim()?.slice(0, 180) || '',
      };
    });
    await shot(page, '09d-refund-modal');
    const submit = page.locator('[data-listora-refund-submit]');
    if (await submit.count()) {
      await submit.click({ force: true });
      await page.waitForTimeout(1800);
      refund.after = await page.evaluate(() => document.body.innerText.slice(0, 500));
      await shot(page, '09e-refund-after');
    }
  } else {
    refund.missing = true;
    await shot(page, '09d-refund-missing');
  }
  report.cards['10337028634'] = refund;

  await page.goto(cfg.wizard_type, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const wizard = await page.evaluate(() => {
    const icons = [...document.querySelectorAll('.listora-wizard__type-icon [data-lucide], .listora-wizard__type-icon i')].map(
      (el) => el.getAttribute('data-lucide') || ''
    );
    return {
      icons,
      unique: [...new Set(icons)],
      uniqueCount: new Set(icons).size,
      iconCount: icons.length,
      allMapPin: icons.length > 0 && icons.every((i) => i === 'map-pin'),
      checked: [...document.querySelectorAll('input[name="listing_types[]"]:checked')].map((n) => n.value),
    };
  });
  await shot(page, '10c-wizard-types');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(250);
  await shot(page, '10d-wizard-types-390');
  await page.setViewportSize({ width: 1440, height: 900 });

  await page.goto(cfg.wizard_loc, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  const loc = await page.evaluate(() => {
    const lat = document.querySelector('input[name="latitude"]');
    const lng = document.querySelector('input[name="longitude"]');
    return {
      latMin: lat?.min,
      latMax: lat?.max,
      lngMin: lng?.min,
      lngMax: lng?.max,
      packCopy: /20 listings per pack/i.test(document.body.innerText),
      bodySlice: document.body.innerText.replace(/\s+/g, ' ').slice(0, 400),
    };
  });
  await shot(page, '11b-wizard-location');
  report.cards['10346233857'] = { checked: wizard.checked };
  report.cards['10346233945'] = { ...wizard, location: loc };

  writeFileSync(join(dir, 'report-tail.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await page.waitForTimeout(1200);
  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
