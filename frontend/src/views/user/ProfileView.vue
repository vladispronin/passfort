<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { RouterLink } from 'vue-router'
import ChangeMasterPasswordForm from '../../components/user/ChangeMasterPasswordForm.vue'
import ChangeEmailForm from '../../components/user/ChangeEmailForm.vue'
import TwoFactorSettings from '../../components/user/TwoFactorSettings.vue'
import SessionsManager from '../../components/user/SessionsManager.vue'

const router = useRouter()
const authStore = useAuthStore()

const showEmailForm = ref(false)
const show2FA = ref(false)
const showPassword = ref(false)
const showSessions = ref(false)
</script>

<template>
  <div class="min-h-screen bg-[#eef2f8]">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-2xl mx-auto px-4 h-14 flex items-center gap-3">
        <button @click="router.back()" class="text-slate-500 hover:text-slate-800 transition-colors text-sm">
          ← Назад
        </button>
        <h2 class="text-lg font-semibold text-slate-800">Профиль</h2>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 space-y-4">

      <!-- Карточка: Профиль -->
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-1 mb-2">Профиль</p>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">

          <!-- Строка: Email -->
          <div class="px-6 py-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs text-slate-400 mb-0.5">Email</p>
                <p class="text-sm font-medium text-slate-900">{{ authStore.user?.email }}</p>
              </div>
              <button
                @click="showEmailForm = !showEmailForm"
                class="text-sm text-brand-500 hover:text-brand-600 font-medium transition-colors"
              >
                {{ showEmailForm ? 'Отмена' : 'Изменить' }}
              </button>
            </div>
            <div v-show="showEmailForm" class="mt-4">
              <ChangeEmailForm />
            </div>
          </div>

          <!-- Строка: Дата регистрации -->
          <div class="px-6 py-4 border-t border-slate-100">
            <p class="text-xs text-slate-400 mb-0.5">Дата регистрации</p>
            <p class="text-sm font-medium text-slate-900">
              {{ authStore.user?.createdAt ? new Date(authStore.user.createdAt).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) : '—' }}
            </p>
          </div>

          <!-- Футер: Zero-knowledge note -->
          <div class="px-6 py-3 border-t border-slate-100 bg-slate-50">
            <p class="text-xs text-slate-400">
              Архитектура zero-knowledge: ваши данные зашифрованы мастер-паролем.
              Серверы PassFort никогда не видят незашифрованные данные.
            </p>
          </div>

        </div>
      </div>

      <!-- Карточка: Безопасность -->
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-1 mb-2">Безопасность</p>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden divide-y divide-slate-100">

          <!-- Строка: Двухфакторная аутентификация -->
          <div>
            <button
              @click="show2FA = !show2FA"
              class="w-full flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors text-left"
            >
              <div class="flex items-center gap-2.5">
                <span class="text-sm font-medium text-slate-800">Двухфакторная аутентификация</span>
                <span
                  class="px-2 py-0.5 text-xs rounded-full font-medium"
                  :class="authStore.user?.is2faEnabled
                    ? 'bg-green-100 text-green-700'
                    : 'bg-slate-100 text-slate-500'"
                >
                  {{ authStore.user?.is2faEnabled ? 'Включена' : 'Отключена' }}
                </span>
              </div>
              <svg
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                :class="{ 'rotate-180': show2FA }"
              >
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <div v-show="show2FA" class="px-6 pb-6">
              <TwoFactorSettings />
            </div>
          </div>

          <!-- Строка: Мастер-пароль -->
          <div>
            <button
              @click="showPassword = !showPassword"
              class="w-full flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors text-left"
            >
              <span class="text-sm font-medium text-slate-800">Мастер-пароль</span>
              <svg
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                :class="{ 'rotate-180': showPassword }"
              >
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <div v-show="showPassword" class="px-6 pb-6">
              <ChangeMasterPasswordForm />
            </div>
          </div>

          <!-- Строка: Активные сессии -->
          <div>
            <button
              @click="showSessions = !showSessions"
              class="w-full flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors text-left"
            >
              <span class="text-sm font-medium text-slate-800">Активные сессии</span>
              <svg
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                :class="{ 'rotate-180': showSessions }"
              >
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <div v-show="showSessions" class="px-6 pb-6">
              <SessionsManager />
            </div>
          </div>

          <!-- Строка: История событий — просто ссылка -->
          <RouterLink
            to="/security-log"
            class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors"
          >
            <span class="text-sm font-medium text-slate-800">История событий безопасности</span>
            <svg
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="w-4 h-4 text-slate-400 shrink-0"
            >
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </RouterLink>

        </div>
      </div>

    </main>
  </div>
</template>
