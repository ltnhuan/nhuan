<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import ActionBar from '@/Components/Lms/ActionBar.vue'
import StatusBadge from '@/Components/Lms/StatusBadge.vue'
import { useLmsAction } from '@/composables/useLmsAction'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const items = ref([])
const folders = ref([])
const selectedFolderId = ref('')
const selectedItem = ref(null)
const loadingPage = ref(false)
const showTrash = ref(false)
const search = ref('')
const typeFilter = ref('')
const statusFilter = ref('')
const creatingFolder = ref(false)
const uploading = ref(false)
const editingItem = ref(null)
const versionItem = ref(null)
const uploadFileInput = ref(null)
const versionFileInput = ref(null)
const { loading, toast, validationErrors, runAction } = useLmsAction(props.apiHeaders)

const folderForm = ref({ title: 'Thư mục mới', description: '', visibility: 'faculty' })
const uploadForm = ref({ title: '', description: '', visibility: 'private', item_type: '', file: null })
const editForm = ref({ title: '', description: '', visibility: 'private', status: 'draft' })
const versionForm = ref({ change_note: '', file: null })

const stats = computed(() => ({
  total: items.value.length,
  folders: items.value.filter((item) => item.item_type === 'folder').length,
  files: items.value.filter((item) => item.item_type !== 'folder').length,
  trashed: items.value.filter((item) => item.status === 'trashed').length,
}))

const visibleFolders = computed(() => [{ id: '', title: 'Tất cả học liệu' }, ...folders.value])
const selectedMetadataRows = computed(() => metadataRows(selectedItem.value?.metadata))
const selectedFolderTitle = computed(() => {
  if (!selectedFolderId.value) return 'Tất cả học liệu'
  return folders.value.find((folder) => String(folder.id) === String(selectedFolderId.value))?.title || 'Thư mục'
})

const rowActions = computed(() => {
  if (showTrash.value) {
    return [
      { action_key: 'repository.restore', label: 'Khôi phục', route: (item) => `/api/v1/repository/items/${item.id}/restore`, method: 'POST' },
      { action_key: 'repository.permanent_delete', label: 'Xóa hẳn', route: (item) => `/api/v1/repository/items/${item.id}/permanent`, method: 'DELETE', confirm_required: true, confirm_message: 'Xóa vĩnh viễn học liệu này?' },
    ]
  }

  return [
    { action_key: 'repository.view', label: 'Xem', local: 'view' },
    { action_key: 'repository.update', label: 'Sửa', local: 'edit' },
    { action_key: 'repository.move', label: 'Di chuyển', local: 'move' },
    { action_key: 'repository.copy', label: 'Sao chép', route: (item) => `/api/v1/repository/items/${item.id}/copy`, method: 'POST' },
    { action_key: 'repository.share', label: 'Chia sẻ toàn hệ thống', route: (item) => `/api/v1/repository/items/${item.id}/share`, method: 'POST', body: { visibility: 'tenant' } },
    { action_key: 'repository.new_version', label: 'Phiên bản', local: 'version' },
    { action_key: 'repository.submit_review', label: 'Gửi duyệt', route: (item) => `/api/v1/repository/items/${item.id}/submit-review`, method: 'POST', confirm_required: true, confirm_message: 'Gửi học liệu sang bước duyệt?' },
    { action_key: 'repository.approve', label: 'Phê duyệt', route: (item) => `/api/v1/repository/items/${item.id}/approve`, method: 'POST', confirm_required: true, confirm_message: 'Phê duyệt học liệu này?' },
    { action_key: 'repository.trash', label: 'Đưa vào thùng rác', route: (item) => `/api/v1/repository/items/${item.id}/trash`, method: 'POST', confirm_required: true, confirm_message: 'Đưa học liệu vào thùng rác?' },
  ]
})

function queryString() {
  const params = new URLSearchParams({ per_page: '100' })
  if (showTrash.value) params.set('trash', '1')
  else if (selectedFolderId.value) params.set('parent_id', selectedFolderId.value)
  if (search.value) params.set('search', search.value)
  if (typeFilter.value) params.set('item_type', typeFilter.value)
  if (statusFilter.value && !showTrash.value) params.set('status', statusFilter.value)
  return params.toString()
}

