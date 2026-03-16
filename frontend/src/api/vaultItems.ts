import { apiClient } from './client'
import type { VaultItem, CreateVaultItemPayload } from '../types/vault'
import type { ApiResponse } from '../types/api'

export const vaultItemsApi = {
  async list(vaultId: string): Promise<VaultItem[]> {
    const { data } = await apiClient.get<ApiResponse<VaultItem[]>>(`/vaults/${vaultId}/items`)
    return data.data
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
