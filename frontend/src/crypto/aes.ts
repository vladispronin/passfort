/**
 * КРИТИЧЕСКИЙ МОДУЛЬ: AES-256-GCM шифрование/дешифрование
 * Все данные хранилища шифруются/дешифруются этим модулем
 */

import { toBase64, fromBase64, randomBytes, encodeText, decodeText } from './utils'
import type { EncryptResult } from '../types/crypto'

const IV_LENGTH = 12   // 96 бит для GCM
const TAG_LENGTH = 128 // 128 бит auth tag

/**
 * Шифрует строку данных с помощью AES-256-GCM
 * @param data - данные для шифрования (JSON строка)
 * @param key - ключ AES-256 (из deriveEncryptionKey)
 * @returns зашифрованные данные с IV и auth tag в base64
 */
export async function encrypt(data: string, key: CryptoKey): Promise<EncryptResult> {
  const iv = randomBytes(IV_LENGTH)
  const encodedData = encodeText(data)

  // AES-GCM возвращает ciphertext + auth tag (последние 16 байт)
  const encryptedBuffer = await crypto.subtle.encrypt(
    {
      name: 'AES-GCM',
      iv,
      tagLength: TAG_LENGTH,
    },
    key,
    encodedData,
  )

  const encryptedBytes = new Uint8Array(encryptedBuffer)
  const ciphertext = encryptedBytes.slice(0, encryptedBytes.length - 16)
  const authTag = encryptedBytes.slice(encryptedBytes.length - 16)

  return {
    encryptedData: toBase64(ciphertext),
    iv: toBase64(iv),
    authTag: toBase64(authTag),
  }
}

/**
 * Дешифрует данные AES-256-GCM
 * @param encryptedData - зашифрованные данные в base64
 * @param iv - вектор инициализации в base64 (12 байт)
 * @param authTag - тег аутентификации в base64 (16 байт)
 * @param key - ключ AES-256
 * @returns расшифрованная строка
 */
export async function decrypt(
  encryptedData: string,
  iv: string,
  authTag: string,
  key: CryptoKey,
): Promise<string> {
  const ciphertext = fromBase64(encryptedData)
  const ivBytes = fromBase64(iv)
  const authTagBytes = fromBase64(authTag)

  // Объединяем ciphertext + authTag (Web Crypto ожидает их вместе)
  const combined = new Uint8Array(ciphertext.length + authTagBytes.length)
  combined.set(ciphertext)
  combined.set(authTagBytes, ciphertext.length)

  const decryptedBuffer = await crypto.subtle.decrypt(
    {
      name: 'AES-GCM',
      iv: ivBytes,
      tagLength: TAG_LENGTH,
    },
    key,
    combined,
  )

  return decodeText(new Uint8Array(decryptedBuffer))
}
