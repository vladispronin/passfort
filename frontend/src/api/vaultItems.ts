import { apiClient } from './client'
import type { VaultItem, CreateVaultItemPayload, ItemType } from '../types/vault'
import type { ApiResponse, PaginationMeta } from '../types/api'

export interface VaultItemsFilters {
  type?: ItemType | null
  category?: string | null
  q?: string
  favorite?: boolean
  page?: number
  limit?: number
}

export interface VaultItemsPage {
  items: VaultItem[]
  meta: PaginationMeta
}

export const vaultItemsApi = {
  async list(vaultId: string, filters?: VaultItemsFilters): Promise<VaultItemsPage> {
    const params: Record<string, string | number | boolean> = {}
    if (filters?.type) params.type = filters.type
    if (filters?.category) params.category = filters.category
    if (filters?.q) params.q = filters.q
    if (filters?.favorite) params.favorite = true
    if (filters?.page) params.page = filters.page
    if (filters?.limit) params.limit = filters.limit

    const { data } = await apiClient.get<ApiResponse<VaultItem[]>>(`/vaults/${vaultId}/items`, { params })
    return {
      items: data.data,
      meta: data.meta as PaginationMeta,
    }
  },

  async get(vaultId: string, itemId: string): Promise<VaultItem> {
    const { data } = await apiClient.get<ApiResponse<VaultItem>>(
      `/vaults/${vaultId}/items/${itemId}`,
    )
    return data.data
  },

  async create(vaultId: string, payload: CreateVaultItemPayload): Promise<VaultItem> {
    const { data } = await apiClient.post<ApiResponse<VaultItem>>(
      `/vaults/${vaultId}/items`,
      payload,
    )
    return data.data
  },

  async update(
    vaultId: string,
    itemId: string,
    payload: CreateVaultItemPayload,
  ): Promise<VaultItem> {
    const { data } = await apiClient.put<ApiResponse<VaultItem>>(
      `/vaults/${vaultId}/items/${itemId}`,
      payload,
    )
    return data.data
  },

  async delete(vaultId: string, itemId: string): Promise<void> {
    await apiClient.delete(`/vaults/${vaultId}/items/${itemId}`)
  },

  async toggleFavorite(vaultId: string, itemId: string): Promise<VaultItem> {
    const { data } = await apiClient.patch<ApiResponse<VaultItem>>(
      `/vaults/${vaultId}/items/${itemId}/favorite`,
    )
    return data.data
  },
}
