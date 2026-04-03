<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import QRCode from 'qrcode'
import { twoFactorApi } from '../../api/twoFactor'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import type { TwoFactorSetupData, TwoFactorStatus } from '../../types/auth'

const authStore = useAuthStore()
const uiStore = useUiStore()

type Step = 'status' | 'setup' | 'backup_codes'

const step = ref<Step>('status')
const isLoading = ref(false)
const status = ref<TwoFactorStatus | null>(null)
const setupData = ref<TwoFactorSetupData | null>(null)
const qrCanvas = ref<HTMLCanvasElement | null>(null)
const backupCodes = ref<string[]>([])
const confirmCode = ref('')
const masterPasswordForAction = ref('')
const showDisableForm = ref(false)
const showRegenerateForm = ref(false)
const copiedCodes = ref(false)

onMounted(async () => {
  await loadStatus()
})

async function loadStatus(): Promise<void> {
  try {
    status.value = await twoFactorApi.getStatus()
  } catch {
    // Если не удалось загрузить — отображаем дефолтное состояние
  }
}

async function startSetup(): Promise<void> {
  isLoading.value = true
  try {
    setupData.value = await twoFactorApi.getSetupData()
    step.value = 'setup'
    // Рендерим QR-код после перехода в режим setup
    await nextTick()
    if (qrCanvas.value && setupData.value) {
      await QRCode.toCanvas(qrCanvas.value, setupData.value.qr_uri, { width: 200, margin: 2 })
    }
  } catch {
    uiStore.showToast('Failed to initialize 2FA setup', 'error')
  } finally {
    isLoading.value = false
  }
}

async function confirmEnable(): Promise<void> {
  if (!confirmCode.value.trim()) return

  isLoading.value = true
  try {
    const result = await twoFactorApi.enable(confirmCode.value.trim())
    backupCodes.value = result.backup_codes
    if (authStore.user) {
      authStore.user.is2faEnabled = true
    }
    await loadStatus()
    step.value = 'backup_codes'
  } catch (error: any) {
    const msg = error?.response?.data?.error ?? 'Invalid code. Please try again.'
    uiStore.showToast(msg, 'error')
    confirmCode.value = ''
  } finally {
    isLoading.value = false
  }
}

