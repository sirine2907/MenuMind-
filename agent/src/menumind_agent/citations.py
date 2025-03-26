"""Turns tool artifacts from one conversation turn into user-facing sources."""

import unicodedata
from collections.abc import Sequence
from typing import Any

from langchain_core.messages import AnyMessage, ToolMessage


def _normalize(text: str) -> str:
    stripped = unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode()
    return " ".join(stripped.lower().replace("’", "'").split())


def collect_citations(turn: Sequence[AnyMessage], answer: str) -> list[dict[str, Any]]:
    dishes: list[dict[str, Any]] = []
    documents: list[dict[str, Any]] = []
    orders: list[dict[str, Any]] = []

    for message in turn:
        if not isinstance(message, ToolMessage) or not isinstance(message.artifact, dict):
            continue
        artifact = message.artifact

        if message.name == "search_menu":
            for hit in artifact.get("hits", []):
                dishes.append(
                    {
                        "type": "dish",
                        "id": hit["id"],
                        "title": hit["name"],
                        "subtitle": f"{hit['price']:g} TND",
                        "url": f"/menu?dish={hit['id']}",
                    }
                )
        elif message.name == "search_docs":
            for hit in artifact.get("hits", []):
                documents.append(
                    {
                        "type": "document",
                        "id": hit["chunk_id"],
                        "title": hit["document_title"],
                        "subtitle": hit.get("heading"),
                        "snippet": hit["content"][:240],
                    }
                )
        elif message.name in ("create_order", "get_order_status") and "id" in artifact:
            orders.append(
                {
                    "type": "order",
                    "id": artifact["id"],
                    "title": f"Order #{artifact['id']}",
                    "subtitle": artifact.get("status_label"),
                    "url": "/orders",
                }
            )

    # Only cite dishes the answer actually talks about.
    normalized_answer = _normalize(answer)
    mentioned = [d for d in dishes if _normalize(d["title"]) in normalized_answer]

    # Documents: the two best passages, one citation per document section.
    return _dedupe(mentioned) + _dedupe(documents, key=("title", "subtitle"))[:2] + _dedupe(orders)


def _dedupe(items: list[dict[str, Any]], key: tuple[str, ...] = ("type", "id")) -> list[dict[str, Any]]:
    seen: set[tuple[Any, ...]] = set()
    unique = []
    for item in items:
        key_value = tuple(item.get(k) for k in key)
        if key_value in seen:
            continue
        seen.add(key_value)
        unique.append(item)
    return unique


def summarize_tool_result(tool: str, artifact: Any) -> str:
    """One-line summary displayed under a tool step in the chat UI."""
    if not isinstance(artifact, dict):
        return "done"
    if artifact.get("status") == "error":
        return f"failed: {artifact.get('message', 'error')}"
    if artifact.get("status") == "confirmation_required":
        return "waiting for your confirmation"

    match tool:
        case "search_menu":
            count = len(artifact.get("hits", []))
            engine = "hybrid search" if artifact.get("engine") == "typesense_hybrid" else "keyword search"
            return f"{count} dish{'es' if count != 1 else ''} found ({engine})"
        case "check_allergens":
            results = [r for r in artifact.get("results", []) if r.get("found")]
            safe = sum(1 for r in results if r.get("safe"))
            return f"{safe}/{len(results)} dishes safe"
        case "search_docs":
            count = len(artifact.get("hits", []))
            return f"{count} passage{'s' if count != 1 else ''} retrieved"
        case "create_order":
            return f"order #{artifact.get('id')} created"
        case "get_order_status":
            return artifact.get("status_label") or artifact.get("message", "done")
    return "done"
