<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const data = ref(null)
const showCreateModal = ref(false)
const showUploadModal = ref(false)
const showEditModal = ref(false)
const editingId = ref('')

const formIsBatch = ref(false)
const formName = ref('')
const formPaket = ref('Self Photo')
const formDriveLink = ref('')
const formGroupName = ref('')
const formHours = ref('168')
const createLoading = ref(false)

const editName = ref('')
const editPaket = ref('Self Photo')
const editDriveLink = ref('')
const editGroupName = ref('')

const selectedIds = ref(new Set())

async function loadData() {
  data.value = await $fetch('/api/albums')
}

async function handleCreate() {
  createLoading.value = true
  try {
    const body = { paket: formPaket.value, drive_link: formDriveLink.value, group_name: formGroupName.value, hours: formHours.value }
    if (!formIsBatch.value) body.name = formName.value
    const res = formIsBatch.value ? await $fetch('/api/albums/batch', { method: 'POST', body }) : await $fetch('/api/albums', { method: 'POST', body })
    if (res.success) {
      if (formIsBatch.value) alert('Sukses! ' + res.count + ' Link berhasil dibuat secara otomatis.')
      showCreateModal.value = false; resetCreateForm(); await loadData()
    } else { alert(res.message) }
  } catch { alert('Terjadi kesalahan jaringan.') }
  finally { createLoading.value = false }
}

function resetCreateForm() {
  formIsBatch.value = false; formName.value = ''; formPaket.value = 'Self Photo'
  formDriveLink.value = ''; formGroupName.value = ''; formHours.value = '168'
}

async function handleDelete(id) {
  if (!confirm('Hapus riwayat proyek ini?')) return
  await $fetch('/api/albums/' + id, { method: 'DELETE' }); await loadData()
}

async function handleDeleteGroup(groupName) {
  if (!confirm('PERHATIAN: Hapus folder "' + groupName + '" beserta SELURUH link di dalamnya?')) return
  await $fetch('/api/albums/delete-group', { method: 'POST', body: { group_name: groupName } }); await loadData()
}

async function handleDeleteAllExpired() {
  if (!confirm('Yakin ingin menghapus SEMUA riwayat link yang telah kedaluwarsa?')) return
  await $fetch('/api/albums/delete-expired', { method: 'POST' }); await loadData()
}

async function handleDeleteBulk() {
  if (selectedIds.value.size === 0) return
  if (!confirm('Hapus ' + selectedIds.value.size + ' riwayat proyek terpilih secara permanen?')) return
  await $fetch('/api/albums/delete-bulk', { method: 'POST', body: { ids: Array.from(selectedIds.value) } })
  selectedIds.value.clear(); await loadData()
}

function toggleSelect(id) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
}

function copyLink(id) {
  navigator.clipboard.writeText(window.location.origin + '/gallery/' + id)
  alert('Link Tersalin! Berikan ke pelanggan.')
}

async function openEdit(id) {
  editingId.value = id
  const res = await $fetch('/api/albums/' + id)
  if (res.success) {
    editName.value = res.album.name; editPaket.value = res.album.paket
    editDriveLink.value = res.album.drive_link; editGroupName.value = res.album.group_name || ''
    showEditModal.value = true
  }
}

async function handleEdit() {
  try {
    const res = await $fetch('/api/albums/' + editingId.value, {
      method: 'PUT',
      body: { name: editName.value, paket: editPaket.value, drive_link: editDriveLink.value, group_name: editGroupName.value },
    })
    if (res.success) { showEditModal.value = false; await loadData() }
    else { alert(res.message) }
  } catch { alert('Gagal menyimpan.') }
}

async function handleLogout() {
  localStorage.removeItem('admin_auth')
  await $fetch('/api/auth/logout', { method: 'POST' }); await navigateTo('/login')
}

function countdown(expiresAt) {
  const diff = expiresAt * 1000 - Date.now()
  if (diff < 0) return 'Expired'
  return Math.floor(diff / 86400000) + ' Hr ' + Math.floor((diff % 86400000) / 3600000) + ' Jm'
}

onMounted(async () => { await loadData(); setInterval(() => loadData(), 60000) })
</script>

