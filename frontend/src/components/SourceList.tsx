import { useEffect, useState } from 'react'
import { api } from '../api/client'
import type { Source } from '../types'

export function SourceList() {
  const [sources, setSources] = useState<Source[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.sources().then(setSources).catch((e: Error) => setError(e.message))
  }, [])

  return (
    <section className="card">
      <h2>Ingested laws</h2>
      {error && <p className="status bad">{error}</p>}
      {sources?.length === 0 && <p className="muted">Nothing ingested yet.</p>}
      {sources && sources.length > 0 && (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Act</th>
                <th>Chunks</th>
                <th>Embedded</th>
              </tr>
            </thead>
            <tbody>
              {sources.map((source) => (
                <tr key={source.id}>
                  <td>
                    {source.source_url ? (
                      <a href={source.source_url} target="_blank" rel="noreferrer">
                        {source.title}
                      </a>
                    ) : (
                      source.title
                    )}
                    {source.short_name && <span className="muted"> ({source.short_name})</span>}
                  </td>
                  <td>{source.chunks_count}</td>
                  <td>
                    {source.embedded_count} / {source.chunks_count}
                    {source.embedded_count < source.chunks_count && <span className="muted"> (in progress)</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