async function load() {
  loadingPage.value = true
  try {
    const [itemResponse, treeResponse] = await Promise.all([
      fetch(`/api/v1/repository/items?${queryString()}`, { headers: props.apiHeaders }),
      fetch('/api/v1/repository/tree', { headers: props.apiHeaders }),
    ])
    const itemPayload = await itemResponse.json()
    const treePayload = await treeResponse.json()
    items.value = itemPayload.data?.data || itemPayload.data || []
    folders.value = treePayload.data || treePayload || []
  } finally {
    loadingPage.value = false
  }
}

async function createFolder() {
  await runAction({
    actionKey: 'repository.folder.create',
    url: '/api/v1/repository/folders',
    method: 'POST',
    body: { ...folderForm.value, parent_id: selectedFolderId.value || null },
    reload: async () => {
      creatingFolder.value = false
      folderForm.value = { title: 'Thư mục mới', description: '', visibility: 'faculty' }
      await load()
    },
  })
}

function onUploadFile(event) {
  uploadForm.value.file = event.target.files?.[0] || null
  if (!uploadForm.value.title && uploadForm.value.file) {
    uploadForm.value.title = uploadForm.value.file.name.replace(/\.[^.]+$/, '')
  }
}

async function uploadFile() {
  const form = new FormData()
  form.append('file', uploadForm.value.file)
  form.append('parent_id', selectedFolderId.value || '')
  form.append('title', uploadForm.value.title || '')
  form.append('description', uploadForm.value.description || '')
  form.append('visibility', uploadForm.value.visibility)
  if (uploadForm.value.item_type) form.append('item_type', uploadForm.value.item_type)

  await runAction({
    actionKey: 'repository.upload',
    url: '/api/v1/repository/upload',
    method: 'POST',
    body: form,
    reload: async () => {
      uploading.value = false
      uploadForm.value = { title: '', description: '', visibility: 'private', item_type: '', file: null }
      if (uploadFileInput.value) uploadFileInput.value.value = ''
      await load()
    },
  })
}

async function openItem(item) {
  const response = await fetch(`/api/v1/repository/items/${item.id}`, { headers: props.apiHeaders })
  const payload = await response.json()
  selectedItem.value = payload.data || payload
}

async function downloadItem(item) {
  const response = await fetch(`/api/v1/repository/items/${item.id}/download-url`, { headers: props.apiHeaders })
  const payload = await response.json()
  const url = payload.data?.url || payload.url
  if (url) window.open(url, '_blank', 'noopener')
}

function openEdit(item) {
  editingItem.value = item
  editForm.value = {
    title: item.title || '',
    description: item.description || '',
    visibility: item.visibility || 'private',
    status: item.status || 'draft',
  }
}

async function saveEdit() {
  await runAction({
    actionKey: `repository.update:${editingItem.value.id}`,
    url: `/api/v1/repository/items/${editingItem.value.id}`,
    method: 'PUT',
    body: editForm.value,
    reload: async () => {
      editingItem.value = null
      await load()
    },
  })
}

function openVersion(item) {
  if (item.item_type === 'folder') return openItem(item)
  versionItem.value = item
  versionForm.value = { change_note: '', file: null }
  if (versionFileInput.value) versionFileInput.value.value = ''
}

function onVersionFile(event) {
  versionForm.value.file = event.target.files?.[0] || null
}

async function uploadVersion() {
  const form = new FormData()
  form.append('file', versionForm.value.file)
  form.append('change_note', versionForm.value.change_note || '')

  await runAction({
    actionKey: `repository.new_version:${versionItem.value.id}`,
    url: `/api/v1/repository/items/${versionItem.value.id}/new-version`,
    method: 'POST',
    body: form,
    reload: async () => {
      versionItem.value = null
      await load()
    },
  })
}

async function moveItem(item) {
  const target = window.prompt('Nhập ID folder đích. Để trống để đưa ra gốc.', selectedFolderId.value || '')
  if (target === null) return
  await runAction({
    actionKey: `repository.move:${item.id}`,
    url: `/api/v1/repository/items/${item.id}/move`,
    method: 'POST',
    body: { parent_id: target ? Number(target) : null },
    reload: load,
  })
}

