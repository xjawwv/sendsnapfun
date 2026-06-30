<script setup lang="ts">
definePageMeta({ layout: false })

const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  loading.value = true
  error.value = ''

  try {
    const res = await $fetch('/api/auth/login', {
      method: 'POST',
      body: { password: password.value },
    })

    if (res.success) {
      localStorage.setItem('admin_auth', 'true')
      await navigateTo('/dashboard')
    } else {
      error.value = res.message || 'Password salah!'
    }
  } catch {
    error.value = 'Terjadi kesalahan.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center p-6 bg-gray-50">
    <div class="w-full max-w-sm bg-white p-8 rounded-[2rem] shadow-xl border border-gray-100 text-center">
      <div class="w-16 h-16 bg-[#355faa] rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-blue-900/20">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <h1 class="text-2xl font-bold mb-2 text-gray-900">Portal Admin</h1>
      <p class="text-sm text-gray-500 mb-8">Masukkan sandi untuk mengelola galeri.</p>
      <form @submit.prevent="handleLogin" class="space-y-4">
        <input
          v-model="password"
          type="password"
          class="w-full bg-gray-50 border border-gray-200 p-4 rounded-xl text-center text-lg outline-none focus:ring-2 focus:ring-[#355faa] transition-all"
          placeholder="••••••"
          required
        >
        <p v-if="error" class="text-red-500 text-xs font-bold">{{ error }}</p>
        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold text-sm uppercase tracking-wider btn-touch disabled:opacity-50"
        >
          {{ loading ? 'Memproses...' : 'Masuk Dashboard' }}
        </button>
      </form>
    </div>
  </div>
</template>
