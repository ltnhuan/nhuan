<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
  Activity,
  Award,
  BarChart3,
  Bell,
  BookOpen,
  Bot,
  BriefcaseBusiness,
  Building2,
  Cable,
  CalendarCheck,
  CheckCircle2,
  ChevronRight,
  ClipboardCheck,
  Command,
  DatabaseZap,
  FileChartColumn,
  FolderOpen,
  GraduationCap,
  KeyRound,
  Landmark,
  LayoutDashboard,
  LockKeyhole,
  LogOut,
  Menu,
  MapPinned,
  Palette,
  PenTool,
  Plug,
  RadioTower,
  Route,
  ScrollText,
  Search,
  Settings,
  ShieldCheck,
  Smartphone,
  TestTube2,
  UploadCloud,
  UserCheck,
  Users,
  Video,
  WalletCards,
  Workflow,
  X,
} from '@lucide/vue'

const currentPath = window.location.pathname
defineProps({
  sessionUser: { type: Object, default: null },
})
defineEmits(['logout'])

const rowDetailOpen = ref(false)
const rowDetail = ref({ title: '', id: '', fields: [] })
const mobileMenuOpen = ref(false)
const desktopMenuOpen = ref(localStorage.getItem('eralms.desktopMenuOpen') !== 'false')
const editDrawerOpen = ref(false)
const editNotice = ref('')
const editDraft = ref({
  title: '',
  status: 'draft',
  note: '',
  fields: [],
})

const defaultIcon = Command
const routeIcons = [
  ['/', LayoutDashboard],
  ['/admin/lms/system-check', ShieldCheck],
  ['/admin/lms/action-check', Activity],
  ['/analytics', BarChart3],
  ['/reports', FileChartColumn],
  ['/ai', Bot],
  ['/mobile', Smartphone],
  ['/courses/studio', PenTool],
  ['/courses', GraduationCap],
  ['/repository', FolderOpen],
  ['/learning-path', Route],
  ['/enrollment', Users],
  ['/videos', Video],
  ['/question-banks/import', UploadCloud],
  ['/question-banks', BookOpen],
  ['/exams', ClipboardCheck],
  ['/assignments', CheckCircle2],
  ['/gradebook', FileChartColumn],
  ['/attendance/live', RadioTower],
  ['/attendance', CalendarCheck],
  ['/community', Users],
  ['/surveys', ClipboardCheck],
  ['/career', BriefcaseBusiness],
  ['/credentials/wallet', WalletCards],
  ['/credentials', Award],
  ['/obe', BarChart3],
  ['/standards', Cable],
  ['/sis/mapping', Workflow],
  ['/sis', DatabaseZap],
  ['/settings/tenants', Building2],
  ['/settings/campuses', MapPinned],
  ['/settings/academic-units', Landmark],
  ['/settings/roles', KeyRound],
  ['/settings/white-label', Palette],
  ['/settings/audit-logs', ScrollText],
  ['/settings', Settings],
  ['/security', LockKeyhole],
  ['/plugins', Plug],
  ['/backup', DatabaseZap],
  ['/uat', TestTube2],
]

