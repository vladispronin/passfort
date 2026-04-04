import { apiClient } from './client'
import type { ChangeMasterPasswordPayload } from '../types/user'
import type { AuthTokens, Session, SecurityLogEntry, PaginationMeta } from '../types/auth'
import type { ApiResponse } from '../types/api'

export const userApi = {
  async changeMasterPassword(payload: ChangeMasterPasswordPayload): Promise<AuthTokens> {
    const { data } = await apiClient.post<ApiResponse<AuthTokens>>('/user/master-password', payload)
    return data.data
  },

  async getSessions(): Promise<Session[]> {
    const { data } = await apiClient.get<ApiResponse<Session[]>>('/user/sessions')
    return data.data
  },

  async revokeSession(id: string): Promise<void> {
    await apiClient.delete(`/user/sessions/${id}`)
  },

  async requestEmailChange(newEmail: string): Promise<void> {
    await apiClient.post('/user/email-change', { newEmail })
  },

  async confirmEmailChange(token: string): Promise<{ message: string }> {
    const { data } = await apiClient.get<ApiResponse<{ message: string }>>('/user/email-change/confirm', { params: { token } })
    return data.data
  },

  async getSecurityLog(page = 1, limit = 20): Promise<{ data: SecurityLogEntry[]; meta: { pagination: PaginationMeta } }> {
    const { data } = await apiClient.get<{ data: SecurityLogEntry[]; meta: { pagination: PaginationMeta } }>('/user/security-log', {
      params: { page, limit },
    })
    return data
  },
}
