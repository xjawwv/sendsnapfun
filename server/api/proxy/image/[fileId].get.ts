export default defineEventHandler(async (event) => {
  const fileId = getRouterParam(event, 'fileId')
  if (!fileId) {
    throw createError({ statusCode: 400, statusMessage: 'File ID required' })
  }

  try {
    const oauth2Client = getOAuth2Client()
    const tokens = await getStoredTokens()
    if (!tokens?.access_token) {
      throw createError({ statusCode: 500, statusMessage: 'Google Drive not connected.' })
    }

    oauth2Client.setCredentials(tokens)

    if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
      const { credentials } = await oauth2Client.refreshAccessToken()
      await saveTokens(credentials)
      oauth2Client.setCredentials(credentials)
    }

    const accessToken = await oauth2Client.getAccessToken()
    const url = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`

    const response = await fetch(url, {
      headers: { Authorization: `Bearer ${accessToken.token}` },
    })

    if (!response.ok) {
      throw createError({ statusCode: response.status, statusMessage: 'Failed to fetch image from Drive' })
    }

    const contentType = response.headers.get('content-type') || 'image/jpeg'

    setResponseHeader(event, 'Content-Type', contentType)
    setResponseHeader(event, 'Cache-Control', 'public, max-age=86400')

    return sendStream(event, response.body!)
  } catch (error: any) {
    console.error('Proxy image error:', error?.message || error)
    throw createError({ statusCode: 500, statusMessage: 'Failed to load image.' })
  }
})
