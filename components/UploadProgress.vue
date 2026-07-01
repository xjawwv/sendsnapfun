<script setup lang="ts">
const { uploading, currentFile, totalFiles, currentFileName } = useUploadState()
const progressPercent = computed(() => {
  if (totalFiles.value === 0) return 0
  return Math.round((currentFile.value / totalFiles.value) * 100)
})
</script>

<template>
  <div v-if="uploading" class="fixed bottom-20 right-4 md:right-6 bg-white rounded-2xl shadow-2xl border border-gray-200 p-4 z-[200] w-72">
    <div class="flex items-center gap-3 mb-3">
      <svg class="animate-spin text-[#355faa] shrink-0" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
      <div class="min-w-0">
        <p class="text-xs font-bold text-[#355faa]">Mengupload ke Drive...</p>
        <p class="text-[10px] text-gray-400 truncate">{{ currentFileName }}</p>
      </div>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
      <div class="bg-[#355faa] h-full rounded-full transition-all duration-300" :style="{ width: progressPercent + '%' }"></div>
    </div>
    <p class="text-[10px] text-gray-500 mt-1.5 font-medium text-right">{{ currentFile }} / {{ totalFiles }}</p>
  </div>
</template>
