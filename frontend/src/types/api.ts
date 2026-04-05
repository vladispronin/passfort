export interface PaginationMeta {
  total: number
  page: number
  limit: number
  pages: number
}

export interface ApiResponse<T> {
  data: T
  meta?: PaginationMeta | Record<string, unknown>
}

export interface ApiError {
  error: string
  code: number
  violations?: Array<{ property: string; message: string }>
}
