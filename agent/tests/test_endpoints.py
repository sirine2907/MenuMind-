import json

import pytest
import respx
from fastapi.testclient import TestClient

from menumind_agent.config import Settings, get_settings
from menumind_agent.main import app

PAYLOAD = {
    "session_id": "u2:demo",
    "message": "I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?",
    "user": {"id": 2, "name": "Yasmine", "role": "customer"},
    "api_token": "2|token",
}


@pytest.fixture
def client(settings: Settings, api_mock: respx.MockRouter):
    app.dependency_overrides[get_settings] = lambda: settings
    with TestClient(app) as test_client:
        test_client.app.state.chat.settings = settings
        test_client.app.state.chat.settings.llm_timeout_seconds = 10
        yield test_client
    app.dependency_overrides.clear()


def parse_sse(body: str) -> list[tuple[str, dict]]:
    events = []
    for frame in body.strip().split("\n\n"):
        lines = dict(line.split(": ", 1) for line in frame.splitlines())
        events.append((lines["event"], json.loads(lines["data"])))
    return events


def test_health_reports_the_active_provider(client: TestClient) -> None:
    body = client.get("/health").json()

    assert body["status"] == "ok"
    assert body["active_provider"] == "mock"


def test_chat_requires_the_shared_secret(client: TestClient) -> None:
    assert client.post("/chat", json=PAYLOAD).status_code == 401
    assert client.post("/chat", json=PAYLOAD, headers={"X-Agent-Secret": "wrong"}).status_code == 401


def test_chat_returns_a_grounded_answer_with_steps_and_citations(client: TestClient) -> None:
    response = client.post("/chat", json=PAYLOAD, headers={"X-Agent-Secret": "s3cret"})

    assert response.status_code == 200
    body = response.json()
    assert [s["tool"] for s in body["steps"]] == ["search_menu", "check_allergens", "search_docs"]
    assert body["steps"][0]["input"]["filters"] == {"vegetarian": True, "max_price": 25.0, "exclude_allergens": ["peanuts"]}
    assert "Ojja Légumes" in body["answer"]
    # Drinks are not suggested when the guest asked for dishes.
    assert "Citronnade" not in body["answer"]
    assert {c["title"] for c in body["citations"]} >= {"Ojja Légumes", "Allergen & Dietary Policy"}
    assert body["provider"] == "mock"


def test_chat_stream_emits_steps_tokens_citations_and_done(client: TestClient) -> None:
    response = client.post("/chat/stream", json=PAYLOAD, headers={"X-Agent-Secret": "s3cret"})

    assert response.status_code == 200
    assert response.headers["content-type"].startswith("text/event-stream")

    events = parse_sse(response.text)
    kinds = [kind for kind, _ in events]
    assert kinds[0] == "meta"
    assert kinds[-2:] == ["citations", "done"]

    steps = [data for kind, data in events if kind == "step"]
    assert [(s["tool"], s["status"]) for s in steps] == [
        ("search_menu", "running"),
        ("search_menu", "done"),
        ("check_allergens", "running"),
        ("check_allergens", "done"),
        ("search_docs", "running"),
        ("search_docs", "done"),
    ]
    assert steps[0]["label"] == "🔎 Searching menu…"

    streamed = "".join(data["text"] for kind, data in events if kind == "token")
    assert streamed == events[-1][1]["answer"]


def test_invalid_payloads_are_rejected(client: TestClient) -> None:
    response = client.post("/chat", json={"session_id": "", "message": ""}, headers={"X-Agent-Secret": "s3cret"})

    assert response.status_code == 422
