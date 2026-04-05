import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useVaultItemsStore } from '../stores/vaultItems'
import { deriveEncryptionKey, deriveVerifyHash, generateSalt, encrypt, decrypt } from '../crypto'
import { vaultItemsApi } from '../api/vaultItems'
import { vaultApi } from '../api/vault'
import { userApi } from '../api/user'
import { authApi } from '../api/auth'

export function useMasterPasswordChange() {
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const progress = ref({ current: 0, total: 0 })

  const authStore = useAuthStore()
  const itemsStore = useVaultItemsStore()

  async function changeMasterPassword(currentPassword: string, newPassword: string): Promise<void> {
    isLoading.value = true
    error.value = null
    progress.value = { current: 0, total: 0 }

    try {
      // 1. Локальная верификация текущего пароля
      const currentVerifyHash = await deriveVerifyHash(currentPassword, authStore.userSalt!)
      const storedHash = authStore.getMasterPasswordHash()
      if (storedHash && currentVerifyHash !== storedHash) {
        throw new Error('Неверный текущий мастер-пароль')
      }

      // 2. Новые криптоматериалы
      const newSalt = generateSalt()
      const [newVerifyHash, newEncryptionKey] = await Promise.all([
        deriveVerifyHash(newPassword, newSalt),
        deriveEncryptionKey(newPassword, newSalt),
      ])

      // 3. Загрузка всех записей из всех vault'ов
      const vaults = await vaultApi.list()
      const allItems = (
        await Promise.all(
          vaults.map(async (v) => {
            const collected = []
            let page = 1
            let pages = 1
            do {
              const result = await vaultItemsApi.list(v.id, { page, limit: 100 })
              collected.push(...result.items)
              pages = result.meta.pages
              page++
            } while (page <= pages)
            return collected
          }),
        )
      ).flat()
      progress.value.total = allItems.length

      // 4. Перешифровывание каждой записи
      const reEncryptedItems = []
      for (const item of allItems) {
        const plaintext = await decrypt(
          item.encryptedData,
          item.iv,
          item.authTag,
          authStore.encryptionKey!,
        )
        const reEncrypted = await encrypt(plaintext, newEncryptionKey)
        reEncryptedItems.push({
          id: item.id,
          encryptedData: reEncrypted.encryptedData,
          iv: reEncrypted.iv,
          authTag: reEncrypted.authTag,
        })
        progress.value.current++
      }

      // 5. Отправка на сервер
      const tokens = await userApi.changeMasterPassword({
        currentMasterPasswordHash: currentVerifyHash,
        newMasterPasswordHash: newVerifyHash,
        newSalt,
        newKdfParams: {
          algorithm: 'PBKDF2',
          iterations: 600_000,
          hash: 'SHA-256',
          keyLength: 256,
        },
        items: reEncryptedItems,
      })

      // 6. Обновление состояния (синхронно после успеха сервера)
      authStore.setTokens(tokens.access_token, tokens.refresh_token)
      authStore.setEncryptionKey(newEncryptionKey, newSalt)
      authStore.setMasterPasswordHash(newVerifyHash)

      // Обновляем профиль (содержит новый salt)
      const profile = await authApi.getMe()
      authStore.setUser(profile)

      // Сбрасываем кэш расшифрованных записей — будут перечитаны с новым ключом
      itemsStore.reset()
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка смены мастер-пароля'
      throw e
    } finally {
      isLoading.value = false
    }
  }

  return { isLoading, error, progress, changeMasterPassword }
}
