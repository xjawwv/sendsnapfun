export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const body = await readBody(event)
  const db = await getDb()

  const ids: string[] = body.ids || []
  for (const id of ids) {
    if (db[id]) delete db[id]
  }

  await saveDb(db)
  return { success: true }
})
