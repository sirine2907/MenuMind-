export type Role = 'admin' | 'customer'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface Allergen {
  id: number
  name: string
  slug: string
  icon: string | null
}

export interface Category {
  id: number
  name: string
  slug: string
  position: number
  dishes_count?: number
}

export interface Dish {
  id: number
  name: string
  slug: string
  description: string
  price: number
  currency: string
  is_vegetarian: boolean
  is_vegan: boolean
  spicy_level: number
  available: boolean
  image_url: string | null
  category: Pick<Category, 'id' | 'name' | 'slug'> | null
  allergens: Allergen[]
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type OrderStatus = 'pending' | 'confirmed' | 'preparing' | 'ready' | 'delivered' | 'cancelled'

export interface OrderItem {
  dish_id: number
  dish_name: string
  quantity: number
  unit_price: number
  subtotal: number
}

export interface Order {
  id: number
  status: OrderStatus
  status_label: string
  total: number
  currency: string
  notes: string | null
  items: OrderItem[]
  customer?: { id: number; name: string; email: string }
  created_at: string
}

export interface KnowledgeDocument {
  id: number
  title: string
  original_filename: string
  mime_type: string
  chunks_count: number
  characters: number
  preview: string
  indexed: boolean
  indexed_at: string | null
  created_at: string
}

export interface AdminStats {
  dishes: { total: number; available: number }
  documents: { total: number; chunks: number }
  orders: { total: number; today: number; revenue_today: number; pending: number }
  search: { enabled: boolean; healthy: boolean; embedding_model: string }
}

export interface DishFilters {
  search?: string
  category?: string
  vegetarian?: boolean
  vegan?: boolean
  max_price?: number
  exclude_allergens?: string[]
  sort?: 'menu' | 'name' | 'price_asc' | 'price_desc' | 'spicy'
  page?: number
}

// ---------------------------------------------------------------- chat

export interface ToolStep {
  id: string
  tool: string
  label: string
  status: 'running' | 'done'
  summary?: string
}

export interface Citation {
  type: 'dish' | 'document' | 'order'
  id: number | string
  title: string
  subtitle?: string | null
  snippet?: string
  url?: string
}

export interface ChatMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
  steps: ToolStep[]
  citations: Citation[]
  status: 'streaming' | 'done' | 'error'
}
