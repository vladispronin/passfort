<script setup lang="ts">
import { onMounted } from 'vue'
import { useUiStore } from './stores/ui'
import { useAuthStore } from './stores/auth'

const uiStore = useUiStore()
const authStore = useAuthStore()

onMounted(() => {
  authStore.initFromStorage()
})
</script>

<template>
  <div>
    <router-view />

    <!-- Toast notifications -->
    <div class="fixed top-4 right-4 z-50 space-y-2">
      <div
        v-for="toast in uiStore.toasts"
        :key="toast.id"
        class="px-4 py-3 rounded-lg shadow-lg text-white text-sm max-w-xs"
        :class="{
          'bg-emerald-600': toast.type === 'success',
          'bg-rose-600': toast.type === 'error',
          'bg-amber-500': toast.type === 'warning',
          'bg-brand-500': toast.type === 'info',
        }"
      >
        {{ toast.message }}
      </div>
    </div>
  </div>
</template>
