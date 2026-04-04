<script setup lang="ts">
import { ref } from 'vue'
import { userApi } from '../../api/user'

const newEmail = ref('')
const isLoading = ref(false)
const error = ref<string | null>(null)
const successEmail = ref<string | null>(null)

async function handleSubmit() {
  error.value = null
  const target = newEmail.value.trim()

  if (!target) return

  isLoading.value = true
  try {
    await userApi.requestEmailChange(target)
    successEmail.value = target
    newEmail.value = ''
  } catch (e: any) {
    const status = e?.response?.status
    const message = e?.response?.data?.error

    if (status === 409) {
      error.value = 'Этот email уже занят другим аккаунтом'
    } else if (status === 400) {
      error.value = message ?? 'Некорректный email'
    } else if (status === 422) {
      error.value = 'Введите корректный email-адрес'
    } else {
      error.value = 'Не удалось отправить письмо. Попробуйте позже.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div v-if="successEmail" class="text-sm text-green-600 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
    Письмо с подтверждением отправлено на <span class="font-medium">{{ successEmail }}</span>.
    Перейдите по ссылке в письме, чтобы завершить смену email.
  </div>

  <form v-else @submit.prevent="handleSubmit" class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Новый email</label>
      <input
        v-model="newEmail"
        type="email"
        required
        :disabled="isLoading"
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400"
        placeholder="new@example.com"
      />
    </div>

    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

    <button
      type="submit"
      :disabled="isLoading"
      class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 text-white text-sm font-medium rounded-lg transition-colors"
    >
      {{ isLoading ? 'Отправка...' : 'Отправить письмо с подтверждением' }}
    </button>

    <p class="text-xs text-slate-400">
      На новый адрес будет отправлено письмо со ссылкой. Email изменится только после перехода по ссылке.
    </p>
  </form>
</template>
