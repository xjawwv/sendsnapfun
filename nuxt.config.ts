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
    gdriveServiceAccount: '',
    gdriveUploadFolderId: '',
  },
  app: {
    head: {
      link: [{ rel: 'icon', type: 'image/png', href: '/snaplink.png' }],
    },
  },
  css: ['~/assets/css/main.css'],
})
