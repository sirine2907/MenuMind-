import { useState, type FormEvent } from 'react'
import { Button, ErrorMessage } from '../../components/ui'
import { useAllergens, useCategories, useSaveDish, type DishInput } from '../../hooks/queries'
import { ApiError } from '../../lib/api'
import type { Dish } from '../../lib/types'

export function DishForm({ dish, onDone }: { dish: Dish | null; onDone: () => void }) {
  const categories = useCategories()
  const allergens = useAllergens()
  const save = useSaveDish()

  const [form, setForm] = useState<DishInput>({
    name: dish?.name ?? '',
    description: dish?.description ?? '',
    price: dish?.price ?? 10,
    category_id: dish?.category?.id ?? null,
    is_vegetarian: dish?.is_vegetarian ?? false,
    is_vegan: dish?.is_vegan ?? false,
    spicy_level: dish?.spicy_level ?? 0,
    available: dish?.available ?? true,
    allergens: dish?.allergens.map((a) => a.slug) ?? [],
  })

  const error = save.error instanceof ApiError ? save.error : null
  const set = <K extends keyof DishInput>(key: K, value: DishInput[K]) => setForm((f) => ({ ...f, [key]: value }))

  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate({ id: dish?.id, data: form }, { onSuccess: onDone })
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      {error && !error.details && <ErrorMessage error={error} />}

      <label className="block">
        <span className="label">Name</span>
        <input className="input" value={form.name} onChange={(e) => set('name', e.target.value)} required />
        {error?.fieldError('name') && <span className="text-xs text-red-600">{error.fieldError('name')}</span>}
      </label>

      <label className="block">
        <span className="label">Description</span>
        <textarea className="input" rows={3} value={form.description} onChange={(e) => set('description', e.target.value)} required />
      </label>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <label className="block">
          <span className="label">Price (TND)</span>
          <input className="input" type="number" step="0.5" min="0" value={form.price} onChange={(e) => set('price', Number(e.target.value))} />
          {error?.fieldError('price') && <span className="text-xs text-red-600">{error.fieldError('price')}</span>}
        </label>
        <label className="block">
          <span className="label">Category</span>
          <select className="input" value={form.category_id ?? ''} onChange={(e) => set('category_id', e.target.value ? Number(e.target.value) : null)}>
            <option value="">None</option>
            {categories.data?.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="label">Spiciness</span>
          <select className="input" value={form.spicy_level} onChange={(e) => set('spicy_level', Number(e.target.value))}>
            {['Mild', '🌶️', '🌶️🌶️', '🌶️🌶️🌶️'].map((label, level) => (
              <option key={level} value={level}>
                {label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="flex flex-wrap gap-4 text-sm">
        {(['is_vegetarian', 'is_vegan', 'available'] as const).map((key) => (
          <label key={key} className="inline-flex items-center gap-2">
            <input type="checkbox" className="h-4 w-4 accent-brand-600" checked={form[key]} onChange={(e) => set(key, e.target.checked)} />
            {{ is_vegetarian: 'Vegetarian', is_vegan: 'Vegan', available: 'Available' }[key]}
          </label>
        ))}
      </div>

      <fieldset>
        <legend className="label">Allergens</legend>
        <div className="flex flex-wrap gap-1.5">
          {allergens.data?.map((a) => {
            const active = form.allergens.includes(a.slug)
            return (
              <button
                type="button"
                key={a.id}
                aria-pressed={active}
                onClick={() => set('allergens', active ? form.allergens.filter((s) => s !== a.slug) : [...form.allergens, a.slug])}
                className={
                  active
                    ? 'rounded-full border border-amber-500 bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200'
                    : 'rounded-full border border-stone-300 px-2.5 py-1 text-xs text-stone-600 dark:border-stone-700 dark:text-stone-400'
                }
              >
                {a.icon} {a.name}
              </button>
            )
          })}
        </div>
      </fieldset>

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={save.isPending}>
          {dish ? 'Save changes' : 'Create dish'}
        </Button>
      </div>
    </form>
  )
}
