import asyncio
import json
from typing import Any

import httpx
import pytest
import respx
from langchain_core.language_models import BaseChatModel
from langchain_core.messages import BaseMessage, HumanMessage
from langchain_core.outputs import ChatResult
from langgraph.checkpoint.memory import InMemorySaver

from menumind_agent import service as service_module
from menumind_agent.api_client import MenuMindApi
from menumind_agent.config import Settings
from menumind_agent.llm import ResolvedModel
from menumind_agent.mock_llm import ScriptedChatModel
from menumind_agent.schemas import ChatRequest
from menumind_agent.service import ChatService

from .conftest import API

QUESTION = "I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?"


class HangingModel(BaseChatModel):
    """A real provider that never answers in time."""

    def bind_tools(self, tools: Any, **kwargs: Any) -> "HangingModel":
        return self

    @property
    def _llm_type(self) -> str:
        return "hanging"

    def _generate(self, messages: list[BaseMessage], stop: Any = None, run_manager: Any = None, **kwargs: Any) -> ChatResult:
        raise NotImplementedError

    async def _agenerate(
        self, messages: list[BaseMessage], stop: Any = None, run_manager: Any = None, **kwargs: Any
    ) -> ChatResult:
        await asyncio.sleep(30)
        raise AssertionError("should have been cancelled")


class UnreachableModel(HangingModel):
    async def _agenerate(
        self, messages: list[BaseMessage], stop: Any = None, run_manager: Any = None, **kwargs: Any
    ) -> ChatResult:
        raise httpx.ConnectError("Ollama went away")


@pytest.fixture
def make_service(monkeypatch: pytest.MonkeyPatch, api_mock: respx.MockRouter):
    def factory(real_model: BaseChatModel, fallback: bool = True) -> ChatService:
        settings = Settings(
            _env_file=None, llm_provider="ollama", llm_timeout_seconds=0.5, llm_fallback_to_mock=fallback, laravel_api_url=API
        )

        async def resolve(_: Settings) -> ResolvedModel:
            return ResolvedModel("ollama", "qwen2.5:7b")

        def create(_: Settings, provider: str | None = None) -> BaseChatModel:
            return ScriptedChatModel(token_delay=0) if provider == "mock" else real_model

        monkeypatch.setattr(service_module, "resolve_model", resolve)
        monkeypatch.setattr(service_module, "create_chat_model", create)
        return ChatService(settings, InMemorySaver(), api_factory=lambda r: MenuMindApi(API, r.api_token))

    return factory


def request() -> ChatRequest:
    return ChatRequest(session_id="s1", message=QUESTION, api_token="tok")


async def human_messages(service: ChatService) -> int:
    graph = service._graph(request(), MenuMindApi(API, None), ResolvedModel("mock", "scripted-demo"))
    state = await graph.aget_state(service._config(request()))
    return sum(isinstance(m, HumanMessage) for m in state.values["messages"])


async def test_a_slow_model_is_replaced_by_the_scripted_one(make_service) -> None:
    service = make_service(HangingModel())

    response = await service.chat(request())

    assert (response.provider, response.fallback_reason) == ("mock", "ollama timed out")
    assert [s.tool for s in response.steps] == ["search_menu", "check_allergens", "search_docs"]
    assert "Ojja Légumes" in response.answer
    assert await human_messages(service) == 1


async def test_a_streamed_turn_falls_back_and_tells_the_client_to_reset(make_service) -> None:
    service = make_service(HangingModel())

    frames = [frame async for frame in service.stream(request())]
    events = [(f.split("\n")[0].removeprefix("event: "), json.loads(f.split("\n")[1].removeprefix("data: "))) for f in frames]
    kinds = [kind for kind, _ in events]

    assert kinds[:3] == ["meta", "meta", "reset"]
    assert events[0][1]["provider"] == "ollama"
    assert events[1][1]["provider"] == "mock"
    assert events[1][1]["fallback_reason"] == "ollama timed out"
    assert kinds[-1] == "done" and "Ojja Légumes" in events[-1][1]["answer"]
    assert "step" in kinds and "token" in kinds
    assert await human_messages(service) == 1


async def test_a_model_that_drops_the_connection_also_falls_back(make_service) -> None:
    service = make_service(UnreachableModel())

    response = await service.chat(request())

    assert response.provider == "mock"
    assert response.fallback_reason == "ollama became unreachable"


async def test_without_fallback_a_timeout_is_reported(make_service) -> None:
    service = make_service(HangingModel(), fallback=False)

    frames = [frame async for frame in service.stream(request())]

    assert frames[-1].startswith("event: error")
    assert "took too long" in frames[-1]
    with pytest.raises(TimeoutError):
        await service.chat(ChatRequest(session_id="s2", message=QUESTION, api_token="tok"))
