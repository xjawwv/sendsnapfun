export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const body = await readBody(event)
  const db = await getDb()

  const albumId = generateAlbumId()
  const name = body.name || 'Proyek Tanpa Judul'
  const paket = body.paket || 'Self Photo'
  const driveLink = body.drive_link || ''
  const groupName = (body.group_name || '').trim()
  const hours = parseInt(body.hours) || 168

  const folderId = getDriveFolderId(driveLink)
  if (!folderId) {
    return { success: false, message: 'Link G-Drive tidak valid.' }
  }

  db[albumId] = {
    id: albumId,
    name,
    paket,
    drive_link: driveLink,
    folder_id: folderId,
    group_name: groupName,
    expires_at: Math.floor(Date.now() / 1000) + (hours * 3600),
    created_at: Math.floor(Date.now() / 1000),
  }

  await saveDb(db)
  return { success: true, album_id: albumId }
})
