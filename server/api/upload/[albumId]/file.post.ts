export default defineEventHandler(async (event) => {
  requireAdmin(event)

  const albumId = getRouterParam(event, 'albumId')
  if (!albumId) {
    return { success: false, message: 'Album ID required' }
  }

  const db = await getDb()
  const album = db[albumId]
  if (!album) {
    return { success: false, message: 'Album not found' }
  }

  const formData = await readFormData(event)
  const file = formData.get('file') as File | null
  if (!file) {
    return { success: false, message: 'No file provided' }
  }

  const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif']
  if (!validTypes.includes(file.type)) {
    return { success: false, message: 'Invalid file type: ' + file.type }
  }

  const buffer = Buffer.from(await file.arrayBuffer())
  const fileName = file.name || `photo_${Date.now()}.jpg`

  await uploadFileToDrive(album.folder_id, fileName, buffer, file.type)

  return {
    success: true,
    file_name: fileName,
    folder_id: album.folder_id,
  }
})
