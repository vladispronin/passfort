<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'
import { useVaultItemsStore } from '../../stores/vaultItems'
import { useCategoriesStore } from '../../stores/categories'
import { useAuth } from '../../composables/useAuth'
import { useAutoLock } from '../../composables/useAutoLock'
import { useUiStore } from '../../stores/ui'

useAutoLock()

const vaultStore = useVaultStore()
const itemsStore = useVaultItemsStore()
const categoriesStore = useCategoriesStore()
const { logout } = useAuth()
const uiStore = useUiStore()
const router = useRouter()

const isLoadingVault = ref(true)

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
    uiStore.showToast('Failed to load vault', 'error')
  } finally {
    isLoadingVault.value = false
  }
})

function openItem(id: string) {
  router.push(`/vault/item/${id}`)
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">PassFort</h1>
        <div class="flex items-center gap-3">
          <input
            v-model="itemsStore.searchQuery"
            type="search"
            placeholder="Search items..."
            class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <router-link to="/profile" class="text-sm text-gray-600 hover:text-gray-900">Profile</router-link>
          <button @click="logout" class="text-sm text-red-600 hover:text-red-800">Logout</button>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto px-4 py-6">
      <div v-if="isLoadingVault" class="text-center py-12 text-gray-500">
        Loading vault...
      </div>

      <div v-else>
        <!-- Items grid -->
        <div v-if="itemsStore.filteredItems.length === 0" class="text-center py-12">
          <div class="text-4xl mb-4">&#128205;</div>
          <p class="text-gray-500">No items yet. Add your first password!</p>
          <router-link
            to="/vault/item/new"
            class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Add Item
          </router-link>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="item in itemsStore.filteredItems"
            :key="item.id"
            @click="openItem(item.id)"
            class="bg-white p-4 rounded-lg shadow-sm cursor-pointer hover:shadow-md transition-shadow border border-gray-200"
          >
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-900">{{ item.titleHint }}</span>
              <span v-if="item.isFavorite" class="text-yellow-500">&#9733;</span>
            </div>
            <div class="mt-1">
              <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                {{ item.itemType }}
              </span>
            </div>
          </div>
        </div>

        <!-- FAB -->
        <router-link
          to="/vault/item/new"
          class="fixed bottom-8 right-8 w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-2xl hover:bg-blue-700"
        >
          +
        </router-link>
      </div>
    </main>
  </div>
</template>
