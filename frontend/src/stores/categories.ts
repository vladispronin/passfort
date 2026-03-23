import { defineStore } from 'pinia'
import { ref } from 'vue'
import { categoriesApi } from '../api/categories'
import type { Category, CreateCategoryPayload } from '../types/category'

export const useCategoriesStore = defineStore('categories', () => {
  const categories = ref<Category[]>([])
  const isLoading = ref(false)

  async function loadCategories(vaultId: string): Promise<void> {
    isLoading.value = true
    try {
      categories.value = await categoriesApi.list(vaultId)
    } finally {
      isLoading.value = false
    }
  }

  async function createCategory(
    vaultId: string,
    payload: CreateCategoryPayload,
  ): Promise<Category> {
    const category = await categoriesApi.create(vaultId, payload)
    categories.value.push(category)
    return category
  }

  async function updateCategory(
    vaultId: string,
    categoryId: string,
    payload: CreateCategoryPayload,
  ): Promise<Category> {
    const updated = await categoriesApi.update(vaultId, categoryId, payload)
    const index = categories.value.findIndex((c) => c.id === categoryId)
    if (index !== -1) categories.value[index] = updated
    return updated
  }

  async function deleteCategory(vaultId: string, categoryId: string): Promise<void> {
    await categoriesApi.delete(vaultId, categoryId)
    categories.value = categories.value.filter((c) => c.id !== categoryId)
  }

  function reset(): void {
    categories.value = []
  }

  return {
    categories,
    isLoading,
    loadCategories,
    createCategory,
    updateCategory,
    deleteCategory,
    reset,
  }
})
