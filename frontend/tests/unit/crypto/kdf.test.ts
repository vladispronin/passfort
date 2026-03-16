import { describe, it, expect, vi, beforeEach } from 'vitest'
import { deriveEncryptionKey, deriveVerifyHash } from '../../../src/crypto/kdf'
import { toBase64, randomBytes } from '../../../src/crypto/utils'

describe('KDF Module', () => {
  const mockSalt = toBase64(new Uint8Array(32).fill(1))
  const masterPassword = 'TestMasterPassword123!'

  describe('deriveEncryptionKey', () => {
    it('должен возвращать CryptoKey объект', async () => {
      const mockKey = { type: 'secret', algorithm: { name: 'AES-GCM' } }

      vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce(mockKey as CryptoKey)
      vi.spyOn(crypto.subtle, 'deriveKey').mockResolvedValueOnce(mockKey as CryptoKey)

      const key = await deriveEncryptionKey(masterPassword, mockSalt)
      expect(key).toBeDefined()
      expect(crypto.subtle.importKey).toHaveBeenCalledWith(
        'raw',
        expect.any(Uint8Array),
        'PBKDF2',
        false,
        ['deriveBits', 'deriveKey'],
      )
    })

    it('должен использовать PBKDF2 с SHA-256', async () => {
      const mockKey = { type: 'secret' }
      vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce(mockKey as CryptoKey)
      vi.spyOn(crypto.subtle, 'deriveKey').mockResolvedValueOnce(mockKey as CryptoKey)

      await deriveEncryptionKey(masterPassword, mockSalt)

      expect(crypto.subtle.deriveKey).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'PBKDF2',
          iterations: 600_000,
          hash: 'SHA-256',
        }),
        mockKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
      )
    })

    it('должен использовать 600000 итераций по умолчанию', async () => {
      const mockKey = {}
      vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce(mockKey as CryptoKey)
      vi.spyOn(crypto.subtle, 'deriveKey').mockResolvedValueOnce(mockKey as CryptoKey)

      await deriveEncryptionKey(masterPassword, mockSalt)

      const deriveKeyCall = vi.mocked(crypto.subtle.deriveKey).mock.calls[0]
      expect((deriveKeyCall[0] as Pbkdf2Params).iterations).toBe(600_000)
    })

    it('должен принимать кастомные параметры итераций', async () => {
      const mockKey = {}
      vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce(mockKey as CryptoKey)
      vi.spyOn(crypto.subtle, 'deriveKey').mockResolvedValueOnce(mockKey as CryptoKey)

      await deriveEncryptionKey(masterPassword, mockSalt, { iterations: 100_000 })

      const deriveKeyCall = vi.mocked(crypto.subtle.deriveKey).mock.calls[0]
      expect((deriveKeyCall[0] as Pbkdf2Params).iterations).toBe(100_000)
    })
  })

  describe('deriveVerifyHash', () => {
    it('должен возвращать hex строку из 64 символов (32 байта)', async () => {
      const mockBits = new ArrayBuffer(32)
      vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce({} as CryptoKey)
      vi.spyOn(crypto.subtle, 'deriveBits').mockResolvedValueOnce(mockBits)

      const hash = await deriveVerifyHash(masterPassword, mockSalt)

      expect(hash).toMatch(/^[0-9a-f]{64}$/)
    })

    it('должен использовать контекст :verify в пароле', async () => {
      const mockBits = new ArrayBuffer(32)
      vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce({} as CryptoKey)
      vi.spyOn(crypto.subtle, 'deriveBits').mockResolvedValueOnce(mockBits)

      await deriveVerifyHash(masterPassword, mockSalt)

      const importKeyCall = vi.mocked(crypto.subtle.importKey).mock.calls[0]
      const passwordBytes = importKeyCall[1] as Uint8Array
      const decoded = new TextDecoder().decode(passwordBytes)
      expect(decoded).toBe(masterPassword + ':verify')
    })

    it('должен использовать 100000 итераций (меньше чем для ключа)', async () => {
      const mockBits = new ArrayBuffer(32)
      vi.spyOn(crypto.subtle, 'importKey').mockResolvedValueOnce({} as CryptoKey)
      vi.spyOn(crypto.subtle, 'deriveBits').mockResolvedValueOnce(mockBits)

      await deriveVerifyHash(masterPassword, mockSalt)

      const deriveBitsCall = vi.mocked(crypto.subtle.deriveBits).mock.calls[0]
      expect((deriveBitsCall[0] as Pbkdf2Params).iterations).toBe(100_000)
    })
  })
})
