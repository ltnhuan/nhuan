export function useLessonPrefetch(fetcher) {
  const prefetched = new Map()

  async function prefetchNextLesson(nextLessonId) {
    if (!nextLessonId || prefetched.has(nextLessonId)) {
      return prefetched.get(nextLessonId)
    }

    const request = fetcher(nextLessonId)
    prefetched.set(nextLessonId, request)
    return request
  }

  function getPrefetchedLesson(lessonId) {
    return prefetched.get(lessonId)
  }

  return { prefetchNextLesson, getPrefetchedLesson }
}
