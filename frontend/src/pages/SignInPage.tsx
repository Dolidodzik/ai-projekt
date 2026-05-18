import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

type AuthMode = 'login' | 'register'

export function SignInPage() {
  const { isAuthenticated, login, register, loading: authLoading } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const initialMode: AuthMode = location.pathname.includes('register') ? 'register' : 'login'

  const [mode, setMode] = useState<AuthMode>(initialMode)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [loginForm, setLoginForm] = useState({ email: '', password: '' })
  const [registerForm, setRegisterForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })

  if (authLoading) {
    return <p className="text-center text-sm text-slate-500">Ladowanie...</p>
  }

  if (isAuthenticated) {
    return <Navigate to="/account" replace />
  }

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError('')
    try {
      await login(loginForm.email, loginForm.password)
      navigate('/account')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Logowanie nieudane')
    } finally {
      setLoading(false)
    }
  }

  async function handleRegister(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError('')
    try {
      await register(registerForm)
      navigate('/account')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Rejestracja nieudana')
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="mx-auto max-w-md">
      <h1 className="text-2xl font-semibold">{mode === 'login' ? 'Logowanie' : 'Rejestracja'}</h1>
      <p className="mt-2 text-sm text-slate-600">
        {mode === 'login'
          ? 'Zaloguj sie, aby zapisywac historie przejazdow i korzystac z konta.'
          : 'Utworz konto uzytkownika.'}
      </p>

      {error ? (
        <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
      ) : null}

      <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="mb-6 inline-flex rounded-lg bg-slate-100 p-1">
          <button
            type="button"
            onClick={() => setMode('login')}
            className={`rounded-md px-4 py-2 text-sm font-medium ${
              mode === 'login' ? 'bg-[#1754d8] text-white' : 'text-slate-700'
            }`}
          >
            Logowanie
          </button>
          <button
            type="button"
            onClick={() => setMode('register')}
            className={`rounded-md px-4 py-2 text-sm font-medium ${
              mode === 'register' ? 'bg-[#1754d8] text-white' : 'text-slate-700'
            }`}
          >
            Rejestracja
          </button>
        </div>

        {mode === 'login' ? (
          <form onSubmit={handleLogin} className="space-y-4">
            <AuthInput
              label="Email"
              type="email"
              value={loginForm.email}
              onChange={(value) => setLoginForm((prev) => ({ ...prev, email: value }))}
            />
            <AuthInput
              label="Haslo"
              type="password"
              value={loginForm.password}
              onChange={(value) => setLoginForm((prev) => ({ ...prev, password: value }))}
            />
            <AuthButton disabled={loading}>Zaloguj</AuthButton>
          </form>
        ) : (
          <form onSubmit={handleRegister} className="space-y-4">
            <AuthInput
              label="Imie i nazwisko"
              value={registerForm.name}
              onChange={(value) => setRegisterForm((prev) => ({ ...prev, name: value }))}
            />
            <AuthInput
              label="Email"
              type="email"
              value={registerForm.email}
              onChange={(value) => setRegisterForm((prev) => ({ ...prev, email: value }))}
            />
            <AuthInput
              label="Haslo"
              type="password"
              value={registerForm.password}
              onChange={(value) => setRegisterForm((prev) => ({ ...prev, password: value }))}
              minLength={8}
            />
            <AuthInput
              label="Powtorz haslo"
              type="password"
              value={registerForm.password_confirmation}
              onChange={(value) => setRegisterForm((prev) => ({ ...prev, password_confirmation: value }))}
              minLength={8}
            />
            <AuthButton disabled={loading}>Utworz konto</AuthButton>
          </form>
        )}
      </div>
    </section>
  )
}

function AuthInput({
  label,
  value,
  onChange,
  type = 'text',
  minLength,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  type?: 'text' | 'email' | 'password'
  minLength?: number
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required
        minLength={minLength}
        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8] focus:ring-2 focus:ring-[#1754d8]/20"
      />
    </label>
  )
}

function AuthButton({ disabled, children }: { disabled?: boolean; children: string }) {
  return (
    <button
      type="submit"
      disabled={disabled}
      className="w-full rounded-lg bg-[#1754d8] px-4 py-2 text-sm font-medium text-white hover:bg-[#1549bc] disabled:opacity-60"
    >
      {children}
    </button>
  )
}
