export default defineEventHandler(async (event) => {
  requireAdmin(event)
  await clearTokens()
  return { success: true }
})
