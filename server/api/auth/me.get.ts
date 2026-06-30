export default defineEventHandler(async (event) => {
  const session = getCookie(event, 'admin_session')
  return { authenticated: session === 'authenticated' }
})