async function disable(): Promise<void> {
  if (!masterPasswordForAction.value) return

  isLoading.value = true
  try {
    const { deriveVerifyHash } = await import('../../crypto')
    const hash = await deriveVerifyHash(masterPasswordForAction.value, authStore.user!.salt)
    await twoFactorApi.disable(hash)
    if (authStore.user) {
      authStore.user.is2faEnabled = false
    }
    await loadStatus()
    showDisableForm.value = false
    masterPasswordForAction.value = ''
    uiStore.showToast('Two-factor authentication disabled', 'success')
  } catch (error: any) {
    const msg = error?.response?.data?.error ?? 'Invalid password'
    uiStore.showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

async function regenerateCodes(): Promise<void> {
  if (!masterPasswordForAction.value) return

  isLoading.value = true
  try {
    const { deriveVerifyHash } = await import('../../crypto')
    const hash = await deriveVerifyHash(masterPasswordForAction.value, authStore.user!.salt)
    const result = await twoFactorApi.regenerateBackupCodes(hash)
    backupCodes.value = result.backup_codes
    await loadStatus()
    showRegenerateForm.value = false
    masterPasswordForAction.value = ''
    step.value = 'backup_codes'
  } catch (error: any) {
    const msg = error?.response?.data?.error ?? 'Invalid password'
    uiStore.showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

async function copyBackupCodes(): Promise<void> {
  try {
    await navigator.clipboard.writeText(backupCodes.value.join('\n'))
    copiedCodes.value = true
    setTimeout(() => { copiedCodes.value = false }, 2000)
  } catch {
    uiStore.showToast('Failed to copy to clipboard', 'error')
  }
}

function cancelSetup(): void {
  step.value = 'status'
  setupData.value = null
  confirmCode.value = ''
}

function doneWithBackupCodes(): void {
  step.value = 'status'
  backupCodes.value = []
  copiedCodes.value = false
}
</script>

<template>
  <!-- Статус 2FA -->
  <div v-if="step === 'status'">
    <div v-if="status" class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-sm font-medium text-slate-700">Two-Factor Authentication</span>
          <span
            class="ml-2 px-2 py-0.5 text-xs rounded-full font-medium"
            :class="status.is_enabled
              ? 'bg-green-100 text-green-700'
              : 'bg-slate-100 text-slate-600'"
          >
            {{ status.is_enabled ? 'Enabled' : 'Disabled' }}
          </span>
        </div>
        <button
          v-if="!status.is_enabled"
          @click="startSetup"
          :disabled="isLoading"
          class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-colors disabled:opacity-50"
        >
          Enable
        </button>
      </div>

      <!-- Управление при включённом 2FA -->
      <template v-if="status.is_enabled">
        <div class="text-sm text-slate-500">
          Backup codes: {{ status.backup_codes_count }} remaining
        </div>

        <!-- Форма отключения -->
        <div v-if="showDisableForm" class="mt-3 p-3 bg-red-50 rounded-lg border border-red-100 space-y-2">
          <p class="text-sm text-red-700">Enter your master password to disable 2FA:</p>
          <input
            v-model="masterPasswordForAction"
            type="password"
            placeholder="Master password"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400"
          />
          <div class="flex gap-2">
            <button
              @click="disable"
              :disabled="isLoading || !masterPasswordForAction"
              class="px-3 py-1.5 text-sm bg-red-600 hover:bg-red-500 text-white rounded-lg transition-colors disabled:opacity-50"
            >
              Confirm Disable
            </button>
            <button
              @click="showDisableForm = false; masterPasswordForAction = ''"
              class="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-800"
            >
              Cancel
            </button>
          </div>
        </div>

        <!-- Форма перегенерации backup-кодов -->
        <div v-if="showRegenerateForm" class="mt-3 p-3 bg-amber-50 rounded-lg border border-amber-100 space-y-2">
          <p class="text-sm text-amber-700">Enter your master password to regenerate backup codes:</p>
          <input
            v-model="masterPasswordForAction"
            type="password"
            placeholder="Master password"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400"
          />
          <div class="flex gap-2">
            <button
              @click="regenerateCodes"
              :disabled="isLoading || !masterPasswordForAction"
              class="px-3 py-1.5 text-sm bg-amber-600 hover:bg-amber-500 text-white rounded-lg transition-colors disabled:opacity-50"
            >
              Regenerate Codes
            </button>
            <button
              @click="showRegenerateForm = false; masterPasswordForAction = ''"
              class="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-800"
            >
              Cancel
            </button>
          </div>
        </div>

        <div v-if="!showDisableForm && !showRegenerateForm" class="flex gap-2">
          <button
            @click="showRegenerateForm = true"
            class="px-3 py-1.5 text-sm border border-slate-300 text-slate-600 hover:text-slate-800 rounded-lg transition-colors"
          >
            Regenerate Backup Codes
          </button>
          <button
            @click="showDisableForm = true"
            class="px-3 py-1.5 text-sm border border-red-200 text-red-600 hover:text-red-700 rounded-lg transition-colors"
          >
            Disable 2FA
          </button>
        </div>
      </template>
    </div>

    <div v-else class="text-sm text-slate-500">Loading...</div>
  </div>

  <!-- Настройка 2FA -->
  <div v-else-if="step === 'setup'" class="space-y-4">
    <h4 class="text-sm font-medium text-slate-700">Set up Authenticator App</h4>

    <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
      <p class="text-sm text-slate-600">
        1. Open your authenticator app (Google Authenticator, Aegis, etc.)
      </p>
      <p class="text-sm text-slate-600">
        2. Scan the QR code:
      </p>
      <div class="flex justify-center bg-white p-3 rounded border border-slate-200">
        <canvas ref="qrCanvas"></canvas>
      </div>
      <p class="text-sm text-slate-600">
        Or enter the key manually:
      </p>
      <div class="bg-white p-3 rounded border border-slate-300 font-mono text-sm text-slate-800 break-all select-all">
        {{ setupData?.secret }}
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-sm text-slate-600">
        3. Enter the 6-digit code from your authenticator to confirm:
      </p>
      <input
        v-model="confirmCode"
        type="text"
        inputmode="numeric"
        maxlength="6"
        placeholder="000000"
        class="w-full px-4 py-2 border border-slate-300 rounded-lg text-center text-xl tracking-widest font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    </div>

    <div class="flex gap-2">
      <button
        @click="confirmEnable"
        :disabled="isLoading || confirmCode.length !== 6"
        class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-colors disabled:opacity-50"
      >
        {{ isLoading ? 'Verifying...' : 'Enable 2FA' }}
      </button>
      <button
        @click="cancelSetup"
        class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800"
      >
        Cancel
      </button>
    </div>
  </div>

  <!-- Backup-коды (показываются один раз) -->
  <div v-else-if="step === 'backup_codes'" class="space-y-4">
    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
      <p class="text-sm font-medium text-amber-800">Save your backup codes now!</p>
      <p class="text-xs text-amber-700 mt-1">These codes won't be shown again. Each can only be used once.</p>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div
        v-for="code in backupCodes"
        :key="code"
        class="bg-slate-100 rounded px-3 py-2 text-center font-mono text-sm text-slate-800 border border-slate-200"
      >
        {{ code }}
      </div>
    </div>

    <div class="flex gap-2">
      <button
        @click="copyBackupCodes"
        class="px-4 py-2 text-sm border border-slate-300 text-slate-700 hover:text-slate-900 rounded-lg transition-colors"
      >
        {{ copiedCodes ? 'Copied!' : 'Copy All' }}
      </button>
      <button
        @click="doneWithBackupCodes"
        class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-colors"
      >
        Done
      </button>
    </div>
  </div>
</template>
