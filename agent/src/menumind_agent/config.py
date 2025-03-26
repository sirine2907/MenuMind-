from functools import lru_cache
from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict

Provider = Literal["ollama", "openai", "azure_openai", "anthropic", "gemini", "mock"]


class Settings(BaseSettings):
    """Runtime configuration, read from environment variables (or .env)."""

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    # --- LLM -----------------------------------------------------------------
    llm_provider: Provider = "ollama"
    # Overrides the provider-specific default model below when set.
    llm_model: str | None = None
    llm_temperature: float = 0.2
    # A turn taking longer than this is considered stuck.
    llm_timeout_seconds: float = 120.0
    # If the configured provider is unreachable, answer with the scripted mock
    # model instead of failing, so the demo always works.
    llm_fallback_to_mock: bool = True

    ollama_base_url: str = "http://localhost:11434"
    ollama_model: str = "qwen2.5:7b"
    ollama_num_ctx: int = 8192

    openai_api_key: str | None = None
    openai_model: str = "gpt-4.1-mini"

    azure_openai_endpoint: str | None = None
    azure_openai_api_key: str | None = None
    azure_openai_deployment: str | None = None
    azure_openai_api_version: str = "2024-10-21"

    anthropic_api_key: str | None = None
    anthropic_model: str = "claude-opus-5-5"

    google_api_key: str | None = None
    gemini_model: str = "gemini-2.5-flash"

    # --- MenuMind API ---------------------------------------------------------
    laravel_api_url: str = "http://localhost:8080/api"
    api_timeout_seconds: float = 20.0
    # When set, /chat endpoints require the X-Agent-Secret header.
    agent_shared_secret: str | None = None

    # --- Conversation ---------------------------------------------------------
    restaurant_name: str = "Dar El Menu"
    max_history_messages: int = 24
    max_agent_steps: int = 8

    @property
    def model_name(self) -> str:
        if self.llm_model:
            return self.llm_model
        return {
            "ollama": self.ollama_model,
            "openai": self.openai_model,
            "azure_openai": self.azure_openai_deployment or "azure-deployment",
            "anthropic": self.anthropic_model,
            "gemini": self.gemini_model,
            "mock": "scripted-demo",
        }[self.llm_provider]


@lru_cache
def get_settings() -> Settings:
    return Settings()
