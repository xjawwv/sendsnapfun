export default defineEventHandler(async (event) => {
  requireAdmin(event)
  const body = await readBody(event)
  const config = useRuntimeConfig()
  const db = await getDb()

  const paket = body.paket || 'Self Photo'
  const driveLink = body.drive_link || ''
  const customGroupName = (body.group_name || '').trim()
  const hours = parseInt(body.hours) || 168

  const mainFolderId = getDriveFolderId(driveLink)
  if (!mainFolderId) {
    return { success: false, message: 'Link G-Drive (Folder Utama) tidak valid.' }
  }

  let finalGroupName = customGroupName
  if (!finalGroupName) {
    finalGroupName = await fetchDriveFolderName(mainFolderId, config.gdriveApiKey)
  }

  const subfolders = await fetchDriveSubfolders(mainFolderId, config.gdriveApiKey)
  if (subfolders.length === 0) {
    return { success: false, message: 'Tidak ada sub-folder klien ditemukan di dalam link tersebut.' }
  }

  let count = 0
  for (const folder of subfolders) {
    const albumId = generateAlbumId()
    db[albumId] = {
      id: albumId,
      name: folder.name,
      paket,
      drive_link: `https://drive.google.com/drive/folders/${folder.id}`,
      folder_id: folder.id,
      group_name: finalGroupName,
      expires_at: Math.floor(Date.now() / 1000) + (hours * 3600),
      created_at: Math.floor(Date.now() / 1000),
    }
    count++
  }

  await saveDb(db)
  return { success: true, count }
})
