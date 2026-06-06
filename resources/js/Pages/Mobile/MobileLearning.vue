<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, required: true },
})
defineEmits(['logout'])

const tabs = [
  { key: 'home', label: 'Home', icon: '⌂' },
  { key: 'courses', label: 'Courses', icon: '▤' },
  { key: 'offline', label: 'Offline', icon: '⇩' },
  { key: 'attendance', label: 'Attend', icon: '◎' },
  { key: 'wallet', label: 'Wallet', icon: '◇' },
]

const activeTab = ref('home')
const online = ref(navigator.onLine)
const loading = ref(true)
const payload = ref(null)
const offlineItems = ref([])
const queue = ref([])
const quizDraft = ref({ question: 'Chọn đáp án đúng cho bài học offline', answer: '' })
const syncMessage = ref('')
const actionMessage = ref('')
const selectedPrompt = ref('')
const attendanceMode = ref('')
const checkInResult = ref(null)
const selectedCredential = ref(null)

const pendingCount = computed(() => queue.value.length + (payload.value?.pending_sync_count || 0))
const myCourses = computed(() => payload.value?.courses || [])
const continueLearning = computed(() => payload.value?.continue_learning || [])
const notifications = computed(() => payload.value?.notifications || [])
const wallet = computed(() => payload.value?.credential_wallet || { certificates: [], badges: [] })
const aiPrompts = computed(() => payload.value?.ai_tutor?.offline_prompts || [])
const attendance = computed(() => payload.value?.attendance || { qr_enabled: false, otp_enabled: false, active_session_id: null, status: 'none' })

onMounted(async () => {
  window.addEventListener('online', () => {
    online.value = true
    syncPending()
  })
  window.addEventListener('offline', () => {
    online.value = false
  })

  await openStores()
  await loadLocal()
  await fetchBootstrap()
})

async function fetchBootstrap(showMessage = true) {
  loading.value = true
  try {
    payload.value = await api('/api/v1/mobile/bootstrap')
    if (showMessage) actionMessage.value = 'Dữ liệu mobile đã tải mới.'
  } catch (error) {
    actionMessage.value = error.message || 'Không tải được dữ liệu mobile.'
  } finally {
    loading.value = false
  }
}

async function api(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })
  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(data.message || 'Không gọi được API.')
  }

  return data.data || data
}

async function downloadCourse(course) {
  if (!course?.components?.length) {
    actionMessage.value = 'Khóa học này chưa có nội dung để tải offline.'
    return
  }

  const bundle = {
    id: course.id,
    title: course.title,
    savedAt: new Date().toISOString(),
    assets: course.components.filter((item) => item.downloadable),
  }
  await putStore('offline_content', bundle)
  offlineItems.value = await allStore('offline_content')
  actionMessage.value = `Đã lưu offline: ${course.title}.`
}

async function saveLessonProgress(course, component, progressPercent = 100) {
  const item = {
    client_uuid: crypto.randomUUID(),
    operation: 'progress.upsert',
    payload: {
      course_id: course.id,
      component_id: component.id,
      progress_percent: progressPercent,
      status: progressPercent >= 100 ? 'completed' : 'in_progress',
      client_updated_at: new Date().toISOString(),
      metadata: { platform: 'pwa', offline_saved: !online.value },
    },
  }
  await putStore('offline_queue', item)
  queue.value = await allStore('offline_queue')
  actionMessage.value = `Đã ghi tiến độ: ${component.title}.`
  if (online.value) await syncPending()
}

async function saveQuizAnswer() {
  if (!quizDraft.value.answer.trim()) {
    actionMessage.value = 'Nhập câu trả lời trước khi lưu.'
    return
  }

  const item = {
    client_uuid: crypto.randomUUID(),
    operation: 'quiz.answer',
    payload: {
      question_id: 1,
      answer_data: { answer: quizDraft.value.answer, source: 'offline_quiz' },
      client_updated_at: new Date().toISOString(),
    },
  }
  await putStore('offline_quiz_answers', { id: item.client_uuid, ...item.payload })
  await putStore('offline_queue', item)
  quizDraft.value.answer = ''
  queue.value = await allStore('offline_queue')
  actionMessage.value = 'Đã lưu câu trả lời quiz vào local.'
  if (online.value) await syncPending()
}

