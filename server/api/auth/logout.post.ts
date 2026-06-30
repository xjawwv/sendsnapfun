export default defineEventHandler(async (event) => {
  deleteCookie(event, 'admin_session', { path: '/' })
  return { success: true }
})
