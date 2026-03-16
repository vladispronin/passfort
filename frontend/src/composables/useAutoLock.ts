import { onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const LOCK_TIMEOUT = 15 * 60 * 1000 // 15 минут

export function useAutoLock() {
  const authStore = useAuthStore()
  const router = useRouter()

  let lockTimer: ReturnType<typeof setTimeout> | null = null

  function resetTimer(): void {
    if (lockTimer) clearTimeout(lockTimer)
    if (!authStore.isUnlocked) return

    lockTimer = setTimeout(() => {
      authStore.lockVault()
      router.push('/unlock')
    }, LOCK_TIMEOUT)
  }

  const events = ['mousedown', 'keydown', 'scroll', 'touchstart']

  onMounted(() => {
    resetTimer()
    events.forEach((e) => document.addEventListener(e, resetTimer, { passive: true }))
  })

  onUnmounted(() => {
    if (lockTimer) clearTimeout(lockTimer)
    events.forEach((e) => document.removeEventListener(e, resetTimer))
  })
}
