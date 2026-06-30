export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const id = getRouterParam(event, 'id')
  const db = await getDb()

  if (!id || !db[id]) {
    return { success: false }
  }

  return { success: true, album: db[id] }
})
