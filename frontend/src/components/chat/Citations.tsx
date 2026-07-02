import { FileText, Receipt, UtensilsCrossed } from 'lucide-react'
import { Link } from 'react-router'
import type { Citation } from '../../lib/types'

const icons = {
  dish: UtensilsCrossed,
  document: FileText,
  order: Receipt,
}

/** Sources the answer was grounded in: dishes, document passages, orders. */
export function Citations({ citations }: { citations: Citation[] }) {
  if (citations.length === 0) return null

  return (
    <div className="mt-3 border-t border-stone-200 pt-3 dark:border-stone-700/70">
      <p className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-stone-500">Sources</p>
      <ul className="flex flex-wrap gap-1.5">
        {citations.map((citation, i) => {
          const Icon = icons[citation.type]
          const body = (
            <>
              <span className="grid h-4 w-4 shrink-0 place-items-center rounded bg-brand-100 text-[10px] font-bold text-brand-700 dark:bg-brand-900/60 dark:text-brand-200">
                {i + 1}
              </span>
              <Icon className="h-3.5 w-3.5 shrink-0 text-stone-500" aria-hidden />
              <span className="font-medium">{citation.title}</span>
              {citation.subtitle && <span className="text-stone-500 dark:text-stone-400">· {citation.subtitle}</span>}
            </>
          )
          const className =
            'inline-flex max-w-full items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs text-stone-700 transition hover:border-brand-300 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-200'

          return (
            <li key={`${citation.type}-${citation.id}`} title={citation.snippet}>
              {citation.url ? (
                <Link to={citation.url} className={className}>
                  {body}
                </Link>
              ) : (
                <span className={className}>{body}</span>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}
