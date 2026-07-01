export default defineEventHandler(async (event) => {
  requireAdmin(event)

  const body = await readBody(event)
  const name = body.name || 'Proyek Tanpa Judul'
  const paket = body.paket || 'Self Photo'
  const groupName = (body.group_name || '').trim()
  const hours = parseInt(body.hours) || 168
  const totalFiles = parseInt(body.total_files) || 0

  const config = useRuntimeConfig()
  const parentFolderId = config.gdriveUploadFolderId
  if (!parentFolderId) {
    return { success: false, message: 'Folder tujuan upload belum dikonfigurasi.' }
  }

  const tokens = await getStoredTokens()
  if (!tokens?.access_token) {
    return { success: false, message: 'Google Drive belum terhubung. Silakan connect Google Drive terlebih dahulu di sidebar.' }
  }

  const sanitizedName = name.replace(/[<>:"/\\|?*]/g, '_').substring(0, 100)

  try {
    const folderId = await createDriveFolder(sanitizedName, parentFolderId)
    await makeFolderPublic(folderId).catch(() => {})
    const albumId = generateAlbumId()
    const driveLink = `https://drive.google.com/drive/folders/${folderId}`
    const db = await getDb()

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

    return { success: true, album_id: albumId, folder_id: folderId }
  } catch (error: any) {
    console.error('Create folder error:', error)
    return { success: false, message: error.message || 'Gagal membuat folder di Google Drive.' }
  }
})
