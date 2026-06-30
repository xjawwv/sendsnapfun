export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const id = getRouterParam(event, 'id')
  const body = await readBody(event)
  const db = await getDb()

  if (!id || !db[id]) {
    return { success: false, message: 'Album tidak ditemukan.' }
  }

  const folderId = getDriveFolderId(body.drive_link)
  if (!folderId) {
    return { success: false, message: 'Link Invalid' }
  }

  db[id].name = body.name
  db[id].paket = body.paket
  db[id].drive_link = body.drive_link
  db[id].folder_id = folderId
  db[id].group_name = (body.group_name || '').trim()

  await saveDb(db)
  return { success: true }
})
