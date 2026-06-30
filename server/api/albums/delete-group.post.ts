export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const body = await readBody(event)
  const db = await getDb()
  const groupName = body.group_name

  for (const id of Object.keys(db)) {
    if ((db[id].group_name || '') === groupName) {
      delete db[id]
    }
  }

  await saveDb(db)
  return { success: true }
})
