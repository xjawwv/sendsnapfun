export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const id = getRouterParam(event, 'id')
  const config = useRuntimeConfig()
  const db = await getDb()

  if (!id || !db[id]) {
    return { success: false, photos: [] }
  }

  const album = db[id]
  if (!album.folder_id) {
    return { success: true, photos: [] }
  }

  try {
    const files = await fetchDriveImages(album.folder_id, config.gdriveApiKey)
    return {
      success: true,
      photos: files.map((f, i) => ({
        id: f.id,
        name: f.name,
        number: i + 1,
      })),
    }
  } catch {
    return { success: true, photos: [] }
  }
})
