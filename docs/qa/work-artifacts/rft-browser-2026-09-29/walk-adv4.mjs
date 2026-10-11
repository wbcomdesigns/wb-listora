import { chromium } from 'playwright';
import { writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 100, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
await page.locator('.listora-submission__type-card').filter({ hasText: /business/i }).first().click();
await page.getByRole('button', { name: /continue/i }).click();
await page.waitForTimeout(400);
report.socialInDom = await page.evaluate(() => {
  const details = document.querySelector('.listora-submission__step[data-step="details"]');
  const all = document.querySelector('.listora-submission');
  const text = (details || all || document.body).innerText;
  const html = details ? details.innerHTML : '';
  return {
    hasDetailsStep: !!details,
    socialInDetails: details ? /social links/i.test(details.innerText + details.innerHTML) : null,
    socialAnywhere: /social links/i.test(all ? all.innerText : ''),
    facebookInput: !!document.querySelector('input[name*="social"], input[name*="facebook"]'),
    detailsSnippet: (details ? details.innerText : '').replace(/\s+/g, ' ').slice(0, 300),
  };
});
await page.screenshot({ path: join(dir, 'shots-adv/20-social-dom.png') });

await page.goto('http://listora.local/my-listings/?tab=listings', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
report.listingRow = await page.evaluate(() => document.body.innerText.replace(/\s+/g, ' ').slice(0, 400));
await page.getByRole('button', { name: /more/i }).first().click().catch(() => {});
await page.waitForTimeout(300);
report.moreItems = await page.evaluate(() =>
  [...document.querySelectorAll('.listora-dashboard__menu-item, [role="menuitem"]')].map((b) => b.textContent.replace(/\s+/g, ' ').trim())
);
const reac = page.locator('.listora-dashboard__menu-item, button').filter({ hasText: /^reactivate$/i }).first();
if (await reac.count()) {
  await reac.click();
  await page.waitForTimeout(400);
  report.reactivate = await page.evaluate(() => {
    const btn = document.querySelector('.listora-confirm__btn--primary');
    return {
      label: btn ? btn.textContent.trim() : '',
      bg: btn ? getComputedStyle(btn).backgroundColor : null,
      token: getComputedStyle(document.documentElement).getPropertyValue('--listora-primary').trim(),
    };
  });
  await page.screenshot({ path: join(dir, 'shots-adv/21-reactivate-more.png') });
  await page.locator('.listora-confirm__btn--cancel').click().catch(() => {});
} else {
  await page.screenshot({ path: join(dir, 'shots-adv/21-no-reactivate.png') });
}

writeFileSync(join(dir, 'report-adv4.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
