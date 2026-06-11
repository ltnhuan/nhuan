<script setup>
import { computed, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import {
  AlignCenter,
  AlignLeft,
  AlignRight,
  BadgeCheck,
  Copy,
  Maximize2,
  Minimize2,
  Eye,
  FileText,
  Image,
  Layers3,
  MousePointer2,
  QrCode,
  Save,
  ShieldCheck,
  Signature,
  Sparkles,
  Trash2,
  Type,
} from '@lucide/vue'

const dynamicFields = [
  { key: 'learner_name', label: 'Họ và tên người học', group: 'Người học', sample: 'Nguyễn Minh Anh' },
  { key: 'learner_code', label: 'Mã người học', group: 'Người học', sample: 'SV202600128' },
  { key: 'learner_email', label: 'Email người học', group: 'Người học', sample: 'minhanh@example.edu.vn' },
  { key: 'learner_birth_date', label: 'Ngày sinh', group: 'Người học', sample: '12/08/2002' },
  { key: 'learner_identity', label: 'Số định danh', group: 'Người học', sample: '079202600128' },
  { key: 'certificate_title', label: 'Tên chứng chỉ', group: 'Chứng chỉ', sample: 'Chứng nhận hoàn thành khóa học' },
  { key: 'certificate_type', label: 'Loại chứng chỉ', group: 'Chứng chỉ', sample: 'Chứng chỉ hoàn thành' },
  { key: 'credential_id', label: 'Mã chứng chỉ', group: 'Chứng chỉ', sample: 'ERA-2026-000128' },
  { key: 'credential_level', label: 'Cấp độ chứng chỉ', group: 'Chứng chỉ', sample: 'Nâng cao' },
  { key: 'course_title', label: 'Tên khóa học', group: 'Khóa học', sample: 'Ứng dụng AI trong giáo dục' },
  { key: 'course_code', label: 'Mã khóa học', group: 'Khóa học', sample: 'AIEDU-2026' },
  { key: 'program_name', label: 'Chương trình đào tạo', group: 'Khóa học', sample: 'Bồi dưỡng nghiệp vụ số' },
  { key: 'learning_hours', label: 'Số giờ học', group: 'Kết quả', sample: '45 giờ' },
  { key: 'final_score', label: 'Điểm tổng kết', group: 'Kết quả', sample: '92/100' },
  { key: 'grade_label', label: 'Xếp loại', group: 'Kết quả', sample: 'Xuất sắc' },
  { key: 'competency_name', label: 'Năng lực đạt được', group: 'Kết quả', sample: 'Thiết kế học liệu AI' },
  { key: 'issued_at', label: 'Ngày cấp', group: 'Phát hành', sample: '06/06/2026' },
  { key: 'expires_at', label: 'Ngày hết hạn', group: 'Phát hành', sample: '06/06/2029' },
  { key: 'issuer_name', label: 'Đơn vị cấp', group: 'Phát hành', sample: 'ERA LMS Academy' },
  { key: 'issuer_representative', label: 'Người đại diện', group: 'Phát hành', sample: 'TS. Trần Quốc Bảo' },
  { key: 'signature_title', label: 'Chức danh ký', group: 'Phát hành', sample: 'Giám đốc học thuật' },
  { key: 'verification_url', label: 'Đường dẫn xác minh', group: 'Xác minh', sample: 'https://eralms.edu.vn/verify/ERA-2026-000128' },
  { key: 'verification_hash', label: 'Mã hash xác minh', group: 'Xác minh', sample: '9F4C-22A8-71D0' },
  { key: 'qr_payload', label: 'Dữ liệu QR', group: 'Xác minh', sample: 'verify:ERA-2026-000128' },
]

const templates = [
  {
    id: 'global-minimal',
    name: 'Tối giản quốc tế',
    accent: '#1d4ed8',
    background: '#f8fafc',
    border: '#1e3a8a',
    description: 'Gọn gàng, chuẩn doanh nghiệp, an toàn khi in',
  },
  {
    id: 'academic-gold',
    name: 'Học thuật trang trọng',
    accent: '#b45309',
    background: '#fffaf0',
    border: '#92400e',
    description: 'Phù hợp trường đại học và viện đào tạo',
  },
  {
    id: 'skills-modern',
    name: 'Kỹ năng hiện đại',
    accent: '#0f766e',
    background: '#f0fdfa',
    border: '#0f766e',
    description: 'Phù hợp micro-credential và CPD',
  },
]

const template = ref(templates[0])
const selectedId = ref('title')
const canvasRef = ref(null)
const addImageInputRef = ref(null)
const replaceImageInputRef = ref(null)
const dragState = ref(null)
const previewMode = ref(false)
const printPreviewOpen = ref(false)
const fullScreen = ref(false)
const leftPanelOpen = ref(true)
const rightPanelOpen = ref(true)
const layersPanelOpen = ref(true)
const snapToGrid = ref(true)
const templateName = ref('Mẫu chứng chỉ mới')
const savedTemplates = ref(JSON.parse(localStorage.getItem('eralms.certificate.templates') || '[]'))
const printMode = ref('full')
const printModes = [
  { id: 'full', name: 'In bằng đầy đủ', description: 'In cả nền màu, khung và toàn bộ thành phần.' },
  { id: 'frame', name: 'In khung', description: 'Giữ khung và nội dung, nền chuyển sang trắng.' },
  { id: 'no-background', name: 'In không nền', description: 'Bỏ nền và họa tiết phụ, chỉ giữ nội dung chính.' },
  { id: 'content-only', name: 'In nội dung', description: 'Không nền, không khung, phù hợp in lên phôi có sẵn.' },
]
const settings = ref({
  'Bắt buộc có QR': true,
  'Xác minh bằng hash': true,
  'Đa ngôn ngữ': true,
  'Sẵn sàng Open Badge': true,
})

const elements = ref([
  { id: 'brand', type: 'text', label: 'Đơn vị cấp', text: 'ERA LMS ACADEMY', x: 34, y: 10, w: 32, h: 5, size: 14, color: '#334155', align: 'center', weight: '700' },
  { id: 'title', type: 'text', label: 'Tiêu đề', text: 'CHỨNG NHẬN HOÀN THÀNH', x: 20, y: 22, w: 60, h: 9, size: 28, color: '#0f172a', align: 'center', weight: '800' },
  { id: 'learner', type: 'field', field: 'learner_name', label: 'Họ và tên người học', x: 25, y: 43, w: 50, h: 9, size: 26, color: '#1d4ed8', align: 'center', weight: '700' },
  { id: 'course', type: 'field', field: 'course_title', label: 'Tên khóa học', x: 24, y: 56, w: 52, h: 7, size: 14, color: '#475569', align: 'center', weight: '500' },
  { id: 'signature', type: 'signature', label: 'Chữ ký', text: 'Giám đốc học thuật', x: 15, y: 75, w: 24, h: 10, size: 12, color: '#334155', align: 'center', weight: '500' },
  { id: 'qr', type: 'qr', label: 'QR xác minh', x: 78, y: 70, w: 12, h: 17, size: 10, color: '#0f172a', align: 'center', weight: '600' },
])

const selected = computed(() => elements.value.find((item) => item.id === selectedId.value))
const qrCount = computed(() => elements.value.filter((item) => item.type === 'qr').length)
const publishReady = computed(() => qrCount.value === 1 && elements.value.some((item) => item.field === 'learner_name'))
const dynamicFieldGroups = computed(() => [...new Set(dynamicFields.map((field) => field.group))].map((group) => ({
  group,
  fields: dynamicFields.filter((field) => field.group === group),
})))
const workspaceColumns = computed(() => {
  if (!leftPanelOpen.value && !rightPanelOpen.value) return 'xl:grid-cols-[minmax(0,1fr)]'
  if (!leftPanelOpen.value) return 'xl:grid-cols-[minmax(0,1fr)_330px]'
  if (!rightPanelOpen.value) return 'xl:grid-cols-[300px_minmax(0,1fr)]'
  return 'xl:grid-cols-[300px_minmax(0,1fr)_330px]'
})
const activePrintMode = computed(() => printModes.find((item) => item.id === printMode.value) || printModes[0])
const showCanvasBackground = computed(() => printMode.value === 'full')
const showCanvasFrame = computed(() => printMode.value === 'full' || printMode.value === 'frame')
const showCanvasGuides = computed(() => printMode.value !== 'content-only')
const canvasStyle = computed(() => ({
  backgroundColor: showCanvasBackground.value ? template.value.background : '#ffffff',
  borderColor: showCanvasFrame.value ? template.value.border : '#e2e8f0',
}))

function normalizeElement(item) {
  return {
    id: item.id || `${item.type || 'layer'}-${Date.now()}`,
    type: item.type || 'text',
    label: item.label || 'Lớp thiết kế',
    text: item.text || '',
    field: item.field,
    src: item.src,
    fit: item.fit || 'contain',
    x: Number.isFinite(Number(item.x)) ? Number(item.x) : 20,
    y: Number.isFinite(Number(item.y)) ? Number(item.y) : 20,
    w: Number.isFinite(Number(item.w)) ? Number(item.w) : 30,
    h: Number.isFinite(Number(item.h)) ? Number(item.h) : 8,
    size: Number.isFinite(Number(item.size)) ? Number(item.size) : 14,
    color: item.color || '#0f172a',
    align: item.align || 'center',
    weight: item.weight || '600',
  }
}

function normalizeTemplate(item) {
  return {
    schemaVersion: 2,
    id: item.id || `tpl-${Date.now()}`,
    name: item.name || 'Mẫu chứng chỉ',
    accent: item.accent || '#1d4ed8',
    background: item.background || '#ffffff',
    border: item.border || '#1e3a8a',
    description: item.description || 'Mẫu chứng chỉ',
    printMode: item.printMode || 'full',
    elements: Array.isArray(item.elements) ? item.elements.map(normalizeElement) : null,
    settings: item.settings || {},
    savedAt: item.savedAt,
  }
}

function applyTemplate(item) {
  const normalized = normalizeTemplate(item)
  template.value = normalized
  printMode.value = normalized.printMode
  if (normalized.elements) {
    elements.value = JSON.parse(JSON.stringify(normalized.elements))
    settings.value = { ...settings.value, ...normalized.settings }
    templateName.value = normalized.name
  }
  selectedId.value = elements.value[0]?.id
}

function saveTemplate() {
  const id = `tpl-${Date.now()}`
  const record = normalizeTemplate({
    id,
    name: templateName.value.trim() || 'Mẫu chứng chỉ chưa đặt tên',
    accent: template.value.accent,
    background: template.value.background,
    border: template.value.border,
    description: 'Mẫu tùy chỉnh đã lưu',
    printMode: printMode.value,
    elements: JSON.parse(JSON.stringify(elements.value)),
    settings: { ...settings.value },
    savedAt: new Date().toLocaleString('vi-VN'),
  })
  savedTemplates.value = [record, ...savedTemplates.value.filter((item) => item.name !== record.name)]
  localStorage.setItem('eralms.certificate.templates', JSON.stringify(savedTemplates.value))
  template.value = record
}

function deleteSavedTemplate(id) {
  savedTemplates.value = savedTemplates.value.filter((item) => item.id !== id)
  localStorage.setItem('eralms.certificate.templates', JSON.stringify(savedTemplates.value))
}

function sampleFor(field) {
  return dynamicFields.find((item) => item.key === field)?.sample || `{{${field}}}`
}

function placeholderFor(field) {
  return `{{${field}}}`
}

function displayText(item) {
  if (item.type === 'field') return sampleFor(item.field)
  if (item.type === 'qr') return 'XÁC MINH'
  return item.text
}

function typeLabel(type) {
  return {
    text: 'Văn bản',
    field: 'Dữ liệu',
    qr: 'QR',
    signature: 'Chữ ký',
    image: 'Ảnh',
  }[type] || type
}

function printPreview() {
  window.print()
}

function triggerAddImage() {
  addImageInputRef.value?.click()
}

function triggerReplaceImage() {
  replaceImageInputRef.value?.click()
}

function readImageFile(file, callback) {
  if (!file || !file.type.startsWith('image/')) return
  const reader = new FileReader()
  reader.onload = () => callback(reader.result)
  reader.readAsDataURL(file)
}

function addImageFromFile(event) {
  const file = event.target.files?.[0]
  readImageFile(file, (src) => {
    addElement('image', { label: file.name || 'Ảnh tải lên', text: 'Ảnh', src })
  })
  event.target.value = ''
}

function replaceSelectedImage(event) {
  const file = event.target.files?.[0]
  readImageFile(file, (src) => {
    if (!selected.value) return
    selected.value.type = 'image'
    selected.value.src = src
    selected.value.text = file.name || 'Ảnh'
    selected.value.label = selected.value.label || 'Ảnh'
  })
  event.target.value = ''
}

function addElement(type, payload = {}) {
  const id = `${type}-${Date.now()}`
  const base = {
    id,
    type,
    label: payload.label || 'Lớp mới',
    text: payload.text || 'Nội dung mới',
    field: payload.field,
    src: payload.src,
    fit: payload.fit || 'contain',
    x: 38,
    y: 36,
    w: type === 'qr' ? 12 : type === 'image' ? 18 : 28,
    h: type === 'qr' ? 17 : type === 'image' ? 12 : 7,
    size: type === 'qr' ? 10 : 14,
    color: template.value.accent,
    align: 'center',
    weight: '600',
  }
  elements.value.push(base)
  selectedId.value = id
}

function duplicateSelected() {
  if (!selected.value) return
  const copy = { ...selected.value, id: `${selected.value.id}-copy-${Date.now()}`, x: selected.value.x + 3, y: selected.value.y + 3, label: `${selected.value.label} bản sao` }
  elements.value.push(copy)
  selectedId.value = copy.id
}

function removeSelected() {
  if (!selected.value) return
  elements.value = elements.value.filter((item) => item.id !== selected.value.id)
  selectedId.value = elements.value[0]?.id || ''
}

function pointerPosition(event) {
  const rect = canvasRef.value.getBoundingClientRect()
  return {
    x: ((event.clientX - rect.left) / rect.width) * 100,
    y: ((event.clientY - rect.top) / rect.height) * 100,
  }
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max)
}

