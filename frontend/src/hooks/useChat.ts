import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useRef, useState } from 'react'
import { ApiError } from '../lib/api'
import { streamChat, type SseEvent } from '../lib/sse'
import type { ChatMessage, Citation, ToolStep } from '../lib/types'
import { keys } from './queries'

const SESSION_KEY = 'menumind-chat-session'

function newSessionId(): string {
  return (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`).replace(/[^A-Za-z0-9-]/g, '')
}

function storedSession(): string {
  try {
    const existing = sessionStorage.getItem(SESSION_KEY)
    if (existing) return existing
    const id = newSessionId()
    sessionStorage.setItem(SESSION_KEY, id)
    return id
  } catch {
    return newSessionId()
  }
}

export interface ModelInfo {
  provider: string
  model: string
  fallback_reason?: string | null
}

/** Chat state machine fed by the agent's SSE stream. */
export function useChat() {
  const queryClient = useQueryClient()
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [model, setModel] = useState<ModelInfo | null>(null)
  const [busy, setBusy] = useState(false)
  const sessionId = useRef(storedSession())
  const abort = useRef<AbortController | null>(null)

  const patchLast = useCallback((patch: (m: ChatMessage) => ChatMessage) => {
    setMessages((all) => all.map((m, i) => (i === all.length - 1 ? patch(m) : m)))
  }, [])

  const handleEvent = useCallback(
    ({ event, data }: SseEvent) => {
      const payload = data as Record<string, unknown>
      switch (event) {
        case 'meta':
          setModel(payload as unknown as ModelInfo)
          break
        case 'step': {
          const step = payload as unknown as ToolStep
          patchLast((m) => ({
            ...m,
            steps: m.steps.some((s) => s.id === step.id) ? m.steps.map((s) => (s.id === step.id ? { ...s, ...step } : s)) : [...m.steps, step],
          }))
          if (step.tool === 'create_order' && step.status === 'done') queryClient.invalidateQueries({ queryKey: keys.orders })
          break
        }
        case 'token':
          patchLast((m) => ({ ...m, content: m.content + String(payload.text ?? '') }))
          break
        case 'reset':
          // The model timed out and the fallback model is finishing the turn:
          // drop the partial text but keep the tool steps already completed.
          patchLast((m) => ({ ...m, content: '' }))
          break
        case 'citations':
          patchLast((m) => ({ ...m, citations: (payload.citations as Citation[]) ?? [] }))
          break
        case 'done':
          patchLast((m) => ({ ...m, content: String(payload.answer ?? m.content), status: 'done' }))
          break
        case 'error':
          patchLast((m) => ({ ...m, content: String(payload.message ?? 'Something went wrong.'), status: 'error' }))
          break
      }
    },
    [patchLast, queryClient],
  )

  const send = useCallback(
    async (text: string) => {
      const message = text.trim()
      if (!message || busy) return

      const now = Date.now()
      setMessages((all) => [
        ...all,
        { id: `u-${now}`, role: 'user', content: message, steps: [], citations: [], status: 'done' },
        { id: `a-${now}`, role: 'assistant', content: '', steps: [], citations: [], status: 'streaming' },
      ])
      setBusy(true)
      abort.current = new AbortController()

      try {
        await streamChat({ message, session_id: sessionId.current }, handleEvent, abort.current.signal)
        // A stream that ends without a "done" event still needs closing.
        patchLast((m) => (m.status === 'streaming' ? { ...m, status: m.content ? 'done' : 'error', content: m.content || 'No answer received.' } : m))
      } catch (error) {
        if ((error as Error).name === 'AbortError') return
        const text = error instanceof ApiError ? error.message : 'Cannot reach the assistant.'
        patchLast((m) => ({ ...m, content: text, status: 'error' }))
      } finally {
        setBusy(false)
      }
    },
    [busy, handleEvent, patchLast],
  )

  const reset = useCallback(() => {
    abort.current?.abort()
    sessionId.current = newSessionId()
    try {
      sessionStorage.setItem(SESSION_KEY, sessionId.current)
    } catch {
      /* ignore */
    }
    setMessages([])
    setBusy(false)
  }, [])

  return { messages, send, busy, reset, model }
}
