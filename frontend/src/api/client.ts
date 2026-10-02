import type { HealthResponse, SearchResponse, Source } from '../types'

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api${path}`, {
    ...init,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init?.headers },
  })

  const body = await response.json().catch(() => {
    throw new Error(`API request failed: ${response.status} ${response.statusText}`)
  })

  // The health endpoint returns 503 with a useful JSON body when degraded.
  if (!response.ok && path !== '/health') {
    throw new Error(body.message ?? `API request failed: ${response.status}`)
  }

  return body as T
}

export const api = {
  health: () => request<HealthResponse>('/health'),
  sources: () => request<{ data: Source[] }>('/sources').then((r) => r.data),
  search: (q: string, limit = 5) =>
    request<SearchResponse>(`/search?${new URLSearchParams({ q, limit: String(limit) })}`),
}
