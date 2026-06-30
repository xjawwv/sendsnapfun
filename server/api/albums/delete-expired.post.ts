export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const db = await getDb()
  const now = Math.floor(Date.now() / 1000)

  for (const id of Object.keys(db)) {
    if (now > db[id].expires_at) {
      delete db[id]
    }
  }

  await saveDb(db)
  return { success: true }
})
