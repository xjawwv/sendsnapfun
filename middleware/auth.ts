export default defineNuxtRouteMiddleware(async (to) => {
  if (to.path === '/login' || to.path.startsWith('/gallery/') || to.path === '/expired') {
    return
  }

  if (import.meta.client) {
    const localAuth = localStorage.getItem('admin_auth')
    if (localAuth === 'true') return
    try {
      const { authenticated } = await $fetch('/api/auth/me')
      if (authenticated) {
        localStorage.setItem('admin_auth', 'true')
        return
      }
    } catch {}
    return navigateTo('/login')
  }

  try {
    const headers = useRequestHeaders(['cookie'])
    if (headers.cookie && headers.cookie.includes('admin_session=authenticated')) return
  } catch {}

  return navigateTo('/login')
})
