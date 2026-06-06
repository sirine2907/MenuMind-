import { Flame, Leaf, Sprout } from 'lucide-react'
import { formatPrice, cn } from '../lib/format'
import type { Allergen, Dish } from '../lib/types'
import { Badge } from './ui'

export function AllergenBadge({ allergen, highlighted = false }: { allergen: Allergen; highlighted?: boolean }) {
  return (
    <Badge
      title={`Contains ${allergen.name.toLowerCase()}`}
      className={cn(
        highlighted
          ? 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-900'
          : 'bg-amber-50 text-amber-800 ring-amber-200/80 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900/60',
      )}
    >
      {allergen.icon && <span aria-hidden>{allergen.icon}</span>}
      {allergen.name}
    </Badge>
  )
}

export function SpicyLevel({ level }: { level: number }) {
  if (level <= 0) return null
  return (
    <span className="inline-flex items-center text-red-500" title={`Spicy level ${level}/3`} aria-label={`Spicy level ${level} of 3`}>
      {Array.from({ length: level }, (_, i) => (
        <Flame key={i} className="h-4 w-4 fill-current" />
      ))}
    </span>
  )
}

export function DishCard({ dish, highlight = false, avoid = [] }: { dish: Dish; highlight?: boolean; avoid?: string[] }) {
  return (
    <article
      id={`dish-${dish.id}`}
      className={cn(
        'card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-md',
        highlight && 'ring-2 ring-brand-500',
        !dish.available && 'opacity-60',
      )}
    >
      <div className="mb-2 flex items-start justify-between gap-3">
        <div>
          {dish.category && <p className="mb-1 text-[11px] font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">{dish.category.name}</p>}
          <h3 className="font-display text-lg font-semibold leading-snug">{dish.name}</h3>
        </div>
        <p className="shrink-0 rounded-lg bg-stone-100 px-2.5 py-1 text-sm font-bold text-stone-900 dark:bg-stone-800 dark:text-white">
          {formatPrice(dish.price)}
        </p>
      </div>

      <p className="mb-4 line-clamp-3 flex-1 text-sm leading-relaxed text-stone-600 dark:text-stone-400">{dish.description}</p>

      <div className="flex flex-wrap items-center gap-1.5">
        {dish.is_vegan ? (
          <Badge className="bg-green-50 text-green-700 ring-green-200 dark:bg-green-950/50 dark:text-green-300 dark:ring-green-900">
            <Sprout className="h-3 w-3" /> Vegan
          </Badge>
        ) : dish.is_vegetarian ? (
          <Badge className="bg-lime-50 text-lime-800 ring-lime-200 dark:bg-lime-950/40 dark:text-lime-300 dark:ring-lime-900">
            <Leaf className="h-3 w-3" /> Vegetarian
          </Badge>
        ) : null}
        <SpicyLevel level={dish.spicy_level} />
        {!dish.available && <Badge className="bg-stone-100 text-stone-600 ring-stone-300">Unavailable</Badge>}
      </div>

      {dish.allergens.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-1.5 border-t border-stone-100 pt-3 dark:border-stone-800" aria-label="Allergens">
          {dish.allergens.map((a) => (
            <AllergenBadge key={a.id} allergen={a} highlighted={avoid.includes(a.slug)} />
          ))}
        </div>
      )}
    </article>
  )
}
