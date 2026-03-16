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
}
