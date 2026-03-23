import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { UserProfile } from '../types/auth'

export const useAuthStore = defineStore('auth', () => {
  // JWT токены в памяти (access_token НЕ в localStorage для безопасности)
  // refresh_token в localStorage для персистентности
  const accessToken = ref<string | null>(null)
  const user = ref<UserProfile | null>(null)

  // Ключ шифрования ТОЛЬКО в памяти — никогда не сохраняется на диск
  const encryptionKey = ref<CryptoKey | null>(null)
  const userSalt = ref<string | null>(null)

  const isAuthenticated = computed(() => accessToken.value !== null)
  const isUnlocked = computed(() => encryptionKey.value !== null)

  function setTokens(access: string, refresh: string): void {
    accessToken.value = access
    localStorage.setItem('access_token', access)
    localStorage.setItem('refresh_token', refresh)
  }

  function setEncryptionKey(key: CryptoKey, salt: string): void {
    encryptionKey.value = key
    userSalt.value = salt
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

  function clearAuth(): void {
    accessToken.value = null
    user.value = null
    encryptionKey.value = null
    userSalt.value = null
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    localStorage.removeItem('master_password_hash')
  }

  function lockVault(): void {
    // Очищаем ключ шифрования из памяти
    encryptionKey.value = null
  }

  // Восстанавливаем токен из localStorage при инициализации
  function initFromStorage(): void {
    const storedToken = localStorage.getItem('access_token')
    if (storedToken) {
      accessToken.value = storedToken
    }
  }

  return {
    accessToken,
    user,
    encryptionKey,
    userSalt,
    isAuthenticated,
    isUnlocked,
    setTokens,
    setEncryptionKey,
    setUser,
    setMasterPasswordHash,
    getMasterPasswordHash,
    clearAuth,
    lockVault,
    initFromStorage,
  }
})
