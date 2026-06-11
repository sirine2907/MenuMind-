import {
  CheckCircle2,
  Database,
  FileText,
  FileUp,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  TrendingUp,
  UtensilsCrossed,
  XCircle,
} from 'lucide-react'
import { useRef, useState, type ReactNode } from 'react'
import { OrderStatusBadge } from '../../components/OrderStatusBadge'
import { Button, ErrorMessage, Modal, Spinner } from '../../components/ui'
import {
  useAdminStats,
  useAllDishes,
  useDeleteDish,
  useDeleteDocument,
  useDocuments,
  useOrders,
  useReindex,
  useUpdateOrderStatus,
  useUploadDocument,
} from '../../hooks/queries'
import { cn, formatDate, formatPrice } from '../../lib/format'
import type { Dish, OrderStatus } from '../../lib/types'
import { DishForm } from './DishForm'

const NEXT_STATUS: Partial<Record<OrderStatus, OrderStatus>> = {
  pending: 'confirmed',
  confirmed: 'preparing',
  preparing: 'ready',
  ready: 'delivered',
}

export function AdminPage() {
  const stats = useAdminStats()
  const reindex = useReindex()

  return (
    <div className="space-y-8">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <h1 className="font-display text-3xl font-bold">Back office</h1>
          <p className="mt-1 text-stone-500 dark:text-stone-400">Menu, knowledge base and search index for Dar El Menu.</p>
        </div>
        <Button onClick={() => reindex.mutate()} loading={reindex.isPending} variant="secondary">
          <RefreshCw className="h-4 w-4" /> Reindex search
        </Button>
      </div>

      {reindex.isSuccess && (
        <p role="status" className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/50 dark:text-green-300">
          Reindexed {reindex.data.data.indexed.dishes} dishes and {reindex.data.data.indexed.doc_chunks} document chunks in{' '}
          {(reindex.data.data.duration_ms / 1000).toFixed(1)}s.
        </p>
      )}
      {reindex.isError && <ErrorMessage error={reindex.error} />}

      {stats.data ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard icon={<UtensilsCrossed />} label="Dishes on the menu" value={`${stats.data.dishes.available}`} hint={`${stats.data.dishes.total} in total`} />
          <StatCard
            icon={<FileText />}
            label="Knowledge base"
            value={`${stats.data.documents.total} docs`}
            hint={`${stats.data.documents.chunks} chunks for RAG`}
          />
          <StatCard
            icon={<TrendingUp />}
            label="Orders today"
            value={`${stats.data.orders.today}`}
            hint={`${formatPrice(stats.data.orders.revenue_today)} · ${stats.data.orders.pending} pending`}
          />
          <StatCard
            icon={<Search />}
            label="Search engine"
            value={stats.data.search.healthy ? 'Hybrid' : 'SQL fallback'}
            hint={stats.data.search.healthy ? `Typesense · ${stats.data.search.embedding_model}` : 'Typesense unreachable'}
            status={stats.data.search.healthy}
          />
        </div>
      ) : (
        <Spinner />
      )}

      <div className="grid gap-8 xl:grid-cols-5">
        <div className="xl:col-span-3">
          <DishesPanel />
        </div>
        <div className="space-y-8 xl:col-span-2">
          <OrdersPanel />
          <DocumentsPanel />
        </div>
      </div>
    </div>
  )
}

function StatCard({ icon, label, value, hint, status }: { icon: ReactNode; label: string; value: string; hint: string; status?: boolean }) {
  return (
    <div className="card p-5">
      <div className="flex items-center justify-between">
        <span className="rounded-xl bg-brand-50 p-2 text-brand-600 dark:bg-brand-900/30 dark:text-brand-300 [&>svg]:h-5 [&>svg]:w-5">{icon}</span>
        {status !== undefined &&
          (status ? <CheckCircle2 className="h-5 w-5 text-green-600" aria-label="healthy" /> : <XCircle className="h-5 w-5 text-amber-500" aria-label="degraded" />)}
      </div>
      <p className="mt-4 text-sm text-stone-500 dark:text-stone-400">{label}</p>
      <p className="font-display text-2xl font-bold text-stone-900 dark:text-white">{value}</p>
      <p className="mt-0.5 truncate text-xs text-stone-500">{hint}</p>
    </div>
  )
}

function Panel({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="card overflow-hidden">
      <header className="flex items-center justify-between border-b border-stone-200 px-5 py-4 dark:border-stone-800">
        <h2 className="font-semibold">{title}</h2>
        {action}
      </header>
      {children}
    </section>
  )
}

