<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { AI_HUB_MENU_LINKS, AI_HUB_TEXT } from '@/config/aiHubConfig'
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
  Gauge,
  Home,
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
  Sparkles,
  Smartphone,
  TestTube2,
  UploadCloud,
  UserCheck,
  Users,
  Video,
  WalletCards,
  Wifi,
  WifiOff,
  Workflow,
  X,
} from '@lucide/vue'

const currentPath = window.location.pathname
const props = defineProps({
  sessionUser: { type: Object, default: null },
})
defineEmits(['logout'])

const rowDetailOpen = ref(false)
const rowDetail = ref({ title: '', id: '', fields: [] })
const mobileMenuOpen = ref(false)
const desktopMenuOpen = ref(localStorage.getItem('eralms.desktopMenuOpen') !== 'false')
const commandOpen = ref(false)
const commandSearch = ref('')
const recentRoutes = ref(JSON.parse(localStorage.getItem('eralms.recentRoutes') || '[]'))
const shellDensity = ref(localStorage.getItem('eralms.shellDensity') || 'comfortable')
const isOnline = ref(navigator.onLine)
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
  ['/admin/lms/data-integrity', DatabaseZap],
  ['/admin/api-ops/mappings', Workflow],
  ['/admin/api-ops/webhooks', Cable],
  ['/admin/api-ops', DatabaseZap],
  ['/dashboards', BarChart3],
  ['/analytics', BarChart3],
  ['/reports', FileChartColumn],
  ['/ai', Bot],
  ['/mobile', Smartphone],
  ['/student/tasks', ClipboardCheck],
  ['/student/journey', Route],
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
  ['/admin/lms/action-check', '/admin/lms/action-check', 'Sửa hành động'],
  ['/admin/api-ops/registry', '/admin/api-ops/registry', 'Sửa API registry'],
  ['/admin/api-ops/requests', '/admin/api-ops/requests', 'Sửa log API'],
  ['/admin/api-ops/events', '/admin/api-ops/events', 'Sửa event bus'],
  ['/admin/api-ops/webhooks', '/admin/api-ops/webhooks', 'Sửa webhook'],
  ['/admin/api-ops/mappings', '/admin/api-ops/mappings', 'Sửa ánh xạ API'],
  ['/admin/api-ops/entity-mappings', '/admin/api-ops/entity-mappings', 'Sửa mapping entity'],
  ['/admin/api-ops/sync-jobs', '/admin/api-ops/sync-jobs', 'Sửa sync job'],
  ['/admin/api-ops/health', '/admin/api-ops/health', 'Sửa health API'],
  ['/admin/api-ops/console', '/admin/api-ops/console', 'Sửa console API'],
  ['/admin/api-ops/contracts', '/admin/api-ops/contracts', 'Sửa contract API'],
  ['/admin/api-ops', '/admin/api-ops', 'Sửa API Ops'],
  ['/dashboards', '/dashboards/executive', 'Sửa dashboard'],
  ['/analytics', '/analytics', 'Sửa phân tích học tập'],
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
  ['/videos/analytics', '/videos/analytics', 'Sửa phân tích video'],
  ['/videos/lesson', '/videos/lesson', 'Sửa video bài học'],
  ['/videos', '/videos', 'Sửa video'],
  ['/assignments/deadlines', '/assignments/deadlines', 'Sửa mốc thời hạn bài tập'],
  ['/assignments/grading', '/assignments/grading', 'Sửa chấm bài'],
  ['/assignments/submission', '/assignments/submission', 'Sửa bài nộp'],
  ['/assignments', '/assignments', 'Sửa bài tập'],
  ['/gradebook/builder', '/gradebook/builder', 'Sửa sổ điểm'],
  ['/gradebook/approval', '/gradebook/approval', 'Sửa duyệt điểm'],
  ['/gradebook/student', '/gradebook/student', 'Sửa điểm cá nhân'],
  ['/gradebook', '/gradebook/builder', 'Sửa sổ điểm'],
  ['/attendance/live', '/attendance/live', 'Sửa phiên trực tiếp'],
  ['/attendance/checkin', '/attendance/checkin', 'Sửa điểm danh trực tuyến'],
  ['/attendance/teacher', '/attendance/teacher', 'Sửa điểm danh'],
  ['/attendance/eligibility', '/attendance/eligibility', 'Sửa điều kiện dự thi'],
  ['/attendance', '/attendance', 'Sửa điểm danh'],
  ['/surveys/builder', '/surveys/builder', 'Sửa khảo sát'],
  ['/surveys', '/surveys/builder', 'Sửa khảo sát'],
  ['/community', '/community', 'Sửa cộng đồng'],
  ['/career/public', '/career/public', 'Sửa hồ sơ công khai'],
  ['/career/employer', '/career/employer', 'Sửa góc nhà tuyển dụng'],
  ['/career', '/career', 'Sửa hồ sơ nghề nghiệp'],
  ['/credentials/certificates', '/credentials/certificates', 'Sửa chứng chỉ'],
  ['/credentials/wallet', '/credentials/wallet', 'Sửa ví chứng chỉ'],
  ['/credentials/verify', '/credentials/verify', 'Sửa xác minh'],
  ['/credentials', '/credentials/certificates', 'Sửa chứng chỉ'],
  ['/obe/outcome-matrix', '/obe/outcome-matrix', 'Sửa ma trận OBE'],
  ['/obe/competency-framework', '/obe/competency-framework', 'Sửa năng lực'],
  ['/obe/coverage', '/obe/coverage', 'Sửa phạm vi OBE'],
  ['/obe/achievement', '/obe/achievement', 'Sửa kết quả đạt chuẩn'],
  ['/obe/accreditation', '/obe/accreditation', 'Sửa báo cáo kiểm định'],
  ['/obe', '/obe', 'Sửa OBE'],
  ['/standards/xapi', '/standards/xapi', 'Sửa xAPI'],
  ['/standards/tools', '/standards/tools', 'Sửa công cụ'],
  ['/standards/lti', '/standards/lti', 'Sửa LTI'],
  ['/standards/scorm', '/standards/scorm', 'Sửa SCORM'],
  ['/standards', '/standards/tools', 'Sửa chuẩn học liệu'],
  ['/sis/mapping', '/sis/mapping', 'Sửa ánh xạ'],
  ['/sis/sync-jobs', '/sis/sync-jobs', 'Sửa công việc đồng bộ'],
  ['/sis/events', '/sis/events', 'Sửa sự kiện tích hợp'],
  ['/sis/systems', '/sis/systems', 'Sửa hệ thống SIS'],
  ['/sis', '/sis/mapping', 'Sửa SIS'],
  ['/settings/tenants', '/settings/tenants', 'Sửa đơn vị'],
  ['/settings/campuses', '/settings/campuses', 'Sửa cơ sở'],
  ['/settings/academic-units', '/settings/academic-units', 'Sửa đơn vị'],
  ['/settings/roles', '/settings/roles', 'Sửa vai trò/phân quyền'],
  ['/settings/white-label', '/settings/white-label', 'Sửa nhãn giao diện'],
  ['/settings/audit-logs', '/settings/audit-logs', 'Sửa nhật ký kiểm tra'],
  ['/settings', '/settings', 'Sửa cấu hình'],
  ['/security', '/security', 'Sửa bảo mật'],
  ['/plugins', '/plugins', 'Sửa plugin'],
  ['/backup', '/backup', 'Sửa sao lưu'],
  ['/uat', '/uat', 'Sửa UAT'],
  ['/ai', '/ai', 'Sửa học liệu AI'],
  ['/mobile', '/mobile', 'Sửa ứng dụng di động'],
  ['/', '/', 'Sửa dashboard'],
]

