import { ref } from 'vue'

export interface PasswordOptions {
  length: number
  uppercase: boolean
  lowercase: boolean
  numbers: boolean
  symbols: boolean
}

const DEFAULT_OPTIONS: PasswordOptions = {
  length: 20,
  uppercase: true,
  lowercase: true,
  numbers: true,
  symbols: true,
}

const CHARS = {
  uppercase: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
  lowercase: 'abcdefghijklmnopqrstuvwxyz',
  numbers: '0123456789',
  symbols: '!@#$%^&*()_+-=[]{}|;:,.<>?',
}

export function usePasswordGenerator() {
  const options = ref<PasswordOptions>({ ...DEFAULT_OPTIONS })
  const generatedPassword = ref('')

  function generate(): string {
    let charset = ''
    if (options.value.uppercase) charset += CHARS.uppercase
    if (options.value.lowercase) charset += CHARS.lowercase
    if (options.value.numbers) charset += CHARS.numbers
    if (options.value.symbols) charset += CHARS.symbols

    if (!charset) charset = CHARS.lowercase + CHARS.numbers

    const bytes = new Uint8Array(options.value.length)
    crypto.getRandomValues(bytes)

    let password = ''
    for (let i = 0; i < options.value.length; i++) {
      password += charset[bytes[i] % charset.length]
    }

    generatedPassword.value = password
    return password
  }

  function getStrength(password: string): { score: number; label: string; color: string } {
    let score = 0
    if (password.length >= 8) score++
    if (password.length >= 16) score++
    if (/[A-Z]/.test(password)) score++
    if (/[a-z]/.test(password)) score++
    if (/[0-9]/.test(password)) score++
    if (/[^A-Za-z0-9]/.test(password)) score++

    if (score <= 2) return { score, label: 'Weak', color: 'text-red-500' }
    if (score <= 4) return { score, label: 'Fair', color: 'text-yellow-500' }
    if (score <= 5) return { score, label: 'Strong', color: 'text-blue-500' }
    return { score, label: 'Very Strong', color: 'text-green-500' }
  }

  return { options, generatedPassword, generate, getStrength }
}
