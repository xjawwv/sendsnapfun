<script setup lang="ts">
const { uploading, currentFile, totalFiles, currentFileName, avgPerMinute, getEstimatedSeconds, cancelUpload } = useUploadState()
const progressPercent = computed(() => {
  if (totalFiles.value === 0) return 0
  return Math.round((currentFile.value / totalFiles.value) * 100)
})
const estimatedTime = computed(() => {
  const s = getEstimatedSeconds()
  if (s <= 0) return ''
  if (s < 60) return s + ' detik'
  return Math.floor(s / 60) + 'm ' + (s % 60) + 'd'
})
</script>

<template>
  <div v-if="uploading" class="fixed bottom-6 right-6 z-[60]">
    <div class="bg-white border border-gray-200 p-4 rounded-2xl shadow-2xl w-80 flex items-center gap-4 animate-in relative">
      <button @click="cancelUpload" class="absolute -top-2 -right-2 w-6 h-6 bg-white border border-gray-200 rounded-full shadow-md flex items-center justify-center hover:bg-gray-50 z-10">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <div class="w-10 h-10 rounded-full border-4 border-gray-100 border-t-[#355faa] animate-spin shrink-0"></div>
      <div class="flex-1 min-w-0">
        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 truncate">{{ currentFileName || 'Mengupload...' }}</p>
        <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden mb-1">
          <div class="h-full bg-[#355faa] transition-all duration-300" :style="{ width: progressPercent + '%' }"></div>
        </div>
        <div class="flex justify-between items-center">
          <p class="text-[10px] font-bold text-right text-[#355faa]">{{ currentFile }} / {{ totalFiles }}</p>
          <p v-if="estimatedTime" class="text-[10px] text-gray-400">~{{ estimatedTime }} lagi</p>
        </div>
      </div>
    </div>
  </div>
</template>
