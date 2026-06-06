import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8001';
const routes = ['/ai', '/question-banks/editor', '/assignments', '/assignments/grading', '/gradebook', '/surveys'];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  const logs = [];
  page.on('console', msg => logs.push({ type: msg.type(), text: msg.text() }));
  page.on('response', response => {
    const url = response.url();
    const type = response.headers()['content-type'] || '';
    if (url.includes('/build/') || (type.includes('text/html') && !url.includes('?v='))) {
      logs.push({ type: 'response', status: response.status(), contentType: type, url });
    }
  });

  await page.goto(`${base}/login?v=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(async () => {
    if ('serviceWorker' in navigator) {
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.map(registration => registration.unregister()));
    }
    if ('caches' in window) {
      const keys = await caches.keys();
      await Promise.all(keys.map(key => caches.delete(key)));
    }
  }).catch(() => {});
  await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
  await page.fill('input[type="password"]', 'admin123456');
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.click('button[type="submit"]')]);
  await page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(800);

  for (const route of routes) {
    logs.push({ type: 'route', route });
    await page.goto(`${base}${route}?v=${Date.now()}`, { waitUntil: 'domcontentloaded' }).catch(error => logs.push({ type: 'nav', route, error: error.message }));
    await page.waitForTimeout(1800);
  }

  console.log(JSON.stringify(logs, null, 2));
  await browser.close();
})();
