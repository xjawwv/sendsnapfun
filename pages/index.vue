<script setup lang="ts">
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
    <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-200 border-t-[#355faa]"></div>
  </div>
</template>
