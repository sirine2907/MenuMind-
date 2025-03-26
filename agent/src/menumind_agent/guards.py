"""Deterministic safety checks that do not rely on the LLM behaving."""

import re
from collections.abc import Sequence

from langchain_core.messages import AIMessage, AnyMessage, HumanMessage

CONFIRM_PATTERN = re.compile(
    r"^\s*(yes|yep|yeah|yup|sure|ok(ay)?|confirm(ed)?|go ahead|please do|do it|place (it|the order)|"
    r"that'?s (right|correct|perfect)|sounds good|oui|d'accord|vas-y|confirme|ey+|behi)\b",
    re.IGNORECASE,
)


def text_of(message: AnyMessage) -> str:
    content = message.content
    if isinstance(content, str):
        return content
    return " ".join(part.get("text", "") for part in content if isinstance(part, dict))


def last_human_index(messages: Sequence[AnyMessage]) -> int:
    for i in range(len(messages) - 1, -1, -1):
        if isinstance(messages[i], HumanMessage):
            return i
    return -1


def is_explicit_confirmation(text: str) -> bool:
    return bool(CONFIRM_PATTERN.search(text))


def order_was_confirmed(messages: Sequence[AnyMessage]) -> bool:
    """True when the guest's latest message explicitly confirms a proposal the
    assistant made in its previous reply. Used to block `create_order` calls the
    model might make on its own initiative."""
    idx = last_human_index(messages)
    if idx <= 0 or not is_explicit_confirmation(text_of(messages[idx])):
        return False

    previous_ai = next(
        (m for m in reversed(messages[:idx]) if isinstance(m, AIMessage) and not m.tool_calls),
        None,
    )
    return previous_ai is not None and "?" in text_of(previous_ai)
