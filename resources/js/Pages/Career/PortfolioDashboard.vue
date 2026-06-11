<script setup>
import { computed, onMounted, ref } from 'vue'
import { ArrowRight, BriefcaseBusiness, Download, FileBadge, LineChart, RefreshCw, Share2, UserRound } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(true)
const error = ref('')
const portfolioPayload = ref(null)
const careerPayload = ref(null)

const profile = computed(() => portfolioPayload.value?.profile || {})
const portfolio = computed(() => portfolioPayload.value?.portfolio || {})
const sections = computed(() => portfolioPayload.value?.sections || {})
const skills = computed(() => portfolioPayload.value?.skills || [])
const skillGroups = computed(() => portfolioPayload.value?.skill_groups || {})
const timeline = computed(() => portfolioPayload.value?.timeline || [])
const growthTrend = computed(() => portfolioPayload.value?.charts?.growth_trend || [])
const careerSections = computed(() => careerPayload.value?.sections || {})
const careerMetrics = computed(() => careerPayload.value?.metrics || {})
const aiRecommendation = computed(() => careerPayload.value?.ai?.career_recommendation || '')
const portfolioItems = computed(() => portfolio.value?.items || [])

const metricCards = computed(() => [
  { label: 'Portfolio Score', value: Number(careerMetrics.value.portfolio_score || portfolioPayload.value?.portfolio_score || 0).toFixed(1), suffix: '/100' },
  { label: 'Skill Score', value: Number(careerMetrics.value.skill_score || 0).toFixed(1), suffix: '/100' },
  { label: 'Language Score', value: Number(careerMetrics.value.language_score || 0).toFixed(1), suffix: '/100' },
  { label: 'Interview Readiness', value: Number(careerMetrics.value.interview_readiness || 0).toFixed(1), suffix: '/100' },
])
const strongestMetric = computed(() => metricCards.value.slice().sort((a, b) => Number(b.value) - Number(a.value))[0] || null)

const sectionCards = computed(() => [
  { key: 'projects', label: 'Projects', items: sections.value.projects || [] },
  { key: 'assignments', label: 'Assignments', items: sections.value.assignments || [] },
  { key: 'certificates', label: 'Certificates', items: sections.value.certificates || [] },
  { key: 'badges', label: 'Badges', items: sections.value.badges || [] },
  { key: 'internships', label: 'Internships', items: sections.value.internships || [] },
  { key: 'activities', label: 'Activities', items: sections.value.activities || [] },
])

const careerCards = computed(() => [
  { label: 'Internships', items: careerSections.value.internships || [], fields: ['company', 'match_score'] },
  { label: 'Jobs', items: careerSections.value.jobs || [], fields: ['company', 'match_score'] },
  { label: 'Career Events', items: careerSections.value.career_events || [], fields: ['date', 'status'] },
  { label: 'Employer Invitations', items: careerSections.value.employer_invitations || [], fields: ['company', 'status'] },
])

const radarPoints = computed(() => {
  const values = [
    Number(skillGroups.value.technical?.score || 0),
    Number(skillGroups.value.soft?.score || 0),
    Number(skillGroups.value.ai?.score || 0),
    Number(skillGroups.value.language?.score || 0),
  ]
  const coords = [[140, 28], [242, 130], [140, 232], [38, 130]]
  return coords.map(([x, y], index) => `${140 + (x - 140) * values[index] / 100},${130 + (y - 130) * values[index] / 100}`).join(' ')
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [portfolioResponse, careerResponse] = await Promise.all([
      fetch('/api/v1/student/portfolio', { headers: props.apiHeaders }),
      fetch('/api/v1/student/career', { headers: props.apiHeaders }),
    ])
    const portfolioData = await portfolioResponse.json().catch(() => ({}))
    const careerData = await careerResponse.json().catch(() => ({}))
    if (!portfolioResponse.ok) throw new Error(portfolioData.message || 'Không tải được Portfolio Center.')
    if (!careerResponse.ok) throw new Error(careerData.message || 'Không tải được Career Center.')
    portfolioPayload.value = portfolioData.data || portfolioData
    careerPayload.value = careerData.data || careerData
  } catch (err) {
    error.value = err.message || 'Không tải được dữ liệu nghề nghiệp.'
  } finally {
    loading.value = false
  }
}

