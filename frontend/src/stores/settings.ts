import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSettingsStore = defineStore('settings', () => {
  const sessionUnlock = ref<boolean>(
    localStorage.getItem('setting_session_unlock') === 'true',
  )

  function setSessionUnlock(value: boolean): void {
    sessionUnlock.value = value
    localStorage.setItem('setting_session_unlock', String(value))
    if (!value) sessionStorage.removeItem('session_encryption_key')
  }

  return { sessionUnlock, setSessionUnlock }
})
