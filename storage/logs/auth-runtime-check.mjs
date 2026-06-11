import { chromium } from '@playwright/test';
const base = 'http://127.0.0.1:8001';
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
page.on('pageerror', err => errors.push(err.message));
await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
await page.fill('input[type="password"]', 'admin123456');
const [response] = await Promise.all([
  page.waitForResponse(r => r.url().endsWith('/login') && r.request().method() === 'POST'),
  page.click('button[type="submit"]'),
]);
console.log('login status', response.status(), await response.text());
await page.waitForTimeout(1000);
console.log('cookies', await page.context().cookies(base));
for (const route of ['/', '/standards/scorm', '/standards/xapi', '/standards/lti', '/standards/tools', '/mobile']) {
  errors.length = 0;
  await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);
  const title = await page.locator('h1,h2').first().textContent().catch(() => 'NO_TITLE');
  const hasAside = await page.locator('aside').count();
  const textLength = (await page.locator('body').innerText()).trim().length;
  console.log(JSON.stringify({ route, title, hasAside, textLength, errors }));
}
await browser.close();
