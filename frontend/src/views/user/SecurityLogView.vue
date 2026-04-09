<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { userApi } from '../../api/user'
import { useUiStore } from '../../stores/ui'
import type { SecurityLogEntry, PaginationMeta } from '../../types/auth'

const router = useRouter()
const uiStore = useUiStore()

const entries = ref<SecurityLogEntry[]>([])
const pagination = ref<PaginationMeta>({ page: 1, limit: 20, total: 0, pages: 0 })
const isLoading = ref(false)

onMounted(async () => {
  await loadPage(1)
})

async function loadPage(page: number): Promise<void> {
  isLoading.value = true
  try {
    const response = await userApi.getSecurityLog(page, 20)
    entries.value = response.data
    pagination.value = response.meta.pagination
  } catch {
    uiStore.showToast('Не удалось загрузить историю событий', 'error')
  } finally {
    isLoading.value = false
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('ru-RU')
}

function actionLabel(action: string): string {
  const labels: Record<string, string> = {
    'user.login': 'Вход в аккаунт',
    'user.logout': 'Выход из аккаунта',
    'user.register': 'Регистрация',
    'user.email_verified': 'Email подтверждён',
    'user.email_change_requested': 'Запрос смены email',
    'user.email_changed': 'Email изменён',
    'user.master_password_changed': 'Мастер-пароль изменён',
    'session.revoked': 'Сессия отозвана',
  }
  return labels[action] ?? action
}

function actionColor(action: string): string {
  if (action.includes('failed') || action.includes('invalid')) return 'text-red-600 bg-red-50 border-red-200'
  if (action.includes('login') || action.includes('register') || action.includes('verified')) return 'text-green-700 bg-green-50 border-green-200'
  if (action.includes('password') || action.includes('email_change')) return 'text-amber-700 bg-amber-50 border-amber-200'
  return 'text-slate-600 bg-slate-50 border-slate-200'
}

function actionIcon(action: string): string {
  if (action.includes('failed') || action.includes('invalid')) return '✕'
  if (action === 'user.login') return '→'
  if (action === 'user.logout') return '←'
  if (action === 'user.register') return '+'
  if (action.includes('password')) return '🔑'
  if (action.includes('email')) return '✉'
  if (action === 'session.revoked') return '✕'
  return '·'
}
</script>

<template>
  <div class="min-h-screen bg-[#eef2f8]">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-2xl mx-auto px-4 h-14 flex items-center gap-3">
        <button
          @click="router.back()"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition-colors"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          <span>Назад</span>
        </button>
        <div class="w-px h-5 bg-slate-200" />
        <h2 class="text-base font-semibold text-slate-800">История событий безопасности</h2>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">

        <div v-if="isLoading && entries.length === 0" class="text-sm text-slate-500 py-4">
          Загрузка...
        </div>

        <div v-else-if="entries.length === 0" class="text-sm text-slate-500 py-4">
          События не найдены.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="entry in entries"
            :key="entry.id"
            class="flex items-start gap-3 p-3 rounded-lg border"
            :class="actionColor(entry.action)"
          >
            <span class="shrink-0 w-6 h-6 flex items-center justify-center text-sm font-bold">
              {{ actionIcon(entry.action) }}
            </span>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium">{{ actionLabel(entry.action) }}</div>
              <div class="text-xs opacity-70 mt-0.5 space-x-2">
                <span>{{ formatDate(entry.createdAt) }}</span>
                <span v-if="entry.ipAddress">· {{ entry.ipAddress }}</span>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="pagination.pages > 1"
          class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100"
        >
          <button
            @click="loadPage(pagination.page - 1)"
            :disabled="pagination.page <= 1 || isLoading"
            class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            ← Назад
          </button>

          <span class="text-sm text-slate-500">
            {{ pagination.page }} / {{ pagination.pages }}
          </span>

          <button
            @click="loadPage(pagination.page + 1)"
            :disabled="pagination.page >= pagination.pages || isLoading"
            class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            Вперёд →
          </button>
        </div>

      </div>
    </main>
  </div>
</template>
