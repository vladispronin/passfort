export interface RegisterPayload {
  email: string
  masterPasswordHash: string
  salt: string
  kdfParams: KdfParams
}

export interface LoginPayload {
  email: string
  masterPasswordHash: string
}

export interface KdfParams {
  algorithm: string
  iterations: number
  hash: string
  keyLength: number
}

export interface KdfParamsResponse {
  salt: string
  algorithm: string
  iterations: number
  hash: string
  keyLength: number
}

export interface AuthTokens {
  access_token: string
  refresh_token: string
  token_type: string
  expires_in: number
}

export interface UserProfile {
  id: string
  email: string
  kdfParams: KdfParams
  salt: string
  createdAt: string
  is2faEnabled: boolean
}

export interface LoginRequires2FA {
  requires_2fa: true
  temp_token: string
}

export type LoginResponse = AuthTokens | LoginRequires2FA

export interface TwoFactorVerifyPayload {
  tempToken: string
  code: string
}

export interface TwoFactorSetupData {
  secret: string
  qr_uri: string
}

export interface BackupCodesResponse {
  backup_codes: string[]
}

export interface TwoFactorStatus {
  is_enabled: boolean
  has_backup_codes: boolean
  backup_codes_count: number
}
