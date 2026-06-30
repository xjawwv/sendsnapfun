import { google } from 'googleapis'
import { Readable } from 'stream'

function getAuth() {
  const config = useRuntimeConfig()
  const serviceAccount = config.gdriveServiceAccount
  if (!serviceAccount) {
    throw createError({ statusCode: 500, statusMessage: 'Google Drive service account not configured' })
  }

  const credentials = typeof serviceAccount === 'string' ? JSON.parse(serviceAccount) : serviceAccount

  const auth = new google.auth.GoogleAuth({
    credentials,
    scopes: ['https://www.googleapis.com/auth/drive.file'],
  })

  return auth
}

export async function createDriveFolder(folderName: string, parentFolderId: string): Promise<string> {
  const auth = await getAuth()
  const drive = google.drive({ version: 'v3', auth })

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
  const auth = await getAuth()
  const drive = google.drive({ version: 'v3', auth })

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

export function makeFolderPublic(drive: any, folderId: string) {
  return drive.permissions.create({
    fileId: folderId,
    requestBody: {
      role: 'reader',
      type: 'anyone',
    },
  })
}
