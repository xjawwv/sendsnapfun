import { createDriveFolder, uploadFileToDrive } from '~/server/utils/google-drive'

export default defineEventHandler(async (event) => {
  requireAdmin(event)

  const config = useRuntimeConfig()
  const parentFolderId = config.gdriveUploadFolderId
  if (!parentFolderId) {
    return { success: false, message: 'Folder tujuan upload belum dikonfigurasi.' }
  }

  const formData = await readFormData(event)
  const name = formData.get('name') as string || 'Proyek Tanpa Judul'
  const paket = formData.get('paket') as string || 'Self Photo'
  const groupName = (formData.get('group_name') as string || '').trim()
  const hours = parseInt(formData.get('hours') as string) || 168
  const files = formData.getAll('files') as File[]

  if (!files || files.length === 0) {
    return { success: false, message: 'Tidak ada file yang diupload.' }
  }

  const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif']
  const validFiles = files.filter(f => validTypes.includes(f.type))
  if (validFiles.length === 0) {
    return { success: false, message: 'Tidak ada file gambar yang valid. Format: JPG, PNG, WebP, GIF.' }
  }

  const sanitizedName = name.replace(/[<>:"/\\|?*]/g, '_').substring(0, 100)

  try {
    const folderId = await createDriveFolder(sanitizedName, parentFolderId)

    for (const file of validFiles) {
      const buffer = Buffer.from(await file.arrayBuffer())
      const fileName = file.name || `photo_${Date.now()}.jpg`
      await uploadFileToDrive(folderId, fileName, buffer, file.type)
    }

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

    return {
      success: true,
      album_id: albumId,
      folder_id: folderId,
      file_count: validFiles.length,
    }
  } catch (error: any) {
    console.error('Upload error:', error)
    return { success: false, message: error.message || 'Gagal mengupload file ke Google Drive.' }
  }
})
