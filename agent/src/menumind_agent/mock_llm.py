"""A scripted, deterministic chat model used when no real LLM is available.

It is NOT a canned-answer bot: it follows the same protocol as a real
tool-calling model. It reads the conversation, emits real tool calls, which
the graph executes against the live API, then writes its answer only from the
tool results. That keeps the demo grounded and lets the whole pipeline
(LangGraph, tools, citations, streaming, UI) be exercised without a model.
"""

import asyncio
import json
import re
import unicodedata
import uuid
from collections.abc import AsyncIterator, Iterator, Sequence
from typing import Any

from langchain_core.callbacks import AsyncCallbackManagerForLLMRun, CallbackManagerForLLMRun
from langchain_core.language_models import BaseChatModel
from langchain_core.messages import AIMessage, AIMessageChunk, AnyMessage, BaseMessage, ToolMessage
from langchain_core.outputs import ChatGeneration, ChatGenerationChunk, ChatResult

from .guards import is_explicit_confirmation, last_human_index, text_of

ALLERGEN_WORDS = {
    "peanut": "peanuts",
    "peanuts": "peanuts",
    "nut": "nuts",
    "nuts": "nuts",
    "gluten": "gluten",
    "wheat": "gluten",
    "dairy": "dairy",
    "milk": "dairy",
    "lactose": "dairy",
    "egg": "eggs",
    "eggs": "eggs",
    "fish": "fish",
    "shellfish": "shellfish",
    "shrimp": "crustaceans",
    "sesame": "sesame",
    "soy": "soy",
    "celery": "celery",
    "mustard": "mustard",
    "almond": "tree-nuts",
    "almonds": "tree-nuts",
}
CATEGORY_WORDS = {
    "dessert": "desserts",
    "desserts": "desserts",
    "sweet": "desserts",
    "drink": "drinks",
    "drinks": "drinks",
    "pizza": "pizzas",
    "pizzas": "pizzas",
    "salad": "salads",
    "salads": "salads",
    "soup": "soups",
    "soups": "soups",
    "starter": "starters",
    "starters": "starters",
}
NUMBER_WORDS = {"a": 1, "an": 1, "one": 1, "two": 2, "three": 3, "four": 4, "five": 5, "six": 6}
DOC_KEYWORDS = re.compile(
    r"\b(hours?|open|opening|close|closing|deliver(y|ies)?|fees?|zones?|payments?|pay|cards?|cash|d17|"
    r"reservations?|book|parking|located|address|where are you|ramadan|eid|children|kids|cancel|"
    r"cross[- ]?contamination|polic(y|ies)|oil|fryer|minimum|how long)\b",
    re.IGNORECASE,
)
STATUS_PATTERN = re.compile(r"\b(status|where is my|track|has my order|is my order)\b", re.IGNORECASE)
ORDER_PATTERN = re.compile(r"\b(order|i'?ll take|i will take|i'?d like|i want|get me|can i (get|have))\b", re.IGNORECASE)
DECLINE_PATTERN = re.compile(r"^\s*(no|nope|cancel|not now|never ?mind|non)\b", re.IGNORECASE)
GREETING_PATTERN = re.compile(r"^\s*(hi|hello|hey|salam|aslema|bonjour|good (morning|evening))\b[\s!.]*$", re.IGNORECASE)


