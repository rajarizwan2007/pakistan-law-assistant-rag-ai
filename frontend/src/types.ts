export interface HealthCheck {
  ok: boolean
  error?: string
  [detail: string]: unknown
}

export interface HealthResponse {
  status: 'ok' | 'degraded'
  checks: Record<string, HealthCheck>
}
