# Progress Log

A short record of what has been built and decided, so later work can pick up from here.
Add a new dated section at the top for each work session. The design itself is in
[`architecture.md`](architecture.md); this file records *what was done and why*.

---

## 2026-10-02 — Project bootstrap & Phase 1 (development environment)

### Repository
- Connected the local repo to `github.com/rajarizwan2007/pakistan-law-assistant-rag-ai`.
  The default branch is **`main`**.
- The remote uses **SSH** (`git@github.com:...`) because HTTPS push had no saved credentials
  and the machine's SSH key was already registered with GitHub.
- Added these files:
  - `.gitignore`: secrets, `vendor/`, `node_modules/`, build output, Docker data, `data/raw`, `CLAUDE.local.md`
  - `README.md`
  - `LICENSE` (MIT)
  - `CLAUDE.md` (shared project rules for Claude Code)
  - `CLAUDE.local.md` (personal notes, git-ignored)

### Decisions made
| Decision | Why |
|----------|-----|
| **Laravel 13 on PHP 8.4** (user's choice) | Latest versions. PHP runs **only in Docker** because the host has PHP 7.4. |
| Postgres 17 + pgvector (`pgvector/pgvector:pg17`) | One DB for relational data and vectors. |
| Ollama models: `nomic-embed-text` (768-dim) + `qwen2.5:3b` | No GPU and about 15 GB of RAM: small models that run on the CPU. |
| Host ports: frontend 5173, API 8090, Postgres 5433, Ollama 11434 | 80, 8000, 8080, 8081 and 3306 are already in use on this machine. |
| PHPUnit runs on Postgres (`law_assistant_test`), not SQLite | pgvector features only exist in Postgres. A separate DB keeps dev data safe. |
| PHP container runs as host UID/GID 1000 | Files Laravel creates in `backend/` belong to the user, not root. |
| React 19 + TypeScript + Vite, `/api` proxied to nginx | No CORS issues in development. |
| Removed Laravel's starter `AGENTS.md`, replaced `backend/CLAUDE.md` | The starter files told AI tools to install PHP on the host, which conflicts with Docker-only PHP. |

### What exists now
- **`docs/architecture.md`**: the services, request and ingestion flows, DB schema (`sources`, `chunks`, `queries`), API contract (`/health`, `/sources`, `/ask`), code layout, `.env` settings, trade-offs and build phases.
- **Docker** (`docker-compose.yml`, `docker/`) runs `db`, `ollama`, `backend` (php-fpm 8.4), `worker` (queue), `nginx` and `frontend`. On first start, `docker/postgres/init.sql` creates the `vector` extension and the test DB.
- **Backend** (`backend/`):
  - a fresh Laravel 13.34 app with the `pgvector/pgvector` package
  - `config/rag.php` (top_k, similarity threshold, embedding dimensions, disclaimer)
  - Ollama settings in `config/services.php`
  - `App\Services\Ollama\OllamaClient`, currently only `installedModels()`
  - `GET /api/health`, which checks the DB, pgvector and that the required Ollama models are installed
  - `tests/Feature/HealthCheckTest.php` (3 tests, using `Http::fake()`)
- **Frontend** (`frontend/`): a status page that calls `/api/health`. API calls go through `src/api/client.ts` and types are in `src/types.ts`.
- **`Makefile`** provides `make up | down | models | migrate | test | artisan c=".." | composer c=".." | psql | logs s=.. | health`.

### Verified
- `make test`: 5 tests passed.
- `/api/health` returns `status: ok`, with pgvector 0.8.7 and both models installed.
- The frontend builds and lints cleanly, and its `/api` proxy returns 200.
- Embedding a question takes under 1 second and returns 768 dimensions. A short `qwen2.5:3b` answer takes about 7.5 seconds on the CPU.

### Gotchas
- On a fresh setup the **worker crash-loops until `make migrate`** runs, because the `cache` table doesn't exist yet. Docker restarts it automatically afterwards.
- A fresh clone needs `cp backend/.env.example backend/.env` and `make artisan c="key:generate"`.
- The database credentials (`law` / `secret`) appear in both `docker-compose.yml` and `backend/.env`. Keep them in sync.
- The Ollama image is about 3 GB and the models about 2.3 GB. Both are stored in Docker volumes, so they download only once.

### Next up: Phase 2, schema and ingestion
1. Migrations for `sources` and `chunks`, with a `vector(768)` column, an HNSW cosine index and a `tsvector` column with a GIN index.
2. `LegalTextChunker`, which splits text by Article/Section and falls back to ~500-token windows.
3. The `php artisan law:ingest {file} --source=...` command, idempotent by file checksum.
4. The `EmbedChunks` queued job, which adds `embed()` to `OllamaClient`.
5. Ingest one act (for example the Pakistan Penal Code) end to end.
