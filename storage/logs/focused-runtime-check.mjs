import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8001';
const routes = ['/', '/analytics', '/standards/scorm', '/standards/xapi', '/standards/lti', '/standards/tools', '/ai', '/mobile', '/career/public'];
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
await page.fill('input[type="password"]', 'admin123456');
await Promise.all([
  page.waitForResponse(r => r.url().endsWith('/login') && r.request().method() === 'POST'),
  page.click('button[type="submit"]'),
]);
await page.waitForTimeout(500);

const report = [];
for (const route of routes) {
  const errors = [];
  const onPageError = error => errors.push(`pageerror: ${error.message}`);
  const onConsole = msg => { if (msg.type() === 'error') errors.push(`console: ${msg.text()}`); };
  const onFailed = request => errors.push(`requestfailed: ${request.url()} ${request.failure()?.errorText}`);
  page.on('pageerror', onPageError);
  page.on('console', onConsole);
  page.on('requestfailed', onFailed);
  await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  const text = (await page.locator('body').innerText()).trim();
  const info = await page.evaluate(() => {
    const aside = document.querySelector('aside');
    const main = document.querySelector('main');
    return {
      hasAside: Boolean(aside),
      asideBg: aside ? getComputedStyle(aside).backgroundColor : null,
      mainPadding: main ? getComputedStyle(main).paddingTop : null,
      title: document.querySelector('h1,h2')?.textContent || '',
    };
  });
  report.push({ route, errors, textLength: text.length, ...info });
  page.off('pageerror', onPageError);
  page.off('console', onConsole);
  page.off('requestfailed', onFailed);
}

console.log(JSON.stringify(report, null, 2));
await browser.close();
