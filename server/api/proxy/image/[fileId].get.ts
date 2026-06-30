import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'fs'
import { join } from 'path'

export default defineEventHandler(async (event) => {
  const fileId = getRouterParam(event, 'fileId')
  if (!fileId) {
    throw createError({ statusCode: 400, statusMessage: 'File ID required' })
  }

  const cacheDir = join(process.cwd(), '.data', 'thumbs')
  if (!existsSync(cacheDir)) {
    mkdirSync(cacheDir, { recursive: true })
  }

  const cachePath = join(cacheDir, fileId)
  if (existsSync(cachePath)) {
    const cached = readFileSync(cachePath)
    setResponseHeader(event, 'Content-Type', 'image/jpeg')
    setResponseHeader(event, 'Cache-Control', 'public, max-age=86400')
    return cached
  }

  const config = useRuntimeConfig()
  const url = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media&key=${config.gdriveApiKey}`

  try {
    const response = await fetch(url)
    if (!response.ok) {
      throw createError({ statusCode: response.status, statusMessage: 'Failed to fetch image' })
    }

    const buffer = Buffer.from(await response.arrayBuffer())

    writeFileSync(cachePath, buffer)

    setResponseHeader(event, 'Content-Type', 'image/jpeg')
    setResponseHeader(event, 'Cache-Control', 'public, max-age=86400')
    setResponseHeader(event, 'Content-Length', buffer.length.toString())

    return buffer
  } catch (error: any) {
    console.error('Proxy error:', error.message)
    throw createError({ statusCode: 500, statusMessage: 'Failed to load image.' })
  }
})
