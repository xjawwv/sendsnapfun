export default defineEventHandler(async (event) => {
  const body = await readBody(event)
  const config = useRuntimeConfig()

  if (body.password === config.adminPassword) {
    setCookie(event, 'admin_session', 'authenticated', {
      httpOnly: true,
      sameSite: 'lax',
      path: '/',
      maxAge: 60 * 60 * 24,
    })
    return { success: true }
  }

  return { success: false, message: 'Password salah!' }
})
