import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots');
const cfg = JSON.parse(readFileSync(join(dir, 'walk-config.json'), 'utf8'));
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

async function main() {
  const browser = await chromium.launch({ headless: false, slowMo: 200 });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  await login(page, cfg.admin);

  // Webhooks via Tools hub tab on Audit Log
  await page.goto(cfg.audit, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  const tab = page.locator('a,button,[role="tab"]').filter({ hasText: /^Webhooks$/i }).first();
  report.webhookTab = await tab.count();
  if (await tab.count()) {
    await tab.click();
    await page.waitForTimeout(800);
  } else {
    await page.goto(cfg.webhooks, { waitUntil: 'domcontentloaded' });
  }
  report.webhooks = await page.evaluate(() => ({
    url: location.href,
    title: document.querySelector('h1')?.textContent?.trim() || '',
    denied: /not allowed/i.test(document.body.innerText),
    text: document.body.innerText.replace(/\s+/g, ' ').slice(0, 400),
  }));
  await page.screenshot({ path: join(shots, '08c-webhooks-tab.png') });

  // Refund: open row … then Remove credits, JS-click submit
  await page.goto(cfg.tx, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  await page.locator('button, a').filter({ hasText: '…' }).first().click({ force: true }).catch(() => {});
  await page.waitForTimeout(200);
  report.refundAmount = await page.locator('[data-listora-refund-open]').first().getAttribute('data-amount').catch(() => null);
  await page.locator('[data-listora-refund-open]').first().evaluate((el) => el.click());
  await page.waitForTimeout(500);
  report.modal = await page.evaluate(() => {
    const modal = document.querySelector('#listora-refund-modal');
    const amount = document.querySelector('[data-listora-refund-amount]');
    if (modal) modal.removeAttribute('hidden');
    if (modal) modal.style.display = 'block';
    return {
      title: document.querySelector('#listora-refund-modal-title')?.textContent?.trim() || '',
      value: amount?.value || '',
      hidden: modal?.hasAttribute('hidden') || false,
    };
  });
  await page.screenshot({ path: join(shots, '09f-refund-modal-forced.png') });
  await page.evaluate(() => document.querySelector('[data-listora-refund-submit]')?.click());
  await page.waitForTimeout(1800);
  report.refundAfter = await page.evaluate(() => document.body.innerText.includes('Credits removed') || document.body.innerText.includes('removed'));
  await page.screenshot({ path: join(shots, '09g-refund-after.png') });

  // Wizard
  await page.goto(cfg.wizard_type, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(700);
  report.wizard = await page.evaluate(() => {
    const icons = [...document.querySelectorAll('[data-lucide]')].map((el) => el.getAttribute('data-lucide'));
    return {
      url: location.href,
      title: document.querySelector('h1')?.textContent?.trim() || '',
      icons: icons.slice(0, 20),
      unique: [...new Set(icons)],
      uniqueCount: new Set(icons).size,
      allMapPin: icons.length > 0 && icons.every((i) => i === 'map-pin'),
      checked: [...document.querySelectorAll('input[type="checkbox"]:checked')].map((n) => n.value),
      typeCount: document.querySelectorAll('.listora-wizard__type, .listora-wizard__type-card, [class*="wizard__type"]').length,
    };
  });
  await page.screenshot({ path: join(shots, '10e-wizard-types.png') });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(200);
  await page.screenshot({ path: join(shots, '10f-wizard-types-390.png') });
  await page.setViewportSize({ width: 1440, height: 900 });

  await page.goto(cfg.wizard_loc, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  report.location = await page.evaluate(() => {
    const lat = document.querySelector('input[name="latitude"]');
    const lng = document.querySelector('input[name="longitude"]');
    return {
      latMax: lat?.getAttribute('max'),
      latMin: lat?.getAttribute('min'),
      lngMax: lng?.getAttribute('max'),
      lngMin: lng?.getAttribute('min'),
      pack20: /20 listings per pack/i.test(document.body.innerText),
    };
  });
  await page.screenshot({ path: join(shots, '11c-wizard-location.png') });

  // Review captcha fields as other
  await login(page, cfg.other);
  await page.goto(cfg.review_listing, { waitUntil: 'domcontentloaded' });
  await page.getByRole('tab', { name: /review/i }).click().catch(() => {});
  await page.getByRole('button', { name: /write a review/i }).click().catch(() => {});
  await page.waitForTimeout(400);
  report.captcha = await page.evaluate(() => ({
    tokens: [...document.querySelectorAll('[name="listora_captcha_token"]')].map((n) => n.outerHTML),
    providers: [...document.querySelectorAll('[name="listora_captcha_provider"]')].map((n) => n.value),
    recaptchaScript: !!document.querySelector('script[src*="recaptcha/api.js"]'),
  }));
  await page.screenshot({ path: join(shots, '05c-review-form-fields.png'), fullPage: true });

  // Preview banner: click type then jump to last step if possible
  await login(page, cfg.owner);
  await page.goto(cfg.add, { waitUntil: 'domcontentloaded' });
  await page.locator('.listora-submission__type, [data-type], button, a').filter({ hasText: /business/i }).first().click().catch(() => {});
  await page.getByRole('button', { name: /continue/i }).click().catch(() => {});
  await page.waitForTimeout(400);
  report.frac = await page.evaluate(() => {
    const banner = document.querySelector('[data-listora-credit-banner]');
    return banner
      ? { balance: banner.getAttribute('data-balance'), cost: banner.getAttribute('data-default-cost'), text: banner.innerText.replace(/\s+/g, ' ').trim() }
      : { inHtml: document.body.innerHTML.includes('data-listora-credit-banner'), balanceAttr: (document.body.innerHTML.match(/data-balance="([^"]+)"/) || [])[1] || null };
  });
  await page.screenshot({ path: join(shots, '03d-after-type.png') });

  writeFileSync(join(dir, 'report-finish.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await page.waitForTimeout(800);
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
