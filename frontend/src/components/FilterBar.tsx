import { Leaf, Search, ShieldAlert, Sprout } from 'lucide-react'
import { cn } from '../lib/format'
import type { Allergen, Category, DishFilters } from '../lib/types'

interface Props {
  filters: DishFilters
  onChange: (filters: DishFilters) => void
  categories: Category[]
  allergens: Allergen[]
}

const chip = (active: boolean) =>
  cn(
    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition',
    active
      ? 'border-brand-600 bg-brand-600 text-white shadow-sm'
      : 'border-stone-300 bg-white text-stone-700 hover:border-stone-400 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-300',
  )

export function FilterBar({ filters, onChange, categories, allergens }: Props) {
  const set = (patch: Partial<DishFilters>) => onChange({ ...filters, ...patch, page: 1 })
  const excluded = filters.exclude_allergens ?? []

  const toggleAllergen = (slug: string) =>
    set({ exclude_allergens: excluded.includes(slug) ? excluded.filter((s) => s !== slug) : [...excluded, slug] })

  return (
    <div className="card space-y-4 p-4 sm:p-5">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <label className="relative flex-1">
          <span className="sr-only">Search dishes</span>
          <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
          <input
            className="input pl-10"
            placeholder="Search couscous, brik, ojja…"
            value={filters.search ?? ''}
            onChange={(e) => set({ search: e.target.value })}
          />
        </label>

        <div className="flex flex-wrap items-center gap-2">
          <button type="button" className={chip(!!filters.vegetarian)} onClick={() => set({ vegetarian: filters.vegetarian ? undefined : true })} aria-pressed={!!filters.vegetarian}>
            <Leaf className="h-4 w-4" /> Vegetarian
          </button>
          <button type="button" className={chip(!!filters.vegan)} onClick={() => set({ vegan: filters.vegan ? undefined : true })} aria-pressed={!!filters.vegan}>
            <Sprout className="h-4 w-4" /> Vegan
          </button>

          <label className="flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-900">
            <span className="font-medium text-stone-700 dark:text-stone-300">Max</span>
            <input
              type="range"
              min={3}
              max={35}
              step={1}
              value={filters.max_price ?? 35}
              onChange={(e) => set({ max_price: Number(e.target.value) >= 35 ? undefined : Number(e.target.value) })}
              className="w-24 accent-brand-600"
              aria-label="Maximum price"
            />
            <span className="w-14 text-right font-semibold tabular-nums">{filters.max_price ? `${filters.max_price} TND` : 'Any'}</span>
          </label>

          <select
            className="input w-auto rounded-full py-1.5"
            value={filters.sort ?? 'menu'}
            onChange={(e) => set({ sort: e.target.value as DishFilters['sort'] })}
            aria-label="Sort dishes"
          >
            <option value="menu">Menu order</option>
            <option value="name">Name</option>
            <option value="price_asc">Price ↑</option>
            <option value="price_desc">Price ↓</option>
            <option value="spicy">Spiciest</option>
          </select>
        </div>
      </div>

      <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        <button type="button" className={chip(!filters.category)} onClick={() => set({ category: undefined })}>
          All
        </button>
        {categories.map((c) => (
          <button key={c.id} type="button" className={chip(filters.category === c.slug)} onClick={() => set({ category: c.slug })}>
            {c.name}
            {c.dishes_count !== undefined && <span className="text-xs opacity-70">{c.dishes_count}</span>}
          </button>
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <span className="mr-1 inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 dark:text-stone-400">
          <ShieldAlert className="h-4 w-4 text-red-500" /> Exclude allergens:
        </span>
        {allergens.map((a) => {
          const active = excluded.includes(a.slug)
          return (
            <button
              key={a.id}
              type="button"
              onClick={() => toggleAllergen(a.slug)}
              aria-pressed={active}
              className={cn(
                'rounded-full border px-2.5 py-1 text-xs font-medium transition',
                active
                  ? 'border-red-500 bg-red-500 text-white line-through decoration-2'
                  : 'border-stone-300 text-stone-600 hover:border-red-300 dark:border-stone-700 dark:text-stone-400',
              )}
            >
              {a.icon} {a.name}
            </button>
          )
        })}
      </div>
    </div>
  )
}
