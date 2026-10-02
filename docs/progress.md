# Progress Log

A short record of what has been built and decided, so later work can pick up from here.
Add a new dated section at the top for each work session. The design itself is in
[`architecture.md`](architecture.md); this file records *what was done and why*.

---

## 2026-10-02 — Phase 2: schema & ingestion

### Built
- **Migrations:**
  - `sources`: title is unique, plus short_name, unit (section/article), year, source_url, file_name, checksum and retrieved_at.
  - `chunks`: chapter, section_ref, heading, content, token_count, `vector(768)` embedding and embedding_model, plus a generated `content_tsv` column.
  - Indexes: **HNSW** (cosine) on the embedding and **GIN** on `content_tsv`.
- **Models:** `Source` with `cite()` → "PPC s.379", and `Chunk` with the pgvector `Vector` cast, `HasNeighbors` and `embeddingText()`.
- **`DocumentTextExtractor`:** runs `pdftohtml -xml` and keeps only body-size text, which drops footnotes and footnote markers. It removes `Page N of M` lines and normalises look-alike characters.
- **`LegalTextChunker`:** one chunk per section, with heading and chapter, and 500-token windows with 50-token overlap for long sections.
- **`IngestionService`:** uses the checksum so re-running is safe, does a transactional replace of chunks, and returns the embedding batches.
- **`EmbedChunks` job:** sends batches of 16 with the `search_document:` prefix, checks the vector dimensions, and skips chunks that are already embedded.
- **Commands:**
  - `php artisan law:ingest <file> --title= --short= --unit= --year= --url= --retrieved= --from-page= --force --sync`
  - `php artisan law:status`
- **`OllamaClient::embed()`**, which embeds a batch of texts in one call to `/api/embed`.
- **Infrastructure:** `poppler-utils` added to the PHP image, and `./data` mounted at `/var/www/data` in backend and worker.
- **Tests:** 21 tests pass (70 assertions). They cover the chunker unit tests, extractor XML filtering, and the ingest command (queueing, unchanged-file skip, changed-file replace, sync embeddings, wrong-dimension error).

### Data
- The Pakistan Penal Code PDF was downloaded from pakistancode.gov.pk into `data/raw/` (git-ignored; the URL is in README).
- Ingested with `--from-page=30`, because pages 1–29 are the table of contents.
- Result: **666 chunks, 631 sections, s.1–s.511**, 161 tokens on average and 500 at most. All sections have headings.
- Embedding all 666 chunks takes about 13 minutes on the CPU through the queue worker.
- **Vector search check:** the correct section came first for all 5 test questions: theft → s.379 (0.83),
  qatl-i-amd → s.302 (0.83), blasphemy → s.295C (0.74), car theft → s.381A (0.77), defamation → s.500 (0.83).
  Long sections can return 2 windows of the same section, so retrieval should de-duplicate by section.

### Problems found and fixed
- **Footnotes and page footers:** plain `pdftotext` mixed footnotes into the text, so I switched to font-size filtering.
- **Footer font size differs by poppler version:** the container's poppler 25.03 reports the footer as 17pt against an 18pt body, so the font filter alone missed it. Footers are now dropped by pattern instead.
- **Heading variants:** `“Oath.”`, `…not committed;`, `.––`, and headings with no punctuation before "Whoever" all needed handling.
- **Leading brackets:** `[ [ 478.` (doubled amendment brackets) needed handling.
- **Odd semicolon:** the PDF uses U+037E (Greek question mark) instead of `;`, so it is normalised.
- **Soft hyphens:** the PDF uses U+00AD as a visible hyphen ("shibh-i-amd", "House-breaking"), so it is mapped to `-`. This affected 22 chunks, and the act was re-ingested with `--force`.
- **Queue worker caches code:** it must be restarted after code changes (`docker compose restart worker`).

### Browser preview (added after Phase 2)
- `GET /api/sources` lists the acts with chunk and embedded counts. `GET /api/search?q=&limit=` is a plain
  vector search, a preview with no threshold or de-duplication yet.
- The React page at http://localhost:5173 has a semantic search box with example questions, a list of ingested laws and the system status.
- **Fix:** the frontend container now has its own `node_modules` volume (`docker/node/Dockerfile`).
  Sharing it with the host broke native binaries, because Alpine uses musl and the host uses glibc.
- **Fix:** nginx re-resolves `backend` via Docker DNS. Before this, rebuilding the backend gave a 502 until nginx was restarted.

### Next up: Phase 3, retrieval
1. A `Retriever` service: embed the question with the `search_query:` prefix, find the top-k chunks by cosine distance, apply the similarity threshold, and optionally filter by source.
2. A `law:search "question"` command to inspect results and scores, and to tune `RAG_SIMILARITY_THRESHOLD`.
3. A small set of evaluation questions with expected sections, to measure how often retrieval finds the right section.

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
