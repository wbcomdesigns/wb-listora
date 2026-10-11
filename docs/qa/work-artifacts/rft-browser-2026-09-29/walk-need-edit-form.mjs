import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-rft-two');
const needId = readFileSync(join(dir, 'rft2-need-edit-id.txt'), 'utf8').trim();
const report = { needId };

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`http://listora.local/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 70 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await login(page, 'journey_owner');
await page.goto('http://listora.local/my-listings/?tab=needs', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
if (await page.locator('#dash-tab-needs').count()) {
  await page.locator('#dash-tab-needs').click();
  await page.waitForTimeout(400);
}
const edit = page.locator(`[data-need-id="${needId}"] a.listora-btn`, { hasText: /^Edit$/ }).first();
report.editCount = await edit.count();
report.editHref = report.editCount ? await edit.getAttribute('href') : null;
if (report.editCount) {
  await edit.click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(800);
}
report.fromButton = await page.evaluate(() => ({
  href: location.href,
  status: document.querySelector('.listora-post-need__editing')?.innerText || '',
  hasQuotesBanner: /already has quotes|vendors who quoted/i.test(document.body.innerText),
  editingYour: /You are editing your request/i.test(document.body.innerText),
}));
await page.screenshot({ path: join(shots, '05-need-edit.png') });

// Direct URL as well if the button did not land on the form
if (!report.fromButton.editingYour && !report.fromButton.hasQuotesBanner) {
  await page.goto(`http://listora.local/post-need/?edit_need=${needId}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  report.direct = await page.evaluate(() => ({
    href: location.href,
    status: document.querySelector('.listora-post-need__editing')?.innerText || '',
    hasQuotesBanner: /already has quotes|vendors who quoted/i.test(document.body.innerText),
    editingYour: /You are editing your request/i.test(document.body.innerText),
  }));
  await page.screenshot({ path: join(shots, '05b-need-edit-direct.png') });
}

await browser.close();
writeFileSync(join(dir, 'report-need-edit-form.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
