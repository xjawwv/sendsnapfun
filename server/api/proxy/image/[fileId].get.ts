export default defineEventHandler(async (event) => {
  const fileId = getRouterParam(event, 'fileId')
  if (!fileId) {
    throw createError({ statusCode: 400, statusMessage: 'File ID required' })
  }

  // Public access: use API key for gallery visitors who don't have OAuth
  const config = useRuntimeConfig()
  const publicUrl = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media&key=${config.gdriveApiKey}`

  try {
    const response = await fetch(publicUrl)
    if (response.ok) {
      const buffer = Buffer.from(await response.arrayBuffer())
      const contentType = response.headers.get('content-type') || 'image/jpeg'
      setResponseHeader(event, 'Content-Type', contentType)
      setResponseHeader(event, 'Cache-Control', 'public, max-age=86400')
      return buffer
    }

    // Fallback: try OAuth (admin upload flow)
    let tokens = await getStoredTokens()
    if (!tokens?.access_token) {
      throw createError({ statusCode: 500, statusMessage: 'Failed to load image.' })
    }
    if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
      tokens = await refreshAccessToken(tokens)
    }

    const authUrl = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`
    const authResponse = await fetch(authUrl, {
      headers: { Authorization: `Bearer ${tokens.access_token}` },
    })
    if (!authResponse.ok) {
      throw createError({ statusCode: authResponse.status, statusMessage: 'Failed to fetch image' })
    }

    const buffer = Buffer.from(await authResponse.arrayBuffer())
    const contentType = authResponse.headers.get('content-type') || 'image/jpeg'
    setResponseHeader(event, 'Content-Type', contentType)
    setResponseHeader(event, 'Cache-Control', 'public, max-age=86400')

    return buffer
  } catch (error: any) {
    console.error('Proxy error:', error.message)
    throw createError({ statusCode: 500, statusMessage: 'Failed to load image.' })
  }
})
