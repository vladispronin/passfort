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
import { userApi } from '../../../src/api/user'

const mockApiClient = apiClient as unknown as {
  get: ReturnType<typeof vi.fn>
  post: ReturnType<typeof vi.fn>
  delete: ReturnType<typeof vi.fn>
}

describe('userApi.getSessions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('вызывает GET /user/sessions и возвращает список сессий', async () => {
    const mockSessions = [
      {
        id: 'session-uuid-1',
        ipAddress: '127.0.0.1',
        deviceInfo: 'Mozilla/5.0 Chrome/120',
        createdAt: '2026-01-01T00:00:00+00:00',
        expiresAt: '2026-01-31T00:00:00+00:00',
        isCurrent: true,
      },
      {
        id: 'session-uuid-2',
        ipAddress: '10.0.0.1',
        deviceInfo: 'Mozilla/5.0 Firefox/121',
        createdAt: '2026-01-02T00:00:00+00:00',
        expiresAt: '2026-02-01T00:00:00+00:00',
        isCurrent: false,
      },
    ]

    mockApiClient.get.mockResolvedValue({ data: { data: mockSessions } })

    const result = await userApi.getSessions()

    expect(mockApiClient.get).toHaveBeenCalledWith('/user/sessions')
    expect(result).toHaveLength(2)
    expect(result[0].id).toBe('session-uuid-1')
    expect(result[0].isCurrent).toBe(true)
    expect(result[1].isCurrent).toBe(false)
  })

  it('возвращает пустой массив если сессий нет', async () => {
    mockApiClient.get.mockResolvedValue({ data: { data: [] } })

    const result = await userApi.getSessions()

    expect(result).toHaveLength(0)
  })
})

describe('userApi.revokeSession', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('вызывает DELETE /user/sessions/{id}', async () => {
    mockApiClient.delete.mockResolvedValue({})

    await userApi.revokeSession('session-uuid-1')

    expect(mockApiClient.delete).toHaveBeenCalledWith('/user/sessions/session-uuid-1')
  })

  it('пробрасывает ошибку при 404', async () => {
    mockApiClient.delete.mockRejectedValue({ response: { status: 404 } })

    await expect(userApi.revokeSession('nonexistent-id')).rejects.toMatchObject({
      response: { status: 404 },
    })
  })
})
