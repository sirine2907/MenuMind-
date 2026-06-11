import { render, screen, within } from '@testing-library/react'
import { gluten, makeDish, peanuts } from '../test/fixtures'
import { DishCard } from './DishCard'

describe('DishCard', () => {
  it('shows the name, price in TND, category and diet badge', () => {
    render(<DishCard dish={makeDish()} />)

    expect(screen.getByRole('heading', { name: 'Ojja Légumes' })).toBeInTheDocument()
    expect(screen.getByText('11.5 TND')).toBeInTheDocument()
    expect(screen.getByText('Starters')).toBeInTheDocument()
    expect(screen.getByText('Vegetarian')).toBeInTheDocument()
    expect(screen.getByLabelText('Spicy level 2 of 3')).toBeInTheDocument()
  })

  it('renders one badge per allergen', () => {
    render(<DishCard dish={makeDish({ allergens: [gluten, peanuts] })} />)

    const allergens = within(screen.getByLabelText('Allergens'))
    expect(allergens.getByText('Gluten')).toBeInTheDocument()
    expect(allergens.getByText('Peanuts')).toBeInTheDocument()
    expect(allergens.getByTitle('Contains peanuts')).toBeInTheDocument()
  })

  it('prefers the vegan badge over the vegetarian one', () => {
    render(<DishCard dish={makeDish({ is_vegan: true })} />)

    expect(screen.getByText('Vegan')).toBeInTheDocument()
    expect(screen.queryByText('Vegetarian')).not.toBeInTheDocument()
  })

  it('flags unavailable dishes and hides the allergen section when empty', () => {
    render(<DishCard dish={makeDish({ available: false, allergens: [], spicy_level: 0 })} />)

    expect(screen.getByText('Unavailable')).toBeInTheDocument()
    expect(screen.queryByLabelText('Allergens')).not.toBeInTheDocument()
  })
})
