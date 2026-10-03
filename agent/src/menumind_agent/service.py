"""Runs one chat turn through the agent and returns it as JSON or as SSE events."""

import json
import logging

import httpx
from langchain_core.messages import AIMessage, HumanMessage, ToolMessage
from langgraph.errors import GraphRecursionError

from .api_client import MenuMindApi
from .citations import collect_citations, summarize_tool_result
from .graph import build_graph
from .guards import last_human_index, text_of
from .llm import ResolvedModel, create_chat_model, resolve_model
from .prompts import system_prompt
from .schemas import ChatRequest, ChatResponse, Step
from .tools import TOOL_LABELS, build_tools

logger = logging.getLogger(__name__)

# errors that mean the LLM did not answer (too slow or not reachable)
LLM_ERRORS = (httpx.TimeoutException, httpx.ConnectError, ConnectionError, TimeoutError)

LOST_MESSAGE = "Sorry, I got lost while looking that up. Could you rephrase your question?"


def sse(event, data):
    return f"event: {event}\ndata: {json.dumps(data, ensure_ascii=False)}\n\n"


class ChatService:
    def __init__(self, settings, checkpointer, api_factory=None):
        self.settings = settings
        # the checkpointer keeps the conversation history of each session
        self.checkpointer = checkpointer
        self.api_factory = api_factory or self.default_api

    def default_api(self, request: ChatRequest) -> MenuMindApi:
        return MenuMindApi(self.settings.laravel_api_url, request.api_token, self.settings.api_timeout_seconds)

    def build_graph(self, request: ChatRequest, api: MenuMindApi, provider: str):
        user_name = request.user.name if request.user else None
        return build_graph(
            llm=create_chat_model(self.settings, provider),
            tools=build_tools(api),
            system_prompt=system_prompt(self.settings.restaurant_name, user_name),
            checkpointer=self.checkpointer,
            history_limit=self.settings.max_history_messages,
        )

    def graph_config(self, request: ChatRequest) -> dict:
        return {
            "configurable": {"thread_id": request.session_id},
            "recursion_limit": self.settings.max_agent_steps * 2 + 1,
        }

    def fallback(self, model: ResolvedModel) -> ResolvedModel | None:
        """The mock model to use when the real one stops answering (or None if not allowed)."""
        if model.provider == "mock" or not self.settings.llm_fallback_to_mock:
            return None
        logger.warning("%s did not answer, finishing the turn with the mock model", model.provider)
        return ResolvedModel("mock", "scripted-demo", fallback_reason=f"{model.provider} did not answer")

    # ---------------------------------------------------------------- JSON

    async def chat(self, request: ChatRequest) -> ChatResponse:
        model = await resolve_model(self.settings)
        api = self.api_factory(request)
        config = self.graph_config(request)

        try:
            graph = self.build_graph(request, api, model.provider)
            try:
                state = await graph.ainvoke({"messages": [HumanMessage(request.message)]}, config, durability="sync")
            except LLM_ERRORS:
                fallback = self.fallback(model)
                if fallback is None:
                    raise
                model = fallback
                # the question is already saved in the checkpoint, so passing None continues the turn
                graph = self.build_graph(request, api, model.provider)
                state = await graph.ainvoke(None, config, durability="sync")
        except GraphRecursionError:
            state = {"messages": [AIMessage(LOST_MESSAGE)]}
        finally:
            await api.aclose()

        turn = current_turn(state["messages"])
        answer = final_answer(turn)

        steps = []
        for message in turn:
            if isinstance(message, ToolMessage):
                steps.append(
                    Step(
                        id=message.tool_call_id,
                        tool=message.name,
                        label=TOOL_LABELS.get(message.name, message.name),
                        input=tool_input(turn, message.tool_call_id),
                        summary=summarize_tool_result(message.name, message.artifact),
                    )
                )

        return ChatResponse(
            session_id=request.session_id,
            answer=answer,
            steps=steps,
            citations=collect_citations(turn, answer),
            provider=model.provider,
            model=model.model,
            fallback_reason=model.fallback_reason,
        )

    # ---------------------------------------------------------------- SSE

    async def stream(self, request: ChatRequest):
        """Yields the SSE events: meta, step, token, citations, done (or error).
        If the model stops answering, we send "reset" and the mock model finishes the turn."""
        try:
            model = await resolve_model(self.settings)
        except RuntimeError as error:
            yield sse("error", {"message": str(error)})
            return

        yield sse("meta", meta_event(request, model))

        api = self.api_factory(request)
        config = self.graph_config(request)
        graph = self.build_graph(request, api, model.provider)

        try:
            try:
                async for frame in self.run_events(graph, {"messages": [HumanMessage(request.message)]}, config):
                    yield frame
            except LLM_ERRORS:
                fallback = self.fallback(model)
                if fallback is None:
                    raise
                model = fallback
                graph = self.build_graph(request, api, model.provider)
                yield sse("meta", meta_event(request, model))
                # tells the browser to remove the half-written answer
                yield sse("reset", {})
                async for frame in self.run_events(graph, None, config):
                    yield frame

            state = await graph.aget_state(config)
            turn = current_turn(state.values["messages"])
            answer = final_answer(turn)
            yield sse("citations", {"citations": collect_citations(turn, answer)})
            yield sse("done", {"session_id": request.session_id, "answer": answer})
        except LLM_ERRORS:
            yield sse("error", {"message": "The assistant is not responding right now. Please try again."})
        except GraphRecursionError:
            yield sse("error", {"message": LOST_MESSAGE})
        except Exception:
            logger.exception("Chat stream failed")
            yield sse("error", {"message": "Something went wrong, please try again."})
        finally:
            await api.aclose()

    async def run_events(self, graph, graph_input, config):
        async for event in graph.astream_events(graph_input, config, version="v2", durability="sync"):
            frame = event_to_sse(event)
            if frame:
                yield frame


