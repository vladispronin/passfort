export interface ApiResponse<T> {
  data: T
  meta?: Record<string, unknown>
}

export interface ApiError {
  error: string
  code: number
  violations?: Array<{ property: string; message: string }>
}
