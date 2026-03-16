import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { vaultItemsApi } from '../api/vaultItems'
import { decrypt, encrypt } from '../crypto'
import { useAuthStore } from './auth'
import type { VaultItem, CreateVaultItemPayload, DecryptedItemData } from '../types/vault'

export const useVaultItemsStore = defineStore('vaultItems', () => {
  const items = ref<VaultItem[]>([])
  const decryptedItems = ref<Map<string, DecryptedItemData>>(new Map())
  const isLoading = ref(false)
  const searchQuery = ref('')

  const filteredItems = computed(() => {
    if (!searchQuery.value) return items.value
    const query = searchQuery.value.toLowerCase()
    return items.value.filter((item) =>
      item.titleHint.toLowerCase().includes(query),
    )
  })

  const favoriteItems = computed(() =>
    items.value.filter((item) => item.isFavorite),
  )

  async function loadItems(vaultId: string): Promise<void> {
    isLoading.value = true
    try {
      items.value = await vaultItemsApi.list(vaultId)
      decryptedItems.value.clear()
    } finally {
      isLoading.value = false
    }
  }

  async function decryptItem(item: VaultItem): Promise<DecryptedItemData> {
    const cached = decryptedItems.value.get(item.id)
    if (cached) return cached

    const authStore = useAuthStore()
    if (!authStore.encryptionKey) {
      throw new Error('Vault is locked')
    }

    const decryptedJson = await decrypt(
      item.encryptedData,
      item.iv,
      item.authTag,
      authStore.encryptionKey,
    )

    const data: DecryptedItemData = JSON.parse(decryptedJson)
    decryptedItems.value.set(item.id, data)
    return data
  }

  async function createItem(
    vaultId: string,
    titleHint: string,
    itemType: VaultItem['itemType'],
    data: DecryptedItemData,
    categoryId?: string | null,
  ): Promise<VaultItem> {
    const authStore = useAuthStore()
    if (!authStore.encryptionKey) {
      throw new Error('Vault is locked')
    }

    const { encryptedData, iv, authTag } = await encrypt(
      JSON.stringify(data),
      authStore.encryptionKey,
    )

    const payload: CreateVaultItemPayload = {
      encryptedData,
      iv,
      authTag,
      itemType,
      titleHint,
      categoryId: categoryId ?? null,
    }

    const item = await vaultItemsApi.create(vaultId, payload)
    items.value.push(item)
    return item
  }

  async function updateItem(
    vaultId: string,
    itemId: string,
    titleHint: string,
    itemType: VaultItem['itemType'],
    data: DecryptedItemData,
    categoryId?: string | null,
  ): Promise<VaultItem> {
    const authStore = useAuthStore()
    if (!authStore.encryptionKey) {
      throw new Error('Vault is locked')
    }

    const { encryptedData, iv, authTag } = await encrypt(
      JSON.stringify(data),
      authStore.encryptionKey,
    )

    const payload: CreateVaultItemPayload = {
      encryptedData,
      iv,
      authTag,
      itemType,
      titleHint,
      categoryId: categoryId ?? null,
    }

    const updated = await vaultItemsApi.update(vaultId, itemId, payload)
    const index = items.value.findIndex((i) => i.id === itemId)
    if (index !== -1) items.value[index] = updated
    decryptedItems.value.delete(itemId)
    return updated
  }

  async function deleteItem(vaultId: string, itemId: string): Promise<void> {
    await vaultItemsApi.delete(vaultId, itemId)
    items.value = items.value.filter((i) => i.id !== itemId)
    decryptedItems.value.delete(itemId)
  }

  async function toggleFavorite(vaultId: string, itemId: string): Promise<void> {
    const updated = await vaultItemsApi.toggleFavorite(vaultId, itemId)
    const index = items.value.findIndex((i) => i.id === itemId)
    if (index !== -1) items.value[index] = updated
  }

  function reset(): void {
    items.value = []
    decryptedItems.value.clear()
    searchQuery.value = ''
  }

  return {
    items,
    isLoading,
    searchQuery,
    filteredItems,
    favoriteItems,
    loadItems,
    decryptItem,
    createItem,
    updateItem,
    deleteItem,
    toggleFavorite,
    reset,
  }
})
