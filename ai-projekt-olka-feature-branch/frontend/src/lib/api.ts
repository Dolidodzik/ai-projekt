export class ApiError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(status: number, body: unknown, message?: string) {
    super(message ?? `Zadanie nie powiodlo sie (HTTP ${status})`)
    this.status = status
    this.body = body
  }
}

export function getApiBaseUrl(): string {
  return import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api'
}

function buildUrl(path: string, params?: Record<string, string | number | undefined | null>): string {
  const base = getApiBaseUrl().replace(/\/$/, '')
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  const url = new URL(`${base}${normalizedPath}`)

  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, String(value))
      }
    }
  }

  return url.toString()
}

export async function apiGet<T>(path: string, params?: Record<string, string | number | undefined | null>): Promise<T> {
  const response = await fetch(buildUrl(path, params))
  const body = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(response.status, body, typeof body?.message === 'string' ? body.message : undefined)
  }

  return body as T
}
