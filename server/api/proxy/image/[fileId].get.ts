export default defineEventHandler(async (event) => {
  const fileId = getRouterParam(event, 'fileId')
  if (!fileId) {
    throw createError({ statusCode: 400, statusMessage: 'File ID required' })
  }

  const config = useRuntimeConfig()
  const url = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media&key=${config.gdriveApiKey}`

  try {
    const response = await fetch(url)
    if (!response.ok) {
      throw createError({ statusCode: response.status, statusMessage: 'Failed to fetch image' })
    }

    const buffer = Buffer.from(await response.arrayBuffer())
    const contentType = response.headers.get('content-type') || 'image/jpeg'

    setResponseHeader(event, 'Content-Type', contentType)
    setResponseHeader(event, 'Cache-Control', 'public, max-age=86400')
    setResponseHeader(event, 'Content-Length', buffer.length.toString())

    return buffer
  } catch (error: any) {
    console.error('Proxy error:', error.message)
    throw createError({ statusCode: 500, statusMessage: 'Failed to load image.' })
  }
})
