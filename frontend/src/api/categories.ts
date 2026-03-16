import { apiClient } from './client'
import type { Category, CreateCategoryPayload } from '../types/category'
import type { ApiResponse } from '../types/api'

export const categoriesApi = {
  async list(vaultId: string): Promise<Category[]> {
    const { data } = await apiClient.get<ApiResponse<Category[]>>(
      `/vaults/${vaultId}/categories`,
    )
    return data.data
  },

  async create(vaultId: string, payload: CreateCategoryPayload): Promise<Category> {
    const { data } = await apiClient.post<ApiResponse<Category>>(
      `/vaults/${vaultId}/categories`,
      payload,
    )
    return data.data
  },

  async update(
    vaultId: string,
    categoryId: string,
    payload: CreateCategoryPayload,
  ): Promise<Category> {
    const { data } = await apiClient.put<ApiResponse<Category>>(
      `/vaults/${vaultId}/categories/${categoryId}`,
      payload,
    )
    return data.data
  },

  async delete(vaultId: string, categoryId: string): Promise<void> {
    await apiClient.delete(`/vaults/${vaultId}/categories/${categoryId}`)
  },
}
