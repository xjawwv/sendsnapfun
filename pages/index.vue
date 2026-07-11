<script setup lang="ts">
definePageMeta({ title: 'SnapLink' })
onMounted(async () => {
  if (import.meta.client) {
    const localAuth = localStorage.getItem('admin_auth')
    if (localAuth === 'true') {
      await navigateTo('/dashboard')
      return
    }
  }

  try {
    const { authenticated } = await $fetch('/api/auth/me')
    if (authenticated) {
      if (import.meta.client) localStorage.setItem('admin_auth', 'true')
      await navigateTo('/dashboard')
    } else {
      await navigateTo('/login')
    }
  } catch {
    await navigateTo('/login')
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="flex flex-col items-center gap-4">
      <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-200 border-t-[#355faa]"></div>
      <div class="flex gap-1">
        <span class="w-2 h-2 bg-[#355faa] rounded-full animate-float" style="animation-delay:0s"></span>
        <span class="w-2 h-2 bg-[#fbdc00] rounded-full animate-float" style="animation-delay:0.2s"></span>
        <span class="w-2 h-2 bg-[#355faa] rounded-full animate-float" style="animation-delay:0.4s"></span>
      </div>
    </div>
  </div>
</template>