const editRouteRules = [
  ['/admin/lms/system-check', '/admin/lms/system-check', 'Sửa hệ thống'],
  ['/admin/lms/action-check', '/admin/lms/action-check', 'Sửa action'],
  ['/analytics', '/analytics', 'Sửa analytics'],
  ['/reports', '/reports', 'Sửa báo cáo'],
  ['/question-banks/editor', '/question-banks/editor', 'Sửa câu hỏi'],
  ['/question-banks/categories', '/question-banks/categories', 'Sửa danh mục'],
  ['/question-banks/blueprints', '/question-banks/blueprints', 'Sửa blueprint'],
  ['/question-banks/outcomes', '/question-banks/outcomes', 'Sửa ma trận'],
  ['/question-banks/import', '/question-banks/import', 'Sửa import'],
  ['/question-banks', '/question-banks/editor', 'Sửa câu hỏi'],
  ['/exams/builder', '/exams/builder', 'Sửa đề thi'],
  ['/exams/assign', '/exams/assign', 'Sửa phân công'],
  ['/exams/manual-grading', '/exams/manual-grading', 'Sửa điểm'],
  ['/exams/results', '/exams/results', 'Sửa kết quả'],
  ['/exams', '/exams/builder', 'Sửa đề thi'],
  ['/courses/studio', '/courses/studio', 'Sửa bài giảng'],
  ['/courses', '/courses/studio', 'Sửa khóa học'],
  ['/repository', '/repository', 'Sửa học liệu'],
  ['/learning-path/learner-progress', '/learning-path/learner-progress', 'Sửa tiến độ cá nhân'],
  ['/learning-path/class-progress', '/learning-path/class-progress', 'Sửa tiến độ lớp'],
  ['/learning-path', '/learning-path', 'Sửa lộ trình'],
  ['/enrollment', '/enrollment', 'Sửa ghi danh'],
  ['/videos/analytics', '/videos/analytics', 'Sửa video analytics'],
  ['/videos/lesson', '/videos/lesson', 'Sửa video bài học'],
  ['/videos', '/videos', 'Sửa video'],
  ['/assignments/deadlines', '/assignments/deadlines', 'Sửa deadline'],
  ['/assignments/grading', '/assignments/grading', 'Sửa chấm bài'],
  ['/assignments/submission', '/assignments/submission', 'Sửa bài nộp'],
  ['/assignments', '/assignments', 'Sửa bài tập'],
  ['/gradebook/builder', '/gradebook/builder', 'Sửa sổ điểm'],
  ['/gradebook/approval', '/gradebook/approval', 'Sửa duyệt điểm'],
  ['/gradebook/student', '/gradebook/student', 'Sửa điểm cá nhân'],
  ['/gradebook', '/gradebook/builder', 'Sửa sổ điểm'],
  ['/attendance/live', '/attendance/live', 'Sửa live session'],
  ['/attendance/checkin', '/attendance/checkin', 'Sửa check-in'],
  ['/attendance/teacher', '/attendance/teacher', 'Sửa điểm danh'],
  ['/attendance/eligibility', '/attendance/eligibility', 'Sửa điều kiện dự thi'],
  ['/attendance', '/attendance', 'Sửa điểm danh'],
  ['/surveys/builder', '/surveys/builder', 'Sửa khảo sát'],
  ['/surveys', '/surveys/builder', 'Sửa khảo sát'],
  ['/community', '/community', 'Sửa cộng đồng'],
  ['/career/public', '/career/public', 'Sửa public portfolio'],
  ['/career/employer', '/career/employer', 'Sửa employer view'],
  ['/career', '/career', 'Sửa portfolio'],
  ['/credentials/certificates', '/credentials/certificates', 'Sửa chứng chỉ'],
  ['/credentials/wallet', '/credentials/wallet', 'Sửa ví chứng chỉ'],
  ['/credentials/verify', '/credentials/verify', 'Sửa xác minh'],
  ['/credentials', '/credentials/certificates', 'Sửa chứng chỉ'],
  ['/obe/outcome-matrix', '/obe/outcome-matrix', 'Sửa ma trận OBE'],
  ['/obe/competency-framework', '/obe/competency-framework', 'Sửa năng lực'],
  ['/obe/coverage', '/obe/coverage', 'Sửa coverage'],
  ['/obe/achievement', '/obe/achievement', 'Sửa achievement'],
  ['/obe/accreditation', '/obe/accreditation', 'Sửa accreditation'],
  ['/obe', '/obe', 'Sửa OBE'],
  ['/standards/xapi', '/standards/xapi', 'Sửa xAPI'],
  ['/standards/tools', '/standards/tools', 'Sửa công cụ'],
  ['/standards/lti', '/standards/lti', 'Sửa LTI'],
  ['/standards/scorm', '/standards/scorm', 'Sửa SCORM'],
  ['/standards', '/standards/tools', 'Sửa chuẩn học liệu'],
  ['/sis/mapping', '/sis/mapping', 'Sửa mapping'],
  ['/sis/sync-jobs', '/sis/sync-jobs', 'Sửa sync jobs'],
  ['/sis/events', '/sis/events', 'Sửa integration events'],
  ['/sis/systems', '/sis/systems', 'Sửa hệ thống SIS'],
  ['/sis', '/sis/mapping', 'Sửa SIS'],
  ['/settings/tenants', '/settings/tenants', 'Sửa tenant'],
  ['/settings/campuses', '/settings/campuses', 'Sửa campus'],
  ['/settings/academic-units', '/settings/academic-units', 'Sửa đơn vị'],
  ['/settings/roles', '/settings/roles', 'Sửa quyền'],
  ['/settings/white-label', '/settings/white-label', 'Sửa giao diện'],
  ['/settings/audit-logs', '/settings/audit-logs', 'Sửa audit logs'],
  ['/settings', '/settings', 'Sửa cấu hình'],
  ['/security', '/security', 'Sửa bảo mật'],
  ['/plugins', '/plugins', 'Sửa plugin'],
  ['/backup', '/backup', 'Sửa backup'],
  ['/uat', '/uat', 'Sửa UAT'],
  ['/ai', '/ai', 'Sửa học liệu AI'],
  ['/mobile', '/mobile', 'Sửa mobile'],
  ['/', '/', 'Sửa dashboard'],
]

