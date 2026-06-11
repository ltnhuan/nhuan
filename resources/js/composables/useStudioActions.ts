import { ref } from 'vue'
import { useLmsAction } from './useLmsAction'

export function useStudioActions(headers: Record<string, string> = {}) {
  const studio = ref<any>(null)
  const course = ref<any>(null)
  const { loading, toast, validationErrors, runAction, showErrorToast } = useLmsAction(headers)

  async function refreshStudio(courseId?: number) {
    const id = courseId || course.value?.id
    if (!id) return null
    const response = await fetch(`/api/v1/courses/${id}/studio`, { headers })
    const payload = await response.json()
    studio.value = payload.data || payload
    course.value = studio.value.course
    return studio.value
  }

  async function loadFirstCourse() {
    const response = await fetch('/api/v1/courses?per_page=1', { headers })
    const payload = await response.json()
    course.value = payload.data?.[0] || null
    if (course.value) await refreshStudio(course.value.id)
    return course.value
  }

  async function loadCourse(courseId: number) {
    const response = await fetch(`/api/v1/courses/${courseId}`, { headers })
    const payload = await response.json()
    course.value = payload.data || payload
    if (course.value) await refreshStudio(course.value.id)
    return course.value
  }

  const createSection = (body: any) => runAction({ actionKey: 'studio.section.create', url: `/api/v1/courses/${course.value.id}/sections`, method: 'POST', body, reload: () => refreshStudio() })
  const createUnit = (parentId: number, body: any) => createSection({ ...body, parent_id: parentId, type: 'unit' })
  const updateSection = (id: number, body: any) => runAction({ actionKey: `studio.section.update:${id}`, url: `/api/v1/course-sections/${id}`, method: 'PUT', body, reload: () => refreshStudio() })
  const reorderSection = (items: any[]) => runAction({ actionKey: 'studio.section.reorder', url: '/api/v1/course-sections/reorder', method: 'POST', body: { items }, reload: () => refreshStudio() })
  const createComponent = (body: any) => runAction({ actionKey: `studio.component.create:${body.component_type}`, url: '/api/v1/course-components', method: 'POST', body, reload: () => refreshStudio() })
  const updateComponent = (id: number, body: any) => runAction({ actionKey: `studio.component.update:${id}`, url: `/api/v1/course-components/${id}`, method: 'PUT', body, reload: () => refreshStudio() })
  const deleteComponent = (id: number) => runAction({ actionKey: `studio.component.delete:${id}`, url: `/api/v1/course-components/${id}`, method: 'DELETE', confirm: true, confirmMessage: 'Xóa component này?', reload: () => refreshStudio() })
  const duplicateComponent = (id: number) => runAction({ actionKey: `studio.component.duplicate:${id}`, url: `/api/v1/course-components/${id}/duplicate`, method: 'POST', reload: () => refreshStudio() })
  const replaceComponentContent = (id: number, contentId: number) => updateComponent(id, { content_id: contentId })
  const submitReview = () => runAction({ actionKey: 'studio.course.submit_review', url: `/api/v1/courses/${course.value.id}/submit-review`, method: 'POST', confirm: true, confirmMessage: 'Gửi khóa học sang review?', reload: () => refreshStudio() })
  const approveCourse = () => runAction({ actionKey: 'studio.course.approve', url: `/api/v1/courses/${course.value.id}/approve`, method: 'POST', confirm: true, confirmMessage: 'Duyệt khóa học này?', reload: () => refreshStudio() })
  const publishCourse = () => runAction({ actionKey: 'studio.course.publish', url: `/api/v1/courses/${course.value.id}/publish`, method: 'POST', confirm: true, confirmMessage: 'Xuất bản khóa học?', reload: () => refreshStudio() })
  const saveDraft = (body: any) => runAction({ actionKey: 'studio.course.save_draft', url: `/api/v1/courses/${course.value.id}`, method: 'PUT', body, reload: () => refreshStudio() })

  function handleActionError(error: any) {
    showErrorToast(error?.message || 'Không thể thực hiện thao tác Studio')
  }

  return {
    studio, course, loading, toast, validationErrors,
    loadFirstCourse, loadCourse, refreshStudio, createSection, createUnit, updateSection, reorderSection,
    createComponent, updateComponent, deleteComponent, duplicateComponent,
    replaceComponentContent, submitReview, approveCourse, publishCourse, saveDraft,
    handleActionError,
  }
}
