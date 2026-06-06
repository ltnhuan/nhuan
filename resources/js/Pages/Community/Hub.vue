<script setup>
import { computed, onMounted, ref } from 'vue'
import { Bell, BookOpen, CheckCircle2, Flag, MessageCircle, RefreshCw, ShieldCheck, Trophy, Users } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(false)
const errors = ref([])
const selectedForum = ref(null)
const selectedThread = ref(null)
const selectedDetail = ref(null)
const data = ref({
  forums: [],
  threads: [],
  posts: [],
  groups: [],
  wikis: [],
  blogs: [],
  reports: [],
  notifications: [],
  reputation: [],
  analytics: {},
})

const resources = [
  ['forums', '/api/v1/community/forums?per_page=50'],
  ['groups', '/api/v1/community/groups?per_page=50'],
  ['wikis', '/api/v1/community/wikis?per_page=50'],
  ['blogs', '/api/v1/community/blogs?per_page=50'],
  ['reports', '/api/v1/community/reports?per_page=50'],
  ['notifications', '/api/v1/community/notifications?per_page=50'],
  ['reputation', '/api/v1/community/reputation?per_page=20'],
  ['analytics', '/api/v1/community/analytics'],
]

const quickLinks = [
  { label: 'Forum dashboard', href: '#community-forums', icon: MessageCircle, metric: 'forums' },
  { label: 'Thread & post', href: '#community-threads', icon: CheckCircle2, metric: 'threads' },
  { label: 'Wiki pages', href: '#community-wiki', icon: BookOpen, metric: 'wikis' },
  { label: 'Blog cộng đồng', href: '#community-blog', icon: BookOpen, metric: 'blogs' },
  { label: 'Nhóm học tập', href: '#community-groups', icon: Users, metric: 'groups' },
  { label: 'Kiểm duyệt', href: '#community-moderation', icon: ShieldCheck, metric: 'reports' },
]

const totalContent = computed(() => data.value.forums.length + data.value.threads.length + data.value.posts.length + data.value.groups.length + data.value.wikis.length + data.value.blogs.length)
const activeTopics = computed(() => data.value.analytics.active_topics ?? data.value.threads.length)
const activeUsers = computed(() => data.value.analytics.active_users ?? data.value.reputation.length)
const engagementScore = computed(() => data.value.analytics.engagement_score ?? data.value.threads.reduce((sum, thread) => sum + (thread.reply_count || 0) + (thread.like_count || 0), 0))
const unreadNotifications = computed(() => data.value.notifications.filter((item) => !item.read_at).length)
const topReputation = computed(() => data.value.reputation[0] || { points: 0, rank: 'new_member', badge_count: 0 })
const maxForumTotal = computed(() => Math.max(1, ...data.value.forums.map((forum) => forumTotal(forum))))
const forumChart = computed(() => data.value.forums.map((forum) => ({
  ...forum,
  total: forumTotal(forum),
  percent: Math.max(7, Math.round((forumTotal(forum) / maxForumTotal.value) * 100)),
})))
const mix = computed(() => [
  { label: 'Forum', value: data.value.forums.length, color: '#0891b2' },
  { label: 'Thread', value: data.value.threads.length, color: '#4f46e5' },
  { label: 'Post', value: data.value.posts.length, color: '#059669' },
  { label: 'Wiki', value: data.value.wikis.length, color: '#d97706' },
  { label: 'Blog', value: data.value.blogs.length, color: '#e11d48' },
  { label: 'Group', value: data.value.groups.length, color: '#7c3aed' },
])
const donutStyle = computed(() => {
  let cursor = 0
  const total = Math.max(1, mix.value.reduce((sum, item) => sum + item.value, 0))
  const stops = mix.value.map((item) => {
    const start = cursor
    cursor += (item.value / total) * 100
    return `${item.color} ${start}% ${cursor}%`
  })
  return { background: `conic-gradient(${stops.join(', ')})` }
})
const recentActivity = computed(() => [
  ...data.value.threads.map((item) => ({ type: 'Topic', title: item.title, at: item.last_activity_at || item.updated_at, tone: 'bg-indigo-50 text-indigo-700' })),
  ...data.value.posts.map((item) => ({ type: 'Post', title: item.body_text || stripHtml(item.body_html), at: item.created_at, tone: 'bg-emerald-50 text-emerald-700' })),
  ...data.value.wikis.map((item) => ({ type: 'Wiki', title: item.title, at: item.updated_at, tone: 'bg-amber-50 text-amber-700' })),
  ...data.value.blogs.map((item) => ({ type: 'Blog', title: item.title, at: item.published_at || item.updated_at, tone: 'bg-rose-50 text-rose-700' })),
].filter((item) => item.at).sort((a, b) => new Date(b.at) - new Date(a.at)).slice(0, 8))
const healthChecks = computed(() => [
  { label: 'Forum', value: data.value.forums.length, ok: data.value.forums.length > 0 },
  { label: 'Thread', value: data.value.threads.length, ok: data.value.threads.length > 0 },
  { label: 'Post', value: data.value.posts.length, ok: data.value.posts.length > 0 },
  { label: 'Wiki', value: data.value.wikis.length, ok: data.value.wikis.length > 0 },
  { label: 'Blog', value: data.value.blogs.length, ok: data.value.blogs.length > 0 },
  { label: 'Group', value: data.value.groups.length, ok: data.value.groups.length > 0 },
  { label: 'Reputation', value: data.value.reputation.length, ok: data.value.reputation.length > 0 },
  { label: 'Moderation', value: data.value.reports.length, ok: true },
])

