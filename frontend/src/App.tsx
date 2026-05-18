import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ReportsPanel } from './components/reports/ReportsPanel'
import { RideHistoryPanel } from './components/ride-history/RideHistoryPanel'
import { BuyTicketsPanel } from './components/tickets/BuyTicketsPanel'
import { MyTicketsPanel } from './components/tickets/MyTicketsPanel'

type User = {
  id: number
  name: string
  email: string
  is_admin: boolean
  created_at: string
}

type AuthResponse = {
  token: string
  user: User
}

type AuthMode = 'login' | 'register'
type AppView = 'profile' | 'my-tickets' | 'buy-tickets' | 'ride-history' | 'reports'

const STORAGE_KEY = 'ai2_auth_token'

const NAV_ITEMS: { key: AppView; label: string; shortLabel: string }[] = [
  { key: 'profile', label: 'Profil', shortLabel: 'Profil' },
  { key: 'my-tickets', label: 'Moje bilety', shortLabel: 'Bilety' },
  { key: 'buy-tickets', label: 'Kupno biletow', shortLabel: 'Kupno' },
  { key: 'ride-history', label: 'Historia przejazdow', shortLabel: 'Historia' },
  { key: 'reports', label: 'Zgloszenia', shortLabel: 'Zgloszenia' },
]

