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
</script>

<template>
  <div class="min-h-screen bg-[#eef2f8]">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
        <button @click="router.back()" class="text-slate-500 hover:text-slate-800 transition-colors">&#8592; Назад</button>
        <h2 class="text-lg font-semibold text-slate-800">Профиль</h2>
      </div>
    </header>
    <main class="max-w-2xl mx-auto px-4 py-6">
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
        <div>
          <label class="text-sm text-slate-500">Email</label>
          <div class="flex items-center justify-between">
            <p class="font-medium text-slate-900">{{ authStore.user?.email }}</p>
            <button
              @click="showEmailForm = !showEmailForm"
              class="text-xs text-blue-600 hover:text-blue-700 font-medium transition-colors"
            >
              {{ showEmailForm ? 'Отмена' : 'Сменить email' }}
            </button>
          </div>
          <div v-if="showEmailForm" class="mt-3">
            <ChangeEmailForm />
          </div>
        </div>
        <div>
          <label class="text-sm text-slate-500">Account Created</label>
          <p class="font-medium text-slate-900">{{ authStore.user?.createdAt ? new Date(authStore.user.createdAt).toLocaleDateString() : '—' }}</p>
        </div>
        <div class="pt-4 border-t border-slate-100">
          <p class="text-xs text-slate-400">
            Zero-knowledge architecture: your data is encrypted with your master password.
            PassFort servers never see your unencrypted data.
          </p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 mt-4">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Безопасность</h3>

        <div class="mb-6">
          <h4 class="text-sm font-medium text-slate-700 mb-3">Двухфакторная аутентификация</h4>
          <TwoFactorSettings />
        </div>

        <div class="border-t border-slate-100 pt-6">
          <h4 class="text-sm font-medium text-slate-700 mb-3">Смена мастер-пароля</h4>
          <ChangeMasterPasswordForm />
        </div>

        <div class="border-t border-slate-100 pt-6">
          <h4 class="text-sm font-medium text-slate-700 mb-3">Активные сессии</h4>
          <SessionsManager />
        </div>

        <div class="border-t border-slate-100 pt-6">
          <h4 class="text-sm font-medium text-slate-700 mb-3">История событий</h4>
          <RouterLink
            to="/security-log"
            class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium transition-colors"
          >
            Просмотр истории безопасности →
          </RouterLink>
        </div>
      </div>
    </main>
  </div>
</template>
