import { chromium } from '@playwright/test';
const base = 'http://127.0.0.1:8001';
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

  await page.goto(`${base}/admin/lms/system-check?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);
  const system = await page.evaluate(() => ({
    h1: document.querySelector('h1')?.innerText || '',
    text: document.body.innerText,
    css: document.querySelector('link[rel="stylesheet"]')?.href || '',
    buttons: document.querySelectorAll('button').length,
  }));

  const hrefs = await page.$$eval('aside a[href^="/"]', links => [...new Set(links.map(a => a.getAttribute('href').split('?')[0]))]);
  const bad = [];
  for (const href of hrefs) {
    await page.goto(`${base}${href}?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(350);
    const state = await page.evaluate(() => ({
      title: document.querySelector('h1')?.innerText || '',
      bodyLength: document.body.innerText.trim().length,
      css: document.querySelector('link[rel="stylesheet"]')?.href || '',
      aside: !!document.querySelector('aside'),
    }));
    if (state.bodyLength < 80 || !state.css || !state.aside) bad.push({ href, ...state });
  }

  console.log(JSON.stringify({ errors, system: { h1: system.h1, hasZeroMenuErrors: system.text.includes('menu route errors') || system.text.includes('menu_route_errors'), css: system.css, buttons: system.buttons }, totalLinks: hrefs.length, bad }, null, 2));
  await browser.close();
})();
