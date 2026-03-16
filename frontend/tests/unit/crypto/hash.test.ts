import { describe, it, expect, vi } from 'vitest'
import { deriveVerifyHash } from '../../../src/crypto/kdf'
import { toBase64 } from '../../../src/crypto/utils'

describe('Hash (Verify)', () => {
  const saltBase64 = toBase64(new Uint8Array(32).fill(42))

  it('хэш верификации должен использовать контекст :verify', async () => {
    const mockBits = new ArrayBuffer(32)
    vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce({} as CryptoKey)
    vi.spyOn(crypto.subtle, 'deriveBits').mockResolvedValueOnce(mockBits)

    await deriveVerifyHash('mypassword', saltBase64)

    const importCall = vi.mocked(crypto.subtle.importKey).mock.calls[0]
    const decoded = new TextDecoder().decode(importCall[1] as ArrayBuffer)
    expect(decoded).toBe('mypassword:verify')
  })

  it('разные пароли дают разные хэши', async () => {
    const bits1 = new Uint8Array(32).fill(1).buffer
    const bits2 = new Uint8Array(32).fill(2).buffer

    vi.spyOn(crypto.subtle, 'importKey')
      .mockResolvedValueOnce({} as CryptoKey)
      .mockResolvedValueOnce({} as CryptoKey)
    vi.spyOn(crypto.subtle, 'deriveBits')
      .mockResolvedValueOnce(bits1)
      .mockResolvedValueOnce(bits2)

    const hash1 = await deriveVerifyHash('password1', saltBase64)
    const hash2 = await deriveVerifyHash('password2', saltBase64)

    expect(hash1).not.toBe(hash2)
  })

  it('возвращает строку из 64 hex символов', async () => {
    vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce({} as CryptoKey)
    vi.spyOn(crypto.subtle, 'deriveBits').mockResolvedValueOnce(new ArrayBuffer(32))

    const hash = await deriveVerifyHash('password', saltBase64)
    expect(hash).toMatch(/^[0-9a-f]{64}$/)
  })
})