const localeText = computed(() => {
  const requested = new URLSearchParams(window.location.search).get('lang')?.toLowerCase()
  if (requested === 'en' || requested === 'vi') return AI_HUB_TEXT[requested]
  return AI_HUB_TEXT.vi
})

const menuGroups = computed(() => AI_HUB_MENU_LINKS.map((link) => ({
  route: link.href,
  label: localeText.value.sections[link.sectionKey]?.title || link.sectionKey,
})))

const menuGroupsBase = [
  {
    title: 'Điều hành',
    items: [
      { label: 'Tổng quan', route: '/' },
      { label: 'Dashboard BGH', route: '/dashboards/executive' },
      { label: 'Dashboard đào tạo', route: '/dashboards/academic' },
      { label: 'Dashboard rủi ro', route: '/dashboards/risk' },
      { label: 'Dashboard SIS', route: '/dashboards/integration' },
      { label: 'Kiểm tra hệ thống', route: '/admin/lms/system-check' },
      { label: 'Kiểm tra hành động', route: '/admin/lms/action-check' },
      { label: 'Phân tích học tập', route: '/analytics' },
      { label: 'Báo cáo', route: '/reports' },
      { label: 'Học tập di động', route: '/mobile' },
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
      { label: 'Phân tích video', route: '/videos/analytics' },
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
      { label: 'Phiên trực tiếp', route: '/attendance/live' },
      { label: 'Điểm danh trực tuyến', route: '/attendance/checkin' },
      { label: 'Bảng điểm danh GV', route: '/attendance/teacher' },
      { label: 'Điều kiện dự thi', route: '/attendance/eligibility' },
      { label: 'Cộng đồng học tập', route: '/community' },
      { label: 'Khảo sát & QA', route: '/surveys' },
      { label: 'Thiết kế khảo sát', route: '/surveys/builder' },
      { label: 'Hồ sơ nghề nghiệp', route: '/career' },
      { label: 'IDP cá nhân', route: '/career/digital-twin' },
      { label: 'Hồ sơ công khai', route: '/career/public' },
      { label: 'Góc nhà tuyển dụng', route: '/career/employer' },
    ],
  },
  {
    title: 'Chứng chỉ & chuẩn',
    items: [
      { label: 'Chứng chỉ số', route: '/credentials' },
      { label: 'Tạo chứng chỉ', route: '/credentials/certificates' },
      { label: 'Ví chứng chỉ', route: '/credentials/wallet' },
      { label: 'Cổng xác minh', route: '/credentials/verify' },
      { label: 'Kết quả đầu ra', route: '/obe' },
      { label: 'Ma trận kết quả đầu ra', route: '/obe/outcome-matrix' },
      { label: 'Khung năng lực', route: '/obe/competency-framework' },
      { label: 'Phạm vi OBE', route: '/obe/coverage' },
      { label: 'Đạt chuẩn', route: '/obe/achievement' },
      { label: 'Báo cáo kiểm định', route: '/obe/accreditation' },
      { label: 'Quản lý SCORM', route: '/standards/scorm' },
      { label: 'Khám phá xAPI', route: '/standards/xapi' },
      { label: 'Đăng ký LTI', route: '/standards/lti' },
      { label: 'Công cụ bên ngoài', route: '/standards/tools' },
    ],
  },
  {
    title: 'Hệ thống',
    items: [
      { label: 'Đồng bộ SIS', route: '/sis' },
      { label: 'API Operations Center', route: '/admin/api-ops' },
      { label: 'API Registry', route: '/admin/api-ops/registry' },
      { label: 'API Gateway Logs', route: '/admin/api-ops/requests' },
      { label: 'Event Bus Center', route: '/admin/api-ops/events' },
      { label: 'Webhook Center', route: '/admin/api-ops/webhooks' },
      { label: 'Data Mapping Center', route: '/admin/api-ops/mappings' },
      { label: 'Entity Mapping Center', route: '/admin/api-ops/entity-mappings' },
      { label: 'Sync Job Center', route: '/admin/api-ops/sync-jobs' },
      { label: 'API Health Monitor', route: '/admin/api-ops/health' },
      { label: 'API Test Console', route: '/admin/api-ops/console' },
      { label: 'Data Contract Manager', route: '/admin/api-ops/contracts' },
      { label: 'Ánh xạ SIS', route: '/sis/mapping' },
      { label: 'Công việc đồng bộ', route: '/sis/sync-jobs' },
      { label: 'Sự kiện tích hợp', route: '/sis/events' },
      { label: 'Cấu hình hệ thống', route: '/sis/systems' },
      { label: 'Cấu hình', route: '/settings' },
      { label: 'Đơn vị', route: '/settings/tenants' },
      { label: 'Cơ sở', route: '/settings/campuses' },
      { label: 'Khoa/Bộ môn', route: '/settings/academic-units' },
      { label: 'Vai trò/Phân quyền', route: '/settings/roles' },
      { label: 'Nhãn giao diện', route: '/settings/white-label' },
      { label: 'Nhật ký kiểm tra', route: '/settings/audit-logs' },
      { label: 'Bảo mật', route: '/security' },
      { label: 'Plugin', route: '/plugins' },
      { label: 'Sao lưu', route: '/backup' },
      { label: 'UAT', route: '/uat' },
    ],
  },
]

