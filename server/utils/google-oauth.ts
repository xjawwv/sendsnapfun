import { google } from 'googleapis'

const SCOPES = ['https://www.googleapis.com/auth/drive']
const TOKENS_KEY = 'google_oauth_tokens'

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
  const config = useRuntimeConfig()
  const url = 'https://oauth2.googleapis.com/token'
  const body = new URLSearchParams({
    code,
    client_id: config.gdriveOauthClientId,
    client_secret: config.gdriveOauthClientSecret,
    redirect_uri: config.gdriveOauthRedirectUri,
    grant_type: 'authorization_code',
  })

  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  })

  if (!response.ok) {
    const errText = await response.text().catch(() => '')
    throw new Error(`OAuth token exchange failed (${response.status}): ${errText}`)
  }

  const tokens = await response.json()
  return tokens
}

export async function saveTokens(tokens: any) {
  // Preserve existing refresh_token if Google didn't return a new one (re-auth case)
  const existing = await getStoredTokens()
  const refreshToken = tokens.refresh_token || existing?.refresh_token || null

  const tokenData = {
    access_token: tokens.access_token,
    refresh_token: refreshToken,
    expiry_date: tokens.expiry_date,
    scope: tokens.scope,
    token_type: tokens.token_type,
  }
  await saveSetting(TOKENS_KEY, tokenData)
}

export async function getStoredTokens(): Promise<any | null> {
  return await getSetting(TOKENS_KEY) || null
}

export async function refreshAccessToken(tokens: any) {
  const config = useRuntimeConfig()
  const url = 'https://oauth2.googleapis.com/token'
  const body = new URLSearchParams({
    client_id: config.gdriveOauthClientId,
    client_secret: config.gdriveOauthClientSecret,
    refresh_token: tokens.refresh_token,
    grant_type: 'refresh_token',
  })

  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  })

  if (!response.ok) {
    const errText = await response.text().catch(() => '')
    throw new Error(`Token refresh failed (${response.status}): ${errText}`)
  }

  const newTokens = await response.json()
  const updated = {
    ...tokens,
    access_token: newTokens.access_token,
    expiry_date: Date.now() + (newTokens.expires_in || 3600) * 1000,
  }
  await saveTokens(updated)
  return updated
}

export async function clearTokens() {
  await saveSetting(TOKENS_KEY, null)
}

export async function getAuthenticatedDrive(): Promise<any> {
  const oauth2Client = getOAuth2Client()
  let tokens = await getStoredTokens()

  if (!tokens) {
    throw createError({ statusCode: 401, statusMessage: 'Google Drive not connected. Please connect your Google account first.' })
  }

  if (tokens.expiry_date && Date.now() >= tokens.expiry_date) {
    tokens = await refreshAccessToken(tokens)
  }

  oauth2Client.setCredentials(tokens)
  return google.drive({ version: 'v3', auth: oauth2Client })
}