const menuGroups = [
  {
    title: 'Điều hành',
    items: [
      { label: 'Tổng quan', route: '/' },
      { label: 'Kiểm tra hệ thống', route: '/admin/lms/system-check' },
      { label: 'Action Check', route: '/admin/lms/action-check' },
      { label: 'Learning Analytics', route: '/analytics' },
      { label: 'Báo cáo', route: '/reports' },
      { label: 'AI trợ giảng', route: '/ai' },
      { label: 'Mobile learning', route: '/mobile' },
    ],
  },
  {
    title: 'Đào tạo',
    items: [
      { label: 'Quản trị khóa học', route: '/courses' },
      { label: 'Studio bài giảng', route: '/courses/studio' },
      { label: 'Kho học liệu', route: '/repository' },
      { label: 'Lộ trình học tập', route: '/learning-path' },
      { label: 'Tiến độ cá nhân', route: '/learning-path/learner-progress' },
      { label: 'Tiến độ lớp', route: '/learning-path/class-progress' },
      { label: 'Quản lý ghi danh', route: '/enrollment' },
    ],
  },
  {
    title: 'Nội dung & đánh giá',
    items: [
      { label: 'Nền tảng video', route: '/videos' },
      { label: 'Video analytics', route: '/videos/analytics' },
      { label: 'Bài học video', route: '/videos/lesson' },
      { label: 'Ngân hàng câu hỏi', route: '/question-banks' },
      { label: 'Soạn câu hỏi', route: '/question-banks/editor' },
      { label: 'Import câu hỏi', route: '/question-banks/import' },
      { label: 'Blueprint đề thi', route: '/question-banks/blueprints' },
      { label: 'Ma trận CLO câu hỏi', route: '/question-banks/outcomes' },
      { label: 'Cây danh mục câu hỏi', route: '/question-banks/categories' },
    ],
  },
  {
    title: 'Khảo thí & điểm',
    items: [
      { label: 'Kiểm tra online', route: '/exams' },
      { label: 'Tạo đề thi', route: '/exams/builder' },
      { label: 'Giao đề thi', route: '/exams/assign' },
      { label: 'Làm bài thi', route: '/exams/take' },
      { label: 'Kết quả thi', route: '/exams/results' },
      { label: 'Chấm tự luận', route: '/exams/manual-grading' },
      { label: 'Bài tập', route: '/assignments' },
      { label: 'Lịch deadline bài tập', route: '/assignments/deadlines' },
      { label: 'Nộp bài', route: '/assignments/submission' },
      { label: 'Chấm bài', route: '/assignments/grading' },
      { label: 'Sổ điểm', route: '/gradebook' },
      { label: 'Thiết lập sổ điểm', route: '/gradebook/builder' },
      { label: 'Duyệt điểm', route: '/gradebook/approval' },
      { label: 'Điểm của tôi', route: '/gradebook/student' },
    ],
  },
  {
    title: 'Người học',
    items: [
      { label: 'Điểm danh online', route: '/attendance' },
      { label: 'Live session', route: '/attendance/live' },
      { label: 'Check-in sinh viên', route: '/attendance/checkin' },
      { label: 'Bảng điểm danh GV', route: '/attendance/teacher' },
      { label: 'Điều kiện dự thi', route: '/attendance/eligibility' },
      { label: 'Cộng đồng học tập', route: '/community' },
      { label: 'Khảo sát & QA', route: '/surveys' },
      { label: 'Thiết kế khảo sát', route: '/surveys/builder' },
      { label: 'Career Portfolio', route: '/career' },
      { label: 'Public portfolio', route: '/career/public' },
      { label: 'Employer View', route: '/career/employer' },
    ],
  },
  {
    title: 'Chứng chỉ & chuẩn',
    items: [
      { label: 'Digital Credential', route: '/credentials' },
      { label: 'Certificate builder', route: '/credentials/certificates' },
      { label: 'Credential wallet', route: '/credentials/wallet' },
      { label: 'Verify portal', route: '/credentials/verify' },
      { label: 'OBE outcomes', route: '/obe' },
      { label: 'OBE outcome matrix', route: '/obe/outcome-matrix' },
      { label: 'Competency framework', route: '/obe/competency-framework' },
      { label: 'OBE coverage', route: '/obe/coverage' },
      { label: 'OBE achievement', route: '/obe/achievement' },
      { label: 'Accreditation reports', route: '/obe/accreditation' },
      { label: 'SCORM Manager', route: '/standards/scorm' },
      { label: 'xAPI Explorer', route: '/standards/xapi' },
      { label: 'LTI Registry', route: '/standards/lti' },
      { label: 'External Tools', route: '/standards/tools' },
    ],
  },
  {
    title: 'Hệ thống',
    items: [
      { label: 'Đồng bộ SIS', route: '/sis' },
      { label: 'Mapping SIS', route: '/sis/mapping' },
      { label: 'Sync jobs', route: '/sis/sync-jobs' },
      { label: 'Integration events', route: '/sis/events' },
      { label: 'System config', route: '/sis/systems' },
      { label: 'Cấu hình', route: '/settings' },
      { label: 'Tenant', route: '/settings/tenants' },
      { label: 'Campus', route: '/settings/campuses' },
      { label: 'Khoa/Bộ môn', route: '/settings/academic-units' },
      { label: 'Role/Permission', route: '/settings/roles' },
      { label: 'White label', route: '/settings/white-label' },
      { label: 'Audit logs', route: '/settings/audit-logs' },
      { label: 'Bảo mật', route: '/security' },
      { label: 'Plugin', route: '/plugins' },
      { label: 'Sao lưu', route: '/backup' },
      { label: 'UAT', route: '/uat' },
    ],
  },
]