async function syncPending() {
  queue.value = await allStore('offline_queue')
  if (!online.value) {
    syncMessage.value = 'Đang offline, dữ liệu sẽ sync khi có mạng.'
    actionMessage.value = syncMessage.value
    return
  }
  if (queue.value.length === 0) {
    syncMessage.value = 'Không có mục local nào cần sync.'
    actionMessage.value = syncMessage.value
    return
  }

  try {
    const result = await api('/api/v1/mobile/sync', {
      method: 'POST',
      body: JSON.stringify({
        device_id: deviceId(),
        items: queue.value,
      }),
    })
    for (const item of result.items || []) {
      if (item.status === 'synced') {
        await deleteStore('offline_queue', item.client_uuid)
      }
    }
    queue.value = await allStore('offline_queue')
    syncMessage.value = `${result.synced} mục đã sync, ${result.conflicts} conflict`
    actionMessage.value = syncMessage.value
    await fetchBootstrap(false)
  } catch (error) {
    syncMessage.value = error.message || 'Không sync được.'
    actionMessage.value = syncMessage.value
  }
}

function choosePrompt(prompt) {
  selectedPrompt.value = prompt
  actionMessage.value = `Đã chọn prompt AI offline: ${prompt}.`
}

function mobileCheckIn(mode) {
  attendanceMode.value = mode
  checkInResult.value = {
    mode,
    sessionId: attendance.value.active_session_id,
    at: new Date().toLocaleString(),
    status: attendance.value.active_session_id ? 'ready' : 'no_session',
  }
  actionMessage.value = attendance.value.active_session_id
    ? `${mode.toUpperCase()} check-in sẵn sàng cho phiên #${attendance.value.active_session_id}.`
    : 'Chưa có phiên điểm danh đang mở.'
}

function showCredential(type, credential) {
  selectedCredential.value = { type, credential }
  actionMessage.value = `Đang xem ${type} #${credential.id}.`
}

function deviceId() {
  const key = 'eralms_mobile_device_id'
  if (!localStorage.getItem(key)) {
    localStorage.setItem(key, `pwa-${crypto.randomUUID()}`)
  }
  return localStorage.getItem(key)
}

let dbPromise
function openStores() {
  dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open('eralms_mobile_learning', 1)
    request.onupgradeneeded = () => {
      const db = request.result
      for (const store of ['offline_queue', 'offline_progress', 'offline_quiz_answers', 'offline_content']) {
        if (!db.objectStoreNames.contains(store)) {
          db.createObjectStore(store, { keyPath: ['offline_content', 'offline_quiz_answers'].includes(store) ? 'id' : 'client_uuid' })
        }
      }
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
  return dbPromise
}

async function putStore(store, value) {
  const db = await dbPromise
  const record = JSON.parse(JSON.stringify(value))
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite')
    tx.objectStore(store).put(record)
    tx.oncomplete = resolve
    tx.onerror = () => reject(tx.error)
  })
}

async function deleteStore(store, key) {
  const db = await dbPromise
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite')
    tx.objectStore(store).delete(key)
    tx.oncomplete = resolve
    tx.onerror = () => reject(tx.error)
  })
}

async function allStore(store) {
  const db = await dbPromise
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readonly')
    const request = tx.objectStore(store).getAll()
    request.onsuccess = () => resolve(request.result || [])
    request.onerror = () => reject(request.error)
  })
}

