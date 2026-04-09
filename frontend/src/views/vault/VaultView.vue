<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'
import { useVaultItemsStore } from '../../stores/vaultItems'
import { useCategoriesStore } from '../../stores/categories'
import { useAuth } from '../../composables/useAuth'
import { useAutoLock } from '../../composables/useAutoLock'
import { useUiStore } from '../../stores/ui'
import CategoryModal from '../../components/category/CategoryModal.vue'
import type { Category } from '../../types/category'
import type { ItemType } from '../../types/vault'

useAutoLock()

const vaultStore = useVaultStore()
const itemsStore = useVaultItemsStore()
const categoriesStore = useCategoriesStore()
const { logout } = useAuth()
const uiStore = useUiStore()
const router = useRouter()

const isLoadingVault = ref(true)
const modalCategory = ref<Category | undefined>(undefined)
const showCategoryModal = ref(false)

// Режим выделения
const selectionMode = ref(false)
const selectedIds = ref<Set<string>>(new Set())
const selectAllPages = ref(false)
const showMoveDropdown = ref(false)
const isBulkLoading = ref(false)

const selectedCount = computed(() =>
  selectAllPages.value ? itemsStore.pagination.total : selectedIds.value.size,
)
const allSelected = computed(
  () => itemsStore.items.length > 0 && itemsStore.items.every((i) => selectedIds.value.has(i.id)),
)
// Баннер: вся страница выделена, но есть ещё страницы и режим "все" не активен
const showSelectAllBanner = computed(
  () =>
    allSelected.value &&
    !selectAllPages.value &&
    itemsStore.pagination.total > itemsStore.items.length,
)

function toggleSelectionMode() {
  selectionMode.value = !selectionMode.value
  if (!selectionMode.value) {
    selectedIds.value = new Set()
    selectAllPages.value = false
    showMoveDropdown.value = false
  }
}

function toggleItemSelection(id: string) {
  selectAllPages.value = false
  const updated = new Set(selectedIds.value)
  if (updated.has(id)) {
    updated.delete(id)
  } else {
    updated.add(id)
  }
  selectedIds.value = updated
}

function toggleSelectAll() {
  if (selectAllPages.value || allSelected.value) {
    selectedIds.value = new Set()
    selectAllPages.value = false
  } else {
    selectedIds.value = new Set(itemsStore.items.map((i) => i.id))
  }
}

function activateSelectAllPages() {
  selectAllPages.value = true
}

async function resolveIds(): Promise<string[]> {
  if (selectAllPages.value && vaultStore.currentVaultId) {
    return await itemsStore.fetchAllIds(vaultStore.currentVaultId)
  }
  return Array.from(selectedIds.value)
}

async function handleBulkDelete() {
  if (selectedCount.value === 0) return
  if (!vaultStore.currentVaultId) return
  isBulkLoading.value = true
  try {
    const ids = await resolveIds()
    const deleted = await itemsStore.bulkDeleteItems(vaultStore.currentVaultId, ids)
    selectedIds.value = new Set()
    selectAllPages.value = false
    uiStore.showToast(`Удалено: ${deleted}`, 'success')
  } catch {
    uiStore.showToast('Ошибка при удалении', 'error')
  } finally {
    isBulkLoading.value = false
  }
}

async function handleBulkMove(categoryId: string | null) {
  if (selectedCount.value === 0) return
  if (!vaultStore.currentVaultId) return
  isBulkLoading.value = true
  showMoveDropdown.value = false
  try {
    const ids = await resolveIds()
    const moved = await itemsStore.bulkMoveItems(vaultStore.currentVaultId, ids, categoryId)
    selectedIds.value = new Set()
    selectAllPages.value = false
    const label = categoryId
      ? categoriesStore.categories.find((c) => c.id === categoryId)?.name ?? 'категорию'
      : 'без категории'
    uiStore.showToast(`Перемещено в: ${label} (${moved})`, 'success')
  } catch {
    uiStore.showToast('Ошибка при перемещении', 'error')
  } finally {
    isBulkLoading.value = false
  }
}

const TYPE_LABELS: Record<ItemType | 'all', string> = {
  all: 'Все',
  login: 'Логин',
  note: 'Заметка',
  card: 'Карта',
  identity: 'Личные данные',
}

const ITEM_TYPE_LABEL: Record<string, string> = {
  login: 'Логин',
  note: 'Заметка',
  card: 'Карта',
  identity: 'Личные данные',
}
const TYPE_OPTIONS = Object.entries(TYPE_LABELS) as [ItemType | 'all', string][]

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

