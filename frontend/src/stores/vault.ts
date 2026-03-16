import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { vaultApi } from '../api/vault'
import type { Vault } from '../types/vault'

export const useVaultStore = defineStore('vault', () => {
  const vaults = ref<Vault[]>([])
  const currentVaultId = ref<string | null>(null)
  const isLoading = ref(false)

  const currentVault = computed(() =>
    vaults.value.find((v) => v.id === currentVaultId.value) ?? null,
  )

  async function loadVaults(): Promise<void> {
    isLoading.value = true
    try {
      vaults.value = await vaultApi.list()
      if (vaults.value.length > 0 && !currentVaultId.value) {
        currentVaultId.value = vaults.value[0].id
      }
    } finally {
      isLoading.value = false
    }
  }

  async function createVault(name: string): Promise<Vault> {
    const vault = await vaultApi.create(name)
    vaults.value.push(vault)
    return vault
  }

  async function deleteVault(id: string): Promise<void> {
    await vaultApi.delete(id)
    vaults.value = vaults.value.filter((v) => v.id !== id)
    if (currentVaultId.value === id) {
      currentVaultId.value = vaults.value[0]?.id ?? null
    }
  }

  function setCurrentVault(id: string): void {
    currentVaultId.value = id
  }

  function reset(): void {
    vaults.value = []
    currentVaultId.value = null
  }

  return {
    vaults,
    currentVaultId,
    currentVault,
    isLoading,
    loadVaults,
    createVault,
    deleteVault,
    setCurrentVault,
    reset,
  }
})