function App() {
  const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api'
  const [mode, setMode] = useState<AuthMode>('login')
  const [view, setView] = useState<AppView>('profile')
  const [ticketsRefreshKey, setTicketsRefreshKey] = useState(0)
  const [token, setToken] = useState<string>(localStorage.getItem(STORAGE_KEY) ?? '')
  const [user, setUser] = useState<User | null>(null)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const [loginForm, setLoginForm] = useState({ email: '', password: '' })
  const [registerForm, setRegisterForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })
  const [profileForm, setProfileForm] = useState({ name: '', email: '' })
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })

  useEffect(() => {
    if (!token) return
    void fetchMe(token)
  }, [token])

  const apiRequest = useCallback(async <T,>(path: string, options: RequestInit = {}): Promise<T> => {
    const headers = new Headers(options.headers ?? {})
    headers.set('Accept', 'application/json')
    if (!headers.has('Content-Type') && options.body) {
      headers.set('Content-Type', 'application/json')
    }
    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
    }

    const response = await fetch(`${apiUrl}${path}`, {
      ...options,
      headers,
    })

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) {
      const validationErrors = payload?.errors
        ? Object.values(payload.errors as Record<string, string[]>).flat()
        : []
      const apiError = validationErrors[0] ?? payload?.message ?? `HTTP ${response.status}`
      throw new Error(String(apiError))
    }

    return payload as T
  }, [apiUrl, token])

  const apiFormRequest = useCallback(
    async <T,>(path: string, formData: FormData): Promise<T> => {
      const headers = new Headers()
      headers.set('Accept', 'application/json')
      if (token) {
        headers.set('Authorization', `Bearer ${token}`)
      }

      const response = await fetch(`${apiUrl}${path}`, {
        method: 'POST',
        headers,
        body: formData,
      })

      const payload = await response.json().catch(() => ({}))
      if (!response.ok) {
        const validationErrors = payload?.errors
          ? Object.values(payload.errors as Record<string, string[]>).flat()
          : []
        const apiError = validationErrors[0] ?? payload?.message ?? `HTTP ${response.status}`
        throw new Error(String(apiError))
      }

      return payload as T
    },
    [apiUrl, token],
  )

  async function fetchMe(nextToken: string) {
    try {
      setLoading(true)
      const response = await fetch(`${apiUrl}/auth/me`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${nextToken}`,
        },
      })

      if (!response.ok) {
        throw new Error('Sesja wygasla. Zaloguj sie ponownie.')
      }

      const profile = (await response.json()) as User
      setUser(profile)
      setProfileForm({ name: profile.name, email: profile.email })
    } catch (err) {
      setToken('')
      localStorage.removeItem(STORAGE_KEY)
      setUser(null)
      setError(err instanceof Error ? err.message : 'Nieznany blad')
    } finally {
      setLoading(false)
    }
  }

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError('')
    setMessage('')
    try {
      const response = await apiRequest<AuthResponse>('/auth/login', {
        method: 'POST',
        body: JSON.stringify(loginForm),
      })
      setToken(response.token)
      localStorage.setItem(STORAGE_KEY, response.token)
      setUser(response.user)
      setProfileForm({ name: response.user.name, email: response.user.email })
      setMessage('Zalogowano pomyslnie.')
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
    setMessage('')
    try {
      const response = await apiRequest<AuthResponse>('/auth/register', {
        method: 'POST',
        body: JSON.stringify(registerForm),
      })
      setToken(response.token)
      localStorage.setItem(STORAGE_KEY, response.token)
      setUser(response.user)
      setProfileForm({ name: response.user.name, email: response.user.email })
      setMessage('Konto utworzone. Jestes zalogowany.')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Rejestracja nieudana')
    } finally {
      setLoading(false)
    }
  }

  async function handleLogout() {
    setLoading(true)
    setError('')
    setMessage('')
    try {
      await apiRequest<{ message: string }>('/auth/logout', { method: 'POST' })
    } catch {
      // Ignore logout API failure and clear local session anyway.
    } finally {
      setToken('')
      setUser(null)
      localStorage.removeItem(STORAGE_KEY)
      setLoading(false)
      setMessage('Wylogowano.')
    }
  }

  async function handleProfileUpdate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError('')
    setMessage('')
    try {
      const response = await apiRequest<{ user: User; message: string }>('/auth/profile', {
        method: 'PATCH',
        body: JSON.stringify(profileForm),
      })
      setUser(response.user)
      setProfileForm({ name: response.user.name, email: response.user.email })
      setMessage(response.message)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Nie udalo sie zapisac profilu')
    } finally {
      setLoading(false)
    }
  }

  async function handlePasswordUpdate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError('')
    setMessage('')
    try {
      const response = await apiRequest<{ token: string; message: string }>('/auth/password', {
        method: 'PATCH',
        body: JSON.stringify(passwordForm),
      })
      setToken(response.token)
      localStorage.setItem(STORAGE_KEY, response.token)
      setPasswordForm({
        current_password: '',
        password: '',
        password_confirmation: '',
      })
      setMessage(response.message)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Nie udalo sie zmienic hasla')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-4">
          <h1 className="shrink-0 text-xl font-semibold">AI2 Konto Uzytkownika</h1>
          {user && (
            <div className="flex items-center gap-3">
              <nav className="hidden flex-wrap gap-1 lg:inline-flex rounded-lg bg-slate-100 p-1">
                {NAV_ITEMS.map(({ key, label }) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setView(key)}
                    className={`rounded-md px-2.5 py-1.5 text-xs font-medium lg:px-3 lg:text-sm ${
                      view === key ? 'bg-[#1754d8] text-white' : 'text-slate-700'
                    }`}
                  >
                    {label}
                  </button>
                ))}
              </nav>
              <button
                type="button"
                onClick={handleLogout}
                disabled={loading}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 disabled:opacity-60"
              >
                Wyloguj
              </button>
            </div>
          )}
        </div>
      </header>

      <main
        className={`mx-auto px-6 py-10 ${view === 'ride-history' || view === 'reports' ? 'max-w-5xl' : 'max-w-4xl'}`}
      >
        <div className="mb-6 rounded-xl border border-[#1754d8]/20 bg-[#1754d8]/5 px-4 py-3 text-sm text-[#1754d8]">
          API: <span className="font-mono">{apiUrl}</span>
        </div>

        {error && <p className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">{error}</p>}
        {message && (
          <p className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">{message}</p>
        )}

        {!user ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
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
                <Input
                  label="Email"
                  type="email"
                  value={loginForm.email}
                  onChange={(value) => setLoginForm((prev) => ({ ...prev, email: value }))}
                />
                <Input
                  label="Haslo"
                  type="password"
                  value={loginForm.password}
                  onChange={(value) => setLoginForm((prev) => ({ ...prev, password: value }))}
                />
                <PrimaryButton disabled={loading}>Zaloguj</PrimaryButton>
              </form>
            ) : (
              <form onSubmit={handleRegister} className="space-y-4">
                <Input
                  label="Imie i nazwisko"
                  value={registerForm.name}
                  onChange={(value) => setRegisterForm((prev) => ({ ...prev, name: value }))}
                />
                <Input
                  label="Email"
                  type="email"
                  value={registerForm.email}
                  onChange={(value) => setRegisterForm((prev) => ({ ...prev, email: value }))}
                />
                <Input
                  label="Haslo"
                  type="password"
                  value={registerForm.password}
                  onChange={(value) => setRegisterForm((prev) => ({ ...prev, password: value }))}
                  minLength={8}
                />
                <Input
                  label="Powtorz haslo"
                  type="password"
                  value={registerForm.password_confirmation}
                  onChange={(value) => setRegisterForm((prev) => ({ ...prev, password_confirmation: value }))}
                  minLength={8}
                />
                <PrimaryButton disabled={loading}>Utworz konto</PrimaryButton>
              </form>
            )}
          </section>
        ) : view === 'my-tickets' ? (
          <MyTicketsPanel
            apiRequest={apiRequest}
            refreshKey={ticketsRefreshKey}
            onMessage={setMessage}
            onError={setError}
            loading={loading}
            setLoading={setLoading}
          />
        ) : view === 'buy-tickets' ? (
          <BuyTicketsPanel
            apiRequest={apiRequest}
            onPurchased={() => {
              setTicketsRefreshKey((prev) => prev + 1)
              setView('my-tickets')
            }}
            onMessage={setMessage}
            onError={setError}
            loading={loading}
            setLoading={setLoading}
          />
        ) : view === 'ride-history' ? (
          <RideHistoryPanel apiRequest={apiRequest} onError={setError} />
        ) : view === 'reports' ? (
          <ReportsPanel
            apiRequest={apiRequest}
            apiFormRequest={apiFormRequest}
            onMessage={setMessage}
            onError={setError}
          />
        ) : (
          <div className="grid gap-6 md:grid-cols-2">
            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-lg font-semibold">Profil</h2>
              <form onSubmit={handleProfileUpdate} className="space-y-4">
                <Input
                  label="Imie i nazwisko"
                  value={profileForm.name}
                  onChange={(value) => setProfileForm((prev) => ({ ...prev, name: value }))}
                />
                <Input
                  label="Email"
                  type="email"
                  value={profileForm.email}
                  onChange={(value) => setProfileForm((prev) => ({ ...prev, email: value }))}
                />
                <PrimaryButton disabled={loading}>Zapisz dane</PrimaryButton>
              </form>
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-lg font-semibold">Zmiana hasla</h2>
              <form onSubmit={handlePasswordUpdate} className="space-y-4">
                <Input
                  label="Aktualne haslo"
                  type="password"
                  value={passwordForm.current_password}
                  onChange={(value) => setPasswordForm((prev) => ({ ...prev, current_password: value }))}
                />
                <Input
                  label="Nowe haslo"
                  type="password"
                  value={passwordForm.password}
                  onChange={(value) => setPasswordForm((prev) => ({ ...prev, password: value }))}
                />
                <Input
                  label="Powtorz nowe haslo"
                  type="password"
                  value={passwordForm.password_confirmation}
                  onChange={(value) => setPasswordForm((prev) => ({ ...prev, password_confirmation: value }))}
                />
                <PrimaryButton disabled={loading}>Zmien haslo</PrimaryButton>
              </form>
            </section>
          </div>
        )}

        {user && (
          <nav className="mt-6 grid grid-cols-3 gap-1 sm:hidden rounded-lg bg-slate-100 p-1">
            {NAV_ITEMS.map(({ key, shortLabel }) => (
              <button
                key={key}
                type="button"
                onClick={() => setView(key)}
                className={`rounded-md px-2 py-2 text-xs font-medium ${
                  view === key ? 'bg-[#1754d8] text-white' : 'text-slate-700'
                }`}
              >
                {shortLabel}
              </button>
            ))}
          </nav>
        )}
      </main>
    </div>
  )
}

type InputProps = {
  label: string
  value: string
  onChange: (nextValue: string) => void
  type?: 'text' | 'email' | 'password'
  minLength?: number
  maxLength?: number
}

function Input({ label, value, onChange, type = 'text', minLength, maxLength }: InputProps) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required
        minLength={minLength}
        maxLength={maxLength}
        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-[#1754d8] focus:ring-2 focus:ring-[#1754d8]/20"
      />
    </label>
  )
}

type PrimaryButtonProps = {
  disabled?: boolean
  children: string
}

function PrimaryButton({ disabled, children }: PrimaryButtonProps) {
  return (
    <button
      type="submit"
      disabled={disabled}
      className="rounded-lg bg-[#1754d8] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#1549bc] disabled:cursor-not-allowed disabled:opacity-60"
    >
      {children}
    </button>
  )
}

export default App
