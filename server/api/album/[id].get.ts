export default defineEventHandler(async (event) => {
  const id = (getRouterParam(event, 'id') || '').toUpperCase()
  const db = await getDb()

  if (!db[id]) {
    throw createError({ statusCode: 404, statusMessage: 'Album not found' })
  }

  const now = Math.floor(Date.now() / 1000)
  if (now > db[id].expires_at) {
    throw createError({ statusCode: 410, statusMessage: 'Link has expired' })
  }

  return { success: true, album: db[id] }
})
