export default defineEventHandler(async (event) => {
  const query = getQuery(event)
  const code = query.code as string
  const error = query.error as string

  if (error) {
    return `
      <html><body style="font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;">
          <h2 style="color:#dc2626;">Authorization Failed</h2>
          <p>${error}</p>
          <a href="/dashboard">Back to Dashboard</a>
        </div>
      </body></html>
    `
  }

  if (!code) {
    return `
      <html><body style="font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;">
          <h2 style="color:#dc2626;">Missing authorization code</h2>
          <a href="/dashboard">Back to Dashboard</a>
        </div>
      </body></html>
    `
  }

  try {
    const oauth2Client = getOAuth2Client()
    const tokens = await getTokensFromCode(oauth2Client, code)
    await saveTokens(tokens)

    return `
      <html><body style="font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;">
          <div style="width:60px;height:60px;background:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:white;font-size:30px;">&#10003;</div>
          <h2 style="color:#059669;">Google Drive Connected!</h2>
          <p style="color:#6b7280;">Your Google Drive is now linked. You can close this tab.</p>
          <a href="/dashboard" style="display:inline-block;margin-top:16px;padding:12px 24px;background:#355faa;color:white;text-decoration:none;border-radius:12px;font-weight:bold;">Go to Dashboard</a>
        </div>
      </body></html>
    `
  } catch (err: any) {
    return `
      <html><body style="font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;">
        <div style="text-align:center;">
          <h2 style="color:#dc2626;">Failed to connect</h2>
          <p>${err.message}</p>
          <a href="/dashboard">Back to Dashboard</a>
        </div>
      </body></html>
    `
  }
})