const learnerMenuGroups = [
  {
    title: 'Hôm nay',
    items: [
      { label: 'AI trợ học', route: '/ai' },
      { label: 'Home', route: '/' },
      { label: 'Task Center', route: '/student/tasks' },
    ],
  },
  {
    title: 'Học',
    items: [
      { label: 'My Learning', route: '/courses' },
      { label: 'Learning Journey', route: '/student/journey' },
      { label: 'Vào trang học', route: '/courses/learn' },
    ],
  },
  {
    title: 'Kết quả',
    items: [
      { label: 'Điểm của tôi', route: '/gradebook/student' },
      { label: 'Điều kiện dự thi', route: '/attendance/eligibility' },
      { label: 'Kết quả thi', route: '/exams/results' },
    ],
  },
  {
    title: 'Hồ sơ',
    items: [
      { label: 'Hồ sơ nghề nghiệp', route: '/career' },
      { label: 'IDP cá nhân', route: '/career/digital-twin' },
      { label: 'Ví chứng chỉ', route: '/credentials/wallet' },
    ],
  },
]

const isLearner = computed(() => props.sessionUser?.user_type === 'student')
const learnerBottomNav = [
  { label: 'Home', route: '/', icon: Home },
  { label: 'Học', route: '/courses', icon: GraduationCap },
  { label: 'Task', route: '/student/tasks', icon: ClipboardCheck },
  { label: 'Điểm', route: '/gradebook/student', icon: FileChartColumn },
  { label: 'Hồ sơ', route: '/career', icon: UserCheck },
]
const operatorBottomNav = [
  { label: 'AI', route: '/ai', icon: Bot },
  { label: 'Home', route: '/', icon: LayoutDashboard },
  { label: 'Studio', route: '/courses/studio', icon: PenTool },
  { label: 'Kho', route: '/repository', icon: FolderOpen },
  { label: 'Check', route: '/admin/lms/data-integrity', icon: DatabaseZap },
]
const visibleMenuGroups = computed(() => {
  const aiHubGroup = {
    title: localeText.value.sidebar?.groupTitle || 'AI Hub',
    items: [
      { label: 'AI Study Companion', route: '/ai' },
      ...menuGroups.value,
    ],
  }

  return isLearner.value ? learnerMenuGroups : [aiHubGroup, ...menuGroupsBase]
})
const shellTitle = computed(() => isLearner.value ? 'EraLMS Learner' : 'EraLMS Enterprise')
const shellSubtitle = computed(() => isLearner.value ? 'Không gian học tập cá nhân' : 'VABIS LMS Operations')
const pageTitle = computed(() => isLearner.value ? 'Bảng điều khiển người học' : 'Bảng điều khiển vận hành LMS')
const bottomNavItems = computed(() => isLearner.value ? learnerBottomNav : operatorBottomNav)
const densityCompact = computed(() => shellDensity.value === 'compact')
const commandItems = computed(() => {
  const menuItems = visibleMenuGroups.value.flatMap((group) => group.items.map((item) => ({
    ...item,
    group: group.title,
    icon: iconFor(item.route),
    tone: 'menu',
  })))
  const recentItems = recentRoutes.value.map((item) => ({
    ...item,
    group: 'Gần đây',
    icon: iconFor(item.route),
    tone: 'recent',
  }))
  const editItem = !isLearner.value ? [{
    label: editTarget.value.label,
    route: editTarget.value.route,
    group: 'Thao tác',
    icon: PenTool,
    tone: 'action',
    action: navigateEdit,
  }] : []

  return [...editItem, ...recentItems, ...menuItems]
})
const filteredCommandItems = computed(() => {
  const keyword = commandSearch.value.trim().toLowerCase()
  const unique = new Map()
  for (const item of commandItems.value) {
    const key = `${item.group}:${item.route}:${item.label}`
    if (!unique.has(key)) unique.set(key, item)
  }
  const items = [...unique.values()]
  if (!keyword) return items.slice(0, 12)

  return items
    .filter((item) => `${item.label} ${item.route} ${item.group}`.toLowerCase().includes(keyword))
    .slice(0, 20)
})

