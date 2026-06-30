import { google } from 'googleapis'

const SCOPES = ['https://www.googleapis.com/auth/drive.file']
const TOKENS_KEY = '_google_oauth_tokens'

export function getOAuth2Client() {
  const config = useRuntimeConfig()
  const clientId = config.gdriveOauthClientId
  const clientSecret = config.gdriveOauthClientSecret

  if (!clientId || !clientSecret) {
    throw createError({ statusCode: 500, statusMessage: 'Google OAuth credentials not configured' })
  }

  return new google.auth.OAuth2(clientId, clientSecret, config.gdriveOauthRedirectUri)
}

export function getAuthUrl(oauth2Client: any): string {
  return oauth2Client.generateAuthUrl({
    access_type: 'offline',
    scope: SCOPES,
    prompt: 'consent',
  })
}

export async function getTokensFromCode(oauth2Client: any, code: string) {
  const { tokens } = await oauth2Client.getToken(code)
  return tokens
}

export async function saveTokens(tokens: any) {
  const db = await getDb()
  const tokenData = {
    access_token: tokens.access_token,
    refresh_token: tokens.refresh_token,
    expiry_date: tokens.expiry_date,
    scope: tokens.scope,
    token_type: tokens.token_type,
  }
  db[TOKENS_KEY] = tokenData as any
  await saveDb(db)
}

export async function getStoredTokens(): Promise<any | null> {
  const db = await getDb()
  return db[TOKENS_KEY] || null
}

export async function clearTokens() {
  const db = await getDb()
  delete db[TOKENS_KEY]
  await saveDb(db)
}

export async function getAuthenticatedDrive(): Promise<any> {
  const oauth2Client = getOAuth2Client()
  const tokens = await getStoredTokens()

  if (!tokens) {
    throw createError({ statusCode: 401, statusMessage: 'Google Drive not connected. Please connect your Google account first.' })
  }

  oauth2Client.setCredentials(tokens)

  if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
    const { credentials } = await oauth2Client.refreshAccessToken()
    await saveTokens(credentials)
    oauth2Client.setCredentials(credentials)
  }

  return google.drive({ version: 'v3', auth: oauth2Client })
}
