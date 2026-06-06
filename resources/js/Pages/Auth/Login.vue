<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  demoCredentials: { type: Object, required: true },
  tenant: { type: Object, default: null },
  loginAction: { type: Function, required: true },
})

const email = ref(props.demoCredentials.email)
const password = ref(props.demoCredentials.password)
const remember = ref(true)
const loading = ref(false)
const error = ref('')

const tenantName = computed(() => props.tenant?.name || 'EraLMS Enterprise')

async function submit() {
  loading.value = true
  error.value = ''

  try {
    await props.loginAction({
      email: email.value,
      password: password.value,
      remember: remember.value,
    })
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

function fillDemo() {
  email.value = props.demoCredentials.email
  password.value = props.demoCredentials.password
}
</script>

<template>
  <main class="min-h-screen bg-[#f6f8fb] text-slate-950">
    <div class="grid min-h-screen lg:grid-cols-[1.08fr_0.92fr]">
      <section class="relative hidden overflow-hidden bg-[#10243f] px-12 py-10 text-white lg:flex lg:flex-col">
        <div class="absolute inset-0 opacity-35">
          <div class="h-full w-full bg-[radial-gradient(circle_at_24%_20%,#37b7ff_0,transparent_30%),radial-gradient(circle_at_72%_42%,#f4b740_0,transparent_26%),linear-gradient(135deg,#10243f_0%,#193f68_55%,#0f172a_100%)]"></div>
        </div>
        <div class="relative z-10 flex items-center gap-3">
          <div class="grid h-11 w-11 place-items-center rounded-md bg-white text-sm font-black text-[#10243f]">E</div>
          <div>
            <div class="text-sm font-semibold">EraLMS Enterprise</div>
            <div class="text-xs text-sky-100">{{ tenantName }}</div>
          </div>
        </div>
        <div class="relative z-10 mt-auto max-w-2xl pb-4">
          <p class="text-sm font-medium uppercase tracking-[0.18em] text-amber-200">Learning operation suite</p>
          <h1 class="mt-5 text-5xl font-semibold leading-tight">Điều hành đào tạo, khảo thí và dữ liệu học tập trên một màn hình.</h1>
          <div class="mt-8 grid grid-cols-3 gap-3">
            <div class="rounded-md border border-white/20 bg-white/10 p-4 backdrop-blur">
              <div class="text-2xl font-semibold">5.000</div>
              <div class="mt-1 text-xs text-slate-200">người học demo</div>
            </div>
            <div class="rounded-md border border-white/20 bg-white/10 p-4 backdrop-blur">
              <div class="text-2xl font-semibold">20+</div>
              <div class="mt-1 text-xs text-slate-200">module vận hành</div>
            </div>
            <div class="rounded-md border border-white/20 bg-white/10 p-4 backdrop-blur">
              <div class="text-2xl font-semibold">VABIS</div>
              <div class="mt-1 text-xs text-slate-200">tenant mặc định</div>
            </div>
          </div>
        </div>
      </section>

      <section class="flex min-h-screen items-center justify-center px-5 py-8 sm:px-8">
        <div class="w-full max-w-[440px]">
          <div class="mb-8 lg:hidden">
            <div class="text-2xl font-bold">EraLMS Enterprise</div>
            <div class="mt-1 text-sm text-slate-500">{{ tenantName }}</div>
          </div>

          <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div>
              <p class="text-sm font-medium text-blue-700">Đăng nhập quản trị</p>
              <h2 class="mt-2 text-3xl font-semibold">Chào mừng quay lại</h2>
              <p class="mt-2 text-sm text-slate-500">Tài khoản demo admin đã được điền sẵn để kiểm tra toàn bộ module.</p>
            </div>

            <form class="mt-7 space-y-4" @submit.prevent="submit">
              <label class="block">
                <span class="text-sm font-medium text-slate-700">Email</span>
                <input v-model="email" class="mt-2 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100" type="email" autocomplete="username" />
              </label>

              <label class="block">
                <span class="text-sm font-medium text-slate-700">Mật khẩu</span>
                <input v-model="password" class="mt-2 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100" type="password" autocomplete="current-password" />
              </label>

              <div class="flex items-center justify-between gap-3 text-sm">
                <label class="flex items-center gap-2 text-slate-600">
                  <input v-model="remember" class="h-4 w-4 rounded border-slate-300 text-blue-700" type="checkbox" />
                  Ghi nhớ phiên demo
                </label>
                <button class="font-medium text-blue-700 hover:text-blue-900" type="button" @click="fillDemo">Điền demo</button>
              </div>

              <p v-if="error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

              <button class="flex h-11 w-full items-center justify-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-400" type="submit" :disabled="loading">
                {{ loading ? 'Đang đăng nhập...' : 'Vào hệ thống' }}
              </button>
            </form>

            <div class="mt-5 rounded-md bg-slate-50 p-3 text-sm text-slate-600">
              <div class="font-medium text-slate-800">Demo admin</div>
              <div class="mt-1 font-mono text-xs">{{ demoCredentials.email }} / {{ demoCredentials.password }}</div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>
