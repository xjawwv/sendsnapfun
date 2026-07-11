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
  const drive = await getAuthenticatedDrive()
  const allFiles: { id: string; name: string }[] = []
  let pageToken: string | undefined

  do {
    const res = await drive.files.list({
      q: `'${folderId}' in parents and mimeType contains 'image/' and trashed=false`,
      fields: 'files(id,name),nextPageToken',
      pageSize: 100,
      pageToken,
    })
    if (res.data.files) allFiles.push(...res.data.files.map(f => ({ id: f.id!, name: f.name! })))
    pageToken = res.data.nextPageToken ?? undefined
  } while (pageToken)

  return allFiles
}
