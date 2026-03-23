import { useAuthStore } from '../stores/auth'
import { useVaultStore } from '../stores/vault'
import { useVaultItemsStore } from '../stores/vaultItems'
import { authApi } from '../api/auth'
import { deriveEncryptionKey, deriveVerifyHash, generateSalt } from '../crypto'
import { useUiStore } from '../stores/ui'
import { useRouter } from 'vue-router'

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

    uiStore.showToast('Account created successfully! Please log in.', 'success')
    await router.push('/login')
  }

  async function login(email: string, masterPassword: string): Promise<void> {
    // Получаем параметры KDF
    const kdfParams = await authApi.getKdfParams(email)

    // Деривируем верификационный хэш
    const masterPasswordHash = await deriveVerifyHash(masterPassword, kdfParams.salt)

    // Логинимся
    const tokens = await authApi.login({ email, masterPasswordHash })
    authStore.setTokens(tokens.access_token, tokens.refresh_token)

    // Сохраняем хэш для верификации при последующей разблокировке
    authStore.setMasterPasswordHash(masterPasswordHash)

    // Загружаем профиль
    const profile = await authApi.getMe()
    authStore.setUser(profile)

    // Деривируем ключ шифрования (остаётся только в памяти!)
    const encKey = await deriveEncryptionKey(masterPassword, kdfParams.salt)
    authStore.setEncryptionKey(encKey, kdfParams.salt)

    await router.push('/vault')
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
      uiStore.showToast('Session expired, please log in again', 'info')
      return
    }

    const derivedHash = await deriveVerifyHash(masterPassword, authStore.user!.salt)
    if (derivedHash !== storedHash) {
      throw new Error('Invalid master password')
    }

    const encKey = await deriveEncryptionKey(masterPassword, authStore.user!.salt)
    authStore.setEncryptionKey(encKey, authStore.user!.salt)

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

  return { register, login, unlock, logout }
}
