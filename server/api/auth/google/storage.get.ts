export default defineEventHandler(async (event) => {
  requireAdmin(event)

  const tokens = await getStoredTokens()
  if (!tokens?.access_token) {
    return { connected: false, usage: 0, limit: 0 }
  }

  const oauth2Client = getOAuth2Client()
  oauth2Client.setCredentials(tokens)

  if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
    const { credentials } = await oauth2Client.refreshAccessToken()
    await saveTokens(credentials)
    oauth2Client.setCredentials(credentials)
  }

  const accessToken = await oauth2Client.getAccessToken()

  try {
    const response = await fetch('https://www.googleapis.com/drive/v3/about?fields=storageQuota', {
      headers: { Authorization: `Bearer ${accessToken.token}` },
    })
    const data = await response.json()

    return {
      connected: true,
      usage: data.storageQuota?.usage || 0,
      limit: data.storageQuota?.limit || 0,
      usageInDrive: data.storageQuota?.usageInDrive || 0,
      usageInTrash: data.storageQuota?.usageInDriveTrash || 0,
    }
  } catch {
    return { connected: true, usage: 0, limit: 0 }
  }
})