const activeRoute = computed(() => {
  const routes = menuGroups
    .flatMap((group) => group.items.map((item) => item.route))
    .filter((route) => route === '/' ? currentPath === '/' : currentPath === route || currentPath.startsWith(`${route}/`))
    .sort((a, b) => b.length - a.length)

  return routes[0] || '/'
})

const editTarget = computed(() => {
  const match = editRouteRules
    .filter(([prefix]) => prefix === '/' ? currentPath === '/' : currentPath === prefix || currentPath.startsWith(`${prefix}/`))
    .sort((a, b) => b[0].length - a[0].length)[0]

  return {
    route: match?.[1] || activeRoute.value || '/',
    label: match?.[2] || 'Sửa trang',
  }
})

function isActive(route) {
  return activeRoute.value === route
}

function iconFor(route) {
  const match = routeIcons
    .filter(([prefix]) => prefix === '/' ? route === '/' : route.startsWith(prefix))
    .sort((a, b) => b[0].length - a[0].length)[0]

  return match?.[1] || defaultIcon
}

function navigate(route) {
  mobileMenuOpen.value = false
  window.location.href = `${route}?v=${Date.now()}`
}

function toggleDesktopMenu() {
  desktopMenuOpen.value = !desktopMenuOpen.value
  localStorage.setItem('eralms.desktopMenuOpen', desktopMenuOpen.value ? 'true' : 'false')
}

