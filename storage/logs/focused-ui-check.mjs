import { chromium } from '@playwright/test';
const base = 'http://127.0.0.1:8001';
const routes = ['/ai', '/courses/studio'];
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', err => errors.push(err.message));
  await page.goto(`${base}/login?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  if (await page.locator('input[type="email"]').count()) {
    await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
    await page.fill('input[type="password"]', 'admin123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1000);
  }
  const results = [];
  for (const route of routes) {
    await page.goto(`${base}${route}?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    results.push(await page.evaluate(() => ({
      url: location.href,
      h1: document.querySelector('h1')?.innerText || '',
      textLength: document.body.innerText.length,
      css: document.querySelector('link[rel="stylesheet"]')?.href || '',
      heroDark: [...document.querySelectorAll('section,div')].some(el => getComputedStyle(el).backgroundColor === 'rgb(15, 23, 42)'),
      cards: document.querySelectorAll('.shadow-sm').length,
      aside: !!document.querySelector('aside'),
    })));
  }
  console.log(JSON.stringify({ errors, results }, null, 2));
  await browser.close();
})();
