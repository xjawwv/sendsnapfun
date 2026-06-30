export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const db = await getDb()
  const now = Math.floor(Date.now() / 1000)

  for (const id of Object.keys(db)) {
    if (id.startsWith('_')) continue
    if (now > db[id].expires_at) {
      try { await deleteDriveFolder(db[id].folder_id) } catch {}
      delete db[id]
    }
  }

  await saveDb(db)
  return { success: true }
})
