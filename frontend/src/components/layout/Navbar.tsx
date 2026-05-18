import { NavLink, useMatch } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'

const publicLinks = [
  { to: '/', label: 'Planer' },
  { to: '/schedule', label: 'Rozklad' },
]

export function Navbar() {
  const { isAuthenticated, loading } = useAuth()
  const accountActive = Boolean(useMatch({ path: '/account/*', end: false }))

  return (
    <header className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
        <NavLink to="/" className="block shrink-0">
          <p className="text-lg font-semibold text-slate-900">Rzeszow Transit</p>
          <p className="text-sm text-slate-500">Komunikacja miejska</p>
        </NavLink>
        <nav className="flex flex-wrap items-center gap-2">
          {publicLinks.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              className={({ isActive }) =>
                `rounded-lg px-3 py-2 text-sm font-medium ${
                  isActive ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'
                }`
              }
            >
              {link.label}
            </NavLink>
          ))}
          {!loading && isAuthenticated ? (
            <NavLink
              to="/account/profil"
              className={`rounded-lg px-3 py-2 text-sm font-medium ${
                accountActive ? 'bg-[#1754d8] text-white' : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              Profil
            </NavLink>
          ) : null}
          {!loading && !isAuthenticated ? (
            <NavLink
              to="/sign-in"
              className={({ isActive }) =>
                `rounded-lg px-3 py-2 text-sm font-medium ${
                  isActive ? 'bg-[#1754d8] text-white' : 'text-slate-600 hover:bg-slate-50'
                }`
              }
            >
              Logowanie
            </NavLink>
          ) : null}
        </nav>
      </div>
    </header>
  )
}
