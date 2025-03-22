# MenuMind Agent

FastAPI + LangGraph service that answers restaurant guests by calling the MenuMind REST API as tools.

```bash
uv sync                      # install (add --extra openai / anthropic / gemini for other providers)
uv run uvicorn menumind_agent.main:app --reload --port 8000
uv run pytest                # tests (LLM and HTTP fully mocked)
uv run ruff check .
```

Endpoints: `GET /health`, `POST /chat` (JSON), `POST /chat/stream` (Server-Sent Events).

See the [root README](../README.md) for the architecture, the tools and the LangGraph flow.
