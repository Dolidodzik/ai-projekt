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
  transitSummary,
} from '../utils'

interface ResultsLocationState {
  planResult?: PlanRouteResult
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
          setError('Failed to load route geometry.')
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

  return (
    <section className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Route result</h1>
          <p className="mt-2 text-slate-600">
            {planResult.from_stop.stop_name} -&gt; {planResult.to_stop.stop_name}
          </p>
        </div>
        <Link to="/" className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">
          New search
        </Link>
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)] lg:items-start">
        <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
          {isLoading ? <div className="p-4"><Spinner label="Loading map..." /></div> : null}
          {!isLoading && error ? <div className="p-4"><Alert>{error}</Alert></div> : null}
          {!isLoading && !error ? (
            <RouteResultMap planResult={mapResult} tripDetails={tripDetails} transit={selectedTransit} />
          ) : null}
        </div>

        <aside className="space-y-4">
          <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 className="text-lg font-semibold">Next departures</h2>
            {historyMessage ? <p className="mt-2 text-xs text-emerald-700">{historyMessage}</p> : null}
            {!isAuthenticated ? (
              <p className="mt-2 text-xs text-slate-500">
                <Link to="/sign-in" className="font-medium text-[#1754d8] hover:underline">
                  Zaloguj sie
                </Link>
                , aby zapisywac trasy w historii.
              </p>
            ) : null}
            <div className="mt-4 space-y-2">
              {transitOptions.map((option, index) => (
                <button
                  key={`${option.type}-${index}-${transitSummary(option, planResult.depart_at)}`}
                  type="button"
                  onClick={() => void handleSelectOption(index)}
                  className={`w-full rounded-xl border px-4 py-3 text-left text-sm ${
                    index === selectedIndex ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white hover:bg-slate-50'
                  }`}
                >
                  {transitSummary(option, planResult.depart_at)}
                </button>
              ))}
            </div>
          </div>

          <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 className="text-lg font-semibold">Summary</h2>
            <dl className="mt-4 space-y-3 text-sm">
              <div>
                <dt className="text-slate-500">Origin stop</dt>
                <dd className="font-medium">{planResult.from_stop.stop_name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Destination stop</dt>
                <dd className="font-medium">{planResult.to_stop.stop_name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Departure</dt>
                <dd className="font-medium">{formatLocaleDateTime24(planResult.depart_at)}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Max transfers</dt>
                <dd className="font-medium">{planResult.max_transfers}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Czas przejazdu</dt>
                <dd className="font-medium">{travelDuration}</dd>
              </div>
              {walkingSummary ? (
                <div>
                  <dt className="text-slate-500">Walking</dt>
                  <dd className="font-medium">{walkingSummary}</dd>
                </div>
              ) : null}
            </dl>
          </div>

          <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 className="text-lg font-semibold">Transit</h2>
            {selectedTransit.type === 'direct' ? (
              <div className="mt-4 rounded-xl bg-slate-50 p-4 text-sm">
                <p className="font-medium">Line {selectedTransit.route.short_name}</p>
                <p className="mt-1 text-slate-600">{selectedTransit.route.long_name}</p>
                <p className="mt-3">
                  {selectedTransit.from_departure_time} -&gt; {selectedTransit.to_arrival_time}
                </p>
                <p className="mt-2 font-medium text-emerald-700">Czas przejazdu: {travelDuration}</p>
              </div>
            ) : (
              <div className="mt-4 space-y-3">
                <p className="rounded-xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                  Czas przejazdu: {travelDuration}
                </p>
                {selectedTransit.legs.map((leg, index) => (
                  <div key={`${leg.trip_pk}-${index}`} className="rounded-xl bg-slate-50 p-4 text-sm">
                    <p className="font-medium">
                      Leg {index + 1}: line {leg.route.short_name}
                    </p>
                    <p className="mt-1 text-slate-600">{leg.route.long_name}</p>
                    <p className="mt-3">
                      {leg.from_departure_time} -&gt; {leg.to_arrival_time}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </div>
        </aside>
      </div>
    </section>
  )
}
