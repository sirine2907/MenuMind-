"""Creates the chat model chosen with LLM_PROVIDER (Ollama by default)."""

import logging
from dataclasses import dataclass

import httpx

from .config import Settings
from .mock_llm import ScriptedChatModel

logger = logging.getLogger(__name__)


@dataclass
class ResolvedModel:
    provider: str
    model: str
    # filled when we use the mock because the real provider can't be used
    fallback_reason: str | None = None


def create_chat_model(settings: Settings, provider: str | None = None):
    provider = provider or settings.llm_provider
    # LLM_MODEL only applies to the configured provider, not to the fallback
    model = settings.model_name if provider == settings.llm_provider else None
    temperature = settings.llm_temperature
    timeout = settings.llm_timeout_seconds

    if provider == "ollama":
        from langchain_ollama import ChatOllama

        return ChatOllama(
            model=model or settings.ollama_model,
            base_url=settings.ollama_base_url,
            temperature=temperature,
            num_ctx=settings.ollama_num_ctx,
            # if Ollama doesn't answer in time, httpx raises a timeout and we use the mock
            client_kwargs={"timeout": timeout},
        )

    if provider == "openai":
        from langchain_openai import ChatOpenAI

        return ChatOpenAI(
            model=model or settings.openai_model, api_key=settings.openai_api_key, temperature=temperature, timeout=timeout
        )

    if provider == "azure_openai":
        from langchain_openai import AzureChatOpenAI

        return AzureChatOpenAI(
            azure_endpoint=settings.azure_openai_endpoint,
            azure_deployment=model or settings.azure_openai_deployment,
            api_key=settings.azure_openai_api_key,
            api_version=settings.azure_openai_api_version,
            temperature=temperature,
            timeout=timeout,
        )

    if provider == "anthropic":
        from langchain_anthropic import ChatAnthropic

        return ChatAnthropic(
            model=model or settings.anthropic_model, api_key=settings.anthropic_api_key, temperature=temperature, timeout=timeout
        )

    if provider == "gemini":
        from langchain_google_genai import ChatGoogleGenerativeAI

        return ChatGoogleGenerativeAI(
            model=model or settings.gemini_model, google_api_key=settings.google_api_key, temperature=temperature, timeout=timeout
        )

    if provider == "mock":
        return ScriptedChatModel()

    raise ValueError(f"Unsupported LLM provider: {provider}")


async def resolve_model(settings: Settings) -> ResolvedModel:
    """Checks that the configured provider can be used, otherwise uses the mock
    (if LLM_FALLBACK_TO_MOCK is true)."""
    problem = await find_provider_problem(settings)

    if problem is None:
        return ResolvedModel(settings.llm_provider, settings.model_name)

    if not settings.llm_fallback_to_mock:
        raise RuntimeError(problem)

    logger.warning("Can't use %s (%s), using the mock model instead", settings.llm_provider, problem)
    return ResolvedModel("mock", "scripted-demo", fallback_reason=problem)


async def find_provider_problem(settings: Settings) -> str | None:
    provider = settings.llm_provider

    if provider == "mock":
        return None
    if provider == "ollama":
        return await check_ollama(settings)
    if provider == "openai" and not settings.openai_api_key:
        return "OPENAI_API_KEY is not set"
    azure_settings = [settings.azure_openai_endpoint, settings.azure_openai_api_key, settings.azure_openai_deployment]
    if provider == "azure_openai" and not all(azure_settings):
        return "AZURE_OPENAI_ENDPOINT, AZURE_OPENAI_API_KEY and AZURE_OPENAI_DEPLOYMENT must be set"
    if provider == "anthropic" and not settings.anthropic_api_key:
        return "ANTHROPIC_API_KEY is not set"
    if provider == "gemini" and not settings.google_api_key:
        return "GOOGLE_API_KEY is not set"

    return None


async def check_ollama(settings: Settings) -> str | None:
    """Ollama must be running and the model must be downloaded."""
    url = settings.ollama_base_url.rstrip("/") + "/api/tags"

    try:
        async with httpx.AsyncClient(timeout=3.0) as client:
            response = await client.get(url)
            response.raise_for_status()
            models = [m.get("name", "") for m in response.json().get("models", [])]
    except (httpx.HTTPError, ValueError) as error:
        return f"Ollama is not reachable at {settings.ollama_base_url} ({error.__class__.__name__})"

    wanted = settings.model_name
    for name in models:
        # "qwen2.5:7b" or "llama3.2" (which Ollama stores as "llama3.2:latest")
        if name == wanted or name == f"{wanted}:latest":
            return None

    return f"model {wanted} is not pulled yet"