function navigateEdit() {
  const detail = {
    path: currentPath,
    target: editTarget.value,
    row: rowDetailOpen.value ? rowDetail.value : null,
    handled: false,
  }
  window.dispatchEvent(new CustomEvent('eralms:edit-page', { detail }))

  if (detail.handled) return

  if (editTarget.value.route !== currentPath) {
    navigate(editTarget.value.route)
    return
  }

  openGlobalEdit(rowDetailOpen.value ? rowDetail.value : null)
}

function openGlobalEdit(row = null) {
  editDraft.value = {
    title: row?.title || editTarget.value.label,
    status: 'draft',
    note: '',
    fields: row?.fields || [
      normalizeField('Module', activeRoute.value),
      normalizeField('Trang', currentPath),
      normalizeField('Hành động', editTarget.value.label),
    ],
  }
  editNotice.value = ''
  editDrawerOpen.value = true
}

function saveGlobalEdit() {
  const key = 'eralms_global_edit_drafts'
  const drafts = JSON.parse(localStorage.getItem(key) || '[]')
  drafts.unshift({
    path: currentPath,
    target: editTarget.value,
    title: editDraft.value.title,
    status: editDraft.value.status,
    note: editDraft.value.note,
    fields: editDraft.value.fields,
    saved_at: new Date().toISOString(),
  })
  localStorage.setItem(key, JSON.stringify(drafts.slice(0, 100)))
  editNotice.value = 'Đã lưu nháp chỉnh sửa cho module hiện tại.'
}

function closeGlobalEdit() {
  editDrawerOpen.value = false
}

function shouldIgnoreRowClick(target) {
  return Boolean(target.closest('button,a,input,select,textarea,label,[role="button"],[data-no-row-popup="true"]'))
}

function normalizeField(label, value) {
  return {
    label: String(label || '').trim() || 'Thông tin',
    value: String(value || '').replace(/\s+/g, ' ').trim() || '-',
  }
}

function inferRowId(fields, row) {
  const explicit = row.dataset.rowId || row.dataset.id || ''
  if (explicit) return explicit

  const idField = fields.find((field) => /^(id|mã|ma|code|key)$/i.test(field.label))
    || fields.find((field) => /(id|mã|ma|code|key)/i.test(field.label))

  return idField?.value || fields[0]?.value || ''
}

function fieldsFromPayload(row) {
  if (!row.dataset.rowPayload) return null

  try {
    const payload = JSON.parse(row.dataset.rowPayload)
    return Object.entries(payload).map(([key, value]) => normalizeField(key, formatPayloadValue(value)))
  } catch {
    return null
  }
}

function formatPayloadValue(value) {
  if (value === null || value === undefined || value === '') return '-'
  if (Array.isArray(value)) return value.map((item) => typeof item === 'object' ? JSON.stringify(item) : item).join(', ')
  if (typeof value === 'object') return JSON.stringify(value, null, 2)

  return value
}

function fieldsFromTable(row) {
  const table = row.closest('table')
  const headers = Array.from(table?.querySelectorAll('thead th') || [])
    .map((cell) => cell.textContent?.trim())
    .filter((text) => text && !/^(thao tác|action|actions)$/i.test(text))

  return Array.from(row.children)
    .filter((cell) => cell.tagName === 'TD')
    .map((cell, index) => normalizeField(headers[index] || `Cột ${index + 1}`, cell.textContent || ''))
    .filter((field) => field.value !== '-' && !/^(thao tác|action|actions)$/i.test(field.label))
}

