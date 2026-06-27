import { ChevronLeft, ChevronRight, MessageCircle, SearchX } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { DishCard } from '../components/DishCard'
import { FilterBar } from '../components/FilterBar'
import { Button, EmptyState, ErrorMessage, Spinner } from '../components/ui'
import { useAllergens, useCategories, useDishes } from '../hooks/queries'
import { useDebounced } from '../hooks/useDebounced'
import type { DishFilters } from '../lib/types'

export function MenuPage() {
  const [params] = useSearchParams()
  const highlighted = Number(params.get('dish')) || null
  const [filters, setFilters] = useState<DishFilters>({ sort: 'menu', page: 1 })
  const debounced = useDebounced(filters, 250)

  const dishes = useDishes(debounced)
  const categories = useCategories()
  const allergens = useAllergens()

  // Deep links from chat citations scroll to the cited dish.
  useEffect(() => {
    if (highlighted && dishes.data) document.getElementById(`dish-${highlighted}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }, [highlighted, dishes.data])

  const meta = dishes.data?.meta

  return (
    <div className="space-y-6">
      <section className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <h1 className="font-display text-3xl font-bold sm:text-4xl">Our menu</h1>
          <p className="mt-1 text-stone-500 dark:text-stone-400">Tunisian home cooking from La Marsa. Prices in Tunisian dinars.</p>
        </div>
        <Link to="/chat">
          <Button variant="secondary">
            <MessageCircle className="h-4 w-4" /> Not sure? Ask MenuMind
          </Button>
        </Link>
      </section>

      <FilterBar filters={filters} onChange={setFilters} categories={categories.data ?? []} allergens={allergens.data ?? []} />

      {dishes.isError && <ErrorMessage error={dishes.error} />}
      {dishes.isPending ? (
        <Spinner label="Loading the menu…" />
      ) : dishes.data && dishes.data.data.length === 0 ? (
        <EmptyState icon={<SearchX className="h-6 w-6" />} title="No dish matches these filters">
          Try removing a filter, or ask the assistant for an alternative.
        </EmptyState>
      ) : (
        <>
          <p className="text-sm text-stone-500" aria-live="polite">
            {meta?.total} dish{meta?.total === 1 ? '' : 'es'}
          </p>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {dishes.data?.data.map((dish) => (
              <DishCard key={dish.id} dish={dish} highlight={dish.id === highlighted} avoid={filters.exclude_allergens} />
            ))}
          </div>
        </>
      )}

      {meta && meta.last_page > 1 && (
        <nav className="flex items-center justify-center gap-3" aria-label="Pagination">
          <Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => setFilters({ ...filters, page: meta.current_page - 1 })}>
            <ChevronLeft className="h-4 w-4" /> Previous
          </Button>
          <span className="text-sm text-stone-500">
            Page {meta.current_page} of {meta.last_page}
          </span>
          <Button variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => setFilters({ ...filters, page: meta.current_page + 1 })}>
            Next <ChevronRight className="h-4 w-4" />
          </Button>
        </nav>
      )}
    </div>
  )
}