async function runRowAction(action, item) {
  if (action.local === 'view') return openItem(item)
  if (action.local === 'edit') return openEdit(item)
  if (action.local === 'move') return moveItem(item)
  if (action.local === 'version') return openVersion(item)

  await runAction({
    actionKey: `${action.action_key}:${item.id}`,
    url: action.route(item),
    method: action.method,
    body: action.body || null,
    confirm: action.confirm_required,
    confirmMessage: action.confirm_message,
    reload: load,
  })
}

function enterFolder(item) {
  if (item.item_type !== 'folder') return
  selectedFolderId.value = String(item.id)
  showTrash.value = false
  load()
}

function leaveFolder() {
  selectedFolderId.value = ''
  load()
}

function formatSize(value) {
  if (!value) return '-'
  if (value < 1024) return `${value} B`
  if (value < 1024 * 1024) return `${Math.ceil(value / 1024)} KB`
  return `${(value / 1024 / 1024).toFixed(1)} MB`
}

function formatDate(value) {
  if (!value) return '-'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function metadataRows(metadata) {
  if (!metadata || typeof metadata !== 'object') return []
  return Object.entries(metadata).map(([key, value]) => ({
    key,
    value: typeof value === 'object' ? JSON.stringify(value) : String(value ?? ''),
  }))
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Kho học liệu</template>

    <section class="space-y-4">
      <div v-if="toast.show" class="rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h1 class="text-lg font-bold text-slate-950">Kho học liệu</h1>
            <p class="mt-1 text-sm text-slate-600">{{ selectedFolderTitle }} · Tạo, tải lên, xem, sửa, di chuyển, sao chép, chia sẻ, tạo phiên bản, gửi duyệt, phê duyệt và chuyển thùng rác bằng API thật.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="load">Làm mới</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="showTrash = !showTrash; load()">{{ showTrash ? 'Kho học liệu' : 'Thùng rác' }}</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="creatingFolder = true">Tạo thư mục</button>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="uploading = true">Tải file lên</button>
          </div>
        </div>

        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-4">
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Tổng item</div><div class="mt-1 text-lg font-semibold">{{ stats.total }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Thư mục</div><div class="mt-1 text-lg font-semibold">{{ stats.folders }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">File</div><div class="mt-1 text-lg font-semibold">{{ stats.files }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Thùng rác</div><div class="mt-1 text-lg font-semibold">{{ stats.trashed }}</div></div>
        </div>

        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-[260px_1fr_160px_160px]">
          <select v-model="selectedFolderId" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" :disabled="showTrash" @change="load">
            <option v-for="folder in visibleFolders" :key="folder.id || 'root'" :value="folder.id">{{ folder.title }}</option>
          </select>
          <input v-model="search" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" placeholder="Tìm theo tên học liệu" @keyup.enter="load" />
          <select v-model="typeFilter" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" @change="load">
            <option value="">Tất cả loại</option>
            <option value="folder">Thư mục</option>
            <option value="video">Video</option>
            <option value="pdf">PDF</option>
            <option value="slide">Slide</option>
            <option value="pptx">PPTX</option>
            <option value="docx">DOCX</option>
            <option value="quiz_template">Quiz Template</option>
            <option value="assignment_template">Assignment Template</option>
            <option value="rubric_template">Rubric Template</option>
            <option value="exam_blueprint">Exam Blueprint</option>
            <option value="image">Hình ảnh</option>
            <option value="file">File</option>
          </select>
          <select v-model="statusFilter" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" :disabled="showTrash" @change="load">
            <option value="">Tất cả trạng thái</option>
            <option value="draft">Nháp</option>
            <option value="review">Chờ duyệt</option>
            <option value="approved">Đã duyệt</option>
            <option value="published">Đã xuất bản</option>
            <option value="archived">Đã lưu trữ</option>
          </select>
        </div>

        <div v-if="creatingFolder" class="border-b border-slate-200 p-4">
          <div class="grid gap-3 md:grid-cols-[1fr_160px]">
            <input v-model="folderForm.title" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Tên thư mục" />
            <select v-model="folderForm.visibility" class="h-10 rounded-md border border-slate-300 px-3 text-sm">
              <option value="private">Riêng tư</option>
              <option value="faculty">Khoa/Bộ môn</option>
              <option value="tenant">Toàn hệ thống</option>
              <option value="public">Công khai</option>
            </select>
            <textarea v-model="folderForm.description" class="min-h-20 rounded-md border border-slate-300 px-3 py-2 text-sm md:col-span-2" placeholder="Mô tả"></textarea>
          </div>
          <div class="mt-3 flex gap-2">
            <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading['repository.folder.create']" @click="createFolder">Lưu thư mục</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="creatingFolder = false">Hủy</button>
          </div>
        </div>

        <div v-if="uploading" class="border-b border-slate-200 p-4">
          <div class="grid gap-3 md:grid-cols-[1fr_160px_160px]">
            <input v-model="uploadForm.title" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Tiêu đề file" />
            <select v-model="uploadForm.visibility" class="h-10 rounded-md border border-slate-300 px-3 text-sm">
              <option value="private">Riêng tư</option>
              <option value="faculty">Khoa/Bộ môn</option>
              <option value="tenant">Toàn hệ thống</option>
              <option value="public">Công khai</option>
            </select>
            <select v-model="uploadForm.item_type" class="h-10 rounded-md border border-slate-300 px-3 text-sm">
              <option value="">Tự nhận diện</option>
              <option value="video">Video</option>
              <option value="pdf">PDF</option>
              <option value="slide">Slide</option>
              <option value="quiz_template">Quiz Template</option>
              <option value="assignment_template">Assignment Template</option>
              <option value="rubric_template">Rubric Template</option>
              <option value="exam_blueprint">Exam Blueprint</option>
              <option value="pptx">PPTX</option>
              <option value="docx">DOCX</option>
              <option value="file">File</option>
            </select>
            <input ref="uploadFileInput" type="file" class="h-10 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm md:col-span-3" @change="onUploadFile" />
          </div>
          <div class="mt-3 flex gap-2">
            <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="!uploadForm.file || loading['repository.upload']" @click="uploadFile">Tải lên</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="uploading = false">Hủy</button>
          </div>
        </div>

        <pre v-if="Object.keys(validationErrors).length" class="border-b border-red-100 bg-red-50 p-3 text-xs text-red-700">{{ validationErrors }}</pre>

        <div class="overflow-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Tên</th>
                <th class="px-4 py-3">Loại</th>
                <th class="px-4 py-3">Phạm vi</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3">Dung lượng</th>
                <th class="px-4 py-3">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="loadingPage"><td colspan="6" class="px-4 py-6 text-center text-slate-500">Đang tải dữ liệu...</td></tr>
              <tr v-if="!loadingPage && selectedFolderId && !showTrash">
                <td colspan="6" class="px-4 py-2"><button class="text-sm font-semibold text-blue-700" @click="leaveFolder">Về thư mục gốc</button></td>
              </tr>
              <tr v-for="item in loadingPage ? [] : items" :key="item.id" class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <button class="text-left font-semibold text-slate-900 hover:text-blue-700" @click="item.item_type === 'folder' ? enterFolder(item) : openItem(item)">
                    {{ item.item_type === 'folder' ? '[Thư mục]' : '[File]' }} {{ item.title }}
                  </button>
                  <div v-if="item.owner?.full_name" class="text-xs text-slate-500">{{ item.owner.full_name }}</div>
                </td>
                <td class="px-4 py-3">{{ item.item_type }}</td>
                <td class="px-4 py-3">{{ item.visibility }}</td>
                <td class="px-4 py-3"><StatusBadge :status="item.status" /></td>
                <td class="px-4 py-3">{{ formatSize(item.file_size) }}</td>
                <td class="min-w-[520px] px-4 py-3">
                  <ActionBar :actions="rowActions" :loading-map="loading" @run="(action) => runRowAction(action, item)" />
                </td>
              </tr>
              <tr v-if="!loadingPage && !items.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Không có học liệu.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <div v-if="selectedItem" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
      <div class="max-h-[88vh] w-full max-w-5xl overflow-auto rounded-lg bg-white shadow-xl">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4">
          <div>
            <h2 class="text-lg font-bold text-slate-950">{{ selectedItem.title }}</h2>
            <p class="mt-1 text-sm text-slate-500">#{{ selectedItem.id }} · {{ selectedItem.item_type }} · {{ selectedItem.visibility }}</p>
          </div>
          <div class="flex flex-wrap justify-end gap-2">
            <button v-if="selectedItem.item_type !== 'folder'" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="downloadItem(selectedItem)">Tải xuống</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="openEdit(selectedItem); selectedItem = null">Sửa</button>
            <button v-if="selectedItem.item_type !== 'folder'" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="openVersion(selectedItem); selectedItem = null">Phiên bản</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="selectedItem = null">Đóng</button>
          </div>
        </div>

        <div class="grid gap-4 p-5 lg:grid-cols-[1fr_320px]">
          <main class="space-y-4">
            <section class="rounded-lg border border-slate-200">
              <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-bold text-slate-950">Thông tin học liệu</h3>
              </div>
              <div class="grid gap-0 text-sm md:grid-cols-2">
                <div class="border-b border-slate-100 px-4 py-3">
                  <div class="text-xs font-semibold uppercase text-slate-500">Tiêu đề</div>
                  <div class="mt-1 text-slate-900">{{ selectedItem.title }}</div>
                </div>
                <div class="border-b border-slate-100 px-4 py-3">
                  <div class="text-xs font-semibold uppercase text-slate-500">Trạng thái</div>
                  <div class="mt-1"><StatusBadge :status="selectedItem.status" /></div>
                </div>
                <div class="border-b border-slate-100 px-4 py-3">
                <div class="text-xs font-semibold uppercase text-slate-500">Loại MIME</div>
                  <div class="mt-1 text-slate-900">{{ selectedItem.mime_type || '-' }}</div>
                </div>
                <div class="border-b border-slate-100 px-4 py-3">
                  <div class="text-xs font-semibold uppercase text-slate-500">Dung lượng</div>
                  <div class="mt-1 text-slate-900">{{ formatSize(selectedItem.file_size) }}</div>
                </div>
                <div class="border-b border-slate-100 px-4 py-3">
                <div class="text-xs font-semibold uppercase text-slate-500">Người sở hữu</div>
                  <div class="mt-1 text-slate-900">{{ selectedItem.owner?.full_name || '-' }}</div>
                </div>
                <div class="border-b border-slate-100 px-4 py-3">
                  <div class="text-xs font-semibold uppercase text-slate-500">Khoa/Bộ môn</div>
                  <div class="mt-1 text-slate-900">{{ selectedItem.academic_unit?.name || '-' }}</div>
                </div>
                <div class="px-4 py-3 md:col-span-2">
                  <div class="text-xs font-semibold uppercase text-slate-500">Mô tả</div>
                  <div class="mt-1 whitespace-pre-wrap text-slate-900">{{ selectedItem.description || 'Chưa có mô tả.' }}</div>
                </div>
              </div>
            </section>

            <section class="rounded-lg border border-slate-200">
              <div class="border-b border-slate-200 px-4 py-3">
              <h3 class="text-sm font-bold text-slate-950">Lịch sử phiên bản</h3>
              </div>
              <div class="overflow-auto">
                <table class="w-full text-left text-sm">
                  <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                      <th class="px-4 py-2">Phiên bản</th>
                      <th class="px-4 py-2">Dung lượng</th>
                      <th class="px-4 py-2">Người tạo</th>
                      <th class="px-4 py-2">Thời gian</th>
                      <th class="px-4 py-2">Ghi chú thay đổi</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="version in selectedItem.versions || []" :key="version.id">
                      <td class="px-4 py-2 font-semibold">v{{ version.version }}</td>
                      <td class="px-4 py-2">{{ formatSize(version.file_size) }}</td>
                      <td class="px-4 py-2">{{ version.creator?.full_name || '-' }}</td>
                      <td class="px-4 py-2">{{ formatDate(version.created_at) }}</td>
                      <td class="px-4 py-2">{{ version.change_note || '-' }}</td>
                    </tr>
                    <tr v-if="!(selectedItem.versions || []).length">
                <td colspan="5" class="px-4 py-6 text-center text-slate-500">Chưa có phiên bản.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section v-if="(selectedItem.children || []).length" class="rounded-lg border border-slate-200">
              <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-bold text-slate-950">Folder con</h3>
              </div>
              <div class="divide-y divide-slate-100">
                <button v-for="child in selectedItem.children" :key="child.id" class="flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-slate-50" @click="selectedItem = null; enterFolder(child)">
                  <span class="font-semibold text-slate-900">{{ child.title }}</span>
                  <span class="text-xs text-slate-500">{{ child.item_type }} · {{ child.status }}</span>
                </button>
              </div>
            </section>
          </main>

          <aside class="space-y-4">
            <section class="rounded-lg border border-slate-200">
              <div class="border-b border-slate-200 px-4 py-3">
              <h3 class="text-sm font-bold text-slate-950">Luồng xử lý</h3>
              </div>
              <div class="space-y-2 p-4 text-sm">
                <div class="flex items-center justify-between"><span>Phạm vi</span><span class="font-semibold">{{ selectedItem.visibility }}</span></div>
                <div class="flex items-center justify-between"><span>Trạng thái</span><StatusBadge :status="selectedItem.status" /></div>
                <div class="flex items-center justify-between"><span>Cha</span><span class="font-semibold">{{ selectedItem.parent?.title || 'Gốc' }}</span></div>
                <div class="flex items-center justify-between"><span>Mã kiểm tra</span><span class="max-w-36 truncate font-mono text-xs">{{ selectedItem.checksum || '-' }}</span></div>
              </div>
            </section>

            <section class="rounded-lg border border-slate-200">
              <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-bold text-slate-950">Đang dùng trong bài giảng</h3>
              </div>
              <div class="divide-y divide-slate-100">
                <div v-for="component in selectedItem.components || []" :key="component.id" class="px-4 py-3 text-sm">
                  <div class="font-semibold text-slate-900">{{ component.title || component.component_type }}</div>
                  <div class="text-xs text-slate-500">{{ component.course?.title || 'Khóa học' }}</div>
                </div>
                <div v-if="!(selectedItem.components || []).length" class="px-4 py-6 text-sm text-slate-500">Chưa gắn vào bài giảng.</div>
              </div>
            </section>

            <section class="rounded-lg border border-slate-200">
              <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="text-sm font-bold text-slate-950">Siêu dữ liệu</h3>
              </div>
              <div class="divide-y divide-slate-100">
                <div v-for="row in selectedMetadataRows" :key="row.key" class="px-4 py-3 text-sm">
                  <div class="text-xs font-semibold uppercase text-slate-500">{{ row.key }}</div>
                  <div class="mt-1 break-words text-slate-800">{{ row.value }}</div>
                </div>
                <div v-if="!selectedMetadataRows.length" class="px-4 py-6 text-sm text-slate-500">Không có siêu dữ liệu.</div>
              </div>
            </section>
          </aside>
        </div>
      </div>
    </div>

    <div v-if="editingItem" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
      <div class="w-full max-w-xl rounded-lg bg-white p-5 shadow-xl">
        <h2 class="text-base font-bold text-slate-950">Sửa học liệu</h2>
        <div class="mt-4 grid gap-3">
          <input v-model="editForm.title" class="h-10 rounded-md border border-slate-300 px-3 text-sm" />
          <textarea v-model="editForm.description" class="min-h-24 rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
          <div class="grid grid-cols-2 gap-3">
              <select v-model="editForm.visibility" class="h-10 rounded-md border border-slate-300 px-3 text-sm">
              <option value="private">Riêng tư</option>
              <option value="faculty">Khoa/Bộ môn</option>
              <option value="tenant">Toàn hệ thống</option>
              <option value="public">Công khai</option>
            </select>
            <select v-model="editForm.status" class="h-10 rounded-md border border-slate-300 px-3 text-sm">
              <option value="draft">Nháp</option>
              <option value="review">Chờ duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="published">Đã xuất bản</option>
              <option value="archived">Đã lưu trữ</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="editingItem = null">Hủy</button>
          <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white" @click="saveEdit">Lưu</button>
        </div>
      </div>
    </div>

    <div v-if="versionItem" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
      <div class="w-full max-w-xl rounded-lg bg-white p-5 shadow-xl">
        <h2 class="text-base font-bold text-slate-950">Tải phiên bản mới</h2>
        <p class="mt-1 text-sm text-slate-500">{{ versionItem.title }}</p>
        <div class="mt-4 grid gap-3">
          <input ref="versionFileInput" type="file" class="h-10 rounded-md border border-slate-300 px-3 py-2 text-sm" @change="onVersionFile" />
          <textarea v-model="versionForm.change_note" class="min-h-24 rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ghi chú thay đổi"></textarea>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="versionItem = null">Hủy</button>
          <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="!versionForm.file" @click="uploadVersion">Tải phiên bản mới</button>
        </div>
      </div>
    </div>
  </EraLmsLayout>
</template>
