import { apiClient } from './client'
import type {
  TwoFactorSetupData,
  BackupCodesResponse,
  TwoFactorStatus,
  TwoFactorVerifyPayload,
  AuthTokens,
} from '../types/auth'
import type { ApiResponse } from '../types/api'

export const twoFactorApi = {
  async getSetupData(): Promise<TwoFactorSetupData> {
    const { data } = await apiClient.get<ApiResponse<TwoFactorSetupData>>('/2fa/setup')
    return data.data
  },

  async enable(code: string): Promise<BackupCodesResponse> {
    const { data } = await apiClient.post<ApiResponse<BackupCodesResponse>>('/2fa/enable', { code })
    return data.data
  },

  async disable(masterPasswordHash: string): Promise<void> {
    await apiClient.delete('/2fa/disable', { data: { masterPasswordHash } })
  },

  async regenerateBackupCodes(masterPasswordHash: string): Promise<BackupCodesResponse> {
    const { data } = await apiClient.post<ApiResponse<BackupCodesResponse>>(
      '/2fa/backup-codes/regenerate',
      { masterPasswordHash },
    )
    return data.data
  },

  async getStatus(): Promise<TwoFactorStatus> {
    const { data } = await apiClient.get<ApiResponse<TwoFactorStatus>>('/2fa/status')
    return data.data
  },

  async verifyLogin(payload: TwoFactorVerifyPayload): Promise<AuthTokens> {
    const { data } = await apiClient.post<ApiResponse<AuthTokens>>('/auth/2fa/verify', {
      tempToken: payload.tempToken,
      code: payload.code,
    })
    return data.data
  },
}
