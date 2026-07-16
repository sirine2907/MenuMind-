.DEFAULT_GOAL := help
COMPOSE := docker compose

.PHONY: help up up-mock down logs ps seed reindex test test-backend test-agent test-frontend lint screenshots

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the whole stack (Ollama + qwen2.5:7b by default)
	$(COMPOSE) up --build -d
	@echo "App: http://localhost:3000  API docs: http://localhost:8080/api/documentation"

up-mock: ## Start the stack with the scripted model (no model download)
	LLM_PROVIDER=mock $(COMPOSE) up --build -d

down: ## Stop the stack
	$(COMPOSE) down

logs: ## Follow logs
	$(COMPOSE) logs -f --tail=100

ps: ## Service status
	$(COMPOSE) ps

seed: ## Reset the database with demo data and rebuild the search index
	$(COMPOSE) exec backend php artisan migrate:fresh --seed --force
	$(COMPOSE) exec backend php artisan menumind:reindex

reindex: ## Rebuild the Typesense collections
	$(COMPOSE) exec backend php artisan menumind:reindex

test: test-backend test-agent test-frontend ## Run every test suite

test-backend: ## Laravel feature and unit tests
	cd backend && php artisan test

test-agent: ## Agent tests (LLM and HTTP mocked)
	cd agent && uv run pytest

test-frontend: ## Frontend component tests
	cd frontend && npm test

lint: ## Lint the agent and the frontend
	cd agent && uv run ruff check . && uv run ruff format --check .
	cd frontend && npm run lint && npm run typecheck

screenshots: ## Capture README screenshots (stack must be running)
	cd frontend && node scripts/screenshots.mjs
