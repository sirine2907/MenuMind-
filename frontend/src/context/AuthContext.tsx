import { useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, tokenStore } from '../lib/api'
import type { User } from '../lib/types'

interface AuthResponse {
  data: { user: User; token: string }
}

interface AuthContextValue {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<User>
  register: (data: { name: string; email: string; password: string; password_confirmation: string }) => Promise<User>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(() => tokenStore.get() !== null)

  // Restore the session from a saved token.
  useEffect(() => {
    if (!tokenStore.get()) return
    api
      .get<{ data: User }>('/auth/me')
      .then((res) => setUser(res.data))
      .catch(() => tokenStore.set(null))
      .finally(() => setLoading(false))
  }, [])

  const authenticate = useCallback((res: AuthResponse) => {
    tokenStore.set(res.data.token)
    setUser(res.data.user)
    return res.data.user
  }, [])

  const login = useCallback(
    async (email: string, password: string) => authenticate(await api.post<AuthResponse>('/auth/login', { email, password })),
    [authenticate],
  )

  const register = useCallback<AuthContextValue['register']>(
    async (data) => authenticate(await api.post<AuthResponse>('/auth/register', data)),
    [authenticate],
  )

  const logout = useCallback(async () => {
    await api.post('/auth/logout').catch(() => undefined)
    tokenStore.set(null)
    setUser(null)
    queryClient.clear()
  }, [queryClient])

  const value = useMemo(() => ({ user, loading, login, register, logout }), [user, loading, login, register, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// oxlint-disable-next-line react/only-export-components -- provider + hook pair
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>')
  return context
}
