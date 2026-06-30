export default defineEventHandler(async (event) => {
  requireAdmin(event)

  const tokens = await getStoredTokens()
  const connected = !!(tokens?.access_token && tokens?.refresh_token)

  return { connected }
})
