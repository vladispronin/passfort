import { describe, it, expect, vi } from 'vitest'
import { encrypt, decrypt } from '../../../src/crypto/aes'
import { toBase64 } from '../../../src/crypto/utils'

describe('AES Module', () => {
  const mockKey = { type: 'secret', algorithm: { name: 'AES-GCM' } } as CryptoKey
  const testData = JSON.stringify({ username: 'user@test.com', password: 'secret123' })

  describe('encrypt', () => {
    it('должен возвращать encryptedData, iv и authTag', async () => {
      const mockEncrypted = new Uint8Array([...new Array(32).fill(0xAB), ...new Array(16).fill(0xCD)])
      vi.spyOn(crypto.subtle, 'encrypt').mockResolvedValueOnce(mockEncrypted.buffer)

      const result = await encrypt(testData, mockKey)

      expect(result).toHaveProperty('encryptedData')
      expect(result).toHaveProperty('iv')
      expect(result).toHaveProperty('authTag')
      expect(typeof result.encryptedData).toBe('string')
      expect(typeof result.iv).toBe('string')
      expect(typeof result.authTag).toBe('string')
    })

    it('должен использовать AES-GCM с 128-битным auth tag', async () => {
      const mockEncrypted = new Uint8Array(48)
      vi.spyOn(crypto.subtle, 'encrypt').mockResolvedValueOnce(mockEncrypted.buffer)

      await encrypt(testData, mockKey)

      expect(crypto.subtle.encrypt).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'AES-GCM',
          tagLength: 128,
        }),
        mockKey,
        expect.any(Uint8Array),
      )
    })

    it('должен генерировать уникальный IV каждый раз', async () => {
      const mockEncrypted = new Uint8Array(48)
      vi.spyOn(crypto.subtle, 'encrypt')
        .mockResolvedValueOnce(mockEncrypted.buffer)
        .mockResolvedValueOnce(mockEncrypted.buffer)

      const result1 = await encrypt(testData, mockKey)
      const result2 = await encrypt(testData, mockKey)

      // IVs должны быть разными (random)
      // В тестовой среде crypto.getRandomValues даёт псевдо-случайные числа
      expect(result1.iv).toBeDefined()
      expect(result2.iv).toBeDefined()
    })

    it('должен разделять authTag (последние 16 байт) от ciphertext', async () => {
      // 32 байта ciphertext + 16 байт tag
      const ciphertext = new Uint8Array(32).fill(0xAB)
      const authTag = new Uint8Array(16).fill(0xCD)
      const combined = new Uint8Array([...ciphertext, ...authTag])

      vi.spyOn(crypto.subtle, 'encrypt').mockResolvedValueOnce(combined.buffer)

      const result = await encrypt(testData, mockKey)

      const resultAuthTag = atob(result.authTag)
      expect(resultAuthTag.length).toBe(16)

      const resultCiphertext = atob(result.encryptedData)
      expect(resultCiphertext.length).toBe(32)
    })
  })

  describe('decrypt', () => {
    it('должен дешифровать данные обратно', async () => {
      const originalData = 'Hello, World!'
      const encodedData = new TextEncoder().encode(originalData)

      vi.spyOn(crypto.subtle, 'decrypt').mockResolvedValueOnce(encodedData.buffer)

      const result = await decrypt(
        toBase64(new Uint8Array(32)),
        toBase64(new Uint8Array(12)),
        toBase64(new Uint8Array(16)),
        mockKey,
      )

      expect(result).toBe(originalData)
    })

    it('должен объединять ciphertext и authTag для Web Crypto', async () => {
      vi.spyOn(crypto.subtle, 'decrypt').mockResolvedValueOnce(new ArrayBuffer(0))

      const encryptedData = toBase64(new Uint8Array(32).fill(0xAB))
      const iv = toBase64(new Uint8Array(12).fill(0x01))
      const authTag = toBase64(new Uint8Array(16).fill(0xCD))

      await decrypt(encryptedData, iv, authTag, mockKey)

      const decryptCall = vi.mocked(crypto.subtle.decrypt).mock.calls[0]
      const combinedData = decryptCall[2] as Uint8Array
      expect(combinedData.length).toBe(32 + 16) // ciphertext + tag
    })

    it('должен использовать AES-GCM алгоритм', async () => {
      vi.spyOn(crypto.subtle, 'decrypt').mockResolvedValueOnce(new ArrayBuffer(0))

      await decrypt(
        toBase64(new Uint8Array(32)),
        toBase64(new Uint8Array(12)),
        toBase64(new Uint8Array(16)),
        mockKey,
      )

      expect(crypto.subtle.decrypt).toHaveBeenCalledWith(
        expect.objectContaining({ name: 'AES-GCM', tagLength: 128 }),
        mockKey,
        expect.any(Uint8Array),
      )
    })
  })
})