<template>
  <div class="min-h-screen bg-[#f3f4f6] dot-grid flex h-screen overflow-hidden">
    <aside class="hidden md:flex w-72 bg-white border-r border-gray-200 flex-col z-20 shadow-sm">
      <div class="p-6 border-b border-gray-100">
        <div class="flex items-center gap-3 mb-1">
          <div class="w-8 h-8 bg-[#355faa] rounded-lg flex items-center justify-center text-white">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><path d="M2 10h20"/></svg>
          </div>
          <span class="font-bold text-lg">Snap Link API</span>
        </div>
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider pl-11">Admin Panel</p>
      </div>
      <nav class="flex-1 p-4 space-y-2">
        <div class="bg-blue-50 text-[#355faa] p-3 rounded-xl flex items-center gap-3 font-bold text-sm cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
          Proyek Klien
        </div>
      </nav>
      <div class="p-4 border-t border-gray-100">
        <button @click="handleLogout" class="w-full flex items-center gap-3 p-3 rounded-xl text-red-500 hover:bg-red-50 font-bold text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Keluar
        </button>
      </div>
    </aside>
    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
      <header class="md:hidden bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-20 shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-[#355faa] rounded-xl flex items-center justify-center text-white">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><path d="M2 10h20"/></svg>
          </div>
          <h2 class="font-bold text-lg leading-none">Snap Link</h2>
        </div>
        <button @click="handleLogout" class="p-2 bg-red-50 text-red-500 rounded-lg">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </header>

      <main class="flex-1 overflow-y-auto p-4 md:p-10 custom-scrollbar relative">
        <div v-if="data" class="flex flex-col gap-8 pb-8">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
              <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Total Proyek (Aktif)</p>
              <p class="text-2xl md:text-3xl font-bold text-gray-800">{{ data.activeLinks }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
              <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Perlu Dihapus</p>
              <p class="text-2xl md:text-3xl font-bold text-red-500">{{ data.expiredCount }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm md:col-span-2 flex items-center justify-between">
              <div>
                <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-1">Status Storage Server</p>
                <p class="text-lg md:text-xl font-bold text-emerald-500 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z"/><path d="m9 12 2 2 4-4"/></svg>
                  0 MB (G-Drive API)
                </p>
              </div>
              <div class="flex gap-3">
                <button @click="showUploadModal = true" class="hidden md:flex bg-emerald-600 text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg hover:bg-emerald-700 items-center gap-2 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  Upload Foto
                </button>
                <button @click="showCreateModal = true" class="hidden md:flex bg-[#355faa] text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg hover:bg-[#2d5191] items-center gap-2 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Buat Link Baru
                </button>
              </div>
            </div>
          </div>

          <div v-if="data.expiredProjects.length > 0" class="p-6 bg-red-50 border border-red-200 rounded-[2rem]">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4 mb-6">
              <div>
                <div class="flex items-center gap-3 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                  <h3 class="font-bold text-red-800 text-lg uppercase tracking-wider">Perhatian: Hapus Dari Drive</h3>
                </div>
                <p class="text-sm text-red-600 font-medium leading-relaxed max-w-3xl">Link klien di bawah ini masa berlakunya sudah habis. Hapus folder fisiknya di Google Drive Anda agar tidak memenuhi kapasitas, lalu hapus riwayatnya dari sini.</p>
              </div>
              <button @click="handleDeleteAllExpired" class="shrink-0 bg-red-600 text-white px-4 py-3 rounded-xl text-xs font-bold hover:bg-red-700 shadow-md flex items-center justify-center gap-2 btn-touch w-full md:w-auto transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                Hapus Semua Riwayat
              </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div v-for="album in data.expiredProjects" :key="album.id" class="bg-white p-5 rounded-2xl border border-red-200 shadow-sm flex flex-col gap-3 relative overflow-hidden group hover:border-red-300 transition-colors">
                <div class="absolute top-0 left-0 w-1 h-full bg-red-500"></div>
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-[10px] font-black text-red-500 uppercase mb-1">Kedaluwarsa</p>
                    <h4 class="font-bold text-gray-900 truncate max-w-[150px]">{{ album.name }}</h4>
                    <p class="text-xs text-gray-400 font-mono mt-1">ID Folder: {{ album.folder_id }}</p>
                  </div>
                  <input type="checkbox" :checked="selectedIds.has(album.id)" @change="toggleSelect(album.id)" class="w-5 h-5 text-red-500 bg-gray-100 border-gray-200 rounded cursor-pointer transition-all">
                </div>
                <div class="flex gap-2 pt-2 mt-auto">
                  <a :href="album.drive_link" target="_blank" class="flex-[2] bg-gray-100 text-gray-700 py-2.5 rounded-xl text-xs font-bold btn-touch flex justify-center items-center gap-2 hover:bg-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    Cek Drive
                  </a>
                  <button @click="handleDelete(album.id)" class="px-4 bg-red-100 text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition-colors btn-touch flex justify-center items-center gap-2 font-bold text-xs">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  </button>
                </div>
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

      <div id="create-panel" v-if="showCreateModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4" @click.self="showCreateModal = false">
        <div class="bg-white rounded-[2rem] w-full max-w-3xl shadow-2xl p-6 md:p-8 max-h-[90vh] overflow-y-auto">
          <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-xl">Hubungkan Folder G-Drive Baru</h3>
            <button type="button" @click="showCreateModal = false" class="text-gray-400 hover:bg-gray-100 p-2 rounded-full transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="formIsBatch" class="w-5 h-5 text-[#355faa] bg-white border-gray-300 rounded focus:ring-[#355faa]">
              <span class="text-sm font-bold text-[#355faa]">Tarik Otomatis dari Folder Utama (Batch Processing)</span>
            </label>
            <p class="text-xs text-blue-600 mt-2 ml-8">Centang jika G-Drive berisi banyak sub-folder. Sistem akan otomatis mendeteksi dan membuatkan folder & link klien secara instan.</p>
          </div>
          <form @submit.prevent="handleCreate" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="md:col-span-1">
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Paket</label>
                <select v-model="formPaket" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none">
                  <option value="Self Photo">Self Photo</option>
                  <option value="Photobox">Photobox</option>
                  <option value="Pas Photo">Pas Photo</option>
                </select>
              </div>
              <div class="md:col-span-2" v-if="!formIsBatch">
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Nama Klien</label>
                <input type="text" v-model="formName" required class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Cth: Sesi Budi & Siska">
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Link Google Drive (Folder Utama / Folder Klien)</label>
              <input type="url" v-model="formDriveLink" required class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Paste URL Folder G-Drive disini...">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Nama Folder di Dashboard (Opsional)</label>
              <input type="text" v-model="formGroupName" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Kosongkan jika tidak ingin dikelompokkan...">
              <p class="text-[10px] text-gray-400 mt-1" v-if="formIsBatch">Jika mode Tarik Otomatis aktif dan dikosongkan, nama folder Dashboard akan otomatis mengikuti nama folder Google Drive Anda.</p>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Durasi Akses Galeri</label>
              <select v-model="formHours" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none">
                <option value="168">1 Minggu</option>
                <option value="336">2 Minggu</option>
                <option value="720">1 Bulan</option>
              </select>
            </div>
            <button type="submit" :disabled="createLoading" class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold uppercase tracking-widest flex justify-center items-center gap-2 btn-touch shadow-lg shadow-blue-900/20 disabled:opacity-50">
              {{ createLoading ? 'Memproses...' : 'Terbitkan Galeri' }}
            </button>
          </form>
        </div>
      </div>

      <div id="edit-panel" v-if="showEditModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4" @click.self="showEditModal = false">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-xl">Edit Data</h3>
            <button @click="showEditModal = false" class="text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form @submit.prevent="handleEdit" class="space-y-4">
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
            <button type="submit" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold btn-touch text-sm mt-4">Simpan</button>
          </form>
        </div>
      </div>

      <button @click="showCreateModal = true" class="md:hidden fixed bottom-6 right-6 w-14 h-14 bg-[#fbdc00] text-gray-900 rounded-full shadow-glow flex items-center justify-center z-40">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </div>

      <UploadModal v-if="showUploadModal" @close="showUploadModal = false" />
  </div>
</template>
