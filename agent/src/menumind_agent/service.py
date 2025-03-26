"""Runs one conversation turn through the graph, as JSON or as SSE events."""

import asyncio
import json
import logging
from collections.abc import AsyncIterator, Callable
from typing import Any

import httpx
from langchain_core.messages import AIMessage, AnyMessage, HumanMessage, ToolMessage
from langgraph.checkpoint.base import BaseCheckpointSaver
from langgraph.errors import GraphRecursionError

from .api_client import MenuMindApi
from .citations import collect_citations, summarize_tool_result
from .config import Settings
from .graph import build_graph
from .guards import last_human_index, text_of
from .llm import ResolvedModel, create_chat_model, resolve_model
from .prompts import system_prompt
from .schemas import ChatRequest, ChatResponse, Step
from .tools import TOOL_LABELS, build_tools

logger = logging.getLogger(__name__)

ApiFactory = Callable[[ChatRequest], MenuMindApi]


def sse(event: str, data: Any) -> str:
    return f"event: {event}\ndata: {json.dumps(data, ensure_ascii=False)}\n\n"


class ChatService:
    def __init__(self, settings: Settings, checkpointer: BaseCheckpointSaver, api_factory: ApiFactory | None = None):
        self.settings = settings
        self.checkpointer = checkpointer
        self.api_factory = api_factory or self._default_api

    def _default_api(self, request: ChatRequest) -> MenuMindApi:
        return MenuMindApi(self.settings.laravel_api_url, request.api_token, self.settings.api_timeout_seconds)

    def _graph(self, request: ChatRequest, api: MenuMindApi, resolved: ResolvedModel):
        return build_graph(
            llm=create_chat_model(self.settings, resolved.provider),
            tools=build_tools(api),
            system_prompt=system_prompt(self.settings.restaurant_name, request.user.name if request.user else None),
            checkpointer=self.checkpointer,
            history_limit=self.settings.max_history_messages,
        )

    def _config(self, request: ChatRequest) -> dict[str, Any]:
        return {
            "configurable": {"thread_id": request.session_id},
            "recursion_limit": self.settings.max_agent_steps * 2 + 1,
        }

    # ------------------------------------------------------------------ JSON

    async def chat(self, request: ChatRequest) -> ChatResponse:
        resolved = await resolve_model(self.settings)
        api = self.api_factory(request)
        try:
            state = await self._invoke(request, api, resolved)
        finally:
            await api.aclose()

        turn = _current_turn(state["messages"])
        answer = _final_answer(turn)
        steps = [
            Step(
                id=m.tool_call_id,
                tool=m.name or "",
                label=TOOL_LABELS.get(m.name or "", m.name or ""),
                input=_tool_input(turn, m.tool_call_id),
                summary=summarize_tool_result(m.name or "", m.artifact),
            )
            for m in turn
            if isinstance(m, ToolMessage)
        ]
        return ChatResponse(
            session_id=request.session_id,
            answer=answer,
            steps=steps,
            citations=collect_citations(turn, answer),
            provider=resolved.provider,
            model=resolved.model,
            fallback_reason=resolved.fallback_reason,
        )

    async def _invoke(self, request: ChatRequest, api: MenuMindApi, resolved: ResolvedModel) -> dict[str, Any]:
        config = self._config(request)
        try:
            async with asyncio.timeout(self.settings.llm_timeout_seconds):
                return await self._graph(request, api, resolved).ainvoke({"messages": [HumanMessage(request.message)]}, config)
        except (TimeoutError, httpx.ConnectError) as exc:
            if resolved.provider == "mock" or not self.settings.llm_fallback_to_mock:
                raise
            # The turn's human message is already checkpointed: resume the
            # graph from there with the scripted model instead of failing.
            logger.warning("LLM turn failed (%s), finishing it with the scripted model", exc.__class__.__name__)
            mock = ResolvedModel("mock", "scripted-demo", fallback_reason=f"{resolved.provider} timed out")
            return await self._graph(request, api, mock).ainvoke(None, config)
        except GraphRecursionError:
            return {"messages": [AIMessage("Sorry, I got lost while looking that up. Could you rephrase your question?")]}

    # ------------------------------------------------------------------- SSE

    async def stream(self, request: ChatRequest) -> AsyncIterator[str]:
        """Yields SSE frames: meta, step (running/done), token, citations, done | error."""
        try:
            resolved = await resolve_model(self.settings)
        except RuntimeError as exc:
            yield sse("error", {"message": str(exc)})
            return

        yield sse(
            "meta",
            {
                "session_id": request.session_id,
                "provider": resolved.provider,
                "model": resolved.model,
                "fallback_reason": resolved.fallback_reason,
            },
        )

        api = self.api_factory(request)
        config = self._config(request)
        graph = self._graph(request, api, resolved)

        try:
            async with asyncio.timeout(self.settings.llm_timeout_seconds):
                async for event in graph.astream_events({"messages": [HumanMessage(request.message)]}, config, version="v2"):
                    frame = _event_to_sse(event)
                    if frame:
                        yield frame

            state = await graph.aget_state(config)
            turn = _current_turn(state.values["messages"])
            answer = _final_answer(turn)
            yield sse("citations", {"citations": collect_citations(turn, answer)})
            yield sse("done", {"session_id": request.session_id, "answer": answer})
        except TimeoutError:
            yield sse("error", {"message": "The assistant took too long to answer. Please try again."})
        except GraphRecursionError:
            yield sse("error", {"message": "Sorry, I got lost while looking that up. Could you rephrase?"})
        except Exception as exc:  # noqa: BLE001 - the stream must always terminate cleanly
            logger.exception("Chat stream failed")
            yield sse("error", {"message": f"Something went wrong: {exc.__class__.__name__}"})
        finally:
            await api.aclose()


def _event_to_sse(event: dict[str, Any]) -> str | None:
    kind = event["event"]

    if kind == "on_chat_model_stream":
        chunk = event["data"].get("chunk")
        text = text_of(chunk) if chunk is not None else ""
        # Chunks that carry tool calls are the model "thinking", not the answer.
        if text and not getattr(chunk, "tool_call_chunks", None):
            return sse("token", {"text": text})

    elif kind == "on_tool_start" and event["name"] in TOOL_LABELS:
        return sse(
            "step",
            {
                "id": event["run_id"],
                "tool": event["name"],
                "label": TOOL_LABELS[event["name"]],
                "status": "running",
                "input": event["data"].get("input", {}),
            },
        )

    elif kind == "on_tool_end" and event["name"] in TOOL_LABELS:
        output = event["data"].get("output")
        artifact = getattr(output, "artifact", None)
        return sse(
            "step",
            {
                "id": event["run_id"],
                "tool": event["name"],
                "label": TOOL_LABELS[event["name"]],
                "status": "done",
                "summary": summarize_tool_result(event["name"], artifact),
            },
        )

    return None


def _current_turn(messages: list[AnyMessage]) -> list[AnyMessage]:
    return messages[last_human_index(messages) + 1 :]


def _final_answer(turn: list[AnyMessage]) -> str:
    for message in reversed(turn):
        if isinstance(message, AIMessage) and not message.tool_calls:
            return text_of(message)
    return ""


def _tool_input(turn: list[AnyMessage], tool_call_id: str) -> dict[str, Any]:
    for message in turn:
        if isinstance(message, AIMessage):
            for call in message.tool_calls:
                if call["id"] == tool_call_id:
                    return call["args"]
    return {}
