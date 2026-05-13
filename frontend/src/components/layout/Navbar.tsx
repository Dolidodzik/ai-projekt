import { NavLink } from 'react-router-dom'

const links = [
  { to: '/', label: 'Planner' },
  { to: '/schedule', label: 'Timetable' },
  { to: '/sign-in', label: 'Sign in' },
]

export function Navbar() {
  return (
    <header className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
        <div>
          <p className="text-lg font-semibold">Rzeszow Transit</p>
          <p className="text-sm text-slate-500">Route planner</p>
        </div>
        <nav className="flex flex-wrap gap-2">
          {links.map((link) => (
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
        </nav>
      </div>
    </header>
  )
}
