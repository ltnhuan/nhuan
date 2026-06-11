import { chromium } from '@playwright/test';
const base='http://127.0.0.1:8001';
const browser=await chromium.launch({headless:true});
const page=await browser.newPage();
await page.goto(`${base}/login`, {waitUntil:'networkidle'});
await page.fill('input[type="email"]','admin.lms@vabis.edu.vn');
await page.fill('input[type="password"]','admin123456');
await Promise.all([page.waitForResponse(r=>r.url().endsWith('/login')&&r.request().method()==='POST'), page.click('button[type="submit"]')]);
for (const route of ['/', '/analytics', '/mobile']) {
  const [req] = await Promise.all([
    page.waitForRequest(r => r.url() === `${base}${route}`),
    page.goto(`${base}${route}`, {waitUntil:'domcontentloaded'})
  ]);
  console.log(route, req.headers()['cookie']);
}
await browser.close();
