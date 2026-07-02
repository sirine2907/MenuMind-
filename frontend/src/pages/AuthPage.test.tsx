import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { vi } from 'vitest'
import { AuthProvider } from '../context/AuthContext'
import { AuthPage } from './AuthPage'

function renderLogin() {
  const router = createMemoryRouter(
    [
      { path: '/login', element: <AuthPage mode="login" /> },
      { path: '/menu', element: <p>Menu page</p> },
    ],
    { initialEntries: ['/login'] },
  )
  render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </QueryClientProvider>,
  )
}

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status })

describe('AuthPage', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('logs in and redirects to the menu', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      json({ data: { token: '1|abc', user: { id: 2, name: 'Yasmine Ben Ali', email: 'demo@menumind.tn', role: 'customer' } } }),
    )
    vi.stubGlobal('fetch', fetchMock)
    renderLogin()

    await userEvent.type(screen.getByLabelText('Email'), 'demo@menumind.tn')
    await userEvent.type(screen.getByLabelText('Password'), 'password')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('Menu page')).toBeInTheDocument()
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ email: 'demo@menumind.tn', password: 'password' })
    expect(localStorage.getItem('menumind-token')).toBe('1|abc')
  })

  it('shows the API error message on bad credentials', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(json({ error: { status: 401, code: 'invalid_credentials', message: 'These credentials do not match our records.' } }, 401)),
    )
    renderLogin()

    await userEvent.type(screen.getByLabelText('Email'), 'demo@menumind.tn')
    await userEvent.type(screen.getByLabelText('Password'), 'wrong')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('These credentials do not match our records.')
  })

  it('lists the demo accounts', () => {
    renderLogin()

    expect(screen.getByText('demo@menumind.tn')).toBeInTheDocument()
  })
})