async function loadLocal() {
  offlineItems.value = await allStore('offline_content')
  queue.value = await allStore('offline_queue')
}
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Mobile Learning</template>

  <div class="min-h-[calc(100vh-6rem)] bg-zinc-50 text-zinc-950">
    <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur">
      <div class="mx-auto flex max-w-md items-center gap-3">
        <div>
          <div class="text-sm font-semibold">EraLMS Mobile</div>
          <div class="text-xs text-zinc-500">{{ sessionUser?.full_name || 'Mobile Learner' }}</div>
        </div>
        <div class="ml-auto flex items-center gap-2">
          <span class="rounded-full px-2 py-1 text-xs font-medium" :class="online ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
            {{ online ? 'Online' : 'Offline' }}
          </span>
          <span v-if="pendingCount" class="rounded-full bg-zinc-900 px-2 py-1 text-xs font-medium text-white">{{ pendingCount }} pending</span>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-md px-4 pb-24 pt-4">
      <div v-if="loading" class="rounded-lg border border-zinc-200 bg-white p-4 text-sm text-zinc-500">Đang tải dữ liệu mobile...</div>
      <template v-else>
      <div v-if="actionMessage" class="mb-3 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">{{ actionMessage }}</div>

      <section v-if="activeTab === 'home'" class="space-y-4">
        <div class="rounded-lg bg-zinc-950 p-4 text-white">
          <div class="text-xs uppercase tracking-wide text-zinc-300">Continue Learning</div>
          <div class="mt-2 text-xl font-semibold">{{ continueLearning[0]?.title || myCourses[0]?.title }}</div>
          <div class="mt-3 h-2 rounded-full bg-zinc-700">
            <div class="h-2 rounded-full bg-cyan-400" :style="{ width: `${continueLearning[0]?.progress_percent || 0}%` }"></div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div v-for="item in notifications" :key="item.type" class="rounded-lg border border-zinc-200 bg-white p-3">
            <div class="text-xs font-medium uppercase text-zinc-500">{{ item.type }}</div>
            <div class="mt-1 text-sm font-semibold">{{ item.title }}</div>
            <div class="mt-1 text-xs text-zinc-500">{{ item.body }}</div>
          </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4">
          <div class="text-sm font-semibold">Mobile AI Tutor</div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              v-for="prompt in aiPrompts"
              :key="prompt"
              class="rounded-full px-3 py-1 text-xs"
              :class="selectedPrompt === prompt ? 'bg-cyan-700 text-white' : 'bg-cyan-50 text-cyan-800'"
              @click="choosePrompt(prompt)"
            >
              {{ prompt }}
            </button>
          </div>
          <div v-if="selectedPrompt" class="mt-3 rounded-md bg-zinc-50 p-3 text-xs text-zinc-600">Prompt đã chọn sẽ dùng cho chế độ học offline: {{ selectedPrompt }}</div>
        </div>
      </section>

      <section v-else-if="activeTab === 'courses'" class="space-y-3">
        <article v-for="course in myCourses" :key="course.id" class="rounded-lg border border-zinc-200 bg-white p-4">
          <div class="flex items-start gap-3">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md bg-cyan-100 text-lg font-bold text-cyan-900">{{ course.title.slice(0, 2) }}</div>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-semibold">{{ course.title }}</div>
              <div class="mt-1 text-xs text-zinc-500">{{ course.estimated_hours }} giờ · {{ course.download_size_mb }} MB</div>
              <div class="mt-2 h-1.5 rounded-full bg-zinc-100"><div class="h-1.5 rounded-full bg-cyan-600" :style="{ width: `${course.progress_percent}%` }"></div></div>
            </div>
          </div>
          <div class="mt-3 flex gap-2 overflow-x-auto">
            <button v-for="component in course.components" :key="component.id" class="shrink-0 rounded-md border border-zinc-200 px-3 py-2 text-xs" @click="saveLessonProgress(course, component, 100)">
              {{ component.type }} · {{ component.title }}
            </button>
          </div>
          <button class="mt-3 w-full rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="!course.offline_ready" @click="downloadCourse(course)">Download offline</button>
        </article>
        <div v-if="!myCourses.length" class="rounded-lg border border-zinc-200 bg-white p-4 text-sm text-zinc-500">Chưa có khóa học nào trong tenant hiện tại.</div>
      </section>

      <section v-else-if="activeTab === 'offline'" class="space-y-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
          <div class="flex items-center">
            <div class="text-sm font-semibold">Offline Content</div>
            <button class="ml-auto rounded-md border border-zinc-300 px-3 py-1.5 text-xs" @click="syncPending">Sync lại</button>
          </div>
          <div class="mt-1 text-xs text-zinc-500">{{ syncMessage || 'Video, PDF và lesson đã download sẽ học được khi mất mạng.' }}</div>
          <div class="mt-3 space-y-2">
            <div v-for="item in offlineItems" :key="item.id" class="rounded-md bg-zinc-50 p-3 text-sm">
              <div class="font-medium">{{ item.title }}</div>
              <div class="text-xs text-zinc-500">{{ item.assets.length }} nội dung · {{ new Date(item.savedAt).toLocaleString() }}</div>
            </div>
            <div v-if="!offlineItems.length" class="rounded-md bg-zinc-50 p-3 text-sm text-zinc-500">Chưa có khóa học nào được lưu offline.</div>
          </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4">
          <div class="text-sm font-semibold">Offline Quiz</div>
          <div class="mt-2 text-sm text-zinc-700">{{ quizDraft.question }}</div>
          <input v-model="quizDraft.answer" class="mt-3 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm" placeholder="Nhập câu trả lời" />
          <button class="mt-3 w-full rounded-md bg-cyan-700 px-3 py-2 text-sm font-medium text-white" @click="saveQuizAnswer">Lưu local</button>
        </div>
      </section>

      <section v-else-if="activeTab === 'attendance'" class="space-y-3">
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
          <div class="text-sm font-semibold">Mobile Attendance</div>
          <div class="mt-2 grid grid-cols-2 gap-2">
            <button class="rounded-md bg-zinc-900 px-3 py-3 text-sm font-medium text-white disabled:opacity-50" :disabled="!attendance.qr_enabled" @click="mobileCheckIn('qr')">QR Check-in</button>
            <button class="rounded-md border border-zinc-300 px-3 py-3 text-sm font-medium disabled:opacity-50" :disabled="!attendance.otp_enabled" @click="mobileCheckIn('otp')">OTP Check-in</button>
          </div>
          <div class="mt-3 text-xs text-zinc-500">Phiên hiện tại: {{ attendance.active_session_id || 'chưa mở' }} · {{ attendance.status }}</div>
          <div v-if="checkInResult" class="mt-3 rounded-md bg-zinc-50 p-3 text-sm">
            <div class="font-medium">{{ attendanceMode.toUpperCase() }} check-in</div>
            <div class="text-xs text-zinc-500">{{ checkInResult.status === 'ready' ? `Sẵn sàng gửi cho phiên #${checkInResult.sessionId}` : 'Không có phiên đang mở' }} · {{ checkInResult.at }}</div>
          </div>
        </div>
      </section>

      <section v-else class="space-y-3">
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
          <div class="text-sm font-semibold">Credential Wallet</div>
          <div class="mt-3 space-y-2 text-sm">
            <button v-for="certificate in wallet.certificates" :key="certificate.id" class="block w-full rounded-md bg-zinc-50 p-3 text-left" @click="showCredential('Certificate', certificate)">Certificate #{{ certificate.id }}</button>
            <button v-for="badge in wallet.badges" :key="badge.id" class="block w-full rounded-md bg-zinc-50 p-3 text-left" @click="showCredential('Badge', badge)">Badge #{{ badge.id }}</button>
            <div v-if="!wallet.certificates.length && !wallet.badges.length" class="text-zinc-500">Chưa có credential trong ví.</div>
          </div>
          <div v-if="selectedCredential" class="mt-3 rounded-md border border-zinc-200 p-3 text-sm">
            <div class="font-medium">{{ selectedCredential.type }} #{{ selectedCredential.credential.id }}</div>
            <div class="mt-1 text-xs text-zinc-500">Trạng thái: {{ selectedCredential.credential.status || 'issued' }}</div>
          </div>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
          <div class="text-sm font-semibold">Mobile Portfolio</div>
          <div class="mt-2 text-xs text-zinc-500">Portfolio artifact và chứng chỉ sẽ đồng bộ khi online.</div>
        </div>
      </section>
      </template>
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white">
      <div class="mx-auto grid max-w-md grid-cols-5">
        <button v-for="tab in tabs" :key="tab.key" class="flex h-16 flex-col items-center justify-center gap-1 text-xs" :class="activeTab === tab.key ? 'text-cyan-700' : 'text-zinc-500'" @click="activeTab = tab.key">
          <span class="text-lg leading-none">{{ tab.icon }}</span>
          <span>{{ tab.label }}</span>
        </button>
      </div>
    </nav>
  </div>
  </EraLmsLayout>
</template>
