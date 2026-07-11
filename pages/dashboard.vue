<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const dialog = useDialog()

const data = ref(null)
const showUploadModal = ref(false)
const showEditModal = ref(false)
const driveConnected = ref(false)
const driveChecking = ref(true)
const driveUsage = ref(0)
const driveLimit = ref(0)
const editingId = ref('')
const editPhotos = ref([])
const editPhotosLoading = ref(false)
const editUploadFile = ref(null)
const editPhotoSearch = ref('')
const isDeleting = ref(false)
const avgUploadPerMin = ref(0)

const editName = ref('')
const editPaket = ref('Self Photo')
const editDriveLink = ref('')
const editGroupName = ref('')

const selectedIds = ref(new Set())

async function loadData() {
  data.value = await $fetch('/api/albums')
}

async function handleDelete(id) {
  if (!await dialog.confirm('Hapus riwayat proyek ini?')) return
  isDeleting.value = true
  try { await $fetch('/api/albums/' + id, { method: 'DELETE' }); await loadData() }
  finally { isDeleting.value = false }
}

async function handleDeleteGroup(groupName) {
  if (!await dialog.confirm('PERHATIAN: Hapus folder "' + groupName + '" beserta SELURUH link di dalamnya?')) return
  isDeleting.value = true
  try { await $fetch('/api/albums/delete-group', { method: 'POST', body: { group_name: groupName } }); await loadData() }
  finally { isDeleting.value = false }
}


async function handleDeleteBulk() {
  if (selectedIds.value.size === 0) return
  if (!await dialog.confirm('Hapus ' + selectedIds.value.size + ' riwayat proyek terpilih secara permanen?')) return
  isDeleting.value = true
  try {
    await $fetch('/api/albums/delete-bulk', { method: 'POST', body: { ids: Array.from(selectedIds.value) } })
    selectedIds.value.clear(); await loadData()
  } finally { isDeleting.value = false }
}

function toggleSelect(id) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
}

function copyLink(id) {
  navigator.clipboard.writeText(window.location.origin + '/gallery/' + id)
  dialog.alert('Link Tersalin! Berikan ke pelanggan.')
}

async function openEdit(id) {
  editingId.value = id
  editPhotos.value = []
  const res = await $fetch('/api/albums/' + id)
  if (res.success) {
    editName.value = res.album.name; editPaket.value = res.album.paket
    editDriveLink.value = res.album.drive_link; editGroupName.value = res.album.group_name || ''
    showEditModal.value = true
    await loadEditPhotos(id)
  }
}

function naturalSort(a, b) {
  return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
}

async function loadEditPhotos(id) {
  editPhotosLoading.value = true
  try {
    const res = await $fetch('/api/albums/' + id + '/photos')
    if (res.success) editPhotos.value = res.photos.sort((a, b) => naturalSort(a.name, b.name))
  } catch {} finally { editPhotosLoading.value = false }
}

const filteredEditPhotos = computed(() => {
  const q = editPhotoSearch.value.toLowerCase()
  if (!q) return editPhotos.value
  return editPhotos.value.filter(p => p.name.toLowerCase().includes(q))
})

async function handleEdit() {
  try {
    const res = await $fetch('/api/albums/' + editingId.value, {
      method: 'PUT',
      body: { name: editName.value, paket: editPaket.value, drive_link: editDriveLink.value, group_name: editGroupName.value },
    })
    if (res.success) { showEditModal.value = false; await loadData() }
    else { dialog.alert(res.message) }
  } catch { dialog.alert('Gagal menyimpan.') }
}

async function deletePhoto(fileId, fileName) {
  if (!await dialog.confirm('Hapus foto "' + fileName + '" dari Google Drive?')) return
  isDeleting.value = true
  try {
    const res = await $fetch('/api/albums/' + editingId.value + '/photos', {
      method: 'POST',
      body: { file_id: fileId },
    })
    if (res.success) {
      await loadEditPhotos(editingId.value)
      await loadData()
    } else {
      dialog.alert(res.message || 'Gagal menghapus foto.')
    }
  } catch {
    dialog.alert('Gagal menghapus foto.')
  } finally { isDeleting.value = false }
}

