<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <header class="bg-white shadow-sm">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
        <button @click="router.back()" class="text-gray-600">&#8592; Back</button>
        <h2 class="text-lg font-semibold">Profile</h2>
      </div>
    </header>
    <main class="max-w-2xl mx-auto px-4 py-6">
      <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
        <div>
          <label class="text-sm text-gray-500">Email</label>
          <p class="font-medium">{{ authStore.user?.email }}</p>
        </div>
        <div>
          <label class="text-sm text-gray-500">Account Created</label>
          <p class="font-medium">{{ authStore.user?.createdAt ? new Date(authStore.user.createdAt).toLocaleDateString() : '—' }}</p>
        </div>
        <div class="pt-4 border-t">
          <p class="text-xs text-gray-400">
            Zero-knowledge architecture: your data is encrypted with your master password.
            PassFort servers never see your unencrypted data.
          </p>
        </div>
      </div>
    </main>
  </div>
</template>