class ScriptedChatModel(BaseChatModel):
    """Rule-based stand-in for a tool-calling LLM (LLM_PROVIDER=mock)."""

    token_delay: float = 0.012

    @property
    def _llm_type(self) -> str:
        return "menumind-scripted"

    def bind_tools(self, tools: Sequence[Any], **kwargs: Any) -> "ScriptedChatModel":
        # The script knows the MenuMind tools by name, schemas are not needed.
        return self

    # ------------------------------------------------------------ LangChain API

    def _generate(
        self,
        messages: list[BaseMessage],
        stop: list[str] | None = None,
        run_manager: CallbackManagerForLLMRun | None = None,
        **kwargs: Any,
    ) -> ChatResult:
        return ChatResult(generations=[ChatGeneration(message=self.decide(messages))])

    def _stream(
        self,
        messages: list[BaseMessage],
        stop: list[str] | None = None,
        run_manager: CallbackManagerForLLMRun | None = None,
        **kwargs: Any,
    ) -> Iterator[ChatGenerationChunk]:
        for chunk in self._chunks(self.decide(messages)):
            if run_manager and chunk.message.content:
                run_manager.on_llm_new_token(str(chunk.message.content), chunk=chunk)
            yield chunk

    async def _astream(
        self,
        messages: list[BaseMessage],
        stop: list[str] | None = None,
        run_manager: AsyncCallbackManagerForLLMRun | None = None,
        **kwargs: Any,
    ) -> AsyncIterator[ChatGenerationChunk]:
        for chunk in self._chunks(self.decide(messages)):
            if run_manager and chunk.message.content:
                await run_manager.on_llm_new_token(str(chunk.message.content), chunk=chunk)
            yield chunk
            if chunk.message.content and self.token_delay:
                await asyncio.sleep(self.token_delay)

    def _chunks(self, message: AIMessage) -> Iterator[ChatGenerationChunk]:
        if message.tool_calls:
            yield ChatGenerationChunk(
                message=AIMessageChunk(
                    content="",
                    additional_kwargs=message.additional_kwargs,
                    tool_call_chunks=[
                        {"name": c["name"], "args": json.dumps(c["args"]), "id": c["id"], "index": i, "type": "tool_call_chunk"}
                        for i, c in enumerate(message.tool_calls)
                    ],
                )
            )
            return

        # Stream the answer word by word, like a real model would.
        words = re.findall(r"\S+\s*|\s+", str(message.content))
        for i, word in enumerate(words):
            yield ChatGenerationChunk(
                message=AIMessageChunk(
                    content=word,
                    additional_kwargs=message.additional_kwargs if i == 0 else {},
                )
            )

    # ------------------------------------------------------------------ script

    def decide(self, messages: Sequence[AnyMessage]) -> AIMessage:
        idx = last_human_index(messages)
        if idx < 0:
            return AIMessage(content="Hello! How can I help you today?")

        human = text_of(messages[idx]).strip()
        turn = list(messages[idx + 1 :])
        results = {m.name: _parse(m) for m in turn if isinstance(m, ToolMessage)}
        proposal = _last_proposal(messages[:idx])

        if proposal and is_explicit_confirmation(human):
            if "create_order" not in results:
                return _call(
                    "create_order",
                    {
                        "items": [{"dish_id": i["dish_id"], "quantity": i["quantity"]} for i in proposal],
                        "confirmed": True,
                    },
                )
            return AIMessage(content=_order_placed(results["create_order"]))

        if proposal and DECLINE_PATTERN.search(human):
            return AIMessage(content="No problem, I haven't placed anything. Is there something else you'd like to try?")

        if GREETING_PATTERN.search(human):
            return AIMessage(
                content=(
                    "Ahlan! 👋 I'm MenuMind, the assistant of Dar El Menu. I can recommend dishes "
                    "(vegetarian, spicy, allergen-free…), answer questions about delivery and opening hours, "
                    "and place your order. What are you in the mood for?"
                )
            )

        if STATUS_PATTERN.search(human):
            if "get_order_status" not in results:
                order_id = re.search(r"#?\b(\d{1,7})\b", human)
                return _call("get_order_status", {"order_id": int(order_id.group(1))} if order_id else {})
            return AIMessage(content=_order_status(results["get_order_status"]))

        if DOC_KEYWORDS.search(human) and not _mentions_menu(human):
            if "search_docs" not in results:
                return _call("search_docs", {"query": human})
            return AIMessage(content=_docs_answer(human, results["search_docs"]))

        if ORDER_PATTERN.search(human) and not re.search(r"\bwhat\b|\?", human):
            if "search_menu" not in results:
                return _call("search_menu", {"query": _strip_order_words(human)})
            return _propose_order(human, results["search_menu"])

        return self._menu_flow(human, results)

    def _menu_flow(self, human: str, results: dict[str, Any]) -> AIMessage:
        filters = extract_filters(human)
        allergies = filters.get("exclude_allergens", [])

        if "search_menu" not in results:
            return _call("search_menu", {"query": human, "filters": filters or None})

        dishes = results["search_menu"].get("dishes", [])
        # Asked for "dishes": recommend food, not coffee, unless drinks were requested.
        if filters.get("category") != "drinks" and not re.search(r"\b(drink|juice|tea|coffee|beverage)", human, re.I):
            dishes = [d for d in dishes if d.get("category") != "Drinks"] or dishes

        if allergies and dishes and "check_allergens" not in results:
            return _call("check_allergens", {"dish_ids": [d["id"] for d in dishes[:6]], "allergens": allergies})

        if allergies and dishes and "search_docs" not in results:
            return _call("search_docs", {"query": f"{' '.join(allergies)} allergy cross-contamination kitchen policy"})

        return AIMessage(content=_menu_answer(filters, dishes, results.get("check_allergens"), results.get("search_docs")))


# ---------------------------------------------------------------- helpers


def _call(name: str, args: dict[str, Any]) -> AIMessage:
    return AIMessage(
        content="", tool_calls=[{"name": name, "args": args, "id": f"call_{uuid.uuid4().hex[:12]}", "type": "tool_call"}]
    )


def _parse(message: ToolMessage) -> dict[str, Any]:
    try:
        return json.loads(text_of(message))
    except (TypeError, ValueError):
        return {}


