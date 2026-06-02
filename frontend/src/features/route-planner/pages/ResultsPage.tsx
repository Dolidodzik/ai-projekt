import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate, useLocation } from 'react-router-dom'
import { RouteResultMap } from '../../../components/map/RouteResultMap'
import { Alert } from '../../../components/ui/Alert'
import { Spinner } from '../../../components/ui/Spinner'
import { useAuth } from '../../../contexts/AuthContext'
import { saveRideToHistory } from '../../ride-history/api'
import { fetchTripDetails } from '../api'
import { loadPlanResult } from '../storage'
import type { PlanRouteResult, TransitResult, TripDetails } from '../types'
import {
  formatDurationMinutes,
  formatLocaleDateTime24,
  formatWalkingSummary,
  getTripPks,
  transitDurationMinutes,
} from '../utils'

interface ResultsLocationState {
  planResult?: PlanRouteResult
}

interface DepartureOption {
  originalIndex: number
  lineLabel: string
  routeName: string | null
  relativeLabel: string
  exactTime: string
  durationLabel: string
  sortValue: number
}

function gtfsTimeToSeconds(time: string): number {
  const [hours = 0, minutes = 0, seconds = 0] = time.split(':').map(Number)
  return hours * 3600 + minutes * 60 + seconds
}

function localAnchorSeconds(departAt: string): number {
  const date = new Date(departAt)
  return date.getHours() * 3600 + date.getMinutes() * 60 + date.getSeconds()
}

