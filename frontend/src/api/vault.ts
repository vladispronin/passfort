import { apiClient } from './client'
import type { Vault } from '../types/vault'
import type { ApiResponse } from '../types/api'

export interface VaultExportData {
  version: string
  exportedAt: string
  vault: { name: string }
  categories: Array<{ id: string; name: string; color: string | null; icon: string | null }>
  items: Array<{
    encryptedData: string
    iv: string
    authTag: string
    itemType: string
    titleHint: string
    isFavorite: boolean
    categoryId: string | null
  }>
}

export interface VaultImportResult {
  imported: { categories: number; items: number }
}

export const vaultApi = {
  async list(): Promise<Vault[]> {
    const { data } = await apiClient.get<ApiResponse<Vault[]>>('/vaults')
    return data.data
  },

  async get(id: string): Promise<Vault> {
    const { data } = await apiClient.get<ApiResponse<Vault>>(`/vaults/${id}`)
    return data.data
  },

  async create(name: string): Promise<Vault> {
    const { data } = await apiClient.post<ApiResponse<Vault>>('/vaults', { name })
    return data.data
  },

  async update(id: string, name: string): Promise<Vault> {
    const { data } = await apiClient.put<ApiResponse<Vault>>(`/vaults/${id}`, { name })
    return data.data
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/vaults/${id}`)
  },

  async exportVault(id: string): Promise<VaultExportData> {
    const { data } = await apiClient.get<ApiResponse<VaultExportData>>(`/vaults/${id}/export`)
    return data.data
  },

  async importVault(id: string, payload: VaultExportData): Promise<VaultImportResult> {
    const { data } = await apiClient.post<ApiResponse<VaultImportResult>>(`/vaults/${id}/import`, payload)
    return data.data
  },
}
