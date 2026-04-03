import { apiClient } from './client'
import type {
  RegisterPayload,
  LoginPayload,
  LoginResponse,
  AuthTokens,
  KdfParamsResponse,
  UserProfile,
} from '../types/auth'
import type { ApiResponse } from '../types/api'

export const authApi = {
  async getKdfParams(email: string): Promise<KdfParamsResponse> {
    const { data } = await apiClient.get<ApiResponse<KdfParamsResponse>>('/auth/kdf-params', {
      params: { email },
    })
    return data.data
  },

  async register(payload: RegisterPayload): Promise<{ id: string; email: string }> {
    const { data } = await apiClient.post<ApiResponse<{ id: string; email: string }>>(
      '/auth/register',
      payload,
    )
    return data.data
  },

  async login(payload: LoginPayload): Promise<LoginResponse> {
    const { data } = await apiClient.post<ApiResponse<LoginResponse>>('/auth/login', payload)
    return data.data
  },

  async logout(): Promise<void> {
    await apiClient.post('/auth/logout')
  },

  async refresh(refreshToken: string): Promise<AuthTokens> {
    const { data } = await apiClient.post<ApiResponse<AuthTokens>>('/auth/refresh', {
      refresh_token: refreshToken,
    })
    return data.data
  },

  async getMe(): Promise<UserProfile> {
    const { data } = await apiClient.get<ApiResponse<UserProfile>>('/user/me')
    return data.data
  },
}
