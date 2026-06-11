<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Bookmark, Eye, Filter, Heart, Play, RefreshCw, Search } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(true)
const error = ref('')
const payload = ref(null)
const q = ref('')
const filters = ref({ category: '', faculty: '', semester: '' })

const sections = computed(() => payload.value?.sections || {})
const allCourses = computed(() => payload.value?.all || [])
const primaryCourse = computed(() => sections.value?.continue_learning?.[0] || allCourses.value[0] || null)
const sectionRows = computed(() => [
  ['continue_learning', 'Tiếp tục học'],
  ['in_progress', 'Đang học'],
  ['upcoming', 'Sắp mở'],
  ['completed', 'Đã hoàn thành'],
  ['archived', 'Lưu trữ'],
])

const categoryOptions = computed(() => [...new Set(allCourses.value.map((course) => course.category).filter(Boolean))])
const facultyOptions = computed(() => [...new Set(allCourses.value.map((course) => course.faculty).filter(Boolean))])
const semesterOptions = computed(() => [...new Set(allCourses.value.map((course) => course.semester).filter(Boolean))])

let debounceId = null
watch([q, filters], () => {
  clearTimeout(debounceId)
  debounceId = setTimeout(load, 250)
}, { deep: true })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (q.value.trim()) params.set('q', q.value.trim())
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value) params.set(key, value)
    })
    const response = await fetch(`/api/v1/student/courses${params.toString() ? `?${params}` : ''}`, { headers: props.apiHeaders })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message || 'Không tải được My Learning.')
    payload.value = data.data || data
  } catch (err) {
    error.value = err.message || 'Không tải được My Learning.'
  } finally {
    loading.value = false
  }
}

