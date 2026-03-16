<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuth } from '../../composables/useAuth'
import { usePasswordGenerator } from '../../composables/usePasswordGenerator'
import { useUiStore } from '../../stores/ui'

const { register } = useAuth()
const { getStrength } = usePasswordGenerator()
const uiStore = useUiStore()

const email = ref('')
const masterPassword = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const isLoading = ref(false)

const strength = computed(() => getStrength(masterPassword.value))
const passwordsMatch = computed(() => masterPassword.value === confirmPassword.value)

async function handleSubmit() {
  if (!passwordsMatch.value) {
    uiStore.showToast('Passwords do not match', 'error')
    return
  }
  if (masterPassword.value.length < 12) {
    uiStore.showToast('Master password must be at least 12 characters', 'error')
    return
  }

  isLoading.value = true
  try {
    await register(email.value, masterPassword.value)
  } catch (error: any) {
    const message = error?.response?.data?.error ?? 'Registration failed'
    uiStore.showToast(message, 'error')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-xl shadow-lg">
      <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-900">PassFort</h1>
        <p class="mt-2 text-gray-600">Create your account</p>
      </div>

      <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 text-sm text-yellow-800">
        <strong>Important:</strong> Your master password cannot be recovered. Store it safely.
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input
            v-model="email"
            type="email"
            required
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Master Password</label>
          <div class="relative mt-1">
            <input
              v-model="masterPassword"
              :type="showPassword ? 'text' : 'password'"
              required
              minlength="12"
              class="block w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400"
            >
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
          <div v-if="masterPassword" class="mt-1">
            <span :class="strength.color" class="text-xs font-medium">
              Strength: {{ strength.label }}
            </span>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Confirm Master Password</label>
          <input
            v-model="confirmPassword"
            type="password"
            required
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            :class="{ 'border-red-500': confirmPassword && !passwordsMatch }"
          />
        </div>

        <button
          type="submit"
          :disabled="isLoading || !passwordsMatch"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isLoading ? 'Creating account...' : 'Create Account' }}
        </button>
      </form>

      <p class="text-center text-sm text-gray-600">
        Already have an account?
        <router-link to="/login" class="text-blue-600 hover:text-blue-500">Sign In</router-link>
      </p>
    </div>
  </div>
</template>
