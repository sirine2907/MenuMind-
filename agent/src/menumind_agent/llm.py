"""Provider-agnostic chat model factory.

Switching LLM is a matter of environment variables: the rest of the agent
only sees a LangChain ``BaseChatModel`` that supports tool calling.
"""

import logging
import time
from dataclasses import dataclass

import httpx
from langchain_core.language_models import BaseChatModel

from .config import Provider, Settings
from .mock_llm import ScriptedChatModel

logger = logging.getLogger(__name__)


@dataclass(frozen=True)
class ResolvedModel:
    provider: Provider
    model: str
    # Why we are not using the configured provider, if we are not.
    fallback_reason: str | None = None


_health_cache: dict[str, tuple[float, str | None]] = {}
_HEALTH_TTL = 30.0


def create_chat_model(settings: Settings, provider: Provider | None = None) -> BaseChatModel:
    provider = provider or settings.llm_provider
    model = settings.model_name if provider == settings.llm_provider else None
    temperature = settings.llm_temperature

    match provider:
        case "ollama":
            from langchain_ollama import ChatOllama

            return ChatOllama(
                model=model or settings.ollama_model,
                base_url=settings.ollama_base_url,
                temperature=temperature,
                num_ctx=settings.ollama_num_ctx,
            )
        case "openai":
            from langchain_openai import ChatOpenAI

            return ChatOpenAI(model=model or settings.openai_model, api_key=settings.openai_api_key, temperature=temperature)
        case "azure_openai":
            from langchain_openai import AzureChatOpenAI

            return AzureChatOpenAI(
                azure_endpoint=settings.azure_openai_endpoint,
                azure_deployment=model or settings.azure_openai_deployment,
                api_key=settings.azure_openai_api_key,
                api_version=settings.azure_openai_api_version,
                temperature=temperature,
            )
        case "anthropic":
            from langchain_anthropic import ChatAnthropic

            return ChatAnthropic(
                model=model or settings.anthropic_model, api_key=settings.anthropic_api_key, temperature=temperature
            )
        case "gemini":
            from langchain_google_genai import ChatGoogleGenerativeAI

            return ChatGoogleGenerativeAI(
                model=model or settings.gemini_model, google_api_key=settings.google_api_key, temperature=temperature
            )
        case "mock":
            return ScriptedChatModel()

    raise ValueError(f"Unsupported LLM provider: {provider}")


async def resolve_model(settings: Settings) -> ResolvedModel:
    """Picks the provider to use for this turn, falling back to the scripted
    model when the configured one is clearly unusable (and fallback is on)."""
    provider = settings.llm_provider
    problem = await _provider_problem(settings)

    if problem is None:
        return ResolvedModel(provider, settings.model_name)

    if not settings.llm_fallback_to_mock:
        raise RuntimeError(problem)

    logger.warning("LLM provider %s unavailable (%s), using the scripted mock model", provider, problem)
    return ResolvedModel("mock", "scripted-demo", fallback_reason=problem)


async def _provider_problem(settings: Settings) -> str | None:
    match settings.llm_provider:
        case "mock":
            return None
        case "ollama":
            return await _ollama_problem(settings)
        case "openai":
            return None if settings.openai_api_key else "OPENAI_API_KEY is not set"
        case "azure_openai":
            missing = [
                n
                for n, v in {
                    "AZURE_OPENAI_ENDPOINT": settings.azure_openai_endpoint,
                    "AZURE_OPENAI_API_KEY": settings.azure_openai_api_key,
                    "AZURE_OPENAI_DEPLOYMENT": settings.azure_openai_deployment,
                }.items()
                if not v
            ]
            return f"{', '.join(missing)} not set" if missing else None
        case "anthropic":
            return None if settings.anthropic_api_key else "ANTHROPIC_API_KEY is not set"
        case "gemini":
            return None if settings.google_api_key else "GOOGLE_API_KEY is not set"
    return f"unknown provider {settings.llm_provider}"


async def _ollama_problem(settings: Settings) -> str | None:
    """Ollama must be up AND have the model pulled. Cached briefly so we do not
    ping Ollama on every message."""
    key = f"{settings.ollama_base_url}|{settings.model_name}"
    cached = _health_cache.get(key)
    if cached and time.monotonic() - cached[0] < _HEALTH_TTL:
        return cached[1]

    problem: str | None = None
    try:
        async with httpx.AsyncClient(timeout=3.0) as client:
            response = await client.get(f"{settings.ollama_base_url.rstrip('/')}/api/tags")
            response.raise_for_status()
            names = {m.get("name", "") for m in response.json().get("models", [])}
            wanted = settings.model_name
            if not any(n == wanted or n == f"{wanted}:latest" or n.split(":")[0] == wanted for n in names):
                problem = f"model {wanted} is not pulled yet"
    except (httpx.HTTPError, ValueError) as exc:
        problem = f"Ollama is not reachable at {settings.ollama_base_url} ({exc.__class__.__name__})"

    _health_cache[key] = (time.monotonic(), problem)
    return problem
