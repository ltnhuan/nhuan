<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  ArrowRight,
  Award,
  Bell,
  Bot,
  CalendarDays,
  CheckCircle2,
  ClipboardList,
  Flame,
  GraduationCap,
  PlayCircle,
  RefreshCw,
  ShieldAlert,
  Sparkles,
  TrendingUp,
} from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(true)
const error = ref('')
const dashboard = ref(null)

const student = computed(() => dashboard.value?.student || {})
const progress = computed(() => dashboard.value?.learning_progress || {})
const courses = computed(() => dashboard.value?.continue_learning || [])
const tasks = computed(() => dashboard.value?.upcoming_tasks || [])
const schedule = computed(() => dashboard.value?.today_schedule || [])
const achievement = computed(() => dashboard.value?.achievement || {})
const risk = computed(() => dashboard.value?.risk_indicator || {})
const ai = computed(() => dashboard.value?.ai_recommendation || {})
const firstCourse = computed(() => courses.value[0] || null)
const firstTask = computed(() => tasks.value[0] || null)
const progressPercent = computed(() => Math.min(100, Number(progress.value.progress_percent || 0)))
const todayCards = computed(() => [
  firstCourse.value ? {
    label: 'Học tiếp',
    title: firstCourse.value.next_lesson || firstCourse.value.title,
    meta: firstCourse.value.title,
    href: firstCourse.value.actions?.continue || '/courses',
    action: 'Tiếp tục học',
    tone: 'blue',
  } : null,
  firstTask.value ? {
    label: 'Việc cần làm',
    title: firstTask.value.title,
    meta: `${firstTask.value.course || 'Khóa học'} · ${firstTask.value.deadline || 'Chưa có hạn'}`,
    href: firstTask.value.href || '/student/tasks',
    action: firstTask.value.action || 'Mở task',
    tone: 'amber',
  } : null,
  {
    label: 'AI Coach',
    title: ai.value.message || 'Chọn bài học ngắn nhất để giữ nhịp học hôm nay.',
    meta: ai.value.lesson || ai.value.quiz || ai.value.assignment || 'Đề xuất cá nhân hóa',
    href: '/ai',
    action: 'Xem gợi ý',
    tone: 'emerald',
  },
].filter(Boolean))

const aiCards = computed(() => [
  ai.value.lesson ? { label: 'Bài nên học', value: ai.value.lesson, href: firstCourse.value?.actions?.continue || '/courses' } : null,
  ai.value.quiz ? { label: 'Quiz nên làm', value: ai.value.quiz, href: firstTask.value?.href || '/student/tasks' } : null,
  ai.value.assignment ? { label: 'Assignment', value: ai.value.assignment, href: firstTask.value?.href || '/student/tasks' } : null,
].filter(Boolean))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/student-home', { headers: props.apiHeaders })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'Không tải được Student Home.')
    dashboard.value = payload.data || payload
  } catch (err) {
    error.value = err.message || 'Không tải được Student Home.'
  } finally {
    loading.value = false
  }
}

function riskClass(level) {
  return {
    low: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    medium: 'border-amber-200 bg-amber-50 text-amber-800',
    high: 'border-red-200 bg-red-50 text-red-700',
    critical: 'border-red-300 bg-red-100 text-red-800',
  }[level] || 'border-slate-200 bg-slate-50 text-slate-700'
}

function priorityClass(priority) {
  return {
    critical: 'border-red-200 bg-red-50 text-red-700',
    high: 'border-orange-200 bg-orange-50 text-orange-700',
    medium: 'border-amber-200 bg-amber-50 text-amber-700',
    low: 'border-slate-200 bg-slate-50 text-slate-700',
  }[priority] || 'border-slate-200 bg-slate-50 text-slate-700'
}

