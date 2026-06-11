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
  await page.waitForTimeout(800);
  const api = await page.evaluate(async () => {
    const headers = { 'X-Tenant-Code': 'VABIS', 'X-Demo-User-Email': 'admin.lms@vabis.edu.vn', 'Content-Type': 'application/json' };
    const check = await fetch('/api/v1/admin/lms/system-check', { headers }).then(r => r.json());
    const smoke = await fetch('/api/v1/admin/lms/system-check/actions/run-smoke-test', { method: 'POST', headers }).then(r => r.json());
    return { check, smoke };
  });
  console.log(JSON.stringify({ errors, checkSummary: api.check.data.summary, smokeSummary: api.smoke.data.summary }, null, 2));
  await browser.close();
})();
