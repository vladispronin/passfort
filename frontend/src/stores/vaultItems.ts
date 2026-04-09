import { defineStore } from 'pinia'
import { ref } from 'vue'
import { vaultItemsApi } from '../api/vaultItems'
import { decrypt, encrypt } from '../crypto'
import { useAuthStore } from './auth'
import type { VaultItem, CreateVaultItemPayload, DecryptedItemData, ItemType } from '../types/vault'
import type { PaginationMeta } from '../types/api'

const DEFAULT_LIMIT = 30

export const useVaultItemsStore = defineStore('vaultItems', () => {
  const items = ref<VaultItem[]>([])
  const decryptedItems = ref<Map<string, DecryptedItemData>>(new Map())
  const isLoading = ref(false)
  const searchQuery = ref('')
  const selectedCategoryId = ref<string | null>(null)
  const selectedItemType = ref<ItemType | null>(null)
  const pagination = ref<PaginationMeta>({ total: 0, page: 1, limit: DEFAULT_LIMIT, pages: 0 })

  function setFilter(categoryId: string | null): void {
    selectedCategoryId.value = categoryId
  }

  async function loadItems(vaultId: string, page: number = 1): Promise<void> {
    isLoading.value = true
    try {
      const result = await vaultItemsApi.list(vaultId, {
        type: selectedItemType.value,
        category: selectedCategoryId.value,
        q: searchQuery.value || undefined,
        page,
        limit: pagination.value.limit,
      })
      items.value = result.items
      pagination.value = result.meta
      decryptedItems.value.clear()
    } finally {
      isLoading.value = false
    }
  }

  async function applyFilters(vaultId: string): Promise<void> {
    await loadItems(vaultId, 1)
  }

  async function setPage(vaultId: string, page: number): Promise<void> {
    await loadItems(vaultId, page)
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
    items.value.unshift(item)
    pagination.value.total += 1
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
    pagination.value.total = Math.max(0, pagination.value.total - 1)
  }

  async function toggleFavorite(vaultId: string, itemId: string): Promise<void> {
    const updated = await vaultItemsApi.toggleFavorite(vaultId, itemId)
    const index = items.value.findIndex((i) => i.id === itemId)
    if (index !== -1) items.value[index] = updated
  }

  async function fetchAllIds(vaultId: string): Promise<string[]> {
    const result = await vaultItemsApi.list(vaultId, {
      type: selectedItemType.value,
      category: selectedCategoryId.value,
      q: searchQuery.value || undefined,
      page: 1,
      limit: 5000,
    })
    return result.items.map((i) => i.id)
  }

  async function bulkDeleteItems(vaultId: string, ids: string[]): Promise<number> {
    const deleted = await vaultItemsApi.bulkDelete(vaultId, ids)
    items.value = items.value.filter((i) => !ids.includes(i.id))
    ids.forEach((id) => decryptedItems.value.delete(id))
    pagination.value.total = Math.max(0, pagination.value.total - deleted)
    return deleted
  }

  async function bulkMoveItems(
    vaultId: string,
    ids: string[],
    categoryId: string | null,
  ): Promise<number> {
    const moved = await vaultItemsApi.bulkMove(vaultId, ids, categoryId)
    items.value = items.value.map((item) =>
      ids.includes(item.id) ? { ...item, categoryId: categoryId ?? undefined } : item,
    )
    return moved
  }

  function reset(): void {
    items.value = []
    decryptedItems.value.clear()
    searchQuery.value = ''
    selectedCategoryId.value = null
    selectedItemType.value = null
    pagination.value = { total: 0, page: 1, limit: DEFAULT_LIMIT, pages: 0 }
  }

  return {
    items,
    isLoading,
    searchQuery,
    selectedCategoryId,
    selectedItemType,
    pagination,
    loadItems,
    applyFilters,
    setPage,
    decryptItem,
    createItem,
    updateItem,
    deleteItem,
    toggleFavorite,
    fetchAllIds,
    bulkDeleteItems,
    bulkMoveItems,
    setFilter,
    reset,
  }
})