function cardTone(tone) {
  return {
    blue: 'border-blue-200 bg-blue-50',
    amber: 'border-amber-200 bg-amber-50',
    emerald: 'border-emerald-200 bg-emerald-50',
  }[tone] || 'border-slate-200 bg-white'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Home</template>
    <section class="mx-auto max-w-7xl space-y-4 px-3 pb-24 pt-3 sm:px-5">
      <div v-if="loading" class="rounded-md border bg-white p-6 text-sm text-slate-500">Đang tải Student Home...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <template v-else>
        <header class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
              <img :src="student.avatar_url || '/icons/pwa-192.png'" class="h-12 w-12 rounded-md object-cover" alt="" />
              <div class="min-w-0">
                <div class="text-xs font-semibold uppercase text-blue-700">Hôm nay học gì?</div>
                <h1 class="truncate text-xl font-bold text-slate-950">Chào {{ student.full_name || sessionUser?.full_name || 'bạn' }}</h1>
                <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                  <span>{{ student.class || 'Lớp học' }}</span>
                  <span>{{ student.major || 'Ngành' }}</span>
                  <span>{{ student.cohort || 'Khóa' }}</span>
                  <span>{{ student.campus || 'Campus' }}</span>
                </div>
              </div>
            </div>
            <div class="flex shrink-0 gap-2">
              <a href="/student/tasks" class="grid h-10 w-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-700" title="Thông báo">
                <Bell class="h-4 w-4" />
              </a>
              <a href="/ai" class="inline-flex h-10 items-center gap-2 rounded-md bg-slate-950 px-3 text-sm font-semibold text-white">
                <Bot class="h-4 w-4" /> AI Coach
              </a>
              <button class="grid h-10 w-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-700" :disabled="loading" title="Tải lại" @click="load">
                <RefreshCw class="h-4 w-4" />
              </button>
            </div>
          </div>
        </header>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
          <div class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 class="text-lg font-bold text-slate-950">Ưu tiên hôm nay</h2>
                  <p class="mt-1 text-sm text-slate-500">Mở app là biết ngay nên học, làm bài hay xử lý deadline nào trước.</p>
                </div>
                <a href="/student/tasks" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">
                  Tất cả task <ArrowRight class="h-4 w-4" />
                </a>
              </div>
              <div class="mt-4 grid gap-3 md:grid-cols-3">
                <a v-for="card in todayCards" :key="card.label" :href="card.href" class="rounded-md border p-4 transition hover:-translate-y-0.5 hover:shadow-md" :class="cardTone(card.tone)">
                  <div class="text-xs font-bold uppercase text-slate-500">{{ card.label }}</div>
                  <h3 class="mt-2 line-clamp-2 min-h-12 text-base font-bold text-slate-950">{{ card.title }}</h3>
                  <p class="mt-2 line-clamp-1 text-xs text-slate-600">{{ card.meta }}</p>
                  <div class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-slate-950">
                    {{ card.action }} <ArrowRight class="h-4 w-4" />
                  </div>
                </a>
              </div>
            </div>

            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-base font-bold text-slate-950"><PlayCircle class="h-5 w-5 text-blue-700" /> Continue Learning</h2>
                <a href="/courses" class="text-sm font-semibold text-blue-700">My Learning</a>
              </div>
              <div class="mt-4 grid gap-3 md:grid-cols-2">
                <article v-for="course in courses.slice(0, 4)" :key="course.enrollment_id || course.course_id" class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                  <div class="flex gap-3 p-3">
                    <img :src="course.thumbnail" class="h-24 w-28 shrink-0 rounded-md object-cover" alt="" />
                    <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-2">
                        <h3 class="line-clamp-2 font-bold text-slate-950">{{ course.title }}</h3>
                        <span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ course.progress }}%</span>
                      </div>
                      <p class="mt-1 truncate text-xs text-slate-500">{{ course.teacher }}</p>
                      <p class="mt-2 line-clamp-1 text-xs font-semibold text-slate-700">{{ course.next_lesson }}</p>
                      <div class="mt-3 h-2 rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, Number(course.progress || 0))}%` }"></div>
                      </div>
                    </div>
                  </div>
                  <div class="border-t border-slate-100 p-3">
                    <a :href="course.actions?.continue" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white">
                      Tiếp tục học <ArrowRight class="h-4 w-4" />
                    </a>
                  </div>
                </article>
                <div v-if="!courses.length" class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600 md:col-span-2">
                  Chưa có khóa đang học. Vào My Learning để xem khóa được gán hoặc quay lại sau khi giảng viên cập nhật nội dung.
                </div>
              </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-base font-bold text-slate-950"><ClipboardList class="h-5 w-5 text-amber-700" /> Việc cần làm</h2>
                <div class="mt-3 space-y-2">
                  <a v-for="task in tasks.slice(0, 5)" :key="task.id" :href="task.href" class="flex gap-3 rounded-md border border-slate-200 p-3 text-sm hover:bg-slate-50">
                    <span class="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500"></span>
                    <span class="min-w-0 flex-1">
                      <span class="line-clamp-1 font-bold text-slate-950">{{ task.title }}</span>
                      <span class="mt-1 block truncate text-xs text-slate-500">{{ task.course }} · {{ task.deadline || '-' }}</span>
                    </span>
                    <span class="shrink-0 rounded border px-2 py-1 text-xs font-semibold" :class="priorityClass(task.priority)">{{ task.type }}</span>
                  </a>
                  <div v-if="!tasks.length" class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">Không có task sắp tới.</div>
                </div>
              </div>

              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-base font-bold text-slate-950"><CalendarDays class="h-5 w-5 text-blue-700" /> Lịch hôm nay</h2>
                <div class="mt-3 space-y-2">
                  <a v-for="item in schedule.slice(0, 5)" :key="item.id" :href="item.href" class="block rounded-md border border-slate-200 p-3 text-sm hover:bg-slate-50">
                    <div class="font-bold text-slate-950">{{ item.title }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ item.type }} · {{ item.deadline || '-' }}</div>
                  </a>
                  <div v-if="!schedule.length" class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">Hôm nay chưa có lịch học hoặc deadline.</div>
                </div>
              </div>
            </section>
          </div>

          <aside class="space-y-4">
            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-base font-bold text-slate-950"><Sparkles class="h-5 w-5 text-emerald-700" /> AI Coach</h2>
              <div class="mt-3 rounded-md bg-slate-950 p-4 text-sm font-medium text-white">{{ ai.message || 'Giữ nhịp học hôm nay với một bài ngắn trước khi xử lý deadline.' }}</div>
              <div class="mt-3 space-y-2">
                <a v-for="item in aiCards" :key="item.label" :href="item.href" class="block rounded-md border border-slate-200 p-3 text-sm hover:bg-slate-50">
                  <div class="text-xs font-bold uppercase text-slate-500">{{ item.label }}</div>
                  <div class="mt-1 line-clamp-2 font-semibold text-slate-950">{{ item.value }}</div>
                </a>
              </div>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-base font-bold text-slate-950"><TrendingUp class="h-5 w-5 text-blue-700" /> Learning Progress</h2>
              <div class="mt-3 flex items-end justify-between gap-3">
                <div class="text-sm text-slate-600">
                  <div class="font-semibold text-slate-950">{{ progress.current_semester || 'Học kỳ hiện tại' }}</div>
                  <div>{{ progress.program || 'Chương trình đào tạo' }}</div>
                  <div class="mt-2">{{ progress.completed_credits || 0 }}/{{ progress.total_credits || 0 }} tín chỉ</div>
                </div>
                <div class="text-right">
                  <div class="text-3xl font-bold text-slate-950">{{ progressPercent.toFixed(1) }}%</div>
                  <div class="mt-2 h-2 w-32 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-blue-600" :style="{ width: `${progressPercent}%` }"></div>
                  </div>
                </div>
              </div>
            </section>

            <section class="grid grid-cols-2 gap-3">
              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-500"><Flame class="h-4 w-4 text-orange-600" /> Streak</div>
                <div class="mt-2 text-2xl font-bold text-slate-950">{{ achievement.streak || 0 }}</div>
              </div>
              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-500"><Award class="h-4 w-4 text-amber-600" /> XP</div>
                <div class="mt-2 text-2xl font-bold text-slate-950">{{ achievement.xp || 0 }}</div>
              </div>
            </section>

            <section class="rounded-md border p-4 shadow-sm" :class="riskClass(risk.level)">
              <h2 class="flex items-center gap-2 text-base font-bold"><ShieldAlert class="h-5 w-5" /> Risk Indicator</h2>
              <div class="mt-2 text-2xl font-bold capitalize">{{ risk.level || 'low' }}</div>
              <div class="mt-1 text-sm">Score {{ Number(risk.score || 0).toFixed(1) }}</div>
            </section>

            <a href="/gradebook/student" class="flex items-center justify-between rounded-md border border-slate-200 bg-white p-4 text-sm font-semibold shadow-sm hover:bg-slate-50">
              <span class="inline-flex items-center gap-2"><GraduationCap class="h-4 w-4 text-blue-700" /> Grade Center</span>
              <span>Open</span>
            </a>
          </aside>
        </section>

        <div v-if="firstCourse" class="fixed bottom-20 left-3 right-3 z-30 rounded-md border border-slate-800 bg-slate-950 p-3 text-white shadow-2xl shadow-slate-950/25 lg:bottom-4 lg:left-auto lg:right-6 lg:w-[420px]">
          <div class="flex items-center gap-3">
            <PlayCircle class="h-5 w-5 shrink-0 text-cyan-300" />
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-bold">{{ firstCourse.next_lesson || firstCourse.title }}</div>
              <div class="truncate text-xs text-slate-300">{{ firstCourse.title }}</div>
            </div>
            <a :href="firstCourse.actions?.continue" class="shrink-0 rounded-md bg-cyan-400 px-3 py-2 text-sm font-bold text-slate-950">Tiếp tục</a>
          </div>
        </div>
      </template>
    </section>
  </EraLmsLayout>
</template>