function groupLabel(key) {
  return {
    technical: 'Technical',
    soft: 'Soft',
    ai: 'AI',
    language: 'Language',
  }[key] || key
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Portfolio & Career</template>
    <section class="mx-auto max-w-7xl px-3 py-3 sm:px-5">
      <div v-if="loading" class="rounded-md border bg-white p-6 text-sm text-slate-500">Đang tải hồ sơ năng lực...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <template v-else>
        <header class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase text-blue-700">Career profile</div>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ profile.headline || 'Digital Portfolio & Career Center' }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ profile.summary || 'Hồ sơ học tập số phục vụ thực tập, tuyển dụng và cựu sinh viên.' }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a href="/career/digital-twin" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold"><UserRound class="h-4 w-4" /> Digital Twin</a>
            <a :href="profile.public_url || '/portfolio'" class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white"><Share2 class="h-4 w-4" /> Public URL</a>
            <button class="grid h-10 w-10 place-items-center rounded-md border border-slate-300" title="Tải lại" @click="load"><RefreshCw class="h-4 w-4" /></button>
          </div>
          </div>
        </header>

        <section class="mt-5 rounded-md border border-slate-200 bg-slate-950 p-4 text-white shadow-sm">
          <div class="grid gap-4 lg:grid-cols-[1fr_260px]">
            <div>
              <div class="text-xs font-bold uppercase text-cyan-200">AI Career Recommendation</div>
              <p class="mt-2 text-lg font-semibold leading-7">{{ aiRecommendation }}</p>
            </div>
            <div class="rounded-md bg-white/10 p-4">
              <div class="text-xs font-bold uppercase text-slate-300">{{ strongestMetric?.label || 'Readiness' }}</div>
              <div class="mt-2 text-4xl font-bold">{{ strongestMetric?.value || 0 }}<span class="text-base text-slate-300">{{ strongestMetric?.suffix }}</span></div>
              <a href="/career/digital-twin" class="mt-4 inline-flex items-center gap-2 rounded-md bg-cyan-400 px-3 py-2 text-sm font-bold text-slate-950">
                Xem kế hoạch <ArrowRight class="h-4 w-4" />
              </a>
            </div>
          </div>
        </section>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div v-for="item in metricCards" :key="item.label" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
            <div class="mt-2 text-3xl font-bold text-slate-950">{{ item.value }}<span class="text-sm text-slate-500">{{ item.suffix }}</span></div>
          </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
          <aside class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><LineChart class="h-4 w-4 text-blue-700" /> Skills Radar</h2>
              <svg viewBox="0 0 280 260" class="mt-3 h-64 w-full">
                <polygon points="140,28 242,130 140,232 38,130" fill="none" stroke="#cbd5e1" />
                <polygon points="140,72 198,130 140,188 82,130" fill="none" stroke="#e2e8f0" />
                <polygon :points="radarPoints" fill="#0f4c8122" stroke="#0f4c81" stroke-width="2" />
                <text x="140" y="20" text-anchor="middle" class="fill-slate-700 text-[11px]">Technical</text>
                <text x="250" y="133" class="fill-slate-700 text-[11px]">Soft</text>
                <text x="140" y="250" text-anchor="middle" class="fill-slate-700 text-[11px]">AI</text>
                <text x="8" y="133" class="fill-slate-700 text-[11px]">Language</text>
              </svg>
              <div class="space-y-2">
                <div v-for="(group, key) in skillGroups" :key="key" class="text-sm">
                  <div class="flex justify-between"><span>{{ groupLabel(key) }}</span><strong>{{ Number(group.score || 0).toFixed(1) }}</strong></div>
                  <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-700" :style="{ width: `${Math.min(100, Number(group.score || 0))}%` }"></div></div>
                </div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Growth Trend</h2>
              <div class="mt-4 space-y-3">
                <div v-for="point in growthTrend" :key="point.month" class="grid grid-cols-[72px_1fr_48px] items-center gap-3 text-sm">
                  <span class="text-xs text-slate-500">{{ point.month }}</span>
                  <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-600" :style="{ width: `${Math.min(100, Number(point.score || 0))}%` }"></div></div>
                  <strong class="text-right">{{ point.score }}</strong>
                </div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><Download class="h-4 w-4 text-slate-700" /> Export</h2>
              <div class="mt-3 grid grid-cols-3 gap-2">
                <a class="rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-semibold" :href="portfolioPayload.exports?.pdf || '#'">PDF</a>
                <a class="rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-semibold" :href="portfolioPayload.exports?.cv || '#'">CV</a>
                <a class="rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-semibold" :href="portfolioPayload.exports?.resume || '#'">Resume</a>
              </div>
              <div class="mt-3 rounded-md bg-slate-50 p-3 text-xs text-slate-600">{{ profile.public_url || '/portfolio' }}</div>
            </div>
          </aside>

          <main class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><BriefcaseBusiness class="h-4 w-4 text-emerald-700" /> Portfolio Sections</h2>
              <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <section v-for="section in sectionCards" :key="section.key" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                  <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-950">{{ section.label }}</h3>
                    <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-600">{{ section.items.length }}</span>
                  </div>
                  <div class="mt-3 space-y-2">
                    <div v-for="item in section.items.slice(0, 3)" :key="item.id" class="rounded-md bg-white p-3 text-sm">
                      <div class="font-semibold text-slate-950">{{ item.title }}</div>
                      <div class="mt-1 text-xs text-slate-500">{{ item.status }} · {{ item.issued_at || item.created_at || '-' }}</div>
                    </div>
                    <div v-if="!section.items.length" class="rounded-md border border-dashed border-slate-300 p-3 text-sm text-slate-500">Chưa có dữ liệu.</div>
                  </div>
                </section>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><FileBadge class="h-4 w-4 text-amber-600" /> Career Center</h2>
              <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <section v-for="block in careerCards" :key="block.label" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                  <h3 class="text-sm font-bold text-slate-950">{{ block.label }}</h3>
                  <div class="mt-3 space-y-2">
                    <div v-for="item in block.items" :key="`${block.label}-${item.title}`" class="rounded-md bg-white p-3 text-sm">
                      <div class="font-semibold text-slate-950">{{ item.title }}</div>
                      <div class="mt-1 text-xs text-slate-500">
                        <span v-for="field in block.fields" :key="field" class="mr-2">{{ field }}: {{ item[field] ?? '-' }}</span>
                      </div>
                    </div>
                    <div v-if="!block.items.length" class="rounded-md border border-dashed border-slate-300 p-3 text-sm text-slate-500">Chưa có mục phù hợp.</div>
                  </div>
                </section>
              </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-950">Career Readiness Actions</h2>
                <div class="mt-3 space-y-2">
                  <div v-for="action in careerSections.career_readiness || []" :key="`${action.phase}-${action.focus}`" class="rounded-md border border-slate-200 p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                      <strong>{{ action.phase }}</strong>
                      <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ action.focus }}</span>
                    </div>
                    <p class="mt-2 leading-6 text-slate-600">{{ action.action }}</p>
                  </div>
                </div>
              </div>

              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-950">Skill tags</h2>
                <p class="mt-3 rounded-md bg-blue-50 p-4 text-sm leading-6 text-blue-900">Dùng các tag này để định vị portfolio và chuẩn bị phỏng vấn.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                  <span v-for="skill in skills.slice(0, 6)" :key="skill.category" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ skill.category }} {{ skill.score }}</span>
                </div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Portfolio Timeline</h2>
              <div class="mt-4 flex flex-wrap gap-2 text-sm">
                <span v-for="event in timeline" :key="event.id" class="rounded-md bg-slate-100 px-3 py-2">{{ event.title }}</span>
                <span v-for="item in portfolioItems.slice(0, 6)" :key="`item-${item.id}`" class="rounded-md bg-emerald-50 px-3 py-2 text-emerald-700">{{ item.title }}</span>
                <span v-if="!timeline.length && !portfolioItems.length" class="text-slate-500">Chưa có timeline.</span>
              </div>
            </div>
          </main>
        </div>
      </template>
    </section>
  </EraLmsLayout>
</template>
