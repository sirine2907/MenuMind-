import { MessageCircle, Receipt } from 'lucide-react'
import { Link } from 'react-router'
import { OrderStatusBadge } from '../components/OrderStatusBadge'
import { Button, EmptyState, ErrorMessage, Spinner } from '../components/ui'
import { useOrders } from '../hooks/queries'
import { formatDate, formatPrice } from '../lib/format'

export function OrdersPage() {
  const orders = useOrders()

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <div>
        <h1 className="font-display text-3xl font-bold">Your orders</h1>
        <p className="mt-1 text-stone-500 dark:text-stone-400">Statuses refresh automatically. You can also ask the assistant “where is my order?”.</p>
      </div>

      {orders.isError && <ErrorMessage error={orders.error} />}
      {orders.isPending ? (
        <Spinner />
      ) : orders.data?.data.length === 0 ? (
        <EmptyState icon={<Receipt className="h-6 w-6" />} title="No orders yet">
          <p>Ask MenuMind for a recommendation and order in the chat.</p>
          <Link to="/chat" className="mt-4 inline-block">
            <Button>
              <MessageCircle className="h-4 w-4" /> Open the assistant
            </Button>
          </Link>
        </EmptyState>
      ) : (
        <ul className="space-y-4">
          {orders.data?.data.map((order, index) => (
            <li key={order.id} className={index === 0 ? 'card p-5 ring-2 ring-brand-500/40' : 'card p-5'}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-display text-lg font-semibold">Order #{order.id}</p>
                  <p className="text-sm text-stone-500">{formatDate(order.created_at)}</p>
                </div>
                <OrderStatusBadge status={order.status} label={order.status_label} />
              </div>

              <ul className="mt-4 divide-y divide-stone-100 text-sm dark:divide-stone-800">
                {order.items.map((item) => (
                  <li key={item.dish_id} className="flex justify-between py-2">
                    <span>
                      <span className="font-semibold">{item.quantity} ×</span> {item.dish_name}
                    </span>
                    <span className="tabular-nums text-stone-600 dark:text-stone-400">{formatPrice(item.subtotal)}</span>
                  </li>
                ))}
              </ul>

              {order.notes && <p className="mt-2 rounded-lg bg-stone-50 px-3 py-2 text-sm italic text-stone-600 dark:bg-stone-800/60 dark:text-stone-400">“{order.notes}”</p>}

              <div className="mt-3 flex justify-end border-t border-stone-100 pt-3 dark:border-stone-800">
                <p className="text-base font-bold">Total {formatPrice(order.total)}</p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
