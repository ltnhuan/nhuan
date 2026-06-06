import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8001';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(`console:${msg.text()}`); });
  page.on('pageerror', err => errors.push(`pageerror:${err.message}`));

  await page.goto(`${base}/login?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
  await page.fill('input[type="password"]', 'admin123456');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1200);
  await page.goto(`${base}/?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);

  const hrefs = await page.$$eval('aside a[href^="/"]', links => [...new Set(links.map(a => a.getAttribute('href')))]);
  const results = [];
  for (const href of hrefs) {
    const cleanHref = href.split('?')[0];
    await page.goto(`${base}${cleanHref}?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(450);
    const state = await page.evaluate(() => {
      const aside = document.querySelector('aside');
      const cssLink = document.querySelector('link[rel="stylesheet"]')?.href || '';
      const h1 = document.querySelector('h1')?.innerText?.trim() || '';
      const textLength = document.body.innerText.trim().length;
      return {
        title: h1,
        textLength,
        hasAside: !!aside,
        cssLink,
        sidebarBg: aside ? getComputedStyle(aside).backgroundColor : '',
        blank: textLength < 80,
      };
    });
    results.push({ href: cleanHref, ...state });
  }

  const bad = results.filter(r => r.blank || !r.hasAside || !r.cssLink || r.sidebarBg !== 'rgb(15, 23, 42)');
  console.log(JSON.stringify({ totalLinks: hrefs.length, bad, errors, results }, null, 2));
  await browser.close();
})();
