import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { UserProfile } from '../types/auth'
import { useSettingsStore } from './settings'

interface PendingTwoFactor {
  tempToken: string
  email: string
  masterPasswordHash: string
  masterPassword: string
}

export const useAuthStore = defineStore('auth', () => {
  // JWT токены в памяти (access_token НЕ в localStorage для безопасности)
  // refresh_token в localStorage для персистентности
  const accessToken = ref<string | null>(null)
  const user = ref<UserProfile | null>(null)

  // Ключ шифрования ТОЛЬКО в памяти — никогда не сохраняется на диск
  const encryptionKey = ref<CryptoKey | null>(null)
  const userSalt = ref<string | null>(null)

  // Состояние ожидания 2FA верификации (только в памяти)
  const pendingTwoFactor = ref<PendingTwoFactor | null>(null)

  const isAuthenticated = computed(() => accessToken.value !== null)
  const isUnlocked = computed(() => encryptionKey.value !== null)
  const requiresTwoFactor = computed(() => pendingTwoFactor.value !== null)

  function setTokens(access: string, refresh: string): void {
    accessToken.value = access
    localStorage.setItem('access_token', access)
    localStorage.setItem('refresh_token', refresh)
  }

  async function setEncryptionKey(key: CryptoKey, salt: string): Promise<void> {
    encryptionKey.value = key
    userSalt.value = salt

    const settingsStore = useSettingsStore()
    if (settingsStore.sessionUnlock) {
      const rawKey = await crypto.subtle.exportKey('raw', key)
      const b64 = btoa(String.fromCharCode(...new Uint8Array(rawKey)))
      sessionStorage.setItem('session_encryption_key', b64)
    }
  }

  function setUser(profile: UserProfile): void {
    user.value = profile
  }

  function setMasterPasswordHash(hash: string): void {
    localStorage.setItem('master_password_hash', hash)
  }

  function getMasterPasswordHash(): string | null {
    return localStorage.getItem('master_password_hash')
  }

  function setPendingTwoFactor(data: PendingTwoFactor): void {
    pendingTwoFactor.value = data
  }

  function clearPendingTwoFactor(): void {
    pendingTwoFactor.value = null
  }

  function clearAuth(): void {
    accessToken.value = null
    user.value = null
    encryptionKey.value = null
    userSalt.value = null
    pendingTwoFactor.value = null
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    localStorage.removeItem('master_password_hash')
    sessionStorage.removeItem('session_encryption_key')
  }

  async function saveKeyToSession(): Promise<void> {
    if (!encryptionKey.value) return
    const rawKey = await crypto.subtle.exportKey('raw', encryptionKey.value)
    const b64 = btoa(String.fromCharCode(...new Uint8Array(rawKey)))
    sessionStorage.setItem('session_encryption_key', b64)
  }

  function lockVault(): void {
    encryptionKey.value = null
    sessionStorage.removeItem('session_encryption_key')
  }

  async function initFromStorage(): Promise<void> {
    const storedToken = localStorage.getItem('access_token')
    if (storedToken) {
      accessToken.value = storedToken
    }

    const settingsStore = useSettingsStore()
    if (settingsStore.sessionUnlock && encryptionKey.value === null) {
      const b64 = sessionStorage.getItem('session_encryption_key')
      if (b64) {
        try {
          const raw = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))
          const key = await crypto.subtle.importKey(
            'raw',
            raw,
            { name: 'AES-GCM', length: 256 },
            true,
            ['encrypt', 'decrypt'],
          )
          encryptionKey.value = key
        } catch {
          sessionStorage.removeItem('session_encryption_key')
        }
      }
    }
  }

  return {
    accessToken,
    user,
    encryptionKey,
    userSalt,
    pendingTwoFactor,
    isAuthenticated,
    isUnlocked,
    requiresTwoFactor,
    setTokens,
    setEncryptionKey,
    setUser,
    setMasterPasswordHash,
    getMasterPasswordHash,
    setPendingTwoFactor,
    clearPendingTwoFactor,
    clearAuth,
    saveKeyToSession,
    lockVault,
    initFromStorage,
  }
})
