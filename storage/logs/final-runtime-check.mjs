import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8001';
const routes = ['/', '/courses', '/videos', '/analytics', '/standards', '/mobile', '/career/public', '/settings/users'];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(`console:${msg.text()}`); });
  page.on('pageerror', err => errors.push(`pageerror:${err.message}`));

  await page.goto(`${base}/login?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
  await page.fill('input[type="password"]', 'admin123456');
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForTimeout(1000);

  const results = [];
  for (const route of routes) {
    const url = `${base}${route}?v=${Date.now()}`;
    let navError = '';
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
    } catch (error) {
      navError = error.message;
    }
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForTimeout(500);
    const result = await page.evaluate(() => {
      const aside = document.querySelector('aside');
      const title = document.querySelector('h1')?.innerText?.trim() || '';
      const bodyText = document.body.innerText.trim();
      const cssLinks = [...document.querySelectorAll('link[rel="stylesheet"]')].map(l => l.href);
      const buttons = document.querySelectorAll('button').length;
      const sidebarBg = aside ? getComputedStyle(aside).backgroundColor : '';
      return {
        title,
        bodyLength: bodyText.length,
        aside: !!aside,
        buttons,
        cssLinks,
        sidebarBg,
        blank: bodyText.length < 80,
      };
    });
    results.push({ route, navError, ...result });
  }
  console.log(JSON.stringify({ errors, results }, null, 2));
  await browser.close();
})();
