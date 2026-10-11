import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const shots = join(dir, 'shots-batch');
mkdirSync(shots, { recursive: true });
const cfg = JSON.parse(readFileSync(join(dir, 'rft-batch-config.json'), 'utf8'));
const report = {};

async function login(page, user) {
  await page.context().clearCookies();
  await page.goto(`${cfg.base}/?autologin=${user}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

const browser = await chromium.launch({ headless: false, slowMo: 100, args: ['--start-maximized'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await login(page, 'journey_admin');

await page.goto(`${cfg.add_listing}?action=edit&id=7264`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1000);
report.adminWizard = await page.evaluate(() => {
  const title = document.querySelector('input[name="title"], #listora-listing-title, input[name="listing_title"]');
  const form = document.querySelector('.listora-submission__form, .listora-submission');
  const filled = [...(form ? form.querySelectorAll('input, textarea, select') : [])].filter((i) => i.offsetParent && i.value).map((i) => ({ name: i.name, v: String(i.value).slice(0, 40) }));
  return {
    url: location.href,
    titleValue: title ? title.value : '',
    filled: filled.slice(0, 12),
    body: document.body.innerText.replace(/\s+/g, ' ').slice(0, 300),
  };
});
await page.screenshot({ path: join(shots, '08b-admin-frontend-wizard.png') });

await page.goto(`${cfg.base}/wp-admin/admin.php?page=listora-settings&tab=credits&subtab=payments`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(800);
report.credits = await page.evaluate(() => {
  const blocks = [...document.querySelectorAll('.listora-settings-block, .listora-settings-pane')];
  return blocks.slice(0, 8).map((b, i) => {
    const r = b.getBoundingClientRect();
    const prev = blocks[i - 1];
    const gap = prev ? Math.round(r.top - prev.getBoundingClientRect().bottom) : null;
    return {
      title: (b.querySelector('.listora-settings-block__title, h2, h3') || b).textContent.replace(/\s+/g, ' ').trim().slice(0, 60),
      top: Math.round(r.top),
      marginTop: getComputedStyle(b).marginTop,
      gapFromPrev: gap,
    };
  });
});
await page.screenshot({ path: join(shots, '03b-credits-full.png'), fullPage: true });

writeFileSync(join(dir, 'report-rft-follow.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
