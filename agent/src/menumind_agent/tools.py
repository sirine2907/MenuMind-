"""Tools the agent can call. Each one is a thin, typed wrapper over the API.

Tools return ``(content, artifact)``: the compact JSON *content* is what the
LLM reads, the raw *artifact* is kept for citations and the UI.
"""

import json
from typing import Any

from langchain_core.tools import BaseTool, tool
from pydantic import BaseModel, Field

from .api_client import MenuMindApi, MenuMindApiError

TOOL_LABELS = {
    "search_menu": "🔎 Searching menu…",
    "check_allergens": "🧪 Checking allergens…",
    "search_docs": "📄 Reading restaurant documents…",
    "create_order": "🧾 Placing order…",
    "get_order_status": "📦 Checking order status…",
}


class MenuFilters(BaseModel):
    vegetarian: bool | None = Field(None, description="Only vegetarian dishes")
    vegan: bool | None = Field(None, description="Only vegan dishes")
    min_price: float | None = Field(None, description="Minimum price in TND")
    max_price: float | None = Field(None, description="Maximum price in TND")
    max_spicy: int | None = Field(None, ge=0, le=3, description="Maximum spiciness from 0 (mild) to 3 (very hot)")
    exclude_allergens: list[str] | None = Field(
        None, description="Allergens the dish must NOT contain, e.g. ['peanuts', 'gluten', 'dairy']"
    )
    category: str | None = Field(
        None, description="Category slug: starters, soups, mains, salads, pizzas, desserts or drinks"
    )


class OrderLine(BaseModel):
    dish_id: int = Field(description="Dish id as returned by search_menu")
    quantity: int = Field(1, ge=1, le=20)


def _dump(data: Any) -> str:
    return json.dumps(data, ensure_ascii=False, separators=(",", ":"))


def _error(exc: MenuMindApiError) -> tuple[str, dict[str, Any]]:
    payload = exc.to_dict()
    return _dump(payload), payload


def build_tools(api: MenuMindApi) -> list[BaseTool]:
    @tool(response_format="content_and_artifact")
    async def search_menu(query: str, filters: MenuFilters | None = None) -> tuple[str, dict[str, Any]]:
        """Search the restaurant menu with hybrid (keyword + semantic) search.

        Use it for ANY question about dishes, prices, ingredients or recommendations.
        `query` describes what the guest wants in natural language; structured
        constraints (diet, budget, allergies, spiciness, category) go in `filters`.
        """
        try:
            result = (await api.search_dishes(query, filters.model_dump(exclude_none=True) if filters else None, limit=10))["data"]
        except MenuMindApiError as exc:
            return _error(exc)

        hits = [
            {
                "id": d["id"],
                "name": d["name"],
                "price_tnd": d["price"],
                "category": d.get("category"),
                "vegetarian": d["is_vegetarian"],
                "vegan": d["is_vegan"],
                "spicy_level": d["spicy_level"],
                "allergens": d["allergens"],
                "description": d["description"],
            }
            for d in result["hits"]
        ]
        content = {"count": len(hits), "dishes": hits}
        if not hits:
            content["note"] = "No dish matches. Do not invent alternatives; tell the guest and suggest relaxing a constraint."
        return _dump(content), result

    @tool(response_format="content_and_artifact")
    async def check_allergens(dish_ids: list[int], allergens: list[str]) -> tuple[str, dict[str, Any]]:
        """Verify specific dishes against the guest's allergies using the restaurant's allergen data.

        Always call this before confirming that a dish is safe for someone with an allergy.
        """
        try:
            result = (await api.check_allergens(dish_ids, allergens))["data"]
        except MenuMindApiError as exc:
            return _error(exc)
        return _dump(result), result

    @tool(response_format="content_and_artifact")
    async def search_docs(query: str) -> tuple[str, dict[str, Any]]:
        """Search the restaurant's documents (opening hours, delivery zones and fees,
        payment methods, allergen and cross-contamination policy, reservations...).

        Use it for any question that is not about a specific dish.
        """
        try:
            result = (await api.search_docs(query))["data"]
        except MenuMindApiError as exc:
            return _error(exc)

        passages = [
            {"source": h["document_title"] + (f" › {h['heading']}" if h.get("heading") else ""), "text": h["content"]}
            for h in result["hits"]
        ]
        content: dict[str, Any] = {"count": len(passages), "passages": passages}
        if not passages:
            content["note"] = "Nothing relevant in the documents. Say you don't know and suggest calling the restaurant."
        return _dump(content), result

    @tool(response_format="content_and_artifact")
    async def create_order(items: list[OrderLine], notes: str | None = None, confirmed: bool = False) -> tuple[str, dict[str, Any]]:
        """Place an order for the current guest.

        ONLY call this after you showed the guest a summary (dishes, quantities,
        total) and they explicitly confirmed it in their last message. Set
        `confirmed` to true only in that case.
        """
        try:
            result = (await api.create_order([i.model_dump() for i in items], notes))["data"]
        except MenuMindApiError as exc:
            return _error(exc)
        return _dump({"status": "created", "order": result}), result

    @tool(response_format="content_and_artifact")
    async def get_order_status(order_id: int | None = None) -> tuple[str, dict[str, Any]]:
        """Get the status of one of the guest's orders. Without an id, returns their most recent order."""
        try:
            if order_id is None:
                order = await api.latest_order()
                if order is None:
                    payload = {"status": "not_found", "message": "This guest has no orders yet."}
                    return _dump(payload), payload
            else:
                order = (await api.get_order(order_id))["data"]
        except MenuMindApiError as exc:
            return _error(exc)
        return _dump(order), order

    return [search_menu, check_allergens, search_docs, create_order, get_order_status]
