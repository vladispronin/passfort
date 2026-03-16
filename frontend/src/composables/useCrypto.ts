import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { encrypt, decrypt } from '../crypto'

export function useCrypto() {
  const authStore = useAuthStore()
  const isReady = computed(() => authStore.encryptionKey !== null)

  async function encryptData(data: object): Promise<{ encryptedData: string; iv: string; authTag: string }> {
    if (!authStore.encryptionKey) {
      throw new Error('Vault is locked — encryption key not available')
    }
    return encrypt(JSON.stringify(data), authStore.encryptionKey)
  }

  async function decryptData<T = unknown>(encryptedData: string, iv: string, authTag: string): Promise<T> {
    if (!authStore.encryptionKey) {
      throw new Error('Vault is locked — encryption key not available')
    }
    const json = await decrypt(encryptedData, iv, authTag, authStore.encryptionKey)
    return JSON.parse(json) as T
  }

  return { isReady, encryptData, decryptData }
}
