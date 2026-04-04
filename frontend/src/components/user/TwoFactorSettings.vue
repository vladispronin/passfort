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
    uiStore.showToast('Не удалось инициализировать настройку 2FA', 'error')
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
    const msg = error?.response?.data?.error ?? 'Неверный код. Попробуйте ещё раз.'
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
    uiStore.showToast('Двухфакторная аутентификация отключена', 'success')
  } catch (error: any) {
    const msg = error?.response?.data?.error ?? 'Неверный пароль'
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
    const msg = error?.response?.data?.error ?? 'Неверный пароль'
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
    uiStore.showToast('Не удалось скопировать в буфер обмена', 'error')
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

      <!-- 2FA отключена -->
      <template v-if="!status.is_enabled">
        <p class="text-sm text-slate-500">
          Добавьте второй фактор защиты входа через приложение-аутентификатор.
        </p>
        <button
          @click="startSetup"
          :disabled="isLoading"
          class="px-4 py-2 text-sm bg-brand-500 hover:bg-brand-600 text-white rounded-lg transition-colors disabled:opacity-50"
        >
          Настроить 2FA
        </button>
      </template>

      <!-- 2FA включена -->
      <template v-else>
        <p class="text-sm text-slate-500">
          Резервные коды: {{ status.backup_codes_count }} осталось
        </p>

        <!-- Форма отключения -->
        <div v-if="showDisableForm" class="p-4 bg-red-50 rounded-lg border border-red-100 space-y-3">
          <p class="text-sm text-red-700">Введите мастер-пароль для отключения 2FA:</p>
          <input
            v-model="masterPasswordForAction"
            type="password"
            placeholder="Мастер-пароль"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400"
          />
          <div class="flex gap-2">
            <button
              @click="disable"
              :disabled="isLoading || !masterPasswordForAction"
              class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors disabled:opacity-50"
            >
              Подтвердить отключение
            </button>
            <button
              @click="showDisableForm = false; masterPasswordForAction = ''"
              class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700 transition-colors"
            >
              Отмена
            </button>
          </div>
        </div>

        <!-- Форма перегенерации backup-кодов -->
        <div v-if="showRegenerateForm" class="p-4 bg-amber-50 rounded-lg border border-amber-100 space-y-3">
          <p class="text-sm text-amber-700">Введите мастер-пароль для обновления резервных кодов:</p>
          <input
            v-model="masterPasswordForAction"
            type="password"
            placeholder="Мастер-пароль"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400"
          />
          <div class="flex gap-2">
            <button
              @click="regenerateCodes"
              :disabled="isLoading || !masterPasswordForAction"
              class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors disabled:opacity-50"
            >
              Обновить коды
            </button>
            <button
              @click="showRegenerateForm = false; masterPasswordForAction = ''"
              class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700 transition-colors"
            >
              Отмена
            </button>
          </div>
        </div>

        <div v-if="!showDisableForm && !showRegenerateForm" class="flex gap-2">
          <button
            @click="showRegenerateForm = true"
            class="px-4 py-2 text-sm border border-slate-300 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors"
          >
            Обновить резервные коды
          </button>
          <button
            @click="showDisableForm = true"
            class="px-4 py-2 text-sm border border-red-200 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
          >
            Отключить 2FA
          </button>
        </div>
      </template>
    </div>

    <div v-else class="text-sm text-slate-500">Загрузка...</div>
  </div>

  <!-- Настройка 2FA -->
  <div v-else-if="step === 'setup'" class="space-y-4">
    <p class="text-sm font-medium text-slate-700">Настройка приложения-аутентификатора</p>

    <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
      <p class="text-sm text-slate-600">
        1. Откройте приложение-аутентификатор (Google Authenticator, Aegis и др.)
      </p>
      <p class="text-sm text-slate-600">
        2. Отсканируйте QR-код:
      </p>
      <div class="flex justify-center bg-white p-3 rounded border border-slate-200">
        <canvas ref="qrCanvas"></canvas>
      </div>
      <p class="text-sm text-slate-600">
        Или введите ключ вручную:
      </p>
      <div class="bg-white p-3 rounded border border-slate-300 font-mono text-sm text-slate-800 break-all select-all">
        {{ setupData?.secret }}
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-sm text-slate-600">
        3. Введите 6-значный код из приложения для подтверждения:
      </p>
      <input
        v-model="confirmCode"
        type="text"
        inputmode="numeric"
        maxlength="6"
        placeholder="000000"
        class="w-full px-4 py-2 border border-slate-300 rounded-lg text-center text-xl tracking-widest font-mono focus:outline-none focus:ring-2 focus:ring-brand-500"
      />
    </div>

    <div class="flex gap-2">
      <button
        @click="confirmEnable"
        :disabled="isLoading || confirmCode.length !== 6"
        class="px-4 py-2 text-sm bg-brand-500 hover:bg-brand-600 text-white rounded-lg transition-colors disabled:opacity-50"
      >
        {{ isLoading ? 'Проверка...' : 'Включить 2FA' }}
      </button>
      <button
        @click="cancelSetup"
        class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700 transition-colors"
      >
        Отмена
      </button>
    </div>
  </div>

  <!-- Backup-коды (показываются один раз) -->
  <div v-else-if="step === 'backup_codes'" class="space-y-4">
    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
      <p class="text-sm font-medium text-amber-800">Сохраните резервные коды прямо сейчас!</p>
      <p class="text-xs text-amber-700 mt-1">Коды больше не будут показаны. Каждый код можно использовать только один раз.</p>
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
        class="px-4 py-2 text-sm border border-slate-300 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors"
      >
        {{ copiedCodes ? 'Скопировано!' : 'Скопировать все' }}
      </button>
      <button
        @click="doneWithBackupCodes"
        class="px-4 py-2 text-sm bg-brand-500 hover:bg-brand-600 text-white rounded-lg transition-colors"
      >
        Готово
      </button>
    </div>
  </div>
</template>
