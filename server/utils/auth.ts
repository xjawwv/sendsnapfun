export function isAdmin(event: H3Event): boolean {
  const session = getCookie(event, 'admin_session')
  return session === 'authenticated'
}

export function requireAdmin(event: H3Event): void {
  if (!isAdmin(event)) {
    throw createError({ statusCode: 401, message: 'Unauthorized' })
  }
}
