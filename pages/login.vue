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
  <div class="min-h-screen flex flex-col items-center justify-center p-4 md:p-6 bg-gradient-to-br from-blue-50 via-white to-yellow-50 overflow-hidden relative">

    <!-- Pinpin-02 — floating bebas di kanan atas (melambai) -->
    <div class="absolute top-4 md:top-8 right-4 md:right-12 w-28 md:w-44 animate-float pointer-events-none z-10" style="animation-delay:0.5s">
      <img src="/Pinpin-02.png" alt="Pinpin" class="w-full h-auto drop-shadow-xl">
    </div>

    <!-- Pinpin-02 kecil — di kiri bawah (sembunyi sebagian) -->
    <div class="absolute bottom-0 left-0 w-20 md:w-32 pointer-events-none z-10 opacity-70">
      <img src="/Pinpin-02.png" alt="Pinpin" class="w-full h-auto">
    </div>

    <!-- Panpan-02 — mengarah ke login card (desktop) + di card (mobile) -->
    <div class="hidden md:block absolute left-4 lg:left-16 xl:left-28 top-1/2 -translate-y-1/2 w-40 lg:w-56 pointer-events-none z-10 animate-slide-left" style="animation-delay:0.2s">
      <img src="/Panpan-02.png" alt="Panpan" class="w-full h-auto drop-shadow-2xl">
    </div>
    <div class="md:hidden absolute left-0 bottom-20 w-24 pointer-events-none z-10 opacity-80">
      <img src="/Panpan-02.png" alt="Panpan" class="w-full h-auto">
    </div>

    <!-- Floating dots background -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <span class="absolute w-2 h-2 bg-[#355faa]/20 rounded-full animate-dot" style="left:10%;top:60%;animation-delay:0s"></span>
      <span class="absolute w-1.5 h-1.5 bg-[#fbdc00]/30 rounded-full animate-dot" style="left:30%;top:20%;animation-delay:1s"></span>
      <span class="absolute w-2.5 h-2.5 bg-[#355faa]/15 rounded-full animate-dot" style="left:70%;top:80%;animation-delay:2s"></span>
      <span class="absolute w-1.5 h-1.5 bg-[#fbdc00]/20 rounded-full animate-dot" style="left:85%;top:40%;animation-delay:3s"></span>
      <span class="absolute w-2 h-2 bg-[#355faa]/25 rounded-full animate-dot" style="left:50%;top:10%;animation-delay:4s"></span>
    </div>

    <!-- Login Card -->
    <div class="relative z-20 w-full max-w-sm bg-white/80 backdrop-blur-xl p-8 rounded-[2rem] shadow-2xl border border-white/50 text-center animate-pop">

      <!-- Avatar -->
      <div class="w-16 h-16 bg-[#355faa] rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-blue-900/20 animate-float">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>

      <h1 class="text-2xl font-bold mb-1 text-gray-900">Portal Admin</h1>
      <p class="text-sm text-gray-500 mb-8">Masukkan sandi untuk mengelola galeri.</p>

      <form @submit.prevent="handleLogin" class="space-y-4">
        <div class="relative">
          <input
            v-model="password"
            type="password"
            class="w-full bg-gray-50/80 border border-gray-200 p-4 rounded-xl text-center text-lg outline-none focus:ring-2 focus:ring-[#355faa] focus:bg-white transition-all"
            placeholder="••••••"
            required
          >
        </div>

        <transition name="fade">
          <p v-if="error" class="text-red-500 text-xs font-bold bg-red-50 py-2 rounded-lg">{{ error }}</p>
        </transition>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold text-sm uppercase tracking-wider btn-touch disabled:opacity-50 hover:bg-[#2d5191] transition-all shadow-lg shadow-blue-900/20 active:scale-95"
        >
          <span v-if="loading" class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Memproses...
          </span>
          <span v-else>Masuk Dashboard</span>
        </button>
      </form>

    </div>

    <!-- Footer branding -->
    <p class="absolute bottom-4 text-[10px] text-gray-400 font-bold uppercase tracking-[0.3em] z-10">Snap Fun Studio</p>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
