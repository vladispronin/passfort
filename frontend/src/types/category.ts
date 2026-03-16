export interface Category {
  id: string
  name: string
  color: string | null
  icon: string | null
  createdAt: string
}

export interface CreateCategoryPayload {
  name: string
  color?: string | null
  icon?: string | null
}
