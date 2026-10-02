import { useState, type FormEvent } from 'react'
import { api } from '../api/client'
import type { SearchResponse, SearchResult } from '../types'

const EXAMPLES = [
  'What is the punishment for theft?',
  'Someone threatened to kill me, is that a crime?',
  'Throwing acid on someone',
  'How do I register a company?',
]

function ResultItem({ result, dimmed = false }: { result: SearchResult; dimmed?: boolean }) {
  return (
    <li className={dimmed ? 'result dimmed' : 'result'}>
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
  )
}

export function SearchPanel() {
  const [query, setQuery] = useState('')
  const [response, setResponse] = useState<SearchResponse | null>(null)
  const [showBelow, setShowBelow] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function search(q: string) {
    if (q.trim().length < 3) return
    setQuery(q)
    setLoading(true)
    setError(null)
    setShowBelow(false)
    try {
      setResponse(await api.search(q))
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
      <h2>Semantic search</h2>
      <p className="muted">
        Finds the sections closest in meaning to your question (embeddings + pgvector). Only sections scoring at
        least the relevance threshold count as sources. No AI-written answer yet; that comes in the next phase.
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

      {response && (
        <>
          <p className={response.meta.has_relevant ? 'banner ok' : 'banner refuse'}>
            {response.meta.has_relevant
              ? `${response.data.length} relevant section(s) found (threshold ${response.meta.threshold}).`
              : `No section reaches the relevance threshold (${response.meta.threshold}). The assistant would refuse to answer this from its sources.`}
          </p>

          <ol className="results">
            {response.data.map((result) => (
              <ResultItem key={result.chunk_id} result={result} />
            ))}
          </ol>

          {response.below_threshold.length > 0 && (
            <>
              <button type="button" className="link-button" onClick={() => setShowBelow((v) => !v)}>
                {showBelow ? 'Hide' : 'Show'} {response.below_threshold.length} closest match(es) below the threshold
              </button>
              {showBelow && (
                <ol className="results">
                  {response.below_threshold.map((result) => (
                    <ResultItem key={result.chunk_id} result={result} dimmed />
                  ))}
                </ol>
              )}
            </>
          )}
        </>
      )}
    </section>
  )
}