onMounted(async () => {
  try {
    await vaultStore.loadVaults()
    if (vaultStore.currentVaultId) {
      await Promise.all([
        itemsStore.loadItems(vaultStore.currentVaultId),
        categoriesStore.loadCategories(vaultStore.currentVaultId),
      ])
    }
  } catch {
    uiStore.showToast('Не удалось загрузить хранилище', 'error')
  } finally {
    isLoadingVault.value = false
  }
})

watch(() => itemsStore.searchQuery, () => {
  if (!vaultStore.currentVaultId) return
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    itemsStore.applyFilters(vaultStore.currentVaultId!)
  }, 300)
})

function openItem(id: string) {
  router.push(`/vault/item/${id}`)
}

function selectCategory(categoryId: string | null) {
  itemsStore.setFilter(categoryId)
  if (vaultStore.currentVaultId) {
    itemsStore.applyFilters(vaultStore.currentVaultId)
  }
}

function selectItemType(type: ItemType | 'all') {
  itemsStore.selectedItemType = type === 'all' ? null : type
  if (vaultStore.currentVaultId) {
    itemsStore.applyFilters(vaultStore.currentVaultId)
  }
}

function goToPage(page: number) {
  if (!vaultStore.currentVaultId) return
  itemsStore.setPage(vaultStore.currentVaultId, page)
}

function openCreateModal() {
  modalCategory.value = undefined
  showCategoryModal.value = true
}

function openEditModal(cat: Category, event: Event) {
  event.stopPropagation()
  modalCategory.value = cat
  showCategoryModal.value = true
}

function onModalSaved() {
  showCategoryModal.value = false
  uiStore.showToast('Категория сохранена', 'success')
}

