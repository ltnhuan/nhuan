<script setup>
import { computed, onMounted, ref } from 'vue'
import { ArrowRight, Download, QrCode, RefreshCw, Share2, ShieldCheck } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

const loading = ref(true)
const error = ref('')
const payload = ref(null)

const summary = computed(() => payload.value?.summary || {})
const certificates = computed(() => payload.value?.certificates || [])
const badges = computed(() => payload.value?.badges || [])
const microCredentials = computed(() => payload.value?.micro_credentials || [])
const timeline = computed(() => payload.value?.timeline || [])
const latestCredential = computed(() => certificates.value[0] || badges.value[0] || null)

const stats = computed(() => [
  { label: 'Certificates', value: summary.value.certificates || 0 },
  { label: 'Badges', value: summary.value.badges || 0 },
  { label: 'Micro Credentials', value: summary.value.micro_credentials || 0 },
  { label: 'Verified', value: summary.value.verified || 0 },
  { label: 'Revoked', value: summary.value.revoked || 0 },
])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/student/credentials', { headers: props.apiHeaders })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message || 'Không tải được Credential Wallet.')
    payload.value = data.data || data
  } catch (err) {
    error.value = err.message || 'Không tải được Credential Wallet.'
  } finally {
    loading.value = false
  }
}

function credentialTitle(item, type) {
  if (type === 'badge') return item.badge?.name || item.title || 'Digital Badge'
  return item.certificate_title || item.title || item.name || 'Credential'
}

function issuedAt(item) {
  return item.issued_at || item.created_at || '-'
}

