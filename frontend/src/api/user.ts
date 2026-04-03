import { apiClient } from './client'
import type { ChangeMasterPasswordPayload } from '../types/user'
import type { AuthTokens, Session } from '../types/auth'
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
}
