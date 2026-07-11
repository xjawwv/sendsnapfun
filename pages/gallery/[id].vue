<script setup lang="ts">
const route = useRoute()
const id = (route.params.id as string).toUpperCase()

const album = ref(null)
const photos = ref([])
const loading = ref(true)
const errorMsg = ref('')
const gifMode = ref(false)
const selectedGifUrls = ref(new Set())
const gifResult = ref('')
const gifProcessing = ref(false)
const showGifModal = ref(false)
const showDownloadModal = ref(false)
const downloadProgress = ref('')
const selectMode = ref(false)
const selectedPhotos = ref(new Set())

let timerInterval = null

async function loadAlbum() {
  try {
    const res = await $fetch('/api/album/' + id)
    album.value = res.album
  } catch (err) {
    if (err.statusCode === 404) errorMsg.value = 'not_found'
    else if (err.statusCode === 410) errorMsg.value = 'expired'
    else errorMsg.value = 'error'
    return
  }
  try {
    const gallery = await $fetch('/api/gallery/' + album.value.folder_id + '?album=' + id)
    photos.value = gallery.files
  } catch (err) {
    errorMsg.value = err.statusMessage || 'Gagal memuat galeri.'
  } finally {
    loading.value = false
  }
}

function startTimer() {
  if (!album.value) return
  const timerEl = document.getElementById('timer')
  if (!timerEl) return
  const expireTime = album.value.expires_at * 1000
  function update() {
    const diff = expireTime - Date.now()
    if (diff < 0) { window.location.reload(); return }
    const d = Math.floor(diff / 86400000)
    const h = Math.floor((diff % 86400000) / 3600000)
    const m = Math.floor((diff % 3600000) / 60000)
    timerEl.innerText = (d > 0 ? d + 'H ' : '') + String(h).padStart(2, '0') + 'j ' + String(m).padStart(2, '0') + 'm'
  }
  update()
  timerInterval = setInterval(update, 60000)
}

function toggleSelectPhoto(photoId) {
  if (selectedPhotos.value.has(photoId)) {
    selectedPhotos.value.delete(photoId)
  } else {
    selectedPhotos.value.add(photoId)
  }
}

function toggleSelectAll() {
  if (selectedPhotos.value.size === photos.value.length) {
    selectedPhotos.value.clear()
  } else {
    selectedPhotos.value = new Set(photos.value.map(p => p.id))
  }
}

async function downloadSelected() {
  const toDownload = photos.value.filter(p => selectedPhotos.value.has(p.id))
  if (toDownload.length === 0) return alert('Pilih minimal 1 foto!')

  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  showDownloadModal.value = true
  downloadProgress.value = 'Menyiapkan...'

  try {
    if (isIOS) {
      downloadProgress.value = 'Menyiapkan file ZIP khusus iPhone/iPad...'
      const zip = new JSZip()
      for (let i = 0; i < toDownload.length; i++) {
        downloadProgress.value = 'Menarik foto ' + (i+1) + ' dari ' + toDownload.length + '...'
        const res = await fetch(toDownload[i].downloadUrl)
        const blob = await res.blob()
        const ext = toDownload[i].name.split('.').pop() || 'jpg'
        zip.file('SnapFun_' + album.value.paket + '_' + album.value.name + '_' + (i+1) + '.' + ext, blob)
      }
      downloadProgress.value = 'Membuat file ZIP...'
      const zipContent = await zip.generateAsync({ type: 'blob' })
      saveAs(zipContent, 'SnapFun_' + album.value.name + '.zip')
    } else {
      for (let i = 0; i < toDownload.length; i++) {
        downloadProgress.value = 'Mengunduh foto ' + (i+1) + ' dari ' + toDownload.length + '...'
        const res = await fetch(toDownload[i].downloadUrl)
        const blob = await res.blob()
        const ext = toDownload[i].name.split('.').pop() || 'jpg'
        saveAs(blob, 'SnapFun_' + album.value.paket + '_' + album.value.name + '_' + (i+1) + '.' + ext)
        await new Promise(r => setTimeout(r, 600))
      }
    }
    downloadProgress.value = 'Selesai!'
    setTimeout(() => showDownloadModal.value = false, 1500)
  } catch {
    downloadProgress.value = 'Gagal mengunduh foto. Cek koneksi Anda.'
    setTimeout(() => showDownloadModal.value = false, 3000)
  }
}

