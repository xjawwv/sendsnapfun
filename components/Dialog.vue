<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[300] flex items-center justify-center p-6" @click.self="type === 'alert' ? resolve(true) : null">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 animate-in">
      <div class="text-center">
        <div v-if="type === 'alert'" class="w-12 h-12 bg-[#355faa]/10 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#355faa" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </div>
        <div v-if="type === 'confirm'" class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <h3 class="font-bold text-lg text-gray-900 mb-2">{{ title }}</h3>
        <p class="text-sm text-gray-500 mb-6">{{ message }}</p>
      </div>
      <div v-if="type === 'alert'" class="flex">
        <button @click="resolve(true)" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold text-sm btn-touch">OK</button>
      </div>
      <div v-if="type === 'confirm'" class="flex gap-3">
        <button @click="resolve(false)" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-bold text-sm btn-touch">Batal</button>
        <button @click="resolve(true)" class="flex-1 bg-red-600 text-white py-3 rounded-xl font-bold text-sm btn-touch">Ya, Hapus</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const { show, type, title, message, resolve } = useDialog()
</script>

<style scoped>
@keyframes slide-in-from-bottom {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
.animate-in {
  animation: slide-in-from-bottom 0.25s ease-out;
}
</style>