def meta_event(request: ChatRequest, model: ResolvedModel) -> dict:
    return {
        "session_id": request.session_id,
        "provider": model.provider,
        "model": model.model,
        "fallback_reason": model.fallback_reason,
    }


def event_to_sse(event: dict) -> str | None:
    """Converts a LangGraph event into an SSE frame for the browser (or None to skip it)."""
    kind = event["event"]
    name = event["name"]

    if kind == "on_chat_model_stream":
        chunk = event["data"].get("chunk")
        text = text_of(chunk) if chunk is not None else ""
        # chunks with tool calls are the model deciding what to do, not the answer
        if text and not getattr(chunk, "tool_call_chunks", None):
            return sse("token", {"text": text})

    if kind == "on_tool_start" and name in TOOL_LABELS:
        step = {"id": event["run_id"], "tool": name, "label": TOOL_LABELS[name], "status": "running"}
        step["input"] = event["data"].get("input", {})
        return sse("step", step)

    if kind == "on_tool_end" and name in TOOL_LABELS:
        artifact = getattr(event["data"].get("output"), "artifact", None)
        step = {"id": event["run_id"], "tool": name, "label": TOOL_LABELS[name], "status": "done"}
        step["summary"] = summarize_tool_result(name, artifact)
        return sse("step", step)

    return None


def current_turn(messages: list) -> list:
    """The messages after the last user message (tool calls, tool results and the answer)."""
    return messages[last_human_index(messages) + 1 :]


def final_answer(turn: list) -> str:
    for message in reversed(turn):
        if isinstance(message, AIMessage) and not message.tool_calls:
            return text_of(message)
    return ""


def tool_input(turn: list, tool_call_id: str) -> dict:
    for message in turn:
        if isinstance(message, AIMessage):
            for call in message.tool_calls:
                if call["id"] == tool_call_id:
                    return call["args"]
    return {}
