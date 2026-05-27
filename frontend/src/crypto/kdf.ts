/**
 * КРИТИЧЕСКИЙ МОДУЛЬ: Key Derivation Function
 * Деривация ключа шифрования из мастер-пароля через PBKDF2
 * Ошибка здесь = компрометация данных пользователя
 */

import { fromBase64, encodeText } from './utils'

export interface KdfOptions {
  iterations?: number
  hash?: string
  keyLengthBits?: number
}

const DEFAULT_OPTIONS: Required<KdfOptions> = {
  iterations: 600_000,
  hash: 'SHA-256',
  keyLengthBits: 256,
}

/**
 * Деривирует ключ шифрования AES-256 из мастер-пароля
 * @param masterPassword - мастер-пароль пользователя (только в памяти!)
 * @param saltBase64 - соль в base64 (32 байта)
 * @param options - параметры KDF
 * @returns CryptoKey для AES-GCM
 */
export async function deriveEncryptionKey(
  masterPassword: string,
  saltBase64: string,
  options: KdfOptions = {},
): Promise<CryptoKey> {
  const opts = { ...DEFAULT_OPTIONS, ...options }
  const salt = fromBase64(saltBase64)
  const passwordBuffer = encodeText(masterPassword)

  // Импортируем пароль как raw key material
  const keyMaterial = await crypto.subtle.importKey(
    'raw',
    passwordBuffer,
    'PBKDF2',
    false,
    ['deriveBits', 'deriveKey'],
  )

  // Деривируем AES-GCM ключ
  const key = await crypto.subtle.deriveKey(
    {
      name: 'PBKDF2',
      salt,
      iterations: opts.iterations,
      hash: opts.hash,
    },
    keyMaterial,
    { name: 'AES-GCM', length: opts.keyLengthBits },
    true,
    ['encrypt', 'decrypt'],
  )

  return key
}

/**
 * Деривирует верификационный хэш для сервера
 * Использует отдельные параметры, чтобы серверный хэш не совпадал с ключом
 * @param masterPassword - мастер-пароль
 * @param saltBase64 - соль в base64
 * @returns верификационные биты в base64 (32 байта)
 */
export async function deriveVerifyHash(
  masterPassword: string,
  saltBase64: string,
): Promise<string> {
  const salt = fromBase64(saltBase64)
  // Добавляем контекст ':verify' для разделения домена
  const passwordWithContext = encodeText(masterPassword + ':verify')

  const keyMaterial = await crypto.subtle.importKey(
    'raw',
    passwordWithContext,
    'PBKDF2',
    false,
    ['deriveBits'],
  )

  const bits = await crypto.subtle.deriveBits(
    {
      name: 'PBKDF2',
      salt,
      iterations: 100_000,
      hash: 'SHA-256',
    },
    keyMaterial,
    256, // 32 байта
  )

  // Возвращаем как hex строку для сервера
  const bytes = new Uint8Array(bits)
  return Array.from(bytes)
    .map(b => b.toString(16).padStart(2, '0'))
    .join('')
}
