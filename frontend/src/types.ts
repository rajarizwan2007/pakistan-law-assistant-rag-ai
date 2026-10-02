export interface HealthCheck {
  ok: boolean
  error?: string
  [detail: string]: unknown
}

export interface HealthResponse {
  status: 'ok' | 'degraded'
  checks: Record<string, HealthCheck>
}

export interface Source {
  id: number
  title: string
  short_name: string | null
  unit: 'section' | 'article'
  year: number | null
  source_url: string | null
  chunks_count: number
  embedded_count: number
}

export interface SearchResult {
  chunk_id: number
  citation: string
  source: string
  chapter: string | null
  heading: string | null
  content: string
  score: number
}
