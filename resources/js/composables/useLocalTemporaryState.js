import { ref, watch } from 'vue'

export function useLocalTemporaryState(key, initialValue) {
  const stored = window.localStorage.getItem(key)
  const state = ref(stored ? JSON.parse(stored) : initialValue)

  watch(state, (value) => {
    window.localStorage.setItem(key, JSON.stringify(value))
  }, { deep: true })

  function clear() {
    window.localStorage.removeItem(key)
    state.value = initialValue
  }

  return { state, clear }
}
