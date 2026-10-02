import { useEffect, useState } from 'react'
import { api } from './api/client'
import type { HealthResponse } from './types'

function App() {
  const [health, setHealth] = useState<HealthResponse | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.health().then(setHealth).catch((e: Error) => setError(e.message))
  }, [])

  return (
    <main className="container">
      <h1>Pakistan Law Assistant</h1>
      <p className="disclaimer">Informational only — not legal advice.</p>

      <section>
        <h2>System status</h2>
        {error && <p className="status bad">API unreachable: {error}</p>}
        {!health && !error && <p>Checking…</p>}
        {health && (
          <ul className="checks">
            {Object.entries(health.checks).map(([name, check]) => (
              <li key={name} className={check.ok ? 'status ok' : 'status bad'}>
                <strong>{name}</strong>: {check.ok ? 'ok' : (check.error ?? 'not ready')}
                {Array.isArray(check.missing) && check.missing.length > 0 && (
                  <> (missing models: {check.missing.join(', ')})</>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    </main>
  )
}

export default App
