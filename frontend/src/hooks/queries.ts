import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import type {
  AdminStats,
  Allergen,
  Category,
  Dish,
  DishFilters,
  KnowledgeDocument,
  Order,
  OrderStatus,
  Paginated,
} from '../lib/types'

export const keys = {
  dishes: (filters: DishFilters) => ['dishes', filters] as const,
  allDishes: ['dishes'] as const,
  allergens: ['allergens'] as const,
  categories: ['categories'] as const,
  orders: ['orders'] as const,
  documents: ['documents'] as const,
  stats: ['admin', 'stats'] as const,
}

export function useDishes(filters: DishFilters, perPage = 12) {
  return useQuery({
    queryKey: [...keys.dishes(filters), perPage],
    queryFn: () => api.get<Paginated<Dish>>('/dishes', { ...filters, per_page: perPage }),
    placeholderData: keepPreviousData,
  })
}

export function useAllDishes() {
  return useQuery({
    queryKey: [...keys.allDishes, 'admin'],
    // available=0 lifts the "available only" restriction: admins see everything.
    queryFn: () => api.get<Paginated<Dish>>('/dishes', { per_page: 100, available: false }),
  })
}

export function useAllergens() {
  return useQuery({
    queryKey: keys.allergens,
    queryFn: () => api.get<{ data: Allergen[] }>('/allergens').then((r) => r.data),
    staleTime: Infinity,
  })
}

export function useCategories() {
  return useQuery({
    queryKey: keys.categories,
    queryFn: () => api.get<{ data: Category[] }>('/categories').then((r) => r.data),
    staleTime: 5 * 60_000,
  })
}

export function useOrders() {
  return useQuery({
    queryKey: keys.orders,
    queryFn: () => api.get<Paginated<Order>>('/orders', { per_page: 50 }),
    refetchInterval: 15_000,
  })
}

export function useUpdateOrderStatus() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: OrderStatus }) => api.patch<{ data: Order }>(`/orders/${id}/status`, { status }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: keys.orders })
      client.invalidateQueries({ queryKey: keys.stats })
    },
  })
}

export function useAdminStats() {
  return useQuery({
    queryKey: keys.stats,
    queryFn: () => api.get<{ data: AdminStats }>('/admin/stats').then((r) => r.data),
  })
}

export function useDocuments() {
  return useQuery({
    queryKey: keys.documents,
    queryFn: () => api.get<{ data: KnowledgeDocument[] }>('/documents').then((r) => r.data),
  })
}

export function useUploadDocument() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ file, title }: { file: File; title?: string }) => {
      const form = new FormData()
      form.append('file', file)
      if (title) form.append('title', title)
      return api.post<{ data: KnowledgeDocument }>('/documents', form)
    },
    onSuccess: () => {
      client.invalidateQueries({ queryKey: keys.documents })
      client.invalidateQueries({ queryKey: keys.stats })
    },
  })
}

export function useDeleteDocument() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/documents/${id}`),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.documents }),
  })
}

export function useReindex() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: () => api.post<{ data: { indexed: { dishes: number; doc_chunks: number }; duration_ms: number } }>('/admin/reindex'),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.stats }),
  })
}

export type DishInput = {
  name: string
  description: string
  price: number
  category_id: number | null
  is_vegetarian: boolean
  is_vegan: boolean
  spicy_level: number
  available: boolean
  allergens: string[]
}

export function useSaveDish() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: DishInput }) =>
      id ? api.patch<{ data: Dish }>(`/dishes/${id}`, data) : api.post<{ data: Dish }>('/dishes', data),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: keys.allDishes })
      client.invalidateQueries({ queryKey: keys.stats })
    },
  })
}

export function useDeleteDish() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/dishes/${id}`),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.allDishes }),
  })
}
