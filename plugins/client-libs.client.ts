export default defineNuxtPlugin(() => {
  const scripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/gifshot/0.3.2/gifshot.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js',
  ]

  if (import.meta.client) {
    scripts.forEach((src) => {
      const script = document.createElement('script')
      script.src = src
      script.async = true
      document.head.appendChild(script)
    })
  }
})
