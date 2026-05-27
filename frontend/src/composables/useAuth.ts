import { useAuthStore } from '../stores/auth'
import { useVaultStore } from '../stores/vault'
import { useVaultItemsStore } from '../stores/vaultItems'
import { authApi } from '../api/auth'
import { twoFactorApi } from '../api/twoFactor'
import { deriveEncryptionKey, deriveVerifyHash, generateSalt } from '../crypto'
import { useUiStore } from '../stores/ui'
import { useRouter } from 'vue-router'
import type { AuthTokens } from '../types/auth'

export function useAuth() {
  const authStore = useAuthStore()
  const vaultStore = useVaultStore()
  const itemsStore = useVaultItemsStore()
  const uiStore = useUiStore()
  const router = useRouter()

  async function register(email: string, masterPassword: string): Promise<void> {
    const salt = generateSalt()

    const masterPasswordHash = await deriveVerifyHash(masterPassword, salt)

    await authApi.register({
      email,
      masterPasswordHash,
      salt,
      kdfParams: {
        algorithm: 'PBKDF2',
        iterations: 600_000,
        hash: 'SHA-256',
        keyLength: 256,
      },
    })

    uiStore.showToast('Аккаунт успешно создан! Войдите в систему.', 'success')
    await router.push('/login')
  }

  async function completeLogin(tokens: AuthTokens, masterPassword: string, salt: string): Promise<void> {
    authStore.setTokens(tokens.access_token, tokens.refresh_token)

    const masterPasswordHash = await deriveVerifyHash(masterPassword, salt)
    authStore.setMasterPasswordHash(masterPasswordHash)

    const profile = await authApi.getMe()
    authStore.setUser(profile)

    const encKey = await deriveEncryptionKey(masterPassword, salt)
    await authStore.setEncryptionKey(encKey, salt)

    await router.push('/vault')
  }

  async function login(email: string, masterPassword: string): Promise<void> {
    // Получаем параметры KDF
    const kdfParams = await authApi.getKdfParams(email)

    // Деривируем верификационный хэш
    const masterPasswordHash = await deriveVerifyHash(masterPassword, kdfParams.salt)

    // Логинимся
    const result = await authApi.login({ email, masterPasswordHash })

    // Если требуется 2FA — перенаправляем на страницу ввода кода
    if ('requires_2fa' in result && result.requires_2fa) {
      authStore.setPendingTwoFactor({
        tempToken: result.temp_token,
        email,
        masterPasswordHash,
        masterPassword,
      })
      await router.push('/two-factor')
      return
    }

    await completeLogin(result as AuthTokens, masterPassword, kdfParams.salt)
  }

  async function verifyTwoFactor(code: string): Promise<void> {
    const pending = authStore.pendingTwoFactor
    if (!pending) {
      throw new Error('No pending 2FA session')
    }

    const tokens = await twoFactorApi.verifyLogin({
      tempToken: pending.tempToken,
      code,
    })

    authStore.clearPendingTwoFactor()

    // Получаем salt для деривации ключа шифрования
    const kdfParams = await authApi.getKdfParams(pending.email)
    await completeLogin(tokens, pending.masterPassword, kdfParams.salt)
  }

  async function unlock(masterPassword: string): Promise<void> {
    const authStore = useAuthStore()

    // После перезагрузки страницы user может быть null, загружаем профиль
    if (!authStore.user) {
      const profile = await authApi.getMe()
      authStore.setUser(profile)
    }

    // Верифицируем мастер-пароль перед разблокировкой
    const storedHash = authStore.getMasterPasswordHash()
    if (!storedHash) {
      // Хэш не сохранён — требуем повторный логин
      authStore.clearAuth()
      await router.push('/login')
      uiStore.showToast('Сессия истекла, войдите снова', 'info')
      return
    }

    const derivedHash = await deriveVerifyHash(masterPassword, authStore.user!.salt)
    if (derivedHash !== storedHash) {
      throw new Error('Invalid master password')
    }

    const encKey = await deriveEncryptionKey(masterPassword, authStore.user!.salt)
    await authStore.setEncryptionKey(encKey, authStore.user!.salt)

    await router.push('/vault')
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } catch {
      // Игнорируем ошибки при логауте
    }

    authStore.clearAuth()
    vaultStore.reset()
    itemsStore.reset()

    await router.push('/login')
  }

  return { register, login, unlock, logout, verifyTwoFactor }
}
