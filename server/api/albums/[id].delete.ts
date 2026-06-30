export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const id = getRouterParam(event, 'id')
  const db = await getDb()

  if (id && db[id]) {
    delete db[id]
    await saveDb(db)
  }

  return { success: true }
})