function DishesPanel() {
  const dishes = useAllDishes()
  const remove = useDeleteDish()
  const [editing, setEditing] = useState<Dish | null | 'new'>(null)

  return (
    <Panel
      title={`Dishes${dishes.data ? ` (${dishes.data.meta.total})` : ''}`}
      action={
        <Button className="px-3 py-1.5" onClick={() => setEditing('new')}>
          <Plus className="h-4 w-4" /> New dish
        </Button>
      }
    >
      {dishes.isPending ? (
        <Spinner />
      ) : (
        <div className="max-h-[640px] overflow-auto">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-stone-50 text-left text-xs uppercase tracking-wider text-stone-500 dark:bg-stone-900">
              <tr>
                <th className="px-5 py-2.5 font-semibold">Dish</th>
                <th className="hidden px-3 py-2.5 font-semibold sm:table-cell">Category</th>
                <th className="px-3 py-2.5 text-right font-semibold">Price</th>
                <th className="px-5 py-2.5" />
              </tr>
            </thead>
            <tbody className="divide-y divide-stone-100 dark:divide-stone-800">
              {dishes.data?.data.map((dish) => (
                <tr key={dish.id} className={cn('hover:bg-stone-50 dark:hover:bg-stone-800/40', !dish.available && 'opacity-50')}>
                  <td className="px-5 py-2.5">
                    <p className="font-medium text-stone-900 dark:text-stone-100">{dish.name}</p>
                    <p className="text-xs text-stone-500">
                      {[dish.is_vegan ? 'vegan' : dish.is_vegetarian ? 'vegetarian' : null, ...dish.allergens.map((a) => a.slug)].filter(Boolean).join(' · ') ||
                        'no allergens'}
                    </p>
                  </td>
                  <td className="hidden px-3 py-2.5 text-stone-600 sm:table-cell dark:text-stone-400">{dish.category?.name}</td>
                  <td className="px-3 py-2.5 text-right font-semibold tabular-nums">{formatPrice(dish.price)}</td>
                  <td className="whitespace-nowrap px-5 py-2.5 text-right">
                    <button className="rounded-lg p-1.5 text-stone-500 hover:bg-stone-100 dark:hover:bg-stone-800" onClick={() => setEditing(dish)} aria-label={`Edit ${dish.name}`}>
                      <Pencil className="h-4 w-4" />
                    </button>
                    <button
                      className="rounded-lg p-1.5 text-stone-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"
                      onClick={() => confirm(`Delete ${dish.name}?`) && remove.mutate(dish.id)}
                      aria-label={`Delete ${dish.name}`}
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'New dish' : 'Edit dish'}>
        {editing !== null && <DishForm dish={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />}
      </Modal>
    </Panel>
  )
}

function OrdersPanel() {
  const orders = useOrders()
  const update = useUpdateOrderStatus()

  return (
    <Panel title="Live orders">
      <ul className="max-h-80 divide-y divide-stone-100 overflow-auto dark:divide-stone-800">
        {orders.data?.data.slice(0, 8).map((order) => {
          const next = NEXT_STATUS[order.status]
          return (
            <li key={order.id} className="flex items-center gap-3 px-5 py-3 text-sm">
              <div className="min-w-0 flex-1">
                <p className="font-medium">
                  #{order.id} · {order.customer?.name}
                </p>
                <p className="truncate text-xs text-stone-500">
                  {order.items.map((i) => `${i.quantity}× ${i.dish_name}`).join(', ')} · {formatDate(order.created_at)}
                </p>
              </div>
              <OrderStatusBadge status={order.status} label={order.status_label} />
              {next && (
                <button
                  onClick={() => update.mutate({ id: order.id, status: next })}
                  className="whitespace-nowrap rounded-lg border border-stone-300 px-2 py-1 text-xs font-medium hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800"
                >
                  → {next}
                </button>
              )}
            </li>
          )
        })}
      </ul>
    </Panel>
  )
}

function DocumentsPanel() {
  const documents = useDocuments()
  const upload = useUploadDocument()
  const remove = useDeleteDocument()
  const input = useRef<HTMLInputElement>(null)
  const [dragging, setDragging] = useState(false)

  const send = (file?: File) => file && upload.mutate({ file })

  return (
    <Panel title="Knowledge base (RAG)">
      <div className="space-y-4 p-5">
        <div
          onDragOver={(e) => {
            e.preventDefault()
            setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(e) => {
            e.preventDefault()
            setDragging(false)
            send(e.dataTransfer.files[0])
          }}
          onClick={() => input.current?.click()}
          className={cn(
            'flex cursor-pointer flex-col items-center rounded-xl border-2 border-dashed px-4 py-6 text-center text-sm transition',
            dragging ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-stone-300 hover:border-brand-400 dark:border-stone-700',
          )}
        >
          {upload.isPending ? <RefreshCw className="mb-2 h-6 w-6 animate-spin text-brand-500" /> : <FileUp className="mb-2 h-6 w-6 text-brand-500" />}
          <p className="font-medium">{upload.isPending ? 'Extracting, chunking and indexing…' : 'Drop a .md, .txt or .pdf file'}</p>
          <p className="text-xs text-stone-500">FAQ, policies, opening hours… The assistant will cite it.</p>
          <input ref={input} type="file" accept=".md,.txt,.pdf" className="hidden" onChange={(e) => send(e.target.files?.[0])} aria-label="Upload document" />
        </div>
        {upload.isError && <ErrorMessage error={upload.error} />}

        <ul className="space-y-2">
          {documents.data?.map((doc) => (
            <li key={doc.id} className="flex items-start gap-3 rounded-xl border border-stone-200 p-3 dark:border-stone-800">
              <FileText className="mt-0.5 h-5 w-5 shrink-0 text-stone-400" />
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{doc.title}</p>
                <p className="text-xs text-stone-500">
                  {doc.original_filename} · {doc.chunks_count} chunks ·{' '}
                  <span className={doc.indexed ? 'text-green-600' : 'text-amber-600'}>
                    <Database className="mb-0.5 inline h-3 w-3" /> {doc.indexed ? 'indexed' : 'not indexed'}
                  </span>
                </p>
              </div>
              <button
                className="rounded-lg p-1.5 text-stone-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"
                onClick={() => confirm(`Delete “${doc.title}”?`) && remove.mutate(doc.id)}
                aria-label={`Delete ${doc.title}`}
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </li>
          ))}
        </ul>
      </div>
    </Panel>
  )
}
