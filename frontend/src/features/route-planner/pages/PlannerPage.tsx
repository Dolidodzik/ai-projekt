import { useState } from 'react'
import type { LatLngExpression } from 'leaflet'
import { useNavigate } from 'react-router-dom'
import { Alert } from '../../../components/ui/Alert'
import { Spinner } from '../../../components/ui/Spinner'
import { planRoute } from '../api'
import { EndpointField } from '../components/EndpointField'
import { MapPickerModal } from '../components/MapPickerModal'
import { savePlanResult } from '../storage'
import {
  createEmptyEndpoint,
  endpointToParams,
  formatDateInputValue,
  formatTimeInputValue,
  validateEndpoint,
  validateTimeInput24,
} from '../utils'

type MapTarget = 'from' | 'to' | null

export function PlannerPage() {
  const navigate = useNavigate()
  const [from, setFrom] = useState(createEmptyEndpoint)
  const [to, setTo] = useState(createEmptyEndpoint)
  const [maxTransfers, setMaxTransfers] = useState(3)
  const [departDate, setDepartDate] = useState(() => formatDateInputValue(new Date()))
  const [departTime, setDepartTime] = useState(() => formatTimeInputValue(new Date()))
  const [mapTarget, setMapTarget] = useState<MapTarget>(null)
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const activeEndpoint = mapTarget === 'from' ? from : mapTarget === 'to' ? to : null
  const activePosition: LatLngExpression | null =
    activeEndpoint && activeEndpoint.lat !== null && activeEndpoint.lon !== null
      ? [activeEndpoint.lat, activeEndpoint.lon]
      : null

  const submit = async () => {
    const fromError = validateEndpoint(from)
    const toError = validateEndpoint(to)
    const timeError = validateTimeInput24(departTime)
    if (fromError || toError || timeError) {
      setError(fromError ?? toError ?? timeError)
      return
    }

    setIsSubmitting(true)
    setError(null)

    try {
      const result = await planRoute({
        ...endpointToParams(from, 'from'),
        ...endpointToParams(to, 'to'),
        max_transfers: maxTransfers,
        depart_at: new Date(`${departDate}T${departTime}`).toISOString(),
      })
      savePlanResult(result)
      navigate('/results', { state: { planResult: result } })
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'Route search failed.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <section className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Plan a route</h1>
        <p className="mt-2 text-slate-600">Search by address or map point.</p>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <EndpointField label="From" value={from} onChange={setFrom} onOpenMap={() => setMapTarget('from')} />
        <EndpointField label="To" value={to} onChange={setTo} onOpenMap={() => setMapTarget('to')} />
      </div>

      <div className="grid gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:grid-cols-2">
        <div className="block text-sm">
          <span className="mb-2 block font-medium text-slate-700">Departure time</span>
          <div className="grid gap-2 sm:grid-cols-2">
            <input
              type="date"
              value={departDate}
              onChange={(event) => setDepartDate(event.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
            />
            <input
              type="text"
              inputMode="numeric"
              value={departTime}
              onChange={(event) => setDepartTime(event.target.value)}
              placeholder="HH:mm"
              pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
            />
          </div>
        </div>
        <label className="block text-sm">
          <span className="mb-2 block font-medium text-slate-700">Max transfers</span>
          <select
            value={maxTransfers}
            onChange={(event) => setMaxTransfers(Number(event.target.value))}
            className="w-full rounded-lg border border-slate-300 px-3 py-2"
          >
            {[0, 1, 2, 3].map((value) => (
              <option key={value} value={value}>
                {value}
              </option>
            ))}
          </select>
        </label>
      </div>

      {error ? <Alert>{error}</Alert> : null}
      {isSubmitting ? <Spinner label="Searching routes..." /> : null}

      <button
        type="button"
        onClick={() => void submit()}
        disabled={isSubmitting}
        className="rounded-lg bg-emerald-600 px-5 py-3 text-sm font-medium text-white disabled:opacity-50"
      >
        Search route
      </button>

      <MapPickerModal
        open={mapTarget !== null}
        initialPosition={activePosition}
        onClose={() => setMapTarget(null)}
        onConfirm={(lat, lon) => {
          if (mapTarget === 'from') {
            setFrom({ mode: 'map', label: 'Map point', lat, lon, stopId: null })
          }
          if (mapTarget === 'to') {
            setTo({ mode: 'map', label: 'Map point', lat, lon, stopId: null })
          }
          setMapTarget(null)
        }}
      />
    </section>
  )
}
