import { reactive, ref } from 'vue'

type ActionOptions = {
  actionKey: string
  url: string
  method?: string
  body?: Record<string, unknown> | FormData | null
  headers?: Record<string, string>
  confirm?: boolean
  confirmMessage?: string
  successMessage?: string
  reload?: (() => Promise<void> | void) | null
}

const toast = reactive({ show: false, type: 'success', message: '' })

export function useLmsAction(defaultHeaders: Record<string, string> = {}) {
  const loading = reactive<Record<string, boolean>>({})
  const validationErrors = ref<Record<string, string[]>>({})

  function confirmAction(message = 'Bạn chắc chắn muốn thực hiện thao tác này?') {
    return window.confirm(message)
  }

  function showLoading(actionKey: string, value = true) {
    loading[actionKey] = value
  }

  function showSuccessToast(message = 'Thao tác thành công') {
    toast.type = 'success'
    toast.message = message
    toast.show = true
    window.setTimeout(() => (toast.show = false), 2600)
  }

  function showErrorToast(message = 'Không thể thực hiện thao tác') {
    toast.type = 'error'
    toast.message = message
    toast.show = true
    window.setTimeout(() => (toast.show = false), 4200)
  }

  async function reloadPageData(reload?: (() => Promise<void> | void) | null) {
    if (reload) {
      await reload()
    }
  }

  function handleValidationErrors(payload: any) {
    validationErrors.value = payload?.errors || {}
    showErrorToast(payload?.message || 'Dữ liệu không hợp lệ')
  }

  function handle403(payload: any) {
    showErrorToast(payload?.message || 'Bạn không có quyền thực hiện thao tác này')
  }

  function handle404(payload: any) {
    showErrorToast(payload?.message || 'Không tìm thấy dữ liệu')
  }

  function handle500(payload: any) {
    showErrorToast(payload?.message || 'Lỗi hệ thống')
  }

  async function runAction(options: ActionOptions) {
    if (loading[options.actionKey]) {
      return null
    }

    if (options.confirm && !confirmAction(options.confirmMessage)) {
      return null
    }

    showLoading(options.actionKey, true)
    validationErrors.value = {}

    try {
      const isFormData = options.body instanceof FormData
      const headers = {
        ...defaultHeaders,
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(options.headers || {}),
      }

      if (isFormData) {
        delete headers['Content-Type']
        delete headers['content-type']
      }

      const response = await fetch(options.url, {
        method: options.method || 'POST',
        headers,
        body: options.body ? (isFormData ? options.body : JSON.stringify(options.body)) : undefined,
      })

      const payload = await response.json().catch(() => ({}))

      if (!response.ok || payload.success === false) {
        if (response.status === 422) handleValidationErrors(payload)
        else if (response.status === 403) handle403(payload)
        else if (response.status === 404) handle404(payload)
        else if (response.status >= 500) handle500(payload)
        else showErrorToast(payload.message || 'Không thể thực hiện thao tác')
        throw new Error(payload.message || `Action failed: ${options.actionKey}`)
      }

      showSuccessToast(options.successMessage || payload.message || 'Thao tác thành công')
      await reloadPageData(options.reload)

      return payload.data ?? payload
    } catch (error: any) {
      if (!toast.show) {
        showErrorToast(error?.message || 'Không thể thực hiện thao tác')
      }
      throw error
    } finally {
      showLoading(options.actionKey, false)
    }
  }

  return {
    loading,
    toast,
    validationErrors,
    confirmAction,
    runAction,
    showLoading,
    showSuccessToast,
    showErrorToast,
    reloadPageData,
    handleValidationErrors,
    handle403,
    handle404,
    handle500,
  }
}
