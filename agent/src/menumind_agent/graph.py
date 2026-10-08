"""The LangGraph agent.

START -> agent -> (tool calls?) -> tools -> agent -> ... -> END

The "agent" node asks the LLM what to do, the "tools" node runs the tools.
create_order is blocked if the customer did not say yes, even if the LLM asks for it.
"""

import json
from collections.abc import Sequence
from typing import Annotated, Any, TypedDict

from langchain_core.language_models import BaseChatModel
from langchain_core.messages import AIMessage, AnyMessage, HumanMessage, SystemMessage, ToolMessage
from langchain_core.runnables import RunnableConfig
from langchain_core.tools import BaseTool
from langgraph.checkpoint.base import BaseCheckpointSaver
from langgraph.graph import END, START, StateGraph
from langgraph.graph.message import add_messages
from langgraph.graph.state import CompiledStateGraph

from .guards import order_was_confirmed


class AgentState(TypedDict):
    messages: Annotated[list[AnyMessage], add_messages]


def trim_history(messages: Sequence[AnyMessage], limit: int) -> list[AnyMessage]:
    """Keeps the last ``limit`` messages without starting mid tool-exchange."""
    recent = list(messages[-limit:])
    while recent and not isinstance(recent[0], HumanMessage):
        recent.pop(0)
    return recent


def build_graph(
    llm: BaseChatModel,
    tools: list[BaseTool],
    system_prompt: str,
    checkpointer: BaseCheckpointSaver | None = None,
    history_limit: int = 24,
) -> CompiledStateGraph:
    tools_by_name = {t.name: t for t in tools}
    model = llm.bind_tools(tools)

    async def agent(state: AgentState, config: RunnableConfig) -> dict[str, Any]:
        messages = [SystemMessage(system_prompt), *trim_history(state["messages"], history_limit)]
        response = await model.ainvoke(messages, config)
        return {"messages": [response]}

    async def run_tools(state: AgentState, config: RunnableConfig) -> dict[str, Any]:
        last = state["messages"][-1]
        assert isinstance(last, AIMessage)
        outputs: list[ToolMessage] = []

        for call in last.tool_calls:
            name = call["name"]

            if name not in tools_by_name:
                outputs.append(_tool_error(call, f"Unknown tool {name}."))
                continue

            if name == "create_order" and not order_was_confirmed(state["messages"]):
                payload = {
                    "status": "confirmation_required",
                    "message": "The order was NOT placed. Show the guest the order summary with the total and ask them to confirm explicitly.",
                }
                outputs.append(ToolMessage(json.dumps(payload), tool_call_id=call["id"], name=name, artifact=payload))
                continue

            try:
                outputs.append(await tools_by_name[name].ainvoke({**call, "type": "tool_call"}, config))
            except Exception as exc:  # noqa: BLE001 - surface any tool failure to the model
                outputs.append(_tool_error(call, f"{exc.__class__.__name__}: {exc}"))

        return {"messages": outputs}

    def route(state: AgentState) -> str:
        last = state["messages"][-1]
        return "tools" if isinstance(last, AIMessage) and last.tool_calls else END

    graph = StateGraph(AgentState)
    graph.add_node("agent", agent)
    graph.add_node("tools", run_tools)
    graph.add_edge(START, "agent")
    graph.add_conditional_edges("agent", route, {"tools": "tools", END: END})
    graph.add_edge("tools", "agent")

    return graph.compile(checkpointer=checkpointer)


def _tool_error(call: dict[str, Any], message: str) -> ToolMessage:
    payload = {"status": "error", "message": message}
    return ToolMessage(json.dumps(payload), tool_call_id=call["id"], name=call["name"], artifact=payload, status="error")
