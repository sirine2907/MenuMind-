from langchain_core.messages import AIMessage, HumanMessage

from menumind_agent.mock_llm import ScriptedChatModel, extract_filters


def test_it_extracts_filters_from_the_showcase_question() -> None:
    filters = extract_filters("I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?")

    assert filters == {"vegetarian": True, "max_price": 25.0, "exclude_allergens": ["peanuts"]}


def test_it_understands_other_phrasings() -> None:
    assert extract_filters("vegan desserts without gluten or milk") == {
        "vegan": True,
        "exclude_allergens": ["gluten", "dairy"],
        "category": "desserts",
    }
    assert extract_filters("something mild, gluten-free please")["exclude_allergens"] == ["gluten"]
    assert extract_filters("something mild")["max_spicy"] == 0


def test_menu_questions_start_with_a_menu_search() -> None:
    message = ScriptedChatModel().decide([HumanMessage("Any vegetarian dishes under 20 TND?")])

    assert message.tool_calls[0]["name"] == "search_menu"
    assert message.tool_calls[0]["args"]["filters"] == {"vegetarian": True, "max_price": 20.0}


def test_practical_questions_go_to_the_documents() -> None:
    message = ScriptedChatModel().decide([HumanMessage("What are your opening hours on Sunday?")])

    assert message.tool_calls[0]["name"] == "search_docs"


def test_status_questions_check_the_order() -> None:
    message = ScriptedChatModel().decide([HumanMessage("Where is my order #12?")])

    assert message.tool_calls[0] == {**message.tool_calls[0], "name": "get_order_status", "args": {"order_id": 12}}


def test_a_confirmation_after_a_proposal_creates_the_order() -> None:
    proposal = AIMessage(
        "Shall I place this order?",
        additional_kwargs={"proposed_order": [{"dish_id": 6, "quantity": 2, "name": "Ojja", "price": 11.5}]},
    )

    message = ScriptedChatModel().decide([HumanMessage("2 ojja"), proposal, HumanMessage("yes")])

    assert message.tool_calls[0]["name"] == "create_order"
    assert message.tool_calls[0]["args"]["items"] == [{"dish_id": 6, "quantity": 2}]


def test_greetings_do_not_call_tools() -> None:
    message = ScriptedChatModel().decide([HumanMessage("Hello!")])

    assert not message.tool_calls
    assert "MenuMind" in message.content
