import { apiClient } from './client'
import type { ChangeMasterPasswordPayload } from '../types/user'
import type { AuthTokens } from '../types/auth'
import type { ApiResponse } from '../types/api'

export const userApi = {
  async changeMasterPassword(payload: ChangeMasterPasswordPayload): Promise<AuthTokens> {
    const { data } = await apiClient.post<ApiResponse<AuthTokens>>('/user/master-password', payload)
    return data.data
  },
}
