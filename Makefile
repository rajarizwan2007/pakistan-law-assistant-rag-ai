# Shortcuts for common development tasks. Run `make help` to list them.
CHAT_MODEL  ?= qwen2.5:3b
EMBED_MODEL ?= nomic-embed-text

.PHONY: help up down restart logs ps models artisan composer migrate test shell psql health

help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

up: ## Build and start all services
	docker compose up -d --build

down: ## Stop all services (data volumes are kept)
	docker compose down

restart: ## Restart all services
	docker compose restart

logs: ## Follow logs of all services (or: make logs s=backend)
	docker compose logs -f $(s)

ps: ## Show service status
	docker compose ps

models: ## Download the Ollama chat and embedding models
	docker compose exec ollama ollama pull $(EMBED_MODEL)
	docker compose exec ollama ollama pull $(CHAT_MODEL)

artisan: ## Run an artisan command, e.g. make artisan c="route:list"
	docker compose exec backend php artisan $(c)

composer: ## Run a composer command, e.g. make composer c="require foo/bar"
	docker compose exec backend composer $(c)

migrate: ## Run database migrations
	docker compose exec backend php artisan migrate

test: ## Run the backend test suite
	docker compose exec backend php artisan test

shell: ## Open a shell in the backend container
	docker compose exec backend bash

psql: ## Open psql on the development database
	docker compose exec db psql -U law -d law_assistant

health: ## Call the API health endpoint
	@curl -s http://localhost:8090/api/health; echo
