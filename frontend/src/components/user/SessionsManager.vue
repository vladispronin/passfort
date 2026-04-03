<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { userApi } from '../../api/user'
import { useUiStore } from '../../stores/ui'
import type { Session } from '../../types/auth'

const uiStore = useUiStore()

const sessions = ref<Session[]>([])
const isLoading = ref(false)
const revokingId = ref<string | null>(null)

onMounted(async () => {
  await loadSessions()
})

async function loadSessions(): Promise<void> {
  isLoading.value = true
  try {
    sessions.value = await userApi.getSessions()
  } catch {
    uiStore.showToast('Failed to load sessions', 'error')
  } finally {
    isLoading.value = false
  }
}

async function revokeSession(id: string): Promise<void> {
  revokingId.value = id
  try {
    await userApi.revokeSession(id)
    uiStore.showToast('Session revoked', 'success')
    await loadSessions()
  } catch {
    uiStore.showToast('Failed to revoke session', 'error')
  } finally {
    revokingId.value = null
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString()
}

function formatDevice(deviceInfo: string | null): string {
  if (!deviceInfo) return 'Unknown device'
  // Извлекаем краткое название браузера/ОС из User-Agent
  if (deviceInfo.includes('Firefox')) return 'Firefox'
  if (deviceInfo.includes('Edg')) return 'Edge'
  if (deviceInfo.includes('Chrome')) return 'Chrome'
  if (deviceInfo.includes('Safari')) return 'Safari'
  if (deviceInfo.includes('curl')) return 'curl'
  return deviceInfo.slice(0, 50)
}
</script>

<template>
  <div class="space-y-3">
    <div v-if="isLoading && sessions.length === 0" class="text-sm text-slate-500">
      Loading...
    </div>

    <div v-else-if="sessions.length === 0" class="text-sm text-slate-500">
      No active sessions found.
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="session in sessions"
        :key="session.id"
        class="flex items-start justify-between p-3 rounded-lg border"
        :class="session.isCurrent ? 'bg-blue-50 border-blue-200' : 'bg-slate-50 border-slate-200'"
      >
        <div class="space-y-0.5 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-slate-800 truncate">
              {{ formatDevice(session.deviceInfo) }}
            </span>
            <span
              v-if="session.isCurrent"
              class="shrink-0 px-1.5 py-0.5 text-xs rounded-full font-medium bg-blue-100 text-blue-700"
            >
              Current
            </span>
          </div>
          <div class="text-xs text-slate-500">
            {{ session.ipAddress ?? 'Unknown IP' }} · Started {{ formatDate(session.createdAt) }}
          </div>
          <div class="text-xs text-slate-400">
            Expires {{ formatDate(session.expiresAt) }}
          </div>
        </div>

        <button
          v-if="!session.isCurrent"
          @click="revokeSession(session.id)"
          :disabled="revokingId === session.id"
          class="shrink-0 ml-3 px-2.5 py-1 text-xs border border-red-200 text-red-600 hover:text-red-700 hover:border-red-300 rounded-lg transition-colors disabled:opacity-50"
        >
          {{ revokingId === session.id ? 'Revoking...' : 'Revoke' }}
        </button>
      </div>
    </div>

    <button
      @click="loadSessions"
      :disabled="isLoading"
      class="text-xs text-slate-500 hover:text-slate-700 transition-colors disabled:opacity-50"
    >
      {{ isLoading ? 'Refreshing...' : 'Refresh' }}
    </button>
  </div>
</template>
