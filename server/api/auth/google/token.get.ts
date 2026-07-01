export default defineEventHandler(async (event) => {
  requireAdmin(event)

  const tokens = await getStoredTokens()
  if (!tokens?.access_token) {
    throw createError({ statusCode: 401, statusMessage: 'Google Drive not connected.' })
  }

  const oauth2Client = getOAuth2Client()
  oauth2Client.setCredentials(tokens)

  if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
    const { credentials } = await oauth2Client.refreshAccessToken()
    await saveTokens(credentials)
    oauth2Client.setCredentials(credentials)
  }

  const accessToken = await oauth2Client.getAccessToken()
  return { token: accessToken.token }
})
