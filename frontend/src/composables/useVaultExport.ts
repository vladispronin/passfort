import { ref } from 'vue'
import { vaultApi, type VaultExportData } from '../api/vault'

export function useVaultExport() {
  const isExporting = ref(false)
  const isImporting = ref(false)

  async function exportVault(vaultId: string, vaultName: string): Promise<void> {
    isExporting.value = true
    try {
      const exportData = await vaultApi.exportVault(vaultId)
      const json = JSON.stringify(exportData, null, 2)
      const blob = new Blob([json], { type: 'application/json' })
      const url = URL.createObjectURL(blob)

      const link = document.createElement('a')
      link.href = url
      const date = new Date().toISOString().slice(0, 10)
      link.download = `passfort-${vaultName.replace(/[^a-z0-9]/gi, '_').toLowerCase()}-${date}.json`
      link.click()

      URL.revokeObjectURL(url)
    } finally {
      isExporting.value = false
    }
  }

  async function importVault(vaultId: string, file: File): Promise<{ categories: number; items: number }> {
    isImporting.value = true
    try {
      const text = await file.text()
      const payload = JSON.parse(text) as VaultExportData

      if (!Array.isArray(payload.items)) {
        throw new Error('Неверный формат файла: отсутствует поле items')
      }

      const result = await vaultApi.importVault(vaultId, payload)
      return result.imported
    } finally {
      isImporting.value = false
    }
  }

  return { isExporting, isImporting, exportVault, importVault }
}
