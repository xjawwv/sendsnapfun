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
const visibleCount = ref(0)
const BATCH_SIZE = 6

let timerInterval = null
let scrollObserver = null

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
    timerEl.innerText = (d > 0 ? d + 'H ' : '') + String(h).padStart(2,'0') + 'j ' + String(m).padStart(2,'0') + 'm'
  }
  update()
  timerInterval = setInterval(update, 60000)
}

async function downloadAll() {
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  showDownloadModal.value = true
  downloadProgress.value = 'Menyiapkan...'
  try {
    if (isIOS) {
      downloadProgress.value = 'Menyiapkan file ZIP khusus iPhone/iPad...'
      const zip = new JSZip()
      for (let i = 0; i < photos.value.length; i++) {
        downloadProgress.value = 'Menarik foto ' + (i+1) + ' dari ' + photos.value.length + '...'
        const res = await fetch(photos.value[i].downloadUrl)
        const blob = await res.blob()
        const ext = photos.value[i].name.split('.').pop() || 'jpg'
        zip.file('SnapFun_' + album.value.paket + '_' + album.value.name + '_' + (i+1) + '.' + ext, blob)
      }
      downloadProgress.value = 'Membuat file ZIP...'
      const zipContent = await zip.generateAsync({ type: 'blob' })
      saveAs(zipContent, 'SnapFun_' + album.value.name + '.zip')
    } else {
      for (let i = 0; i < photos.value.length; i++) {
        downloadProgress.value = 'Mengunduh foto ' + (i+1) + ' dari ' + photos.value.length + '...'
        const res = await fetch(photos.value[i].downloadUrl)
        const blob = await res.blob()
        const ext = photos.value[i].name.split('.').pop() || 'jpg'
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

async function downloadSingle(url, filename) {
  try {
    const res = await fetch(url); const blob = await res.blob(); saveAs(blob, filename)
  } catch { alert('Gagal mengunduh foto.') }
}

function toggleGifMode() { gifMode.value = !gifMode.value; if (!gifMode.value) selectedGifUrls.value.clear() }
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

function startLazyLoad() {
  visibleCount.value = BATCH_SIZE
  scrollObserver = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (entry.isIntersecting && visibleCount.value < photos.value.length) {
        visibleCount.value = Math.min(visibleCount.value + BATCH_SIZE, photos.value.length)
      }
    }
  }, { rootMargin: '200px' })

  nextTick(() => {
    const sentinel = document.getElementById('scroll-sentinel')
    if (sentinel) scrollObserver.observe(sentinel)
  })
}

onMounted(async () => {
  await loadAlbum()
  if (album.value) { startTimer(); startLazyLoad() }
})

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (scrollObserver) scrollObserver.disconnect()
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
      <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-[#355faa]">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto pt-20 pb-32 px-4 md:px-8 mt-4 bg-[#f9fafb]">
      <div class="mb-8 mt-2 bg-[#355faa] text-white rounded-2xl p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fbdc00" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-widest text-white/80 mb-1">Akses Galeri Berakhir Dalam</p>
          <p class="text-lg md:text-2xl font-black" id="timer">Menghitung...</p>
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
        <div v-for="(photo, i) in photos.slice(0, visibleCount)" :key="photo.id" class="relative aspect-[4/5] bg-white rounded-xl overflow-hidden group shadow-sm border border-gray-100">
          <img :src="photo.displayUrl" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" crossorigin="anonymous">
          <div v-if="!gifMode" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-3">
            <button @click="downloadSingle(photo.downloadUrl, 'SnapFun_' + album.paket + '_' + album.name + '_' + (i+1) + '.' + (photo.name.split('.').pop() || 'jpg'))" class="w-full bg-white text-[#355faa] py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest hover:bg-gray-50 shadow-lg btn-touch">Unduh</button>
          </div>
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
      <div v-if="visibleCount < photos.length" id="scroll-sentinel" class="flex justify-center py-8 text-gray-400">
        <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
      </div>
      <div class="mt-10 text-center px-6 pb-12"><p class="text-gray-400 text-xs font-bold uppercase tracking-[0.3em]">Snap Fun Studio</p></div>
    </main>

    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 pt-4 pb-6 px-6 z-30 shadow-[0_-5px_20px_rgba(0,0,0,0.05)]">
      <div v-if="!gifMode" class="flex gap-3">
        <button @click="toggleGifMode" class="flex-1 bg-white border border-gray-200 text-gray-700 h-14 rounded-2xl font-bold text-[10px] md:text-xs uppercase shadow-sm flex items-center justify-center gap-2 btn-touch">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="14" rx="2" ry="2"/><line x1="21" y1="10" x2="3" y2="10"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="8" y1="14" x2="16" y2="14"/><line x1="14" y1="18" x2="14" y2="21"/><line x1="10" y1="18" x2="10" y2="21"/></svg>
          Buat GIF
        </button>
        <button @click="downloadAll" class="flex-[2] bg-[#fbdc00] text-gray-900 h-14 rounded-2xl font-bold text-xs md:text-sm uppercase shadow-lg btn-touch flex items-center justify-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 17 4 4 4-4"/></svg>
          Simpan Semua
        </button>
      </div>
      <div v-else class="flex gap-3">
        <button @click="toggleGifMode" class="flex-1 bg-gray-100 text-gray-600 h-14 rounded-2xl font-bold text-xs uppercase btn-touch">Batal</button>
        <button @click="generateGIF" class="flex-[2] bg-[#355faa] text-white h-14 rounded-2xl font-bold text-xs uppercase shadow-lg btn-touch">Proses GIF ({{ selectedGifUrls.size }})</button>
      </div>
    </div>

    <div v-if="showDownloadModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-6">
      <div class="bg-white p-8 rounded-[2rem] w-full max-w-sm text-center">
        <div class="animate-spin rounded-full h-16 w-16 border-4 border-gray-200 border-t-[#355faa] mx-auto mb-6"></div>
        <h3 class="font-bold text-xl text-gray-900 mb-2">Tunggu Sebentar...</h3>
        <p class="text-sm text-gray-500 font-medium">{{ downloadProgress }}</p>
      </div>
    </div>

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
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="animate-spin text-[#355faa] mb-2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
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
