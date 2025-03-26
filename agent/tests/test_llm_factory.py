import httpx
import pytest
import respx
from langchain_ollama import ChatOllama

from menumind_agent import llm
from menumind_agent.config import Settings
from menumind_agent.mock_llm import ScriptedChatModel


@pytest.fixture(autouse=True)
def clear_health_cache() -> None:
    llm._health_cache.clear()


def ollama_settings(**overrides: object) -> Settings:
    return Settings(_env_file=None, llm_provider="ollama", ollama_base_url="http://ollama.test:11434", **overrides)


def test_ollama_is_the_default_provider() -> None:
    settings = Settings(_env_file=None)

    assert settings.llm_provider == "ollama"
    assert settings.model_name == "qwen2.5:7b"


def test_it_builds_a_chat_ollama_model() -> None:
    model = llm.create_chat_model(ollama_settings(ollama_model="llama3.2:3b"))

    assert isinstance(model, ChatOllama)
    assert model.model == "llama3.2:3b"
    assert model.base_url == "http://ollama.test:11434"


def test_llm_model_overrides_the_provider_default() -> None:
    assert ollama_settings(llm_model="mistral-nemo").model_name == "mistral-nemo"


async def test_ollama_is_used_when_the_model_is_pulled() -> None:
    with respx.mock() as router:
        router.get("http://ollama.test:11434/api/tags").mock(
            return_value=httpx.Response(200, json={"models": [{"name": "qwen2.5:7b"}]})
        )
        resolved = await llm.resolve_model(ollama_settings())

    assert (resolved.provider, resolved.model, resolved.fallback_reason) == ("ollama", "qwen2.5:7b", None)


async def test_it_falls_back_to_the_mock_when_ollama_is_down() -> None:
    with respx.mock() as router:
        router.get("http://ollama.test:11434/api/tags").mock(side_effect=httpx.ConnectError("refused"))
        resolved = await llm.resolve_model(ollama_settings())

    assert resolved.provider == "mock"
    assert "not reachable" in resolved.fallback_reason
    assert isinstance(llm.create_chat_model(ollama_settings(), resolved.provider), ScriptedChatModel)


async def test_it_falls_back_when_the_model_is_not_pulled_yet() -> None:
    with respx.mock() as router:
        router.get("http://ollama.test:11434/api/tags").mock(return_value=httpx.Response(200, json={"models": []}))
        resolved = await llm.resolve_model(ollama_settings())

    assert resolved.provider == "mock"
    assert resolved.fallback_reason == "model qwen2.5:7b is not pulled yet"


async def test_without_fallback_an_unusable_provider_is_an_error() -> None:
    settings = Settings(_env_file=None, llm_provider="openai", openai_api_key=None, llm_fallback_to_mock=False)

    with pytest.raises(RuntimeError, match="OPENAI_API_KEY"):
        await llm.resolve_model(settings)
