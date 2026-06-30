export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const db = await getDb()

  const now = Math.floor(Date.now() / 1000)
  const activeLinks: Album[] = []
  const expiredProjects: Album[] = []
  const groupedProjects: Record<string, Album[]> = {}
  const looseProjects: Album[] = []

  for (const id of Object.keys(db).reverse()) {
    const album = db[id]
    if (now > album.expires_at) {
      expiredProjects.push(album)
    } else {
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
  }

  return {
    activeLinks: activeLinks.length,
    expiredCount: expiredProjects.length,
    expiredProjects,
    groupedProjects,
    looseProjects,
  }
})
