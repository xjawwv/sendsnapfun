export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const db = await getDb()
  const folder = db['_google_upload_folder']
  if (!folder?.folder_id) {
    return { connected: false }
  }
  return { connected: true, folder_id: folder.folder_id }
})
