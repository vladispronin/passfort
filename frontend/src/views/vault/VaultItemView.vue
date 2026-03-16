<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'
import { useVaultItemsStore } from '../../stores/vaultItems'
import { useUiStore } from '../../stores/ui'
import { useClipboard } from '../../composables/useClipboard'
import type { DecryptedItemData, ItemType } from '../../types/vault'

const route = useRoute()
const router = useRouter()
const vaultStore = useVaultStore()
const itemsStore = useVaultItemsStore()
const uiStore = useUiStore()
const { copy } = useClipboard()

const isNew = computed(() => route.params.id === 'new')
const isLoading = ref(false)
const isDecrypting = ref(false)

const form = ref({
  titleHint: '',
  itemType: 'login' as ItemType,
  username: '',
  password: '',
  url: '',
  notes: '',
  categoryId: null as string | null,
})

const showPassword = ref(false)

onMounted(async () => {
  if (!isNew.value) {
    const item = itemsStore.items.find((i) => i.id === route.params.id)
    if (!item) {
      router.push('/vault')
      return
    }

    isDecrypting.value = true
    try {
      const data = await itemsStore.decryptItem(item)
      form.value = {
        titleHint: item.titleHint,
        itemType: item.itemType,
        username: data.username ?? '',
        password: data.password ?? '',
        url: data.url ?? '',
        notes: data.notes ?? '',
        categoryId: item.categoryId,
      }
    } catch {
      uiStore.showToast('Failed to decrypt item', 'error')
    } finally {
      isDecrypting.value = false
    }
  }
})

async function handleSave() {
  if (!vaultStore.currentVaultId) return
  isLoading.value = true

  try {
    const data: DecryptedItemData = {
      username: form.value.username,
      password: form.value.password,
      url: form.value.url,
      notes: form.value.notes,
    }

    if (isNew.value) {
      await itemsStore.createItem(
        vaultStore.currentVaultId,
        form.value.titleHint,
        form.value.itemType,
        data,
        form.value.categoryId,
      )
    } else {
      await itemsStore.updateItem(
        vaultStore.currentVaultId,
        route.params.id as string,
        form.value.titleHint,
        form.value.itemType,
        data,
        form.value.categoryId,
      )
    }

    uiStore.showToast('Item saved', 'success')
    router.push('/vault')
  } catch {
    uiStore.showToast('Failed to save item', 'error')
  } finally {
    isLoading.value = false
  }
}

async function handleDelete() {
  if (!vaultStore.currentVaultId || isNew.value) return
  if (!confirm('Delete this item?')) return

  try {
    await itemsStore.deleteItem(vaultStore.currentVaultId, route.params.id as string)
    uiStore.showToast('Item deleted', 'success')
    router.push('/vault')
  } catch {
    uiStore.showToast('Failed to delete item', 'error')
  }
}

async function copyPassword() {
  if (form.value.password) {
    await copy(form.value.password)
    uiStore.showToast('Password copied (clears in 30s)', 'info', 2000)
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <header class="bg-white shadow-sm">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
        <button @click="router.back()" class="text-gray-600 hover:text-gray-900">&#8592; Back</button>
        <h2 class="text-lg font-semibold">{{ isNew ? 'New Item' : 'Edit Item' }}</h2>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">
      <div v-if="isDecrypting" class="text-center py-12 text-gray-500">
        Decrypting...
      </div>

      <form v-else @submit.prevent="handleSave" class="bg-white rounded-xl shadow-sm p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Title</label>
          <input
            v-model="form.titleHint"
            type="text"
            required
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="e.g. GitHub"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Type</label>
          <select v-model="form.itemType" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md">
            <option value="login">Login</option>
            <option value="note">Secure Note</option>
            <option value="card">Card</option>
            <option value="identity">Identity</option>
          </select>
        </div>

        <template v-if="form.itemType === 'login'">
          <div>
            <label class="block text-sm font-medium text-gray-700">Username / Email</label>
            <input v-model="form.username" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <div class="relative mt-1">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="block w-full px-3 py-2 pr-20 border border-gray-300 rounded-md"
              />
              <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
                <button type="button" @click="showPassword = !showPassword" class="text-gray-400 hover:text-gray-600 text-xs">
                  {{ showPassword ? 'Hide' : 'Show' }}
                </button>
                <button type="button" @click="copyPassword" class="text-gray-400 hover:text-gray-600 text-xs">
                  Copy
                </button>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">URL</label>
            <input v-model="form.url" type="url" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>
        </template>

        <div>
          <label class="block text-sm font-medium text-gray-700">Notes</label>
          <textarea v-model="form.notes" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md" />
        </div>

        <div class="flex gap-3 pt-4">
          <button
            type="submit"
            :disabled="isLoading"
            class="flex-1 py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {{ isLoading ? 'Saving...' : 'Save' }}
          </button>
          <button
            v-if="!isNew"
            type="button"
            @click="handleDelete"
            class="py-2 px-4 border border-red-300 text-red-600 rounded-md hover:bg-red-50"
          >
            Delete
          </button>
        </div>
      </form>
    </main>
  </div>
</template>
