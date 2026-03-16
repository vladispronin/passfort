<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'

const router = useRouter()
const vaultStore = useVaultStore()
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <header class="bg-white shadow-sm">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
        <button @click="router.back()" class="text-gray-600">&#8592; Back</button>
        <h2 class="text-lg font-semibold">Vault Settings</h2>
      </div>
    </header>
    <main class="max-w-2xl mx-auto px-4 py-6">
      <div class="bg-white rounded-xl shadow-sm p-6">
        <p class="text-gray-500">Vault: {{ vaultStore.currentVault?.name }}</p>
      </div>
    </main>
  </div>
</template>
