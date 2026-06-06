import { Sparkles } from 'lucide-react'
import { useState, type ChangeEvent, type FormEvent, type ReactNode } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { Logo, ThemeToggle } from '../components/Layout'
import { Button, ErrorMessage } from '../components/ui'
import { useAuth } from '../context/AuthContext'
import { ApiError } from '../lib/api'

export function AuthPage({ mode }: { mode: 'login' | 'register' }) {
  const { user, login, register } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [error, setError] = useState<ApiError | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (user) return <Navigate to={(location.state as { from?: string } | null)?.from ?? '/menu'} replace />

  const isLogin = mode === 'login'
  const update = (field: keyof typeof form) => (e: ChangeEvent<HTMLInputElement>) => setForm({ ...form, [field]: e.target.value })

  async function submit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      if (isLogin) await login(form.email, form.password)
      else await register(form)
      navigate('/menu')
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'network', 'Cannot reach the server.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <div className="relative hidden overflow-hidden bg-brand-700 p-12 text-brand-50 lg:flex lg:flex-col lg:justify-between">
        <div className="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:22px_22px]" />
        <div className="relative">
          <span className="font-display text-2xl font-bold">MenuMind</span>
        </div>
        <div className="relative max-w-md">
          <p className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm">
            <Sparkles className="h-4 w-4" /> AI assistant · Dar El Menu, La Marsa
          </p>
          <h1 className="font-display text-4xl font-bold leading-tight text-white">
            “I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?”
          </h1>
          <p className="mt-4 text-brand-100">
            Ask in plain words. MenuMind searches the menu, checks allergens against the kitchen's data and cites its sources before you order.
          </p>
        </div>
        <p className="relative text-sm text-brand-200">Couscous · Brik · Ojja · Lablabi · Makroudh</p>
      </div>

      <div className="flex flex-col px-6 py-8 sm:px-12">
        <div className="flex items-center justify-between">
          <Logo />
          <ThemeToggle />
        </div>

        <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
          <h2 className="font-display text-3xl font-bold">{isLogin ? 'Welcome back' : 'Create your account'}</h2>
          <p className="mt-2 text-sm text-stone-500">
            {isLogin ? 'Log in to chat with MenuMind and track your orders.' : 'It takes 20 seconds, then you can order through the assistant.'}
          </p>

          <form onSubmit={submit} className="mt-8 space-y-4" noValidate>
            {error && !error.details && <ErrorMessage error={error} />}

            {!isLogin && (
              <Field label="Full name" error={error?.fieldError('name')}>
                <input className="input" value={form.name} onChange={update('name')} autoComplete="name" required />
              </Field>
            )}
            <Field label="Email" error={error?.fieldError('email')}>
              <input className="input" type="email" value={form.email} onChange={update('email')} autoComplete="email" required />
            </Field>
            <Field label="Password" error={error?.fieldError('password')}>
              <input
                className="input"
                type="password"
                value={form.password}
                onChange={update('password')}
                autoComplete={isLogin ? 'current-password' : 'new-password'}
                required
              />
            </Field>
            {!isLogin && (
              <Field label="Confirm password">
                <input className="input" type="password" value={form.password_confirmation} onChange={update('password_confirmation')} required />
              </Field>
            )}

            <Button type="submit" className="w-full" loading={submitting}>
              {isLogin ? 'Log in' : 'Create account'}
            </Button>
          </form>

          {isLogin && (
            <div className="mt-6 rounded-xl border border-dashed border-stone-300 p-4 text-sm text-stone-600 dark:border-stone-700 dark:text-stone-400">
              <p className="font-medium text-stone-800 dark:text-stone-200">Demo accounts</p>
              <p className="mt-1">
                Customer: <code>demo@menumind.tn</code> / <code>password</code>
                <br />
                Admin: <code>admin@menumind.tn</code> / <code>password</code>
              </p>
            </div>
          )}

          <p className="mt-6 text-center text-sm text-stone-500">
            {isLogin ? 'New here? ' : 'Already have an account? '}
            <Link to={isLogin ? '/register' : '/login'} className="font-semibold text-brand-600 hover:underline">
              {isLogin ? 'Create an account' : 'Log in'}
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="label">{label}</span>
      {children}
      {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
    </label>
  )
}
