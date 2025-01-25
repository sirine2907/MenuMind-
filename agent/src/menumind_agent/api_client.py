"""Async client for the MenuMind Laravel API.

Every request carries the end user's own Sanctum token, so the agent can
never do more than the customer it is talking to.
"""

from typing import Any

import httpx


class MenuMindApiError(RuntimeError):
    def __init__(self, status: int, code: str, message: str, details: Any = None):
        super().__init__(message)
        self.status = status
        self.code = code
        self.message = message
        self.details = details

    def to_dict(self) -> dict[str, Any]:
        error: dict[str, Any] = {"status": "error", "code": self.code, "message": self.message}
        if self.details:
            error["details"] = self.details
        return error


class MenuMindApi:
    def __init__(self, base_url: str, token: str | None, timeout: float = 20.0, transport: httpx.AsyncBaseTransport | None = None):
        headers = {"Accept": "application/json"}
        if token:
            headers["Authorization"] = f"Bearer {token}"
        self._client = httpx.AsyncClient(
            base_url=base_url.rstrip("/") + "/",
            headers=headers,
            timeout=timeout,
            transport=transport,
        )

    async def aclose(self) -> None:
        await self._client.aclose()

    async def search_dishes(self, query: str, filters: dict[str, Any] | None = None, limit: int = 8) -> dict[str, Any]:
        params: dict[str, Any] = {"q": query, "limit": limit}
        for key, value in (filters or {}).items():
            if value is None or value == []:
                continue
            if isinstance(value, bool):
                params[key] = "1" if value else "0"
            elif isinstance(value, list):
                params[key] = ",".join(str(v) for v in value)
            else:
                params[key] = value
        return await self._get("search/dishes", params)

    async def check_allergens(self, dish_ids: list[int], allergens: list[str]) -> dict[str, Any]:
        return await self._post("dishes/allergen-check", {"dish_ids": dish_ids, "allergens": allergens})

    async def search_docs(self, query: str, limit: int = 4) -> dict[str, Any]:
        return await self._get("search/docs", {"q": query, "limit": limit})

    async def create_order(self, items: list[dict[str, int]], notes: str | None = None) -> dict[str, Any]:
        payload: dict[str, Any] = {"items": items}
        if notes:
            payload["notes"] = notes
        return await self._post("orders", payload)

    async def get_order(self, order_id: int) -> dict[str, Any]:
        return await self._get(f"orders/{order_id}")

    async def latest_order(self) -> dict[str, Any] | None:
        orders = (await self._get("orders", {"per_page": 1})).get("data") or []
        return orders[0] if orders else None

    async def _get(self, path: str, params: dict[str, Any] | None = None) -> dict[str, Any]:
        return self._handle(await self._client.get(path, params=params))

    async def _post(self, path: str, payload: dict[str, Any]) -> dict[str, Any]:
        return self._handle(await self._client.post(path, json=payload))

    @staticmethod
    def _handle(response: httpx.Response) -> dict[str, Any]:
        if response.is_success:
            return response.json()

        try:
            error = response.json().get("error", {})
        except ValueError:
            error = {}
        raise MenuMindApiError(
            status=response.status_code,
            code=error.get("code", "http_error"),
            message=error.get("message", f"API request failed with HTTP {response.status_code}"),
            details=error.get("details"),
        )
