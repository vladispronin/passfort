// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '../../../src/stores/auth'

describe('useAuthStore — 2FA state', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('изначально pendingTwoFactor равен null', () => {
    const store = useAuthStore()
    expect(store.pendingTwoFactor).toBeNull()
  })

  it('requiresTwoFactor возвращает false при отсутствии pending', () => {
    const store = useAuthStore()
    expect(store.requiresTwoFactor).toBe(false)
  })

  it('setPendingTwoFactor устанавливает состояние', () => {
    const store = useAuthStore()

    const pending = {
      tempToken: 'test_temp_token',
      email: 'test@example.com',
      masterPasswordHash: 'hash',
      masterPassword: 'password',
    }

    store.setPendingTwoFactor(pending)

    expect(store.pendingTwoFactor).toEqual(pending)
    expect(store.requiresTwoFactor).toBe(true)
  })

  it('clearPendingTwoFactor очищает состояние', () => {
    const store = useAuthStore()

    store.setPendingTwoFactor({
      tempToken: 'token',
      email: 'test@example.com',
      masterPasswordHash: 'hash',
      masterPassword: 'password',
    })

    store.clearPendingTwoFactor()

    expect(store.pendingTwoFactor).toBeNull()
    expect(store.requiresTwoFactor).toBe(false)
  })

  it('clearAuth также очищает pendingTwoFactor', () => {
    const store = useAuthStore()

    store.setPendingTwoFactor({
      tempToken: 'token',
      email: 'test@example.com',
      masterPasswordHash: 'hash',
      masterPassword: 'password',
    })

    store.clearAuth()

    expect(store.pendingTwoFactor).toBeNull()
    expect(store.requiresTwoFactor).toBe(false)
  })

  it('requiresTwoFactor вычисляется реактивно', () => {
    const store = useAuthStore()

    expect(store.requiresTwoFactor).toBe(false)

    store.setPendingTwoFactor({
      tempToken: 'token',
      email: 'test@example.com',
      masterPasswordHash: 'hash',
      masterPassword: 'password',
    })

    expect(store.requiresTwoFactor).toBe(true)

    store.clearPendingTwoFactor()

    expect(store.requiresTwoFactor).toBe(false)
  })
})
