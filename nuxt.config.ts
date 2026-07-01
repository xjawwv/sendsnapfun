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
    adminPassword: 'oresnaporefun',
    gdriveApiKey: 'AIzaSyDCLkm6elVRsozVyg48Aejd3K1nEl-7U2g',
    gdriveOauthClientId: '',
    gdriveOauthClientSecret: '',
    gdriveOauthRedirectUri: 'http://localhost:3000/api/auth/google/callback',
    gdriveUploadFolderId: '1YgBqzxxuq71NmHLi7MjnnLT4XcnRCWfb',
  },
  app: {
    head: {
      link: [{ rel: 'icon', type: 'image/svg+xml', href: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📸</text></svg>' }],
    },
  },
  css: ['~/assets/css/main.css'],
})
