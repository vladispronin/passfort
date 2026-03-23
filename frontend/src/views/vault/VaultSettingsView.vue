<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'

const router = useRouter()
const vaultStore = useVaultStore()
</script>

<template>
  <div class="min-h-screen bg-[#eef2f8]">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
        <button @click="router.back()" class="text-slate-600">&#8592; Back</button>
        <h2 class="text-lg font-semibold">Vault Settings</h2>
      </div>
    </header>
    <main class="max-w-2xl mx-auto px-4 py-6">
      <div class="bg-white rounded-xl shadow-sm p-6">
        <p class="text-slate-500">Vault: {{ vaultStore.currentVault?.name }}</p>
      </div>
    </main>
  </div>
</template>
