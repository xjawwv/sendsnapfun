export default defineEventHandler(async (event) => {
  requireAdmin(event)

  const oauth2Client = getOAuth2Client()
  const url = getAuthUrl(oauth2Client)

  return { success: true, url }
})
