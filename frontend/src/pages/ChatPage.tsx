import { Cpu, RotateCcw, SendHorizonal, Sparkles } from 'lucide-react'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { MessageBubble } from '../components/chat/MessageBubble'
import { Button } from '../components/ui'
import { useAuth } from '../context/AuthContext'
import { useChat } from '../hooks/useChat'

const SUGGESTIONS = [
  "I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?",
  'Something warm and spicy without meat?',
  'Do you deliver to Carthage? How much is it?',
  'Where is my order?',
]

export function ChatPage() {
  const { user } = useAuth()
  const { messages, send, busy, reset, model } = useChat()
  const [input, setInput] = useState('')
  const endRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' })
  }, [messages])

  function submit(e?: FormEvent) {
    e?.preventDefault()
    if (!input.trim()) return
    send(input)
    setInput('')
  }

  return (
    <div className="mx-auto flex h-[calc(100vh-7.5rem)] max-w-4xl flex-col md:h-[calc(100vh-6.5rem)]">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold sm:text-3xl">Ask MenuMind</h1>
          <p className="text-sm text-stone-500 dark:text-stone-400">Answers grounded in our live menu and house documents.</p>
        </div>
        <div className="flex items-center gap-2">
          {model && (
            <span
              className="hidden items-center gap-1.5 rounded-full border border-stone-200 bg-white px-2.5 py-1 text-xs text-stone-600 sm:inline-flex dark:border-stone-700 dark:bg-stone-900 dark:text-stone-300"
              title={model.fallback_reason ? `Fallback: ${model.fallback_reason}` : undefined}
            >
              <Cpu className="h-3.5 w-3.5" />
              {model.provider === 'mock' ? 'Demo model (offline)' : `${model.provider} · ${model.model}`}
            </span>
          )}
          <Button variant="ghost" onClick={reset} disabled={messages.length === 0} aria-label="New conversation">
            <RotateCcw className="h-4 w-4" /> <span className="hidden sm:inline">New chat</span>
          </Button>
        </div>
      </div>

      <div className="card flex min-h-0 flex-1 flex-col overflow-hidden">
        <div className="flex-1 space-y-5 overflow-y-auto p-4 sm:p-6" aria-live="polite">
          {messages.length === 0 ? (
            <div className="flex h-full flex-col items-center justify-center text-center">
              <div className="mb-4 rounded-2xl bg-brand-50 p-4 text-brand-600 dark:bg-brand-900/30 dark:text-brand-300">
                <Sparkles className="h-7 w-7" />
              </div>
              <h2 className="font-display text-xl font-semibold">Ahlan{user ? `, ${user.name.split(' ')[0]}` : ''}! What are you craving?</h2>
              <p className="mt-1 max-w-md text-sm text-stone-500">
                I can recommend dishes, check allergens, answer questions about delivery and hours, and place your order.
              </p>
              <div className="mt-6 grid w-full max-w-2xl gap-2 sm:grid-cols-2">
                {SUGGESTIONS.map((s) => (
                  <button
                    key={s}
                    onClick={() => send(s)}
                    className="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-left text-sm text-stone-700 transition hover:border-brand-300 hover:bg-brand-50 dark:border-stone-700 dark:bg-stone-800/50 dark:text-stone-300 dark:hover:bg-stone-800"
                  >
                    {s}
                  </button>
                ))}
              </div>
            </div>
          ) : (
            messages.map((m) => <MessageBubble key={m.id} message={m} />)
          )}
          <div ref={endRef} />
        </div>

        <form onSubmit={submit} className="border-t border-stone-200 p-3 sm:p-4 dark:border-stone-800">
          <div className="flex items-end gap-2">
            <textarea
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) submit(e)
              }}
              rows={1}
              placeholder="Ask about dishes, allergens, delivery… or say “I'd like to order”"
              className="input max-h-40 min-h-[46px] resize-none"
              aria-label="Message"
            />
            <Button type="submit" disabled={!input.trim() || busy} loading={busy} className="h-[46px] w-[46px] shrink-0 p-0" aria-label="Send">
              {!busy && <SendHorizonal className="h-5 w-5" />}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