const activeRoute = computed(() => {
  const routes = visibleMenuGroups.value
    .flatMap((group) => group.items.map((item) => {
      const [base] = item.route.split('#')
      return base.split('?')[0]
    }))
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
  const [base] = route.split('#')
  const target = base.split('?')[0]
  return activeRoute.value === target
}

function iconFor(route) {
  const match = routeIcons
    .filter(([prefix]) => prefix === '/' ? route === '/' : route.startsWith(prefix))
    .sort((a, b) => b[0].length - a[0].length)[0]

  return match?.[1] || defaultIcon
}

function navigate(route) {
  mobileMenuOpen.value = false
  commandOpen.value = false
  pushRecentRoute(route)
  const [base, hash] = route.split('#')
  const withVersion = base.includes('v=') ? base : `${base}${base.includes('?') ? '&' : '?'}v=${Date.now()}`
  window.location.href = `${withVersion}${hash ? `#${hash}` : ''}`
}

function openCommand() {
  commandSearch.value = ''
  commandOpen.value = true
}

function runCommand(item) {
  if (item.action) {
    commandOpen.value = false
    item.action()
    return
  }

  navigate(item.route)
}

function pushRecentRoute(route = currentPath) {
  const item = visibleMenuGroups.value
    .flatMap((group) => group.items.map((menuItem) => ({ ...menuItem, group: group.title })))
    .find((menuItem) => {
      const [base] = menuItem.route.split('#')
      const target = base.split('?')[0]
      return target === route || target === currentPath
    })

  if (!item) return

  recentRoutes.value = [
    { label: item.label, route: item.route },
    ...recentRoutes.value.filter((recent) => recent.route !== item.route),
  ].slice(0, 6)
  localStorage.setItem('eralms.recentRoutes', JSON.stringify(recentRoutes.value))
}

function toggleDensity() {
  shellDensity.value = densityCompact.value ? 'comfortable' : 'compact'
  localStorage.setItem('eralms.shellDensity', shellDensity.value)
}

function handleShellKeydown(event) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    openCommand()
    return
  }

  if (event.key === 'Escape') {
    commandOpen.value = false
    mobileMenuOpen.value = false
  }
}

