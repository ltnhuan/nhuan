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

const courses = ref([])
const loadingPage = ref(false)
const creating = ref(false)
const { loading, toast, validationErrors, runAction } = useLmsAction(props.apiHeaders)

const form = ref({
  code: `COURSE-${Date.now().toString().slice(-5)}`,
  title: 'Khóa học mới',
  level: 'college',
  course_type: 'blended',
  visibility: 'tenant',
  language: 'vi',
})

const rowActions = computed(() => [
  { action_key: 'course.clone', label: 'Nhân bản', route: (course) => `/api/v1/courses/${course.id}/clone`, method: 'POST' },
  { action_key: 'course.submit_review', label: 'Gửi duyệt', route: (course) => `/api/v1/courses/${course.id}/submit-review`, method: 'POST', confirm_required: true, confirm_message: 'Gửi khóa học sang duyệt?' },
  { action_key: 'course.approve', label: 'Duyệt', route: (course) => `/api/v1/courses/${course.id}/approve`, method: 'POST', confirm_required: true, confirm_message: 'Duyệt khóa học này?' },
  { action_key: 'course.publish', label: 'Phát hành', route: (course) => `/api/v1/courses/${course.id}/publish`, method: 'POST', confirm_required: true, confirm_message: 'Phát hành khóa học cho người học?' },
  { action_key: 'course.archive', label: 'Lưu trữ', route: (course) => `/api/v1/courses/${course.id}/archive`, method: 'POST', confirm_required: true, confirm_message: 'Lưu trữ khóa học này?' },
])

async function load() {
  loadingPage.value = true
  try {
    const response = await fetch('/api/v1/courses?per_page=50', { headers: props.apiHeaders })
    const payload = await response.json()
    courses.value = payload.data || []
  } finally {
    loadingPage.value = false
  }
}

async function createCourse() {
  await runAction({
    actionKey: 'course.create',
    url: '/api/v1/courses',
    method: 'POST',
    body: form.value,
    reload: async () => {
      creating.value = false
      form.value.code = `COURSE-${Date.now().toString().slice(-5)}`
      form.value.title = 'Khóa học mới'
      await load()
    },
  })
}

async function runRowAction(action, course) {
  await runAction({
    actionKey: `${action.action_key}:${course.id}`,
    url: action.route(course),
    method: action.method,
    confirm: action.confirm_required,
    confirmMessage: action.confirm_message,
    reload: load,
  })
}

function openStudio(course) {
  window.location.href = `/courses/studio?course_id=${course.id}`
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Khóa học / Quản lý</template>

    <section class="space-y-5">
      <div v-if="toast.show" class="rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h1 class="text-lg font-bold text-slate-950">Quản trị khóa học</h1>
            <p class="mt-1 text-sm text-slate-600">Tạo, nhân bản, gửi duyệt, phát hành và lưu trữ bằng API thật.</p>
          </div>
          <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" :disabled="loadingPage" @click="creating = true">Tạo khóa học</button>
        </div>

        <div v-if="creating" class="border-b border-slate-200 bg-slate-50 p-5">
          <div class="grid gap-3 md:grid-cols-5">
            <label class="text-xs font-semibold text-slate-600">Mã<input v-model="form.code" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
            <label class="text-xs font-semibold text-slate-600 md:col-span-2">Tên khóa học<input v-model="form.title" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
            <label class="text-xs font-semibold text-slate-600">Cấp độ<input v-model="form.level" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
            <label class="text-xs font-semibold text-slate-600">Loại học<input v-model="form.course_type" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
          </div>
          <div class="mt-3 flex gap-2">
            <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading['course.create']" @click="createCourse">Lưu</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" :disabled="loading['course.create']" @click="creating = false">Hủy</button>
          </div>
          <pre v-if="Object.keys(validationErrors).length" class="mt-3 text-xs text-red-700">{{ validationErrors }}</pre>
        </div>

        <div class="overflow-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Mã</th>
                <th class="px-4 py-3">Tên</th>
                <th class="px-4 py-3">Cấp độ</th>
                <th class="px-4 py-3">Loại</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="loadingPage"><td colspan="6" class="px-4 py-6 text-center text-slate-500">Đang tải dữ liệu...</td></tr>
              <tr v-for="course in courses" v-else :key="course.id">
                <td class="px-4 py-3 font-semibold">{{ course.code }}</td>
                <td class="px-4 py-3">{{ course.title }}</td>
                <td class="px-4 py-3">{{ course.level }}</td>
                <td class="px-4 py-3">{{ course.course_type }}</td>
                <td class="px-4 py-3"><StatusBadge :status="course.status" /></td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-2">
                    <button class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" @click="openStudio(course)">Sửa trong Studio</button>
                    <ActionBar :actions="rowActions" :loading-map="loading" @run="(action) => runRowAction(action, course)" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </EraLmsLayout>
</template>
