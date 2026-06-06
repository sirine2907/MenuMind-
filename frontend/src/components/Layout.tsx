import { ChefHat, LayoutDashboard, LogOut, MessageCircle, Moon, Receipt, Sun, UtensilsCrossed } from 'lucide-react'
import { NavLink, Outlet } from 'react-router'
import { useAuth } from '../context/AuthContext'
import { useTheme } from '../context/ThemeContext'
import { cn } from '../lib/format'

const linkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition',
    isActive
      ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-200'
      : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-stone-100',
  )

export function Logo() {
  return (
    <span className="flex items-center gap-2">
      <span className="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-white shadow-sm shadow-brand-900/30">
        <ChefHat className="h-5 w-5" />
      </span>
      <span className="leading-tight">
        <span className="block font-display text-lg font-bold text-stone-900 dark:text-white">MenuMind</span>
        <span className="block text-[11px] font-medium uppercase tracking-wider text-stone-500">Dar El Menu</span>
      </span>
    </span>
  )
}

export function ThemeToggle() {
  const { theme, toggle } = useTheme()
  return (
    <button
      onClick={toggle}
      className="rounded-xl p-2 text-stone-500 transition hover:bg-stone-100 hover:text-stone-900 dark:hover:bg-stone-800 dark:hover:text-white"
      aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
    >
      {theme === 'dark' ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
    </button>
  )
}

export function Layout() {
  const { user, logout } = useAuth()

  const links = [
    { to: '/menu', label: 'Menu', icon: UtensilsCrossed },
    { to: '/chat', label: 'Assistant', icon: MessageCircle },
    { to: '/orders', label: 'Orders', icon: Receipt },
    ...(user?.role === 'admin' ? [{ to: '/admin', label: 'Admin', icon: LayoutDashboard }] : []),
  ]

  return (
    <div className="flex min-h-screen flex-col">
      <header className="sticky top-0 z-30 border-b border-stone-200/80 bg-white/80 backdrop-blur-lg dark:border-stone-800 dark:bg-stone-950/80">
        <div className="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
          <NavLink to="/menu">
            <Logo />
          </NavLink>
          <nav className="ml-6 hidden items-center gap-1 md:flex">
            {links.map(({ to, label, icon: Icon }) => (
              <NavLink key={to} to={to} className={linkClass}>
                <Icon className="h-4 w-4" />
                {label}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-2">
            <ThemeToggle />
            {user && (
              <div className="flex items-center gap-2 border-l border-stone-200 pl-3 dark:border-stone-800">
                <span className="grid h-8 w-8 place-items-center rounded-full bg-stone-200 text-xs font-bold text-stone-700 dark:bg-stone-800 dark:text-stone-200">
                  {user.name.split(' ').map((p) => p[0]).slice(0, 2).join('')}
                </span>
                <span className="hidden text-sm font-medium lg:block">{user.name}</span>
                <button
                  onClick={logout}
                  className="rounded-xl p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-900 dark:hover:bg-stone-800 dark:hover:text-white"
                  aria-label="Log out"
                >
                  <LogOut className="h-4 w-4" />
                </button>
              </div>
            )}
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-7xl flex-1 px-4 pb-24 pt-6 sm:px-6 md:pb-10">
        <Outlet />
      </main>

      {/* Mobile tab bar */}
      <nav className="fixed inset-x-0 bottom-0 z-30 flex border-t border-stone-200 bg-white/95 backdrop-blur md:hidden dark:border-stone-800 dark:bg-stone-950/95">
        {links.map(({ to, label, icon: Icon }) => (
          <NavLink
            key={to}
            to={to}
            className={({ isActive }) =>
              cn('flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-medium', isActive ? 'text-brand-600' : 'text-stone-500')
            }
          >
            <Icon className="h-5 w-5" />
            {label}
          </NavLink>
        ))}
      </nav>
    </div>
  )
}
