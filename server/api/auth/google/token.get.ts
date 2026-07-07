export default defineEventHandler(async (event) => {
  requireAdmin(event)

  let tokens = await getStoredTokens()
  if (!tokens?.access_token) {
    throw createError({ statusCode: 401, statusMessage: 'Google Drive not connected.' })
  }

  if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
    try {
      tokens = await refreshAccessToken(tokens)
    } catch (err: any) {
      throw createError({ statusCode: 500, statusMessage: 'Token refresh gagal. Silakan disconnect dan connect ulang Google Drive.' })
    }
  }

  return { token: tokens.access_token }
})
