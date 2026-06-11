import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8001';
const routes = [
  '/', '/admin/lms/system-check', '/admin/lms/action-check', '/moodle-parity',
  '/analytics', '/reports', '/ai', '/mobile',
  '/courses', '/courses/studio', '/repository', '/learning-path', '/learning-path/learner-progress', '/learning-path/class-progress', '/enrollment',
  '/videos', '/videos/analytics', '/videos/lesson',
  '/question-banks', '/question-banks/editor', '/question-banks/import', '/question-banks/blueprints', '/question-banks/outcomes', '/question-banks/categories',
  '/exams', '/exams/builder', '/exams/assign', '/exams/take', '/exams/results', '/exams/manual-grading',
  '/assignments', '/assignments/submission', '/assignments/grading',
  '/gradebook', '/gradebook/builder', '/gradebook/approval', '/gradebook/student',
  '/attendance', '/attendance/live', '/attendance/checkin', '/attendance/teacher', '/attendance/eligibility',
  '/community', '/surveys', '/surveys/builder',
  '/career', '/career/public', '/career/employer',
  '/credentials', '/credentials/certificates', '/credentials/wallet', '/credentials/verify',
  '/obe', '/obe/outcome-matrix', '/obe/competency-framework', '/obe/coverage', '/obe/achievement', '/obe/accreditation',
  '/standards/scorm', '/standards/xapi', '/standards/lti', '/standards/tools',
  '/sis', '/sis/mapping', '/sis/sync-jobs', '/sis/events', '/sis/systems',
  '/settings', '/settings/tenants', '/settings/campuses', '/settings/academic-units', '/settings/roles', '/settings/white-label', '/settings/audit-logs',
  '/security', '/plugins', '/backup', '/uat',
];

async function login(page) {
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
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(800);
}

async function auditViewport(browser, viewport) {
  const page = await browser.newPage({ viewport });
  const errors = [];
  const failedRequests = [];
  page.on('console', msg => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  page.on('pageerror', err => errors.push(`pageerror:${err.message}`));
  page.on('requestfailed', request => {
    const failure = request.failure();
    failedRequests.push(`${request.method()} ${request.url()} ${failure?.errorText || ''}`);
  });

  await login(page);

  const results = [];
  for (const route of routes) {
    const beforeErrors = errors.length;
    const beforeFailed = failedRequests.length;
    let navError = '';

    try {
      await page.goto(`${base}${route}?v=${Date.now()}`, { waitUntil: 'domcontentloaded', timeout: 12000 });
      await page.waitForTimeout(350);
    } catch (error) {
      navError = error.message;
    }

    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForTimeout(300);

    let data;
    try {
      data = await page.evaluate(() => {
      const bodyText = document.body.innerText.trim();
      const h1 = document.querySelector('h1')?.innerText?.trim() || '';
      const aside = document.querySelector('aside');
      const main = document.querySelector('main') || document.body;
      const rects = [...document.querySelectorAll('button,a,td,th,h1,h2,h3,p,span')]
        .map(el => {
          const rect = el.getBoundingClientRect();
          return { text: el.innerText?.trim() || '', width: rect.width, scrollWidth: el.scrollWidth };
        })
        .filter(item => item.text && item.width > 0 && item.scrollWidth > item.width + 3)
        .slice(0, 8);

      return {
        title: h1,
        bodyLength: bodyText.length,
        hasAside: Boolean(aside),
        buttonCount: document.querySelectorAll('button').length,
        tableCount: document.querySelectorAll('table').length,
        blank: bodyText.length < 120,
        dashboardFallback: location.pathname !== '/' && h1 === 'Tổng quan hệ thống',
        horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 8,
        mainOverflow: main.scrollWidth > main.clientWidth + 8,
        clippedText: rects,
      };
      });
    } catch (error) {
      await page.waitForTimeout(600);
      data = await page.evaluate(() => {
        const bodyText = document.body.innerText.trim();
        const h1 = document.querySelector('h1')?.innerText?.trim() || '';
        return {
          title: h1,
          bodyLength: bodyText.length,
          hasAside: Boolean(document.querySelector('aside')),
          buttonCount: document.querySelectorAll('button').length,
          tableCount: document.querySelectorAll('table').length,
          blank: bodyText.length < 120,
          dashboardFallback: location.pathname !== '/' && h1 === 'Tổng quan hệ thống',
          horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 8,
          mainOverflow: false,
          clippedText: [],
        };
      });
    }

    const realErrors = errors.slice(beforeErrors)
      .filter(error => !error.includes('net::ERR_ABORTED'))
      .filter(error => !error.includes('Failed to fetch'));
    const realFailedRequests = failedRequests.slice(beforeFailed)
      .filter(error => !error.includes('net::ERR_ABORTED'));

    results.push({
      route,
      viewport,
      navError: navError.includes('net::ERR_ABORTED') ? '' : navError,
      newErrors: realErrors,
      newFailedRequests: realFailedRequests,
      ...data,
    });
  }

  await page.close();
  return results;
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const mode = process.argv[2] || 'desktop';
  const desktop = mode === 'mobile' ? [] : await auditViewport(browser, { width: 1440, height: 950 });
  const mobile = mode === 'desktop' ? [] : await auditViewport(browser, { width: 390, height: 844 });
  await browser.close();

  const results = [...desktop, ...mobile];
  const issues = results.filter(row =>
    row.navError ||
    row.newErrors.length ||
    row.newFailedRequests.length ||
    row.blank ||
    row.dashboardFallback ||
    row.horizontalOverflow
  );

  console.log(JSON.stringify({
    checked: results.length,
    issueCount: issues.length,
    issues,
  }, null, 2));
})();
