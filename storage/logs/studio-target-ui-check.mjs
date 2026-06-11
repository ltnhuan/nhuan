import { chromium } from '@playwright/test';
const base = 'http://127.0.0.1:8001';
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });
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
  await page.goto(`${base}/courses/studio?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1800);
  await page.getByText('Thêm hoạt động').first().click().catch(() => {});
  await page.waitForTimeout(500);
  const state = await page.evaluate(() => ({
    h1: document.querySelector('h1')?.innerText || '',
    css: document.querySelector('link[rel="stylesheet"]')?.href || '',
    textLength: document.body.innerText.length,
    drawer: document.body.innerText.includes('Bài học văn bản') && document.body.innerText.includes('SCORM / xAPI'),
    leftAside: !!document.querySelector('aside'),
    cards: document.querySelectorAll('article, section, .shadow-sm').length,
    buttons: document.querySelectorAll('button').length,
  }));
  console.log(JSON.stringify({ errors, state }, null, 2));
  await browser.close();
})();
