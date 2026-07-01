<script setup lang="ts">
const { uploading, currentFile, totalFiles, currentFileName } = useUploadState()
const progressPercent = computed(() => {
  if (totalFiles.value === 0) return 0
  return Math.round((currentFile.value / totalFiles.value) * 100)
})
</script>

<template>
  <div v-if="uploading" class="fixed bottom-6 right-6 z-[60]">
    <div class="bg-white border border-gray-200 p-4 rounded-2xl shadow-2xl w-80 flex items-center gap-4 animate-in slide-in-from-bottom-5">
      <div class="w-10 h-10 rounded-full border-4 border-gray-100 border-t-[#355faa] animate-spin shrink-0"></div>
      <div class="flex-1 min-w-0">
        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 truncate">{{ currentFileName || 'Mengupload...' }}</p>
        <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden mb-1">
          <div class="h-full bg-[#355faa] transition-all duration-300" :style="{ width: progressPercent + '%' }"></div>
        </div>
        <p class="text-[10px] font-bold text-right text-[#355faa]">{{ currentFile }} / {{ totalFiles }}</p>
      </div>
    </div>
  </div>
</template>
