<script setup lang="ts">
import { ref } from 'vue'
import { useMasterPasswordChange } from '../../composables/useMasterPasswordChange'
import { useUiStore } from '../../stores/ui'

const uiStore = useUiStore()
const { isLoading, error, progress, changeMasterPassword } = useMasterPasswordChange()

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const localError = ref<string | null>(null)

async function handleSubmit() {
  localError.value = null

  if (newPassword.value.length < 12) {
    localError.value = 'Новый мастер-пароль должен содержать минимум 12 символов'
    return
  }

  if (newPassword.value !== confirmPassword.value) {
    localError.value = 'Пароли не совпадают'
    return
  }

  try {
    await changeMasterPassword(currentPassword.value, newPassword.value)
    uiStore.showToast('Мастер-пароль успешно изменён', 'success')
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  } catch {
    // error.value уже установлен в composable
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Текущий мастер-пароль</label>
      <input
        v-model="currentPassword"
        type="password"
        required
        :disabled="isLoading"
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400"
        placeholder="Введите текущий мастер-пароль"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Новый мастер-пароль</label>
      <input
        v-model="newPassword"
        type="password"
        required
        :disabled="isLoading"
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400"
        placeholder="Минимум 12 символов"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Подтверждение нового пароля</label>
      <input
        v-model="confirmPassword"
        type="password"
        required
        :disabled="isLoading"
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400"
        placeholder="Повторите новый мастер-пароль"
      />
    </div>

    <!-- Прогресс перешифровывания -->
    <div v-if="isLoading && progress.total > 0" class="space-y-1">
      <p class="text-xs text-slate-500">
        Перешифровывание записей: {{ progress.current }} / {{ progress.total }}
      </p>
      <div class="w-full bg-slate-200 rounded-full h-1.5">
        <div
          class="bg-blue-500 h-1.5 rounded-full transition-all"
          :style="{ width: `${progress.total > 0 ? (progress.current / progress.total) * 100 : 0}%` }"
        />
      </div>
    </div>

    <!-- Сообщение об ошибке -->
    <p v-if="localError || error" class="text-sm text-red-500">
      {{ localError || error }}
    </p>

    <button
      type="submit"
      :disabled="isLoading"
      class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 text-white text-sm font-medium rounded-lg transition-colors"
    >
      {{ isLoading ? 'Обработка...' : 'Сменить мастер-пароль' }}
    </button>

    <p class="text-xs text-slate-400">
      После смены пароля все активные сессии будут завершены. Вы останетесь в системе с новым токеном.
    </p>
  </form>
</template>
