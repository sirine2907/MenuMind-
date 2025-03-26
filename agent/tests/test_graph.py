import json

import respx
from langchain_core.messages import AIMessage, HumanMessage, SystemMessage, ToolMessage
from langgraph.checkpoint.memory import InMemorySaver

from menumind_agent.api_client import MenuMindApi
from menumind_agent.graph import build_graph, trim_history
from menumind_agent.tools import build_tools

from .conftest import API, FakeToolModel, tool_call

CONFIG = {"configurable": {"thread_id": "t1"}}


def make_graph(model: FakeToolModel):
    return build_graph(model, build_tools(MenuMindApi(API, token="tok")), "SYSTEM", InMemorySaver())


async def test_the_agent_calls_tools_and_answers_from_their_results(api_mock: respx.MockRouter) -> None:
    model = FakeToolModel(
        responses=[
            tool_call(
                "search_menu",
                {"query": "vegetarian", "filters": {"vegetarian": True, "max_price": 25, "exclude_allergens": ["peanuts"]}},
            ),
            tool_call("check_allergens", {"dish_ids": [6, 11], "allergens": ["peanuts"]}, "call_2"),
            AIMessage("Try the Ojja Légumes (11.5 TND)."),
        ],
        calls=[],
    )

    state = await make_graph(model).ainvoke({"messages": [HumanMessage("Vegetarian, no peanuts, under 25?")]}, CONFIG)

    tool_messages = [m for m in state["messages"] if isinstance(m, ToolMessage)]
    assert [m.name for m in tool_messages] == ["search_menu", "check_allergens"]
    assert json.loads(tool_messages[0].content)["dishes"][0]["name"] == "Ojja Légumes"
    assert tool_messages[0].artifact["engine"] == "typesense_hybrid"
    assert state["messages"][-1].content == "Try the Ojja Légumes (11.5 TND)."

    search = api_mock["search"].calls.last.request
    assert search.url.params["exclude_allergens"] == "peanuts"
    # Every LLM call starts with the system prompt.
    assert all(isinstance(call[0], SystemMessage) for call in model.calls)


async def test_create_order_is_blocked_without_explicit_confirmation(api_mock: respx.MockRouter) -> None:
    model = FakeToolModel(
        responses=[
            tool_call("create_order", {"items": [{"dish_id": 6, "quantity": 2}], "confirmed": True}),
            AIMessage("Here is your order summary: 2 × Ojja Légumes, 23 TND. Shall I place it?"),
        ],
        calls=[],
    )

    state = await make_graph(model).ainvoke({"messages": [HumanMessage("I want two ojja")]}, CONFIG)

    blocked = next(m for m in state["messages"] if isinstance(m, ToolMessage))
    assert blocked.artifact["status"] == "confirmation_required"
    assert not api_mock["create_order"].called


async def test_create_order_goes_through_after_the_guest_confirms(api_mock: respx.MockRouter) -> None:
    model = FakeToolModel(
        responses=[
            AIMessage("2 × Ojja Légumes and 1 Citronnade, total 27 TND. Shall I place this order?"),
            tool_call(
                "create_order", {"items": [{"dish_id": 6, "quantity": 2}, {"dish_id": 33, "quantity": 1}], "confirmed": True}
            ),
            AIMessage("Done! Order #42 is placed."),
        ],
        calls=[],
    )
    graph = make_graph(model)

    await graph.ainvoke({"messages": [HumanMessage("2 ojja and a citronnade")]}, CONFIG)
    state = await graph.ainvoke({"messages": [HumanMessage("Yes please")]}, CONFIG)

    assert api_mock["create_order"].called
    payload = json.loads(api_mock["create_order"].calls.last.request.content)
    assert payload["items"] == [{"dish_id": 6, "quantity": 2}, {"dish_id": 33, "quantity": 1}]
    assert state["messages"][-1].content == "Done! Order #42 is placed."


async def test_memory_keeps_the_conversation_per_session(api_mock: respx.MockRouter) -> None:
    model = FakeToolModel(responses=[AIMessage("Ahlan!"), AIMessage("You said hello before.")], calls=[])
    graph = make_graph(model)

    await graph.ainvoke({"messages": [HumanMessage("hello")]}, CONFIG)
    await graph.ainvoke({"messages": [HumanMessage("what did I say?")]}, CONFIG)

    second_call = model.calls[1]
    assert [m.content for m in second_call[1:]] == ["hello", "Ahlan!", "what did I say?"]


async def test_api_errors_are_returned_to_the_model_instead_of_crashing() -> None:
    with respx.mock(base_url=API) as router:
        import httpx

        router.get("/orders/999").mock(
            return_value=httpx.Response(403, json={"error": {"code": "forbidden", "message": "Not yours."}})
        )
        model = FakeToolModel(
            responses=[tool_call("get_order_status", {"order_id": 999}), AIMessage("I can't access that order.")], calls=[]
        )

        state = await make_graph(model).ainvoke({"messages": [HumanMessage("status of order 999")]}, CONFIG)

    tool_message = next(m for m in state["messages"] if isinstance(m, ToolMessage))
    assert json.loads(tool_message.content) == {"status": "error", "code": "forbidden", "message": "Not yours."}


def test_history_trimming_never_starts_with_a_tool_message() -> None:
    history = [
        HumanMessage("a"),
        tool_call("search_docs", {"query": "x"}),
        ToolMessage("{}", tool_call_id="call_1", name="search_docs"),
        AIMessage("b"),
        HumanMessage("c"),
        AIMessage("d"),
    ]

    assert [m.content for m in trim_history(history, 4)] == ["c", "d"]
    assert len(trim_history(history, 50)) == 6
