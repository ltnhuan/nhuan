import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8001';
const routes = ['/assignments', '/assignments/deadlines', '/assignments/submission', '/assignments/grading', '/repository', '/admin/lms/action-check'];

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
const errors = [];
page.on('pageerror', error => errors.push(`pageerror: ${error.message}`));
page.on('console', msg => {
  if (msg.type() === 'error') errors.push(`console: ${msg.text()}`);
});
page.on('requestfailed', request => {
  const failure = request.failure()?.errorText || '';
  if (failure.includes('ERR_ABORTED')) return;
  errors.push(`requestfailed: ${request.url()} ${failure}`);
});

await page.goto(`${base}/login?v=${Date.now()}`, { waitUntil: 'networkidle' });
if (await page.locator('input[type="email"]').count()) {
  await page.fill('input[type="email"]', 'admin.lms@vabis.edu.vn');
  await page.fill('input[type="password"]', 'admin123456');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(800);
}

const routeResults = [];
for (const route of routes) {
  await page.goto(`${base}${route}?v=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  routeResults.push(await page.evaluate((route) => {
    const bodyText = document.body.innerText.trim();
    const activeLink = [...document.querySelectorAll('aside a')].find(link => link.getAttribute('href')?.split('?')[0] === route);
    const activeLinks = [...document.querySelectorAll('aside a')].filter(link => link.className.includes('bg-cyan-500'));
    return {
      route,
      textLength: bodyText.length,
      h1: document.querySelector('h1')?.innerText || '',
      hasSidebar: Boolean(document.querySelector('aside')),
      hasActiveMenuLink: Boolean(activeLink),
      activeMenuCount: activeLinks.length,
      activeMenuRoutes: activeLinks.map(link => link.getAttribute('href')?.split('?')[0]),
      hasTable: Boolean(document.querySelector('table')),
      hasAssignmentDeadlineMenu: bodyText.includes('Lịch deadline bài tập'),
      hasRuntimeErrorText: /runtime error|cannot read|undefined|null is not an object/i.test(bodyText),
    };
  }, route));
}

const api = await page.evaluate(async () => {
  const headers = {
    'X-Tenant-Code': 'VABIS',
    'X-Demo-User-Email': 'admin.lms@vabis.edu.vn',
    'Content-Type': 'application/json',
  };
  const assignments = await fetch('/api/v1/assignments?per_page=5', { headers }).then(async response => ({ ok: response.ok, status: response.status, body: await response.json() }));
  const firstId = assignments.body?.data?.[0]?.id;
  const deadline = firstId
    ? await fetch(`/api/v1/assignments/${firstId}/deadline`, { headers }).then(async response => ({ ok: response.ok, status: response.status, body: await response.json() }))
    : null;
  const actionCheck = await fetch('/api/v1/admin/lms/action-check', { headers }).then(async response => ({ ok: response.ok, status: response.status, body: await response.json() }));
  const assignmentActions = (actionCheck.body?.data?.actions || []).filter(action => action.module === 'assignment');
  const repositoryActions = (actionCheck.body?.data?.actions || []).filter(action => action.module === 'repository');

  return {
    assignments: {
      ok: assignments.ok,
      status: assignments.status,
      count: assignments.body?.data?.length || 0,
      first: assignments.body?.data?.[0] ? {
        id: assignments.body.data[0].id,
        title: assignments.body.data[0].title,
        hasDeadlineEngine: Boolean(assignments.body.data[0].settings?.deadline_engine),
        submissionsCount: assignments.body.data[0].submissions_count,
        lateCount: assignments.body.data[0].late_submissions_count,
      } : null,
    },
    deadline: deadline ? {
      ok: deadline.ok,
      status: deadline.status,
      phase: deadline.body?.phase,
      mode: deadline.body?.deadline_mode,
      officialDue: deadline.body?.official_due_at,
      effectiveDue: deadline.body?.effective_due_at,
    } : null,
    actionCheck: {
      ok: actionCheck.ok,
      status: actionCheck.status,
      summary: actionCheck.body?.data?.summary,
      assignmentActions: assignmentActions.length,
      repositoryActions: repositoryActions.length,
      assignmentIssues: assignmentActions.filter(action => action.status !== 'OK'),
      repositoryIssues: repositoryActions.filter(action => action.status !== 'OK'),
    },
  };
});

console.log(JSON.stringify({ errors, routeResults, api }, null, 2));
await browser.close();
