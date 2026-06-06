import { ref } from 'vue'

export function useOptimisticMutation(state, mutate) {
  const pending = ref(false)
  const error = ref(null)

  async function run(applyLocal, payload) {
    const previous = structuredClone(state.value)
    pending.value = true
    error.value = null

    try {
      applyLocal(state.value)
      return await mutate(payload)
    } catch (exception) {
      state.value = previous
      error.value = exception
      throw exception
    } finally {
      pending.value = false
    }
  }

  return { run, pending, error }
}
