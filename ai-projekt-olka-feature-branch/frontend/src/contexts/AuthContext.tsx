import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { getApiBaseUrl } from '../lib/api'

export const AUTH_STORAGE_KEY = 'ai2_auth_token'

export type AuthUser = {
  id: number
  name: string
  email: string
  is_admin: boolean
  created_at: string
}

type AuthResponse = {
  token: string
  user: AuthUser
}

type AuthContextValue = {
  token: string
  user: AuthUser | null
  loading: boolean
  isAuthenticated: boolean
  apiRequest: <T>(path: string, options?: RequestInit) => Promise<T>
  apiFormRequest: <T>(path: string, formData: FormData) => Promise<T>
  login: (email: string, password: string) => Promise<void>
  register: (payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }) => Promise<void>
  logout: () => Promise<void>
  refreshUser: () => Promise<void>
  setSession: (token: string, user: AuthUser) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState(() => localStorage.getItem(AUTH_STORAGE_KEY) ?? '')
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(Boolean(localStorage.getItem(AUTH_STORAGE_KEY)))

  const apiRequest = useCallback(
    async <T,>(path: string, options: RequestInit = {}): Promise<T> => {
      const headers = new Headers(options.headers ?? {})
      headers.set('Accept', 'application/json')
      if (!headers.has('Content-Type') && options.body) {
        headers.set('Content-Type', 'application/json')
      }
      if (token) {
        headers.set('Authorization', `Bearer ${token}`)
      }

      const response = await fetch(`${getApiBaseUrl()}${path}`, {
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
    },
    [token],
  )

  const apiFormRequest = useCallback(
    async <T,>(path: string, formData: FormData): Promise<T> => {
      const headers = new Headers()
      headers.set('Accept', 'application/json')
      if (token) {
        headers.set('Authorization', `Bearer ${token}`)
      }

      const response = await fetch(`${getApiBaseUrl()}${path}`, {
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
    [token],
  )

  const refreshUser = useCallback(async () => {
    if (!token) {
      setUser(null)
      setLoading(false)
      return
    }

    try {
      setLoading(true)
      const profile = await apiRequest<AuthUser>('/auth/me')
      setUser(profile)
    } catch {
      setToken('')
      setUser(null)
      localStorage.removeItem(AUTH_STORAGE_KEY)
    } finally {
      setLoading(false)
    }
  }, [apiRequest, token])

  useEffect(() => {
    void refreshUser()
  }, [refreshUser])

  const setSession = useCallback((nextToken: string, nextUser: AuthUser) => {
    setToken(nextToken)
    setUser(nextUser)
    localStorage.setItem(AUTH_STORAGE_KEY, nextToken)
  }, [])

  const login = useCallback(
    async (email: string, password: string) => {
      const response = await apiRequest<AuthResponse>('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      })
      setSession(response.token, response.user)
    },
    [apiRequest, setSession],
  )

  const register = useCallback(
    async (payload: {
      name: string
      email: string
      password: string
      password_confirmation: string
    }) => {
      const response = await apiRequest<AuthResponse>('/auth/register', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      setSession(response.token, response.user)
    },
    [apiRequest, setSession],
  )

  const logout = useCallback(async () => {
    try {
      await apiRequest<{ message: string }>('/auth/logout', { method: 'POST' })
    } catch {
    } finally {
      setToken('')
      setUser(null)
      localStorage.removeItem(AUTH_STORAGE_KEY)
    }
  }, [apiRequest])

  const value = useMemo<AuthContextValue>(
    () => ({
      token,
      user,
      loading,
      isAuthenticated: Boolean(user && token),
      apiRequest,
      apiFormRequest,
      login,
      register,
      logout,
      refreshUser,
      setSession,
    }),
    [
      token,
      user,
      loading,
      apiRequest,
      apiFormRequest,
      login,
      register,
      logout,
      refreshUser,
      setSession,
    ],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}
