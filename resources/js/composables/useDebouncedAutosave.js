import { onBeforeUnmount, ref, watch } from 'vue'

export function useDebouncedAutosave(source, save, delay = 800) {
  const saving = ref(false)
  const lastSavedAt = ref(null)
  let timer = null

  const stop = watch(source, (value) => {
    clearTimeout(timer)
    timer = setTimeout(async () => {
      saving.value = true
      try {
        await save(value)
        lastSavedAt.value = new Date()
      } finally {
        saving.value = false
      }
    }, delay)
  }, { deep: true })

  onBeforeUnmount(() => {
    clearTimeout(timer)
    stop()
  })

  return { saving, lastSavedAt }
}
