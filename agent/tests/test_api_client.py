import httpx
import pytest
import respx

from menumind_agent.api_client import MenuMindApi, MenuMindApiError

from .conftest import API


async def test_search_serializes_filters_for_laravel(api_mock: respx.MockRouter) -> None:
    api = MenuMindApi(API, token="1|abc")

    await api.search_dishes(
        "vegetarian", {"vegetarian": True, "max_price": 25.0, "exclude_allergens": ["peanuts", "gluten"], "vegan": None}
    )

    request = api_mock["search"].calls.last.request
    assert request.headers["Authorization"] == "Bearer 1|abc"
    assert request.url.params["vegetarian"] == "1"
    assert request.url.params["max_price"] == "25.0"
    assert request.url.params["exclude_allergens"] == "peanuts,gluten"
    assert "vegan" not in request.url.params
    await api.aclose()


async def test_api_errors_are_mapped_to_the_laravel_error_envelope() -> None:
    with respx.mock(base_url=API) as router:
        router.post("/orders").mock(
            return_value=httpx.Response(
                422,
                json={
                    "error": {
                        "status": 422,
                        "code": "dish_unavailable",
                        "message": "Some dishes are unavailable.",
                        "details": {"dishes": ["Juice"]},
                    }
                },
            )
        )
        api = MenuMindApi(API, token=None)

        with pytest.raises(MenuMindApiError) as error:
            await api.create_order([{"dish_id": 1, "quantity": 1}])

    assert error.value.status == 422
    assert error.value.code == "dish_unavailable"
    assert error.value.to_dict()["details"] == {"dishes": ["Juice"]}


async def test_latest_order_returns_none_when_the_guest_has_no_orders() -> None:
    with respx.mock(base_url=API) as router:
        router.get("/orders").mock(return_value=httpx.Response(200, json={"data": []}))
        assert await MenuMindApi(API, token="t").latest_order() is None
