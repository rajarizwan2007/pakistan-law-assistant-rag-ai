import type { HealthResponse } from '../types'

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api${path}`, {
    ...init,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init?.headers },
  })

  // The health endpoint returns 503 with a JSON body when degraded, so only fail on non-JSON errors.
  const body = await response.json().catch(() => {
    throw new Error(`API request failed: ${response.status} ${response.statusText}`)
  })

  return body as T
}

export const api = {
  health: () => request<HealthResponse>('/health'),
}
