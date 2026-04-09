<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '../../composables/useAuth'
import { useUiStore } from '../../stores/ui'

const { login } = useAuth()
const uiStore = useUiStore()

const email = ref('')
const masterPassword = ref('')
const isLoading = ref(false)
const showPassword = ref(false)

async function handleSubmit() {
  if (!email.value || !masterPassword.value) return

  isLoading.value = true
  try {
    await login(email.value, masterPassword.value)
  } catch (error: any) {
    const message = error?.response?.data?.error ?? 'Не удалось войти. Попробуйте ещё раз.'
    uiStore.showToast(message, 'error')
  } finally {
    isLoading.value = false
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
        <p class="text-blue-200 text-sm mt-1">Ваша надёжная крепость</p>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl px-8 py-8">
        <h2 class="text-xl font-semibold text-slate-800 mb-6">Войдите в хранилище</h2>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input
              v-model="email"
              type="email"
              required
              autocomplete="email"
              class="mt-1 block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
              placeholder="you@example.com"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Мастер-пароль</label>
            <div class="relative mt-1">
              <input
                v-model="masterPassword"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                class="block w-full px-3 py-2 pr-16 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                placeholder="Введите мастер-пароль"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 text-xs"
              >
                {{ showPassword ? 'Скрыть' : 'Показать' }}
              </button>
            </div>
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ isLoading ? 'Вход...' : 'Войти' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
          Нет аккаунта?
          <router-link to="/register" class="text-brand-500 hover:text-brand-600 font-medium">Регистрация</router-link>
        </p>
      </div>
    </div>
  </div>
</template>
