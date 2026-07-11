import { Readable } from 'stream'

export async function createDriveFolder(folderName: string, parentFolderId: string): Promise<string> {
  const drive = await getAuthenticatedDrive()

  const response = await drive.files.create({
    requestBody: {
      name: folderName,
      mimeType: 'application/vnd.google-apps.folder',
      parents: [parentFolderId],
    },
    fields: 'id',
  })

  return response.data.id!
}

export async function uploadFileToDrive(
  folderId: string,
  fileName: string,
  fileBuffer: Buffer,
  mimeType: string,
): Promise<string> {
  const drive = await getAuthenticatedDrive()

  const response = await drive.files.create({
    requestBody: {
      name: fileName,
      parents: [folderId],
    },
    media: {
      mimeType,
      body: Readable.from(fileBuffer),
    },
    fields: 'id',
  })

  return response.data.id!
}

export async function makeFolderPublic(folderId: string) {
  const drive = await getAuthenticatedDrive()

  return drive.permissions.create({
    fileId: folderId,
    requestBody: {
      role: 'reader',
      type: 'anyone',
    },
  })
}

export async function deleteDriveFolder(folderId: string) {
  const drive = await getAuthenticatedDrive()
  await drive.files.delete({ fileId: folderId })
}

export async function fetchDriveImagesAuth(folderId: string): Promise<{ id: string; name: string }[]> {
  const query = encodeURIComponent(`'${folderId}' in parents and mimeType contains 'image/' and trashed=false`)
  const allFiles: { id: string; name: string }[] = []
  let pageToken: string | undefined

  do {
    const { accessToken } = await getCurrentAccessToken()
    const url = `https://www.googleapis.com/drive/v3/files?q=${query}&fields=files(id,name),nextPageToken&pageSize=100${pageToken ? '&pageToken=' + pageToken : ''}`
    const response = await fetch(url, { headers: { Authorization: `Bearer ${accessToken}` } })
    if (!response.ok) throw new Error(await response.text().catch(() => 'Gagal fetch drive images'))
    const data = await response.json()
    if (!data.files) break
    allFiles.push(...data.files.map((f: any) => ({ id: f.id, name: f.name })))
    pageToken = data.nextPageToken
  } while (pageToken)

  return allFiles
}