const DataBlock = {
  props: {
    title: { type: String, required: true },
    type: { type: String, required: true },
    items: { type: Array, default: () => [] },
  },
  emits: ['select'],
  methods: {
    rowTitle(item) {
      return item.title || item.name || item.reason || item.subject || item.code || `#${item.id}`
    },
  },
  template: `
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-200 p-4">
        <h2 class="text-sm font-semibold text-slate-950">{{ title }}</h2>
      </div>
      <div class="divide-y divide-slate-100">
        <button v-for="item in items.slice(0, 8)" :key="item.id" class="block w-full p-4 text-left hover:bg-slate-50 focus:bg-slate-50 focus:outline-none" type="button" @click="$emit('select', type, item)">
          <div class="font-semibold text-slate-950">{{ rowTitle(item) }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ item.status || item.forum_type || item.group_type || item.wiki_type || item.blog_type || item.reportable_type || 'active' }}</div>
          <div class="mt-2 text-xs font-semibold text-cyan-700">Mở chi tiết</div>
        </button>
        <div v-if="!items.length" class="p-8 text-center text-sm text-slate-500">Chưa có dữ liệu.</div>
      </div>
    </section>
  `,
}

onMounted(loadHub)

async function loadHub() {
  loading.value = true
  errors.value = []

  const results = await Promise.allSettled(resources.map(([key, url]) => fetchResource(key, url)))
  for (const result of results) {
    if (result.status === 'fulfilled') {
      const { key, payload } = result.value
      data.value[key] = key === 'analytics' ? (payload.data ?? payload) : dataList(payload)
    } else {
      errors.value.push(result.reason)
    }
  }

  selectedForum.value = data.value.forums[0] || null
  await loadForumThreads(selectedForum.value)
  loading.value = false
}

async function loadForumThreads(forum) {
  selectedForum.value = forum
  selectedThread.value = null
  data.value.threads = []
  data.value.posts = []
  if (!forum?.id) return

  try {
    data.value.threads = dataList(await getJson(`/api/v1/community/forums/${forum.id}/threads?per_page=50`))
    selectedThread.value = data.value.threads[0] || null
    await loadThreadPosts(selectedThread.value)
  } catch (error) {
    errors.value.push({ key: 'threads', message: error.message || 'Không load được thread' })
  }
}

async function loadThreadPosts(thread) {
  selectedThread.value = thread
  data.value.posts = []
  if (!thread?.id) return

  try {
    const payload = await getJson(`/api/v1/community/threads/${thread.id}`)
    selectedThread.value = payload
    data.value.posts = payload.posts || []
  } catch (error) {
    errors.value.push({ key: 'posts', message: error.message || 'Không load được post' })
  }
}

async function fetchResource(key, url) {
  return { key, payload: await getJson(url) }
}

async function getJson(url) {
  const response = await fetch(url, { headers: props.apiHeaders })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.message || `HTTP ${response.status}`)
  return payload
}

function dataList(payload) {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  return []
}

function forumTotal(forum) {
  return (forum.thread_count || forum.threads_count || 0) + (forum.post_count || 0)
}

