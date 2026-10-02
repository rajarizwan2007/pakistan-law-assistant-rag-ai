import { useEffect, useState } from 'react'
import { api } from '../api/client'
import type { HealthResponse } from '../types'

export function SystemStatus() {
  const [health, setHealth] = useState<HealthResponse | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.health().then(setHealth).catch((e: Error) => setError(e.message))
  }, [])

  return (
    <section className="card">
      <h2>System status</h2>
      {error && <p className="status bad">API unreachable: {error}</p>}
      {!health && !error && <p className="muted">Checking…</p>}
      {health && (
        <ul className="checks">
          {Object.entries(health.checks).map(([name, check]) => (
            <li key={name} className={check.ok ? 'status ok' : 'status bad'}>
              <span className="dot" /> <strong>{name}</strong>: {check.ok ? 'ok' : (check.error ?? 'not ready')}
              {name === 'database' && typeof check.pgvector === 'string' && (
                <span className="muted"> (pgvector {check.pgvector})</span>
              )}
              {Array.isArray(check.models) && check.models.length > 0 && (
                <span className="muted"> ({check.models.join(', ')})</span>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
