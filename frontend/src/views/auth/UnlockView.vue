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
  <div class="min-h-screen bg-gradient-to-br from-blue-950 via-blue-800 to-blue-500 flex items-center justify-center px-4 py-12 relative overflow-hidden">
    <!-- Декоративные круги -->
    <div class="absolute -top-16 -left-16 w-72 h-72 rounded-full bg-white/5 blur-sm"></div>
    <div class="absolute top-8 right-12 w-48 h-48 rounded-full bg-white/10 blur-sm"></div>
    <div class="absolute bottom-12 left-1/4 w-56 h-56 rounded-full bg-blue-400/20 blur-lg"></div>
    <div class="absolute -bottom-12 -right-12 w-64 h-64 rounded-full bg-white/5"></div>

    <!-- Карточка -->
    <div class="relative z-10 w-full max-w-md">
      <!-- Логотип над карточкой -->
      <div class="flex flex-col items-center mb-6 text-white">
        <img src="/passfort-icon.svg" alt="PassFort" class="w-14 h-14 mb-3" />
        <h1 class="text-2xl font-bold tracking-wide">PassFort</h1>
        <p class="text-blue-200 text-sm mt-1">Your secure fortress</p>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl px-8 py-8">
        <div class="text-center mb-6">
          <div class="text-4xl mb-3">&#128274;</div>
          <h2 class="text-xl font-semibold text-slate-800">Vault Locked</h2>
          <p class="mt-1 text-slate-500 text-sm">Enter your master password to unlock</p>
          <p v-if="authStore.user" class="text-xs text-slate-400 mt-1">{{ authStore.user.email }}</p>
        </div>

        <form @submit.prevent="handleUnlock" class="space-y-4">
          <input
            v-model="masterPassword"
            type="password"
            required
            autocomplete="current-password"
            placeholder="Master password"
            class="block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
          />

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-brand-500 hover:bg-brand-600 disabled:opacity-50 transition-colors"
          >
            {{ isLoading ? 'Unlocking...' : 'Unlock Vault' }}
          </button>

          <button
            type="button"
            @click="logout"
            class="w-full py-2 px-4 border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 text-sm transition-colors"
          >
            Sign Out
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
