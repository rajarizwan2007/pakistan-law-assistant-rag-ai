# Backend (Laravel 13, PHP 8.4)

See the root `CLAUDE.md` and `docs/architecture.md` for project context.

- PHP runs **only in Docker**. The host has PHP 7.4, which cannot run this app. Do not
  install PHP on the host and do not run `php`/`composer` directly; use the containers:
  - `docker compose exec backend php artisan ...` (or `make artisan c="..."`)
  - `docker compose exec backend composer ...` (or `make composer c="..."`)
  - Tests: `make test` (runs against the `law_assistant_test` Postgres database)
- Database is PostgreSQL 17 + pgvector, not SQLite.
- Ollama is reached via `App\Services\Ollama\OllamaClient` (config in `config/services.php`);
  RAG tuning lives in `config/rag.php`. In tests, fake Ollama with `Http::fake()`.
