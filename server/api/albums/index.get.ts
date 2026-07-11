export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const db = await getDb()

  const now = Math.floor(Date.now() / 1000)
  let deletedCount = 0

  // Auto-delete expired albums
  for (const id of Object.keys(db)) {
    if (id.startsWith('_')) continue
    if (now > db[id].expires_at) {
      try { await deleteDriveFolder(db[id].folder_id) } catch {}
      delete db[id]
      deletedCount++
    }
  }

  if (deletedCount > 0) {
    await saveDb(db)
    console.log(`Auto-deleted ${deletedCount} expired albums`)
  }

  const activeLinks: Album[] = []
  const groupedProjects: Record<string, Album[]> = {}
  const looseProjects: Album[] = []

  for (const id of Object.keys(db).reverse()) {
    if (id.startsWith('_')) continue
    const album = db[id]
    activeLinks.push(album)
    if (album.group_name) {
      if (!groupedProjects[album.group_name]) {
        groupedProjects[album.group_name] = []
      }
      groupedProjects[album.group_name].push(album)
    } else {
      looseProjects.push(album)
    }
  }

  return {
    activeLinks: activeLinks.length,
    groupedProjects,
    looseProjects,
  }
})
