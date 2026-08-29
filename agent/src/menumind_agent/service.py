"""Runs one conversation turn through the graph, as JSON or as SSE events."""

import asyncio
import contextlib
import json
import logging
from collections.abc import AsyncIterator, Callable
from typing import Any

import httpx
from langchain_core.messages import AIMessage, AnyMessage, HumanMessage, ToolMessage
from langgraph.checkpoint.base import BaseCheckpointSaver
from langgraph.errors import GraphRecursionError
from langgraph.graph.state import CompiledStateGraph

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

# LLM back-end failures the scripted model can take over from mid-turn.
RECOVERABLE_LLM_ERRORS = (TimeoutError, httpx.ConnectError, ConnectionError)

LOST_MESSAGE = "Sorry, I got lost while looking that up. Could you rephrase your question?"


def sse(event: str, data: Any) -> str:
    return f"event: {event}\ndata: {json.dumps(data, ensure_ascii=False)}\n\n"


class ChatService:
    def __init__(self, settings: Settings, checkpointer: BaseCheckpointSaver, api_factory: ApiFactory | None = None):
        self.settings = settings
        self.checkpointer = checkpointer
        self.api_factory = api_factory or self._default_api

    def _default_api(self, request: ChatRequest) -> MenuMindApi:
        return MenuMindApi(self.settings.laravel_api_url, request.api_token, self.settings.api_timeout_seconds)

    def _graph(self, request: ChatRequest, api: MenuMindApi, resolved: ResolvedModel) -> CompiledStateGraph:
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

    def _fallback_for(self, resolved: ResolvedModel, exc: BaseException) -> ResolvedModel | None:
        if resolved.provider == "mock" or not self.settings.llm_fallback_to_mock:
            return None
        reason = f"{resolved.provider} timed out" if isinstance(exc, TimeoutError) else f"{resolved.provider} became unreachable"
        logger.warning("LLM turn failed (%s), finishing it with the scripted model", reason)
        return ResolvedModel("mock", "scripted-demo", fallback_reason=reason)

    async def _resume_input(
        self, graph: CompiledStateGraph, request: ChatRequest, config: dict[str, Any]
    ) -> dict[str, Any] | None:
        """Input that finishes an interrupted turn: ``None`` resumes from the last
        checkpoint, unless the guest's message never reached the checkpoint."""
        snapshot = await graph.aget_state(config)
        messages = snapshot.values.get("messages", [])
        idx = last_human_index(messages)
        if idx >= 0 and text_of(messages[idx]) == request.message and snapshot.next:
            return None
        return {"messages": [HumanMessage(request.message)]}

    # ------------------------------------------------------------------ JSON

    async def chat(self, request: ChatRequest) -> ChatResponse:
        resolved = await resolve_model(self.settings)
        api = self.api_factory(request)
        try:
            state, resolved = await self._invoke(request, api, resolved)
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

    async def _invoke(
        self, request: ChatRequest, api: MenuMindApi, resolved: ResolvedModel
    ) -> tuple[dict[str, Any], ResolvedModel]:
        config = self._config(request)
        graph = self._graph(request, api, resolved)
        try:
            try:
                async with asyncio.timeout(self.settings.llm_timeout_seconds):
                    state = await graph.ainvoke({"messages": [HumanMessage(request.message)]}, config, durability="sync")
                return state, resolved
            except RECOVERABLE_LLM_ERRORS as exc:
                fallback = self._fallback_for(resolved, exc)
                if fallback is None:
                    raise
                graph = self._graph(request, api, fallback)
                state = await graph.ainvoke(await self._resume_input(graph, request, config), config, durability="sync")
                return state, fallback
        except GraphRecursionError:
            return {"messages": [AIMessage(LOST_MESSAGE)]}, resolved

    # ------------------------------------------------------------------- SSE

    async def stream(self, request: ChatRequest) -> AsyncIterator[str]:
        """Yields SSE frames: meta, step (running/done), token, citations, done | error.

        If the model times out or drops mid-turn, a ``reset`` frame tells the
        client to discard the partial answer and the scripted model finishes
        the turn from the last checkpoint."""
        try:
            resolved = await resolve_model(self.settings)
        except RuntimeError as exc:
            yield sse("error", {"message": str(exc)})
            return

        yield sse("meta", _meta(request, resolved))

        api = self.api_factory(request)
        config = self._config(request)
        graph = self._graph(request, api, resolved)

        try:
            try:
                async for frame in self._stream_graph(graph, {"messages": [HumanMessage(request.message)]}, config):
                    yield frame
            except RECOVERABLE_LLM_ERRORS as exc:
                fallback = self._fallback_for(resolved, exc)
                if fallback is None:
                    raise
                resolved, graph = fallback, self._graph(request, api, fallback)
                yield sse("meta", _meta(request, resolved))
                yield sse("reset", {})
                async for frame in self._stream_graph(graph, await self._resume_input(graph, request, config), config):
                    yield frame

            state = await graph.aget_state(config)
            turn = _current_turn(state.values["messages"])
            answer = _final_answer(turn)
            yield sse("citations", {"citations": collect_citations(turn, answer)})
            yield sse("done", {"session_id": request.session_id, "answer": answer})
        except TimeoutError:
            yield sse("error", {"message": "The assistant took too long to answer. Please try again."})
        except GraphRecursionError:
            yield sse("error", {"message": LOST_MESSAGE})
        except Exception as exc:  # noqa: BLE001 - the stream must always terminate cleanly
            logger.exception("Chat stream failed")
            yield sse("error", {"message": f"Something went wrong: {exc.__class__.__name__}"})
        finally:
            await api.aclose()

    async def _stream_graph(
        self, graph: CompiledStateGraph, graph_input: dict[str, Any] | None, config: dict[str, Any]
    ) -> AsyncIterator[str]:
        """SSE frames for one graph run; raises TimeoutError past the deadline.

        The graph runs in its own task and feeds a queue, so the deadline only
        ever interrupts our own wait, never the code consuming this generator
        (a timeout around ``yield`` would cancel whatever the consumer awaits)."""
        loop = asyncio.get_running_loop()
        deadline = loop.time() + self.settings.llm_timeout_seconds
        queue: asyncio.Queue[str | None] = asyncio.Queue()

        async def pump() -> None:
            try:
                async for event in graph.astream_events(graph_input, config, version="v2", durability="sync"):
                    if frame := _event_to_sse(event):
                        queue.put_nowait(frame)
            finally:
                queue.put_nowait(None)

        task = asyncio.create_task(pump())
        try:
            while (frame := await asyncio.wait_for(queue.get(), max(deadline - loop.time(), 0))) is not None:
                yield frame
            await task  # surfaces any error raised inside the graph
        finally:
            if not task.done():
                task.cancel()
                with contextlib.suppress(asyncio.CancelledError, Exception):
                    await task


def _meta(request: ChatRequest, resolved: ResolvedModel) -> dict[str, Any]:
    return {
        "session_id": request.session_id,
        "provider": resolved.provider,
        "model": resolved.model,
        "fallback_reason": resolved.fallback_reason,
    }


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
