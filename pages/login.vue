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
  <div class="min-h-screen flex flex-col md:flex-row bg-white overflow-hidden">

    <!-- ═══ LEFT PANEL — Pinpin hero ═══ -->
    <div class="relative flex-1 flex flex-col items-center justify-center p-8 md:p-12 bg-gradient-to-br from-blue-600 via-blue-500 to-blue-400 overflow-hidden min-h-[40vh] md:min-h-0">

      <!-- Decorative blobs -->
      <div class="absolute -top-20 -right-20 w-72 h-72 bg-white/5 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-16 -left-16 w-56 h-56 bg-yellow-300/10 rounded-full blur-3xl"></div>

      <!-- Pinpin — hero; berganti saat error -->
      <div class="relative z-10 flex flex-col items-center md:items-start gap-4 md:gap-6 max-w-md">

        <div class="w-48 md:w-72 lg:w-80 drop-shadow-2xl animate-float" style="animation-delay:0.2s">
          <img :src="error ? '/Pinpin-03.png' : '/Pinpin-02.png'" alt="Pinpin" class="w-full h-auto transition-all duration-500">
        </div>

        <div class="text-center md:text-left">
          <h1 class="text-2xl md:text-4xl font-black text-white leading-tight drop-shadow-lg">
            Snap<span class="text-[#fbdc00]">Link</span>
          </h1>
          <p class="text-sm md:text-base text-blue-100 font-medium mt-2 max-w-xs">
            Kelola & bagikan galeri foto klien dengan mudah.
          </p>
        </div>

      </div>
    </div>

    <!-- ═══ RIGHT PANEL — Login Card ═══ -->
    <div class="relative flex-1 flex items-center justify-center p-6 md:p-12 bg-gray-50/50 overflow-hidden">

      <!-- Decorative dot -->
      <div class="absolute -bottom-8 -left-8 w-40 h-40 bg-[#fbdc00]/10 rounded-full blur-2xl"></div>

      <div class="relative z-20 w-full max-w-sm animate-pop">

        <!-- Logo mini -->
        <div class="flex items-center justify-center gap-3 mb-8">
          <div class="w-10 h-10 bg-[#355faa] rounded-xl flex items-center justify-center shadow-lg shadow-blue-900/20">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          <span class="font-bold text-xl text-gray-800">Portal Admin</span>
        </div>

        <!-- Card -->
        <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-gray-100">
          <p class="text-sm text-gray-500 mb-6 text-center">Masukkan sandi untuk melanjutkan.</p>

          <form @submit.prevent="handleLogin" class="space-y-5">
            <div>
              <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Password</label>
              <input
                v-model="password"
                type="password"
                class="w-full bg-gray-50 border border-gray-200 p-4 rounded-xl text-center text-lg outline-none focus:ring-2 focus:ring-[#355faa] focus:bg-white transition-all"
                placeholder="••••••"
                required
              >
            </div>

            <transition name="fade">
              <p v-if="error" class="text-red-500 text-xs font-bold bg-red-50 py-3 rounded-xl text-center">{{ error }}</p>
            </transition>

            <button
              type="submit"
              :disabled="loading"
              class="relative w-full bg-[#355faa] text-white py-4 rounded-xl font-bold text-sm uppercase tracking-wider overflow-hidden transition-all shadow-lg shadow-blue-900/20 hover:shadow-xl hover:bg-[#2d5191] active:scale-[0.98] disabled:opacity-60"
            >
              <span v-if="loading" class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Memproses...
              </span>
              <span v-else>Masuk Dashboard</span>
            </button>
          </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-[10px] text-gray-400 font-bold uppercase tracking-[0.3em] mt-8">Snap Fun Studio</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
