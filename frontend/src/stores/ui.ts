import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: string
  type: 'success' | 'error' | 'warning' | 'info'
  message: string
  duration?: number
}

export const useUiStore = defineStore('ui', () => {
  const isLoading = ref(false)
  const toasts = ref<Toast[]>([])
  const modals = ref<Record<string, boolean>>({})

  function showToast(
    message: string,
    type: Toast['type'] = 'info',
    duration = 3000,
  ): void {
    const id = crypto.randomUUID()
    toasts.value.push({ id, type, message, duration })
    if (duration > 0) {
      setTimeout(() => removeToast(id), duration)
    }
  }

  function removeToast(id: string): void {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  function openModal(name: string): void {
    modals.value[name] = true
  }

  function closeModal(name: string): void {
    modals.value[name] = false
  }

  function isModalOpen(name: string): boolean {
    return modals.value[name] ?? false
  }

  return {
    isLoading,
    toasts,
    modals,
    showToast,
    removeToast,
    openModal,
    closeModal,
    isModalOpen,
  }
})