async function handleEditUpload(e) {
  const files = e.target.files
  if (!files || files.length === 0) return

  const { startUpload, updateProgress, finishUpload } = useUploadState()
  startUpload(files.length)

  try {
    const tokenRes = await $fetch('/api/auth/google/token')
    const token = tokenRes.token

    const albumRes = await $fetch('/api/albums/' + editingId.value)
    const folderId = albumRes.album.folder_id

    for (let i = 0; i < files.length; i++) {
      updateProgress(i + 1, files[i].name)
      const metadata = { name: files[i].name, parents: [folderId] }
      const form = new FormData()
      form.append('metadata', new Blob([JSON.stringify(metadata)], { type: 'application/json' }))
      form.append('file', files[i])

      const res = await fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: form,
      })
      if (!res.ok) {
        const data = await res.json().catch(() => ({}))
        throw new Error(data.error?.message || 'Gagal upload ' + files[i].name)
      }
    }
    finishUpload()
    dialog.alert('Upload selesai! ' + files.length + ' foto berhasil diupload.')
    await loadEditPhotos(editingId.value)
  } catch (err) { finishUpload(err.message); dialog.alert(err.message) }
  finally { e.target.value = '' }
}

const showBulkModal = ref(false)
const bulkDriveLink = ref('')
const bulkPaket = ref('Self Photo')
const bulkGroupName = ref('')
const bulkHours = ref('168')
const bulkLoading = ref(false)
const bulkResult = ref('')

async function handleBulkAdd() {
  if (!bulkDriveLink.value) return
  bulkLoading.value = true
  bulkResult.value = ''
  try {
    const res = await $fetch('/api/albums/batch', {
      method: 'POST',
      body: {
        drive_link: bulkDriveLink.value,
        paket: bulkPaket.value,
        group_name: bulkGroupName.value,
        hours: bulkHours.value,
      },
    })
    if (res.success) {
      bulkResult.value = '✅ ' + res.count + ' proyek berhasil dibuat!'
      setTimeout(() => { showBulkModal.value = false; bulkResult.value = ''; loadData() }, 2000)
    } else {
      bulkResult.value = '❌ ' + (res.message || 'Gagal.')
    }
  } catch (err) {
    bulkResult.value = '❌ Gagal: ' + (err.message || 'Kesalahan jaringan')
  } finally { bulkLoading.value = false }
}

async function handleLogout() {
  localStorage.removeItem('admin_auth')
  await $fetch('/api/auth/logout', { method: 'POST' }); await navigateTo('/login')
}

function countdown(expiresAt) {
  if (!expiresAt) return 'N/A'
  const diff = expiresAt * 1000 - Date.now()
  if (diff < 0) return 'Expired'
  return Math.floor(diff / 86400000) + ' Hr ' + Math.floor((diff % 86400000) / 3600000) + ' Jm'
}

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B'
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i]
}

async function checkDriveStatus() {
  try {
    const res = await $fetch('/api/auth/google/status')
    driveConnected.value = res.connected
    if (res.connected) {
      const storage = await $fetch('/api/auth/google/storage')
      driveUsage.value = storage.usage || 0
      driveLimit.value = storage.limit || 0
    }
  } catch {} finally { driveChecking.value = false }
}

async function connectDrive() {
  try {
    const res = await $fetch('/api/auth/google/connect')
    if (res.url) window.location.href = res.url
  } catch { dialog.alert('Gagal menghubungkan Google Drive.') }
}

async function disconnectDrive() {
  if (!await dialog.confirm('Putuskan koneksi Google Drive?')) return
  await $fetch('/api/auth/google/disconnect', { method: 'POST' })
  driveConnected.value = false
}

onMounted(async () => {
  await loadData(); await checkDriveStatus(); setInterval(() => loadData(), 60000)
})
</script>

