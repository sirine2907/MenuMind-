import { createSseParser } from './sse'

describe('createSseParser', () => {
  it('parses complete events with JSON payloads', () => {
    const parse = createSseParser()

    expect(parse('event: step\ndata: {"tool":"search_menu","status":"running"}\n\nevent: token\ndata: {"text":"Hi"}\n\n')).toEqual([
      { event: 'step', data: { tool: 'search_menu', status: 'running' } },
      { event: 'token', data: { text: 'Hi' } },
    ])
  })

  it('buffers frames split across network chunks', () => {
    const parse = createSseParser()

    expect(parse('event: tok')).toEqual([])
    expect(parse('en\ndata: {"text":"Sa')).toEqual([])
    expect(parse('ha"}\n\nevent: done\ndata: {}\n')).toEqual([{ event: 'token', data: { text: 'Saha' } }])
    expect(parse('\n')).toEqual([{ event: 'done', data: {} }])
  })

  it('handles CRLF line endings and non-JSON data', () => {
    const parse = createSseParser()

    expect(parse('data: plain text\r\n\r\n')).toEqual([{ event: 'message', data: 'plain text' }])
  })
})