function updateOnlineState() {
  isOnline.value = navigator.onLine
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
  window.addEventListener('keydown', handleShellKeydown)
  window.addEventListener('online', updateOnlineState)
  window.addEventListener('offline', updateOnlineState)
  pushRecentRoute(currentPath)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleTableClick)
  window.removeEventListener('keydown', handleShellKeydown)
  window.removeEventListener('online', updateOnlineState)
  window.removeEventListener('offline', updateOnlineState)
})
</script>

<template>
  <div class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900" :class="{ 'eralms-density-compact': densityCompact }">
    <aside v-if="desktopMenuOpen" class="fixed inset-y-0 left-0 hidden w-76 overflow-y-auto border-r border-slate-950 bg-slate-950 text-slate-100 shadow-2xl shadow-slate-950/30 lg:block">
      <div class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-4 py-4 backdrop-blur">
        <div class="flex items-center gap-3">
          <div class="grid h-10 w-10 place-items-center rounded-md bg-cyan-500 text-white shadow-lg shadow-cyan-950/40">
            <BookOpen class="h-5 w-5" />
          </div>
          <div class="min-w-0">
            <div class="text-sm font-bold tracking-wide text-white">{{ shellTitle }}</div>
            <div class="text-xs text-slate-400">{{ shellSubtitle }}</div>
          </div>
          <button class="ml-auto grid h-9 w-9 place-items-center rounded-md border border-white/10 text-slate-300 hover:bg-white/10" title="Đóng menu chính" @click="toggleDesktopMenu">
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>
      <nav class="space-y-5 px-3 py-4 text-sm">
        <section v-for="group in visibleMenuGroups" :key="group.title">
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
              <div class="text-sm font-bold tracking-wide text-white">{{ shellTitle }}</div>
              <div class="text-xs text-slate-400">{{ shellSubtitle }}</div>
            </div>
          </div>
          <button class="grid h-9 w-9 place-items-center rounded-md border border-white/10 text-slate-300" @click="mobileMenuOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>
        <nav class="space-y-5 px-3 py-4 text-sm">
          <section v-for="group in visibleMenuGroups" :key="group.title">
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
      <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white/90 px-5 shadow-sm shadow-slate-200/60 backdrop-blur" :class="densityCompact ? 'h-14' : 'h-16'">
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
          <div class="truncate text-sm font-semibold text-slate-950">{{ pageTitle }}</div>
        </div>
        <button class="relative ml-auto hidden h-10 w-80 items-center rounded-md border border-slate-300 bg-slate-50 pl-9 pr-3 text-left text-sm text-slate-500 outline-none hover:border-cyan-300 hover:bg-white focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-100 xl:flex" @click="openCommand">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <span class="truncate">Tìm kiếm toàn hệ thống</span>
        </button>
        <select v-if="!isLearner" class="hidden h-10 rounded-md border border-slate-300 bg-white px-2 text-sm md:block"><option>VABIS LMS</option></select>
        <select v-if="!isLearner" class="hidden h-10 rounded-md border border-slate-300 bg-white px-2 text-sm xl:block"><option>Vũng Tàu</option><option>Cơ sở trực tuyến</option></select>
        <button class="hidden h-10 w-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 md:grid" :title="densityCompact ? 'Mật độ rộng' : 'Mật độ gọn'" @click="toggleDensity">
          <Gauge class="h-4 w-4" />
        </button>
        <div class="hidden h-10 items-center gap-2 rounded-md border px-3 text-xs font-semibold md:flex" :class="isOnline ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'">
          <Wifi v-if="isOnline" class="h-4 w-4" />
          <WifiOff v-else class="h-4 w-4" />
          <span>{{ isOnline ? 'Online' : 'Offline' }}</span>
        </div>
        <button class="hidden h-10 w-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 sm:grid" title="Thông báo">
          <Bell class="h-4 w-4" />
        </button>
        <button
          v-if="!isLearner"
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
          <div class="font-semibold text-slate-700">{{ sessionUser?.full_name || 'Quản trị demo' }}</div>
          <div class="text-slate-500">{{ sessionUser?.email }}</div>
          </div>
        </div>
        <button class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-slate-950 px-3 text-sm font-semibold text-white hover:bg-slate-800" @click="$emit('logout')">
          <LogOut class="h-4 w-4" />
          <span class="hidden sm:inline">Đăng xuất</span>
        </button>
      </header>
      <main class="mx-auto min-w-0 max-w-[1600px] overflow-x-hidden p-3 sm:p-5" :class="{ 'pb-24': true, 'sm:p-4': densityCompact }">
        <slot />
      </main>
      <nav class="fixed inset-x-3 bottom-3 z-40 grid grid-cols-5 rounded-md border border-slate-200 bg-white/95 p-1 shadow-2xl shadow-slate-950/20 backdrop-blur lg:hidden">
        <a
          v-for="item in bottomNavItems"
          :key="item.route"
          :href="item.route"
          class="grid min-w-0 place-items-center gap-1 rounded-md px-1 py-2 text-[11px] font-semibold text-slate-500"
          :class="{ 'bg-slate-950 text-white': isActive(item.route) }"
          @click.prevent="navigate(item.route)"
        >
          <component :is="item.icon" class="h-4 w-4" />
          <span class="truncate">{{ item.label }}</span>
        </a>
      </nav>
    </div>

    <div v-if="commandOpen" class="fixed inset-0 z-[70] bg-slate-950/45 p-3 backdrop-blur-sm sm:p-6" @click.self="commandOpen = false">
      <section class="mx-auto mt-10 flex max-h-[82vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl shadow-slate-950/30 sm:mt-16">
        <header class="border-b border-slate-200 p-3">
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="commandSearch"
              autofocus
              class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 pl-9 pr-10 text-sm outline-none focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-100"
              placeholder="Tìm module hoặc thao tác"
            />
            <button class="absolute right-1 top-1 grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="commandOpen = false">
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="min-h-0 flex-1 overflow-auto p-2">
          <button
            v-for="item in filteredCommandItems"
            :key="`${item.group}-${item.route}-${item.label}`"
            class="grid w-full grid-cols-[40px_minmax(0,1fr)_auto] items-center gap-3 rounded-md px-3 py-2.5 text-left hover:bg-slate-50"
            @click="runCommand(item)"
          >
            <span class="grid h-10 w-10 place-items-center rounded-md" :class="item.tone === 'action' ? 'bg-cyan-50 text-cyan-700' : item.tone === 'recent' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700'">
              <component :is="item.icon || Sparkles" class="h-4 w-4" />
            </span>
            <span class="min-w-0">
              <span class="block truncate text-sm font-bold text-slate-950">{{ item.label }}</span>
              <span class="mt-0.5 block truncate text-xs text-slate-500">{{ item.route }}</span>
            </span>
            <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500">{{ item.group }}</span>
          </button>
          <div v-if="filteredCommandItems.length === 0" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
            Không có kết quả phù hợp.
          </div>
        </div>
      </section>
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
              <option value="draft">Nháp</option>
              <option value="ready">Sẵn sàng</option>
              <option value="review">Đang duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="published">Đã phát hành</option>
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
