import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// Мокируем все внешние зависимости
vi.mock('../../../src/api/vault', () => ({
  vaultApi: { list: vi.fn() },
}))
vi.mock('../../../src/api/vaultItems', () => ({
  vaultItemsApi: { list: vi.fn() },
}))
vi.mock('../../../src/api/user', () => ({
  userApi: { changeMasterPassword: vi.fn() },
}))
vi.mock('../../../src/api/auth', () => ({
  authApi: { getMe: vi.fn() },
}))
vi.mock('../../../src/crypto', () => ({
  deriveVerifyHash: vi.fn(),
  deriveEncryptionKey: vi.fn(),
  generateSalt: vi.fn(),
  encrypt: vi.fn(),
  decrypt: vi.fn(),
}))

import { vaultApi } from '../../../src/api/vault'
import { vaultItemsApi } from '../../../src/api/vaultItems'
import { userApi } from '../../../src/api/user'
import { authApi } from '../../../src/api/auth'
import { deriveVerifyHash, deriveEncryptionKey, generateSalt, encrypt, decrypt } from '../../../src/crypto'
import { useMasterPasswordChange } from '../../../src/composables/useMasterPasswordChange'
import { useAuthStore } from '../../../src/stores/auth'

const fakeEncryptionKey = {} as CryptoKey
const fakeNewKey = {} as CryptoKey
const fakeTokens = {
  access_token: 'new_access',
  refresh_token: 'new_refresh',
  token_type: 'Bearer',
  expires_in: 900,
}
const fakeItem = {
  id: 'item-uuid-1',
  encryptedData: 'enc',
  iv: 'iviviviviviviv==',
  authTag: 'tagtagtagtagtagta',
  itemType: 'login' as const,
  titleHint: 'Test',
  isFavorite: false,
  categoryId: null,
  vaultId: 'vault-1',
  createdAt: '',
  updatedAt: '',
}
const fakeProfile = {
  id: 'user-1',
  email: 'test@example.com',
  salt: 'newSalt',
  kdfParams: { algorithm: 'PBKDF2', iterations: 600000, hash: 'SHA-256', keyLength: 256 },
  createdAt: '',
}

