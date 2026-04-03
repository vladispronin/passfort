<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const { verifyTwoFactor } = useAuth()

// Если нет pending сессии — редирект на /login
if (!authStore.requiresTwoFactor) {
  router.replace('/login')
}

const code = ref('')
const isLoading = ref(false)
const isBackupMode = ref(false)

async function handleSubmit(): Promise<void> {
  if (!code.value.trim()) return

  isLoading.value = true
  try {
    await verifyTwoFactor(code.value.trim().toUpperCase())
  } catch {
    const message = isBackupMode.value
      ? 'Invalid backup code. Please try again.'
      : 'Invalid code. Please try again.'
    uiStore.showToast(message, 'error')
    code.value = ''
  } finally {
    isLoading.value = false
  }
}

function switchToBackup(): void {
  isBackupMode.value = true
  code.value = ''
}

function switchToTotp(): void {
  isBackupMode.value = false
  code.value = ''
}

function goBack(): void {
  authStore.clearPendingTwoFactor()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4 shadow-lg">
          <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-white">Two-Factor Authentication</h1>
        <p class="text-slate-400 text-sm mt-2">
          {{ isBackupMode ? 'Enter a backup code' : 'Enter the 6-digit code from your authenticator app' }}
        </p>
      </div>

      <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 shadow-2xl border border-white/10">
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <input
              v-model="code"
              :type="isBackupMode ? 'text' : 'text'"
              :inputmode="isBackupMode ? 'text' : 'numeric'"
              :maxlength="isBackupMode ? 8 : 6"
              :placeholder="isBackupMode ? 'XXXXXXXX' : '000000'"
              autocomplete="one-time-code"
              class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-400 text-center text-2xl tracking-widest font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              :class="{ 'text-base tracking-normal': isBackupMode }"
            />
          </div>

          <button
            type="submit"
            :disabled="isLoading || !code.trim()"
            class="w-full py-3 bg-blue-600 hover:bg-blue-500 disabled:bg-blue-800 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors"
          >
            {{ isLoading ? 'Verifying...' : 'Verify' }}
          </button>
        </form>

        <div class="mt-4 text-center space-y-2">
          <button
            v-if="!isBackupMode"
            @click="switchToBackup"
            class="text-sm text-slate-300 hover:text-white transition-colors"
          >
            Use a backup code instead
          </button>
          <button
            v-if="isBackupMode"
            @click="switchToTotp"
            class="text-sm text-slate-300 hover:text-white transition-colors"
          >
            Use authenticator app instead
          </button>
        </div>
      </div>

      <div class="text-center mt-6">
        <button
          @click="goBack"
          class="text-sm text-slate-400 hover:text-slate-200 transition-colors"
        >
          ← Back to login
        </button>
      </div>
    </div>
  </div>
</template>
