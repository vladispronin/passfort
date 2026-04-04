<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { userApi } from '../../api/user'

type State = 'loading' | 'success' | 'error'

const route = useRoute()
const router = useRouter()

const state = ref<State>('loading')
const errorMessage = ref('')

onMounted(async () => {
  const token = route.query.token as string | undefined

  if (!token) {
    errorMessage.value = 'Неверная ссылка: токен отсутствует.'
    state.value = 'error'
    return
  }

  try {
    await userApi.confirmEmailChange(token)
    state.value = 'success'
  } catch (e: any) {
    const message = e?.response?.data?.error
    errorMessage.value = message ?? 'Недействительная или просроченная ссылка.'
    state.value = 'error'
  }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-950 via-blue-800 to-blue-500 flex items-center justify-center px-4 py-12 relative overflow-hidden">
    <div class="absolute -top-16 -left-16 w-72 h-72 rounded-full bg-white/5 blur-sm"></div>
    <div class="absolute top-8 right-12 w-48 h-48 rounded-full bg-white/10 blur-sm"></div>
    <div class="absolute bottom-12 left-1/4 w-56 h-56 rounded-full bg-blue-400/20 blur-lg"></div>
    <div class="absolute -bottom-12 -right-12 w-64 h-64 rounded-full bg-white/5"></div>

    <div class="relative z-10 w-full max-w-md">
      <div class="flex flex-col items-center mb-6 text-white">
        <img src="/passfort-icon.svg" alt="PassFort" class="w-14 h-14 mb-3" />
        <h1 class="text-2xl font-bold tracking-wide">PassFort</h1>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl px-8 py-8 text-center">
        <!-- Загрузка -->
        <div v-if="state === 'loading'" class="space-y-3">
          <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
          <p class="text-slate-500 text-sm">Подтверждение смены email...</p>
        </div>

        <!-- Успех -->
        <div v-else-if="state === 'success'" class="space-y-4">
          <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-slate-800">Email успешно изменён</h2>
          <p class="text-sm text-slate-500">
            Теперь используйте новый адрес для входа в аккаунт.
          </p>
          <button
            @click="router.push('/login')"
            class="w-full py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
          >
            Войти
          </button>
        </div>

        <!-- Ошибка -->
        <div v-else class="space-y-4">
          <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-slate-800">Не удалось подтвердить</h2>
          <p class="text-sm text-slate-500">{{ errorMessage }}</p>
          <button
            @click="router.push('/')"
            class="w-full py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-slate-600 hover:bg-slate-700 transition-colors"
          >
            На главную
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
