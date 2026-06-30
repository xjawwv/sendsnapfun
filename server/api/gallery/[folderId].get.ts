export default defineEventHandler(async (event) => {
  const folderId = getRouterParam(event, 'folderId')
  const config = useRuntimeConfig()
  const query = getQuery(event)

  const albumId = query.album as string
  const db = await getDb()

  if (albumId) {
    const album = db[albumId.toUpperCase()]
    if (!album) {
      throw createError({ statusCode: 404, statusMessage: 'Album not found' })
    }
    const now = Math.floor(Date.now() / 1000)
    if (now > album.expires_at) {
      throw createError({ statusCode: 410, statusMessage: 'Link has expired' })
    }
  }

  if (!folderId) {
    throw createError({ statusCode: 400, statusMessage: 'Folder ID required' })
  }

  try {
    const files = await fetchDriveImages(folderId, config.gdriveApiKey)
    return {
      success: true,
      files: files.map((f) => ({
        id: f.id,
        name: f.name,
        displayUrl: `https://www.googleapis.com/drive/v3/files/${f.id}?alt=media&key=${config.gdriveApiKey}`,
        downloadUrl: `https://www.googleapis.com/drive/v3/files/${f.id}?alt=media&key=${config.gdriveApiKey}`,
      })),
    }
  } catch (error: any) {
    throw createError({ statusCode: 500, statusMessage: error.message })
  }
})
