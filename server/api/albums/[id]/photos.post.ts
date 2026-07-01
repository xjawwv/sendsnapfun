export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const id = getRouterParam(event, 'id')
  const body = await readBody(event)
  const fileId = body.file_id

  if (!id || !fileId) {
    return { success: false, message: 'Missing required fields' }
  }

  try {
    const drive = await getAuthenticatedDrive()
    await drive.files.delete({ fileId })
    return { success: true }
  } catch (error) {
    console.error('Delete photo error:', error)
    return { success: false, message: 'Gagal menghapus foto dari Google Drive.' }
  }
})
