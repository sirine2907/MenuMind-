import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import type { ChatMessage } from '../../lib/types'
import { MessageBubble } from './MessageBubble'

function assistant(overrides: Partial<ChatMessage>): ChatMessage {
  return { id: 'a1', role: 'assistant', content: '', steps: [], citations: [], status: 'done', ...overrides }
}

const renderBubble = (message: ChatMessage) =>
  render(
    <MemoryRouter>
      <MessageBubble message={message} />
    </MemoryRouter>,
  )

describe('MessageBubble', () => {
  it('shows tool steps while the agent is working', () => {
    renderBubble(
      assistant({
        status: 'streaming',
        steps: [
          { id: '1', tool: 'search_menu', label: '🔎 Searching menu…', status: 'done', summary: '6 dishes found' },
          { id: '2', tool: 'check_allergens', label: '🧪 Checking allergens…', status: 'running' },
        ],
      }),
    )

    expect(screen.getByText('🔎 Searching menu…')).toBeInTheDocument()
    expect(screen.getByText('· 6 dishes found')).toBeInTheDocument()
    expect(screen.getByLabelText('running')).toBeInTheDocument()
    expect(screen.getByLabelText('done')).toBeInTheDocument()
  })

  it('renders markdown answers with numbered, linked citations', () => {
    renderBubble(
      assistant({
        content: 'Try the **Ojja Légumes**.',
        citations: [
          { type: 'dish', id: 6, title: 'Ojja Légumes', subtitle: '11.5 TND', url: '/menu?dish=6' },
          { type: 'document', id: '1-2', title: 'Allergen & Dietary Policy', subtitle: 'Peanuts and tree nuts', snippet: 'We never cook with peanut oil.' },
        ],
      }),
    )

    expect(screen.getByText('Ojja Légumes', { selector: 'strong' })).toBeInTheDocument()
    expect(screen.getByText('Sources')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /ojja légumes/i })).toHaveAttribute('href', '/menu?dish=6')
    expect(screen.getByTitle('We never cook with peanut oil.')).toHaveTextContent('Allergen & Dietary Policy')
  })

  it('hides citations until the answer is complete', () => {
    renderBubble(assistant({ status: 'streaming', content: 'Here are', citations: [{ type: 'dish', id: 1, title: 'Brik' }] }))

    expect(screen.queryByText('Sources')).not.toBeInTheDocument()
  })

  it('displays errors', () => {
    renderBubble(assistant({ status: 'error', content: 'The assistant is currently unavailable.' }))

    expect(screen.getByText('The assistant is currently unavailable.')).toBeInTheDocument()
  })
})
