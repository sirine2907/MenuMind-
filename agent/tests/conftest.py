from collections.abc import Iterator, Sequence
from typing import Any

import httpx
import pytest
import respx
from langchain_core.language_models import BaseChatModel
from langchain_core.messages import AIMessage, BaseMessage
from langchain_core.outputs import ChatGeneration, ChatResult

from menumind_agent.config import Settings

API = "http://api.test/api"

DISHES = [
    {
        "id": 6,
        "name": "Ojja Légumes",
        "description": "Eggs in spicy tomato sauce.",
        "category": "Starters",
        "price": 11.5,
        "is_vegetarian": True,
        "is_vegan": False,
        "spicy_level": 2,
        "available": True,
        "allergens": ["eggs", "gluten"],
        "score": 0.9,
    },
    {
        "id": 11,
        "name": "Couscous aux Sept Légumes",
        "description": "Vegan couscous.",
        "category": "Traditional Mains",
        "price": 17.5,
        "is_vegetarian": True,
        "is_vegan": True,
        "spicy_level": 1,
        "available": True,
        "allergens": ["gluten", "celery"],
        "score": 0.8,
    },
    {
        "id": 33,
        "name": "Citronnade",
        "description": "Fresh lemonade.",
        "category": "Drinks",
        "price": 4.0,
        "is_vegetarian": True,
        "is_vegan": True,
        "spicy_level": 0,
        "available": True,
        "allergens": [],
        "score": 0.5,
    },
]

DOC_HITS = [
    {
        "chunk_id": "1-2",
        "document_id": 1,
        "document_title": "Allergen & Dietary Policy",
        "heading": "Peanuts and tree nuts",
        "content": "Peanuts are used in only two recipes. We never cook with peanut oil.",
        "score": 0.7,
    },
]

ORDER = {
    "id": 42,
    "status": "pending",
    "status_label": "Pending",
    "total": 27.0,
    "currency": "TND",
    "items": [
        {"dish_id": 6, "dish_name": "Ojja Légumes", "quantity": 2, "unit_price": 11.5, "subtotal": 23.0},
        {"dish_id": 33, "dish_name": "Citronnade", "quantity": 1, "unit_price": 4.0, "subtotal": 4.0},
    ],
}


@pytest.fixture
def settings() -> Settings:
    return Settings(
        _env_file=None,
        llm_provider="mock",
        laravel_api_url=API,
        agent_shared_secret="s3cret",
        llm_timeout_seconds=10,
    )


@pytest.fixture
def api_mock() -> Iterator[respx.MockRouter]:
    """The Laravel API, mocked at the HTTP level."""
    with respx.mock(base_url=API, assert_all_called=False) as router:
        router.get("/search/dishes", name="search").mock(
            return_value=httpx.Response(
                200, json={"data": {"engine": "typesense_hybrid", "query": "", "filters": {}, "hits": DISHES}}
            )
        )
        router.post("/dishes/allergen-check", name="allergens").mock(
            return_value=httpx.Response(
                200,
                json={
                    "data": {
                        "allergens_checked": ["peanuts"],
                        "results": [
                            {
                                "dish_id": d["id"],
                                "found": True,
                                "name": d["name"],
                                "allergens": d["allergens"],
                                "conflicts": [],
                                "safe": True,
                            }
                            for d in DISHES
                        ],
                    }
                },
            )
        )
        router.get("/search/docs", name="docs").mock(
            return_value=httpx.Response(200, json={"data": {"engine": "typesense_hybrid", "query": "", "hits": DOC_HITS}})
        )
        router.post("/orders", name="create_order").mock(return_value=httpx.Response(201, json={"data": ORDER}))
        router.get("/orders/42", name="order").mock(return_value=httpx.Response(200, json={"data": ORDER}))
        router.get("/orders", name="orders").mock(return_value=httpx.Response(200, json={"data": [ORDER]}))
        yield router


class FakeToolModel(BaseChatModel):
    """Replays a fixed list of AI messages, one per call: a stand-in for an LLM
    whose decisions we want to control exactly."""

    responses: list[AIMessage]
    calls: list[list[BaseMessage]] = []

    @property
    def _llm_type(self) -> str:
        return "fake-tool-model"

    def bind_tools(self, tools: Sequence[Any], **kwargs: Any) -> "FakeToolModel":
        return self

    def _generate(
        self, messages: list[BaseMessage], stop: list[str] | None = None, run_manager: Any = None, **kwargs: Any
    ) -> ChatResult:
        self.calls.append(list(messages))
        return ChatResult(generations=[ChatGeneration(message=self.responses.pop(0))])


def tool_call(name: str, args: dict[str, Any], call_id: str = "call_1") -> AIMessage:
    return AIMessage(content="", tool_calls=[{"name": name, "args": args, "id": call_id, "type": "tool_call"}])