function progressWidth(course) {
  return `${Math.min(100, Math.max(0, Number(course?.progress || 0)))}%`
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>My Learning</template>
    <section class="mx-auto max-w-7xl space-y-5 px-3 pb-24 pt-3 sm:px-5">
      <header class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div class="text-xs font-bold uppercase text-blue-700">Learning shelf</div>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">My Learning</h1>
          </div>
          <button class="grid h-10 w-10 place-items-center rounded-md bg-slate-950 text-white disabled:opacity-60" :disabled="loading" title="Tải lại" @click="load">
            <RefreshCw class="h-4 w-4" />
          </button>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_180px_180px_180px]">
          <label class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input v-model="q" class="h-11 w-full rounded-md border border-slate-300 pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Tìm khóa, giảng viên, kỹ năng" />
          </label>
          <label class="relative">
            <Filter class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <select v-model="filters.category" class="h-11 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm">
              <option value="">Category</option>
              <option v-for="item in categoryOptions" :key="item" :value="item">{{ item }}</option>
            </select>
          </label>
          <select v-model="filters.faculty" class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm">
            <option value="">Faculty</option>
            <option v-for="item in facultyOptions" :key="item" :value="item">{{ item }}</option>
          </select>
          <select v-model="filters.semester" class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm">
            <option value="">Semester</option>
            <option v-for="item in semesterOptions" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>
      </header>

      <div v-if="loading" class="rounded-md border bg-white p-6 text-sm text-slate-500">Đang tải khóa học...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <template v-else>
        <section v-if="primaryCourse" class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
          <div class="grid lg:grid-cols-[360px_minmax(0,1fr)]">
            <img :src="primaryCourse.thumbnail" class="h-56 w-full object-cover lg:h-full" alt="" />
            <div class="p-5">
              <div class="text-xs font-bold uppercase text-blue-700">Học tiếp ngay</div>
              <h2 class="mt-2 text-2xl font-bold text-slate-950">{{ primaryCourse.title }}</h2>
              <p class="mt-1 text-sm text-slate-500">{{ primaryCourse.teacher }}</p>
              <p class="mt-4 line-clamp-2 text-sm font-semibold text-slate-700">{{ primaryCourse.next_lesson }}</p>
              <div class="mt-4 h-2 rounded-full bg-slate-100">
                <div class="h-2 rounded-full bg-blue-600" :style="{ width: progressWidth(primaryCourse) }"></div>
              </div>
              <div class="mt-4 flex flex-wrap items-center gap-2">
                <a :href="primaryCourse.actions?.continue" class="inline-flex h-10 items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-bold text-white">
                  <Play class="h-4 w-4" /> Tiếp tục học
                </a>
                <a :href="primaryCourse.actions?.preview" class="grid h-10 w-10 place-items-center rounded-md border border-slate-300 text-slate-700" title="Xem lộ trình">
                  <Eye class="h-4 w-4" />
                </a>
                <button class="grid h-10 w-10 place-items-center rounded-md border border-slate-300 text-slate-700" title="Bookmark">
                  <Bookmark class="h-4 w-4" />
                </button>
                <button class="grid h-10 w-10 place-items-center rounded-md border border-slate-300 text-slate-700" title="Favorite">
                  <Heart class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </section>

        <section v-for="[key, label] in sectionRows" :key="key" class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-950">{{ label }}</h2>
            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ (sections[key] || []).length }}</span>
          </div>
          <div class="overflow-x-auto pb-2">
            <div class="flex min-w-max gap-3">
              <article v-for="course in sections[key] || []" :key="`${key}-${course.enrollment_id || course.course_id}`" class="w-80 overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="relative">
                  <img :src="course.thumbnail" class="h-40 w-full object-cover" alt="" />
                  <span class="absolute right-3 top-3 rounded-md bg-white/95 px-2 py-1 text-xs font-bold text-slate-800">{{ course.progress }}%</span>
                </div>
                <div class="p-3">
                  <h3 class="line-clamp-2 min-h-11 font-bold text-slate-950">{{ course.title }}</h3>
                  <p class="mt-1 truncate text-xs text-slate-500">{{ course.teacher }}</p>
                  <p class="mt-3 line-clamp-1 text-sm font-semibold text-slate-700">{{ course.next_lesson }}</p>
                  <div class="mt-3 h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-blue-600" :style="{ width: progressWidth(course) }"></div>
                  </div>
                  <div class="mt-3 flex items-center justify-between gap-2">
                    <a :href="course.actions?.continue" class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-md bg-slate-950 px-3 text-sm font-bold text-white">
                      <Play class="h-4 w-4" /> Tiếp tục
                    </a>
                    <a :href="course.actions?.preview" class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-300 text-slate-700" title="Preview">
                      <Eye class="h-4 w-4" />
                    </a>
                    <button class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-300 text-slate-700" title="Bookmark">
                      <Bookmark class="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </article>
              <div v-if="!(sections[key] || []).length" class="w-80 rounded-md border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-600">
                Chưa có khóa trong mục này. Khi có dữ liệu, khóa sẽ xuất hiện ở đây.
              </div>
            </div>
          </div>
        </section>

        <div v-if="!allCourses.length" class="rounded-md border border-dashed border-slate-300 bg-white p-8 text-center">
          <div class="mx-auto grid h-12 w-12 place-items-center rounded-md bg-blue-50 text-blue-700">
            <Search class="h-6 w-6" />
          </div>
          <h2 class="mt-4 text-lg font-bold text-slate-950">Chưa tìm thấy khóa học</h2>
          <p class="mt-2 text-sm text-slate-500">Thử đổi bộ lọc hoặc quay lại Home để xem bài học được đề xuất.</p>
          <a href="/" class="mt-4 inline-flex rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white">Về Home</a>
        </div>

        <div v-if="primaryCourse" class="fixed bottom-20 left-3 right-3 z-30 rounded-md border border-slate-800 bg-slate-950 p-3 text-white shadow-2xl shadow-slate-950/25 lg:bottom-4 lg:left-auto lg:right-6 lg:w-[420px]">
          <div class="flex items-center gap-3">
            <Play class="h-5 w-5 shrink-0 text-cyan-300" />
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-bold">{{ primaryCourse.next_lesson || primaryCourse.title }}</div>
              <div class="truncate text-xs text-slate-300">{{ primaryCourse.title }}</div>
            </div>
            <a :href="primaryCourse.actions?.continue" class="shrink-0 rounded-md bg-cyan-400 px-3 py-2 text-sm font-bold text-slate-950">Tiếp tục</a>
          </div>
        </div>
      </template>
    </section>
  </EraLmsLayout>
</template>
