<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'
import { useVaultItemsStore } from '../../stores/vaultItems'
import { useCategoriesStore } from '../../stores/categories'
import { useAuth } from '../../composables/useAuth'
import { useAutoLock } from '../../composables/useAutoLock'
import { useUiStore } from '../../stores/ui'
import CategoryModal from '../../components/category/CategoryModal.vue'
import type { Category } from '../../types/category'

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

const itemCountByCategory = computed(() => {
  const counts = new Map<string, number>()
  for (const item of itemsStore.items) {
    if (item.categoryId) {
      counts.set(item.categoryId, (counts.get(item.categoryId) ?? 0) + 1)
    }
  }
  return counts
})

const categoryById = computed(() => {
  const map = new Map<string, Category>()
  for (const cat of categoriesStore.categories) {
    map.set(cat.id, cat)
  }
  return map
})

function openItem(id: string) {
  router.push(`/vault/item/${id}`)
}

function selectCategory(categoryId: string | null) {
  itemsStore.setFilter(categoryId)
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
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#eef2f8]">
    <!-- Шапка -->
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <img src="/passfort-icon.svg" alt="PassFort" class="w-7 h-7" />
          <h1 class="text-xl font-bold text-brand-900">PassFort</h1>
        </div>
        <div class="flex items-center gap-3">
          <input
            v-model="itemsStore.searchQuery"
            type="search"
            placeholder="Поиск..."
            class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50"
          />
          <router-link to="/profile" class="text-sm text-slate-600 hover:text-slate-900">Профиль</router-link>
          <button @click="logout" class="text-sm text-rose-500 hover:text-rose-700">Выход</button>
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
              <span class="text-xs text-slate-400">{{ itemsStore.items.length }}</span>
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
              <span class="flex items-center gap-1 shrink-0">
                <span class="text-xs text-slate-400">{{ itemCountByCategory.get(cat.id) ?? 0 }}</span>
                <span
                  @click="openEditModal(cat, $event)"
                  class="text-slate-300 hover:text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-xs ml-1"
                  title="Редактировать"
                >
                  &#9998;
                </span>
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
          <div v-if="itemsStore.filteredItems.length === 0" class="text-center py-12">
            <div class="text-4xl mb-4">&#128205;</div>
            <p class="text-slate-500">
              {{ itemsStore.selectedCategoryId ? 'В этой категории нет записей.' : 'Нет записей. Добавьте первый пароль!' }}
            </p>
            <router-link
              v-if="!itemsStore.selectedCategoryId"
              to="/vault/item/new"
              class="mt-4 inline-block px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 text-sm font-medium transition-colors"
            >
              Добавить запись
            </router-link>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="item in itemsStore.filteredItems"
              :key="item.id"
              @click="openItem(item.id)"
              class="bg-white p-4 rounded-xl shadow-sm cursor-pointer hover:shadow-md transition-shadow border border-slate-100"
            >
              <div class="flex items-center justify-between">
                <span class="font-medium text-slate-900 truncate">{{ item.titleHint }}</span>
                <span v-if="item.isFavorite" class="text-yellow-400 ml-2 shrink-0">&#9733;</span>
              </div>
              <div class="mt-2 flex items-center gap-2 flex-wrap">
                <span class="text-xs px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full">
                  {{ item.itemType }}
                </span>
                <span
                  v-if="item.categoryId && categoryById.get(item.categoryId)"
                  class="text-xs px-2 py-0.5 rounded-full text-white font-medium"
                  :style="{ backgroundColor: categoryById.get(item.categoryId)?.color ?? '#6b7280' }"
                >
                  {{ categoryById.get(item.categoryId)?.icon ? categoryById.get(item.categoryId)?.icon + ' ' : '' }}
                  {{ categoryById.get(item.categoryId)?.name }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- FAB: новая запись -->
    <router-link
      to="/vault/item/new"
      class="fixed bottom-8 right-8 w-14 h-14 bg-brand-500 text-white rounded-full shadow-lg flex items-center justify-center text-2xl hover:bg-brand-600 transition-colors"
    >
      +
    </router-link>

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
