# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

A small, **source-grounded** Retrieval-Augmented Generation (RAG) assistant for Pakistani law.
Users ask a question; the system retrieves relevant passages from a curated set of Pakistani
legal texts and a local LLM answers **only from those passages, with citations**.

This is a **full-time software-engineering learning project** built for learning and interview
demonstration. It is **not** a production system and must **not** present output as legal advice.

## Stack (target)

| Layer       | Technology                                   |
|-------------|----------------------------------------------|
| Frontend    | React (Vite)                                 |
| Backend API | Laravel 13 (PHP 8.4)                         |
| Database    | PostgreSQL + `pgvector` (documents, chunks, embeddings) |
| LLM / embeddings | Ollama running a local model            |
| Infra       | Docker / Docker Compose                      |
| Hosting     | GitHub (`rajarizwan2007/pakistan-law-assistant-rag-ai`) |

## Layout

Full design: `docs/architecture.md`.

```
frontend/         React 19 + TypeScript + Vite (chat UI, citation display)
backend/          Laravel 13 API on PHP 8.4 (ingestion, retrieval, answer generation)
docker/           Dockerfiles and service config (php, nginx, postgres init)
data/raw/         Source legal documents (git-ignored, not created yet)
data/processed/   Cleaned/chunked text (git-ignored, not created yet)
docs/             Architecture notes, decisions, evaluation results
docker-compose.yml
Makefile          Shortcuts for common commands (`make help`)
```

PHP and Composer run **only inside Docker**. The host has PHP 7.4, which Laravel 13 can't use.

## RAG pipeline (intended design)

1. **Ingest** – load source documents (e.g., Constitution of Pakistan, PPC, CrPC), record
   source metadata (act name, section/article number, URL, retrieval date).
2. **Chunk** – split by legal structure (section/article) where possible, not just by fixed size.
3. **Embed** – generate embeddings via Ollama and store them in a `pgvector` column.
4. **Retrieve** – embed the user query, run similarity search (top-k), optionally filter by act.
5. **Generate** – prompt the local LLM with retrieved chunks only; require citations to
   act + section for each claim.
6. **Respond** – return the answer, citations, and the retrieved source snippets to the UI.

## Non-negotiable rules

- **Grounding:** answers must be based only on retrieved passages. If retrieval returns nothing
  relevant, the assistant says it cannot answer from its sources. It must not fall back to the
  model's general knowledge.
- **Citations:** every answer cites act/section for the passages it used.
- **Disclaimer:** the UI and API responses state that output is informational, not legal advice.
- **Local-first:** use Ollama/local models by default; do not add paid or external LLM APIs
  without asking.
- **Secrets:** never commit `.env` files or credentials. Provide `.env.example` instead.
- **Source data:** don't commit large raw legal corpora; document where to obtain them.

## Conventions

- Laravel: follow standard Laravel structure (Controllers thin, logic in Services/Actions,
  migrations for every schema change, Form Requests for validation).
- React: functional components and hooks; keep API calls in a dedicated module.
- Tests: PHPUnit/Pest for backend, Vitest for frontend. Add tests for retrieval and
  prompt-building logic in particular.
- Commits: small, descriptive commits; one logical change per commit.

## Commands

| Task | Command |
|------|---------|
| Start everything | `make up` (`docker compose up -d --build`) |
| Download LLM models | `make models` |
| Check service health | `make health` or open http://localhost:5173 |
| Run migrations | `make migrate` |
| Run backend tests | `make test` |
| Artisan / Composer | `make artisan c="route:list"`, `make composer c="require x/y"` |
| Postgres shell | `make psql` |
| Logs | `make logs s=backend` |

Ports on the host: frontend 5173, API 8090, Postgres 5433, Ollama 11434.
