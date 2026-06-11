import { lazy, Suspense, type ReactNode } from 'react'
import { createBrowserRouter, Navigate } from 'react-router'
import { Layout } from './components/Layout'
import { RequireAuth } from './components/RequireAuth'
import { Spinner } from './components/ui'
import { AuthPage } from './pages/AuthPage'
import { MenuPage } from './pages/MenuPage'
import { OrdersPage } from './pages/OrdersPage'

// The chat (markdown renderer) and back office are split into their own chunks.
const ChatPage = lazy(() => import('./pages/ChatPage').then((m) => ({ default: m.ChatPage })))
const AdminPage = lazy(() => import('./pages/admin/AdminPage').then((m) => ({ default: m.AdminPage })))

const suspended = (node: ReactNode) => <Suspense fallback={<Spinner />}>{node}</Suspense>

export const router = createBrowserRouter([
  { path: '/login', element: <AuthPage mode="login" /> },
  { path: '/register', element: <AuthPage mode="register" /> },
  {
    element: <Layout />,
    children: [
      { index: true, element: <Navigate to="/menu" replace /> },
      { path: '/menu', element: <MenuPage /> },
      { path: '/chat', element: <RequireAuth>{suspended(<ChatPage />)}</RequireAuth> },
      { path: '/orders', element: <RequireAuth><OrdersPage /></RequireAuth> },
      { path: '/admin', element: <RequireAuth role="admin">{suspended(<AdminPage />)}</RequireAuth> },
      { path: '*', element: <Navigate to="/menu" replace /> },
    ],
  },
])
