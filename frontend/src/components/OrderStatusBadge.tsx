import { cn } from '../lib/format'
import type { OrderStatus } from '../lib/types'
import { Badge } from './ui'

const styles: Record<OrderStatus, string> = {
  pending: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-900',
  confirmed: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:ring-sky-900',
  preparing: 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-950/50 dark:text-violet-300 dark:ring-violet-900',
  ready: 'bg-teal-50 text-teal-700 ring-teal-200 dark:bg-teal-950/50 dark:text-teal-300 dark:ring-teal-900',
  delivered: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950/50 dark:text-green-300 dark:ring-green-900',
  cancelled: 'bg-stone-100 text-stone-600 ring-stone-300 dark:bg-stone-800 dark:text-stone-400 dark:ring-stone-700',
}

export function OrderStatusBadge({ status, label }: { status: OrderStatus; label: string }) {
  return (
    <Badge className={cn(styles[status])}>
      <span className={cn('h-1.5 w-1.5 rounded-full bg-current', status === 'preparing' && 'animate-pulse')} />
      {label}
    </Badge>
  )
}
