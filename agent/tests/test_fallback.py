import json
from typing import ClassVar

import httpx
import pytest
from langchain_core.language_models import BaseChatModel
from langchain_core.messages import HumanMessage
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


class SlowModel(BaseChatModel):
    """Behaves like Ollama when it doesn't answer before the timeout."""

    error: ClassVar[Exception] = httpx.ReadTimeout("timed out")

    def bind_tools(self, tools, **kwargs):
        return self

    @property
    def _llm_type(self):
        return "slow"

    def _generate(self, messages, stop=None, run_manager=None, **kwargs) -> ChatResult:
        raise self.error

    async def _agenerate(self, messages, stop=None, run_manager=None, **kwargs) -> ChatResult:
        raise self.error


class UnreachableModel(SlowModel):
    error: ClassVar[Exception] = ConnectionError("Failed to connect to Ollama")


@pytest.fixture
def make_service(monkeypatch, api_mock):
    def factory(real_model, fallback=True):
        settings = Settings(_env_file=None, llm_provider="ollama", llm_fallback_to_mock=fallback, laravel_api_url=API)

        async def resolve(settings):
            return ResolvedModel("ollama", "qwen2.5:7b")

        def create(settings, provider=None):
            if provider == "mock":
                return ScriptedChatModel(token_delay=0)
            return real_model

        monkeypatch.setattr(service_module, "resolve_model", resolve)
        monkeypatch.setattr(service_module, "create_chat_model", create)
        return ChatService(settings, InMemorySaver(), api_factory=lambda request: MenuMindApi(API, request.api_token))

    return factory


def make_request():
    return ChatRequest(session_id="s1", message=QUESTION, api_token="tok")


async def count_user_messages(service):
    graph = service.build_graph(make_request(), MenuMindApi(API, None), "mock")
    state = await graph.aget_state(service.graph_config(make_request()))
    return sum(isinstance(m, HumanMessage) for m in state.values["messages"])


async def test_a_slow_model_is_replaced_by_the_mock(make_service):
    service = make_service(SlowModel())

    response = await service.chat(make_request())

    assert response.provider == "mock"
    assert response.fallback_reason == "ollama did not answer"
    assert [step.tool for step in response.steps] == ["search_menu", "check_allergens", "search_docs"]
    assert "Ojja Légumes" in response.answer
    # the question is not saved twice in the conversation
    assert await count_user_messages(service) == 1


async def test_a_streamed_turn_falls_back_and_tells_the_browser_to_reset(make_service):
    service = make_service(SlowModel())

    frames = [frame async for frame in service.stream(make_request())]
    events = []
    for frame in frames:
        lines = frame.split("\n")
        events.append((lines[0].removeprefix("event: "), json.loads(lines[1].removeprefix("data: "))))
    kinds = [kind for kind, _ in events]

    assert kinds[:3] == ["meta", "meta", "reset"]
    assert events[0][1]["provider"] == "ollama"
    assert events[1][1]["provider"] == "mock"
    assert kinds[-1] == "done"
    assert "Ojja Légumes" in events[-1][1]["answer"]
    assert "step" in kinds and "token" in kinds
    assert await count_user_messages(service) == 1


async def test_a_lost_connection_also_falls_back(make_service):
    service = make_service(UnreachableModel())

    response = await service.chat(make_request())

    assert response.provider == "mock"


async def test_without_fallback_the_error_is_reported(make_service):
    service = make_service(SlowModel(), fallback=False)

    frames = [frame async for frame in service.stream(make_request())]

    assert frames[-1].startswith("event: error")
    assert "not responding" in frames[-1]
    with pytest.raises(httpx.ReadTimeout):
        await service.chat(ChatRequest(session_id="s2", message=QUESTION, api_token="tok"))
