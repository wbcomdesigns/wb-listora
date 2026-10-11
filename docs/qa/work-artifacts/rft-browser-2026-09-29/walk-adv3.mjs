import { chromium } from 'playwright';
import { writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-adv');
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 130, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, 'journey_other');
await page.goto('http://listora.local/listing/the-golden-fork/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(600);
await page.locator('[role="tab"]').filter({ hasText: /review/i }).first().click();
await page.waitForTimeout(400);
await page.getByRole('button', { name: /write a review/i }).click().catch(() => {});
await page.waitForTimeout(600);
report.criteria = await page.evaluate(() => {
  const text = document.body.innerText;
  const crit = [...document.querySelectorAll('[class*="criteria"] label, .listora-review-form label, [data-criterion]')].map((el) => el.textContent.replace(/\s+/g, ' ').trim());
  return {
    qa: /QA Bounce Criterion/i.test(text),
    foodLabel: /(?:^|\n)\s*Food\s*$/m.test(text) || crit.some((t) => /^food$/i.test(t)),
    crit,
    hasForm: !!document.querySelector('#listora-detail-review-content, .listora-review-form, textarea[name="content"]'),
  };
});
await page.screenshot({ path: join(shots, '17-write-review.png'), fullPage: true });

await login(page, 'journey_owner');
await page.goto('http://listora.local/add-listing/', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(400);
await page.locator('.listora-submission__type-card').filter({ hasText: /business/i }).first().click();
await page.getByRole('button', { name: /continue/i }).click();
await page.waitForTimeout(400);
await page.locator('input[name="title"]').fill('QA-ADV-social-check');
await page.getByRole('button', { name: /continue/i }).click();
await page.waitForTimeout(500);
report.details = await page.evaluate(() => ({
  social: /social links/i.test(document.body.innerText),
  facebook: /\bFacebook\b/.test(document.body.innerText),
  heading: document.body.innerText.replace(/\s+/g, ' ').slice(0, 350),
}));
await page.screenshot({ path: join(shots, '18-details-social.png') });

await page.goto('http://listora.local/my-listings/?tab=listings&listings_status=listora_deactivated', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(700);
const reac = page.locator('button').filter({ hasText: /^reactivate$/i });
report.reactivateCount = await reac.count();
if (await reac.count()) {
  await reac.first().click();
  await page.waitForTimeout(400);
  report.reactivate = await page.evaluate(() => {
    const btn = document.querySelector('.listora-confirm__btn--primary');
    return {
      label: btn ? btn.textContent.trim() : '',
      bg: btn ? getComputedStyle(btn).backgroundColor : null,
      token: getComputedStyle(document.documentElement).getPropertyValue('--listora-primary').trim(),
    };
  });
  await page.screenshot({ path: join(shots, '19-reactivate-filtered.png') });
  await page.locator('.listora-confirm__btn--cancel').click().catch(() => {});
}

writeFileSync(join(dir, 'report-adv3.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