async function downloadAll() {
  selectedPhotos.value = new Set(photos.value.map(p => p.id))
  await downloadSelected()
}

async function downloadSingle(url, filename) {
  try {
    const res = await fetch(url)
    const blob = await res.blob()
    saveAs(blob, filename)
  } catch { alert('Gagal mengunduh foto.') }
}

function toggleGifMode() {
  gifMode.value = !gifMode.value
  if (!gifMode.value) selectedGifUrls.value.clear()
  selectMode.value = false
  selectedPhotos.value.clear()
}

function toggleGifSelect(url) {
  if (selectedGifUrls.value.has(url)) selectedGifUrls.value.delete(url)
  else selectedGifUrls.value.add(url)
}

function generateGIF() {
  if (selectedGifUrls.value.size < 2) return alert('Pilih minimal 2 foto!')
  showGifModal.value = true; gifProcessing.value = true; gifResult.value = ''
  const images = Array.from(selectedGifUrls.value)
  gifshot.createGIF({
    images, gifWidth: 600, gifHeight: 600, interval: 0.5, crossOrigin: 'Anonymous',
  }, function(obj) {
    if (!obj.error) { gifResult.value = obj.image; gifProcessing.value = false }
    else { alert('Gagal membuat GIF.'); showGifModal.value = false }
  })
}

function enterSelectMode() {
  selectMode.value = true
  selectedPhotos.value.clear()
  gifMode.value = false
  selectedGifUrls.value.clear()
}

function exitSelectMode() {
  selectMode.value = false
  selectedPhotos.value.clear()
}

onMounted(async () => {
  await loadAlbum()
  if (album.value) startTimer()
})

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
})
</script>

