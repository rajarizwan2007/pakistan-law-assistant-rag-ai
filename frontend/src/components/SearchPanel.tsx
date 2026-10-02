import { useState, type FormEvent } from 'react'
import { api } from '../api/client'
import type { SearchResult } from '../types'

const EXAMPLES = [
  'What is the punishment for theft?',
  'What happens if someone steals a car?',
  'Punishment for murder',
  'Is defamation a crime?',
]

export function SearchPanel() {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<SearchResult[] | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function search(q: string) {
    if (q.trim().length < 3) return
    setQuery(q)
    setLoading(true)
    setError(null)
    try {
      setResults(await api.search(q))
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setLoading(false)
    }
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault()
    search(query)
  }

  return (
    <section className="card">
      <h2>Semantic search (preview)</h2>
      <p className="muted">
        Finds the sections closest in meaning to your question using embeddings and pgvector. No AI-written answer
        yet; that comes in a later phase.
      </p>

      <form onSubmit={onSubmit} className="search-form">
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Ask about the Pakistan Penal Code…"
          aria-label="Search question"
        />
        <button type="submit" disabled={loading || query.trim().length < 3}>
          {loading ? 'Searching…' : 'Search'}
        </button>
      </form>

      <div className="examples">
        {EXAMPLES.map((example) => (
          <button key={example} type="button" className="chip" onClick={() => search(example)}>
            {example}
          </button>
        ))}
      </div>

      {error && <p className="status bad">{error}</p>}

      {results && (
        <ol className="results">
          {results.map((result) => (
            <li key={result.chunk_id} className="result">
              <div className="result-head">
                <span className="citation">{result.citation}</span>
                <span className="heading">{result.heading}</span>
                <span className="score" title="Cosine similarity (1 = identical meaning)">
                  {result.score.toFixed(3)}
                </span>
              </div>
              {result.chapter && <div className="muted small">{result.chapter}</div>}
              <p className="content">{result.content}</p>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}
