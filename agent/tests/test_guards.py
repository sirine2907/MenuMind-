import pytest
from langchain_core.messages import AIMessage, HumanMessage

from menumind_agent.guards import is_explicit_confirmation, order_was_confirmed


@pytest.mark.parametrize("text", ["yes", "Yes please!", "ok go ahead", "Confirm", "oui", "sounds good", "place the order"])
def test_explicit_confirmations(text: str) -> None:
    assert is_explicit_confirmation(text)


@pytest.mark.parametrize("text", ["what is in the brik?", "no", "maybe later", "I want a pizza", "yesterday was great"])
def test_non_confirmations(text: str) -> None:
    assert not is_explicit_confirmation(text)


def test_confirmation_requires_a_previous_question_from_the_assistant() -> None:
    proposal = AIMessage("2 × Ojja Légumes, total 23 TND. Shall I place this order?")

    assert order_was_confirmed([HumanMessage("I want ojja"), proposal, HumanMessage("yes")])
    assert not order_was_confirmed([HumanMessage("yes")])
    assert not order_was_confirmed([HumanMessage("hi"), AIMessage("Here is the menu."), HumanMessage("yes")])
    assert not order_was_confirmed([HumanMessage("I want ojja"), proposal, HumanMessage("how spicy is it?")])
