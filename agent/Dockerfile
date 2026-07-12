# syntax=docker/dockerfile:1
FROM python:3.12-slim-bookworm

COPY --from=ghcr.io/astral-sh/uv:0.13.1 /uv /uvx /bin/

ENV UV_COMPILE_BYTECODE=1 \
    UV_LINK_MODE=copy \
    UV_PROJECT_ENVIRONMENT=/opt/venv \
    PATH=/opt/venv/bin:$PATH \
    PYTHONUNBUFFERED=1

WORKDIR /app

# Dependencies first (cached layer), all LLM providers included so switching
# provider is only an environment change.
COPY pyproject.toml uv.lock README.md ./
RUN uv sync --frozen --no-dev --all-extras --no-install-project

COPY src ./src
RUN uv sync --frozen --no-dev --all-extras

RUN useradd --create-home --uid 1000 agent
USER agent

EXPOSE 8000
CMD ["uvicorn", "menumind_agent.main:app", "--host", "0.0.0.0", "--port", "8000", "--proxy-headers"]
