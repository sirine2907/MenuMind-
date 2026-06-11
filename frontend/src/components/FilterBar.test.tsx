import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import { gluten, peanuts } from '../test/fixtures'
import { FilterBar } from './FilterBar'

const categories = [{ id: 1, name: 'Starters', slug: 'starters', position: 0, dishes_count: 6 }]

describe('FilterBar', () => {
  it('toggles the vegetarian filter and resets pagination', async () => {
    const onChange = vi.fn()
    render(<FilterBar filters={{ page: 3 }} onChange={onChange} categories={categories} allergens={[peanuts]} />)

    await userEvent.click(screen.getByRole('button', { name: /vegetarian/i }))

    expect(onChange).toHaveBeenCalledWith({ page: 1, vegetarian: true })
  })

  it('adds and removes excluded allergens', async () => {
    const onChange = vi.fn()
    const { rerender } = render(<FilterBar filters={{}} onChange={onChange} categories={[]} allergens={[peanuts, gluten]} />)

    await userEvent.click(screen.getByRole('button', { name: /peanuts/i }))
    expect(onChange).toHaveBeenLastCalledWith({ exclude_allergens: ['peanuts'], page: 1 })

    rerender(<FilterBar filters={{ exclude_allergens: ['peanuts'] }} onChange={onChange} categories={[]} allergens={[peanuts, gluten]} />)
    expect(screen.getByRole('button', { name: /peanuts/i })).toHaveAttribute('aria-pressed', 'true')

    await userEvent.click(screen.getByRole('button', { name: /peanuts/i }))
    expect(onChange).toHaveBeenLastCalledWith({ exclude_allergens: [], page: 1 })
  })

  it('filters by category', async () => {
    const onChange = vi.fn()
    render(<FilterBar filters={{}} onChange={onChange} categories={categories} allergens={[]} />)

    await userEvent.click(screen.getByRole('button', { name: /starters/i }))

    expect(onChange).toHaveBeenCalledWith({ category: 'starters', page: 1 })
  })
})
