<script setup lang="ts">
const { uploading, currentFile, totalFiles, currentFileName } = useUploadState()
const progressPercent = computed(() => {
  if (totalFiles.value === 0) return 0
  return Math.round((currentFile.value / totalFiles.value) * 100)
})
</script>

<template>
  <div v-if="uploading" class="fixed bottom-24 right-4 md:right-6 bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-gray-100 p-5 z-[200] w-64">
    <div class="flex items-center gap-4 mb-4">
      <div class="relative w-10 h-10 shrink-0">
        <svg class="w-10 h-10 -rotate-90" viewBox="0 0 40 40">
          <circle cx="20" cy="20" r="16" fill="none" stroke="#e5e7eb" stroke-width="3" />
          <circle cx="20" cy="20" r="16" fill="none" stroke="#355faa" stroke-width="3" stroke-linecap="round"
            :stroke-dasharray="100.53"
            :stroke-dashoffset="100.53 - (100.53 * progressPercent / 100)"
            class="transition-all duration-500 ease-out" />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
          <svg class="animate-spin w-4 h-4 text-[#355faa]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
        </div>
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-gray-800">Mengupload ke Drive...</p>
        <p class="text-xs text-gray-400 truncate mt-0.5">{{ currentFileName }}</p>
      </div>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
      <div class="bg-[#355faa] h-full rounded-full transition-all duration-500 ease-out" :style="{ width: progressPercent + '%' }"></div>
    </div>
    <div class="flex justify-between items-center mt-2.5">
      <p class="text-[11px] text-gray-400 font-medium">{{ currentFile }} / {{ totalFiles }}</p>
      <p class="text-[11px] text-[#355faa] font-semibold">{{ progressPercent }}%</p>
    </div>
  </div>
</template>
