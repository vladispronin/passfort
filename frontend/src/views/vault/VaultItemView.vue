<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useVaultStore } from '../../stores/vault'
import { useVaultItemsStore } from '../../stores/vaultItems'
import { useCategoriesStore } from '../../stores/categories'
import { useUiStore } from '../../stores/ui'
import { useClipboard } from '../../composables/useClipboard'
import type { DecryptedItemData, ItemType } from '../../types/vault'

const route = useRoute()
const router = useRouter()
const vaultStore = useVaultStore()
const itemsStore = useVaultItemsStore()
const categoriesStore = useCategoriesStore()
const uiStore = useUiStore()
const { copy } = useClipboard()

const isNew = computed(() => route.params.id === 'new')
const isLoading = ref(false)
const isDecrypting = ref(false)

const form = ref({
  titleHint: '',
  itemType: 'login' as ItemType,
  username: '',
  password: '',
  url: '',
  notes: '',
  categoryId: null as string | null,
})

const showPassword = ref(false)

onMounted(async () => {
  if (vaultStore.currentVaultId && categoriesStore.categories.length === 0) {
    await categoriesStore.loadCategories(vaultStore.currentVaultId).catch(() => {})
  }

  if (!isNew.value) {
    const item = itemsStore.items.find((i) => i.id === route.params.id)
    if (!item) {
      router.push('/vault')
      return
    }

    isDecrypting.value = true
    try {
      const data = await itemsStore.decryptItem(item)
      form.value = {
        titleHint: item.titleHint,
        itemType: item.itemType,
        username: data.username ?? '',
        password: data.password ?? '',
        url: data.url ?? '',
        notes: data.notes ?? '',
        categoryId: item.categoryId,
      }
    } catch {
      uiStore.showToast('Не удалось расшифровать запись', 'error')
    } finally {
      isDecrypting.value = false
    }
  }
})

async function handleSave() {
  if (!vaultStore.currentVaultId) return
  isLoading.value = true

  try {
    const data: DecryptedItemData = {
      username: form.value.username,
      password: form.value.password,
      url: form.value.url,
      notes: form.value.notes,
    }

    if (isNew.value) {
      await itemsStore.createItem(
        vaultStore.currentVaultId,
        form.value.titleHint,
        form.value.itemType,
        data,
        form.value.categoryId,
      )
    } else {
      await itemsStore.updateItem(
        vaultStore.currentVaultId,
        route.params.id as string,
        form.value.titleHint,
        form.value.itemType,
        data,
        form.value.categoryId,
      )
    }

    uiStore.showToast('Запись сохранена', 'success')
    router.push('/vault')
  } catch {
    uiStore.showToast('Не удалось сохранить запись', 'error')
  } finally {
    isLoading.value = false
  }
}

async function handleDelete() {
  if (!vaultStore.currentVaultId || isNew.value) return
  if (!confirm('Удалить эту запись?')) return

  try {
    await itemsStore.deleteItem(vaultStore.currentVaultId, route.params.id as string)
    uiStore.showToast('Запись удалена', 'success')
    router.push('/vault')
  } catch {
    uiStore.showToast('Не удалось удалить запись', 'error')
  }
}

async function copyPassword() {
  if (form.value.password) {
    await copy(form.value.password)
    uiStore.showToast('Пароль скопирован (очистится через 30с)', 'info', 2000)
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#eef2f8]">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
        <button @click="router.back()" class="text-slate-500 hover:text-slate-800 transition-colors">&#8592; Назад</button>
        <h2 class="text-lg font-semibold text-slate-800">{{ isNew ? 'Новая запись' : 'Редактировать запись' }}</h2>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">
      <div v-if="isDecrypting" class="text-center py-12 text-slate-400">
        Расшифровка...
      </div>

      <form v-else @submit.prevent="handleSave" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700">Название</label>
          <input
            v-model="form.titleHint"
            type="text"
            required
            class="mt-1 block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
            placeholder="Например: GitHub"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700">Тип</label>
          <select v-model="form.itemType" class="mt-1 block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
            <option value="login">Логин</option>
            <option value="note">Заметка</option>
            <option value="card">Карта</option>
            <option value="identity">Личные данные</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700">Категория</label>
          <select v-model="form.categoryId" class="mt-1 block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
            <option :value="null">— Без категории —</option>
            <option
              v-for="cat in categoriesStore.categories"
              :key="cat.id"
              :value="cat.id"
            >
              {{ cat.icon ? cat.icon + ' ' : '' }}{{ cat.name }}
            </option>
          </select>
        </div>

        <template v-if="form.itemType === 'login'">
          <div>
            <label class="block text-sm font-medium text-slate-700">Логин / Email</label>
            <input v-model="form.username" type="text" class="mt-1 block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700">Пароль</label>
            <div class="relative mt-1">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="block w-full px-3 py-2 pr-24 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500"
              />
              <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
                <button type="button" @click="showPassword = !showPassword" class="text-slate-400 hover:text-slate-600 text-xs">
                  {{ showPassword ? 'Скрыть' : 'Показать' }}
                </button>
                <button type="button" @click="copyPassword" class="text-slate-400 hover:text-slate-600 text-xs">
                  Копировать
                </button>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700">URL</label>
            <input v-model="form.url" type="url" class="mt-1 block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500" />
          </div>
        </template>

        <div>
          <label class="block text-sm font-medium text-slate-700">Заметки</label>
          <textarea v-model="form.notes" rows="3" class="mt-1 block w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500" />
        </div>

        <div class="flex gap-3 pt-4">
          <button
            type="submit"
            :disabled="isLoading"
            class="flex-1 py-2.5 px-4 bg-brand-500 text-white rounded-lg hover:bg-brand-600 disabled:opacity-50 text-sm font-semibold transition-colors"
          >
            {{ isLoading ? 'Сохраняю...' : 'Сохранить' }}
          </button>
          <button
            v-if="!isNew"
            type="button"
            @click="handleDelete"
            class="py-2.5 px-4 border border-rose-200 text-rose-500 rounded-lg hover:bg-rose-50 text-sm transition-colors"
          >
            Удалить
          </button>
        </div>
      </form>
    </main>
  </div>
</template>
