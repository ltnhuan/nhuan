<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  video: { type: Object, required: true },
  playback: { type: Object, required: true },
  requiredPercent: { type: Number, default: 90 },
})

const emit = defineEmits(['heartbeat', 'event', 'completed'])

const player = ref(null)
const session = ref(null)
const watchedPercent = ref(props.video.watch_percent || 0)
const lastHeartbeatAt = ref(Date.now())
const tabHidden = ref(false)
let heartbeatTimer = null

const completionText = computed(() => `Cần xem tối thiểu ${props.requiredPercent}% để hoàn thành bài học`)

async function startSession() {
  const response = await fetch('/api/v1/video-sessions/start', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Tenant-Code': 'VABIS', 'X-Demo-User-Email': 'sv.lms@vabis.edu.vn' },
    body: JSON.stringify({ video_asset_id: props.video.id }),
  })
  session.value = response.ok ? await response.json() : null
}

function currentPosition() {
  return player.value ? Math.round(player.value.currentTime || 0) : 0
}

async function sendEvent(eventType, metadata = {}) {
  if (!session.value) return
  const endpoint = eventType === 'heartbeat' ? 'heartbeat' : 'event'
  const now = Date.now()
  const delta = Math.min(15, Math.max(0, Math.round((now - lastHeartbeatAt.value) / 1000)))
  lastHeartbeatAt.value = now

  const payload = {
    event_type: eventType,
    position_seconds: currentPosition(),
    watched_delta_seconds: tabHidden.value ? 0 : delta,
    playback_rate: player.value?.playbackRate || 1,
    metadata: { tab_hidden: tabHidden.value, ...metadata },
  }

  const response = await fetch(`/api/v1/video-sessions/${session.value.id}/${endpoint}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Tenant-Code': 'VABIS', 'X-Demo-User-Email': 'sv.lms@vabis.edu.vn' },
    body: JSON.stringify(payload),
  })

  emit(eventType === 'heartbeat' ? 'heartbeat' : 'event', payload)
  if (response.ok && eventType === 'heartbeat') {
    watchedPercent.value = Math.min(100, watchedPercent.value + (delta / Math.max(1, props.video.duration_seconds || 600)) * 100)
    if (watchedPercent.value >= props.requiredPercent) emit('completed')
  }
}

function onVisibilityChange() {
  tabHidden.value = document.hidden
  sendEvent(document.hidden ? 'tab_hidden' : 'tab_visible')
}

onMounted(async () => {
  await startSession()
  document.addEventListener('visibilitychange', onVisibilityChange)
  heartbeatTimer = window.setInterval(() => sendEvent('heartbeat'), 12000)
})

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange)
  if (heartbeatTimer) window.clearInterval(heartbeatTimer)
  sendEvent('ended')
})
</script>

<template>
  <section class="overflow-hidden border bg-white">
    <div class="bg-slate-950">
      <video
        ref="player"
        class="aspect-video w-full bg-black"
        controls
        controlsList="nodownload"
        :poster="video.thumbnail_url"
        :src="playback.url"
        @play="sendEvent('play')"
        @pause="sendEvent('pause')"
        @seeked="sendEvent('seek', { seeked: true })"
        @ratechange="sendEvent('rate_change')"
        @ended="sendEvent('ended')"
      ></video>
    </div>

    <div class="grid gap-4 p-4 md:grid-cols-[1fr_220px]">
      <div>
        <h2 class="text-base font-semibold text-slate-950">{{ video.title }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ completionText }}</p>
        <div class="mt-3 h-2 rounded-full bg-slate-100">
          <div class="h-2 rounded-full bg-emerald-600" :style="{ width: `${Math.min(100, watchedPercent)}%` }"></div>
        </div>
      </div>
      <dl class="grid grid-cols-2 gap-2 text-sm">
        <div class="rounded-md bg-slate-50 p-3">
          <dt class="text-slate-500">Đã xem</dt>
          <dd class="font-semibold text-slate-950">{{ Math.round(watchedPercent) }}%</dd>
        </div>
        <div class="rounded-md bg-slate-50 p-3">
          <dt class="text-slate-500">Nguồn phát</dt>
          <dd class="font-semibold text-slate-950">{{ playback.delivery === 'hls' ? 'HLS' : 'Local' }}</dd>
        </div>
      </dl>
    </div>
  </section>
</template>