describe('useMasterPasswordChange', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('успешно меняет мастер-пароль', async () => {
    // Настраиваем auth store
    const authStore = useAuthStore()
    authStore.setEncryptionKey(fakeEncryptionKey, 'currentSalt')
    authStore.setTokens('old_access', 'old_refresh')
    authStore.setMasterPasswordHash('currentVerifyHash')

    // Мокируем криптофункции
    vi.mocked(deriveVerifyHash).mockResolvedValueOnce('currentVerifyHash') // верификация текущего
    vi.mocked(deriveVerifyHash).mockResolvedValueOnce('newVerifyHash')      // новый хэш
    vi.mocked(deriveEncryptionKey).mockResolvedValue(fakeNewKey)
    vi.mocked(generateSalt).mockReturnValue('newSalt44chars============================')
    vi.mocked(decrypt).mockResolvedValue('{"username":"test"}')
    vi.mocked(encrypt).mockResolvedValue({
      encryptedData: 'newEnc',
      iv: 'iviviviviviviv==',
      authTag: 'tagtagtagtagtagta',
    })

    // Мокируем API
    vi.mocked(vaultApi.list).mockResolvedValue([{ id: 'vault-1', name: 'Test', createdAt: '', updatedAt: '' }])
    vi.mocked(vaultItemsApi.list).mockResolvedValue([fakeItem])
    vi.mocked(userApi.changeMasterPassword).mockResolvedValue(fakeTokens)
    vi.mocked(authApi.getMe).mockResolvedValue(fakeProfile)

    const { changeMasterPassword, isLoading, error, progress } = useMasterPasswordChange()

    await changeMasterPassword('currentPassword', 'newPassword')

    expect(isLoading.value).toBe(false)
    expect(error.value).toBeNull()

    // Проверяем что состояние обновилось
    expect(authStore.userSalt).toBe('newSalt44chars============================')
    expect(authStore.accessToken).toBe('new_access')

    // API должны быть вызваны
    expect(userApi.changeMasterPassword).toHaveBeenCalledOnce()
    expect(authApi.getMe).toHaveBeenCalledOnce()

    // Прогресс должен завершиться
    expect(progress.value.current).toBe(1)
    expect(progress.value.total).toBe(1)
  })

  it('устанавливает ошибку при неверном текущем пароле (локально)', async () => {
    const authStore = useAuthStore()
    authStore.setEncryptionKey(fakeEncryptionKey, 'currentSalt')
    authStore.setMasterPasswordHash('correctHash')

    // Деривированный хэш не совпадает с сохранённым
    vi.mocked(deriveVerifyHash).mockResolvedValueOnce('wrongHash')

    const { changeMasterPassword, error, isLoading } = useMasterPasswordChange()

    await expect(changeMasterPassword('wrongPassword', 'newPassword')).rejects.toThrow(
      'Неверный текущий мастер-пароль',
    )

    expect(error.value).toBe('Неверный текущий мастер-пароль')
    expect(isLoading.value).toBe(false)

    // API не должен вызываться
    expect(userApi.changeMasterPassword).not.toHaveBeenCalled()
  })

  it('обновляет прогресс для каждой записи', async () => {
    const authStore = useAuthStore()
    authStore.setEncryptionKey(fakeEncryptionKey, 'currentSalt')
    authStore.setMasterPasswordHash('hash')

    vi.mocked(deriveVerifyHash).mockResolvedValue('hash')
    vi.mocked(deriveEncryptionKey).mockResolvedValue(fakeNewKey)
    vi.mocked(generateSalt).mockReturnValue('newSalt44chars============================')
    vi.mocked(decrypt).mockResolvedValue('{}')
    vi.mocked(encrypt).mockResolvedValue({ encryptedData: 'enc', iv: 'iviv', authTag: 'tag' })
    vi.mocked(vaultApi.list).mockResolvedValue([{ id: 'v1', name: 'V1', createdAt: '', updatedAt: '' }])
    vi.mocked(vaultItemsApi.list).mockResolvedValue([fakeItem, { ...fakeItem, id: 'item-2' }])
    vi.mocked(userApi.changeMasterPassword).mockResolvedValue(fakeTokens)
    vi.mocked(authApi.getMe).mockResolvedValue(fakeProfile)

    const { changeMasterPassword, progress } = useMasterPasswordChange()

    await changeMasterPassword('currentPassword', 'newPassword')

    expect(progress.value.total).toBe(2)
    expect(progress.value.current).toBe(2)
  })

  it('устанавливает ошибку при сетевой ошибке', async () => {
    const authStore = useAuthStore()
    authStore.setEncryptionKey(fakeEncryptionKey, 'currentSalt')
    authStore.setMasterPasswordHash('hash')

    vi.mocked(deriveVerifyHash).mockResolvedValue('hash')
    vi.mocked(deriveEncryptionKey).mockResolvedValue(fakeNewKey)
    vi.mocked(generateSalt).mockReturnValue('newSalt44chars============================')
    vi.mocked(vaultApi.list).mockResolvedValue([{ id: 'v1', name: 'V1', createdAt: '', updatedAt: '' }])
    vi.mocked(vaultItemsApi.list).mockResolvedValue([])
    vi.mocked(userApi.changeMasterPassword).mockRejectedValue(new Error('Network error'))

    const { changeMasterPassword, error, isLoading } = useMasterPasswordChange()

    await expect(changeMasterPassword('currentPassword', 'newPassword')).rejects.toThrow(
      'Network error',
    )

    expect(error.value).toBe('Network error')
    expect(isLoading.value).toBe(false)
  })
})
