export default defineNuxtConfig({
  compatibilityDate: '2025-04-01',
  devtools: { enabled: false },
  modules: ['@nuxtjs/tailwindcss'],
  tailwindcss: {
    config: {
      theme: {
        extend: {
          colors: {
            primary: '#355faa',
            action: '#fbdc00',
          },
        },
      },
    },
  },
  nitro: {
    storage: {
      db: {
        driver: 'fs',
        base: './data',
      },
    },
  },
  runtimeConfig: {
    adminPassword: '',
    gdriveApiKey: '',
    gdriveOauthClientId: '',
    gdriveOauthClientSecret: '',
    gdriveOauthRedirectUri: 'http://localhost:3000/api/auth/google/callback',
    gdriveUploadFolderId: '',
    mysqlHost: 'localhost',
    mysqlPort: '3306',
    mysqlUser: 'root',
    mysqlPassword: '',
    mysqlDatabase: 'sendsnapfun',
  },
  app: {
    head: {
      title: 'SnapLink — Portal Admin',
      link: [
        { rel: 'icon', href: '/favicon.ico' },
      ],
    },
  },
  css: ['~/assets/css/main.css'],
})
