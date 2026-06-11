import type { Allergen, Dish } from '../lib/types'

export const peanuts: Allergen = { id: 5, name: 'Peanuts', slug: 'peanuts', icon: '🥜' }
export const gluten: Allergen = { id: 1, name: 'Gluten', slug: 'gluten', icon: '🌾' }

export function makeDish(overrides: Partial<Dish> = {}): Dish {
  return {
    id: 6,
    name: 'Ojja Légumes',
    slug: 'ojja-legumes',
    description: 'Eggs poached in a spicy tomato and pepper sauce.',
    price: 11.5,
    currency: 'TND',
    is_vegetarian: true,
    is_vegan: false,
    spicy_level: 2,
    available: true,
    image_url: null,
    category: { id: 1, name: 'Starters', slug: 'starters' },
    allergens: [gluten],
    ...overrides,
  }
}
