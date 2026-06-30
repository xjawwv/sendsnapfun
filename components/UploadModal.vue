<script setup lang="ts">
const emit = defineEmits<{ close: [] }>()

const files = ref<File[]>([])
const formName = ref('')
const formPaket = ref('Self Photo')
const formGroupName = ref('')
const formHours = ref('168')
const uploading = ref(false)
const uploadProgress = ref(0)
const dragOver = ref(false)

function onFileInput(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files) {
    addFiles(Array.from(input.files))
  }
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  if (e.dataTransfer?.files) {
    addFiles(Array.from(e.dataTransfer.files))
  }
}

function addFiles(newFiles: File[]) {
  const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
  for (const f of newFiles) {
    if (validTypes.includes(f.type) && !files.value.some(ex => ex.name === f.name && ex.size === f.size)) {
      files.value.push(f)
    }
  }
}

function removeFile(index: number) {
  files.value.splice(index, 1)
}

async function handleUpload() {
  if (!formName.value || files.value.length === 0) return

  uploading.value = true
  uploadProgress.value = 0

  try {
    const body = new FormData()
    body.append('name', formName.value)
    body.append('paket', formPaket.value)
    body.append('group_name', formGroupName.value)
    body.append('hours', formHours.value)
    for (const f of files.value) {
      body.append('files', f)
    }

    uploadProgress.value = 50
    const res = await $fetch('/api/upload', { method: 'POST', body })

    if (res.success) {
      uploadProgress.value = 100
      alert(`Sukses! ${res.file_count} foto berhasil diupload dan link galeri siap digunakan.`)
      emit('close')
      window.location.reload()
    } else {
      alert(res.message || 'Gagal mengupload.')
    }
  } catch (err: any) {
    alert(err.message || 'Terjadi kesalahan jaringan.')
  } finally {
    uploading.value = false
  }
}

const totalSize = computed(() => {
  const total = files.value.reduce((sum, f) => sum + f.size, 0)
  if (total < 1024 * 1024) return (total / 1024).toFixed(1) + ' KB'
  return (total / (1024 * 1024)).toFixed(1) + ' MB'
})
</script>

<template>
  <div class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-[2rem] w-full max-w-2xl shadow-2xl p-6 md:p-8 max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-6">
        <h3 class="font-bold text-xl">Upload & Buat Link Galeri Baru</h3>
        <button type="button" @click="$emit('close')" class="text-gray-400 hover:bg-gray-100 p-2 rounded-full transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
          <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Paket</label>
          <select v-model="formPaket" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none">
            <option value="Self Photo">Self Photo</option>
            <option value="Photobox">Photobox</option>
            <option value="Pas Photo">Pas Photo</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Nama Klien</label>
          <input v-model="formName" type="text" required class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Cth: Sesi Budi & Siska">
        </div>
      </div>

      <div class="mb-4">
        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Folder Dashboard (Opsional)</label>
        <input v-model="formGroupName" type="text" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Kosongkan jika tidak ingin dikelompokkan...">
      </div>

      <div class="mb-4">
        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Durasi Akses Galeri</label>
        <select v-model="formHours" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none">
          <option value="168">1 Minggu</option>
          <option value="336">2 Minggu</option>
          <option value="720">1 Bulan</option>
        </select>
      </div>

      <div class="mb-6">
        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Upload Foto</label>
        <div
          class="border-2 border-dashed rounded-2xl p-8 text-center transition-colors cursor-pointer"
          :class="dragOver ? 'border-[#355faa] bg-blue-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50'"
          @dragover.prevent="dragOver = true"
          @dragleave="dragOver = false"
          @drop.prevent="onDrop"
          @click="$refs.fileInput.click()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" class="mx-auto mb-3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="12"/><line x1="15" y1="15" x2="12" y2="12"/></svg>
          <p class="text-sm text-gray-500 font-bold">Klik atau drag & drop foto disini</p>
          <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP, GIF</p>
          <input ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="onFileInput">
        </div>
      </div>

      <div v-if="files.length > 0" class="mb-6">
        <div class="flex justify-between items-center mb-2">
          <span class="text-xs font-bold text-gray-500 uppercase">{{ files.length }} File ({{ totalSize }})</span>
        </div>
        <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
          <div v-for="(file, i) in files" :key="i" class="relative group aspect-square bg-gray-100 rounded-xl overflow-hidden border border-gray-200">
            <img :src="URL.createObjectURL(file)" class="w-full h-full object-cover">
            <button @click="removeFile(i)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs font-bold">×</button>
          </div>
        </div>
      </div>

      <button
        @click="handleUpload"
        :disabled="uploading || !formName || files.length === 0"
        class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold uppercase tracking-widest flex justify-center items-center gap-2 btn-touch shadow-lg shadow-blue-900/20 disabled:opacity-50"
      >
        <svg v-if="!uploading" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <svg v-else class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        {{ uploading ? 'Mengupload ke Google Drive...' : 'Upload & Terbitkan Galeri' }}
      </button>
    </div>
  </div>
</template>
