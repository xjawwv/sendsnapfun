export function isAdmin(event: H3Event): boolean {
  const session = getCookie(event, 'admin_session')
  return session === 'authenticated'
}

export function requireAdmin(event: H3Event): void {
  if (!isAdmin(event)) {
    throw createError({ statusCode: 401, message: 'Unauthorized' })
  }
  // Refresh session cookie on every authenticated request
  setCookie(event, 'admin_session', 'authenticated', {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24,
  })
}
