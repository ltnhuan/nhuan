<script setup>
import { computed, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const notice = ref('')
const currentItemId = ref(102)
const course = ref({ title: 'EraLMS Enterprise Foundations', progress: 46, target: 'Hoàn thành trước 30/06/2026' })
const sections = ref([
  {
    title: 'Chương 1: Nền tảng',
    progress: 75,
    items: [
      { id: 101, title: 'Bài 1: Tổng quan hệ thống', status: 'Hoàn thành', progress: 100, locked: false, message: '' },
      { id: 102, title: 'Bài 2: Quy trình học', status: 'Đang học', progress: 46, locked: false, message: '' },
      { id: 103, title: 'Quiz Chương 1', status: 'Bị khóa', progress: 0, locked: true, message: 'Cần hoàn thành Bài 2 và đạt video watch 90% để mở quiz.' },
    ],
  },
  {
    title: 'Chương 2: Thực hành',
    progress: 10,
    items: [
      { id: 104, title: 'Video: Thiết lập khóa học', status: 'Chưa học', progress: 0, locked: false, message: '' },
      { id: 105, title: 'Assignment: Thiết kế outline', status: 'Chờ duyệt', progress: 80, locked: false, message: 'Bài đã nộp, đang chờ giảng viên duyệt hoàn thành.' },
    ],
  },
])

const currentItem = computed(() => sections.value.flatMap((section) => section.items).find((item) => item.id === currentItemId.value))
const nextUnlockedItem = computed(() => sections.value.flatMap((section) => section.items).find((item) => !item.locked && item.status !== 'Hoàn thành'))
const timeline = ref([
  'Video giới thiệu: xem 82%',
  'PDF chương 1: xác nhận đã đọc',
  'Quiz Chương 1: chưa mở vì thiếu điều kiện',
])

function openItem(item) {
  currentItemId.value = item.id
  if (item.locked) {
    notice.value = item.message
    return
  }

  notice.value = `Đã mở ${item.title}.`
}

function continueLearning() {
  const item = nextUnlockedItem.value
  if (!item) return
  openItem(item)
}

function markProgress() {
  const item = currentItem.value
  if (!item || item.locked) return
  item.progress = Math.min(100, item.progress + 20)
  item.status = item.progress >= 100 ? 'Hoàn thành' : 'Đang học'
  timeline.value.unshift(`${item.title}: cập nhật ${item.progress}%`)
  course.value.progress = Math.min(100, course.value.progress + 5)
  notice.value = item.progress >= 100 ? `${item.title} đã hoàn thành.` : `Đã cập nhật tiến độ ${item.title}.`
}

function requestApproval() {
  notice.value = 'Đã gửi yêu cầu duyệt hoàn thành tới giảng viên.'
  timeline.value.unshift('Gửi yêu cầu duyệt hoàn thành assignment')
}

function badgeClass(item) {
  if (item.locked) return 'bg-amber-50 text-amber-800 ring-amber-200'
  if (item.status === 'Hoàn thành') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (item.status === 'Chờ duyệt') return 'bg-violet-50 text-violet-700 ring-violet-200'
  if (item.status === 'Đang học') return 'bg-blue-50 text-blue-700 ring-blue-200'
  return 'bg-slate-100 text-slate-700 ring-slate-200'
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Đào tạo / Tiến độ cá nhân</template>

    <section class="border-b border-slate-200 bg-white">
      <div class="mx-auto max-w-6xl px-4 py-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div class="text-xs font-semibold uppercase text-cyan-700">Learner journey</div>
            <h1 class="mt-1 text-xl font-bold text-slate-950">{{ course.title }}</h1>
            <p class="mt-2 text-sm text-slate-600">Hiển thị rõ bài đang học, lý do bị khóa, hành động tiếp theo và timeline gần đây.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a href="/learning-path" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Xem lộ trình</a>
            <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white" @click="continueLearning">Tiếp tục học</button>
          </div>
        </div>
        <div class="mt-4 h-2 rounded-full bg-slate-100">
          <div class="h-2 rounded-full bg-emerald-600" :style="{ width: `${course.progress}%` }"></div>
        </div>
        <div class="mt-2 flex flex-wrap justify-between gap-2 text-sm text-slate-600"><span>{{ course.progress }}% hoàn thành</span><span>{{ course.target }}</span></div>
        <div v-if="notice" class="mt-4 rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ notice }}</div>
      </div>
    </section>

    <section class="mx-auto grid max-w-6xl gap-4 px-4 py-5 sm:px-6 lg:grid-cols-[1fr_320px]">
      <main class="space-y-4">
        <article v-for="section in sections" :key="section.title" class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-slate-950">{{ section.title }}</h2>
            <span class="text-sm text-slate-600">{{ section.progress }}%</span>
          </div>
          <div class="mt-3 h-1.5 rounded-full bg-slate-100">
            <div class="h-1.5 rounded-full bg-cyan-600" :style="{ width: `${section.progress}%` }"></div>
          </div>
          <div class="mt-4 divide-y divide-slate-100">
            <button v-for="item in section.items" :key="item.id" class="grid w-full gap-2 py-3 text-left sm:grid-cols-[1fr_auto]" :class="{ 'opacity-80': item.locked, 'bg-cyan-50/40 px-2': currentItemId === item.id }" @click="openItem(item)">
              <div>
                <div class="text-sm font-semibold text-slate-950">{{ item.title }}</div>
                <div class="mt-2 h-1.5 max-w-sm rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-cyan-600" :style="{ width: `${item.progress}%` }"></div></div>
                <p v-if="item.message" class="mt-2 text-sm text-amber-800">{{ item.message }}</p>
              </div>
              <span class="inline-flex h-7 items-center rounded-full px-2.5 text-xs font-semibold ring-1" :class="badgeClass(item)">{{ item.status }}</span>
            </button>
          </div>
        </article>
      </main>

      <aside class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-bold text-slate-950">Đang chọn</h2>
          <p class="mt-2 text-sm font-semibold text-slate-800">{{ currentItem?.title }}</p>
          <p class="mt-1 text-sm text-slate-600">Trạng thái: {{ currentItem?.status }}</p>
          <div class="mt-3 grid gap-2">
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="currentItem?.locked" @click="markProgress">Cập nhật tiến độ +20%</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="requestApproval">Yêu cầu duyệt</button>
          </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-bold text-slate-950">Timeline gần đây</h2>
          <ol class="mt-3 space-y-3 text-sm text-slate-700">
            <li v-for="event in timeline" :key="event" class="rounded-md border border-slate-200 p-3">{{ event }}</li>
          </ol>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