function statusClass(status) {
  if (['issued', 'active', 'verified', 'published'].includes(status)) return 'bg-emerald-50 text-emerald-700'
  if (status === 'revoked') return 'bg-red-50 text-red-700'
  return 'bg-slate-100 text-slate-700'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Credential Wallet</template>
    <section class="mx-auto max-w-7xl px-3 py-3 sm:px-5">
      <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="text-xs font-bold uppercase text-blue-700">Digital credentials</div>
          <h1 class="mt-1 text-2xl font-bold text-slate-950">Credential Wallet</h1>
          <p class="mt-2 text-sm text-slate-600">Chứng chỉ, huy hiệu, micro credential và lịch sử xác thực số.</p>
        </div>
          <button class="grid h-10 w-10 place-items-center rounded-md bg-slate-950 text-white disabled:opacity-60" :disabled="loading" title="Tải lại" @click="load">
            <RefreshCw class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div v-if="loading" class="mt-5 rounded-md border bg-white p-6 text-sm text-slate-500">Đang tải credential wallet...</div>
      <div v-else-if="error" class="mt-5 rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <template v-else>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <div v-for="item in stats" :key="item.label" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
            <div class="mt-2 text-3xl font-bold text-slate-950">{{ item.value }}</div>
          </div>
        </div>

        <section v-if="latestCredential" class="mt-5 rounded-md border border-slate-200 bg-slate-950 p-4 text-white shadow-sm">
          <div class="grid gap-4 lg:grid-cols-[1fr_220px]">
            <div>
              <div class="text-xs font-bold uppercase text-cyan-200">Mới nhất</div>
              <h2 class="mt-2 text-2xl font-bold">{{ credentialTitle(latestCredential, latestCredential.badge ? 'badge' : 'certificate') }}</h2>
              <p class="mt-2 text-sm text-slate-300">Issued: {{ issuedAt(latestCredential) }} · {{ latestCredential.status || 'issued' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
              <a :href="latestCredential.verification_url || latestCredential.qr_payload || '#'" class="inline-flex items-center gap-2 rounded-md bg-cyan-400 px-3 py-2 text-sm font-bold text-slate-950">
                Verify <ArrowRight class="h-4 w-4" />
              </a>
              <a :href="latestCredential.verification_url || '#'" class="grid h-10 w-10 place-items-center rounded-md bg-white/10 text-white" title="Share">
                <Share2 class="h-4 w-4" />
              </a>
            </div>
          </div>
        </section>

        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
          <main class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><ShieldCheck class="h-4 w-4 text-emerald-700" /> Certificates</h2>
              <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="item in certificates" :key="item.id" class="rounded-md border border-slate-200 bg-slate-50 p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <h3 class="font-bold text-slate-950">{{ credentialTitle(item, 'certificate') }}</h3>
                      <p class="mt-1 text-xs text-slate-500">{{ item.issue_code || item.verification_hash || '-' }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{ item.status }}</span>
                  </div>
                  <div class="mt-4 text-sm text-slate-600">Issued: {{ issuedAt(item) }}</div>
                  <div class="mt-4 flex flex-wrap gap-2">
                    <a class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold" :href="item.qr_payload || item.verification_url || '#'"><QrCode class="h-3 w-3" /> Verify QR</a>
                    <a class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold" :href="item.verification_url || '#'"><Download class="h-3 w-3" /> Download</a>
                    <a class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold" :href="item.verification_url || '#'"><Share2 class="h-3 w-3" /> Share</a>
                  </div>
                </article>
                <div v-if="!certificates.length" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500 md:col-span-2 xl:col-span-3">Chưa có chứng chỉ.</div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Badges</h2>
              <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article v-for="item in badges" :key="item.id" class="rounded-md border border-slate-200 p-4">
                  <div class="grid h-12 w-12 place-items-center rounded-md bg-amber-50 text-amber-700"><ShieldCheck class="h-6 w-6" /></div>
                  <h3 class="mt-3 font-bold text-slate-950">{{ credentialTitle(item, 'badge') }}</h3>
                  <p class="mt-1 text-xs text-slate-500">{{ item.issue_code || '-' }}</p>
                  <div class="mt-3"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{ item.status }}</span></div>
                </article>
                <div v-if="!badges.length" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500 md:col-span-2 xl:col-span-4">Chưa có huy hiệu.</div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Micro Credentials</h2>
              <div class="mt-4 overflow-auto">
                <table class="w-full text-left text-sm">
                  <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Title</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Status</th></tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in microCredentials" :key="item.id">
                      <td class="px-3 py-2 font-mono text-xs">{{ item.code }}</td>
                      <td class="px-3 py-2 font-semibold">{{ item.title }}</td>
                      <td class="px-3 py-2">{{ item.credential_type }}</td>
                      <td class="px-3 py-2"><span class="rounded px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{ item.status }}</span></td>
                    </tr>
                    <tr v-if="!microCredentials.length"><td colspan="4" class="px-3 py-5 text-center text-slate-500">Chưa có micro credential.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </main>

          <aside class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Verify QR</h2>
              <div class="mt-4 grid aspect-square place-items-center rounded-md border border-slate-200 bg-slate-50">
                <QrCode class="h-24 w-24 text-slate-700" />
              </div>
              <p class="mt-3 text-xs leading-5 text-slate-500">QR dùng URL xác thực hoặc hash của chứng chỉ/huy hiệu đã cấp.</p>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Timeline</h2>
              <div class="mt-4 space-y-3">
                <div v-for="item in timeline" :key="`${item.type}-${item.title}-${item.at}`" class="rounded-md border border-slate-200 p-3 text-sm">
                  <div class="flex items-center justify-between gap-3">
                    <strong class="text-slate-950">{{ item.title }}</strong>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{ item.status }}</span>
                  </div>
                  <div class="mt-1 text-xs text-slate-500">{{ item.type }} · {{ item.at || '-' }}</div>
                </div>
                <div v-if="!timeline.length" class="rounded-md border border-dashed border-slate-300 p-3 text-sm text-slate-500">Chưa có lịch sử credential.</div>
              </div>
            </div>
          </aside>
        </div>
      </template>
    </section>
  </EraLmsLayout>
</template>
