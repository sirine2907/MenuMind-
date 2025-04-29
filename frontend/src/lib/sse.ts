import { ApiError, tokenStore } from './api'

export interface SseEvent {
  event: string
  data: unknown
}

/**
 * Incremental Server-Sent Events parser: feed it text chunks as they arrive,
 * get back the complete events. Partial frames are kept for the next chunk.
 */
export function createSseParser() {
  let buffer = ''

  return (chunk: string): SseEvent[] => {
    buffer += chunk.replace(/\r\n/g, '\n')
    const events: SseEvent[] = []
    let boundary: number

    while ((boundary = buffer.indexOf('\n\n')) !== -1) {
      const frame = buffer.slice(0, boundary)
      buffer = buffer.slice(boundary + 2)

      let event = 'message'
      const data: string[] = []
      for (const line of frame.split('\n')) {
        if (line.startsWith('event:')) event = line.slice(6).trim()
        else if (line.startsWith('data:')) data.push(line.slice(5).trimStart())
      }
      if (data.length === 0) continue

      const raw = data.join('\n')
      try {
        events.push({ event, data: JSON.parse(raw) })
      } catch {
        events.push({ event, data: raw })
      }
    }
    return events
  }
}

/** POSTs a chat message and calls `onEvent` for each SSE event of the reply. */
export async function streamChat(
  body: { message: string; session_id: string },
  onEvent: (event: SseEvent) => void,
  signal?: AbortSignal,
): Promise<void> {
  const token = tokenStore.get()
  const response = await fetch('/api/chat', {
    method: 'POST',
    signal,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'text/event-stream',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ ...body, stream: true }),
  })

  if (!response.ok || !response.body) {
    const error = (await response.json().catch(() => null))?.error
    throw new ApiError(response.status, error?.code ?? 'chat_failed', error?.message ?? 'The assistant is unavailable.')
  }

  const reader = response.body.pipeThrough(new TextDecoderStream()).getReader()
  const parse = createSseParser()

  for (;;) {
    const { value, done } = await reader.read()
    if (done) break
    parse(value).forEach(onEvent)
  }
}