def _last_proposal(history: Sequence[AnyMessage]) -> list[dict[str, Any]] | None:
    for message in reversed(history):
        if isinstance(message, AIMessage) and not message.tool_calls:
            return message.additional_kwargs.get("proposed_order")
    return None


def _mentions_menu(text: str) -> bool:
    return bool(re.search(r"\b(dish|dishes|menu|eat|vegetarian|vegan|recommend|couscous|brik|pizza|dessert)\b", text, re.I))


def extract_filters(text: str) -> dict[str, Any]:
    lowered = text.lower()
    filters: dict[str, Any] = {}

    if re.search(r"\bvegan\b", lowered):
        filters["vegan"] = True
    elif re.search(r"\b(vegetarian|veggie|meatless|no meat)\b", lowered):
        filters["vegetarian"] = True

    price = re.search(r"(?:under|below|less than|max(?:imum)?|up to|cheaper than|<)\s*(\d+(?:[.,]\d+)?)", lowered)
    if price:
        filters["max_price"] = float(price.group(1).replace(",", "."))

    allergies: list[str] = []
    for clause in re.findall(
        r"(?:allergic to|allergy to|allergies to|intolerant to|no|without|free of|free from)\s+([a-z ,]+?)(?:[.?!;]|,? (?:what|which|can|could|and i|i )|$)",
        lowered,
    ):
        for word in re.findall(r"[a-z]+", clause):
            if word in ALLERGEN_WORDS and ALLERGEN_WORDS[word] not in allergies:
                allergies.append(ALLERGEN_WORDS[word])
    for word, allergen in ALLERGEN_WORDS.items():
        if re.search(rf"\b{word}[- ]free\b", lowered) and allergen not in allergies:
            allergies.append(allergen)
    if allergies:
        filters["exclude_allergens"] = allergies

    if re.search(r"\b(not spicy|mild|no spice|not too spicy)\b", lowered):
        filters["max_spicy"] = 0 if "not too" not in lowered else 1

    for word, category in CATEGORY_WORDS.items():
        if re.search(rf"\b{word}\b", lowered):
            filters["category"] = category
            break

    return filters


def _ascii(text: str) -> str:
    return unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode().lower()


def _fmt_price(value: float) -> str:
    return f"{value:g} TND"


def _dish_line(dish: dict[str, Any]) -> str:
    tags = []
    if dish.get("vegan"):
        tags.append("vegan")
    elif dish.get("vegetarian"):
        tags.append("vegetarian")
    if dish.get("spicy_level"):
        tags.append("🌶️" * dish["spicy_level"])
    allergens = dish.get("allergens") or []
    tags.append(f"contains {', '.join(allergens)}" if allergens else "no listed allergens")
    return f"- **{dish['name']}** — {_fmt_price(dish['price_tnd'])} · {' · '.join(tags)}"


def _describe_filters(filters: dict[str, Any]) -> str:
    parts = []
    if filters.get("vegan"):
        parts.append("vegan")
    elif filters.get("vegetarian"):
        parts.append("vegetarian")
    noun = {
        "desserts": "desserts",
        "drinks": "drinks",
        "pizzas": "pizzas",
        "salads": "salads",
        "soups": "soups",
        "starters": "starters",
    }.get(filters.get("category", ""), "dishes")
    text = " ".join(parts + [noun])
    if "max_price" in filters:
        text += f" under {_fmt_price(filters['max_price'])}"
    if filters.get("exclude_allergens"):
        text += f" without {' or '.join(filters['exclude_allergens'])}"
    return text


def _sentences_with(passages: list[dict[str, Any]], keywords: list[str], limit: int = 2) -> list[tuple[str, str]]:
    found: list[tuple[str, str]] = []
    for passage in passages:
        for sentence in re.split(r"(?<=[.!?])\s+", passage["text"]):
            if any(k in sentence.lower() for k in keywords) and len(found) < limit:
                found.append((passage["source"], sentence.strip()))
    return found


