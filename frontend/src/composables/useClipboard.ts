import { ref } from 'vue'

const AUTO_CLEAR_TIMEOUT = 30_000 // 30 секунд

export function useClipboard() {
  const isCopied = ref(false)
  let clearTimer: ReturnType<typeof setTimeout> | null = null

  async function copy(text: string, autoClear = true): Promise<void> {
    await navigator.clipboard.writeText(text)
    isCopied.value = true

    if (autoClear) {
      if (clearTimer) clearTimeout(clearTimer)
      // Автоматически очищаем буфер обмена через 30 сек
      clearTimer = setTimeout(async () => {
        await navigator.clipboard.writeText('')
        isCopied.value = false
      }, AUTO_CLEAR_TIMEOUT)
    }

    // Визуальный сброс через 2 секунды
    setTimeout(() => {
      isCopied.value = false
    }, 2000)
  }

  function cleanup(): void {
    if (clearTimer) {
      clearTimeout(clearTimer)
      clearTimer = null
    }
  }

  return { isCopied, copy, cleanup }
}
