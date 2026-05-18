import { useCallback, useEffect, useRef, useState } from 'react'
import { RideHistoryCard } from './RideHistoryCard'
import type { ApiRequestFn, RideHistoryMeta, RideHistoryResponse } from './types'

type RideHistoryPanelProps = {
  apiRequest: ApiRequestFn
  onError: (error: string) => void
}

export function RideHistoryPanel({ apiRequest, onError }: RideHistoryPanelProps) {
  const apiRequestRef = useRef(apiRequest)
  apiRequestRef.current = apiRequest

  const [rides, setRides] = useState<RideHistoryResponse['data']>([])
  const [meta, setMeta] = useState<RideHistoryMeta | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)

  const loadHistory = useCallback(
    async (targetPage: number) => {
      try {
        setLoading(true)
        const response = await apiRequestRef.current<RideHistoryResponse>(
          `/ride-history?page=${targetPage}&per_page=10`,
        )
        setRides(response.data)
        setMeta(response.meta)
        setPage(response.meta.current_page)
      } catch (err) {
        onError(err instanceof Error ? err.message : 'Nie udalo sie pobrac historii przejazdow')
      } finally {
        setLoading(false)
      }
    },
    [onError],
  )

  useEffect(() => {
    void loadHistory(1)
  }, [loadHistory])

  function goToPage(nextPage: number) {
    if (!meta || nextPage < 1 || nextPage > meta.last_page) return
    void loadHistory(nextPage)
  }

  return (
    <section className="space-y-6">
      <header>
        <h2 className="text-lg font-semibold text-slate-900">Historia przejazdow</h2>
        <p className="mt-1 text-sm text-slate-500">
          Przejazdy, ktore wyszukiwales w planerze. Kafelki ponizej pokazuja podsumowanie kazdego wyszukiwania.
        </p>
      </header>

      {loading ? (
        <p className="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
          Ladowanie historii...
        </p>
      ) : rides.length === 0 ? (
        <EmptyState />
      ) : (
        <>
          <ul className="space-y-4">
            {rides.map((ride) => (
              <li key={ride.id}>
                <RideHistoryCard ride={ride} />
              </li>
            ))}
          </ul>

          {meta && meta.last_page > 1 && (
            <Pagination meta={meta} page={page} loading={loading} onPageChange={goToPage} />
          )}
        </>
      )}
    </section>
  )
}

function EmptyState() {
  return (
    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
      <p className="text-sm font-medium text-slate-700">Brak historii wyszukiwan</p>
      <p className="mt-2 text-sm text-slate-500">
        Gdy wyszukasz polaczenie w planerze, pojawi sie tutaj jako kafelek z mapa i szczegolami trasy.
      </p>
    </div>
  )
}

function Pagination({
  meta,
  page,
  loading,
  onPageChange,
}: {
  meta: RideHistoryMeta
  page: number
  loading: boolean
  onPageChange: (page: number) => void
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
      <p className="text-sm text-slate-600">
        Strona {meta.current_page} z {meta.last_page} · lacznie {meta.total} wyszukiwan
      </p>
      <div className="flex gap-2">
        <button
          type="button"
          disabled={loading || page <= 1}
          onClick={() => onPageChange(page - 1)}
          className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        >
          Poprzednia
        </button>
        <button
          type="button"
          disabled={loading || page >= meta.last_page}
          onClick={() => onPageChange(page + 1)}
          className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        >
          Nastepna
        </button>
      </div>
    </div>
  )
}
