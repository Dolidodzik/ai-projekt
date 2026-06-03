import { useCallback, useEffect, useState } from 'react'
import { formatDate, formatPrice, statusClass, statusLabel } from './types'
import type { ApiRequestFn, UserTicket } from './types'

type MyTicketsPanelProps = {
  apiRequest: ApiRequestFn
  refreshKey: number
  onMessage: (message: string) => void
  onError: (error: string) => void
  loading: boolean
  setLoading: (loading: boolean) => void
}

export function MyTicketsPanel({
  apiRequest,
  refreshKey,
  onMessage,
  onError,
  loading,
  setLoading,
}: MyTicketsPanelProps) {
  const [tickets, setTickets] = useState<UserTicket[]>([])
  const [filter, setFilter] = useState<'all' | 'active' | 'inactive' | 'expired'>('all')

  const loadTickets = useCallback(async () => {
    try {
      setLoading(true)
      const response = await apiRequest<{ data: UserTicket[] }>('/tickets')
      setTickets(response.data)
    } catch (err) {
      onError(err instanceof Error ? err.message : 'Nie udalo sie pobrac biletow')
    } finally {
      setLoading(false)
    }
  }, [apiRequest, onError, setLoading])

  useEffect(() => {
    void loadTickets()
  }, [loadTickets, refreshKey])

  async function handleActivate(ticketId: number) {
    onMessage('')
    onError('')
    try {
      setLoading(true)
      const response = await apiRequest<{ message: string }>(`/tickets/${ticketId}/activate`, {
        method: 'POST',
      })
      onMessage(response.message)
      await loadTickets()
    } catch (err) {
      onError(err instanceof Error ? err.message : 'Aktywacja nieudana')
    } finally {
      setLoading(false)
    }
  }

  const filtered = tickets.filter((ticket) => filter === 'all' || ticket.status === filter)

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">Moje bilety</h2>
        <div className="inline-flex rounded-lg bg-slate-100 p-1">
          {(['all', 'active', 'inactive', 'expired'] as const).map((value) => (
            <button
              key={value}
              type="button"
              onClick={() => setFilter(value)}
              className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                filter === value ? 'bg-[#1754d8] text-white' : 'text-slate-700'
              }`}
            >
              {value === 'all'
                ? 'Wszystkie'
                : value === 'active'
                  ? 'Aktywne'
                  : value === 'inactive'
                    ? 'Nieaktywne'
                    : 'Wygasle'}
            </button>
          ))}
        </div>
      </div>

      {filtered.length === 0 ? (
        <p className="text-sm text-slate-500">
          {tickets.length === 0 ? 'Nie masz jeszcze zadnych biletow.' : 'Brak biletow w wybranym filtrze.'}
        </p>
      ) : (
        <ul className="space-y-3">
          {filtered.map((ticket) => (
            <li key={ticket.id} className="rounded-xl border border-slate-200 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-medium">{ticket.ticket_type.name}</p>
                  <p className="text-sm text-slate-500">{formatPrice(ticket.ticket_type.price)}</p>
                </div>
                <span
                  className={`rounded-full border px-3 py-1 text-xs font-medium ${statusClass(ticket.status)}`}
                >
                  {statusLabel(ticket.status)}
                </span>
              </div>

              <dl className="mt-3 grid gap-1 text-sm text-slate-600 sm:grid-cols-2">
                <div>
                  <dt className="text-slate-500">Zakup</dt>
                  <dd>{formatDate(ticket.purchase_date)}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">Wazny od</dt>
                  <dd>{formatDate(ticket.valid_from)}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">Wazny do</dt>
                  <dd>{formatDate(ticket.valid_until)}</dd>
                </div>
              </dl>

              {ticket.can_activate && (
                <button
                  type="button"
                  disabled={loading}
                  onClick={() => void handleActivate(ticket.id)}
                  className="mt-3 rounded-lg bg-[#1754d8] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#1549bc] disabled:opacity-60"
                >
                  Aktywuj bilet
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
