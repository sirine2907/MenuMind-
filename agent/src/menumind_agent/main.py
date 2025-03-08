import logging
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Annotated

from fastapi import Depends, FastAPI, Header, HTTPException, Request
from fastapi.responses import StreamingResponse
from langgraph.checkpoint.memory import InMemorySaver

from . import __version__
from .config import Settings, get_settings
from .llm import resolve_model
from .schemas import ChatRequest, ChatResponse
from .service import ChatService

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncIterator[None]:
    # In-memory conversation memory, one thread per session id. Swap for a
    # Postgres/SQLite checkpointer to survive restarts (see README trade-offs).
    app.state.chat = ChatService(get_settings(), InMemorySaver())
    yield


app = FastAPI(title="MenuMind Agent", version=__version__, lifespan=lifespan)


def chat_service(request: Request) -> ChatService:
    return request.app.state.chat


def verify_secret(
    settings: Annotated[Settings, Depends(get_settings)],
    x_agent_secret: Annotated[str | None, Header()] = None,
) -> None:
    if settings.agent_shared_secret and x_agent_secret != settings.agent_shared_secret:
        raise HTTPException(status_code=401, detail="Invalid or missing X-Agent-Secret header")


@app.get("/health")
async def health(settings: Annotated[Settings, Depends(get_settings)]) -> dict[str, object]:
    resolved = await resolve_model(settings) if settings.llm_fallback_to_mock else None
    return {
        "status": "ok",
        "version": __version__,
        "configured_provider": settings.llm_provider,
        "active_provider": resolved.provider if resolved else settings.llm_provider,
        "model": resolved.model if resolved else settings.model_name,
        "fallback_reason": resolved.fallback_reason if resolved else None,
    }


@app.post("/chat", response_model=ChatResponse, dependencies=[Depends(verify_secret)])
async def chat(body: ChatRequest, service: Annotated[ChatService, Depends(chat_service)]) -> ChatResponse:
    return await service.chat(body)


@app.post("/chat/stream", dependencies=[Depends(verify_secret)])
async def chat_stream(body: ChatRequest, service: Annotated[ChatService, Depends(chat_service)]) -> StreamingResponse:
    return StreamingResponse(
        service.stream(body),
        media_type="text/event-stream",
        headers={"Cache-Control": "no-cache", "X-Accel-Buffering": "no"},
    )
