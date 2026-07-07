export default defineEventHandler(async (event) => {
  requireAdmin(event)

  let tokens = await getStoredTokens()
  if (!tokens?.access_token) {
    return { connected: false, usage: 0, limit: 0 }
  }

  if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
    tokens = await refreshAccessToken(tokens)
  }

  try {
    const response = await fetch('https://www.googleapis.com/drive/v3/about?fields=storageQuota', {
      headers: { Authorization: `Bearer ${tokens.access_token}` },
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
