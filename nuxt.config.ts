export default defineNuxtConfig({
  compatibilityDate: '2025-04-01',
  devtools: { enabled: true },
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
      link: [{ rel: 'icon', type: 'image/png', href: '/snaplink.png' }],
    },
  },
  css: ['~/assets/css/main.css'],
})
