<script setup lang="ts">
const { uploading, currentFile, totalFiles, currentFileName } = useUploadState()
const progressPercent = computed(() => {
  if (totalFiles.value === 0) return 0
  return Math.round((currentFile.value / totalFiles.value) * 100)
})
</script>

<template>
  <div v-if="uploading" class="fixed bottom-24 right-4 md:right-6 z-[200] max-w-[260px]">
    <div class="bg-white rounded-lg shadow-[0_8px_32px_rgba(0,0,0,0.12)] border border-gray-200 overflow-hidden">
      
      <div class="flex items-center gap-3 px-4 pt-3.5 pb-2.5">
        <div class="relative w-9 h-9 shrink-0">
          <svg class="w-9 h-9 -rotate-90" viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="14.5" fill="none" stroke="#e5e7eb" stroke-width="3" />
            <circle cx="18" cy="18" r="14.5" fill="none" stroke="#1a73e8" stroke-width="3" stroke-linecap="round"
              :stroke-dasharray="91.1"
              :stroke-dashoffset="91.1 - (91.1 * progressPercent / 100)"
              class="transition-all duration-500 ease-out" />
          </svg>
          <div class="absolute inset-0 flex items-center justify-center">
            <span class="text-[9px] font-bold text-gray-700">{{ progressPercent }}%</span>
          </div>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-gray-800 leading-tight truncate">{{ currentFileName || 'Mengupload...' }}</p>
          <p class="text-[11px] text-gray-400 mt-0.5">{{ currentFile }} / {{ totalFiles }} file</p>
        </div>
      </div>

      <div class="h-1 w-full bg-gray-100">
        <div class="h-full bg-[#1a73e8] transition-all duration-500 ease-out" :style="{ width: progressPercent + '%' }"></div>
      </div>

    </div>
  </div>
</template>
