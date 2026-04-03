// @vitest-environment node
import { describe, it, expect, vi, beforeEach } from 'vitest'

vi.mock('../../../src/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}))

import { apiClient } from '../../../src/api/client'
import { twoFactorApi } from '../../../src/api/twoFactor'

const mockApiClient = apiClient as unknown as {
  get: ReturnType<typeof vi.fn>
  post: ReturnType<typeof vi.fn>
  delete: ReturnType<typeof vi.fn>
}

describe('twoFactorApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('getSetupData вызывает GET /2fa/setup', async () => {
    mockApiClient.get.mockResolvedValue({
      data: { data: { secret: 'TESTSECRET', qr_uri: 'otpauth://totp/PassFort:test' } },
    })

    const result = await twoFactorApi.getSetupData()

    expect(mockApiClient.get).toHaveBeenCalledWith('/2fa/setup')
    expect(result.secret).toBe('TESTSECRET')
    expect(result.qr_uri).toContain('otpauth://')
  })

  it('enable отправляет код на POST /2fa/enable', async () => {
    mockApiClient.post.mockResolvedValue({
      data: { data: { backup_codes: ['CODE0001', 'CODE0002'] } },
    })

    const result = await twoFactorApi.enable('123456')

    expect(mockApiClient.post).toHaveBeenCalledWith('/2fa/enable', { code: '123456' })
    expect(result.backup_codes).toHaveLength(2)
  })

  it('disable отправляет DELETE /2fa/disable с masterPasswordHash', async () => {
    mockApiClient.delete.mockResolvedValue({ data: null })

    await twoFactorApi.disable('hash_value')

    expect(mockApiClient.delete).toHaveBeenCalledWith('/2fa/disable', {
      data: { masterPasswordHash: 'hash_value' },
    })
  })

  it('regenerateBackupCodes отправляет POST с masterPasswordHash', async () => {
    mockApiClient.post.mockResolvedValue({
      data: { data: { backup_codes: ['NEW001'] } },
    })

    const result = await twoFactorApi.regenerateBackupCodes('hash_value')

    expect(mockApiClient.post).toHaveBeenCalledWith('/2fa/backup-codes/regenerate', {
      masterPasswordHash: 'hash_value',
    })
    expect(result.backup_codes).toContain('NEW001')
  })

  it('getStatus вызывает GET /2fa/status', async () => {
    mockApiClient.get.mockResolvedValue({
      data: { data: { is_enabled: true, has_backup_codes: true, backup_codes_count: 8 } },
    })

    const result = await twoFactorApi.getStatus()

    expect(mockApiClient.get).toHaveBeenCalledWith('/2fa/status')
    expect(result.is_enabled).toBe(true)
    expect(result.backup_codes_count).toBe(8)
  })

  it('verifyLogin отправляет tempToken и code на POST /auth/2fa/verify', async () => {
    mockApiClient.post.mockResolvedValue({
      data: {
        data: {
          access_token: 'jwt_access',
          refresh_token: 'jwt_refresh',
          token_type: 'Bearer',
          expires_in: 900,
        },
      },
    })

    const result = await twoFactorApi.verifyLogin({
      tempToken: 'temp_abc',
      code: '123456',
    })

    expect(mockApiClient.post).toHaveBeenCalledWith('/auth/2fa/verify', {
      tempToken: 'temp_abc',
      code: '123456',
    })
    expect(result.access_token).toBe('jwt_access')
    expect(result.token_type).toBe('Bearer')
  })
})