<template>
  <div class="min-h-screen bg-[#f3f4f6] dot-grid flex h-screen overflow-hidden">
    <aside class="hidden md:flex w-72 bg-white border-r border-gray-200 flex-col z-20 shadow-sm">
      <div class="p-6 border-b border-gray-100 flex items-center justify-center">
              <div class="navbar-logo-svg">
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
      <nav class="flex-1 p-4 space-y-2">
        <div class="bg-blue-50 text-[#355faa] p-3 rounded-xl flex items-center gap-3 font-bold text-sm cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
          Proyek Klien
        </div>
      </nav>
      <div class="px-4 py-2 border-t border-gray-100">
        <div class="flex items-center gap-3 p-3 rounded-xl text-sm">
          <div class="w-2.5 h-2.5 rounded-full shrink-0" :class="[driveChecking ? 'animate-spin bg-gray-400' : driveConnected ? 'bg-emerald-500' : 'bg-gray-300']"></div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-xs truncate">{{ driveChecking ? 'Checking...' : driveConnected ? 'Google Drive Connected' : 'Not Connected' }}</p>
          </div>
          <button v-if="!driveConnected && !driveChecking" @click="connectDrive" class="text-[10px] font-bold text-[#355faa] hover:underline shrink-0">Connect</button>
          <button v-if="driveConnected" @click="disconnectDrive" class="text-[10px] font-bold text-red-500 hover:underline shrink-0">Disconnect</button>
        </div>
      </div>
      <div class="p-4 border-t border-gray-100">
        <button @click="handleLogout" class="w-full flex items-center gap-3 p-3 rounded-xl text-red-500 hover:bg-red-50 font-bold text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Keluar
        </button>
      </div>
    </aside>
    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
      <header class="md:hidden bg-white border-b border-gray-200 px-4 py-3 flex justify-between items-center z-20 shrink-0">
        <div class="flex items-center gap-2">
          <div class="navbar-logo-svg">
            <div style="width: 100px; height: 68px; position: relative; transform: scale(0.45); transform-origin: left center;">
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
        <button @click="handleLogout" class="p-2 bg-red-50 text-red-500 rounded-lg">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </header>

      <main class="flex-1 overflow-y-auto p-4 md:p-10 custom-scrollbar relative">
        <div v-if="data" class="flex flex-col gap-8 pb-8">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-4">
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
              <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Total Proyek</p>
              <p class="text-2xl md:text-3xl font-bold text-gray-800">{{ data.activeLinks }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
              <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Upload Speed</p>
              <p class="text-2xl md:text-3xl font-bold" :class="avgUploadPerMin > 0 ? 'text-emerald-500' : 'text-gray-400'">
                {{ avgUploadPerMin > 0 ? avgUploadPerMin : '-' }}
              </p>
              <p class="text-[10px] text-gray-400 mt-0.5">file/menit</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
              <div class="mb-3">
                <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-1">Google Drive</p>
                <p class="text-sm md:text-base font-bold text-gray-800">{{ formatBytes(driveUsage) }} / {{ formatBytes(driveLimit) }}</p>
              </div>
              <div class="flex gap-2">
                <button @click="showUploadModal = true" class="flex-1 bg-[#059669] text-white py-2.5 rounded-xl font-bold text-[11px] flex items-center justify-center gap-1.5 btn-touch hover:bg-emerald-700">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  Upload
                </button>
                <button @click="showBulkModal = true" class="flex-1 bg-[#355faa] text-white py-2.5 rounded-xl font-bold text-[11px] flex items-center justify-center gap-1.5 btn-touch hover:bg-blue-700">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                  Bulk
                </button>
              </div>
            </div>
          </div>


          <div v-if="Object.keys(data.groupedProjects).length > 0">
            <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wider ml-1 mb-4">Folder Proyek Aktif</h3>
            <div class="space-y-4">
              <div v-for="(albums, groupName) in data.groupedProjects" :key="groupName" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-4 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors" @click="$event.currentTarget.nextElementSibling?.classList.toggle('hidden')">
                  <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-[#355faa]">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                    </div>
                    <div>
                      <h4 class="font-bold text-gray-900 text-lg">{{ groupName }}</h4>
                      <p class="text-xs text-gray-500 font-medium">{{ albums.length }} Link Klien Aktif</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <button @click.stop="handleDeleteGroup(groupName)" class="p-2.5 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors btn-touch" title="Hapus Seluruh Folder & Isinya">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400 transition-transform duration-300"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                </div>
                <div class="hidden border-t border-gray-100 p-5 bg-gray-50/50">
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="album in albums" :key="album.id" class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col gap-3 hover:border-blue-200 transition-colors">
                      <div class="flex justify-between items-start">
                        <div>
                          <p class="text-[10px] font-black text-[#355faa] uppercase mb-1">{{ album.paket }}</p>
                          <h4 class="font-bold text-gray-900 truncate max-w-[150px]">{{ album.name }}</h4>
                          <p class="text-xs text-gray-400 font-mono mt-1">ID: {{ album.id }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                          <span class="bg-emerald-50 text-emerald-600 px-2 py-1 rounded-lg text-[10px] font-bold uppercase">{{ countdown(album.expires_at) }}</span>
                          <input type="checkbox" :checked="selectedIds.has(album.id)" @change="toggleSelect(album.id)" class="w-5 h-5 text-[#355faa] bg-gray-100 border-gray-200 rounded cursor-pointer transition-all">
                        </div>
                      </div>
                      <div class="bg-gray-50 p-3 rounded-xl mt-auto text-xs text-gray-500 overflow-hidden text-ellipsis whitespace-nowrap" :title="album.folder_id">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline text-[#355faa]"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        {{ album.folder_id }}
                      </div>
                      <div class="flex gap-2 pt-1 mt-auto">
                        <button @click="copyLink(album.id)" class="flex-[2] bg-[#fbdc00] text-gray-900 py-2.5 rounded-xl text-xs font-bold btn-touch flex justify-center gap-2">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                          Link Web
                        </button>
                        <button @click="openEdit(album.id)" class="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-xl text-xs font-bold btn-touch hover:bg-gray-200">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mx-auto"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button @click="handleDelete(album.id)" class="px-3 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors btn-touch">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wider ml-1 mb-4">Proyek Lepas Lainnya</h3>
            <div v-if="data.looseProjects.length === 0" class="text-center py-20 bg-white rounded-3xl border border-dashed border-gray-200">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="2" class="mx-auto mb-3"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
              <p class="text-gray-400 text-sm font-bold">Tidak ada link aktif di luar folder.</p>
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div v-for="album in data.looseProjects" :key="album.id" class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col gap-3 hover:border-blue-200 transition-colors">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-[10px] font-black text-[#355faa] uppercase mb-1">{{ album.paket }}</p>
                    <h4 class="font-bold text-gray-900 truncate max-w-[150px]">{{ album.name }}</h4>
                    <p class="text-xs text-gray-400 font-mono mt-1">ID: {{ album.id }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="bg-emerald-50 text-emerald-600 px-2 py-1 rounded-lg text-[10px] font-bold uppercase">{{ countdown(album.expires_at) }}</span>
                    <input type="checkbox" :checked="selectedIds.has(album.id)" @change="toggleSelect(album.id)" class="w-5 h-5 text-[#355faa] bg-gray-100 border-gray-200 rounded cursor-pointer transition-all">
                  </div>
                </div>
                <div class="bg-gray-50 p-3 rounded-xl mt-auto text-xs text-gray-500 overflow-hidden text-ellipsis whitespace-nowrap" :title="album.folder_id">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline text-[#355faa]"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                  {{ album.folder_id }}
                </div>
                <div class="flex gap-2 pt-1 mt-auto">
                  <button @click="copyLink(album.id)" class="flex-[2] bg-[#fbdc00] text-gray-900 py-2.5 rounded-xl text-xs font-bold btn-touch flex justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Link Web
                  </button>
                  <button @click="openEdit(album.id)" class="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-xl text-xs font-bold btn-touch hover:bg-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mx-auto"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <button @click="handleDelete(album.id)" class="px-3 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors btn-touch">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
      <div v-if="selectedIds.size > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-5 z-[100]">
        <span class="text-sm font-bold whitespace-nowrap">{{ selectedIds.size }} Terpilih</span>
        <div class="h-6 w-px bg-gray-600"></div>
        <button @click="handleDeleteBulk" class="text-red-400 hover:text-red-300 font-bold text-sm flex items-center gap-2 btn-touch transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Hapus Terpilih
        </button>
      </div>

      <div id="edit-panel" v-if="showEditModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4" @click.self="showEditModal = false">
        <div class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl p-6 max-h-[90vh] overflow-y-auto">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-xl">Edit Data</h3>
            <button @click="showEditModal = false" class="text-gray-400 hover:bg-gray-100 p-2 rounded-full transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="flex flex-col md:flex-row gap-6">
            <form @submit.prevent="handleEdit" class="space-y-4 md:w-[320px] shrink-0">
              <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Paket</label>
                <select v-model="editPaket" class="w-full bg-gray-50 p-3 rounded-xl border outline-none">
                  <option value="Self Photo">Self Photo</option>
                  <option value="Photobox">Photobox</option>
                  <option value="Pas Photo">Pas Photo</option>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Nama Klien</label>
                <input type="text" v-model="editName" required class="w-full bg-gray-50 p-3 rounded-xl border outline-none">
              </div>
              <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Link Google Drive Baru</label>
                <input type="url" v-model="editDriveLink" required class="w-full bg-gray-50 p-3 rounded-xl border outline-none">
              </div>
              <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Pindah ke Folder Dashboard</label>
                <input type="text" v-model="editGroupName" class="w-full bg-gray-50 p-3 rounded-xl border outline-none" placeholder="Kosongkan jika tak ingin dikelompokkan...">
              </div>
              <button type="submit" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold btn-touch text-sm">Simpan</button>
            </form>

            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between mb-3">
                <h4 class="font-bold text-sm text-gray-700">Foto ({{ editPhotos.length }})</h4>
                <label class="bg-[#355faa] text-white px-4 py-2 rounded-xl text-xs font-bold btn-touch flex items-center gap-1.5 cursor-pointer">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  Upload
                  <input type="file" multiple accept="image/*" class="hidden" @change="handleEditUpload">
                </label>
              </div>
              <div v-if="editPhotos.length > 0" class="relative mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input v-model="editPhotoSearch" type="text" placeholder="Cari nama file..." class="w-full bg-gray-50 pl-9 pr-3 py-2 rounded-lg border border-gray-200 text-xs outline-none focus:border-[#355faa]">
              </div>
              <div v-if="editPhotosLoading" class="text-center py-10 text-gray-400">
                <svg class="animate-spin mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                <p class="text-xs">Memuat daftar foto...</p>
              </div>
              <div v-else-if="editPhotos.length === 0" class="text-center py-10 text-gray-400">
                <p class="text-xs">Belum ada foto di folder ini.</p>
              </div>
              <div v-else-if="filteredEditPhotos.length === 0" class="text-center py-10 text-gray-400">
                <p class="text-xs">Tidak ada hasil ditemukan.</p>
              </div>
              <div v-else class="max-h-[400px] overflow-y-auto rounded-xl border border-gray-100">
                <table class="w-full text-xs">
                  <thead class="sticky top-0 bg-gray-50">
                    <tr>
                      <th class="text-left py-2 px-3 font-bold text-gray-500 text-[10px] uppercase w-10">#</th>
                      <th class="text-left py-2 px-3 font-bold text-gray-500 text-[10px] uppercase">Nama File</th>
                      <th class="w-8"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-50">
                    <tr v-for="(photo, i) in filteredEditPhotos" :key="photo.id" class="hover:bg-gray-50 group">
                      <td class="py-2 px-3 text-gray-400 font-mono">{{ i + 1 }}</td>
                      <td class="py-2 px-3 text-gray-700 truncate">{{ photo.name }}</td>
                      <td class="py-2 px-3">
                        <button @click="deletePhoto(photo.id, photo.name)" class="text-gray-400 hover:text-red-500 p-1 rounded transition-colors">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

      <!-- Bulk Add Modal -->
      <div v-if="showBulkModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4" @click.self="!bulkLoading ? showBulkModal = false : null">
        <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl p-6">
          <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-xl">Tarik Otomatis dari Folder Parent</h3>
            <button @click="showBulkModal = false" :disabled="bulkLoading" class="text-gray-400 hover:bg-gray-100 p-2 rounded-full disabled:opacity-30">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form @submit.prevent="handleBulkAdd" class="space-y-4">
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
              <p class="font-bold mb-1">Cara kerja:</p>
              <p class="text-xs">Masukkan link folder utama Google Drive. Sistem akan mendeteksi semua sub-folder di dalamnya dan membuat 1 link galeri per sub-folder secara otomatis.</p>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Link Google Drive (Folder Utama)</label>
              <input v-model="bulkDriveLink" type="url" required class="w-full bg-gray-50 p-3 rounded-xl border outline-none" placeholder="https://drive.google.com/drive/folders/...">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Paket</label>
                <select v-model="bulkPaket" class="w-full bg-gray-50 p-3 rounded-xl border outline-none">
                  <option value="Self Photo">Self Photo</option>
                  <option value="Photobox">Photobox</option>
                  <option value="Pas Photo">Pas Photo</option>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Durasi</label>
                <select v-model="bulkHours" class="w-full bg-gray-50 p-3 rounded-xl border outline-none">
                  <option value="168">1 Minggu</option>
                  <option value="336">2 Minggu</option>
                  <option value="720">1 Bulan</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Nama Folder Dashboard (Opsional)</label>
              <input v-model="bulkGroupName" type="text" class="w-full bg-gray-50 p-3 rounded-xl border outline-none" placeholder="Kosongkan = pakai nama folder Google Drive">
            </div>
            <p v-if="bulkResult" class="text-sm font-bold text-center" :class="bulkResult.includes('✅') ? 'text-emerald-600' : 'text-red-500'">{{ bulkResult }}</p>
            <button type="submit" :disabled="bulkLoading || !bulkDriveLink" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold text-sm btn-touch disabled:opacity-50">
              {{ bulkLoading ? 'Memproses...' : 'Tarik Semua Sub-Folder' }}
            </button>
          </form>
        </div>
      </div>

      <UploadModal v-if="showUploadModal" @close="showUploadModal = false" />
      <UploadProgress />
  </div>

  <!-- Deleting Overlay -->
  <div v-if="isDeleting" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[250] flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 text-center animate-in">
      <div class="w-12 h-12 rounded-full border-4 border-gray-100 border-t-red-500 animate-spin mx-auto mb-4"></div>
      <p class="font-bold text-gray-900">Menghapus...</p>
      <p class="text-xs text-gray-400 mt-1">Mohon tunggu sebentar</p>
    </div>
  </div>
</template>