function onModalDeleted(deletedId: string) {
  showCategoryModal.value = false
  uiStore.showToast('Категория удалена', 'success')
  if (itemsStore.selectedCategoryId === deletedId) {
    itemsStore.setFilter(null)
    if (vaultStore.currentVaultId) {
      itemsStore.applyFilters(vaultStore.currentVaultId)
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#eef2f8]">
    <!-- Шапка -->
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <img src="/passfort-icon.svg" alt="PassFort" class="w-7 h-7" />
          <h1 class="text-xl font-bold text-brand-900">PassFort</h1>
        </div>
        <div class="flex items-center gap-1">
          <input
            v-model="itemsStore.searchQuery"
            type="search"
            placeholder="Поиск..."
            class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50 mr-2"
          />
          <router-link
            to="/vault/settings"
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            <span>Настройки</span>
          </router-link>
          <router-link
            to="/profile"
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>Профиль</span>
          </router-link>
          <div class="w-px h-5 bg-slate-200 mx-1" />
          <button
            @click="logout"
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span>Выход</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Основной контент -->
    <main class="max-w-7xl mx-auto px-4 py-6">
      <div v-if="isLoadingVault" class="text-center py-12 text-slate-400">
        Загрузка хранилища...
      </div>

      <div v-else class="flex gap-6">
        <!-- Сайдбар категорий -->
        <aside class="w-52 shrink-0">
          <nav class="bg-white rounded-xl shadow-sm border border-slate-100 p-3 space-y-1">
            <!-- Все записи -->
            <button
              @click="selectCategory(null)"
              class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors"
              :class="itemsStore.selectedCategoryId === null
                ? 'bg-brand-50 text-brand-700 font-medium'
                : 'text-slate-700 hover:bg-slate-50'"
            >
              <span class="flex items-center gap-2">
                <span>&#128194;</span>
                <span>Все</span>
              </span>
            </button>

            <!-- Без категории -->
            <button
              @click="selectCategory('none')"
              class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors"
              :class="itemsStore.selectedCategoryId === 'none'
                ? 'bg-brand-50 text-brand-700 font-medium'
                : 'text-slate-700 hover:bg-slate-50'"
            >
              <span class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-300 shrink-0 ml-px" />
                <span>Без категории</span>
              </span>
            </button>

            <div v-if="categoriesStore.categories.length" class="border-t border-slate-100 my-1" />

            <!-- Список категорий -->
            <button
              v-for="cat in categoriesStore.categories"
              :key="cat.id"
              @click="selectCategory(cat.id)"
              class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors group"
              :class="itemsStore.selectedCategoryId === cat.id
                ? 'bg-brand-50 text-brand-700 font-medium'
                : 'text-slate-700 hover:bg-slate-50'"
            >
              <span class="flex items-center gap-2 min-w-0">
                <span
                  class="w-2.5 h-2.5 rounded-full shrink-0"
                  :style="{ backgroundColor: cat.color ?? '#6b7280' }"
                />
                <span class="truncate">{{ cat.icon ? cat.icon + ' ' : '' }}{{ cat.name }}</span>
              </span>
              <span
                @click="openEditModal(cat, $event)"
                class="text-slate-300 hover:text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-xs ml-1 shrink-0"
                title="Редактировать"
              >
                &#9998;
              </span>
            </button>

            <!-- Кнопка добавления категории -->
            <div class="border-t border-slate-100 mt-1 pt-1">
              <button
                @click="openCreateModal"
                class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-500 hover:bg-slate-50 hover:text-brand-600 transition-colors"
              >
                <span class="text-base leading-none">+</span>
                <span>Категория</span>
              </button>
            </div>
          </nav>
        </aside>

        <!-- Список записей -->
        <div class="flex-1 min-w-0">
          <!-- Фильтр по типу + режим выделения + счётчик -->
          <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div class="flex items-center gap-1.5 flex-wrap">
              <template v-if="selectionMode">
                <button
                  @click="toggleSelectAll"
                  class="px-3 py-1 rounded-full text-xs font-medium transition-colors border"
                  :class="allSelected
                    ? 'bg-brand-500 text-white border-brand-500'
                    : 'bg-white text-slate-600 hover:bg-slate-100 border-slate-200'"
                >
                  {{ allSelected ? 'Снять всё' : 'Выбрать всё' }}
                </button>
              </template>
              <template v-else>
                <button
                  v-for="[type, label] in TYPE_OPTIONS"
                  :key="type"
                  @click="selectItemType(type)"
                  class="px-3 py-1 rounded-full text-xs font-medium transition-colors"
                  :class="(type === 'all' && itemsStore.selectedItemType === null) || itemsStore.selectedItemType === type
                    ? 'bg-brand-500 text-white'
                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                >
                  {{ label }}
                </button>
              </template>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs" :class="selectAllPages ? 'text-brand-600 font-medium' : 'text-slate-400'">
                {{ selectionMode ? `Выбрано: ${selectedCount}` : `Найдено: ${itemsStore.pagination.total}` }}
              </span>
              <button
                @click="toggleSelectionMode"
                class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border transition-colors"
                :class="selectionMode
                  ? 'bg-brand-50 text-brand-700 border-brand-300 hover:bg-brand-100'
                  : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:text-slate-900'"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                {{ selectionMode ? 'Отменить выделение' : 'Выделить' }}
              </button>
            </div>
          </div>

          <!-- Баннер "выделить все страницы" -->
          <Transition name="fade">
            <div
              v-if="showSelectAllBanner || (selectionMode && selectAllPages)"
              class="mb-3 flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm"
              :class="selectAllPages
                ? 'bg-brand-50 border border-brand-200 text-brand-700'
                : 'bg-amber-50 border border-amber-200 text-amber-800'"
            >
              <template v-if="!selectAllPages">
                <span>Выделены {{ itemsStore.items.length }} записей на странице.</span>
                <button
                  @click="activateSelectAllPages"
                  class="font-medium underline underline-offset-2 hover:no-underline"
                >
                  Выделить все {{ itemsStore.pagination.total }}?
                </button>
              </template>
              <template v-else>
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Выделены все {{ itemsStore.pagination.total }} записей.</span>
                <button
                  @click="toggleSelectAll"
                  class="font-medium underline underline-offset-2 hover:no-underline"
                >
                  Отменить выделение всех
                </button>
              </template>
            </div>
          </Transition>

          <div v-if="itemsStore.isLoading" class="text-center py-12 text-slate-400">
            Загрузка...
          </div>

          <div v-else-if="itemsStore.items.length === 0" class="text-center py-12">
            <div class="text-4xl mb-4">&#128205;</div>
            <p class="text-slate-500">
              {{ itemsStore.selectedCategoryId || itemsStore.selectedItemType || itemsStore.searchQuery
                ? 'Записей по заданным фильтрам не найдено.'
                : 'Нет записей. Добавьте первый пароль!' }}
            </p>
            <router-link
              v-if="!itemsStore.selectedCategoryId && !itemsStore.selectedItemType && !itemsStore.searchQuery"
              to="/vault/item/new"
              class="mt-4 inline-block px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 text-sm font-medium transition-colors"
            >
              Добавить запись
            </router-link>
          </div>

          <div v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div
                v-for="item in itemsStore.items"
                :key="item.id"
                @click="selectionMode ? toggleItemSelection(item.id) : openItem(item.id)"
                class="bg-white p-4 rounded-xl shadow-sm cursor-pointer transition-all border"
                :class="selectionMode && selectedIds.has(item.id)
                  ? 'border-brand-400 ring-2 ring-brand-200 shadow-md'
                  : 'border-slate-100 hover:shadow-md'"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2 min-w-0">
                    <input
                      v-if="selectionMode"
                      type="checkbox"
                      :checked="selectedIds.has(item.id)"
                      @click.stop="toggleItemSelection(item.id)"
                      class="w-4 h-4 rounded border-slate-300 text-brand-500 focus:ring-brand-400 shrink-0"
                    />
                    <span class="font-medium text-slate-900 truncate">{{ item.titleHint }}</span>
                  </div>
                  <span v-if="item.isFavorite" class="text-yellow-400 ml-2 shrink-0">&#9733;</span>
                </div>
                <div class="mt-2 flex items-center gap-2 flex-wrap">
                  <span class="text-xs px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full">
                    {{ ITEM_TYPE_LABEL[item.itemType] ?? item.itemType }}
                  </span>
                  <span
                    v-if="item.categoryId"
                    class="text-xs px-2 py-0.5 rounded-full text-white font-medium"
                    :style="{ backgroundColor: categoriesStore.categories.find(c => c.id === item.categoryId)?.color ?? '#6b7280' }"
                  >
                    {{ categoriesStore.categories.find(c => c.id === item.categoryId)?.icon
                      ? categoriesStore.categories.find(c => c.id === item.categoryId)?.icon + ' '
                      : '' }}
                    {{ categoriesStore.categories.find(c => c.id === item.categoryId)?.name }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Пагинация -->
            <div
              v-if="itemsStore.pagination.pages > 1"
              class="flex items-center justify-center gap-2 mt-6"
            >
              <button
                @click="goToPage(itemsStore.pagination.page - 1)"
                :disabled="itemsStore.pagination.page <= 1"
                class="px-3 py-1.5 rounded-lg text-sm border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                ← Назад
              </button>
              <span class="text-sm text-slate-500 px-2">
                {{ itemsStore.pagination.page }} / {{ itemsStore.pagination.pages }}
              </span>
              <button
                @click="goToPage(itemsStore.pagination.page + 1)"
                :disabled="itemsStore.pagination.page >= itemsStore.pagination.pages"
                class="px-3 py-1.5 rounded-lg text-sm border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                Вперёд →
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- FAB: новая запись (скрыт в режиме выделения) -->
    <router-link
      v-if="!selectionMode"
      to="/vault/item/new"
      class="fixed bottom-8 right-8 w-14 h-14 bg-brand-500 text-white rounded-full shadow-lg flex items-center justify-center text-2xl hover:bg-brand-600 transition-colors"
    >
      +
    </router-link>

    <!-- Панель bulk-действий -->
    <Transition name="slide-up">
      <div
        v-if="selectionMode && selectedCount > 0"
        class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 shadow-lg px-4 py-3 flex items-center justify-between gap-3 z-50"
      >
        <span class="text-sm text-slate-600 font-medium shrink-0">
          Выбрано: {{ selectedCount }}
        </span>

        <div class="flex items-center gap-2">
          <!-- Переместить в категорию -->
          <div class="relative">
            <button
              @click="showMoveDropdown = !showMoveDropdown"
              :disabled="isBulkLoading"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-50 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
              Переместить
            </button>
            <div
              v-if="showMoveDropdown"
              class="absolute bottom-full mb-2 right-0 bg-white border border-slate-200 rounded-lg shadow-lg py-1 min-w-40 z-10"
            >
              <button
                @click="handleBulkMove(null)"
                class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
              >
                <span class="w-2.5 h-2.5 rounded-full bg-slate-300 shrink-0" />
                Без категории
              </button>
              <div v-if="categoriesStore.categories.length" class="border-t border-slate-100 my-1" />
              <button
                v-for="cat in categoriesStore.categories"
                :key="cat.id"
                @click="handleBulkMove(cat.id)"
                class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
              >
                <span
                  class="w-2.5 h-2.5 rounded-full shrink-0"
                  :style="{ backgroundColor: cat.color ?? '#6b7280' }"
                />
                {{ cat.icon ? cat.icon + ' ' : '' }}{{ cat.name }}
              </button>
            </div>
          </div>

          <!-- Удалить -->
          <button
            @click="handleBulkDelete"
            :disabled="isBulkLoading"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-50 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Удалить
          </button>
        </div>
      </div>
    </Transition>

    <!-- Модал категории -->
    <CategoryModal
      v-if="showCategoryModal"
      :category="modalCategory"
      @close="showCategoryModal = false"
      @saved="onModalSaved"
      @deleted="onModalDeleted"
    />
  </div>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.2s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
