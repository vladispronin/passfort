<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'
import { useVaultItemsStore } from '../../stores/vaultItems'
import { useCategoriesStore } from '../../stores/categories'
import { useUiStore } from '../../stores/ui'
import { useSettingsStore } from '../../stores/settings'
import { useAuthStore } from '../../stores/auth'
import { useVaultExport } from '../../composables/useVaultExport'

const router = useRouter()
const vaultStore = useVaultStore()
const itemsStore = useVaultItemsStore()
const categoriesStore = useCategoriesStore()
const uiStore = useUiStore()
const settingsStore = useSettingsStore()
const authStore = useAuthStore()
const { isExporting, isImporting, exportVault, importVault } = useVaultExport()

async function toggleSessionUnlock() {
  const newValue = !settingsStore.sessionUnlock
  settingsStore.setSessionUnlock(newValue)
  if (newValue) {
    await authStore.saveKeyToSession()
  }
}

const importFileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const showExport = ref(false)
const showImport = ref(false)
const showConfirm = ref(false)

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
}

async function handleExport() {
  const vault = vaultStore.currentVault
  if (!vault) return

  try {
    await exportVault(vault.id, vault.name)
    uiStore.showToast('Резервная копия скачана', 'success')
  } catch {
    uiStore.showToast('Не удалось экспортировать хранилище', 'error')
  }
}

function requestImport() {
  if (!selectedFile.value) return
  showConfirm.value = true
}

async function confirmImport() {
  showConfirm.value = false
  if (!selectedFile.value || !vaultStore.currentVaultId) return

  try {
    const result = await importVault(vaultStore.currentVaultId, selectedFile.value)
    uiStore.showToast(
      `Импортировано: ${result.items} записей, ${result.categories} категорий`,
      'success',
    )
    selectedFile.value = null
    if (importFileInput.value) importFileInput.value.value = ''
    await Promise.all([
      itemsStore.loadItems(vaultStore.currentVaultId),
      categoriesStore.loadCategories(vaultStore.currentVaultId),
    ])
  } catch (err) {
    const message = err instanceof Error ? err.message : 'Не удалось импортировать данные'
    uiStore.showToast(message, 'error')
  }
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
        <h2 class="text-base font-semibold text-slate-800">Настройки</h2>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 space-y-4">

      <!-- Секция: Хранилище -->
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-1 mb-2">Хранилище</p>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden divide-y divide-slate-100">

          <!-- Строка: Экспорт -->
          <div>
            <button
              @click="showExport = !showExport"
              class="w-full flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors text-left"
            >
              <span class="text-sm font-medium text-slate-800">Резервная копия</span>
              <svg
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                :class="{ 'rotate-180': showExport }"
              >
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <div v-show="showExport" class="px-6 pb-6">
              <p class="text-sm text-slate-500 mb-4">
                Скачайте зашифрованную резервную копию всех записей и категорий в формате JSON.
              </p>
              <button
                :disabled="isExporting"
                @click="handleExport"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {{ isExporting ? 'Экспортируется...' : 'Скачать резервную копию' }}
              </button>
            </div>
          </div>

          <!-- Строка: Импорт -->
          <div>
            <button
              @click="showImport = !showImport"
              class="w-full flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors text-left"
            >
              <span class="text-sm font-medium text-slate-800">Восстановление из копии</span>
              <svg
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                :class="{ 'rotate-180': showImport }"
              >
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <div v-show="showImport" class="px-6 pb-6">
              <p class="text-sm text-slate-500 mb-4">
                Восстановите записи из файла резервной копии PassFort. Существующие данные не удаляются.
              </p>
              <div class="space-y-3">
                <input
                  ref="importFileInput"
                  type="file"
                  accept=".json"
                  @change="onFileSelected"
                  class="block w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                />
                <button
                  :disabled="!selectedFile || isImporting"
                  @click="requestImport"
                  class="px-4 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {{ isImporting ? 'Импортируется...' : 'Импортировать' }}
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Секция: Безопасность -->
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-1 mb-2">Безопасность</p>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 flex items-start justify-between gap-4">
            <div class="flex-1">
              <p class="text-sm font-medium text-slate-800">Оставаться разблокированным при обновлении страницы</p>
              <p class="text-xs text-slate-500 mt-1">
                Хранилище не будет запрашивать мастер-пароль при обновлении страницы. Закрытие вкладки или браузера всё равно заблокирует хранилище.
              </p>
              <p class="text-xs text-amber-600 mt-1.5 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                Снижает защиту: ключ шифрования сохраняется в памяти сессии браузера.
              </p>
            </div>
            <button
              role="switch"
              :aria-checked="settingsStore.sessionUnlock"
              @click="toggleSessionUnlock"
              class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
              :class="settingsStore.sessionUnlock ? 'bg-brand-500' : 'bg-slate-200'"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition duration-200"
                :class="settingsStore.sessionUnlock ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </div>
        </div>
      </div>

    </main>

    <!-- Диалог подтверждения импорта -->
    <Teleport to="body">
      <div
        v-if="showConfirm"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="showConfirm = false"
      >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6">
          <h3 class="text-base font-semibold text-slate-900 mb-2">Подтвердите импорт</h3>
          <p class="text-sm text-slate-500 mb-6">
            Записи из файла будут добавлены в хранилище. Существующие данные не удалятся, но могут появиться дубли.
          </p>
          <div class="flex gap-3 justify-end">
            <button
              @click="showConfirm = false"
              class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
            >
              Отмена
            </button>
            <button
              @click="confirmImport"
              class="px-4 py-2 text-sm bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition-colors"
            >
              Импортировать
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
