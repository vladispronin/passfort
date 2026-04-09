<script setup lang="ts">
import { ref, watch } from 'vue'
import { useCategoriesStore } from '../../stores/categories'
import { useVaultStore } from '../../stores/vault'
import { useUiStore } from '../../stores/ui'
import type { Category, CreateCategoryPayload } from '../../types/category'

const props = defineProps<{
  category?: Category
}>()

const emit = defineEmits<{
  close: []
  saved: [category: Category]
  deleted: [categoryId: string]
}>()

const categoriesStore = useCategoriesStore()
const vaultStore = useVaultStore()
const uiStore = useUiStore()

const PRESET_COLORS = [
  '#ef4444', // красный
  '#f97316', // оранжевый
  '#eab308', // жёлтый
  '#22c55e', // зелёный
  '#4361ee', // синий (brand)
  '#8b5cf6', // фиолетовый
  '#6b7280', // серый
  '#0f172a', // чёрный
  '#ec4899', // розовый
  '#14b8a6', // бирюзовый
  '#92400e', // коричневый
  '#84cc16', // лаймовый
  '#55ddf8', // циановый
  '#1e3a8a', // тёмно-синий (navy)
  '#be185d', // малиновый
  '#4d7c0f', // тёмно-оливковый
]

const PRESET_ICONS = [
  '💼', '🏠', '🎮', '🛒', '💰', '🔐', '⭐', '🌐', '📱', '🏦', '✉️', '🔑',
  '🔒', '🛡️', '💻', '🏢', '💳', '👤', '📧', '🔗', '☁️', '📝', '🎓', '✈️',
]

const isLoading = ref(false)
const name = ref(props.category?.name ?? '')
const color = ref<string | null>(props.category?.color ?? PRESET_COLORS[4])
const icon = ref<string | null>(props.category?.icon ?? null)

watch(
  () => props.category,
  (cat) => {
    name.value = cat?.name ?? ''
    color.value = cat?.color ?? PRESET_COLORS[4]
    icon.value = cat?.icon ?? null
  },
)

const isEditing = !!props.category

async function handleSave() {
  if (!name.value.trim() || !vaultStore.currentVaultId) return
  isLoading.value = true

  const payload: CreateCategoryPayload = {
    name: name.value.trim(),
    color: color.value,
    icon: icon.value,
  }

  try {
    let saved: Category
    if (isEditing && props.category) {
      saved = await categoriesStore.updateCategory(vaultStore.currentVaultId, props.category.id, payload)
    } else {
      saved = await categoriesStore.createCategory(vaultStore.currentVaultId, payload)
    }
    emit('saved', saved)
  } catch {
    uiStore.showToast('Не удалось сохранить категорию', 'error')
  } finally {
    isLoading.value = false
  }
}

async function handleDelete() {
  if (!props.category || !vaultStore.currentVaultId) return
  if (!confirm(`Удалить категорию «${props.category.name}»? Записи останутся, но потеряют категорию.`)) return

  isLoading.value = true
  try {
    await categoriesStore.deleteCategory(vaultStore.currentVaultId, props.category.id)
    emit('deleted', props.category.id)
  } catch {
    uiStore.showToast('Не удалось удалить категорию', 'error')
  } finally {
    isLoading.value = false
  }
}

function selectIcon(emoji: string) {
  icon.value = icon.value === emoji ? null : emoji
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="emit('close')"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <!-- Заголовок -->
        <div class="flex items-center justify-between mb-5">
          <h3 class="text-lg font-semibold text-slate-900">
            {{ isEditing ? 'Изменить категорию' : 'Новая категория' }}
          </h3>
          <button
            @click="emit('close')"
            class="text-slate-400 hover:text-slate-600 text-xl leading-none"
          >
            &times;
          </button>
        </div>

        <!-- Название -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-700 mb-1">Название</label>
          <input
            v-model="name"
            type="text"
            maxlength="255"
            required
            autofocus
            placeholder="Например: Работа"
            class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
          />
        </div>

        <!-- Цвет -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-700 mb-2">Цвет</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="c in PRESET_COLORS"
              :key="c"
              type="button"
              @click="color = c"
              :style="{ backgroundColor: c }"
              class="w-7 h-7 rounded-full border-2 transition-transform hover:scale-110"
              :class="color === c ? 'border-slate-700 scale-110' : 'border-transparent'"
            />
          </div>
        </div>

        <!-- Иконка -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-slate-700 mb-2">
            Иконка
            <span class="text-slate-400 font-normal">(необязательно)</span>
          </label>
          <div class="flex flex-wrap gap-1">
            <button
              v-for="emoji in PRESET_ICONS"
              :key="emoji"
              type="button"
              @click="selectIcon(emoji)"
              class="w-9 h-9 text-lg rounded-lg hover:bg-slate-100 transition-colors"
              :class="icon === emoji ? 'bg-brand-50 ring-2 ring-brand-400' : ''"
            >
              {{ emoji }}
            </button>
          </div>
        </div>

        <!-- Кнопки -->
        <div class="flex gap-2">
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
          >
            Отмена
          </button>
          <button
            v-if="isEditing"
            type="button"
            @click="handleDelete"
            :disabled="isLoading"
            class="px-4 py-2 text-sm text-rose-500 border border-rose-200 rounded-lg hover:bg-rose-50 disabled:opacity-50 transition-colors"
          >
            Удалить
          </button>
          <button
            type="button"
            @click="handleSave"
            :disabled="isLoading || !name.trim()"
            class="flex-1 px-4 py-2 text-sm bg-brand-500 text-white rounded-lg hover:bg-brand-600 disabled:opacity-50 transition-colors font-medium"
          >
            {{ isLoading ? 'Сохраняю...' : 'Сохранить' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
