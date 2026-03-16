<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '../../composables/useAuth'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'

const { unlock, logout } = useAuth()
const authStore = useAuthStore()
const uiStore = useUiStore()

const masterPassword = ref('')
const isLoading = ref(false)

async function handleUnlock() {
  if (!masterPassword.value) return

  isLoading.value = true
  try {
    await unlock(masterPassword.value)
  } catch {
    uiStore.showToast('Invalid master password', 'error')
  } finally {
    isLoading.value = false
    masterPassword.value = ''
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-xl shadow-lg">
      <div class="text-center">
        <div class="text-4xl mb-4">&#128274;</div>
        <h2 class="text-2xl font-bold text-gray-900">Vault Locked</h2>
        <p class="mt-2 text-gray-600">Enter your master password to unlock</p>
        <p v-if="authStore.user" class="text-sm text-gray-500">{{ authStore.user.email }}</p>
      </div>

      <form @submit.prevent="handleUnlock" class="space-y-4">
        <input
          v-model="masterPassword"
          type="password"
          required
          autocomplete="current-password"
          placeholder="Master password"
          class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
        />

        <button
          type="submit"
          :disabled="isLoading"
          class="w-full py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {{ isLoading ? 'Unlocking...' : 'Unlock Vault' }}
        </button>

        <button
          type="button"
          @click="logout"
          class="w-full py-2 px-4 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50"
        >
          Sign Out
        </button>
      </form>
    </div>
  </div>
</template>
