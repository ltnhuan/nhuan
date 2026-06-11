import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8001';
const routes = [
  '/', '/courses', '/courses/studio', '/repository', '/learning-path', '/learning-path/learner-progress', '/learning-path/class-progress', '/enrollment', '/videos', '/videos/analytics', '/videos/lesson', '/question-banks', '/question-banks/categories', '/question-banks/editor', '/question-banks/import', '/question-banks/outcomes', '/question-banks/blueprints', '/exams', '/exams/builder', '/exams/assign', '/exams/take', '/exams/results', '/exams/manual-grading', '/assignments', '/assignments/submission', '/assignments/grading', '/community', '/gradebook', '/gradebook/builder', '/gradebook/approval', '/gradebook/student', '/attendance', '/attendance/live', '/attendance/checkin', '/attendance/teacher', '/attendance/eligibility', '/analytics', '/surveys', '/surveys/builder', '/credentials', '/credentials/certificates', '/credentials/wallet', '/credentials/verify', '/career', '/career/public', '/career/employer', '/obe', '/obe/outcome-matrix', '/obe/competency-framework', '/obe/coverage', '/obe/achievement', '/obe/accreditation', '/standards/scorm', '/standards/xapi', '/standards/lti', '/standards/tools', '/sis', '/sis/mapping', '/sis/sync-jobs', '/sis/events', '/sis/systems', '/reports', '/ai', '/mobile', '/settings', '/settings/tenants', '/settings/campuses', '/settings/academic-units', '/settings/roles', '/settings/white-label', '/settings/audit-logs'
];

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('pageerror', error => errors.push(`pageerror: ${error.message}`));
page.on('console', msg => {
  if (msg.type() === 'error') errors.push(`console: ${msg.text()}`);
});
page.on('requestfailed', request => errors.push(`requestfailed: ${request.url()} ${request.failure()?.errorText}`));

await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
await page.fill('input[type="password"]', 'admin123456');
await page.click('button[type="submit"]');
await page.waitForTimeout(500);

const results = [];
for (const route of routes) {
  await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
  const bodyText = (await page.locator('body').innerText()).trim();
  const appBox = await page.locator('#app').boundingBox();
  const cssOk = await page.evaluate(() => {
    const sidebar = document.querySelector('aside');
    const main = document.querySelector('main');
    const bodyBg = getComputedStyle(document.body).backgroundColor;
    return {
      hasSidebar: Boolean(sidebar),
      sidebarBg: sidebar ? getComputedStyle(sidebar).backgroundColor : null,
      mainPadding: main ? getComputedStyle(main).paddingTop : null,
      bodyBg,
    };
  });
  results.push({ route, textLength: bodyText.length, appHeight: appBox?.height || 0, ...cssOk });
}

console.log(JSON.stringify({ errors, results }, null, 2));
await browser.close();
