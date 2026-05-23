import { useEffect, useState } from 'react'
import type { GeocodingResult } from '../../../lib/geocoding'
import { searchAddresses } from '../../../lib/geocoding'
import type { EndpointMode, PlannerEndpoint } from '../types'

interface EndpointFieldProps {
  label: string
  value: PlannerEndpoint
  onChange: (value: PlannerEndpoint) => void
  onOpenMap: () => void
}

const emptyEndpoint = (mode: EndpointMode): PlannerEndpoint => ({
  mode,
  label: '',
  lat: null,
  lon: null,
  stopId: null,
})

export function EndpointField({ label, value, onChange, onOpenMap }: EndpointFieldProps) {
  const [suggestions, setSuggestions] = useState<GeocodingResult[]>([])
  const [isSearching, setIsSearching] = useState(false)
  const canSearch = value.mode === 'address' && value.label.trim().length >= 3

  useEffect(() => {
    if (!canSearch) {
      return
    }

    let cancelled = false
    const timeout = window.setTimeout(async () => {
      setIsSearching(true)
      const results = await searchAddresses(value.label)
      if (!cancelled) {
        setSuggestions(results)
        setIsSearching(false)
      }
    }, 350)

    return () => {
      cancelled = true
      window.clearTimeout(timeout)
    }
  }, [canSearch, value.label])

  const visibleSuggestions = canSearch ? suggestions : []

  return (
    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h3 className="text-sm font-semibold">{label}</h3>
        <div className="flex rounded-lg border border-slate-200 bg-white p-1">
          <button
            type="button"
            onClick={() => onChange(emptyEndpoint('address'))}
            className={`rounded-md px-3 py-1 text-sm ${value.mode === 'address' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600'}`}
          >
            Adres
          </button>
          <button
            type="button"
            onClick={() => onChange(emptyEndpoint('map'))}
            className={`rounded-md px-3 py-1 text-sm ${value.mode === 'map' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600'}`}
          >
            Mapa
          </button>
        </div>
      </div>

      {value.mode === 'address' ? (
        <div className="space-y-2">
          <input
            value={value.label}
            onChange={(event) => onChange({ ...value, label: event.target.value, lat: null, lon: null, stopId: null })}
            placeholder="Wyszukaj adres"
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
          />
          {isSearching ? <p className="text-sm text-slate-500">Szukam...</p> : null}
          {visibleSuggestions.length > 0 ? (
            <ul className="max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white">
              {visibleSuggestions.map((item) => (
                <li key={`${item.lat}-${item.lon}-${item.label}`}>
                  <button
                    type="button"
                    onClick={() =>
                      onChange({
                        ...value,
                        label: item.label,
                        lat: item.lat,
                        lon: item.lon,
                        stopId: null,
                      })
                    }
                    className="w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                  >
                    {item.label}
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : (
        <div className="space-y-3">
          <button
            type="button"
            onClick={onOpenMap}
            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700"
          >
            Wybierz na mapie
          </button>
          {value.lat !== null && value.lon !== null ? (
            <p className="text-sm text-slate-600">
              {value.lat.toFixed(5)}, {value.lon.toFixed(5)}
            </p>
          ) : (
            <p className="text-sm text-slate-500">Nie wybrano punktu.</p>
          )}
        </div>
      )}
    </div>
  )
}
