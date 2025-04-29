const TOKEN_KEY = 'menumind-token'

export const tokenStore = {
  get: (): string | null => {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set: (token: string | null) => {
    try {
      if (token) localStorage.setItem(TOKEN_KEY, token)
      else localStorage.removeItem(TOKEN_KEY)
    } catch {
      /* storage unavailable: the session simply won't persist */
    }
  },
}

/** Mirrors the Laravel error envelope: { error: { status, code, message, details } } */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly details?: Record<string, string[]>

  constructor(status: number, code: string, message: string, details?: Record<string, string[]>) {
    super(message)
    this.status = status
    this.code = code
    this.details = details
  }

  fieldError(field: string): string | undefined {
    return this.details?.[field]?.[0]
  }
}

type Query = Record<string, string | number | boolean | string[] | undefined | null>

export function toQueryString(params: Query = {}): string {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0)) continue
    if (typeof value === 'boolean') search.set(key, value ? '1' : '0')
    else if (Array.isArray(value)) search.set(key, value.join(','))
    else search.set(key, String(value))
  }
  const qs = search.toString()
  return qs ? `?${qs}` : ''
}

export async function apiFetch<T>(path: string, init: RequestInit & { query?: Query } = {}): Promise<T> {
  const { query, headers, ...rest } = init
  const token = tokenStore.get()
  const isForm = rest.body instanceof FormData

  const response = await fetch(`/api${path}${toQueryString(query)}`, {
    ...rest,
    headers: {
      Accept: 'application/json',
      ...(isForm || !rest.body ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
  })

  if (response.status === 204) return undefined as T

  const body = await response.json().catch(() => null)

  if (!response.ok) {
    const error = body?.error
    if (response.status === 401) tokenStore.set(null)
    throw new ApiError(
      response.status,
      error?.code ?? 'http_error',
      error?.message ?? `Request failed (${response.status})`,
      error?.details,
    )
  }

  return body as T
}

export const api = {
  get: <T>(path: string, query?: Query) => apiFetch<T>(path, { query }),
  post: <T>(path: string, data?: unknown) =>
    apiFetch<T>(path, { method: 'POST', body: data instanceof FormData ? data : JSON.stringify(data ?? {}) }),
  patch: <T>(path: string, data: unknown) => apiFetch<T>(path, { method: 'PATCH', body: JSON.stringify(data) }),
  delete: <T = void>(path: string) => apiFetch<T>(path, { method: 'DELETE' }),
}