function openRowDetail(row) {
  const payloadFields = fieldsFromPayload(row)
  const fields = payloadFields?.length ? payloadFields : fieldsFromTable(row)
  if (!fields.length) return

  const id = inferRowId(fields, row)
  const title = row.dataset.rowTitle || fields.find((field) => /title|tên|name/i.test(field.label))?.value || id || 'Bản ghi'

  rowDetail.value = {
    id,
    title,
    fields,
  }
  rowDetailOpen.value = true
}

function handleTableClick(event) {
  if (shouldIgnoreRowClick(event.target)) return

  const row = event.target.closest('tbody tr')
  if (!row || row.getAttribute('aria-hidden') === 'true' || row.closest('[data-disable-row-popup="true"]')) return
  if (!row.querySelector('td')) return

  openRowDetail(row)
}

function closeRowDetail() {
  rowDetailOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', handleTableClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleTableClick)
})
</script>

<template>
  <div class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900">
    <aside v-if="desktopMenuOpen" class="fixed inset-y-0 left-0 hidden w-76 overflow-y-auto border-r border-slate-950 bg-slate-950 text-slate-100 shadow-2xl shadow-slate-950/30 lg:block">
      <div class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-4 py-4 backdrop-blur">
        <div class="flex items-center gap-3">
          <div class="grid h-10 w-10 place-items-center rounded-md bg-cyan-500 text-white shadow-lg shadow-cyan-950/40">
            <BookOpen class="h-5 w-5" />
          </div>
          <div class="min-w-0">
            <div class="text-sm font-bold tracking-wide text-white">EraLMS Enterprise</div>
            <div class="text-xs text-slate-400">VABIS LMS Operations</div>
          </div>
          <button class="ml-auto grid h-9 w-9 place-items-center rounded-md border border-white/10 text-slate-300 hover:bg-white/10" title="Đóng menu chính" @click="toggleDesktopMenu">
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>
      <nav class="space-y-5 px-3 py-4 text-sm">
        <section v-for="group in menuGroups" :key="group.title">
          <div class="mb-2 px-2 text-[11px] font-semibold uppercase text-slate-500">{{ group.title }}</div>
          <div class="space-y-1">
            <a
              v-for="item in group.items"
              :key="item.route"
              :href="item.route"
              class="group flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-slate-300 transition hover:bg-white/10 hover:text-white"
              :class="{ 'bg-cyan-500/15 text-white ring-1 ring-cyan-400/25 shadow-sm shadow-cyan-950/30': isActive(item.route) }"
              @click.prevent="navigate(item.route)"
            >
              <span
                class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white/5 text-slate-400 transition group-hover:bg-white/10 group-hover:text-white"
                :class="{ 'bg-cyan-400 text-slate-950 group-hover:bg-cyan-300 group-hover:text-slate-950': isActive(item.route) }"
              >
                <component :is="iconFor(item.route)" class="h-4 w-4" />
              </span>
              <span class="truncate">{{ item.label }}</span>
              <ChevronRight
                class="ml-auto h-4 w-4 shrink-0 text-slate-600 transition group-hover:text-slate-300"
                :class="{ 'text-cyan-200': isActive(item.route) }"
              />
            </a>
          </div>
        </section>
      </nav>
    </aside>
    <div
      v-if="mobileMenuOpen"
      class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden"
      @click.self="mobileMenuOpen = false"
    >
      <aside class="h-full w-[min(86vw,304px)] overflow-y-auto border-r border-slate-950 bg-slate-950 text-slate-100 shadow-2xl shadow-slate-950/40">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-white/10 bg-slate-950/95 px-4 py-4 backdrop-blur">
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-md bg-cyan-500 text-white shadow-lg shadow-cyan-950/40">
              <BookOpen class="h-5 w-5" />
            </div>
            <div>
              <div class="text-sm font-bold tracking-wide text-white">EraLMS Enterprise</div>
              <div class="text-xs text-slate-400">VABIS LMS Operations</div>
            </div>
          </div>
          <button class="grid h-9 w-9 place-items-center rounded-md border border-white/10 text-slate-300" @click="mobileMenuOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>
        <nav class="space-y-5 px-3 py-4 text-sm">
          <section v-for="group in menuGroups" :key="group.title">
            <div class="mb-2 px-2 text-[11px] font-semibold uppercase text-slate-500">{{ group.title }}</div>
            <div class="space-y-1">
              <a
                v-for="item in group.items"
                :key="item.route"
                :href="item.route"
                class="group flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-slate-300 transition hover:bg-white/10 hover:text-white"
                :class="{ 'bg-cyan-500/15 text-white ring-1 ring-cyan-400/25 shadow-sm shadow-cyan-950/30': isActive(item.route) }"
                @click.prevent="navigate(item.route)"
              >
                <span
                  class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white/5 text-slate-400 transition group-hover:bg-white/10 group-hover:text-white"
                  :class="{ 'bg-cyan-400 text-slate-950 group-hover:bg-cyan-300 group-hover:text-slate-950': isActive(item.route) }"
                >
                  <component :is="iconFor(item.route)" class="h-4 w-4" />
                </span>
                <span class="truncate">{{ item.label }}</span>
                <ChevronRight
                  class="ml-auto h-4 w-4 shrink-0 text-slate-600 transition group-hover:text-slate-300"
                  :class="{ 'text-cyan-200': isActive(item.route) }"
                />
              </a>
            </div>
          </section>
        </nav>
      </aside>
    </div>

    <div class="min-w-0" :class="desktopMenuOpen ? 'lg:pl-76' : 'lg:pl-0'">
      <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-5 shadow-sm shadow-slate-200/60 backdrop-blur">
        <button class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-300 bg-white text-slate-700 lg:hidden" @click="mobileMenuOpen = true">
          <Menu class="h-4 w-4" />
        </button>
        <button class="hidden h-10 shrink-0 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 lg:inline-flex" @click="toggleDesktopMenu">
          <Menu v-if="!desktopMenuOpen" class="h-4 w-4" />
          <X v-else class="h-4 w-4" />
          {{ desktopMenuOpen ? 'Đóng menu' : 'Mở menu' }}
        </button>
        <div class="min-w-0">
          <div class="text-xs text-slate-500">Trang chủ / <slot name="breadcrumb">Tổng quan</slot></div>
          <div class="truncate text-sm font-semibold text-slate-950">Bảng điều khiển vận hành LMS</div>
        </div>
        <div class="relative ml-auto hidden xl:block">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input class="h-10 w-80 rounded-md border border-slate-300 bg-slate-50 pl-9 pr-3 text-sm outline-none focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-100" placeholder="Tìm kiếm toàn hệ thống" />
        </div>
        <select class="hidden h-10 rounded-md border border-slate-300 bg-white px-2 text-sm md:block"><option>VABIS LMS</option></select>
        <select class="hidden h-10 rounded-md border border-slate-300 bg-white px-2 text-sm xl:block"><option>Vũng Tàu</option><option>Online Campus</option></select>
        <button class="hidden h-10 w-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 sm:grid" title="Thông báo">
          <Bell class="h-4 w-4" />
        </button>
        <button
          class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md border border-cyan-200 bg-cyan-50 px-3 text-sm font-semibold text-cyan-800 hover:bg-cyan-100"
          :title="editTarget.label"
          @click="navigateEdit"
        >
          <PenTool class="h-4 w-4" />
          <span class="hidden sm:inline">{{ editTarget.label }}</span>
          <span class="sm:hidden">Sửa</span>
        </button>
        <div class="hidden items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 md:flex">
          <div class="grid h-8 w-8 place-items-center rounded-md bg-cyan-100 text-cyan-800">
            <UserCheck class="h-4 w-4" />
          </div>
          <div class="text-right text-xs leading-tight">
          <div class="font-semibold text-slate-700">{{ sessionUser?.full_name || 'Demo Admin' }}</div>
          <div class="text-slate-500">{{ sessionUser?.email }}</div>
          </div>
        </div>
        <button class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-slate-950 px-3 text-sm font-semibold text-white hover:bg-slate-800" @click="$emit('logout')">
          <LogOut class="h-4 w-4" />
          <span class="hidden sm:inline">Đăng xuất</span>
        </button>
      </header>
      <main class="mx-auto min-w-0 max-w-[1600px] overflow-x-hidden p-3 sm:p-5">
        <slot />
      </main>
    </div>

    <div v-if="rowDetailOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="closeRowDetail">
      <section class="max-h-[88vh] w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl shadow-slate-950/30">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div class="min-w-0">
            <div class="text-xs font-semibold uppercase text-slate-500">Chi tiết bản ghi</div>
            <h2 class="mt-1 truncate text-lg font-bold text-slate-950">{{ rowDetail.title }}</h2>
          <div v-if="rowDetail.id" class="mt-1 font-mono text-xs text-slate-500">ID: {{ rowDetail.id }}</div>
          </div>
          <div class="flex shrink-0 gap-2">
            <button class="rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-800 hover:bg-cyan-100" @click="navigateEdit">Sửa</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeRowDetail">Đóng</button>
          </div>
        </header>

        <div class="max-h-[68vh] overflow-auto p-5">
          <dl class="grid gap-3 md:grid-cols-2">
            <div v-for="field in rowDetail.fields" :key="field.label" class="rounded-md border border-slate-200 bg-slate-50 p-3">
              <dt class="text-xs font-semibold uppercase text-slate-500">{{ field.label }}</dt>
              <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-900">{{ field.value }}</dd>
            </div>
          </dl>
        </div>
      </section>
    </div>

    <div v-if="editDrawerOpen" class="fixed inset-0 z-[60] flex justify-end bg-slate-950/45 backdrop-blur-sm" @click.self="closeGlobalEdit">
      <section class="flex h-full w-full max-w-xl flex-col bg-white shadow-2xl shadow-slate-950/30">
        <header class="border-b border-slate-200 px-5 py-4">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="text-xs font-semibold uppercase text-cyan-700">Chế độ sửa dùng chung</div>
              <h2 class="mt-1 truncate text-lg font-bold text-slate-950">{{ editTarget.label }}</h2>
              <p class="mt-1 text-sm text-slate-500">{{ currentPath }}</p>
            </div>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeGlobalEdit">Đóng</button>
          </div>
          <div v-if="editNotice" class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ editNotice }}</div>
        </header>

        <div class="min-h-0 flex-1 overflow-auto p-5">
          <div class="space-y-4">
            <label class="block">
              <span class="text-xs font-semibold uppercase text-slate-500">Tiêu đề chỉnh sửa</span>
              <input v-model="editDraft.title" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100" />
            </label>
            <label class="block">
              <span class="text-xs font-semibold uppercase text-slate-500">Trạng thái</span>
              <select v-model="editDraft.status" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100">
                <option value="draft">Draft</option>
                <option value="ready">Ready</option>
                <option value="review">Review</option>
                <option value="approved">Approved</option>
                <option value="published">Published</option>
              </select>
            </label>
            <label class="block">
              <span class="text-xs font-semibold uppercase text-slate-500">Ghi chú chỉnh sửa</span>
              <textarea v-model="editDraft.note" rows="5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100" placeholder="Nhập nội dung cần sửa, lý do hoặc chỉ dẫn cho module này."></textarea>
            </label>

            <div class="rounded-md border border-slate-200">
              <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase text-slate-500">Dữ liệu liên quan</div>
              <div class="max-h-80 overflow-auto divide-y divide-slate-100">
                <div v-for="field in editDraft.fields" :key="field.label" class="grid gap-1 px-3 py-2 text-sm">
                  <span class="text-xs font-semibold uppercase text-slate-500">{{ field.label }}</span>
                  <span class="break-words text-slate-900">{{ field.value }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-5 py-4">
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeGlobalEdit">Hủy</button>
          <button class="rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-800" @click="saveGlobalEdit">Lưu nháp sửa</button>
        </footer>
      </section>
    </div>
  </div>
</template>
