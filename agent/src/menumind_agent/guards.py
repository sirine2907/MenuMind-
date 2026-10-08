"""Checks done in code, so we don't have to trust the LLM for them."""

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
    """True if the customer's last message is a "yes" to a question the assistant asked just before."""
    idx = last_human_index(messages)
    if idx <= 0 or not is_explicit_confirmation(text_of(messages[idx])):
        return False

    # find the assistant's last answer before the "yes"
    for message in reversed(messages[:idx]):
        if isinstance(message, AIMessage) and not message.tool_calls:
            return "?" in text_of(message)

    return False
