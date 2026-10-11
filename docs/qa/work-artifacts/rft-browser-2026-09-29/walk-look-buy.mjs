import { chromium } from 'playwright';
import { writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const dir = dirname(fileURLToPath(import.meta.url));
const theme = process.env.LOOK_THEME || 'theme';
const browser = await chromium.launch({ headless: false, slowMo: 120 });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto('http://listora.local/?autologin=journey_owner', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
await page.goto('http://listora.local/buy-credits-2/', { waitUntil: 'domcontentloaded', timeout: 45000 });
await page.waitForTimeout(800);
const out = await page.evaluate(() => {
  const el = [...document.querySelectorAll('button, a, .listora-btn')].find((n) => /buy now/i.test(n.innerText));
  if (!el) return { missing: true, body: document.body.innerText.replace(/\s+/g, ' ').slice(0, 400) };
  const cs = getComputedStyle(el);
  return { text: el.innerText.trim(), bg: cs.backgroundColor, color: cs.color, deco: cs.textDecorationLine, h: Math.round(el.getBoundingClientRect().height) };
});
await page.screenshot({ path: join(dir, 'shots', `look-${theme}-buy-owner.png`) });
writeFileSync(join(dir, `report-look-${theme}-buy.json`), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await browser.close();