function formatGtfsTimeLabel(time: string): string {
  const [hours = '00', minutes = '00'] = time.split(':')
  return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`
}

function firstDepartureTime(transit: TransitResult): string {
  return transit.type === 'direct' ? transit.from_departure_time : transit.legs[0].from_departure_time
}

function lastArrivalTime(transit: TransitResult): string {
  return transit.type === 'direct'
    ? transit.to_arrival_time
    : transit.legs[transit.legs.length - 1].to_arrival_time
}

function lineLabel(transit: TransitResult): string {
  if (transit.type === 'direct') {
    return transit.route.short_name
  }

  return transit.legs.map((leg) => leg.route.short_name).join(' + ')
}

function routeName(transit: TransitResult): string | null {
  if (transit.type === 'direct') {
    return transit.route.long_name
  }

  return transit.legs.map((leg) => leg.route.long_name ?? `Linia ${leg.route.short_name}`).join(' -> ')
}

function relativeDepartureLabel(firstDeparture: string, departAt: string): { label: string; sortValue: number } {
  const deltaMinutes = Math.max(0, Math.round((gtfsTimeToSeconds(firstDeparture) - localAnchorSeconds(departAt)) / 60))

  if (deltaMinutes === 0) {
    return { label: 'odjazd teraz', sortValue: 0 }
  }

  if (deltaMinutes < 60) {
    return { label: `odjazd za ${deltaMinutes} min`, sortValue: deltaMinutes }
  }

  const hours = Math.floor(deltaMinutes / 60)
  const minutes = deltaMinutes % 60
  return {
    label: minutes > 0 ? `odjazd za ${hours} h ${minutes} min` : `odjazd za ${hours} h`,
    sortValue: deltaMinutes,
  }
}

export function ResultsPage() {
  const location = useLocation()
  const { token, isAuthenticated } = useAuth()
  const state = location.state as ResultsLocationState | null
  const [planResult] = useState<PlanRouteResult | null>(() => state?.planResult ?? loadPlanResult())
  const [selectedIndex, setSelectedIndex] = useState(0)
  const [tripDetails, setTripDetails] = useState<TripDetails[]>([])
  const [error, setError] = useState<string | null>(null)
  const [historyMessage, setHistoryMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(Boolean(planResult))

  const transitOptions = useMemo<TransitResult[]>(
    () => planResult?.transit_options ?? (planResult?.transit ? [planResult.transit] : []),
    [planResult],
  )

  const selectedTransit = transitOptions[selectedIndex] ?? transitOptions[0] ?? null
  const mapResult = useMemo(
    () => (planResult && selectedTransit ? { ...planResult, transit: selectedTransit } : null),
    [planResult, selectedTransit],
  )
  const departureOptions = useMemo<DepartureOption[]>(() => {
    if (!planResult) {
      return []
    }

    return transitOptions
      .map((transit, originalIndex) => {
        const departureTime = firstDepartureTime(transit)
        const relative = relativeDepartureLabel(departureTime, planResult.depart_at)

        return {
          originalIndex,
          lineLabel: lineLabel(transit),
          routeName: routeName(transit),
          relativeLabel: relative.label,
          exactTime: formatGtfsTimeLabel(departureTime),
          durationLabel: formatDurationMinutes(transitDurationMinutes(transit)),
          sortValue: relative.sortValue,
        }
      })
      .sort((a, b) => a.sortValue - b.sortValue)
  }, [planResult, transitOptions])

  useEffect(() => {
    if (!selectedTransit) {
      return
    }

    let cancelled = false

    const loadDetails = async () => {
      setIsLoading(true)
      setError(null)

      try {
        const details = await Promise.all(getTripPks(selectedTransit).map((tripPk) => fetchTripDetails(tripPk)))
        if (!cancelled) {
          setTripDetails(details)
        }
      } catch {
        if (!cancelled) {
          setError('Nie udalo sie zaladowac geometrii trasy.')
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    void loadDetails()

    return () => {
      cancelled = true
    }
  }, [selectedTransit])

  async function handleSelectOption(index: number) {
    setSelectedIndex(index)
    setHistoryMessage(null)

    if (!isAuthenticated || !token || !planResult) {
      return
    }

    const option = transitOptions[index]
    if (!option) {
      return
    }

    try {
      await saveRideToHistory(token, planResult, option)
      setHistoryMessage('Trasa zapisana w historii przejazdow.')
    } catch {
      setHistoryMessage(null)
    }
  }

  if (!planResult || !selectedTransit || !mapResult) {
    return <Navigate to="/" replace />
  }

  const walkingSummary = formatWalkingSummary(planResult.walking_segments)
  const travelDuration = formatDurationMinutes(transitDurationMinutes(selectedTransit))
  const selectedRouteName = routeName(selectedTransit)

  return (
    <section className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Wynik trasy</h1>
          <p className="mt-2 text-slate-600">
            {planResult.from_stop.stop_name} -&gt; {planResult.to_stop.stop_name}
          </p>
        </div>
        <Link to="/" className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">
          Nowe wyszukiwanie
        </Link>
      </div>

      <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold">Najblizsze odjazdy</h2>
            <p className="text-sm text-slate-500">Wybierz kafelek, aby przelaczyc wariant trasy na mapie.</p>
            {historyMessage ? <p className="mt-2 text-xs text-[#1754d8]">{historyMessage}</p> : null}
            {!isAuthenticated ? (
              <p className="mt-2 text-xs text-slate-500">
                <Link to="/sign-in" className="font-medium text-[#1754d8] hover:underline">
                  Zaloguj sie
                </Link>
                , aby zapisywac trasy w historii.
              </p>
            ) : null}
          </div>
          <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
            {planResult.from_stop.stop_name} -&gt; {planResult.to_stop.stop_name}
          </span>
        </div>

        <div className="grid max-h-[16rem] gap-3 overflow-y-auto pr-1 md:grid-cols-2 xl:grid-cols-3">
          {departureOptions.map((departure, index) => (
            <DepartureCard
              key={`${departure.originalIndex}-${departure.lineLabel}-${departure.exactTime}`}
              departure={departure}
              highlighted={index === 0}
              selected={departure.originalIndex === selectedIndex}
              onSelect={() => void handleSelectOption(departure.originalIndex)}
            />
          ))}
        </div>
      </section>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)] lg:items-start">
        <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
          {isLoading ? <div className="p-4"><Spinner label="Ladowanie mapy..." /></div> : null}
          {!isLoading && error ? <div className="p-4"><Alert>{error}</Alert></div> : null}
          {!isLoading && !error ? (
            <RouteResultMap planResult={mapResult} tripDetails={tripDetails} transit={selectedTransit} />
          ) : null}
        </div>

        <aside className="space-y-4">
          <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 className="text-lg font-semibold">Podsumowanie</h2>
            <dl className="mt-4 space-y-3 text-sm">
              <div>
                <dt className="text-slate-500">Przystanek poczatkowy</dt>
                <dd className="font-medium">{planResult.from_stop.stop_name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Przystanek docelowy</dt>
                <dd className="font-medium">{planResult.to_stop.stop_name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Odjazd</dt>
                <dd className="font-medium">{formatLocaleDateTime24(planResult.depart_at)}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Maks. przesiadki</dt>
                <dd className="font-medium">{planResult.max_transfers}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Czas przejazdu</dt>
                <dd className="font-medium">{travelDuration}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Najblizszy odjazd</dt>
                <dd className="font-medium">{departureOptions[0]?.relativeLabel ?? '-'}</dd>
              </div>
              {walkingSummary ? (
                <div>
                  <dt className="text-slate-500">Pieszo</dt>
                  <dd className="font-medium">{walkingSummary}</dd>
                </div>
              ) : null}
            </dl>
          </div>

        </aside>
      </div>

      <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold">Komunikacja</h2>
            <p className="mt-1 text-sm text-slate-500">Podluzne podsumowanie wybranego wariantu przejazdu.</p>
          </div>
          <div className="flex flex-wrap items-center gap-3 text-sm">
            <span className="rounded-xl bg-red-600 px-4 py-3 text-lg font-bold text-white">
              {lineLabel(selectedTransit)}
            </span>
            <span className="font-medium text-slate-900">{selectedRouteName ?? 'Wybrana linia'}</span>
            <span className="text-slate-400">|</span>
            <span className="text-slate-600">
              {formatGtfsTimeLabel(firstDepartureTime(selectedTransit))} -&gt; {formatGtfsTimeLabel(lastArrivalTime(selectedTransit))}
            </span>
            <span className="text-slate-400">|</span>
            <span className="font-medium text-emerald-700">Czas przejazdu: {travelDuration}</span>
          </div>
        </div>
      </section>
    </section>
  )
}

function DepartureCard({
  departure,
  highlighted,
  selected,
  onSelect,
}: {
  departure: DepartureOption
  highlighted: boolean
  selected: boolean
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`flex items-center gap-4 rounded-2xl border p-4 m-1 text-left transition ${
        highlighted
          ? 'border-red-200 bg-red-50 shadow-md ring-2 ring-red-500/20'
          : 'border-slate-200 bg-slate-50 hover:bg-slate-100'
      } ${selected ? 'outline outline-2 outline-red-500' : ''}`}
    >
      <span
        className={`flex h-16 w-16 shrink-0 items-center justify-center rounded-xl text-xl font-black text-white shadow-sm ${
          highlighted ? 'bg-red-600' : 'bg-red-500'
        }`}
      >
        {departure.lineLabel}
      </span>
      <span className="min-w-0">
        <span className={`block text-sm font-semibold ${highlighted ? 'text-red-700' : 'text-slate-700'}`}>
          {departure.relativeLabel}
        </span>
        <span className="mt-1 block text-2xl font-bold tracking-tight text-slate-950">{departure.exactTime}</span>
        <span className="mt-1 block truncate text-xs text-slate-500">{departure.routeName ?? 'trasa bez nazwy'}</span>
        <span className="mt-1 block text-xs font-medium text-emerald-700">Czas: {departure.durationLabel}</span>
      </span>
    </button>
  )
}
