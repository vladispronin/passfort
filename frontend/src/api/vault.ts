import { apiClient } from './client'
import type { Vault } from '../types/vault'
import type { ApiResponse } from '../types/api'

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
}