<template>
  <div v-if="errorMsg === 'expired' || errorMsg === 'not_found'" class="min-h-screen flex flex-col items-center justify-center p-8 text-center bg-[#f9fafb]">
    <div class="w-24 h-24 bg-red-50 text-red-500 rounded-3xl flex items-center justify-center mb-6">
      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <h2 class="text-2xl font-bold mb-3 text-gray-900">Link Telah Kedaluwarsa</h2>
    <p class="text-gray-500 text-sm">Batas waktu akses galeri proyek ini telah habis.</p>
  </div>
  <div v-else-if="errorMsg === 'error'" class="min-h-screen flex flex-col items-center justify-center p-8 text-center bg-[#f9fafb]">
    <h2 class="text-2xl font-bold mb-3 text-gray-900">Galat</h2>
    <p class="text-gray-500 text-sm">Terjadi kesalahan saat memuat data.</p>
  </div>
  <div v-else-if="album" class="min-h-screen bg-[#f9fafb] flex flex-col h-screen overflow-hidden relative">

    <!-- Select Mode Header -->
    <div v-if="selectMode" class="fixed top-0 left-0 right-0 bg-[#355faa] text-white px-5 py-4 z-[60] shadow-lg flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button @click="toggleSelectAll" class="p-2 bg-white/20 rounded-full text-xs font-bold">
          {{ selectedPhotos.size === photos.length ? 'Batal Pilih' : 'Pilih Semua' }}
        </button>
        <span class="text-sm font-bold">{{ selectedPhotos.size }} foto dipilih</span>
      </div>
      <div class="flex items-center gap-2">
        <button @click="downloadSelected" :disabled="selectedPhotos.size === 0" class="bg-[#fbdc00] text-gray-900 px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 disabled:opacity-50">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 17 4 4 4-4"/></svg>
          Download
        </button>
        <button @click="exitSelectMode" class="p-2 bg-white/20 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>

    <!-- GIF Mode Header -->
    <div v-if="gifMode" class="fixed top-0 left-0 right-0 bg-[#fbdc00] text-gray-900 px-5 py-4 z-[60] shadow-lg flex items-center justify-between">
      <div class="flex items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-[#355faa]"><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
        <span class="text-xs md:text-sm font-bold">Pilih foto untuk GIF</span>
      </div>
      <button @click="toggleGifMode" class="p-2 bg-black/5 rounded-full">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <header class="bg-white/90 backdrop-blur-md px-5 py-4 flex justify-between items-center border-b border-gray-200 shrink-0 z-20 absolute top-0 w-full">
      <div class="overflow-hidden">
        <p class="text-[10px] font-bold text-[#355faa] uppercase tracking-widest mb-0.5">{{ album.paket }}</p>
        <h1 class="text-gray-900 font-bold text-lg truncate max-w-[200px]">{{ album.name }}</h1>
      </div>
      <div class="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden">
        <div class="navbar-logo-svg" style="transform: scale(0.18); transform-origin: center; width: 154px; height: 105px;">
          <div style="width: 153.84px; height: 105px; position: relative;">
                    <svg width="154" height="105" viewBox="0 0 154 105" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 0; top: 0;"><path d="M132.585 10.3348C128.575 10.3348 125.722 11.2509 123.431 11.9896C121.847 12.5001 120.595 12.9003 119.308 12.9003C118.022 12.9003 116.564 12.4839 114.434 11.5061L109.13 9.07215V17.8488C108.386 16.8817 107.537 15.9978 106.575 15.2053C102.657 11.9735 96.9683 10.3348 89.671 10.3348C85.4688 10.3348 81.3351 10.8613 78.3323 11.7801L75.6512 12.5995V19.0792C74.9658 17.6124 74.0931 16.3121 73.0307 15.1892C69.9858 11.9681 65.57 10.3375 59.9072 10.3375C55.3359 10.3375 52.3938 11.4228 50.0317 12.2932C48.2627 12.946 46.9841 13.4161 45.2441 13.4161C43.9893 13.4161 42.4708 13.2066 39.8898 11.6861L38.2369 10.7136V3.44135L35.9828 2.43661C32.4528 0.865038 27.7629 0 22.7671 0C10.1603 0 2.32785 6.33465 2.32785 16.5297C2.32785 20.9436 3.70927 24.4387 7.10219 28.6027L9.60931 31.6679C11.0514 33.4598 11.6973 34.3436 11.9029 34.8084C12.2904 35.6868 11.6867 36.4095 10.1471 35.9985C6.70938 35.0824 0 26.6818 0 26.6818V51.7196L2.30412 52.7055C5.46768 54.0595 9.41685 54.8842 13.5822 55.0777C11.3941 57.9038 11.0988 61.6407 11.0988 64.6146V103.719H33.4098V90.5711H44.7775C45.695 93.9479 47.2293 96.7875 49.3831 99.0495C53.1399 102.999 58.5495 105 65.4645 105C73.2891 105 79.5898 102.244 83.4125 97.5693V103.719H126.679V72.4509C126.679 69.3051 126.226 66.5354 125.33 64.1525L130.808 64.2277V54.5377C137.414 54.3281 143.032 52.2515 147.103 48.4985C151.511 44.4366 153.841 38.7091 153.841 31.9312C153.841 19.012 145.3 10.3348 132.585 10.3348Z" fill="#1759CA"></path></svg>
                    <svg width="34" height="38" viewBox="0 0 34 38" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 76.26px; top: 14.17px;"><path d="M0.262801 27.6158C0.262801 22.5492 3.15745 17.547 15.4927 15.688L18.6404 15.2393C17.6966 13.4448 15.4294 12.5448 12.4715 12.5448C8.31933 12.5448 5.23486 14.4038 3.4079 17.0984V1.44977C5.92557 0.678762 9.70075 0.165649 13.6657 0.165649C26.5045 0.165649 33.1111 5.48751 33.1111 16.3273V37.1688C30.2797 35.7585 28.139 34.9257 25.6213 34.9257C21.0289 34.9257 18.071 38.0043 11.8388 38.0043C4.47561 38.0043 0.260162 33.7705 0.260162 27.6158H0.262801ZM19.2679 24.9213V21.9716L17.8838 22.2295C14.5489 22.8715 13.2255 24.153 13.2255 26.012C13.2255 27.871 14.4224 28.7683 16.0569 28.7683C17.6914 28.7683 19.2652 27.8066 19.2652 24.9213H19.2679Z" fill="white"></path></svg>
                    <svg width="39" height="47" viewBox="0 0 39 47" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 112.89px; top: 14.17px;"><path d="M20.5846 0.168335C14.3551 0.168335 11.4604 2.7339 7.30561 2.7339C5.35475 2.7339 3.40389 2.15631 0.88623 1.00113V37.7276C7.61406 38.8022 11.7188 42.1173 15.0458 46.2464V36.7229H17.69C30.2124 36.7229 38.0791 29.4775 38.0791 17.9338C38.0791 7.16117 31.2827 0.168335 20.5846 0.168335ZM16.936 25.8186H15.0484V16.647C15.0484 13.6328 16.182 11.7093 19.0134 11.7093C21.8448 11.7093 23.5452 14.017 23.5452 18.2508C23.5452 23.9596 20.7771 25.8186 16.936 25.8186Z" fill="white"></path></svg>
                    <svg width="32" height="49" viewBox="0 0 32 49" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 3.76px; top: 3.83px;"><path d="M0.761993 46.1729V31.486C2.20932 34.1779 4.85353 35.7172 7.93536 35.7172C11.0172 35.7172 12.7809 34.4331 12.7809 32.0609C12.7809 30.6506 12.0875 29.4309 9.50923 26.226L6.99156 23.1473C4.22345 19.7489 3.08984 17.0544 3.08984 13.5271C3.08984 5.57521 9.19551 0.828247 19.7671 0.828247C24.2356 0.828247 28.451 1.59657 31.4722 2.94517V17.632C30.0882 15.0664 27.067 13.4008 23.8586 13.4008C21.0878 13.4008 19.4533 14.7468 19.4533 17.0571C19.4533 18.4675 20.21 19.8779 22.725 22.8921L25.2427 25.9708C28.0108 29.3691 29.1444 32.0636 29.1444 35.3975C29.1444 43.3494 22.725 48.2871 12.4672 48.2871C8.12517 48.2871 3.9071 47.5188 0.761993 46.1702V46.1729Z" fill="white"></path></svg>
                    <svg width="36" height="37" viewBox="0 0 36 37" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 38.01px; top: 14.17px;"><path d="M7.24149 3.24701C12.464 3.24701 14.6679 0.168335 21.9046 0.168335C31.2186 0.168335 35.7504 5.17051 35.7504 14.5973V36.7229H21.2771V17.1628C21.2771 14.2131 19.8298 13.0579 17.879 13.0579C15.9281 13.0579 14.4808 14.2131 14.4808 17.1628V36.7229H0.00749207V1.00113C2.83888 2.66942 4.97955 3.24432 7.24413 3.24432L7.24149 3.24701Z" fill="white"></path></svg>
                    <svg width="36" height="42" viewBox="0 0 36 42" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 87.18px; top: 58.02px;"><path d="M7.41109 3.10053C12.6336 3.10053 14.8375 0.0218506 22.0742 0.0218506C31.3882 0.0218506 35.92 5.02403 35.92 14.4508V41.8848H21.4467V17.0163C21.4467 14.0666 19.9994 12.9114 18.0486 12.9114C16.0977 12.9114 14.6504 14.0666 14.6504 17.0163V41.8848H0.177094V0.854653C3.00848 2.52294 5.14915 3.09784 7.41373 3.09784L7.41109 3.10053Z" fill="white"></path></svg>
                    <svg width="31" height="45" viewBox="0 0 31 45" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 14.86px; top: 54.99px;"><path d="M9.41823 -0.0057373H30.3109V15.5139C28.2968 13.2062 25.4654 12.18 18.3553 12.18H15.6504V19.5543H30.3135V31.7401H15.6504V44.8876H0.860809V9.61445C0.860809 2.81772 2.87494 -0.0057373 9.41823 -0.0057373Z" fill="white"></path></svg>
                    <svg width="37" height="28" viewBox="0 0 37 28" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 47.53px; top: 74.09px;"><path d="M0.529785 8.31552V0.0922852H15.1691C15.1691 12.732 15.1296 2.41607 15.1296 7.80241C15.1296 12.1625 16.5137 14.6018 18.9681 14.6018C21.0455 14.6018 22.2397 13.3822 22.2397 11.2035V0.0949742H36.7156V11.5903C36.7156 21.0815 29.6029 27.1744 18.4645 27.1744C6.69609 27.1744 0.529785 20.6974 0.529785 8.32089V8.31552Z" fill="white"></path></svg>
                    <svg width="12" height="13" viewBox="0 0 12 13" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 52.46px; top: 58.36px;"><path d="M6.22528 12.0976C9.40661 12.0976 11.9856 9.46955 11.9856 6.22769C11.9856 2.98583 9.40661 0.357788 6.22528 0.357788C3.04395 0.357788 0.464966 2.98583 0.464966 6.22769C0.464966 9.46955 3.04395 12.0976 6.22528 12.0976Z" fill="#FEDD03"></path></svg>
                    <svg width="11" height="13" viewBox="0 0 11 13" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; left: 68.79px; top: 58.04px;"><path d="M8.76108 12.4119C8.38145 12.4119 7.99918 12.3098 7.6591 12.0949L2.18615 8.6455C1.3109 8.09478 0.78891 7.19213 0.78891 6.23307C0.78891 5.274 1.30826 4.37135 2.18087 3.82063L7.65647 0.357793C8.58708 -0.23054 9.83932 0.0193019 10.4509 0.92195C11.0599 1.82191 10.7989 3.03082 9.86832 3.62184L5.74252 6.23038L9.86568 8.82818C10.7989 9.41651 11.0626 10.6254 10.4536 11.5281C10.066 12.1003 9.42015 12.4119 8.76108 12.4119Z" fill="#FEDD03"></path></svg>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto pt-20 pb-32 px-4 md:px-8 mt-4 bg-[#f9fafb]">
      <div class="mb-8 mt-2 bg-[#355faa] text-white rounded-2xl p-5 flex items-center gap-4 animate-pop">
        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center animate-float">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fbdc00" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-widest text-white/80 mb-1">Akses Galeri Berakhir Dalam</p>
          <p class="text-lg md:text-2xl font-black animate-pulse" id="timer">Menghitung...</p>
        </div>
      </div>

      <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-gray-400">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="animate-spin mb-4"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm font-bold animate-pulse">Menarik foto dari Google Drive...</p>
      </div>
      <div v-else-if="errorMsg" class="flex flex-col items-center justify-center py-20 text-red-500">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mb-2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-sm text-center px-4">{{ errorMsg }}</p>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <div v-for="(photo, i) in photos" :key="photo.id" class="relative aspect-[4/5] bg-white rounded-xl overflow-hidden group shadow-sm border border-gray-100 animate-card-enter card-lift">
          <img :src="photo.displayUrl" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" crossorigin="anonymous" decoding="async">

          <!-- Normal overlay (download) -->
          <div v-if="!gifMode && !selectMode" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-3">
            <button @click="downloadSingle(photo.downloadUrl, 'SnapFun_' + album.paket + '_' + album.name + '_' + (i+1) + '.' + (photo.name.split('.').pop() || 'jpg'))" class="w-full bg-white text-[#355faa] py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest hover:bg-gray-50 shadow-lg btn-touch">Unduh</button>
          </div>

          <!-- Select mode overlay -->
          <div v-if="selectMode" class="absolute inset-0 cursor-pointer" @click="toggleSelectPhoto(photo.id)">
            <div class="absolute top-2 right-2 w-7 h-7 rounded-full border-2 flex items-center justify-center transition-all"
              :class="selectedPhotos.has(photo.id) ? 'bg-[#355faa] border-[#355faa]' : 'bg-white/80 border-white/80'">
              <svg v-if="selectedPhotos.has(photo.id)" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="absolute inset-0 border-4 transition-all"
              :class="selectedPhotos.has(photo.id) ? 'border-[#355faa]' : 'border-transparent'"></div>
          </div>

          <!-- GIF mode overlay -->
          <label v-if="gifMode" class="absolute inset-0 bg-white/0 cursor-pointer">
            <input type="checkbox" class="hidden" :value="photo.downloadUrl" :checked="selectedGifUrls.has(photo.downloadUrl)" @change="toggleGifSelect(photo.downloadUrl)">
            <div class="absolute inset-0 border-4 transition-all flex items-start justify-end p-2" :class="selectedGifUrls.has(photo.downloadUrl) ? 'border-[#355faa] bg-[rgba(53,95,170,0.1)]' : 'border-transparent'">
              <div class="w-6 h-6 bg-[#355faa] rounded-full text-white flex items-center justify-center transition-all" :class="selectedGifUrls.has(photo.downloadUrl) ? 'opacity-100 scale-100' : 'opacity-0 scale-50'">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
            </div>
          </label>
        </div>
      </div>
      <div class="mt-10 text-center px-6 pb-12"><p class="text-gray-400 text-xs font-bold uppercase tracking-[0.3em] animate-pulse">Snap Fun Studio</p></div>
    </main>

    <!-- Bottom Actions -->
    <div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-gray-100 px-4 pb-safe pt-3 z-30">
      <div v-if="!gifMode && !selectMode" class="flex gap-2">
        <button @click="enterSelectMode" class="flex-1 bg-gray-50 border border-gray-200 text-gray-700 h-12 rounded-xl font-bold text-[11px] flex items-center justify-center gap-1.5 btn-touch active:bg-gray-100">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Pilih
        </button>
        <button @click="toggleGifMode" class="flex-1 bg-gray-50 border border-gray-200 text-gray-700 h-12 rounded-xl font-bold text-[11px] flex items-center justify-center gap-1.5 btn-touch active:bg-gray-100">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="14" rx="2" ry="2"/><line x1="21" y1="10" x2="3" y2="10"/></svg>
          GIF
        </button>
        <button @click="downloadAll" class="flex-[2] bg-[#fbdc00] text-gray-900 h-12 rounded-xl font-bold text-xs shadow-md btn-touch flex items-center justify-center gap-1.5 active:bg-yellow-400">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 17 4 4 4-4"/></svg>
          Simpan Semua
        </button>
      </div>

      <div v-if="gifMode" class="flex gap-2">
        <button @click="toggleGifMode" class="flex-1 bg-gray-50 text-gray-600 h-12 rounded-xl font-bold text-xs btn-touch border border-gray-200">Batal</button>
        <button @click="generateGIF" class="flex-[2] bg-[#355faa] text-white h-12 rounded-xl font-bold text-xs shadow-md btn-touch flex items-center justify-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/><line x1="17" y1="17" x2="22" y2="17"/></svg>
          Proses GIF ({{ selectedGifUrls.size }})
        </button>
      </div>
    </div>

    <!-- Download Modal -->
    <div v-if="showDownloadModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-6">
      <div class="bg-white p-8 rounded-[2rem] w-full max-w-sm text-center">
        <div class="animate-spin rounded-full h-16 w-16 border-4 border-gray-200 border-t-[#355faa] mx-auto mb-6"></div>
        <h3 class="font-bold text-xl text-gray-900 mb-2">Tunggu Sebentar...</h3>
        <p class="text-sm text-gray-500 font-medium">{{ downloadProgress }}</p>
      </div>
    </div>

    <!-- GIF Modal -->
    <div v-if="showGifModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-6">
      <div class="bg-white p-6 rounded-[2rem] w-full max-w-sm">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-lg">GIF Anda Siap!</h3>
          <button @click="showGifModal = false">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden mb-4 border flex items-center justify-center relative">
          <img v-if="gifResult" :src="gifResult" class="w-full h-full object-contain">
          <div v-if="gifProcessing" class="absolute inset-0 bg-white/80 flex flex-col items-center justify-center">
            <svg class="animate-spin text-[#355faa] mb-2" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <p class="text-[10px] font-bold text-[#355faa]">Memproses...</p>
          </div>
        </div>
        <a v-if="gifResult" :href="gifResult" download="SnapFun_GIF.gif" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold flex items-center justify-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 17 4 4 4-4"/></svg>
          Unduh GIF
        </a>
      </div>
    </div>
  </div>
</template>