function grid(value) {
  return snapToGrid.value ? Math.round(value) : Math.round(value * 10) / 10
}

function startDrag(event, item, mode = 'move') {
  if (previewMode.value) return
  selectedId.value = item.id
  event.currentTarget.setPointerCapture?.(event.pointerId)
  const point = pointerPosition(event)
  dragState.value = { id: item.id, mode, startX: point.x, startY: point.y, original: { ...item } }
}

function onPointerMove(event) {
  if (!dragState.value) return
  const item = elements.value.find((entry) => entry.id === dragState.value.id)
  if (!item) return
  const point = pointerPosition(event)
  const dx = point.x - dragState.value.startX
  const dy = point.y - dragState.value.startY

  if (dragState.value.mode === 'resize') {
    item.w = grid(clamp(dragState.value.original.w + dx, 6, 100 - item.x))
    item.h = grid(clamp(dragState.value.original.h + dy, 4, 100 - item.y))
    return
  }

  item.x = grid(clamp(dragState.value.original.x + dx, 0, 100 - item.w))
  item.y = grid(clamp(dragState.value.original.y + dy, 0, 100 - item.h))
}

function endDrag() {
  dragState.value = null
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Chứng chỉ số / Thiết kế chứng chỉ</template>

    <section
      class="mx-auto px-4 py-4 sm:px-6"
      :class="fullScreen ? 'fixed inset-0 z-50 max-w-none overflow-auto bg-slate-100' : 'max-w-[1540px]'"
    >
      <div class="sticky top-0 z-40 -mx-4 flex flex-col gap-3 border-b border-slate-200 bg-slate-100/95 px-4 pb-4 pt-2 backdrop-blur sm:-mx-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold uppercase text-blue-700">
            <BadgeCheck class="h-4 w-4" />
            Xưởng thiết kế chứng chỉ có xác minh
          </div>
          <h1 class="mt-1 text-2xl font-semibold text-slate-950">Thiết kế chứng chỉ</h1>
          <p class="mt-1 max-w-3xl text-sm text-slate-600">
            Thiết kế mẫu chứng chỉ theo chuẩn quốc tế: thư viện mẫu, trường dữ liệu động, quản lý lớp, QR xác minh, mã hash và xem trước bản in trước khi phát hành hàng loạt.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" @click="leftPanelOpen = !leftPanelOpen">
            {{ leftPanelOpen ? 'Ẩn công cụ' : 'Mở công cụ' }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" @click="rightPanelOpen = !rightPanelOpen">
            {{ rightPanelOpen ? 'Ẩn thuộc tính' : 'Mở thuộc tính' }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" @click="previewMode = !previewMode">
            <Eye class="h-4 w-4" /> {{ previewMode ? 'Chỉnh sửa' : 'Xem nhanh' }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" @click="printPreviewOpen = true">
            <FileText class="h-4 w-4" /> Xem trước bản in
          </button>
          <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" @click="fullScreen = !fullScreen">
            <Minimize2 v-if="fullScreen" class="h-4 w-4" />
            <Maximize2 v-else class="h-4 w-4" />
            {{ fullScreen ? 'Thu nhỏ' : 'Toàn màn hình' }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:bg-slate-400" :disabled="!publishReady">
            <Save class="h-4 w-4" /> Phát hành mẫu
          </button>
        </div>
      </div>

      <div class="mt-4 grid gap-4" :class="workspaceColumns">
        <aside v-if="leftPanelOpen" class="space-y-4">
          <div class="border bg-white p-4">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><Sparkles class="h-4 w-4 text-blue-700" /> Quản lý mẫu</h2>
            <label class="mt-3 block">
              <span class="text-xs font-semibold text-slate-500">Tên mẫu</span>
              <input v-model="templateName" class="mt-1 w-full rounded-md border px-3 py-2 text-sm" />
            </label>
            <button class="mt-3 flex w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="saveTemplate">
              <Save class="h-4 w-4" /> Lưu mẫu hiện tại
            </button>
            <h3 class="mt-4 text-xs font-bold uppercase text-slate-500">Mẫu hệ thống</h3>
            <div class="mt-3 space-y-2">
              <button
                v-for="item in templates"
                :key="item.id"
                class="w-full rounded-md border px-3 py-3 text-left text-sm"
                :class="template.id === item.id ? 'border-blue-600 bg-blue-50 text-blue-950' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                @click="applyTemplate(item)"
              >
                <span class="flex items-center justify-between font-semibold">
                  {{ item.name }}
                  <span class="h-4 w-4 rounded-full border border-white shadow" :style="{ backgroundColor: item.accent }"></span>
                </span>
                <span class="mt-1 block text-xs text-slate-500">{{ item.description }}</span>
              </button>
            </div>
            <div class="mt-4" v-if="savedTemplates.length">
              <h3 class="text-xs font-bold uppercase text-slate-500">Mẫu đã lưu</h3>
              <div class="mt-2 space-y-2">
                <div v-for="item in savedTemplates" :key="item.id" class="rounded-md border border-slate-200 p-2">
                  <button class="w-full text-left text-sm font-semibold text-slate-800" @click="applyTemplate(item)">{{ item.name }}</button>
                  <div class="mt-1 flex items-center justify-between gap-2 text-[11px] text-slate-500">
                    <span>{{ item.savedAt }}</span>
                    <button class="font-semibold text-red-600" @click="deleteSavedTemplate(item.id)">Xóa</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="border bg-white p-4">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><MousePointer2 class="h-4 w-4 text-blue-700" /> Thành phần kéo thả</h2>
            <div class="mt-3 grid grid-cols-2 gap-2">
              <button class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50" @click="addElement('text', { label: 'Văn bản', text: 'Tiêu đề mới' })"><Type class="mx-auto mb-1 h-4 w-4" />Văn bản</button>
              <button class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50" @click="addElement('qr', { label: 'QR xác minh' })"><QrCode class="mx-auto mb-1 h-4 w-4" />QR</button>
              <button class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50" @click="addElement('signature', { label: 'Chữ ký', text: 'Người ký' })"><Signature class="mx-auto mb-1 h-4 w-4" />Chữ ký</button>
              <button class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50" @click="addElement('image', { label: 'Logo', text: 'LOGO' })"><Image class="mx-auto mb-1 h-4 w-4" />Logo</button>
            </div>
            <button class="mt-2 flex w-full items-center justify-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-100" @click="triggerAddImage">
              <Image class="h-4 w-4" /> Chèn ảnh từ máy
            </button>
            <input ref="addImageInputRef" type="file" accept="image/*" class="hidden" @change="addImageFromFile" />
          </div>

          <div class="border bg-white p-4">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><FileText class="h-4 w-4 text-blue-700" /> Trường dữ liệu động</h2>
            <div class="mt-3 max-h-[520px] space-y-3 overflow-auto pr-1">
              <div v-for="group in dynamicFieldGroups" :key="group.group">
                <h3 class="mb-2 text-[11px] font-bold uppercase text-slate-500">{{ group.group }}</h3>
                <div class="space-y-2">
                  <button
                    v-for="field in group.fields"
                    :key="field.key"
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-left text-xs hover:border-blue-300 hover:bg-blue-50"
                    @click="addElement('field', { field: field.key, label: field.label })"
                  >
                    <span class="font-semibold text-slate-800">{{ field.label }}</span>
                    <span class="block font-mono text-[11px] text-slate-500">{{ placeholderFor(field.key) }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </aside>

        <main class="min-w-0 border bg-white p-3 sm:p-5">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2 text-sm text-slate-600">
              <ShieldCheck class="h-4 w-4 text-emerald-600" />
              <span :class="publishReady ? 'text-emerald-700' : 'text-amber-700'">{{ publishReady ? 'Đã sẵn sàng phát hành' : 'Cần đúng 1 QR và trường họ tên người học' }}</span>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="snapToGrid" type="checkbox" class="rounded border-slate-300" />
              Bám lưới
            </label>
          </div>

          <div class="overflow-auto rounded-lg bg-slate-100 p-4" :class="fullScreen ? 'min-h-[calc(100vh-150px)]' : 'min-h-[620px]'">
            <div
              ref="canvasRef"
              class="relative mx-auto aspect-[1.414/1] min-w-[900px] max-w-7xl overflow-hidden border bg-white shadow-sm"
              :style="canvasStyle"
              @pointermove="onPointerMove"
              @pointerup="endDrag"
              @pointerleave="endDrag"
            >
              <div v-if="showCanvasFrame" class="absolute inset-[5%] border-2" :style="{ borderColor: template.border }"></div>
              <div v-if="showCanvasGuides" class="absolute inset-[7%] border border-dashed border-slate-300"></div>
              <div v-if="showCanvasGuides" class="absolute left-[9%] right-[9%] top-[35%] h-px bg-slate-200"></div>
              <div v-if="showCanvasGuides" class="absolute left-[9%] right-[9%] top-[67%] h-px bg-slate-200"></div>

              <div
                v-for="item in elements"
                :key="item.id"
                class="absolute select-none"
                :class="[
                  previewMode ? '' : 'cursor-move',
                  selectedId === item.id && !previewMode ? 'ring-2 ring-blue-500 ring-offset-2' : 'hover:ring-1 hover:ring-blue-300',
                ]"
                :style="{ left: item.x + '%', top: item.y + '%', width: item.w + '%', height: item.h + '%' }"
                @pointerdown.stop="startDrag($event, item)"
              >
                <div
                  class="grid h-full w-full place-items-center overflow-hidden px-2 text-center"
                  :class="item.type === 'qr' ? 'border border-slate-900 bg-white' : item.type === 'signature' ? 'border-t border-slate-400' : ''"
                  :style="{ color: item.color, fontSize: item.size + 'px', textAlign: item.align, fontWeight: item.weight }"
                >
                  <QrCode v-if="item.type === 'qr'" class="h-3/5 w-3/5 text-slate-950" />
                  <img v-else-if="item.type === 'image' && item.src" :src="item.src" :alt="item.label" class="h-full w-full" :style="{ objectFit: item.fit }" />
                  <span v-else-if="item.type === 'image'" class="grid h-full w-full place-items-center rounded border border-dashed border-slate-300 text-xs font-bold">LOGO</span>
                  <span v-else class="w-full truncate" :style="{ textAlign: item.align }">{{ displayText(item) }}</span>
                </div>
                <button
                  v-if="selectedId === item.id && !previewMode"
                  class="absolute -bottom-2 -right-2 h-4 w-4 rounded-sm border border-blue-600 bg-white"
                  title="Đổi kích thước"
                  @pointerdown.stop="startDrag($event, item, 'resize')"
                ></button>
              </div>
            </div>
          </div>
        </main>

        <aside v-if="rightPanelOpen" class="space-y-4">
          <div class="border bg-white p-4">
            <div class="flex items-center justify-between">
              <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><Layers3 class="h-4 w-4 text-blue-700" /> Lớp thiết kế</h2>
              <div class="flex gap-1">
                <button class="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600" @click="layersPanelOpen = !layersPanelOpen">
                  {{ layersPanelOpen ? 'Đóng' : 'Mở' }}
                </button>
                <button v-if="layersPanelOpen" class="rounded-md border border-slate-200 p-2 text-slate-600" title="Nhân bản" @click="duplicateSelected"><Copy class="h-4 w-4" /></button>
                <button v-if="layersPanelOpen" class="rounded-md border border-slate-200 p-2 text-red-600" title="Xóa" @click="removeSelected"><Trash2 class="h-4 w-4" /></button>
              </div>
            </div>
            <div v-if="!layersPanelOpen" class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
              Đã thu gọn danh sách lớp để rộng vùng thiết kế. Bấm `Mở` để quản lý lớp.
            </div>
            <div v-if="layersPanelOpen" class="mt-3 max-h-72 space-y-2 overflow-auto pr-1">
              <button
                v-for="item in [...elements].reverse()"
                :key="item.id"
                class="flex w-full items-center justify-between rounded-md border px-3 py-2 text-left text-xs"
                :class="selectedId === item.id ? 'border-blue-500 bg-blue-50 text-blue-950' : 'border-slate-200 text-slate-700'"
                @click="selectedId = item.id"
              >
                <span class="font-semibold">{{ item.label }}</span>
                <span class="text-slate-400">{{ typeLabel(item.type) }}</span>
              </button>
            </div>
          </div>

          <div class="border bg-white p-4">
            <h2 class="text-sm font-semibold text-slate-950">Bảng thuộc tính</h2>
            <div v-if="selected" class="mt-3 space-y-3 text-sm">
              <label class="block">
                <span class="text-xs font-semibold text-slate-500">Tên lớp</span>
                <input v-model="selected.label" class="mt-1 w-full rounded-md border px-3 py-2" />
              </label>
              <label v-if="selected.type === 'text' || selected.type === 'signature'" class="block">
                <span class="text-xs font-semibold text-slate-500">Nội dung</span>
                <input v-model="selected.text" class="mt-1 w-full rounded-md border px-3 py-2" />
              </label>
              <label v-if="selected.type === 'field'" class="block">
                <span class="text-xs font-semibold text-slate-500">Trường dữ liệu động</span>
                <select v-model="selected.field" class="mt-1 w-full rounded-md border px-3 py-2">
                  <option v-for="field in dynamicFields" :key="field.key" :value="field.key">{{ field.label }}</option>
                </select>
              </label>
              <div v-if="selected.type === 'image'" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold text-slate-500">Ảnh hiển thị</span>
                <div class="mt-2 grid grid-cols-[72px_1fr] gap-3">
                  <div class="grid h-16 w-16 place-items-center overflow-hidden rounded-md border border-slate-200 bg-white">
                    <img v-if="selected.src" :src="selected.src" :alt="selected.label" class="h-full w-full" :style="{ objectFit: selected.fit }" />
                    <Image v-else class="h-6 w-6 text-slate-400" />
                  </div>
                  <div class="space-y-2">
                    <button class="w-full rounded-md border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-50" @click="triggerReplaceImage">
                      Chọn ảnh cho lớp này
                    </button>
                    <select v-model="selected.fit" class="w-full rounded-md border px-3 py-2 text-xs">
                      <option value="contain">Vừa khung</option>
                      <option value="cover">Phủ kín khung</option>
                      <option value="fill">Kéo giãn theo khung</option>
                    </select>
                  </div>
                </div>
                <input ref="replaceImageInputRef" type="file" accept="image/*" class="hidden" @change="replaceSelectedImage" />
              </div>
              <div class="grid grid-cols-2 gap-2">
                <label><span class="text-xs font-semibold text-slate-500">X</span><input v-model.number="selected.x" type="number" class="mt-1 w-full rounded-md border px-2 py-2" /></label>
                <label><span class="text-xs font-semibold text-slate-500">Y</span><input v-model.number="selected.y" type="number" class="mt-1 w-full rounded-md border px-2 py-2" /></label>
                <label><span class="text-xs font-semibold text-slate-500">W</span><input v-model.number="selected.w" type="number" class="mt-1 w-full rounded-md border px-2 py-2" /></label>
                <label><span class="text-xs font-semibold text-slate-500">H</span><input v-model.number="selected.h" type="number" class="mt-1 w-full rounded-md border px-2 py-2" /></label>
              </div>
              <div class="grid grid-cols-[1fr_90px] gap-2">
                <label><span class="text-xs font-semibold text-slate-500">Màu</span><input v-model="selected.color" type="color" class="mt-1 h-10 w-full rounded-md border p-1" /></label>
                <label><span class="text-xs font-semibold text-slate-500">Cỡ chữ</span><input v-model.number="selected.size" type="number" class="mt-1 w-full rounded-md border px-2 py-2" /></label>
              </div>
              <div class="grid grid-cols-3 gap-2">
                <button class="rounded-md border p-2" :class="selected.align === 'left' ? 'bg-blue-50 text-blue-700' : ''" title="Căn trái" @click="selected.align = 'left'"><AlignLeft class="mx-auto h-4 w-4" /></button>
                <button class="rounded-md border p-2" :class="selected.align === 'center' ? 'bg-blue-50 text-blue-700' : ''" title="Căn giữa" @click="selected.align = 'center'"><AlignCenter class="mx-auto h-4 w-4" /></button>
                <button class="rounded-md border p-2" :class="selected.align === 'right' ? 'bg-blue-50 text-blue-700' : ''" title="Căn phải" @click="selected.align = 'right'"><AlignRight class="mx-auto h-4 w-4" /></button>
              </div>
            </div>
          </div>

          <div class="border bg-white p-4">
            <h2 class="text-sm font-semibold text-slate-950">Kiểm soát chứng chỉ</h2>
            <div class="mt-3 space-y-3 text-sm">
              <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <h3 class="text-xs font-bold uppercase text-slate-500">Nền và kiểu in</h3>
                <div class="mt-3 grid grid-cols-2 gap-2">
                  <label>
                    <span class="text-xs font-semibold text-slate-500">Màu nền</span>
                    <input v-model="template.background" type="color" class="mt-1 h-10 w-full rounded-md border bg-white p-1" />
                  </label>
                  <label>
                    <span class="text-xs font-semibold text-slate-500">Màu khung</span>
                    <input v-model="template.border" type="color" class="mt-1 h-10 w-full rounded-md border bg-white p-1" />
                  </label>
                </div>
                <label class="mt-3 block">
                  <span class="text-xs font-semibold text-slate-500">Loại bản in</span>
                  <select v-model="printMode" class="mt-1 w-full rounded-md border bg-white px-3 py-2 text-sm">
                    <option v-for="mode in printModes" :key="mode.id" :value="mode.id">{{ mode.name }}</option>
                  </select>
                </label>
                <div class="mt-2 rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-600">
                  {{ activePrintMode.description }}
                </div>
              </div>
              <label v-for="(_, key) in settings" :key="key" class="flex items-center justify-between gap-3">
                <span class="text-slate-700">{{ key }}</span>
                <input v-model="settings[key]" type="checkbox" class="rounded border-slate-300" />
              </label>
              <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                Số lớp QR: <strong :class="qrCount === 1 ? 'text-emerald-700' : 'text-red-700'">{{ qrCount }}</strong>. Chuẩn phát hành yêu cầu đúng một QR để quét xác minh.
              </div>
            </div>
          </div>
        </aside>
      </div>

      <div v-if="printPreviewOpen" class="fixed inset-0 z-[60] grid place-items-center bg-slate-950/70 p-4">
        <div class="max-h-[94vh] w-full max-w-6xl overflow-auto rounded-lg bg-white p-4 shadow-2xl">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
            <div>
              <h2 class="text-lg font-semibold text-slate-950">Xem trước bản in</h2>
              <p class="mt-1 text-sm text-slate-600">Bản xem trước dùng trực tiếp bố cục, màu sắc và dữ liệu mẫu đang thiết kế trên canvas.</p>
              <div class="mt-2 inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-700">
                {{ activePrintMode.name }}
              </div>
            </div>
            <div class="flex gap-2">
              <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" @click="printPreview">In thử</button>
              <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="printPreviewOpen = false">Đóng</button>
            </div>
          </div>

          <div class="bg-slate-100 p-4">
            <div
              class="relative mx-auto aspect-[1.414/1] w-full max-w-5xl overflow-hidden border bg-white shadow-sm"
              :style="canvasStyle"
            >
              <div v-if="showCanvasFrame" class="absolute inset-[5%] border-2" :style="{ borderColor: template.border }"></div>
              <div
                v-for="item in elements"
                :key="`print-${item.id}`"
                class="absolute select-none"
                :style="{ left: item.x + '%', top: item.y + '%', width: item.w + '%', height: item.h + '%' }"
              >
                <div
                  class="grid h-full w-full place-items-center overflow-hidden px-2 text-center"
                  :class="item.type === 'qr' ? 'border border-slate-900 bg-white' : item.type === 'signature' ? 'border-t border-slate-400' : ''"
                  :style="{ color: item.color, fontSize: item.size + 'px', textAlign: item.align, fontWeight: item.weight }"
                >
                  <QrCode v-if="item.type === 'qr'" class="h-3/5 w-3/5 text-slate-950" />
                  <img v-else-if="item.type === 'image' && item.src" :src="item.src" :alt="item.label" class="h-full w-full" :style="{ objectFit: item.fit }" />
                  <span v-else-if="item.type === 'image'" class="grid h-full w-full place-items-center rounded border border-dashed border-slate-300 text-xs font-bold">LOGO</span>
                  <span v-else class="w-full truncate" :style="{ textAlign: item.align }">{{ displayText(item) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </EraLmsLayout>
</template>