def _menu_answer(
    filters: dict[str, Any], dishes: list[dict[str, Any]], check: dict[str, Any] | None, docs: dict[str, Any] | None
) -> str:
    if not dishes:
        return (
            f"I couldn't find any {_describe_filters(filters)} on our menu right now. "
            "Would you like me to relax one of the constraints, for example the budget?"
        )

    allergies = filters.get("exclude_allergens", [])
    unsafe_ids: set[int] = set()
    if check:
        unsafe_ids = {r["dish_id"] for r in check.get("results", []) if r.get("found") and not r.get("safe")}
    safe = [d for d in dishes if d["id"] not in unsafe_ids][:6]

    lines = [f"Here {'is' if len(safe) == 1 else 'are'} {len(safe)} {_describe_filters(filters)} you can order:", ""]
    lines += [_dish_line(d) for d in safe]

    if allergies and check:
        lines += ["", f"✅ I double-checked each of them against our allergen data: none contains {' or '.join(allergies)}."]

    if allergies and docs:
        quotes = _sentences_with(docs.get("passages", []), [a.rstrip("s") for a in allergies] + ["oil"], limit=2)
        if quotes:
            source = quotes[0][0].split(" › ")[0]
            lines += [
                "",
                f"⚠️ From our *{source}*: "
                + " ".join(q[1] for q in quotes)
                + " If your allergy is severe, please mention it to the staff when ordering.",
            ]

    cheapest = min(safe, key=lambda d: d["price_tnd"])
    lines += [
        "",
        f"Would you like me to order one of these? The **{cheapest['name']}** is a great choice at {_fmt_price(cheapest['price_tnd'])}.",
    ]
    return "\n".join(lines)


def _strip_order_words(text: str) -> str:
    text = re.sub(
        r"\b(i'?d like to order|i'?d like|i'?ll take|i will take|i want|please|order|get me|can i (get|have)|the|and|a|an|some|x)\b",
        " ",
        text,
        flags=re.I,
    )
    text = re.sub(r"\b\d+\b|\b(one|two|three|four|five|six)\b", " ", text, flags=re.I)
    return " ".join(text.split()) or "popular dishes"


def _quantity_for(dish_name: str, text: str) -> int:
    first_word = re.escape(dish_name.split()[0].lower())
    match = re.search(rf"\b(\d+|a|an|one|two|three|four|five|six)\s*x?\s+(?:the\s+)?{first_word}", text.lower())
    if not match:
        return 1
    value = match.group(1)
    return int(value) if value.isdigit() else NUMBER_WORDS.get(value, 1)


def _propose_order(human: str, menu: dict[str, Any]) -> AIMessage:
    dishes = menu.get("dishes", [])
    if not dishes:
        return AIMessage(content="I couldn't find that dish on our menu. Could you tell me which dish you'd like?")

    # A dish is chosen when every significant word of its name was mentioned.
    said = set(re.findall(r"\w+", _ascii(human)))
    chosen = [
        d
        for d in dishes
        if (words := [w for w in re.findall(r"\w+", _ascii(d["name"])) if len(w) > 3]) and all(w in said for w in words)
    ]
    chosen = chosen[:4] or dishes[:1]

    items = [
        {"dish_id": d["id"], "name": d["name"], "price": d["price_tnd"], "quantity": _quantity_for(d["name"], human)}
        for d in chosen
    ]
    total = sum(i["price"] * i["quantity"] for i in items)

    lines = ["Here is your order summary:", ""]
    lines += [f"- {i['quantity']} × **{i['name']}** — {_fmt_price(i['price'] * i['quantity'])}" for i in items]
    lines += ["", f"**Total: {_fmt_price(round(total, 3))}**", "", "Shall I place this order? (yes / no)"]
    return AIMessage(content="\n".join(lines), additional_kwargs={"proposed_order": items})


def _order_placed(result: dict[str, Any]) -> str:
    if result.get("status") != "created":
        return f"I couldn't place the order: {result.get('message', 'unknown error')}. Nothing was charged."
    order = result["order"]
    items = "\n".join(f"- {i['quantity']} × {i['dish_name']}" for i in order["items"])
    return (
        f"✅ Your order **#{order['id']}** has been placed!\n\n{items}\n\n"
        f"**Total: {_fmt_price(order['total'])}** · Status: **{order['status_label']}**\n\n"
        'You can follow it on the Orders page, or just ask me "where is my order?". Saha! 🍽️'
    )


def _order_status(order: dict[str, Any]) -> str:
    if "id" not in order:
        return order.get("message", "I couldn't find that order.")
    items = ", ".join(f"{i['quantity']} × {i['dish_name']}" for i in order.get("items", []))
    return f"Order **#{order['id']}** is currently **{order['status_label']}**. It contains {items} for a total of {_fmt_price(order['total'])}."


def _docs_answer(question: str, docs: dict[str, Any]) -> str:
    passages = docs.get("passages", [])
    if not passages:
        return "I couldn't find that in our documents. Please call us on +216 71 740 123 and the team will help you."

    keywords = [
        w
        for w in re.findall(r"[a-z]{4,}", question.lower())
        if w not in {"what", "when", "your", "does", "with", "much", "have", "there"}
    ]
    quotes = _sentences_with(passages, keywords, limit=3) or [(passages[0]["source"], passages[0]["text"])]
    source = quotes[0][0]
    body = " ".join(q[1] for q in quotes)
    return f"{body}\n\n_Source: {source}._ Anything else I can help you with?"
