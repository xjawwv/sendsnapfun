export interface DriveFile {
  id: string
  name: string
  mimeType: string
  thumbnailLink?: string
}

export interface DriveFolder {
  id: string
  name: string
}

export async function fetchDriveSubfolders(folderId: string, apiKey: string): Promise<DriveFolder[]> {
  const query = encodeURIComponent(`'${folderId}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false`)
  const url = `https://www.googleapis.com/drive/v3/files?q=${query}&key=${apiKey}&fields=files(id,name)&pageSize=1000`

  const response = await fetch(url)
  if (!response.ok) {
    throw new Error('Gagal akses API Google Drive. Pastikan folder sudah di-set ke "Siapa saja yang memiliki link".')
  }

  const data = await response.json()
  return data.files || []
}

export async function fetchDriveFolderName(folderId: string, apiKey: string): Promise<string> {
  const url = `https://www.googleapis.com/drive/v3/files/${folderId}?key=${apiKey}&fields=name`

  const response = await fetch(url)
  if (!response.ok) return 'Proyek Batch'

  const data = await response.json()
  return data.name || 'Proyek Batch'
}

export async function fetchDriveImages(folderId: string, apiKey: string): Promise<DriveFile[]> {
  const query = encodeURIComponent(`'${folderId}' in parents and mimeType contains 'image/'`)
  let allFiles: DriveFile[] = []
  let pageToken: string | undefined = undefined

  do {
    const url = `https://www.googleapis.com/drive/v3/files?q=${query}&key=${apiKey}&fields=files(id,name,thumbnailLink),nextPageToken&pageSize=100${pageToken ? '&pageToken=' + pageToken : ''}`

    const response = await fetch(url)
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.error?.message || 'Gagal mengambil foto dari Google Drive.')
    }

    const data = await response.json()
    if (!data.files) break
    allFiles = allFiles.concat(data.files)
    pageToken = data.nextPageToken
  } while (pageToken)

  return allFiles
}
