from typing import Any

from pydantic import BaseModel, Field


class UserContext(BaseModel):
    id: int
    name: str
    role: str = "customer"


class ChatRequest(BaseModel):
    session_id: str = Field(min_length=1, max_length=128)
    message: str = Field(min_length=1, max_length=4000)
    user: UserContext | None = None
    # The end user's Sanctum token, forwarded by the Laravel API.
    api_token: str | None = None


class Step(BaseModel):
    id: str
    tool: str
    label: str
    status: str = "done"
    input: dict[str, Any] = Field(default_factory=dict)
    summary: str | None = None


class ChatResponse(BaseModel):
    session_id: str
    answer: str
    steps: list[Step]
    citations: list[dict[str, Any]]
    provider: str
    model: str
    fallback_reason: str | None = None
