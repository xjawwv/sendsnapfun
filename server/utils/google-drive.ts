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