function titleOf(item) {
  return item.title || item.name || item.subject || item.code || `#${item.id}`
}

function number(value) {
  return new Intl.NumberFormat('vi-VN').format(value || 0)
}

function date(value) {
  if (!value) return 'Chưa có'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function stripHtml(value) {
  return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

function metricValue(key) {
  if (key === 'threads') {
    return number(data.value.forums.reduce((sum, forum) => sum + (forum.thread_count || forum.threads_count || 0), 0) || data.value.threads.length)
  }
  if (key === 'posts') {
    return number(data.value.forums.reduce((sum, forum) => sum + (forum.post_count || 0), 0) || data.value.posts.length)
  }
  return number(data.value[key]?.length || 0)
}

function openDetail(type, item) {
  selectedDetail.value = { type, item }
}

function detailRows(detail) {
  if (!detail?.item) return []
  const item = detail.item
  const rows = [
    ['Loại', item.wiki_type || item.blog_type || item.group_type || item.reportable_type || detail.type],
    ['Trạng thái', item.status || 'active'],
    ['ID', item.id],
  ]

  if (detail.type === 'wiki') {
    rows.push(['Slug', item.slug], ['Version', item.version], ['Cập nhật', date(item.updated_at)])
  } else if (detail.type === 'blog') {
    rows.push(['Likes', number(item.like_count)], ['Bình luận', number(item.comment_count)], ['Xuất bản', date(item.published_at)])
  } else if (detail.type === 'group') {
    rows.push(['Thành viên', number(item.member_count)], ['Hiển thị', item.visibility || 'members'])
  } else if (detail.type === 'moderation') {
    rows.push(['Đối tượng', `${item.reportable_type || '-'} #${item.reportable_id || '-'}`], ['Lý do', item.reason], ['Người báo cáo', item.reported_by])
  }

  return rows.filter(([_, value]) => value !== undefined && value !== null && value !== '')
}
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Cộng đồng học tập / Dashboard</template>

    <section class="mx-auto max-w-7xl space-y-5 px-4 py-5 sm:px-6">
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-0 lg:grid-cols-[1fr_380px]">
          <div class="p-5">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase text-slate-500">
              <span>Learning Community</span>
              <span class="rounded-full bg-emerald-50 px-2 py-1 text-emerald-700">Live API data</span>
            </div>
            <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <h1 class="text-2xl font-semibold text-slate-950">Dashboard cộng đồng học tập</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">Tổng hợp forum, Q&A, post, wiki, blog, nhóm học tập, reputation, notification và queue kiểm duyệt từ API community.</p>
              </div>
              <button class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading" @click="loadHub">
                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /> {{ loading ? 'Đang tải' : 'Tải lại' }}
              </button>
            </div>
          </div>
          <div class="border-t border-slate-200 bg-slate-950 p-5 text-white lg:border-l lg:border-t-0">
            <div class="text-xs font-semibold uppercase text-slate-300">Community health</div>
            <div class="mt-4 grid grid-cols-4 gap-2">
              <div v-for="item in healthChecks" :key="item.label" class="rounded-md bg-white/10 p-2">
                <CheckCircle2 class="h-4 w-4" :class="item.ok ? 'text-emerald-300' : 'text-slate-500'" />
                <div class="mt-2 text-lg font-semibold">{{ number(item.value) }}</div>
                <div class="truncate text-[11px] text-slate-300">{{ item.label }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-4">
        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><Users class="h-5 w-5 text-cyan-600" /><div class="mt-3 text-xs font-semibold uppercase text-slate-500">Active users</div><div class="mt-1 text-2xl font-semibold">{{ number(activeUsers) }}</div></article>
        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><MessageCircle class="h-5 w-5 text-indigo-600" /><div class="mt-3 text-xs font-semibold uppercase text-slate-500">Active topics</div><div class="mt-1 text-2xl font-semibold">{{ number(activeTopics) }}</div></article>
        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><Trophy class="h-5 w-5 text-amber-600" /><div class="mt-3 text-xs font-semibold uppercase text-slate-500">Engagement score</div><div class="mt-1 text-2xl font-semibold">{{ number(engagementScore) }}</div></article>
        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><Bell class="h-5 w-5 text-rose-600" /><div class="mt-3 text-xs font-semibold uppercase text-slate-500">Unread notifications</div><div class="mt-1 text-2xl font-semibold">{{ number(unreadNotifications) }}</div></article>
      </div>

      <div v-if="errors.length" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ errors.length }} endpoint chưa trả dữ liệu. Dashboard vẫn hiển thị các phần đã load thành công.
      </div>

      <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <a
          v-for="link in quickLinks"
          :key="link.href"
          class="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md"
          :href="link.href"
        >
          <component :is="link.icon" class="h-5 w-5 text-slate-700 transition group-hover:text-cyan-700" />
          <div class="mt-3 text-sm font-semibold text-slate-950">{{ link.label }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ metricValue(link.metric) }} bản ghi</div>
        </a>
      </section>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <main class="space-y-5">
          <div class="grid gap-5 lg:grid-cols-2">
            <section id="community-forums" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-950">Hoạt động theo forum</h2>
                <span class="text-xs text-slate-500">{{ number(data.forums.length) }} forum</span>
              </div>
              <div class="mt-4 space-y-3">
                <button v-for="forum in forumChart" :key="forum.id" class="block w-full text-left" @click="loadForumThreads(forum)">
                  <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="truncate font-medium text-slate-800">{{ forum.title }}</span>
                    <span class="text-xs text-slate-500">{{ number(forum.total) }}</span>
                  </div>
                  <div class="mt-2 h-3 rounded-full bg-slate-100">
                    <div class="h-3 rounded-full bg-cyan-600" :style="{ width: `${forum.percent}%` }" />
                  </div>
                </button>
                <div v-if="!forumChart.length" class="rounded-md bg-slate-50 p-6 text-center text-sm text-slate-500">Chưa có forum.</div>
              </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-semibold text-slate-950">Cơ cấu dữ liệu cộng đồng</h2>
              <div class="mt-4 flex items-center gap-5">
                <div class="relative h-40 w-40 shrink-0 rounded-full" :style="donutStyle">
                  <div class="absolute inset-8 rounded-full bg-white" />
                  <div class="absolute inset-0 flex items-center justify-center text-center">
                    <div><div class="text-2xl font-semibold text-slate-950">{{ number(totalContent) }}</div><div class="text-xs text-slate-500">records</div></div>
                  </div>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                  <div v-for="item in mix" :key="item.label" class="flex items-center justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.color }" /><span class="truncate">{{ item.label }}</span></span>
                    <strong>{{ number(item.value) }}</strong>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <section id="community-threads" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
              <div>
                <h2 class="text-sm font-semibold text-slate-950">Thread & post của forum đang chọn</h2>
                <p class="mt-1 text-xs text-slate-500">{{ selectedForum?.title || 'Chưa chọn forum' }}</p>
              </div>
              <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ number(data.threads.length) }} threads</span>
            </div>
            <div class="grid divide-y divide-slate-100 lg:grid-cols-[minmax(0,1fr)_420px] lg:divide-x lg:divide-y-0">
              <div class="max-h-[520px] overflow-auto divide-y divide-slate-100">
                <button v-for="thread in data.threads" :key="thread.id" class="block w-full p-4 text-left hover:bg-slate-50" :class="selectedThread?.id === thread.id ? 'bg-slate-50' : ''" @click="loadThreadPosts(thread)">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ thread.thread_type || 'discussion' }}</span>
                    <span v-if="thread.correct_post_id" class="rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Correct answer</span>
                  </div>
                  <div class="mt-2 font-semibold text-slate-950">{{ thread.title }}</div>
                  <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ thread.excerpt }}</p>
                  <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
                    <span>{{ number(thread.reply_count) }} replies</span>
                    <span>{{ number(thread.like_count) }} likes</span>
                    <span>{{ date(thread.last_activity_at) }}</span>
                  </div>
                </button>
                <div v-if="!data.threads.length" class="p-8 text-center text-sm text-slate-500">Forum này chưa có thread.</div>
              </div>
              <div class="max-h-[520px] overflow-auto divide-y divide-slate-100">
                <article v-for="post in data.posts" :key="post.id" class="p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div><div class="font-semibold text-slate-950">{{ post.author?.full_name || post.author?.code || `User #${post.user_id}` }}</div><div class="text-xs text-slate-500">{{ date(post.created_at) }}</div></div>
                    <CheckCircle2 v-if="post.is_correct_answer" class="h-5 w-5 text-emerald-600" />
                  </div>
                  <div class="prose prose-sm mt-3 max-w-none text-slate-700" v-html="post.body_html || post.body_text" />
                </article>
                <div v-if="!data.posts.length" class="p-8 text-center text-sm text-slate-500">Chưa có post để hiển thị.</div>
              </div>
            </div>
          </section>

          <div class="grid gap-5 lg:grid-cols-2">
            <div id="community-wiki" class="scroll-mt-24"><DataBlock title="Wiki" type="wiki" :items="data.wikis" @select="openDetail" /></div>
            <div id="community-blog" class="scroll-mt-24"><DataBlock title="Blog" type="blog" :items="data.blogs" @select="openDetail" /></div>
            <div id="community-groups" class="scroll-mt-24"><DataBlock title="Nhóm học tập" type="group" :items="data.groups" @select="openDetail" /></div>
            <div id="community-moderation" class="scroll-mt-24"><DataBlock title="Kiểm duyệt" type="moderation" :items="data.reports" @select="openDetail" /></div>
          </div>

          <section v-if="selectedDetail" id="community-detail" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 p-4">
              <div>
                <div class="text-xs font-semibold uppercase text-slate-500">Chi tiết {{ selectedDetail.type }}</div>
                <h2 class="mt-1 text-lg font-semibold text-slate-950">{{ titleOf(selectedDetail.item) }}</h2>
              </div>
              <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" type="button" @click="selectedDetail = null">Đóng</button>
            </div>
            <div class="grid gap-5 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
              <dl class="space-y-3">
                <div v-for="[label, value] in detailRows(selectedDetail)" :key="label" class="rounded-md bg-slate-50 p-3">
                  <dt class="text-xs font-semibold uppercase text-slate-500">{{ label }}</dt>
                  <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ value }}</dd>
                </div>
              </dl>
              <div class="min-w-0 rounded-md border border-slate-200 p-4">
                <div class="text-xs font-semibold uppercase text-slate-500">Nội dung</div>
                <div v-if="selectedDetail.item.body_html" class="prose prose-sm mt-3 max-w-none text-slate-700" v-html="selectedDetail.item.body_html" />
                <p v-else class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedDetail.item.description || selectedDetail.item.note || selectedDetail.item.body || selectedDetail.item.excerpt || 'Bản ghi này không có nội dung mô tả.' }}</p>
              </div>
            </div>
          </section>
        </main>

        <aside class="space-y-5">
          <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-950">Dòng hoạt động mới</h2>
            <div class="mt-4 space-y-3">
              <article v-for="item in recentActivity" :key="`${item.type}-${item.title}-${item.at}`" class="flex gap-3">
                <span class="mt-0.5 rounded px-2 py-1 text-xs font-semibold" :class="item.tone">{{ item.type }}</span>
                <div class="min-w-0">
                  <div class="line-clamp-2 text-sm font-medium text-slate-800">{{ item.title }}</div>
                  <div class="mt-1 text-xs text-slate-500">{{ date(item.at) }}</div>
                </div>
              </article>
              <div v-if="!recentActivity.length" class="rounded-md bg-slate-50 p-5 text-center text-sm text-slate-500">Chưa có hoạt động.</div>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-950">Reputation</h2>
            <div class="mt-4 rounded-lg bg-amber-50 p-4">
              <Trophy class="h-6 w-6 text-amber-700" />
              <div class="mt-3 text-3xl font-semibold text-slate-950">{{ number(topReputation.points) }}</div>
              <div class="text-sm font-semibold text-amber-800">{{ topReputation.rank }}</div>
              <div class="mt-2 text-sm text-slate-600">{{ number(topReputation.badge_count) }} huy hiệu</div>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-950">Notification</h2>
            <div class="mt-3 space-y-2">
              <article v-for="item in data.notifications.slice(0, 5)" :key="item.id" class="rounded-md bg-slate-50 p-3">
                <div class="text-sm font-semibold text-slate-950">{{ item.title }}</div>
                <div class="mt-1 text-xs text-slate-600">{{ item.body }}</div>
              </article>
              <div v-if="!data.notifications.length" class="rounded-md bg-slate-50 p-5 text-center text-sm text-slate-500">Không có thông báo.</div>
            </div>
          </section>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
